<?php
require_once __DIR__ . '/../config.php';

// Authentication check (admin only)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /possystem/index.php');
    exit;
}

$rest = $pdo->query("SELECT name FROM restaurant_details LIMIT 1")->fetch();
$restaurant_name = $rest ? $rest['name'] : 'Cafe POS';

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
    <title>Sahakari Report</title>
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
        <h2>Sahakari Report</h2>
        <table class='table table-bordered'>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Total Investment</th>
                    <th>Total Withdrawal</th>
                    <th>Net Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sahakaris as $s): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['name']); ?></td>
                        <td><?php echo number_format($summaries[$s['id']]['invest'],2); ?></td>
                        <td><?php echo number_format($summaries[$s['id']]['withdraw'],2); ?></td>
                        <td><?php echo number_format($summaries[$s['id']]['invest'] - $summaries[$s['id']]['withdraw'],2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href='sahakari.php' class='btn btn-outline-primary mt-3'>Back to Sahakari</a>
    </div>
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>
