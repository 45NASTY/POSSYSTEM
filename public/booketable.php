<?php
require_once __DIR__ . '/../config.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: /possystem/index.php');
    exit;
}

// Fetch restaurant name for navbar
$rest = $pdo->query("SELECT name FROM restaurant_details LIMIT 1")->fetch();
$restaurant_name = $rest ? $rest['name'] : 'Cafe POS';

// Handle table booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_table'])) {
    $table_id = $_POST['table_id'];
    $pdo->prepare("UPDATE tables SET status='occupied' WHERE id=?")->execute([$table_id]);
    header('Location: booketable.php');
    exit;
}

$tables = $pdo->query("SELECT * FROM tables ORDER BY table_number")->fetchAll();
?>
<?php
// Fetch customers for booking (only those not already booked)
$customers = $pdo->query("SELECT * FROM customers WHERE id NOT IN (SELECT customer_id FROM bills WHERE status = 'open' AND customer_id IS NOT NULL)")->fetchAll();

// Handle customer booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_customer'])) {
    $customer_id = $_POST['customer_id'];
    // Create an open bill for the customer (no table assigned, use NULL for table_id)
    $stmt = $pdo->prepare("INSERT INTO bills (customer_id, table_id, status, created_at) VALUES (?, NULL, 'open', NOW())");
    $stmt->execute([$customer_id]);
    header('Location: booketable.php');
    exit;
}

// Fetch currently booked customers (open bills with customer, no table)
$booked_customers = $pdo->query("SELECT c.* FROM customers c JOIN bills b ON c.id = b.customer_id WHERE b.status = 'open' AND (b.table_id IS NULL OR b.table_id = '')")->fetchAll();
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Book a Table</title>
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
            <li class="nav-item"><a class="nav-link" href="/possystem/public/booketable.php">Table Booking</a></li>
            <li class="nav-item"><a class="nav-link" href="/possystem/public/billing.php">Table Billing</a></li>
            <li class="nav-item"><a class="nav-link" href="/possystem/public/printbill.php">Print Bill</a></li>
            <li class="nav-item"><a class="nav-link" href="/possystem/public/logout.php">Logout</a></li>
          </ul>
        </div>
      </div>
    </nav>
    <div class='container mt-4'>
        <h2>Book a Table</h2>
        <form method='post' class='mb-4'>
            <div class='row g-2'>
                <div class='col'>
                    <select name='table_id' class='form-select' required>
                        <option value=''>Select Table</option>
                        <?php foreach ($tables as $table): if ($table['status'] === 'available'): ?>
                            <option value='<?php echo $table['id']; ?>'><?php echo htmlspecialchars($table['table_number']); ?></option>
                        <?php endif; endforeach; ?>
                    </select>
                </div>
                <div class='col'>
                    <button type='submit' name='book_table' class='btn btn-primary'>Book Table</button>
                </div>
            </div>
        </form>

        <h2>Book a Customer</h2>
        <form method='post' class='mb-4'>
            <div class='row g-2'>
                <div class='col'>
                    <select name='customer_id' class='form-select' required>
                        <option value=''>Select Customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value='<?php echo $customer['id']; ?>'><?php echo htmlspecialchars($customer['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class='col'>
                    <button type='submit' name='book_customer' class='btn btn-success'>Book Customer</button>
                </div>
            </div>
        </form>
        <h4>Currently Booked Tables</h4>
        <ul class='list-group'>
            <?php foreach ($tables as $table): if ($table['status'] === 'occupied'): ?>
                <li class='list-group-item'>Table <?php echo htmlspecialchars($table['table_number']); ?></li>
            <?php endif; endforeach; ?>
        </ul>
        <h4 class='mt-4'>Currently Booked Customers</h4>
        <ul class='list-group'>
            <?php if (count($booked_customers) === 0): ?>
                <li class='list-group-item text-muted'>No customers currently booked.</li>
            <?php else: foreach ($booked_customers as $customer): ?>
                <li class='list-group-item'><?php echo htmlspecialchars($customer['name']); ?></li>
            <?php endforeach; endif; ?>
        </ul>
    </div>
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>
