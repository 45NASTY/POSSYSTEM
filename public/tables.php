<?php
require_once __DIR__ . '/../config.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: /possystem/index.php');
    exit;
}

// Handle add table
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_table'])) {
    $table_number = trim($_POST['table_number']);
    if ($table_number !== '') {
        $stmt = $pdo->prepare("INSERT INTO tables (table_number) VALUES (?)");
        $stmt->execute([$table_number]);
    }
    header('Location: tables.php');
    exit;
}

// Fetch all tables
$tables = $pdo->query("SELECT * FROM tables ORDER BY table_number")->fetchAll();
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Manage Tables</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='/possystem/public/style.css' rel='stylesheet'>
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
        <li class="nav-item"><a class="nav-link" href="/possystem/public/restaurant.php">Restaurant</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/admin/createstaff.php">Create Staff</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/admin/customers.php">Customers</a></li>
        <li class="nav-item"><a class="nav-link active" href="/possystem/public/tables.php">Tables</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class='container d-flex justify-content-center align-items-center' style="min-height: 70vh;">
  <div class="card p-5 mb-4 bg-light bg-opacity-75 shadow-lg w-100" style="max-width: 700px; font-size: 1.25rem;">
    <h2 class="mb-4 text-center" style="font-size:2rem;">Manage Tables</h2>
    <form method='post' class='mb-4'>
        <div class='input-group input-group-lg'>
            <input type='text' name='table_number' class='form-control' placeholder='Table Number' required>
            <button type='submit' name='add_table' class='btn btn-primary'>Add Table</button>
        </div>
    </form>
    <table class='table table-bordered table-hover bg-white bg-opacity-75' style="font-size:1.15rem;">
        <thead><tr><th style="width:80px;">#</th><th>Table Number</th></tr></thead>
        <tbody>
        <?php foreach ($tables as $table): ?>
            <tr><td><?php echo $table['id']; ?></td><td><?php echo htmlspecialchars($table['table_number']); ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
  </div>
</div>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>
