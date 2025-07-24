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
        if ($payment_type === 'credit' && $customer_id) {
            $stmt = $pdo->prepare("SELECT total_amount FROM bills WHERE id=?");
            $stmt->execute([$bill_id]);
            $bill_total = $stmt->fetchColumn();
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
            $stmt = $pdo->prepare("SELECT total_amount FROM bills WHERE id=?");
            $stmt->execute([$bill_id]);
            $bill_total = $stmt->fetchColumn();
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
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Table Billing</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='/possystem/public/style.css' rel='stylesheet'>
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
        // Switch Table section
        html += `<form method='post' class='mt-2'><input type='hidden' name='bill_id' value='${bill.id}'>`;
        html += `<div class='mb-2'><label class='form-label'>Switch Table:</label><select name='new_table_id' class='form-select' required>`;
        availableTables.forEach(t => {
            html += `<option value='${t.id}'>Table ${t.table_number}</option>`;
        });
        html += `</select></div>`;
        html += `<button type='submit' name='switch_table' class='btn btn-warning'>Switch Table</button></form>`;
        // Payment section
        html += `<form method='post' class='mt-3'><input type='hidden' name='bill_id' value='${bill.id}'>`;
        html += `<div class='mb-2'><label class='form-label'>Payment Type:</label><select name='payment_type' class='form-select' required id='payment-type'><option value='online'>Online</option><option value='offline'>Offline</option><option value='credit' selected>Credit</option></select></div>`;
        html += `<div class='mb-2' id='customer-select'><label class='form-label'>Select Customer:</label><select name='customer_id' class='form-select' id='customer-id'>`;
        customers.forEach((c, idx) => { html += `<option value='${c.id}'${idx===0?' selected':''}>${c.name} (${c.phone})</option>`; });
        html += `</select></div>`;
        html += `<div id='credit-warning'></div>`;
        html += `<button type='submit' name='checkout' class='btn btn-danger'>Checkout</button></form>`;
    }
    document.getElementById('billing-modal-body').innerHTML = html;
    // Always show customer select and warning for credit by default
    setTimeout(() => {
        let paymentType = document.getElementById('payment-type');
        let customerId = document.getElementById('customer-id');
        if (paymentType && paymentType.value === 'credit' && customerId) {
            showCreditWarning(customerId.value, bill ? bill.total_amount : 0);
        }
        paymentType.addEventListener('change', function() {
            let customerSelect = document.getElementById('customer-select');
            if (this.value === 'credit') {
                customerSelect.style.display = '';
                let customerId = document.getElementById('customer-id');
                if (customerId) showCreditWarning(customerId.value, bill ? bill.total_amount : 0);
            } else {
                customerSelect.style.display = 'none';
                document.getElementById('credit-warning').innerHTML = '';
            }
        });
        let customerIdSelect = document.getElementById('customer-id');
        customerIdSelect.addEventListener('change', function() {
            showCreditWarning(this.value, bill ? bill.total_amount : 0);
        });
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
