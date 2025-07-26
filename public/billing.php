<?php
// DEBUG: Show POST data for troubleshooting
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo '<pre style="background: #fff; color: #000; z-index: 99999; position: absolute; top: 0; left: 0; width: 100vw;">';
    print_r($_POST);
    echo '</pre>';
}
require_once __DIR__ . '/../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /possystem/index.php');
    exit;
}

// Fetch restaurant name for navbar
$rest = $pdo->query("SELECT name FROM restaurant_details LIMIT 1")->fetch();
$restaurant_name = $rest ? $rest['name'] : 'Cafe POS';

// Fetch only booked tables
$booked_tables = $pdo->query("SELECT * FROM tables WHERE status='occupied' ORDER BY table_number")->fetchAll();
$menu_items = $pdo->query("SELECT mi.*, mc.name as category FROM menu_items mi JOIN menu_categories mc ON mi.category_id = mc.id ORDER BY mc.name, mi.name")->fetchAll();
$customers = $pdo->query("SELECT * FROM customers ORDER BY name")->fetchAll();

// Handle add item, save bill, checkout, and switch table
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Remove entire bill and unbook table
    if (isset($_POST['remove_bill'])) {
        $bill_id = $_POST['bill_id'];
        // Get table id before deleting bill
        $stmt = $pdo->prepare("SELECT table_id FROM bills WHERE id=?");
        $stmt->execute([$bill_id]);
        $table_id = $stmt->fetchColumn();
        // Delete bill items
        $pdo->prepare("DELETE FROM bill_items WHERE bill_id=?")->execute([$bill_id]);
        // Delete bill
        $pdo->prepare("DELETE FROM bills WHERE id=?")->execute([$bill_id]);
        // Set table to available
        if ($table_id) {
            $pdo->prepare("UPDATE tables SET status='available' WHERE id=?")->execute([$table_id]);
        }
        header('Location: billing.php');
        exit;
    }
if (isset($_POST['add_item']) || isset($_POST['add_item_customer'])) {
    // For table billing, use table_id; for customer billing, use bill_id
    if (isset($_POST['add_item'])) {
        $table_id = $_POST['table_id'];
        // Find or create open bill for this table
        $stmt = $pdo->prepare("SELECT id FROM bills WHERE table_id=? AND status='open'");
        $stmt->execute([$table_id]);
        $bill = $stmt->fetch();
        if (!$bill) {
            $pdo->prepare("INSERT INTO bills (table_id, status, created_at) VALUES (?, 'open', NOW())")->execute([$table_id]);
            $bill_id = $pdo->lastInsertId();
        } else {
            $bill_id = $bill['id'];
        }
    } else {
        // Customer billing: bill_id is provided
        $bill_id = $_POST['bill_id'];
    }
    $menu_item_id = $_POST['menu_item_id'];
    $quantity = max(1, (int)$_POST['quantity']);
    // Get item price
    $stmt = $pdo->prepare("SELECT price FROM menu_items WHERE id=?");
    $stmt->execute([$menu_item_id]);
    $price = $stmt->fetch()['price'];
    // Add item to bill
    $pdo->prepare("INSERT INTO bill_items (bill_id, menu_item_id, quantity, price) VALUES (?, ?, ?, ?)")->execute([$bill_id, $menu_item_id, $quantity, $price]);
    // Update bill total
    $stmt = $pdo->prepare("SELECT SUM(quantity*price) as total FROM bill_items WHERE bill_id=?");
    $stmt->execute([$bill_id]);
    $total = $stmt->fetch()['total'];
    $pdo->prepare("UPDATE bills SET total_amount=? WHERE id=?")->execute([$total, $bill_id]);
}
    if (isset($_POST['checkout'])) {
        $bill_id = $_POST['bill_id'];
        $payment_type = $_POST['payment_type'];
        $customer_id = isset($_POST['customer_id']) ? $_POST['customer_id'] : null;
        // Always recalculate and update bill total before closing
        $stmt = $pdo->prepare("SELECT SUM(quantity*price) as total FROM bill_items WHERE bill_id=?");
        $stmt->execute([$bill_id]);
        $total = $stmt->fetch()['total'] ?? 0;
        $pdo->prepare("UPDATE bills SET total_amount=? WHERE id=?")->execute([$total, $bill_id]);
        if ($payment_type === 'credit' && $customer_id) {
            // Use the recalculated total
            $bill_total = $total;
            // Update pending_credit for customer
            $stmt = $pdo->prepare("UPDATE customers SET pending_credit = pending_credit + ? WHERE id=?");
            $stmt->execute([$bill_total, $customer_id]);
            // If credit, check credit limit
            $stmt = $pdo->prepare("SELECT credit_limit FROM customers WHERE id=?");
            $stmt->execute([$customer_id]);
            $limit = $stmt->fetchColumn();
            $stmt = $pdo->prepare("SELECT IFNULL(SUM(total_amount),0) FROM bills WHERE customer_id=? AND status='closed' AND payment_type='credit'");
            $stmt->execute([$customer_id]);
            $used = $stmt->fetchColumn();
            if ($used + $bill_total > $limit) {
                $error = "Credit limit exceeded!"; // Only show warning
            }
            $pdo->prepare("UPDATE bills SET status='closed', closed_at=NOW(), payment_type=?, customer_id=? WHERE id=?")->execute([$payment_type, $customer_id, $bill_id]);
            $pdo->prepare("UPDATE tables SET status='available' WHERE id=(SELECT table_id FROM bills WHERE id=?)")->execute([$bill_id]);
        } else {
            $pdo->prepare("UPDATE bills SET status='closed', closed_at=NOW(), payment_type=?, customer_id=NULL WHERE id=?")->execute([$payment_type, $bill_id]);
            $pdo->prepare("UPDATE tables SET status='available' WHERE id=(SELECT table_id FROM bills WHERE id=?)")->execute([$bill_id]);
        }
    }
    if (isset($_POST['split_checkout'])) {
        $bill_id = $_POST['bill_id'];
        $split_amounts = isset($_POST['split_amount']) ? $_POST['split_amount'] : [];
        $split_types = isset($_POST['split_type']) ? $_POST['split_type'] : [];
        $split_customers = isset($_POST['split_customer']) ? $_POST['split_customer'] : [];
        // Validate split total
        $stmt = $pdo->prepare("SELECT total_amount, table_id FROM bills WHERE id=?");
        $stmt->execute([$bill_id]);
        $bill_row = $stmt->fetch();
        $bill_total = floatval($bill_row['total_amount']);
        $table_id = $bill_row['table_id'];
        $split_total = 0;
        foreach ($split_amounts as $idx => $amt) {
            $split_total += floatval($amt);
        }
        if (abs($split_total - $bill_total) > 0.01) {
            // Invalid split, do not process
            header('Location: billing.php');
            exit;
        }
        // Fetch all bill items from the original bill
        $stmt = $pdo->prepare("SELECT menu_item_id, quantity, price FROM bill_items WHERE bill_id=?");
        $stmt->execute([$bill_id]);
        $bill_items = $stmt->fetchAll();
        // For each split, create a new closed bill and copy items
        foreach ($split_amounts as $idx => $amt) {
            $type = $split_types[$idx];
            $amount = floatval($amt);
            $customer_id = ($type === 'credit' && isset($split_customers[$idx]) && $split_customers[$idx]) ? $split_customers[$idx] : null;
            // Insert new closed bill for this split (each with only its split amount)
            $pdo->prepare("INSERT INTO bills (table_id, status, created_at, closed_at, payment_type, total_amount, customer_id) VALUES (?, 'closed', NOW(), NOW(), ?, ?, ?)")
                ->execute([$table_id, $type, $amount, $customer_id]);
            $new_bill_id = $pdo->lastInsertId();
            // Copy all items to new bill (each split bill gets the same items, but the total_amount is only the split amount)
            foreach ($bill_items as $item) {
                $pdo->prepare("INSERT INTO bill_items (bill_id, menu_item_id, quantity, price) VALUES (?, ?, ?, ?)")
                    ->execute([$new_bill_id, $item['menu_item_id'], $item['quantity'], $item['price']]);
            }
            // If credit, update customer pending_credit
            if ($type === 'credit' && $customer_id) {
                $pdo->prepare("UPDATE customers SET pending_credit = pending_credit + ? WHERE id=?")
                    ->execute([$amount, $customer_id]);
            }
        }
        // Delete original bill and its items
        $pdo->prepare("DELETE FROM bill_items WHERE bill_id=?")->execute([$bill_id]);
        $pdo->prepare("DELETE FROM bills WHERE id=?")->execute([$bill_id]);
        // Set table to available
        $pdo->prepare("UPDATE tables SET status='available' WHERE id=?")->execute([$table_id]);
        header('Location: billing.php');
        exit;
    }
    if (isset($_POST['remove_bill_item'])) {
        $bill_item_id = $_POST['bill_item_id'];
        $pdo->prepare("DELETE FROM bill_items WHERE id=?")->execute([$bill_item_id]);
        // Optionally update bill total
        $bill_id = $_POST['bill_id'];
        $stmt = $pdo->prepare("SELECT SUM(quantity*price) as total FROM bill_items WHERE bill_id=?");
        $stmt->execute([$bill_id]);
        $total = $stmt->fetch()['total'] ?? 0;
        $pdo->prepare("UPDATE bills SET total_amount=? WHERE id=?")->execute([$total, $bill_id]);
        header('Location: billing.php');
        exit;
    }
    // Switch table logic
    if (isset($_POST['switch_table']) && isset($_POST['bill_id']) && isset($_POST['new_table_id'])) {
        $bill_id = $_POST['bill_id'];
        $new_table_id = $_POST['new_table_id'];
        // Get current table id
        $stmt = $pdo->prepare("SELECT table_id FROM bills WHERE id=?");
        $stmt->execute([$bill_id]);
        $old_table_id = $stmt->fetchColumn();
        // Update bill's table_id
        $pdo->prepare("UPDATE bills SET table_id=? WHERE id=?")->execute([$new_table_id, $bill_id]);
        // Set old table to available
        $pdo->prepare("UPDATE tables SET status='available' WHERE id=?")->execute([$old_table_id]);
        // Set new table to occupied
        $pdo->prepare("UPDATE tables SET status='occupied' WHERE id=?")->execute([$new_table_id]);
        header('Location: billing.php');
        exit;
    }
    header('Location: billing.php');
    exit;
}

// Fetch open bills for booked tables
$bills = [];
foreach ($booked_tables as $table) {
    $stmt = $pdo->prepare("SELECT * FROM bills WHERE table_id=? AND status='open'");
    $stmt->execute([$table['id']]);
    $bills[$table['id']] = $stmt->fetch();
}

?>
<!DOCTYPE html>
<html lang='en'>
<head>
<script>
// Make PHP customers array available to JS for split payment
var customers = <?php echo json_encode(array_map(function($c) {
    return [
        'id' => $c['id'],
        'name' => $c['name'],
        'phone' => $c['phone'],
        'credit_limit' => isset($c['credit_limit']) ? $c['credit_limit'] : 0
    ];
}, $customers)); ?>;
</script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Table Billing</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='/possystem/public/style.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg mb-4">
      <div class="container-fluid">
        <a class="navbar-brand" href="/possystem/public/dashboard.php"><?php echo htmlspecialchars($restaurant_name); ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="#navbarNav" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
          <ul class="navbar-nav ms-auto">
            <li class="nav-item"><a class="nav-link" href="/possystem/public/booketable.php">Table Booking</a></li>
            <li class="nav-item"><a class="nav-link" href="/possystem/public/billing.php">Table Billing</a></li>
            <li class="nav-item"><a class="nav-link" href="/possystem/public/printbill.php">Print Bill</a></li>
            <li class="nav-item"><a class="nav-link" href="/possystem/public/logout.php">Logout</a></li>
          </ul>
        </div>
      </div>
    </nav>

<div class='container mt-4'>
    <h2>Billing for Booked Tables</h2>
    <div class='row'>
        <?php foreach ($booked_tables as $table): ?>
            <div class='col-md-3 mb-4'>
                <div class='card h-100'>
                    <div class='card-header bg-dark text-white'>Table <?php echo htmlspecialchars($table['table_number']); ?></div>
                    <div class='card-body d-flex flex-column justify-content-center align-items-center'>
                        <button class='btn btn-primary' data-bs-toggle='modal' data-bs-target='#tableBillingModal<?php echo $table['id']; ?>'>Bill / Add Items</button>
                    </div>
                </div>
            </div>

            <!-- Modal for Table Billing -->
            <div class='modal fade' id='tableBillingModal<?php echo $table['id']; ?>' tabindex='-1' aria-labelledby='tableBillingModalLabel<?php echo $table['id']; ?>' aria-hidden='true'>
              <div class='modal-dialog modal-lg modal-dialog-centered'>
                <div class='modal-content'>
                  <div class='modal-header'>
                    <h5 class='modal-title' id='tableBillingModalLabel<?php echo $table['id']; ?>'>Table Billing: <?php echo htmlspecialchars($table['table_number']); ?></h5>
                    <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                  </div>
                  <div class='modal-body'>
                    <form method='post' class='mb-3'>
                        <input type='hidden' name='table_id' value='<?php echo $table['id']; ?>'>
                        <input type='hidden' name='add_item' value='1'>
                        <div class='row g-2 align-items-center'>
                            <div class='col'>
                                <input type="text" class="form-control mb-1 menu-search-input" placeholder="Search item...">
                                <select name='menu_item_id' class='form-select menu-select-table' data-table-id='<?php echo $table['id']; ?>' required>
                                    <option value=''>Select Item</option>
                                    <?php foreach ($menu_items as $item): ?>
                                        <option value='<?php echo $item['id']; ?>'><?php echo htmlspecialchars($item['name']); ?> (<?php echo number_format($item['price'],2); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class='col'>
                                <input type='number' name='quantity' class='form-control' placeholder='Qty' min='1' value='1' required>
                            </div>
                            <div class='col'>
                                <button type='submit' class='btn btn-success'>Add</button>
                            </div>
                        </div>
                    </form>

                    <h6>Bill Items:</h6>
                    <ul class='list-group mb-2'>
                        <?php
                        $stmt = $pdo->prepare("SELECT bi.*, mi.name FROM bill_items bi JOIN menu_items mi ON bi.menu_item_id = mi.id WHERE bi.bill_id=(SELECT id FROM bills WHERE table_id=? AND status='open')");
                        $stmt->execute([$table['id']]);
                        $bill_items = $stmt->fetchAll();
                        foreach ($bill_items as $bi): ?>
                            <li class='list-group-item d-flex justify-content-between align-items-center'><?php echo htmlspecialchars($bi['name']); ?> x <?php echo $bi['quantity']; ?><span>NPR <?php echo number_format($bi['price']*$bi['quantity'],2); ?></span>
                                <form method='post' style='display:inline;'>
                                    <input type='hidden' name='bill_item_id' value='<?php echo $bi['id']; ?>'>
                                    <input type='hidden' name='bill_id' value='<?php echo $bi['bill_id']; ?>'>
                                    <button type='submit' name='remove_bill_item' class='btn btn-sm btn-outline-danger ms-2'>Remove</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php
                    $stmt = $pdo->prepare("SELECT total_amount FROM bills WHERE table_id=? AND status='open'");
                    $stmt->execute([$table['id']]);
                    $total = $stmt->fetchColumn() ?: 0;
                    ?>
                    <strong>Total: NPR <?php echo number_format($total, 2); ?></strong>
                    <!-- Remove Bill button -->
                    <?php
                    $stmt = $pdo->prepare("SELECT id FROM bills WHERE table_id=? AND status='open'");
                    $stmt->execute([$table['id']]);
                    $bill_id = $stmt->fetchColumn();
                    if ($bill_id): ?>
                    <form method='post' class='mt-2 d-inline'>
                        <input type='hidden' name='bill_id' value='<?php echo $bill_id; ?>'>
                        <button type='submit' name='remove_bill' class='btn btn-outline-danger btn-sm me-2'><i class='bi bi-x-circle'></i> Remove Bill</button>
                    </form>
                    <!-- Checkout Form for Table Bill -->
                    <form method='post' class='mt-3' id='direct-payment-form-table-<?php echo $bill_id; ?>'>
                        <input type='hidden' name='bill_id' value='<?php echo $bill_id; ?>'>
                        <div class='mb-2'><label class='form-label'>Direct Payment:</label></div>
                        <div class='row g-2 mb-2'>
                            <div class='col'>
                                <select name='payment_type' class='form-select' required>
                                    <option value='cash'>Cash</option>
                                    <option value='card'>Card</option>
                                    <option value='credit'>Credit</option>
                                </select>
                            </div>
                            <div class='col customer-select-col' style='display:none;'>
                                <select name='customer_id' class='form-select'>
                                    <option value=''>Select Customer</option>
                                    <?php foreach ($customers as $cust): ?>
                                        <option value='<?php echo $cust['id']; ?>'><?php echo htmlspecialchars($cust['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class='col'>
                                <button type='submit' name='checkout' class='btn btn-primary'>Checkout</button>
                            </div>
                        </div>
                    </form>
                    <!-- Split Payment Section for Table Bill -->
                    <form method='post' class='mt-3' id='split-payment-form-table-<?php echo $bill_id; ?>'>
                        <input type='hidden' name='bill_id' value='<?php echo $bill_id; ?>'>
                        <div class='mb-2'><label class='form-label'>Split Payment:</label></div>
                        <div id='split-payments-container-table-<?php echo $bill_id; ?>'></div>
                        <button type='button' class='btn btn-outline-secondary btn-sm add-split-btn-table' data-bill-id='<?php echo $bill_id; ?>'>Add Split</button>
                        <button type='submit' name='split_checkout' class='btn btn-success btn-sm ms-2'>Split Checkout</button>
                    </form>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php
    // Fetch open bills for booked customers (no table assigned)
    $booked_customers_bills = $pdo->query("SELECT b.*, c.name as customer_name, c.phone FROM bills b JOIN customers c ON b.customer_id = c.id WHERE b.status = 'open' AND b.table_id IS NULL")->fetchAll();
    ?>
    <?php if (count($booked_customers_bills) > 0): ?>
    <h2 class='mt-5'>Billing for Booked Customers</h2>
    <div class='row'>
        <?php foreach ($booked_customers_bills as $bill): ?>
            <div class='col-md-3 mb-4'>
                <div class='card h-100'>
                    <div class='card-header bg-info text-dark'>Customer: <?php echo htmlspecialchars($bill['customer_name']); ?></div>
                    <div class='card-body d-flex flex-column justify-content-center align-items-center'>
                        <div class='mb-2'><?php echo htmlspecialchars($bill['phone']); ?></div>
                        <button class='btn btn-primary' data-bs-toggle='modal' data-bs-target='#customerBillingModal<?php echo $bill['id']; ?>'>Bill / Add Items</button>
                    </div>
                </div>
            </div>

            <!-- Modal for Booked Customer Billing -->
            <div class='modal fade' id='customerBillingModal<?php echo $bill['id']; ?>' tabindex='-1' aria-labelledby='customerBillingModalLabel<?php echo $bill['id']; ?>' aria-hidden='true'>
              <div class='modal-dialog modal-lg modal-dialog-centered'>
                <div class='modal-content'>
                  <div class='modal-header'>
                    <h5 class='modal-title' id='customerBillingModalLabel<?php echo $bill['id']; ?>'>Customer Billing: <?php echo htmlspecialchars($bill['customer_name']); ?></h5>
                    <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                  </div>
                  <div class='modal-body'>
                    <form method='post' class='mb-3'>
                        <input type='hidden' name='bill_id' value='<?php echo $bill['id']; ?>'>
                        <div class='row g-2 align-items-center'>
                            <div class='col'>
                                <input type="text" class="form-control mb-1 menu-search-input" placeholder="Search item...">
                                <select name='menu_item_id' class='form-select menu-select-customer' data-bill-id='<?php echo $bill['id']; ?>' required>
                                    <option value=''>Select Item</option>
                                    <?php foreach ($menu_items as $item): ?>
                                        <option value='<?php echo $item['id']; ?>'><?php echo htmlspecialchars($item['name']); ?> (<?php echo number_format($item['price'],2); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class='col'>
                                <input type='number' name='quantity' class='form-control' placeholder='Qty' min='1' value='1' required>
                            </div>
                            <div class='col'>
                                <button type='submit' name='add_item_customer' class='btn btn-success'>Add</button>
                            </div>
                        </div>
                    </form>

                    <h6>Bill Items:</h6>
                    <ul class='list-group mb-2'>
                        <?php
                        $stmt = $pdo->prepare("SELECT bi.*, mi.name FROM bill_items bi JOIN menu_items mi ON bi.menu_item_id = mi.id WHERE bi.bill_id=?");
                        $stmt->execute([$bill['id']]);
                        $bill_items = $stmt->fetchAll();
                        foreach ($bill_items as $bi): ?>
                            <li class='list-group-item d-flex justify-content-between align-items-center'><?php echo htmlspecialchars($bi['name']); ?> x <?php echo $bi['quantity']; ?><span>NPR <?php echo number_format($bi['price']*$bi['quantity'],2); ?></span>
                                <form method='post' style='display:inline;'>
                                    <input type='hidden' name='bill_item_id' value='<?php echo $bi['id']; ?>'>
                                    <input type='hidden' name='bill_id' value='<?php echo $bi['bill_id']; ?>'>
                                    <button type='submit' name='remove_bill_item' class='btn btn-sm btn-outline-danger ms-2'>Remove</button>
                                </form>
// --- Split Payment for Customer and Table Billing Modals ---
                        <?php endforeach; ?>
                    </ul>
                    <strong>Total: NPR <?php echo number_format($bill['total_amount'] ?? 0, 2); ?></strong>
                    <!-- Remove Bill button -->
                    <form method='post' class='mt-2 d-inline'>
                        <input type='hidden' name='bill_id' value='<?php echo $bill['id']; ?>'>
                        <button type='submit' name='remove_bill' class='btn btn-outline-danger btn-sm me-2'><i class='bi bi-x-circle'></i> Remove Bill</button>
                    </form>
                    <!-- Direct Payment for customer bill -->
                    <form method='post' class='mt-3' id='direct-payment-form-customer-<?php echo $bill['id']; ?>'>
                        <input type='hidden' name='bill_id' value='<?php echo $bill['id']; ?>'>
                        <div class='mb-2'><label class='form-label'>Direct Payment:</label></div>
                        <div class='row g-2 mb-2'>
                            <div class='col-4'><select name='payment_type' class='form-select direct-payment-type' required>
                                <option value='online'>Online</option>
                                <option value='offline'>Offline</option>
                                <option value='credit'>Credit</option>
                            </select></div>
                            <div class='col-5 direct-customer-select' style='display:none;'>
                                <input type='hidden' name='customer_id' value='<?php echo $bill['customer_id']; ?>'>
                                <span class='form-control-plaintext'><?php echo htmlspecialchars($bill['customer_name']); ?></span>
                            </div>
                            <div class='col-3'><button type='submit' name='checkout' class='btn btn-success'>Checkout (Direct)</button></div>
                        </div>
                        <div class='direct-credit-warning'></div>
                    </form>
                    <!-- Split Payment Section -->
                    <form method='post' class='mt-3' id='split-payment-form-customer-<?php echo $bill['id']; ?>'><input type='hidden' name='bill_id' value='<?php echo $bill['id']; ?>'>
                        <div class='mb-2'><label class='form-label'>Split Payment:</label></div>
                        <div id='split-payments-container-customer-<?php echo $bill['id']; ?>'></div>
                        <button type='button' class='btn btn-secondary btn-sm mb-2 add-split-btn-customer' data-bill-id='<?php echo $bill['id']; ?>'>Add Split</button>
                        <div class='split-error-customer text-danger mb-2'></div>
                        <button type='submit' name='split_checkout' class='btn btn-danger'>Checkout (Split)</button>
                    </form>
                  </div>
                </div>
              </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>








<script>
// --- Menu Item Search Filter for Dropdowns ---
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('menu-search-input')) {
        const input = e.target;
        const select = input.parentElement.querySelector('select');
        const filter = input.value.toLowerCase();
        Array.from(select.options).forEach(option => {
            if (option.value === '') {
                option.style.display = '';
            } else {
                option.style.display = option.text.toLowerCase().includes(filter) ? '' : 'none';
            }
        });
    }
});

// --- Split Payment for Customer and Table Billing Modals ---
document.addEventListener('DOMContentLoaded', function() {
    function createSplitRow(idx, billType) {
        // billType: 'customer' or 'table'
        return `<div class='row g-2 mb-2 split-row' data-idx='${idx}'>
            <div class='col-4'><input type='number' step='0.01' min='0' name='split_amount[${idx}]' class='form-control split-amount' placeholder='Amount' required></div>
            <div class='col-4'><select name='split_type[${idx}]' class='form-select split-type' required>
                <option value='cash'>Cash</option>
                <option value='card'>Card</option>
                <option value='credit'>Credit</option>
            </select></div>
            <div class='col-3 split-customer-col' style='display:none;'>
                <select name='split_customer[${idx}]' class='form-select split-customer'>
                    <option value=''>Select Customer</option>
                    ${customers.map(c => `<option value='${c.id}'>${c.name} (${c.phone})</option>`).join('')}
                </select>
            </div>
            <div class='col-1'><button type='button' class='btn btn-outline-danger btn-sm remove-split-btn'>&times;</button></div>
            <div class='col-12 split-credit-warning'></div>
        </div>`;
    }

    // Add Split for both types
    document.querySelectorAll('.add-split-btn-customer').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const billId = btn.getAttribute('data-bill-id');
            const container = document.getElementById('split-payments-container-customer-' + billId);
            let idx = container.childElementCount;
            container.insertAdjacentHTML('beforeend', createSplitRow(idx, 'customer'));
            updateCustomerSelects(container);
        });
    });
    document.querySelectorAll('.add-split-btn-table').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const billId = btn.getAttribute('data-bill-id');
            const container = document.getElementById('split-payments-container-table-' + billId);
            let idx = container.childElementCount;
            container.insertAdjacentHTML('beforeend', createSplitRow(idx, 'table'));
            updateCustomerSelects(container);
        });
    });

    function updateCustomerSelects(container) {
        container.querySelectorAll('.split-row').forEach(row => {
            const typeSel = row.querySelector('.split-type');
            const custCol = row.querySelector('.split-customer-col');
            if (typeSel.value === 'credit') {
                custCol.style.display = '';
            } else {
                custCol.style.display = 'none';
                row.querySelector('.split-credit-warning').innerHTML = '';
            }
        });
    }

    // Event listeners for both types
    document.querySelectorAll('[id^="split-payments-container-"]').forEach(function(container) {
        container.addEventListener('change', function(e) {
            if (e.target.classList.contains('split-type')) {
                updateCustomerSelects(container);
            }
            validateSplitTotal(container);
        });
        container.addEventListener('input', function(e) {
            if (e.target.classList.contains('split-amount')) {
                validateSplitTotal(container);
            }
        });
        container.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-split-btn')) {
                e.target.closest('.split-row').remove();
                updateCustomerSelects(container);
                validateSplitTotal(container);
            }
        });
    });

    function validateSplitTotal(container) {
        let total = 0;
        container.querySelectorAll('.split-row').forEach(row => {
            total += parseFloat(row.querySelector('.split-amount').value || 0);
        });
        // Find the closest error div
        let errorDiv = container.parentElement.querySelector('.split-error-customer, .split-error-table');
        // Find the total from the modal
        let totalText = container.closest('.modal-body').querySelector('strong').textContent;
        let billTotal = parseFloat(totalText.replace(/[^\d.]/g, '')) || 0;
        if (errorDiv) {
            if (Math.abs(total - billTotal) > 0.01) {
                errorDiv.textContent = `Split total (NPR ${total.toFixed(2)}) must match bill total (NPR ${billTotal.toFixed(2)})`;
                return false;
            } else {
                errorDiv.textContent = '';
                return true;
            }
        }
    }
});
</script>
</script>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>
