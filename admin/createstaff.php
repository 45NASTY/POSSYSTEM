<?php
require_once __DIR__ . '/../config.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: /possystem/index.php');
    exit;
}

// Handle staff creation, password change, and delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create staff
    if (isset($_POST['create_staff'])) {
        $username = trim($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Username already exists!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'staff')");
            $stmt->execute([$username, $password]);
            $success = "Staff user created successfully!";
        }
    }
    // Change password
    if (isset($_POST['change_password']) && isset($_POST['staff_id'])) {
        $staff_id = $_POST['staff_id'];
        $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=? AND role='staff'");
        $stmt->execute([$new_password, $staff_id]);
        $success = "Password updated for staff.";
    }
    // Delete staff
    if (isset($_POST['delete_staff']) && isset($_POST['staff_id'])) {
        $staff_id = $_POST['staff_id'];
        $stmt = $pdo->prepare("DELETE FROM users WHERE id=? AND role='staff'");
        $stmt->execute([$staff_id]);
        $success = "Staff deleted.";
    }
}

// Fetch all staff
$staff_stmt = $pdo->prepare("SELECT id, username FROM users WHERE role='staff' ORDER BY username");
$staff_stmt->execute();
$staff_list = $staff_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Staff - Cafe Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/possystem/public/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(90deg, #4f8cff 60%, #6a9cff 100%);">
  <div class="container-fluid">
    <?php
    require_once __DIR__ . '/../config.php';
    $rest = $pdo->query("SELECT name FROM restaurant_details LIMIT 1")->fetch();
    $restaurant_name = $rest ? $rest['name'] : 'Cafe POS';
    ?>
    <a class="navbar-brand fw-bold fs-3 text-white" href="/possystem/public/dashboard.php"><?php echo htmlspecialchars($restaurant_name); ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="#navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link text-white" href="/possystem/public/dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/possystem/public/restaurant.php">Restaurant</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/possystem/admin/createstaff.php">Create Staff</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/possystem/admin/createadmin.php">Create Admin</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/possystem/admin/customers.php">Customers</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/possystem/public/tables.php">Tables</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/possystem/public/logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class="container py-5" style="max-width: 1100px;">
  <div class="row g-4 align-items-stretch">
    <div class="col-lg-5">
      <div class="card shadow-lg p-4 bg-light bg-opacity-90 h-100 d-flex flex-column justify-content-center">
        <h2 class="mb-4 text-center" style="font-size:2rem;">Create Staff User</h2>
        <?php if (!empty($error)): ?>
          <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
          <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="post" class="row g-3">
          <div class="col-12">
            <input type="text" name="username" class="form-control form-control-lg" placeholder="Username" required>
          </div>
          <div class="col-12">
            <input type="password" name="password" class="form-control form-control-lg" placeholder="Password" required>
          </div>
          <div class="col-12 d-grid">
            <button type="submit" name="create_staff" class="btn btn-success btn-lg">Create Staff</button>
          </div>
        </form>
      </div>
    </div>
    <div class="col-lg-7">
      <div class="card shadow-lg p-4 bg-light bg-opacity-90 h-100">
        <h3 class="mb-4 text-center" style="font-size:1.5rem;">Staff List</h3>
        <div class="mb-3 d-flex justify-content-end">
          <input type="text" id="staffSearch" class="form-control form-control-lg" style="max-width: 320px;" placeholder="Search by name...">
        </div>
        <div class="table-responsive">
          <table class="table table-bordered table-hover bg-white bg-opacity-75 align-middle" style="min-width:400px;" id="staffTable">
            <thead class="table-light">
              <tr>
                <th>Username</th>
                <th style="width: 240px;">Change Password</th>
                <th style="width: 90px;">Delete</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($staff_list as $staff): ?>
              <tr>
                <td class="fw-semibold staff-username"><?php echo htmlspecialchars($staff['username']); ?></td>
                <td>
                  <form method="post" class="d-flex flex-nowrap align-items-center gap-2">
                    <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                    <input type="password" name="new_password" class="form-control form-control-sm" placeholder="New Password" required style="max-width:120px;">
                    <button type="submit" name="change_password" class="btn btn-primary btn-sm">Change</button>
                  </form>
                </td>
                <td>
                  <form method="post" onsubmit="return confirm('Delete this staff user?');">
                    <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                    <button type="submit" name="delete_staff" class="btn btn-danger btn-sm">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</script>
<script>
// Staff search filter
document.getElementById('staffSearch').addEventListener('input', function() {
  const search = this.value.toLowerCase();
  document.querySelectorAll('#staffTable tbody tr').forEach(row => {
    const name = row.querySelector('.staff-username').textContent.toLowerCase();
    row.style.display = name.includes(search) ? '' : 'none';
  });
});
</script>
</body>
</html>
