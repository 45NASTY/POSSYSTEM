<?php
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: /possystem/index.php');
    exit;
}
// Fetch users for filter
$users = $pdo->query("SELECT id, username, role FROM users WHERE role IN ('admin','staff') ORDER BY role, username")->fetchAll();
// Handle filter
$where = [];
$params = [];
if (!empty($_GET['user_id'])) {
    $where[] = "a.user_id = ?";
    $params[] = $_GET['user_id'];
}
if (!empty($_GET['from_date'])) {
    $where[] = "a.date >= ?";
    $params[] = $_GET['from_date'];
}
if (!empty($_GET['to_date'])) {
    $where[] = "a.date <= ?";
    $params[] = $_GET['to_date'];
}
$sql = "SELECT a.*, u.username, u.role FROM attendance a JOIN users u ON a.user_id = u.id";
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY a.date DESC, u.role, u.username";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendance = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Attendance Report - Cafe Management</title>
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
        <li class="nav-item"><a class="nav-link" href="/possystem/reports/sales.php">Sales Report</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/reports/inventory.php">Inventory Report</a></li>
        <li class="nav-item"><a class="nav-link active" href="/possystem/reports/attendance.php">Attendance Report</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class='container admin-card'>
    <div class='admin-title'>Attendance Report</div>
    <form method='get' class='row g-3 mb-4'>
        <div class='col-md-3'>
            <select name='user_id' class='form-select'>
                <option value=''>All Employees</option>
                <?php foreach ($users as $u): ?>
                    <option value='<?php echo $u['id']; ?>' <?php if (!empty($_GET['user_id']) && $_GET['user_id'] == $u['id']) echo 'selected'; ?>><?php echo htmlspecialchars($u['username'] . " (" . $u['role'] . ")"); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class='col-md-3'>
            <input type='date' name='from_date' class='form-control' value='<?php echo htmlspecialchars($_GET['from_date'] ?? ""); ?>' placeholder='From Date'>
        </div>
        <div class='col-md-3'>
            <input type='date' name='to_date' class='form-control' value='<?php echo htmlspecialchars($_GET['to_date'] ?? ""); ?>' placeholder='To Date'>
        </div>
        <div class='col-md-3'>
            <button type='submit' class='btn btn-primary w-100'>Filter</button>
        </div>
    </form>
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
