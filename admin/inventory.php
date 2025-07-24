<?php
require_once '../config.php';

// Handle add purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_purchase'])) {
    $item_name = trim($_POST['item_name']);
    $quantity = $_POST['quantity'];
    $total_price = $_POST['total_price'];
    $purchased_at = $_POST['purchased_at'];
    $payment_type = $_POST['payment_type'];
    $stmt = $pdo->prepare("INSERT INTO inventory_purchases (item_name, quantity, total_price, purchased_at, payment_type) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$item_name, $quantity, $total_price, $purchased_at, $payment_type]);
    header('Location: inventory.php');
    exit;
}

// Filter
$filter = $_GET['filter'] ?? 'daily';
$where = '';
if ($filter === 'daily') {
    $where = "WHERE purchased_at = CURDATE()";
} elseif ($filter === 'monthly') {
    $where = "WHERE MONTH(purchased_at) = MONTH(CURDATE()) AND YEAR(purchased_at) = YEAR(CURDATE())";
} elseif ($filter === 'yearly') {
    $where = "WHERE YEAR(purchased_at) = YEAR(CURDATE())";
}
$purchases = $pdo->query("SELECT * FROM inventory_purchases $where ORDER BY purchased_at DESC")->fetchAll();
$total = $pdo->query("SELECT IFNULL(SUM(total_price),0) as total FROM inventory_purchases $where")->fetch()['total'];
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Inventory Purchases</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <?php
    require_once __DIR__ . '/../config.php';
    $rest = $pdo->query("SELECT name FROM restaurant_details LIMIT 1")->fetch();
    $restaurant_name = $rest ? $rest['name'] : 'Cafe POS';
    ?>
    <a class="navbar-brand" href="/possystem/public/index.php"><?php echo htmlspecialchars($restaurant_name); ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="/possystem/public/dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/billing.php">Table Billing</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/tables.php">Manage Tables</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/admin/menu.php">Menu</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/admin/customers.php">Customers</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/reports/sales.php">Sales Report</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/reports/inventory.php">Inventory Report</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class='container mt-4'>
    <h2>Log Inventory Purchase</h2>
    <form method='post' class='row g-3 mb-4'>
        <div class='col-md-3'><input type='text' name='item_name' class='form-control' placeholder='Item Name' required></div>
        <div class='col-md-2'><input type='number' name='quantity' class='form-control' placeholder='Quantity' required></div>
        <div class='col-md-2'><input type='number' step='0.01' name='total_price' class='form-control' placeholder='Total Price' required></div>
        <div class='col-md-3'><input type='date' name='purchased_at' class='form-control' value='<?php echo date('Y-m-d'); ?>' required></div>
        <div class='col-md-2'>
            <select name='payment_type' class='form-select' required>
                <option value='cash'>Cash</option>
                <option value='online'>Online</option>
            </select>
        </div>
        <div class='col-md-2'><button type='submit' name='add_purchase' class='btn btn-success'>Log Purchase</button></div>
    </form>
    <h2>Inventory Purchases</h2>
    <div class='mb-3'>
        <a href='?filter=daily' class='btn btn-outline-primary btn-sm <?php if($filter==='daily') echo "active"; ?>'>Daily</a>
        <a href='?filter=monthly' class='btn btn-outline-primary btn-sm <?php if($filter==='monthly') echo "active"; ?>'>Monthly</a>
        <a href='?filter=yearly' class='btn btn-outline-primary btn-sm <?php if($filter==='yearly') echo "active"; ?>'>Yearly</a>
    </div>
    <table class='table table-bordered'>
        <thead>
            <tr>
                <th>Date</th>
                <th>Item Name</th>
                <th>Quantity</th>
                <th>Total Price</th>
                <th>Payment Type</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($purchases as $p): ?>
            <tr>
                <td><?php echo htmlspecialchars($p['purchased_at']); ?></td>
                <td><?php echo htmlspecialchars($p['item_name']); ?></td>
                <td><?php echo $p['quantity']; ?></td>
                <td><?php echo number_format($p['total_price'],2); ?></td>
                <td><?php echo htmlspecialchars($p['payment_type']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan='4'>Total</th>
                <th><?php echo number_format($total,2); ?></th>
            </tr>
        </tfoot>
    </table>
</div>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>