<?php
require_once __DIR__ . '/../config.php';


// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /possystem/index.php');
    exit;
}

// Filter
$filter = $_GET['filter'] ?? 'daily';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$where = [];
if ($filter === 'daily') {
    $where[] = "purchased_at = CURDATE()";
} elseif ($filter === 'monthly') {
    $where[] = "MONTH(purchased_at) = MONTH(CURDATE()) AND YEAR(purchased_at) = YEAR(CURDATE())";
} elseif ($filter === 'yearly') {
    $where[] = "YEAR(purchased_at) = YEAR(CURDATE())";
}
if ($from_date) {
    $where[] = "DATE(purchased_at) >= '" . $from_date . "'";
}
if ($to_date) {
    $where[] = "DATE(purchased_at) <= '" . $to_date . "'";
}
$where_sql = $where ? ("WHERE " . implode(' AND ', $where)) : '';
$purchases = $pdo->query("SELECT * FROM inventory_purchases $where_sql ORDER BY purchased_at DESC")->fetchAll();
$total = $pdo->query("SELECT IFNULL(SUM(total_price),0) as total FROM inventory_purchases $where_sql")->fetch()['total'];

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=inventory_report.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date','Item Name','Quantity','Total Price']);
    foreach ($purchases as $p) {
        fputcsv($output, [$p['purchased_at'], $p['item_name'], $p['quantity'], $p['total_price']]);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Inventory Report</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='/possystem/public/style.css' rel='stylesheet'>
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
        <li class="nav-item"><a class="nav-link" href="/possystem/reports/sales.php">Sales Report</a></li>
        <li class="nav-item"><a class="nav-link active" href="/possystem/reports/inventory.php">Inventory Report</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/reports/attendance.php">Attendance Report</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class='container mt-4' style="max-width: 1000px;">
  <div class="card p-5 bg-light bg-opacity-75 shadow-lg">
    <h2 class="mb-4">Inventory Report</h2>
    <div class='mb-3'>
        <a href='?filter=daily' class='btn btn-outline-primary btn-lg me-2 <?php if($filter==='daily') echo "active"; ?>'>Daily</a>
        <a href='?filter=monthly' class='btn btn-outline-primary btn-lg me-2 <?php if($filter==='monthly') echo "active"; ?>'>Monthly</a>
        <a href='?filter=yearly' class='btn btn-outline-primary btn-lg me-2 <?php if($filter==='yearly') echo "active"; ?>'>Yearly</a>
        <a href='?filter=<?php echo $filter; ?>&export=csv' class='btn btn-success btn-lg'>Export CSV</a>
    </div>
    <div class='mb-3 row'>
        <div class='col-md-2'>
            <input type='date' name='from_date' class='form-control' value='<?php echo htmlspecialchars($from_date); ?>' placeholder='From Date'>
        </div>
        <div class='col-md-2'>
            <input type='date' name='to_date' class='form-control' value='<?php echo htmlspecialchars($to_date); ?>' placeholder='To Date'>
        </div>
        <div class='col-md-2'>
            <button type='submit' class='btn btn-primary'>Apply</button>
        </div>
    </div>
    <table class='table table-bordered table-hover bg-white bg-opacity-75' style="font-size:1.15rem;">
        <thead>
            <tr>
                <th>Date</th>
                <th>Item Name</th>
                <th>Quantity</th>
                <th>Total Price</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($purchases as $p): ?>
            <tr>
                <td><?php echo htmlspecialchars($p['purchased_at']); ?></td>
                <td><?php echo htmlspecialchars($p['item_name']); ?></td>
                <td><?php echo $p['quantity']; ?></td>
                <td><?php echo number_format($p['total_price'],2); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan='3'>Total</th>
                <th><?php echo number_format($total,2); ?></th>
            </tr>
        </tfoot>
    </table>
  </div>
</div>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>