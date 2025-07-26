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

// Handle add sahakari
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sahakari'])) {
    $name = trim($_POST['sahakari_name']);
    $pdo->prepare("INSERT INTO sahakari (name) VALUES (?)")->execute([$name]);
    $success = 'Sahakari added!';
}

// Handle new investment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_investment'])) {
    $sahakari_id = $_POST['sahakari_id'];
    $amount = $_POST['investment_amount'];
    $pdo->prepare("INSERT INTO sahakari_investments (sahakari_id, amount, created_at) VALUES (?, ?, NOW())")
        ->execute([$sahakari_id, $amount]);
    $success = 'Investment added!';
}

// Handle new withdrawal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_withdrawal'])) {
    $sahakari_id = $_POST['sahakari_id'];
    $amount = $_POST['withdrawal_amount'];
    $pdo->prepare("INSERT INTO sahakari_withdrawals (sahakari_id, amount, created_at) VALUES (?, ?, NOW())")
        ->execute([$sahakari_id, $amount]);
    $success = 'Withdrawal added!';
}

// Fetch sahakari list
$sahakaris = $pdo->query("SELECT * FROM sahakari ORDER BY name")->fetchAll();

// Fetch investment/withdrawal summary
$summaries = [];
foreach ($sahakaris as $s) {
    $total_invest = $pdo->prepare("SELECT IFNULL(SUM(amount),0) FROM sahakari_investments WHERE sahakari_id=?");
    $total_invest->execute([$s['id']]);
    $invest = $total_invest->fetchColumn();
    $total_with = $pdo->prepare("SELECT IFNULL(SUM(amount),0) FROM sahakari_withdrawals WHERE sahakari_id=?");
    $total_with->execute([$s['id']]);
    $with = $total_with->fetchColumn();
    $summaries[$s['id']] = ['invest'=>$invest, 'withdraw'=>$with];
}
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Sahakari Management</title>
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
            <li class="nav-item"><a class="nav-link" href="/possystem/admin/sahakari.php">Sahakari</a></li>
            <li class="nav-item"><a class="nav-link" href="/possystem/public/logout.php">Logout</a></li>
          </ul>
        </div>
      </div>
    </nav>
    <div class='container mt-4'>
        <h2>Sahakari Management</h2>
        <?php if (!empty($success)): ?>
            <div class='alert alert-success'><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Add Sahakari Form -->
        <div class='card mb-4'>
            <div class='card-header bg-light'><strong>Add Sahakari</strong></div>
            <div class='card-body'>
                <form method='post' class='row g-2'>
                    <div class='col-md-6'>
                        <input type='text' name='sahakari_name' class='form-control' placeholder='Sahakari Name' required>
                    </div>
                    <div class='col-md-2'>
                        <button type='submit' name='add_sahakari' class='btn btn-primary'>Add Sahakari</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sahakari List and Actions -->
        <h4>Sahakari List</h4>
        <table class='table table-bordered'>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Total Investment</th>
                    <th>Total Withdrawal</th>
                    <th>New Investment</th>
                    <th>New Withdrawal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sahakaris as $s): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['name']); ?></td>
                        <td><?php echo number_format($summaries[$s['id']]['invest'],2); ?></td>
                        <td><?php echo number_format($summaries[$s['id']]['withdraw'],2); ?></td>
                        <td>
                            <form method='post' class='d-flex gap-2'>
                                <input type='hidden' name='sahakari_id' value='<?php echo $s['id']; ?>'>
                                <input type='number' name='investment_amount' class='form-control' placeholder='Amount' min='0' required>
                                <button type='submit' name='add_investment' class='btn btn-success btn-sm'>Add</button>
                            </form>
                        </td>
                        <td>
                            <form method='post' class='d-flex gap-2'>
                                <input type='hidden' name='sahakari_id' value='<?php echo $s['id']; ?>'>
                                <input type='number' name='withdrawal_amount' class='form-control' placeholder='Amount' min='0' required>
                                <button type='submit' name='add_withdrawal' class='btn btn-danger btn-sm'>Withdraw</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href='sahakari_report.php' class='btn btn-outline-info mt-3'>View Sahakari Report</a>
    </div>
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>
