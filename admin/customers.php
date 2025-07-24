<?php
require_once __DIR__ . '/../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /possystem/index.php');
    exit;
}

// Handle add/edit actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add customer
    if (isset($_POST['add_customer'])) {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $credit_limit = floatval($_POST['credit_limit']);
        $stmt = $pdo->prepare("INSERT INTO customers (name, phone, email, credit_limit) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $phone, $email, $credit_limit]);
        header('Location: customers.php');
        exit;
    }
    // Update credit limit
    if (isset($_POST['update_credit_limit'])) {
        $id = $_POST['customer_id'];
        $credit_limit = floatval($_POST['credit_limit']);
        $stmt = $pdo->prepare("UPDATE customers SET credit_limit=? WHERE id=?");
        $stmt->execute([$credit_limit, $id]);
        header('Location: customers.php');
        exit;
    }
    // Update pending credit
    if (isset($_POST['update_pending_credit'])) {
        $id = $_POST['customer_id'];
        $pending_credit = floatval($_POST['pending_credit']);
        // Update pending_credit directly in customers table
        $stmt = $pdo->prepare("UPDATE customers SET pending_credit=? WHERE id=?");
        $stmt->execute([$pending_credit, $id]);
        header('Location: customers.php');
        exit;
    }
}

// Fetch customers and their credit info
$stmt = $pdo->prepare("SELECT c.*, c.pending_credit FROM customers c ORDER BY c.name");
$stmt->execute();
$customers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Customer Management</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='../public/style.css' rel='stylesheet'>
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
        <li class="nav-item"><a class="nav-link" href="/possystem/public/restaurant.php">Restaurant</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/admin/createstaff.php">Create Staff</a></li>
        <li class="nav-item"><a class="nav-link active" href="/possystem/admin/customers.php">Customers</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/tables.php">Tables</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class='container mt-4' style="max-width: 1200px;">
  <div class="card p-5 mb-5 bg-light bg-opacity-75 shadow-lg" style="font-size: 1.25rem;">
    <h2 class="mb-4" style="font-size:2rem;">Register Regular Customer</h2>
    <form method='post' class='row g-4'>
        <div class='col-md-3'><input type='text' name='name' class='form-control form-control-lg' placeholder='Name' required></div>
        <div class='col-md-2'><input type='text' name='phone' class='form-control form-control-lg' placeholder='Phone' required></div>
        <div class='col-md-3'><input type='email' name='email' class='form-control form-control-lg' placeholder='Email' required></div>
        <div class='col-md-2'><input type='number' step='0.01' name='credit_limit' class='form-control form-control-lg' placeholder='Credit Limit' required></div>
        <div class='col-md-2'><button type='submit' name='add_customer' class='btn btn-success btn-lg'>Register</button></div>
    </form>
  </div>
  <div class="card p-5 bg-light bg-opacity-75 shadow-lg" style="font-size: 1.25rem; overflow-x:auto;">
    <h2 class="mb-4" style="font-size:2rem;">Regular Customers</h2>
    <div style="width:100%; overflow-x:auto;">
      <table class='table table-bordered table-hover bg-white bg-opacity-75' style="font-size:1.15rem; width:100%; min-width:900px;">
          <thead>
              <tr>
                  <th>Name</th>
                  <th>Phone</th>
                  <th>Email</th>
                  <th>Credit Limit</th>
                  <th>Pending Credit</th>
                  <th>Update Credit Limit</th>
                  <th>Update Pending Credit</th>
              </tr>
          </thead>
          <tbody>
          <?php foreach ($customers as $cust): ?>
              <tr>
                  <td><?php echo htmlspecialchars($cust['name']); ?></td>
                  <td><?php echo htmlspecialchars($cust['phone']); ?></td>
                  <td><?php echo htmlspecialchars($cust['email']); ?></td>
                  <td><?php echo number_format($cust['credit_limit'],2); ?></td>
                  <td><?php echo number_format($cust['pending_credit'],2); ?></td>
                  <td>
                      <form method='post' class='d-flex flex-wrap'>
                          <input type='hidden' name='customer_id' value='<?php echo $cust['id']; ?>'>
                          <input type='number' step='0.01' name='credit_limit' value='<?php echo $cust['credit_limit']; ?>' class='form-control form-control-lg me-2 mb-2' required>
                          <button type='submit' name='update_credit_limit' class='btn btn-primary btn-lg'>Update</button>
                      </form>
                  </td>
                  <td>
                      <form method='post' class='d-flex flex-wrap'>
                          <input type='hidden' name='customer_id' value='<?php echo $cust['id']; ?>'>
                          <input type='number' step='0.01' name='pending_credit' value='<?php echo $cust['pending_credit']; ?>' class='form-control form-control-lg me-2 mb-2' required>
                          <button type='submit' name='update_pending_credit' class='btn btn-info btn-lg'>Update</button>
                      </form>
                  </td>
              </tr>
          <?php endforeach; ?>
          </tbody>
      </table>
    </div>
  </div>
</div>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>
