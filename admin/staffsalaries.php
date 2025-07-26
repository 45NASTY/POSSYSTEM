
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

// Handle new staff creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_staff'])) {
    $name = trim($_POST['staff_name']);
    $phone = trim($_POST['staff_phone']);
    $position = trim($_POST['staff_position']);
    $pdo->prepare("INSERT INTO staff (name, phone, position) VALUES (?, ?, ?)")->execute([$name, $phone, $position]);
    $success = 'Staff added successfully!';
}

// Fetch staff
$staff = $pdo->query("SELECT * FROM staff ORDER BY name")->fetchAll();

// Handle salary payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_salary'])) {
    $staff_id = $_POST['staff_id'];
    $amount = $_POST['amount'];
    $month = $_POST['month'];
    $year = $_POST['year'];
    $pdo->prepare("INSERT INTO staff_salaries (staff_id, amount, month, year, paid_at) VALUES (?, ?, ?, ?, NOW())")
        ->execute([$staff_id, $amount, $month, $year]);
    $success = 'Salary paid successfully!';
}


// Fetch salary records
$salaries = $pdo->query("SELECT ss.*, s.name FROM staff_salaries ss JOIN staff s ON ss.staff_id = s.id ORDER BY ss.paid_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Staff Salaries</title>
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
            <li class="nav-item"><a class="nav-link" href="/possystem/admin/staffsalaries.php">Staff Salaries</a></li>
            <li class="nav-item"><a class="nav-link" href="/possystem/public/logout.php">Logout</a></li>
          </ul>
        </div>
      </div>
    </nav>
    <div class='container mt-4'>
        <h2>Staff Salaries</h2>
        <?php if (!empty($success)): ?>
            <div class='alert alert-success'><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Add Staff Form -->
        <div class='card mb-4'>
            <div class='card-header bg-light'><strong>Add New Staff</strong></div>
            <div class='card-body'>
                <form method='post' class='row g-2'>
                    <div class='col-md-4'>
                        <input type='text' name='staff_name' class='form-control' placeholder='Name' required>
                    </div>
                    <div class='col-md-3'>
                        <input type='text' name='staff_phone' class='form-control' placeholder='Phone'>
                    </div>
                    <div class='col-md-3'>
                        <input type='text' name='staff_position' class='form-control' placeholder='Position'>
                    </div>
                    <div class='col-md-2'>
                        <button type='submit' name='create_staff' class='btn btn-primary'>Add Staff</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Pay Salary Form (AD) -->
        <form method='post' class='row g-3 mb-4'>
            <div class='col-md-3'>
                <select name='staff_id' class='form-select' required>
                    <option value=''>Select Staff</option>
                    <?php foreach ($staff as $s): ?>
                        <option value='<?php echo $s['id']; ?>'><?php echo htmlspecialchars($s['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class='col-md-2'>
                <input type='number' name='amount' class='form-control' placeholder='Amount' min='0' required>
            </div>
            <div class='col-md-2'>
                <select name='month' class='form-select' required>
                    <option value=''>महिना (Month)</option>
                    <?php 
                    $bs_months = [1=>'Baisakh',2=>'Jestha',3=>'Ashadh',4=>'Shrawan',5=>'Bhadra',6=>'Ashwin',7=>'Kartik',8=>'Mangsir',9=>'Poush',10=>'Magh',11=>'Falgun',12=>'Chaitra'];
                    foreach ($bs_months as $num=>$mon): ?>
                        <option value='<?php echo $num; ?>'><?php echo $mon; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class='col-md-2'>
                <input type='number' name='year' class='form-control' placeholder='Year (BS)' min='2000' max='2200' value='<?php echo (date('Y')+57); ?>' required>
            </div>
            <div class='col-md-2'>
                <button type='submit' name='pay_salary' class='btn btn-success'>Pay Salary</button>
            </div>
        </form>

        <h4>Salary Records</h4>
        <table class='table table-bordered'>
            <thead>
                <tr>
                    <th>Staff</th>
                    <th>Amount</th>
                    <th>Month (BS)</th>
                    <th>Year (BS)</th>
                    <th>Paid At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($salaries as $sal): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($sal['name']); ?></td>
                        <td><?php echo number_format($sal['amount'],2); ?></td>
                        <td><?php 
                            $bs_months = [1=>'Baisakh',2=>'Jestha',3=>'Ashadh',4=>'Shrawan',5=>'Bhadra',6=>'Ashwin',7=>'Kartik',8=>'Mangsir',9=>'Poush',10=>'Magh',11=>'Falgun',12=>'Chaitra'];
                            echo isset($bs_months[$sal['month']]) ? $bs_months[$sal['month']] : $sal['month'];
                        ?></td>
                        <td><?php echo $sal['year']; ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($sal['paid_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>
