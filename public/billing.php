<?php
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
    if (isset($_POST['add_item'])) {
        $table_id = $_POST['table_id'];
        $menu_item_id = $_POST['menu_item_id'];
        $quantity = max(1, (int)$_POST['quantity']);
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
                        <button class='btn btn-primary' data-bs-toggle='modal' data-bs-target='#billingModal' data-table-id='<?php echo $table['id']; ?>' data-table-number='<?php echo htmlspecialchars($table['table_number']); ?>'>Bill / Add Items</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Billing Modal -->
<div class='modal fade' id='billingModal' tabindex='-1' aria-labelledby='billingModalLabel' aria-hidden='true'>
  <div class='modal-dialog modal-lg modal-dialog-centered'>
    <div class='modal-content'>
      <div class='modal-header'>
        <h5 class='modal-title' id='billingModalLabel'>Table Billing</h5>
        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
      </div>
      <div class='modal-body' id='billing-modal-body'>
        <!-- Content loaded by JS -->
      </div>
    </div>
  </div>
</div>
<script>

const menuItems = <?php echo json_encode($menu_items); ?>;
const customers = <?php echo json_encode($customers); ?>;
const bills = <?php echo json_encode($bills); ?>;
const bookedTables = <?php echo json_encode($booked_tables); ?>;
// Get available tables for switch
const availableTables = <?php echo json_encode($pdo->query("SELECT * FROM tables WHERE status='available' ORDER BY table_number")->fetchAll()); ?>;
// Pass used credit for each customer
const customerCredits = {};
<?php foreach ($customers as $c): ?>
customerCredits[<?php echo $c['id']; ?>] = <?php
    $stmt = $pdo->prepare("SELECT IFNULL(SUM(total_amount),0) FROM bills WHERE customer_id=? AND status='closed' AND payment_type='credit'");
    $stmt->execute([$c['id']]);
    echo json_encode($stmt->fetchColumn());
?>;
<?php endforeach; ?>

function renderBillingModal(tableId, tableNumber) {
    let bill = bills[tableId] || null;
    let html = `<h5>Table: ${tableNumber}</h5>`;
    if (!bill) {
        html += `<div class='alert alert-info'>No bill started yet. Add an item to start a bill.</div>`;
    }
    html += `<form method='post' class='mb-3'><input type='hidden' name='table_id' value='${tableId}'>`;
    html += `<div class='row g-2'><div class='col'><select name='menu_item_id' class='form-select' required><option value=''>Select Item</option>`;
    menuItems.forEach(item => {
        html += `<option value='${item.id}'>${item.name} (${parseFloat(item.price).toFixed(2)})</option>`;
    });
    html += `</select></div><div class='col'><input type='number' name='quantity' class='form-control' placeholder='Qty' min='1' value='1' required></div><div class='col'><button type='submit' name='add_item' class='btn btn-success'>Add</button></div></div></form>`;
    if (bill) {
        html += `<h6>Bill Items:</h6><ul class='list-group mb-2'>`;
        // Bill items will be loaded via PHP for now (can be improved with AJAX)
        <?php foreach ($booked_tables as $table): ?>
        if (tableId == <?php echo $table['id']; ?> && bills[tableId]) {
            <?php
            if ($bills[$table['id']]) {
                $stmt = $pdo->prepare("SELECT bi.*, mi.name FROM bill_items bi JOIN menu_items mi ON bi.menu_item_id = mi.id WHERE bi.bill_id=?");
                $stmt->execute([$bills[$table['id']]['id']]);
                $bill_items = $stmt->fetchAll();
                foreach ($bill_items as $bi) {
                    echo "html += `<li class='list-group-item d-flex justify-content-between align-items-center'>{$bi['name']} x {$bi['quantity']}<span>NPR ".number_format($bi['price']*$bi['quantity'],2)."</span> <form method='post' style='display:inline;'><input type='hidden' name='bill_item_id' value='{$bi['id']}'><input type='hidden' name='bill_id' value='{$bi['bill_id']}'><button type='submit' name='remove_bill_item' class='btn btn-sm btn-outline-danger ms-2'>Remove</button></form></li>`;\n";
                }
            }
            ?>
        }
        <?php endforeach; ?>
        html += `</ul><strong>Total: NPR ${parseFloat(bill.total_amount||0).toFixed(2)}</strong>`;

        // Remove Bill button
        html += `<form method='post' class='mt-2 d-inline'><input type='hidden' name='bill_id' value='${bill.id}'><button type='submit' name='remove_bill' class='btn btn-outline-danger btn-sm me-2'><i class='bi bi-x-circle'></i> Remove Bill & Unbook Table</button></form>`;
        // Transfer Table Button (improved)
        html += `<button type='button' class='btn btn-warning btn-sm fw-bold ms-1' style='color:#333;box-shadow:0 2px 8px #0001;' data-bs-toggle='modal' data-bs-target='#transferTableModal${bill.id}'><i class='bi bi-arrow-left-right'></i> Transfer Table</button>`;
        // Transfer Table Modal (improved)
        html += `
        <div class='modal fade' id='transferTableModal${bill.id}' tabindex='-1' aria-labelledby='transferTableModalLabel${bill.id}' aria-hidden='true'>
          <div class='modal-dialog'>
            <div class='modal-content rounded-4 shadow-lg border-0'>
              <form method='post' autocomplete='off'>
                <div class='modal-header bg-gradient bg-primary bg-opacity-25 rounded-top-4 border-0'>
                  <h5 class='modal-title fw-bold d-flex align-items-center gap-2' id='transferTableModalLabel${bill.id}'><span class='text-warning'><i class='bi bi-arrow-left-right'></i></span> Transfer Table</h5>
                  <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                </div>
                <div class='modal-body pb-0'>
                  <input type='hidden' name='bill_id' value='${bill.id}'>
                  <div class='mb-4'>
                    <label for='new_table_id${bill.id}' class='form-label fw-semibold'>Select New Table</label>
                    <select class='form-select form-select-lg border-2 border-warning' name='new_table_id' id='new_table_id${bill.id}' required style='font-size:1.1rem;'>
                      <option value='' disabled selected>🪑 Choose a table...</option>
                      ${availableTables.filter(t => String(t.id) !== String(tableId)).map(t => `<option value='${t.id}'>🪑 Table ${t.table_number} (${t.status.charAt(0).toUpperCase() + t.status.slice(1)})</option>`).join('')}
                    </select>
                  </div>
                  <div class='alert alert-info small rounded-3 border-0 mb-0' style='background:#f8fafc;'>
                    <i class='bi bi-info-circle-fill text-primary'></i> Transferring will move this bill and all items to the selected table.<br>The current table will be freed for new customers.
                  </div>
                </div>
                <div class='modal-footer d-flex justify-content-between border-0 pt-0 pb-4'>
                  <button type='button' class='btn btn-outline-secondary px-4 rounded-pill' data-bs-dismiss='modal'><i class='bi bi-x-lg'></i> Cancel</button>
                  <button type='submit' name='switch_table' class='btn btn-success px-4 rounded-pill fw-bold' id='transferBtn${bill.id}' disabled><i class='bi bi-arrow-repeat'></i> Transfer</button>
                </div>
              </form>
            </div>
          </div>
        </div>`;
        // Enable Transfer button only if a table is selected
        setTimeout(() => {
          const sel = document.getElementById('new_table_id'+bill.id);
          const btn = document.getElementById('transferBtn'+bill.id);
          if (sel && btn) {
            sel.addEventListener('change', function() {
              btn.disabled = !sel.value;
            });
          }
        }, 300);

        // Direct Payment Section
        html += `<form method='post' class='mt-3' id='direct-payment-form'><input type='hidden' name='bill_id' value='${bill.id}'>`;
        html += `<div class='mb-2'><label class='form-label'>Direct Payment:</label></div>`;
        html += `<div class='row g-2 mb-2'>`;
        html += `<div class='col-4'><select name='payment_type' class='form-select' id='direct-payment-type' required>
            <option value='online'>Online</option>
            <option value='offline'>Offline</option>
            <option value='credit'>Credit</option>
        </select></div>`;
        html += `<div class='col-5' id='direct-customer-select' style='display:none;'>
            <select name='customer_id' class='form-select' id='direct-customer-id'>
                <option value=''>Select Customer</option>
                ${customers.map(c => `<option value='${c.id}'>${c.name} (${c.phone})</option>`).join('')}
            </select>
        </div>`;
        html += `<div class='col-3'><button type='submit' name='checkout' class='btn btn-success'>Checkout (Direct)</button></div>`;
        html += `</div>`;
        html += `<div id='direct-credit-warning'></div>`;
        html += `</form>`;

        // Split Payment Section
        html += `<form method='post' class='mt-3' id='split-payment-form'><input type='hidden' name='bill_id' value='${bill.id}'>`;
        html += `<div class='mb-2'><label class='form-label'>Split Payment:</label></div>`;
        html += `<div id='split-payments-container'></div>`;
        html += `<button type='button' class='btn btn-secondary btn-sm mb-2' id='add-split-btn'>Add Split</button>`;
        html += `<div id='split-error' class='text-danger mb-2'></div>`;
        html += `<button type='submit' name='split_checkout' class='btn btn-danger'>Checkout (Split)</button></form>`;

    }
    document.getElementById('billing-modal-body').innerHTML = html;
    // Split payment UI logic
    setTimeout(() => {
        // Direct payment customer select logic
        const directType = document.getElementById('direct-payment-type');
        const directCustCol = document.getElementById('direct-customer-select');
        const directCustId = document.getElementById('direct-customer-id');
        const directCreditWarning = document.getElementById('direct-credit-warning');
        if (directType) {
            function updateDirectCustomer() {
                if (directType.value === 'credit') {
                    directCustCol.style.display = '';
                } else {
                    directCustCol.style.display = 'none';
                    directCreditWarning.innerHTML = '';
                }
            }
            function updateDirectCreditWarning() {
                if (directType.value === 'credit' && directCustId.value) {
                    let customer = customers.find(c => c.id == directCustId.value);
                    let usedCredit = parseFloat(customerCredits[directCustId.value] || 0);
                    let creditLimit = parseFloat(customer.credit_limit);
                    let billTotal = parseFloat(bill.total_amount||0);
                    if ((usedCredit + billTotal) > creditLimit) {
                        directCreditWarning.innerHTML = `<div class='alert alert-warning mt-2'>Warning: This bill will exceed the customer's credit limit!</div>`;
                    } else {
                        directCreditWarning.innerHTML = '';
                    }
                } else {
                    directCreditWarning.innerHTML = '';
                }
            }
            directType.addEventListener('change', function() {
                updateDirectCustomer();
                updateDirectCreditWarning();
            });
            if (directCustId) {
                directCustId.addEventListener('change', function() {
                    updateDirectCreditWarning();
                });
            }
            updateDirectCustomer();
        }

        // Split payment logic (unchanged)
        const splitPaymentsContainer = document.getElementById('split-payments-container');
        const addSplitBtn = document.getElementById('add-split-btn');
        let splitIndex = 0;
        function createSplitRow(idx) {
            return `<div class='row g-2 mb-2 split-row' data-idx='${idx}'>
                <div class='col-4'><input type='number' step='0.01' min='0' name='split_amount[${idx}]' class='form-control split-amount' placeholder='Amount' required></div>
                <div class='col-4'><select name='split_type[${idx}]' class='form-select split-type' required>
                    <option value='online'>Online</option>
                    <option value='offline'>Offline</option>
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
        function updateCustomerSelects() {
            splitPaymentsContainer.querySelectorAll('.split-row').forEach(row => {
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
        function updateCreditWarnings() {
            splitPaymentsContainer.querySelectorAll('.split-row').forEach(row => {
                const typeSel = row.querySelector('.split-type');
                if (typeSel.value === 'credit') {
                    let warningDiv = row.querySelector('.split-credit-warning');
                    const custSel = row.querySelector('.split-customer');
                    const amt = parseFloat(row.querySelector('.split-amount').value || 0);
                    const customerId = custSel.value;
                    if (customerId) {
                        let customer = customers.find(c => c.id == customerId);
                        let usedCredit = parseFloat(customerCredits[customerId] || 0);
                        let creditLimit = parseFloat(customer.credit_limit);
                        if ((usedCredit + amt) > creditLimit) {
                            warningDiv.innerHTML = `<div class='alert alert-warning mt-2'>Warning: ${customer.name} will exceed credit limit!</div>`;
                        } else {
                            warningDiv.innerHTML = '';
                        }
                    } else {
                        warningDiv.innerHTML = '';
                    }
                }
            });
        }
        function validateSplitTotal() {
            let total = 0;
            splitPaymentsContainer.querySelectorAll('.split-row').forEach(row => {
                total += parseFloat(row.querySelector('.split-amount').value || 0);
            });
            let errorDiv = document.getElementById('split-error');
            if (Math.abs(total - parseFloat(bill.total_amount||0)) > 0.01) {
                errorDiv.textContent = `Split total (NPR ${total.toFixed(2)}) must match bill total (NPR ${parseFloat(bill.total_amount||0).toFixed(2)})`;
                return false;
            } else {
                errorDiv.textContent = '';
                return true;
            }
        }
        function addSplitRow() {
            splitPaymentsContainer.insertAdjacentHTML('beforeend', createSplitRow(splitIndex));
            splitIndex++;
            updateCustomerSelects();
        }
        if (addSplitBtn) {
            addSplitBtn.addEventListener('click', function() {
                addSplitRow();
            });
        }
        if (splitPaymentsContainer) {
            splitPaymentsContainer.addEventListener('change', function(e) {
                if (e.target.classList.contains('split-type')) {
                    updateCustomerSelects();
                    updateCreditWarnings();
                }
                if (e.target.classList.contains('split-customer') || e.target.classList.contains('split-amount')) {
                    updateCreditWarnings();
                }
                validateSplitTotal();
            });
            splitPaymentsContainer.addEventListener('input', function(e) {
                if (e.target.classList.contains('split-amount')) {
                    updateCreditWarnings();
                    validateSplitTotal();
                }
            });
            splitPaymentsContainer.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-split-btn')) {
                    e.target.closest('.split-row').remove();
                    updateCustomerSelects();
                    updateCreditWarnings();
                    validateSplitTotal();
                }
            });
            // Add two split rows by default
            addSplitRow();
            addSplitRow();
        }
    }, 100);
}

function toggleCustomerSelect(val, billTotal) {
    let customerSelect = document.getElementById('customer-select');
    let customerId = document.getElementById('customer-id');
    customerSelect.style.display = (val === 'credit') ? '' : 'none';
    if (val === 'credit' && customerId) {
        showCreditWarning(customerId.value, billTotal);
    } else {
        document.getElementById('credit-warning').innerHTML = '';
    }
}

function showCreditWarning(customerId, billTotal) {
    let customer = customers.find(c => c.id == customerId);
    if (!customer) return;
    let usedCredit = parseFloat(customerCredits[customerId] || 0);
    let creditLimit = parseFloat(customer.credit_limit);
    billTotal = parseFloat(billTotal || 0);
    if ((usedCredit + billTotal) > creditLimit) {
        document.getElementById('credit-warning').innerHTML = `<div class='alert alert-warning mt-2'>Warning: This bill will exceed the customer's credit limit!</div>`;
    } else {
        document.getElementById('credit-warning').innerHTML = '';
    }
}

var billingModal = document.getElementById('billingModal');
billingModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var tableId = button.getAttribute('data-table-id');
    var tableNumber = button.getAttribute('data-table-number');
    renderBillingModal(tableId, tableNumber);
});
</script>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>
