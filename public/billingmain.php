<?php
require_once __DIR__ . '/../config.php';

// Fetch restaurant name for navbar
$rest = $pdo->query("SELECT name FROM restaurant_details LIMIT 1")->fetch();
$restaurant_name = $rest ? $rest['name'] : 'Cafe POS';

// Fetch last 5 bills (remove customer_name if not present)
$latest_bills = $pdo->query("SELECT id, total_amount, payment_type, status, created_at FROM bills ORDER BY created_at DESC LIMIT 5")->fetchAll();
// Fetch last 5 credit bills
$latest_credit_bills = $pdo->query("SELECT id, total_amount, payment_type, status, created_at FROM bills WHERE payment_type='credit' ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Billing Main</title>
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
  <h2>Billing Main Page</h2>
  <p>Welcome to the main billing page. Please select a table to bill or view billing history.</p>
  <div class="row mt-4">
    <div class="col-md-6">
      <div class="card mb-4">
        <div class="card-header bg-primary text-white">Latest 5 Bills</div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <thead><tr><th>#</th><th>Amount</th><th>Type</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach($latest_bills as $bill): ?>
                <tr>
                  <td><?php echo $bill['id']; ?></td>
                  <td><?php echo number_format($bill['total_amount'],2); ?></td>
                  <td><?php echo ucfirst($bill['payment_type']); ?></td>
                  <td><?php echo ucfirst($bill['status']); ?></td>
                  <td><?php echo date('Y-m-d H:i', strtotime($bill['created_at'])); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card mb-4">
        <div class="card-header bg-warning text-dark">Latest 5 Credit Bills</div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <thead><tr><th>#</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach($latest_credit_bills as $bill): ?>
                <tr>
                  <td><?php echo $bill['id']; ?></td>
                  <td><?php echo number_format($bill['total_amount'],2); ?></td>
                  <td><?php echo ucfirst($bill['status']); ?></td>
                  <td><?php echo date('Y-m-d H:i', strtotime($bill['created_at'])); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>
