<?php
require_once __DIR__ . '/../config.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: /possystem/index.php');
    exit;
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $pan_vat = trim($_POST['pan_vat']);
    $stmt = $pdo->prepare("DELETE FROM restaurant_details");
    $stmt->execute();
    $stmt = $pdo->prepare("INSERT INTO restaurant_details (name, address, phone, pan_vat) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $address, $phone, $pan_vat]);
    header('Location: restaurant.php');
    exit;
}
$rest = $pdo->query("SELECT * FROM restaurant_details LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Restaurant Details</title>
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
        <li class="nav-item"><a class="nav-link active" href="/possystem/public/restaurant.php">Restaurant</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/admin/createstaff.php">Create Staff</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/admin/customers.php">Customers</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/tables.php">Tables</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class='container mt-4'>
  <h2>Restaurant Details</h2>
  <form method="post" class="mb-4">
    <div class="mb-3">
      <label>Name</label>
      <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($rest['name'] ?? ''); ?>" required>
    </div>
    <div class="mb-3">
      <label>Address</label>
      <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($rest['address'] ?? ''); ?>" required>
    </div>
    <div class="mb-3">
      <label>Phone</label>
      <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($rest['phone'] ?? ''); ?>" required>
    </div>
    <div class="mb-3">
      <label>PAN/VAT</label>
      <input type="text" name="pan_vat" class="form-control" value="<?php echo htmlspecialchars($rest['pan_vat'] ?? ''); ?>">
    </div>
    <button type="submit" class="btn btn-primary">Save</button>
  </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
