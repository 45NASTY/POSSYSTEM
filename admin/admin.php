<?php
require_once __DIR__ . '/../config.php';

// Check if the user is authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: /possystem/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Admin Panel</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='/possystem/public/style.css' rel='stylesheet'>
    <style>
      .admin-card {
        background: rgba(255,255,255,0.85);
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(60,60,120,0.08);
        padding: 2rem;
        margin-top: 2rem;
      }
      .admin-title {
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 1.5rem;
        letter-spacing: 1px;
      }
      .list-group-item a {
        font-weight: 500;
        color: #2563eb;
        text-decoration: none;
        transition: color 0.2s;
      }
      .list-group-item a:hover {
        color: #6366f1;
        text-decoration: underline;
      }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg mb-4">
  <div class="container-fluid">
    <?php
    require_once __DIR__ . '/../config.php';
    $rest = $pdo->query("SELECT name FROM restaurant_details LIMIT 1")->fetch();
    $restaurant_name = $rest ? $rest['name'] : 'Cafe POS';
    ?>
    <a class="navbar-brand" href="/possystem/public/dashboard.php"><?php echo htmlspecialchars($restaurant_name); ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="#navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="/possystem/public/dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link active" href="/possystem/admin/admin.php">Admin</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/billingmain.php">Billing</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/inventory.php">Inventory Management</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/reports/report.php">Report</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class='container admin-card'>
    <div class='admin-title'>Admin Panel</div>
    <ul class='list-group'>
        <li class='list-group-item'><a href='/possystem/admin/createstaff.php'>Create Staff</a></li>
        <li class='list-group-item'><a href='/possystem/admin/createadmin.php'>Create Admin</a></li>
        <li class='list-group-item'><a href='/possystem/admin/menu.php'>Manage Menu</a></li>
        <li class='list-group-item'><a href='/possystem/admin/customers.php'>Manage Customers</a></li>
        <li class='list-group-item'><a href='/possystem/public/tables.php'>Manage Tables</a></li>
        <li class='list-group-item'><a href='/possystem/public/restaurant.php'>Restaurant Details</a></li>
        <li class='list-group-item'><a href='/possystem/admin/attendance.php'>Attendance</a></li>
    </ul>
</div>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>
