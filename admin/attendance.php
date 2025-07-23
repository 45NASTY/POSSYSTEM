<?php
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: /possystem/index.php');
    exit;
}
// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $user_id = $_POST['user_id'];
    $date = date('Y-m-d');
    // Check if already marked
    $stmt = $pdo->prepare("SELECT id FROM attendance WHERE user_id=? AND date=?");
    $stmt->execute([$user_id, $date]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO attendance (user_id, date) VALUES (?, ?)");
        $stmt->execute([$user_id, $date]);
        $success = "Attendance marked!";
    } else {
        $error = "Attendance already marked for today.";
    }
}
// Fetch all staff and admin users
$users = $pdo->query("SELECT id, username, role FROM users WHERE role IN ('admin','staff') ORDER BY role, username")->fetchAll();
// Fetch today's attendance
$today = date('Y-m-d');
$attendance = $pdo->query("SELECT a.*, u.username, u.role FROM attendance a JOIN users u ON a.user_id = u.id WHERE a.date='$today' ORDER BY u.role, u.username")->fetchAll();
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Attendance - Cafe Management</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='/possystem/public/style.css' rel='stylesheet'>
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
        <li class="nav-item"><a class="nav-link" href="/possystem/admin/admin.php">Admin</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/billingmain.php">Billing</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/inventory.php">Inventory Management</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/reports/report.php">Report</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class='container admin-card'>
    <div class='admin-title'>Attendance (<?php echo date('Y-m-d'); ?>)</div>
    <?php if (!empty($error)): ?>
        <div class='alert alert-danger'><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class='alert alert-success'><?php echo $success; ?></div>
    <?php endif; ?>
    <form method='post' class='row g-3 mb-4'>
        <div class='col-md-6'>
            <select name='user_id' class='form-select' required>
                <option value=''>Select User</option>
                <?php foreach ($users as $u): ?>
                    <option value='<?php echo $u['id']; ?>'><?php echo htmlspecialchars($u['username'] . " (" . $u['role'] . ")"); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class='col-md-6'>
            <button type='submit' name='mark_attendance' class='btn btn-primary w-100'>Mark Attendance</button>
        </div>
    </form>
    <h4>Today's Attendance</h4>
    <table class='table table-bordered bg-white'>
        <thead><tr><th>User</th><th>Role</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($attendance as $a): ?>
            <tr>
                <td><?php echo htmlspecialchars($a['username']); ?></td>
                <td><?php echo htmlspecialchars($a['role']); ?></td>
                <td><?php echo htmlspecialchars($a['date']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>
