<?php
require_once __DIR__ . '/../config.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: /possystem/index.php');
    exit;
}

// Daily Sales (reduced)
$stmt = $pdo->prepare("SELECT IFNULL(SUM(total_amount),0) as daily_sales FROM bills WHERE DATE(created_at) = CURDATE() AND status = 'closed'");
$stmt->execute();
$daily_sales = $stmt->fetch()['daily_sales'];

// Daily Inventory Purchase
$stmt = $pdo->prepare("SELECT IFNULL(SUM(total_price),0) as daily_inventory FROM inventory_purchases WHERE purchased_at = CURDATE()");
$stmt->execute();
$daily_inventory = $stmt->fetch()['daily_inventory'];

// Monthly Sales
$stmt = $pdo->prepare("SELECT IFNULL(SUM(total_amount),0) as monthly_sales FROM bills WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status = 'closed'");
$stmt->execute();
$monthly_sales = $stmt->fetch()['monthly_sales'];

// Bills count today/month
$stmt = $pdo->prepare("SELECT COUNT(*) as count_today FROM bills WHERE DATE(created_at) = CURDATE()");
$stmt->execute();
$count_today = $stmt->fetch()['count_today'];
$stmt = $pdo->prepare("SELECT COUNT(*) as count_month FROM bills WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$stmt->execute();
$count_month = $stmt->fetch()['count_month'];

// Daily sales for last 14 days for bar graph
$sales_days = $pdo->query("SELECT DATE(closed_at) as day, SUM(total_amount) as sales FROM bills WHERE status='closed' AND closed_at IS NOT NULL GROUP BY day ORDER BY day DESC LIMIT 14")->fetchAll();
$sales_days = array_reverse($sales_days);

// Daily inventory purchases for last 14 days for bar graph
$inventory_days = $pdo->query("SELECT purchased_at as day, SUM(total_price) as inventory FROM inventory_purchases GROUP BY day ORDER BY day DESC LIMIT 14")->fetchAll();
$inventory_days = array_reverse($inventory_days);

// Daily sales for last 14 days for bar graph (by payment type)
$sales_days_offline = $pdo->query("SELECT DATE(closed_at) as day, SUM(total_amount) as sales FROM bills WHERE status='closed' AND closed_at IS NOT NULL AND (payment_type!='online' AND payment_type!='credit') GROUP BY day ORDER BY day DESC LIMIT 14")->fetchAll();
$sales_days_offline = array_reverse($sales_days_offline);
$sales_days_online = $pdo->query("SELECT DATE(closed_at) as day, SUM(total_amount) as sales FROM bills WHERE status='closed' AND closed_at IS NOT NULL AND payment_type='online' GROUP BY day ORDER BY day DESC LIMIT 14")->fetchAll();
$sales_days_online = array_reverse($sales_days_online);
$sales_days_credit = $pdo->query("SELECT DATE(closed_at) as day, SUM(total_amount) as sales FROM bills WHERE status='closed' AND closed_at IS NOT NULL AND payment_type='credit' GROUP BY day ORDER BY day DESC LIMIT 14")->fetchAll();
$sales_days_credit = array_reverse($sales_days_credit);

// Daily inventory purchases for last 14 days for bar graph (by payment type)
$inventory_days_cash = $pdo->query("SELECT purchased_at as day, SUM(total_price) as inventory FROM inventory_purchases WHERE payment_type='cash' GROUP BY day ORDER BY day DESC LIMIT 14")->fetchAll();
$inventory_days_cash = array_reverse($inventory_days_cash);
$inventory_days_online = $pdo->query("SELECT purchased_at as day, SUM(total_price) as inventory FROM inventory_purchases WHERE payment_type='online' GROUP BY day ORDER BY day DESC LIMIT 14")->fetchAll();
$inventory_days_online = array_reverse($inventory_days_online);

// Daily Inventory Purchase by payment type
$stmt = $pdo->prepare("SELECT IFNULL(SUM(total_price),0) as cash_inventory FROM inventory_purchases WHERE purchased_at = CURDATE() AND payment_type = 'cash'");
$stmt->execute();
$cash_inventory = $stmt->fetch()['cash_inventory'];
$stmt = $pdo->prepare("SELECT IFNULL(SUM(total_price),0) as online_inventory FROM inventory_purchases WHERE purchased_at = CURDATE() AND payment_type = 'online'");
$stmt->execute();
$online_inventory = $stmt->fetch()['online_inventory'];

// Daily Sales breakdown by payment type
$stmt = $pdo->prepare("SELECT IFNULL(SUM(total_amount),0) as offline_sales FROM bills WHERE DATE(created_at) = CURDATE() AND status = 'closed' AND payment_type!='online' AND payment_type!='credit'");
$stmt->execute();
$offline_sales = $stmt->fetch()['offline_sales'];
$stmt = $pdo->prepare("SELECT IFNULL(SUM(total_amount),0) as online_sales FROM bills WHERE DATE(created_at) = CURDATE() AND status = 'closed' AND payment_type = 'online'");
$stmt->execute();
$online_sales = $stmt->fetch()['online_sales'];
$stmt = $pdo->prepare("SELECT IFNULL(SUM(total_amount),0) as credit_sales FROM bills WHERE DATE(created_at) = CURDATE() AND status = 'closed' AND payment_type = 'credit'");
$stmt->execute();
$credit_sales = $stmt->fetch()['credit_sales'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Cafe Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/possystem/public/style.css" rel="stylesheet">
    <style>
      .dashboard-card {
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(60,60,120,0.08);
        margin-bottom: 1.5rem;
        text-align: center;
        font-weight: 500;
      }
      .dashboard-card .card-header {
        font-size: 1.1rem;
        font-weight: 600;
        background: rgba(0,0,0,0.04);
        border-radius: 16px 16px 0 0;
      }
      .dashboard-card .card-title {
        font-size: 2rem;
        font-weight: bold;
        margin: 0.5rem 0;
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
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link active" href="/possystem/public/dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/billingmain.php">Billing</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/admin/admin.php">Admin</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/inventory.php">Inventory Management</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/reports/report.php">Report</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class="container">
    <div class="row">
        <div class="col-md-3">
            <div class="card dashboard-card" style="background:#22c55e;color:#fff;">
                <div class="card-header">Today's Sales (NPR)</div>
                <div class="card-body">
                    <div style="font-size:1.1rem;">Offline: <strong><?php echo number_format($offline_sales,2); ?></strong></div>
                    <div style="font-size:1.1rem;">Online: <strong><?php echo number_format($online_sales,2); ?></strong></div>
                    <div style="font-size:1.1rem;">Credit: <strong><?php echo number_format($credit_sales,2); ?></strong></div>
                    <div style="font-size:1.2rem; margin-top:8px;">Total: <strong><?php echo number_format($daily_sales,2); ?></strong></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card" style="background:#6366f1;color:#fff;">
                <div class="card-header">Today's Inventory Purchase (NPR)</div>
                <div class="card-body">
                    <div style="font-size:1.1rem;">Cash: <strong><?php echo number_format($cash_inventory,2); ?></strong></div>
                    <div style="font-size:1.1rem;">Online: <strong><?php echo number_format($online_inventory,2); ?></strong></div>
                    <div style="font-size:1.2rem; margin-top:8px;">Total: <strong><?php echo number_format($daily_inventory,2); ?></strong></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card" style="background:#06b6d4;color:#fff;">
                <div class="card-header">Monthly Sales (NPR)</div>
                <div class="card-body"><h4 class="card-title"><?php echo number_format($monthly_sales,2); ?></h4></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card" style="background:#fbbf24;color:#fff;">
                <div class="card-header">Bills Today</div>
                <div class="card-body"><h4 class="card-title"><?php echo $count_today; ?></h4></div>
            </div>
        </div>
    </div>
    <div class="row mt-4">
        <div class="col-md-6">
            <h5>Daily Sales (Last 14 Days)</h5>
            <canvas id="salesBarChart" height="100"></canvas>
        </div>
        <div class="col-md-6">
            <h5>Daily Inventory Purchases (Last 14 Days)</h5>
            <canvas id="inventoryBarChart" height="100"></canvas>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const salesLabels = <?php echo json_encode(array_map(function($d){return $d['day'];}, $sales_days)); ?>;
const salesData = <?php echo json_encode(array_map(function($d){return (float)$d['sales'];}, $sales_days)); ?>;
const salesDataOffline = <?php echo json_encode(array_map(function($d){return (float)$d['sales'];}, $sales_days_offline)); ?>;
const salesDataOnline = <?php echo json_encode(array_map(function($d){return (float)$d['sales'];}, $sales_days_online)); ?>;
const salesDataCredit = <?php echo json_encode(array_map(function($d){return (float)$d['sales'];}, $sales_days_credit)); ?>;
const ctx = document.getElementById('salesBarChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: salesLabels,
        datasets: [
            {
                label: 'Sales (NPR) - Offline',
                data: salesDataOffline,
                backgroundColor: '#22c55e',
            },
            {
                label: 'Sales (NPR) - Online',
                data: salesDataOnline,
                backgroundColor: '#06b6d4',
            },
            {
                label: 'Sales (NPR) - Credit',
                data: salesDataCredit,
                backgroundColor: '#fbbf24',
            },
            {
                label: 'Sales (NPR) - Total',
                data: salesData,
                backgroundColor: '#2563eb',
            }
        ]
    },
    options: {
        scales: {
            x: { title: { display: true, text: 'Date' } },
            y: { title: { display: true, text: 'Sales (NPR)' }, beginAtZero: true }
        }
    }
});
const inventoryLabels = <?php echo json_encode(array_map(function($d){return $d['day'];}, $inventory_days)); ?>;
const inventoryData = <?php echo json_encode(array_map(function($d){return (float)$d['inventory'];}, $inventory_days)); ?>;
const inventoryDataCash = <?php echo json_encode(array_map(function($d){return (float)$d['inventory'];}, $inventory_days_cash)); ?>;
const inventoryDataOnline = <?php echo json_encode(array_map(function($d){return (float)$d['inventory'];}, $inventory_days_online)); ?>;
const ctx2 = document.getElementById('inventoryBarChart').getContext('2d');
new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: inventoryLabels,
        datasets: [
            {
                label: 'Inventory Purchase (NPR) - Cash',
                data: inventoryDataCash,
                backgroundColor: '#22c55e',
            },
            {
                label: 'Inventory Purchase (NPR) - Online',
                data: inventoryDataOnline,
                backgroundColor: '#06b6d4',
            },
            {
                label: 'Inventory Purchase (NPR) - Total',
                data: inventoryData,
                backgroundColor: '#6366f1',
            }
        ]
    },
    options: {
        scales: {
            x: { title: { display: true, text: 'Date' } },
            y: { title: { display: true, text: 'Inventory (NPR)' }, beginAtZero: true }
        }
    }
});
</script>
</body>
</html>