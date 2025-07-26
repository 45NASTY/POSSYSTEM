<?php
require_once __DIR__ . '/../config.php';

// Authentication check (admin only)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /possystem/index.php');
    exit;
}

// Fetch restaurant name for navbar
$rest = $pdo->query("SELECT name FROM restaurant_details LIMIT 1")->fetch();
$restaurant_name = $rest ? $rest['name'] : 'Cafe POS';

$bs_months = [1=>'Baisakh',2=>'Jestha',3=>'Ashadh',4=>'Shrawan',5=>'Bhadra',6=>'Ashwin',7=>'Kartik',8=>'Mangsir',9=>'Poush',10=>'Magh',11=>'Falgun',12=>'Chaitra'];

// Handle rent payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_rent'])) {
    $amount = $_POST['rent_amount'];
    $month = $_POST['rent_month'];
    $year = $_POST['rent_year'];
    $pdo->prepare("INSERT INTO rent_bills (amount, month, year, paid_at) VALUES (?, ?, ?, NOW())")
        ->execute([$amount, $month, $year]);
    $success = 'Rent paid successfully!';
}

// Handle electricity payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_electricity'])) {
    $amount = $_POST['elec_amount'];
    $month = $_POST['elec_month'];
    $year = $_POST['elec_year'];
    $pdo->prepare("INSERT INTO electricity_bills (amount, month, year, paid_at) VALUES (?, ?, ?, NOW())")
        ->execute([$amount, $month, $year]);
    $success = 'Electricity bill paid successfully!';
}

// Handle other bill payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_other_bill'])) {
    $title = trim($_POST['other_title']);
    $amount = $_POST['other_amount'];
    $month = $_POST['other_month'];
    $year = $_POST['other_year'];
    $pdo->prepare("INSERT INTO other_bills (title, amount, month, year, paid_at) VALUES (?, ?, ?, ?, NOW())")
        ->execute([$title, $amount, $month, $year]);
    $success = 'Other bill paid successfully!';
}

// Fetch rent records
$rents = $pdo->query("SELECT * FROM rent_bills ORDER BY paid_at DESC")->fetchAll();
// Fetch electricity records
$elecs = $pdo->query("SELECT * FROM electricity_bills ORDER BY paid_at DESC")->fetchAll();
// Fetch other bills records
$other_bills = $pdo->query("SELECT * FROM other_bills ORDER BY paid_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Rent, Electricity & Bills</title>
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
            <li class="nav-item"><a class="nav-link" href="/possystem/admin/admin.php">Admin Home</a></li>
            <li class="nav-item"><a class="nav-link" href="/possystem/admin/bills.php">Rent & Bills</a></li>
            <li class="nav-item"><a class="nav-link" href="/possystem/public/logout.php">Logout</a></li>
          </ul>
        </div>
      </div>
    </nav>
    <div class='container mt-4'>
        <h2>Rent, Electricity & Other Bills</h2>
        <?php if (!empty($success)): ?>
            <div class='alert alert-success'><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Rent Form -->
        <h4>Rent Payment</h4>
        <form method='post' class='row g-3 mb-4'>
            <div class='col-md-3'>
                <input type='number' name='rent_amount' class='form-control' placeholder='Amount' min='0' required>
            </div>
            <div class='col-md-2'>
                <select name='rent_month' class='form-select' required>
                    <option value=''>महिना (Month)</option>
                    <?php foreach ($bs_months as $num=>$mon): ?>
                        <option value='<?php echo $num; ?>'><?php echo $mon; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class='col-md-2'>
                <input type='number' name='rent_year' class='form-control' placeholder='Year (BS)' min='2000' max='2200' value='<?php echo (date('Y')+57); ?>' required>
            </div>
            <div class='col-md-2'>
                <button type='submit' name='pay_rent' class='btn btn-warning'>Pay Rent</button>
            </div>
        </form>
        <table class='table table-bordered'>
            <thead>
                <tr>
                    <th>Amount</th>
                    <th>Month (BS)</th>
                    <th>Year (BS)</th>
                    <th>Paid At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rents as $rent): ?>
                    <tr>
                        <td><?php echo number_format($rent['amount'],2); ?></td>
                        <td><?php echo isset($bs_months[$rent['month']]) ? $bs_months[$rent['month']] : $rent['month']; ?></td>
                        <td><?php echo $rent['year']; ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($rent['paid_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Electricity Form -->
        <h4>Electricity Bill Payment</h4>
        <form method='post' class='row g-3 mb-4'>
            <div class='col-md-3'>
                <input type='number' name='elec_amount' class='form-control' placeholder='Amount' min='0' required>
            </div>
            <div class='col-md-2'>
                <select name='elec_month' class='form-select' required>
                    <option value=''>महिना (Month)</option>
                    <?php foreach ($bs_months as $num=>$mon): ?>
                        <option value='<?php echo $num; ?>'><?php echo $mon; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class='col-md-2'>
                <input type='number' name='elec_year' class='form-control' placeholder='Year (BS)' min='2000' max='2200' value='<?php echo (date('Y')+57); ?>' required>
            </div>
            <div class='col-md-2'>
                <button type='submit' name='pay_electricity' class='btn btn-info'>Pay Electricity</button>
            </div>
        </form>
        <table class='table table-bordered'>
            <thead>
                <tr>
                    <th>Amount</th>
                    <th>Month (BS)</th>
                    <th>Year (BS)</th>
                    <th>Paid At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($elecs as $elec): ?>
                    <tr>
                        <td><?php echo number_format($elec['amount'],2); ?></td>
                        <td><?php echo isset($bs_months[$elec['month']]) ? $bs_months[$elec['month']] : $elec['month']; ?></td>
                        <td><?php echo $elec['year']; ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($elec['paid_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Other Bills Form -->
        <h4>Other Bills Payment</h4>
        <form method='post' class='row g-3 mb-4'>
            <div class='col-md-3'>
                <input type='text' name='other_title' class='form-control' placeholder='Bill Title' required>
            </div>
            <div class='col-md-2'>
                <input type='number' name='other_amount' class='form-control' placeholder='Amount' min='0' required>
            </div>
            <div class='col-md-2'>
                <select name='other_month' class='form-select' required>
                    <option value=''>महिना (Month)</option>
                    <?php foreach ($bs_months as $num=>$mon): ?>
                        <option value='<?php echo $num; ?>'><?php echo $mon; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class='col-md-2'>
                <input type='number' name='other_year' class='form-control' placeholder='Year (BS)' min='2000' max='2200' value='<?php echo (date('Y')+57); ?>' required>
            </div>
            <div class='col-md-2'>
                <button type='submit' name='pay_other_bill' class='btn btn-secondary'>Pay Other Bill</button>
            </div>
        </form>
        <table class='table table-bordered'>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Amount</th>
                    <th>Month (BS)</th>
                    <th>Year (BS)</th>
                    <th>Paid At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($other_bills as $ob): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ob['title']); ?></td>
                        <td><?php echo number_format($ob['amount'],2); ?></td>
                        <td><?php echo isset($bs_months[$ob['month']]) ? $bs_months[$ob['month']] : $ob['month']; ?></td>
                        <td><?php echo $ob['year']; ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($ob['paid_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>
