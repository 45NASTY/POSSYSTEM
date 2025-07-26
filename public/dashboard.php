<?php
require_once __DIR__ . '/../config.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: /possystem/index.php');
    exit;
}

// Daily Sales (include split payments)
$stmt = $pdo->prepare("SELECT IFNULL(SUM(total_amount),0) as non_split_sales FROM bills WHERE DATE(closed_at) = CURDATE() AND status = 'closed' AND payment_type != 'split'");
$stmt->execute();
$non_split_sales = $stmt->fetch()['non_split_sales'];
$stmt = $pdo->prepare("SELECT IFNULL(SUM(bp.amount),0) as split_sales FROM bill_payments bp INNER JOIN bills b ON bp.bill_id = b.id WHERE b.payment_type = 'split' AND DATE(b.closed_at) = CURDATE() AND b.status = 'closed'");
$stmt->execute();
$split_sales = $stmt->fetch()['split_sales'];
$daily_sales = $non_split_sales + $split_sales;

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

// Daily sales for last 14 days for bar graph (include split payments)
$sales_days = $pdo->query("
    SELECT day, SUM(sales) as sales FROM (
        SELECT DATE(closed_at) as day, total_amount as sales
        FROM bills
        WHERE status='closed' AND closed_at IS NOT NULL AND payment_type != 'split'
        UNION ALL
        SELECT DATE(b.closed_at) as day, bp.amount as sales
        FROM bill_payments bp
        INNER JOIN bills b ON bp.bill_id = b.id
        WHERE b.status='closed' AND b.closed_at IS NOT NULL AND b.payment_type = 'split'
    ) t
    GROUP BY day
    ORDER BY day DESC
    LIMIT 14
")->fetchAll();
$sales_days = array_reverse($sales_days);

// Daily inventory purchases for last 14 days for bar graph
$inventory_days = $pdo->query("SELECT purchased_at as day, SUM(total_price) as inventory FROM inventory_purchases GROUP BY day ORDER BY day DESC LIMIT 14")->fetchAll();
$inventory_days = array_reverse($inventory_days);

// Daily sales for last 14 days for bar graph (by payment type, include split payments)
$sales_days_offline = $pdo->query("
    SELECT day, SUM(sales) as sales FROM (
        SELECT DATE(closed_at) as day, total_amount as sales
        FROM bills
        WHERE status='closed' AND closed_at IS NOT NULL AND payment_type = 'offline'
        UNION ALL
        SELECT DATE(b.closed_at) as day, bp.amount as sales
        FROM bill_payments bp
        INNER JOIN bills b ON bp.bill_id = b.id
        WHERE b.status='closed' AND b.closed_at IS NOT NULL AND b.payment_type = 'split' AND bp.payment_type = 'offline'
    ) t
    GROUP BY day
    ORDER BY day DESC
    LIMIT 14
")->fetchAll();
$sales_days_offline = array_reverse($sales_days_offline);

$sales_days_online = $pdo->query("
    SELECT day, SUM(sales) as sales FROM (
        SELECT DATE(closed_at) as day, total_amount as sales
        FROM bills
        WHERE status='closed' AND closed_at IS NOT NULL AND payment_type = 'online'
        UNION ALL
        SELECT DATE(b.closed_at) as day, bp.amount as sales
        FROM bill_payments bp
        INNER JOIN bills b ON bp.bill_id = b.id
        WHERE b.status='closed' AND b.closed_at IS NOT NULL AND b.payment_type = 'split' AND bp.payment_type = 'online'
    ) t
    GROUP BY day
    ORDER BY day DESC
    LIMIT 14
")->fetchAll();
$sales_days_online = array_reverse($sales_days_online);

$sales_days_credit = $pdo->query("
    SELECT day, SUM(sales) as sales FROM (
        SELECT DATE(closed_at) as day, total_amount as sales
        FROM bills
        WHERE status='closed' AND closed_at IS NOT NULL AND payment_type = 'credit'
        UNION ALL
        SELECT DATE(b.closed_at) as day, bp.amount as sales
        FROM bill_payments bp
        INNER JOIN bills b ON bp.bill_id = b.id
        WHERE b.status='closed' AND b.closed_at IS NOT NULL AND b.payment_type = 'split' AND bp.payment_type = 'credit'
    ) t
    GROUP BY day
    ORDER BY day DESC
    LIMIT 14
")->fetchAll();
$sales_days_credit = array_reverse($sales_days_credit);

// Daily inventory purchases for last 14 days for bar graph (by payment type)
$inventory_days_offline = $pdo->query("SELECT purchased_at as day, SUM(total_price) as inventory FROM inventory_purchases WHERE payment_type='offline' GROUP BY day ORDER BY day DESC LIMIT 14")->fetchAll();
$inventory_days_offline = array_reverse($inventory_days_offline);
$inventory_days_online = $pdo->query("SELECT purchased_at as day, SUM(total_price) as inventory FROM inventory_purchases WHERE payment_type='online' GROUP BY day ORDER BY day DESC LIMIT 14")->fetchAll();
$inventory_days_online = array_reverse($inventory_days_online);

// Daily Inventory Purchase by payment type
$stmt = $pdo->prepare("SELECT IFNULL(SUM(total_price),0) as offline_inventory FROM inventory_purchases WHERE purchased_at = CURDATE() AND payment_type = 'offline'");
$stmt->execute();
$offline_inventory = $stmt->fetch()['offline_inventory'];
$stmt = $pdo->prepare("SELECT IFNULL(SUM(total_price),0) as online_inventory FROM inventory_purchases WHERE purchased_at = CURDATE() AND payment_type = 'online'");
$stmt->execute();
$online_inventory = $stmt->fetch()['online_inventory'];

// Daily Sales breakdown by payment type (all bills now stored in bills table)
$offline_sales = 0;
$online_sales = 0;
$credit_sales = 0;
$stmt = $pdo->prepare("SELECT payment_type, SUM(total_amount) as total FROM bills WHERE DATE(closed_at) = CURDATE() AND status = 'closed' GROUP BY payment_type");
$stmt->execute();
$sales_by_type = $stmt->fetchAll();
foreach ($sales_by_type as $row) {
    if ($row['payment_type'] === 'offline') $offline_sales = floatval($row['total']);
    elseif ($row['payment_type'] === 'online') $online_sales = floatval($row['total']);
    elseif ($row['payment_type'] === 'credit') $credit_sales = floatval($row['total']);
}
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
                    <div style="font-size:1.2rem; margin-top:8px;">Total: <strong><?php echo number_format($offline_sales + $online_sales + $credit_sales,2); ?></strong></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card" style="background:#6366f1;color:#fff;">
                <div class="card-header">Today's Inventory Purchase (NPR)</div>
                <div class="card-body">
                    <div style="font-size:1.1rem;">Offline: <strong><?php echo number_format($offline_inventory,2); ?></strong></div>
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
        <!-- Staff Salaries Summary Card -->
        <div class="col-md-3">
            <div class="card dashboard-card" style="background:#0ea5e9;color:#fff;">
                <div class="card-header">Staff Salaries (This Month)</div>
                <div class="card-body">
                    <?php
                    // Try to use BS columns, fallback to created_at if present, else show 0
                    $total_salary = 0;
                    $has_bs = false;
                    $has_created = false;
                    try {
                        $pdo->query("SELECT bs_month, bs_year FROM staff_salaries LIMIT 1");
                        $has_bs = true;
                    } catch (Exception $e) { $has_bs = false; }
                    if (!$has_bs) {
                        try {
                            $pdo->query("SELECT created_at FROM staff_salaries LIMIT 1");
                            $has_created = true;
                        } catch (Exception $e) { $has_created = false; }
                    }
                    if ($has_bs) {
                        $bs_month = date('n');
                        $bs_year = date('Y');
                        $stmt = $pdo->prepare("SELECT IFNULL(SUM(amount),0) as total_salary FROM staff_salaries WHERE bs_month = ? AND bs_year = ?");
                        $stmt->execute([$bs_month, $bs_year]);
                        $total_salary = $stmt->fetch()['total_salary'];
                    } elseif ($has_created) {
                        $stmt = $pdo->prepare("SELECT IFNULL(SUM(amount),0) as total_salary FROM staff_salaries WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                        $stmt->execute();
                        $total_salary = $stmt->fetch()['total_salary'];
                    } else {
                        $total_salary = 0;
                    }
                    ?>
                    <div style="font-size:1.2rem;">Total: <strong><?php echo number_format($total_salary,2); ?></strong></div>
                </div>
            </div>
        </div>

        <!-- Total Staff Card -->
        <div class="col-md-3">
            <div class="card dashboard-card" style="background:#2563eb;color:#fff;">
                <div class="card-header">Total Staff</div>
                <div class="card-body">
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT COUNT(*) as total FROM staff");
                        $total_staff = $stmt->fetch()['total'];
                    } catch (Exception $e) { $total_staff = 0; }
                    ?>
                    <div style="font-size:2rem;"><strong><?php echo $total_staff; ?></strong></div>
                </div>
            </div>
        </div>

        <!-- Total Customers Card -->
        <div class="col-md-3">
            <div class="card dashboard-card" style="background:#f472b6;color:#fff;">
                <div class="card-header">Total Customers</div>
                <div class="card-body">
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT COUNT(*) as total FROM customers");
                        $total_customers = $stmt->fetch()['total'];
                    } catch (Exception $e) { $total_customers = 0; }
                    ?>
                    <div style="font-size:2rem;"><strong><?php echo $total_customers; ?></strong></div>
                </div>
            </div>
        </div>

        <!-- Total Menu Items Card -->
        <div class="col-md-3">
            <div class="card dashboard-card" style="background:#10b981;color:#fff;">
                <div class="card-header">Total Menu Items</div>
                <div class="card-body">
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT COUNT(*) as total FROM menu");
                        $total_menu = $stmt->fetch()['total'];
                    } catch (Exception $e) { $total_menu = 0; }
                    ?>
                    <div style="font-size:2rem;"><strong><?php echo $total_menu; ?></strong></div>
                </div>
            </div>
        </div>

        <!-- Total Expenses Card -->
        <div class="col-md-3">
            <div class="card dashboard-card" style="background:#ef4444;color:#fff;">
                <div class="card-header">Total Expenses (This Month)</div>
                <div class="card-body">
                    <?php
                    // Inventory Purchases (this month)
                    $inventory_exp = 0;
                    try {
                        $pdo->query("SELECT created_at FROM inventory_purchases LIMIT 1");
                        $stmt = $pdo->prepare("SELECT IFNULL(SUM(total_price),0) as total FROM inventory_purchases WHERE MONTH(purchased_at) = MONTH(CURDATE()) AND YEAR(purchased_at) = YEAR(CURDATE())");
                        $stmt->execute();
                        $inventory_exp = $stmt->fetch()['total'];
                    } catch (Exception $e) { $inventory_exp = 0; }

                    // Paid Bills (this month)
                    $bills_exp = 0;
                    try {
                        $pdo->query("SELECT created_at FROM rent_bills LIMIT 1");
                        $stmt = $pdo->prepare("SELECT IFNULL(SUM(amount),0) as total FROM rent_bills WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                        $stmt->execute();
                        $bills_exp += $stmt->fetch()['total'];
                    } catch (Exception $e) {}
                    try {
                        $pdo->query("SELECT created_at FROM electricity_bills LIMIT 1");
                        $stmt = $pdo->prepare("SELECT IFNULL(SUM(amount),0) as total FROM electricity_bills WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                        $stmt->execute();
                        $bills_exp += $stmt->fetch()['total'];
                    } catch (Exception $e) {}
                    try {
                        $pdo->query("SELECT created_at FROM other_bills LIMIT 1");
                        $stmt = $pdo->prepare("SELECT IFNULL(SUM(amount),0) as total FROM other_bills WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                        $stmt->execute();
                        $bills_exp += $stmt->fetch()['total'];
                    } catch (Exception $e) {}

                    // Sahakari Withdrawals (this month)
                    $sahakari_exp = 0;
                    try {
                        $pdo->query("SELECT created_at FROM sahakari_withdrawals LIMIT 1");
                        $stmt = $pdo->prepare("SELECT IFNULL(SUM(amount),0) as total FROM sahakari_withdrawals WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                        $stmt->execute();
                        $sahakari_exp = $stmt->fetch()['total'];
                    } catch (Exception $e) { $sahakari_exp = 0; }

                    $total_expenses = $inventory_exp + $bills_exp + $sahakari_exp;
                    ?>
                    <div style="font-size:1.1rem;">Inventory: <strong><?php echo number_format($inventory_exp,2); ?></strong></div>
                    <div style="font-size:1.1rem;">Bills: <strong><?php echo number_format($bills_exp,2); ?></strong></div>
                    <div style="font-size:1.1rem;">Sahakari: <strong><?php echo number_format($sahakari_exp,2); ?></strong></div>
                    <div style="font-size:1.2rem; margin-top:8px;">Total: <strong><?php echo number_format($total_expenses,2); ?></strong></div>
                </div>
            </div>
        </div>
        <!-- Bills (Rent/Electricity/Other) Summary Card -->
        <div class="col-md-3">
            <div class="card dashboard-card" style="background:#f43f5e;color:#fff;">
                <div class="card-header">Bills (This Month)</div>
                <div class="card-body">
                    <?php
                    // Try to use BS columns, fallback to created_at if present, else show 0
                    $rent = $electricity = $other = 0;
                    $has_bs = false;
                    $has_created = false;
                    try {
                        $pdo->query("SELECT bs_month, bs_year FROM rent_bills LIMIT 1");
                        $pdo->query("SELECT bs_month, bs_year FROM electricity_bills LIMIT 1");
                        $pdo->query("SELECT bs_month, bs_year FROM other_bills LIMIT 1");
                        $has_bs = true;
                    } catch (Exception $e) { $has_bs = false; }
                    if (!$has_bs) {
                        try {
                            $pdo->query("SELECT created_at FROM rent_bills LIMIT 1");
                            $pdo->query("SELECT created_at FROM electricity_bills LIMIT 1");
                            $pdo->query("SELECT created_at FROM other_bills LIMIT 1");
                            $has_created = true;
                        } catch (Exception $e) { $has_created = false; }
                    }
                    if ($has_bs) {
                        $bs_month = date('n');
                        $bs_year = date('Y');
                        $stmt = $pdo->prepare("SELECT IFNULL(SUM(amount),0) as total FROM rent_bills WHERE bs_month = ? AND bs_year = ?");
                        $stmt->execute([$bs_month, $bs_year]);
                        $rent = $stmt->fetch()['total'];
                        $stmt = $pdo->prepare("SELECT IFNULL(SUM(amount),0) as total FROM electricity_bills WHERE bs_month = ? AND bs_year = ?");
                        $stmt->execute([$bs_month, $bs_year]);
                        $electricity = $stmt->fetch()['total'];
                        $stmt = $pdo->prepare("SELECT IFNULL(SUM(amount),0) as total FROM other_bills WHERE bs_month = ? AND bs_year = ?");
                        $stmt->execute([$bs_month, $bs_year]);
                        $other = $stmt->fetch()['total'];
                    } elseif ($has_created) {
                        $stmt = $pdo->prepare("SELECT IFNULL(SUM(amount),0) as total FROM rent_bills WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                        $stmt->execute();
                        $rent = $stmt->fetch()['total'];
                        $stmt = $pdo->prepare("SELECT IFNULL(SUM(amount),0) as total FROM electricity_bills WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                        $stmt->execute();
                        $electricity = $stmt->fetch()['total'];
                        $stmt = $pdo->prepare("SELECT IFNULL(SUM(amount),0) as total FROM other_bills WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                        $stmt->execute();
                        $other = $stmt->fetch()['total'];
                    } else {
                        $rent = $electricity = $other = 0;
                    }
                    $total_bills = $rent + $electricity + $other;
                    ?>
                    <div style="font-size:1.1rem;">Rent: <strong><?php echo number_format($rent,2); ?></strong></div>
                    <div style="font-size:1.1rem;">Electricity: <strong><?php echo number_format($electricity,2); ?></strong></div>
                    <div style="font-size:1.1rem;">Other: <strong><?php echo number_format($other,2); ?></strong></div>
                    <div style="font-size:1.2rem; margin-top:8px;">Total: <strong><?php echo number_format($total_bills,2); ?></strong></div>
                </div>
            </div>
        </div>
        <!-- Sahakari Summary Card -->
        <div class="col-md-3">
            <div class="card dashboard-card" style="background:#a21caf;color:#fff;">
                <div class="card-header">Sahakari (Net Balance)</div>
                <div class="card-body">
                    <?php
                    // Sahakari net balance
                    $stmt = $pdo->query("SELECT IFNULL(SUM(amount),0) as total_investment FROM sahakari_investments");
                    $total_investment = $stmt->fetch()['total_investment'];
                    $stmt = $pdo->query("SELECT IFNULL(SUM(amount),0) as total_withdrawal FROM sahakari_withdrawals");
                    $total_withdrawal = $stmt->fetch()['total_withdrawal'];
                    $net_balance = $total_investment - $total_withdrawal;
                    ?>
                    <div style="font-size:1.1rem;">Investment: <strong><?php echo number_format($total_investment,2); ?></strong></div>
                    <div style="font-size:1.1rem;">Withdrawal: <strong><?php echo number_format($total_withdrawal,2); ?></strong></div>
                    <div style="font-size:1.2rem; margin-top:8px;">Net: <strong><?php echo number_format($net_balance,2); ?></strong></div>
                </div>
            </div>
        </div>
    </div>
    <!-- Quick Links Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card dashboard-card" style="background:#fff;color:#222;">
                <div class="card-header" style="background:#f3f4f6;">Quick Links</div>
                <div class="card-body d-flex flex-wrap justify-content-center gap-3">
                    <a href="/possystem/admin/staffsalaries.php" class="btn btn-outline-primary">Staff Salaries</a>
                    <a href="/possystem/admin/bills.php" class="btn btn-outline-danger">Bills</a>
                    <a href="/possystem/admin/sahakari.php" class="btn btn-outline-dark">Sahakari</a>
                    <a href="/possystem/admin/sahakari_report.php" class="btn btn-outline-secondary">Sahakari Report</a>
                </div>
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
const inventoryDataOffline = <?php echo json_encode(array_map(function($d){return (float)$d['inventory'];}, $inventory_days_offline)); ?>;
const inventoryDataOnline = <?php echo json_encode(array_map(function($d){return (float)$d['inventory'];}, $inventory_days_online)); ?>;
const ctx2 = document.getElementById('inventoryBarChart').getContext('2d');
new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: inventoryLabels,
        datasets: [
            {
                label: 'Inventory Purchase (NPR) - Offline',
                data: inventoryDataOffline,
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