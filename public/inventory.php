<?php
require_once __DIR__ . '/../config.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: /possystem/index.php');
    exit;
}

// Fetch inventory purchases
$purchases = $pdo->query("SELECT * FROM inventory_purchases ORDER BY purchased_at DESC")->fetchAll();
$total = $pdo->query("SELECT IFNULL(SUM(total_price),0) as total FROM inventory_purchases")->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/possystem/public/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="/possystem/public/dashboard.php">Cafe POS</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="#navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="/possystem/public/dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/billingmain.php">Billing</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/admin/admin.php">Admin</a></li>
        <li class="nav-item"><a class="nav-link active" href="/possystem/public/inventory.php">Inventory Management</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/reports/report.php">Report</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class='container mt-4' style="max-width: 1000px;">
  <div class="card p-5 mb-4 bg-light bg-opacity-75 shadow-lg">
    <h2 class="mb-4">Inventory Management</h2>
    <form method="post" class="mb-4 row g-3">
        <div class="col-md-2">
            <input type="text" name="item_name" class="form-control form-control-lg" placeholder="Item Name" required>
        </div>
        <div class="col-md-2">
            <input type="number" name="quantity" class="form-control form-control-lg" placeholder="Quantity" min="1" required>
        </div>
        <div class="col-md-2">
            <input type="number" step="0.01" name="total_price" class="form-control form-control-lg" placeholder="Total Price" min="0" required>
        </div>
        <div class="col-md-2">
            <input type="date" name="purchased_at" class="form-control form-control-lg" required value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="col-md-2">
            <select name="payment_type" class="form-select form-select-lg" required>
                <option value="cash">Cash</option>
                <option value="online">Online</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" name="add_inventory" class="btn btn-success btn-lg w-100">Add</button>
        </div>
    </form>
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_inventory'])) {
        $date = $_POST['purchased_at'];
        $item = $_POST['item_name'];
        $qty = max(1, (int)$_POST['quantity']);
        $price = max(0, (float)$_POST['total_price']);
        $payment_type = $_POST['payment_type'];
        $stmt = $pdo->prepare("INSERT INTO inventory_purchases (item_name, quantity, total_price, purchased_at, payment_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$item, $qty, $price, $date, $payment_type]);
        echo '<div class="alert alert-success">Inventory item added!</div>';
        // Refresh to show new entry
        echo '<script>window.location.href = "inventory.php";</script>';
    }
    ?>
    <table class="table table-bordered table-hover bg-white bg-opacity-75" style="font-size:1.15rem;">
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
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
