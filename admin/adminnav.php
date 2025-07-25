<?php
session_start();
// Admin navigation bar for reuse
if (!isset($_SESSION['user_id'])) {
    header('Location: /possystem/index.php');
    exit;
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <?php
    require_once __DIR__ . '/../config.php';
    $rest = $pdo->query("SELECT name FROM restaurant_details LIMIT 1")->fetch();
    $restaurant_name = $rest ? $rest['name'] : 'Cafe POS';
    ?>
    <a class="navbar-brand" href="/possystem/public/dashboard.php"><?php echo htmlspecialchars($restaurant_name); ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="/possystem/public/dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/restaurant.php">Restaurant</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/admin/createstaff.php">Create Staff</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/admin/customers.php">Customers</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/tables.php">Tables</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/billingmain.php">Billing</a></li>
        <li class="nav-item"><a class="nav-link active" href="/possystem/admin/admin.php">Admin</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/inventory.php">Inventory Management</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/reports/report.php">Report</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>
