<?php
require_once __DIR__ . '/../config.php';

// Authentication check
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
    $where[] = "DATE(b.created_at) = CURDATE() AND b.status='closed'";
} elseif ($filter === 'monthly') {
    $where[] = "MONTH(b.created_at) = MONTH(CURDATE()) AND YEAR(b.created_at) = YEAR(CURDATE()) AND b.status='closed'";
} elseif ($filter === 'yearly') {
    $where[] = "YEAR(b.created_at) = YEAR(CURDATE()) AND b.status='closed'";
}
if ($from_date) {
    $where[] = "DATE(b.created_at) >= '" . $from_date . "'";
}
if ($to_date) {
    $where[] = "DATE(b.created_at) <= '" . $to_date . "'";
}
$where_sql = $where ? ("WHERE " . implode(' AND ', $where)) : '';
$sales = $pdo->query("SELECT b.*, t.table_number, c.name as customer_name FROM bills b LEFT JOIN tables t ON b.table_id = t.id LEFT JOIN customers c ON b.customer_id = c.id $where_sql ORDER BY b.created_at DESC")->fetchAll();
$total = $pdo->query("SELECT IFNULL(SUM(total_amount),0) as total FROM bills b $where_sql")->fetch()['total'];

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=sales_report.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Bill ID','Table','Customer','Total Amount','Payment Type','Created At']);
    foreach ($sales as $s) {
        fputcsv($output, [$s['id'],$s['table_number'],$s['customer_name'],$s['total_amount'],$s['payment_type'],$s['created_at']]);
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
    <title>Sales Report</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='../public/style.css' rel='stylesheet'>
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
            <li class="nav-item"><a class="nav-link active" href="/possystem/reports/sales.php">Sales Report</a></li>
            <li class="nav-item"><a class="nav-link" href="/possystem/reports/inventory.php">Inventory Report</a></li>
            <li class="nav-item"><a class="nav-link" href="/possystem/reports/attendance.php">Attendance Report</a></li>
          </ul>
        </div>
      </div>
    </nav>
    <div class='container mt-4' style="max-width: 1000px;">
      <div class="card p-5 bg-light bg-opacity-75 shadow-lg">
        <h2 class="mb-4">Sales Report</h2>
        <div class='mb-3'>
            <a href='?filter=daily' class='btn btn-outline-primary btn-lg me-2 <?php if($filter==='daily') echo "active"; ?>'>Daily</a>
            <a href='?filter=monthly' class='btn btn-outline-primary btn-lg me-2 <?php if($filter==='monthly') echo "active"; ?>'>Monthly</a>
            <a href='?filter=yearly' class='btn btn-outline-primary btn-lg me-2 <?php if($filter==='yearly') echo "active"; ?>'>Yearly</a>
            <a href='?filter=<?php echo $filter; ?>&export=csv' class='btn btn-success btn-lg'>Export CSV</a>
        </div>
        <div class='mb-3 row'>
            <form method='get' class='row g-2'>
                <div class='col-md-2'>
                    <label for='from_date' class='form-label'>From Date</label>
                    <input type='date' id='from_date' name='from_date' class='form-control' value='<?php echo htmlspecialchars($from_date); ?>' placeholder='From Date'>
                </div>
                <div class='col-md-2'>
                    <label for='to_date' class='form-label'>To Date</label>
                    <input type='date' id='to_date' name='to_date' class='form-control' value='<?php echo htmlspecialchars($to_date); ?>' placeholder='To Date'>
                </div>
                <div class='col-md-2 d-flex align-items-end'>
                    <button type='submit' class='btn btn-primary'>Apply</button>
                </div>
            </form>
        </div>
        <table class='table table-bordered table-hover bg-white bg-opacity-75' style="font-size:1.15rem;">
            <thead>
                <tr>
                    <th>Bill ID</th>
                    <th>Table</th>
                    <th>Customer</th>
                    <th>Total Amount</th>
                    <th>Payment Type</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sales as $s): ?>
                <tr>
                    <td><?php echo $s['id']; ?></td>
                    <td><?php echo htmlspecialchars($s['table_number']); ?></td>
                    <td><?php echo htmlspecialchars($s['customer_name']); ?></td>
                    <td><?php echo number_format($s['total_amount'],2); ?></td>
                    <td><?php echo htmlspecialchars($s['payment_type']); ?></td>
                    <td><?php echo $s['created_at']; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan='3'>Total</th>
                    <th><?php echo number_format($total,2); ?></th>
                    <th colspan='2'></th>
                </tr>
            </tfoot>
        </table>
      </div>
    </div>
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>