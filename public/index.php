<?php
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafe Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/possystem/public/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg mb-4">
  <div class="container-fluid">
    <?php
    require_once __DIR__ . '/../config.php';
    $rest = $pdo->query("SELECT name FROM restaurant_details LIMIT 1")->fetch();
    $restaurant_name = $rest ? $rest['name'] : 'Cafe POS';
    ?>
    <a class="navbar-brand" href="/possystem/public/index.php"><?php echo htmlspecialchars($restaurant_name); ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="#navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="/possystem/public/dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/billing.php">Table Billing</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/booketable.php">Table Booking</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/printbill.php">Print Bill</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/restaurant.php">Restaurant</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/tables.php">Manage Tables</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/admin/menu.php">Menu</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/admin/customers.php">Customers</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/reports/sales.php">Sales Report</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/reports/inventory.php">Inventory Report</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class="container d-flex justify-content-center align-items-center" style="min-height: 70vh;">
  <div class="card p-5 bg-light bg-opacity-75 shadow-lg text-center" style="max-width: 500px; width:100%; font-size:1.25rem;">
    <h1 class="mb-3" style="font-size:2rem;">Welcome to the Cafe Management System</h1>
    <p>Use the navigation bar to access different modules.</p>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>