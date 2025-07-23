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

// Fetch recently checked out bills (online/offline)
$stmt = $pdo->prepare("SELECT b.id, b.table_id, b.total_amount, b.payment_type, b.closed_at, t.table_number FROM bills b JOIN tables t ON b.table_id = t.id WHERE b.status = 'closed' AND (b.payment_type = 'online' OR b.payment_type = 'offline') ORDER BY b.closed_at DESC LIMIT 20");
$stmt->execute();
$bills = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Print Bill - Cafe Management</title>
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
          </ul>
        </div>
      </div>
    </nav>
    <div class='container mt-4'>
    <h3>Recently Checked Out Bills (Online/Offline)</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Bill ID</th>
                <th>Table</th>
                <th>Total Amount</th>
                <th>Payment Type</th>
                <th>Closed At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($bills as $bill): ?>
            <tr>
                <td><?php echo $bill['id']; ?></td>
                <td><?php echo htmlspecialchars($bill['table_number']); ?></td>
                <td><?php echo number_format($bill['total_amount'],2); ?></td>
                <td><?php echo ucfirst($bill['payment_type']); ?></td>
                <td><?php echo $bill['closed_at']; ?></td>
                <td>
                    <a href="printbill.php?bill_id=<?php echo $bill['id']; ?>" class="btn btn-primary btn-sm">Print</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    // If a bill is selected, show printable view
    if (isset($_GET['bill_id'])) {
        $bill_id = intval($_GET['bill_id']);
        // Fetch restaurant details
        $stmt = $pdo->query("SELECT * FROM restaurant_details LIMIT 1");
        $rest = $stmt->fetch();
        $stmt = $pdo->prepare("SELECT b.*, t.table_number, c.name as customer_name FROM bills b JOIN tables t ON b.table_id = t.id LEFT JOIN customers c ON b.customer_id = c.id WHERE b.id = ?");
        $stmt->execute([$bill_id]);
        $bill = $stmt->fetch();
        if ($bill) {
            $stmt = $pdo->prepare("SELECT bi.*, mi.name FROM bill_items bi JOIN menu_items mi ON bi.menu_item_id = mi.id WHERE bi.bill_id = ?");
            $stmt->execute([$bill_id]);
            $items = $stmt->fetchAll();
            // Calculate VAT and subtotal
            $vat = round($bill['total_amount'] * 0.13, 2);
            $subtotal = round($bill['total_amount'] - $vat, 2);
            echo '<div id="bill-print-area">';
            echo '<!DOCTYPE html><html><head><title>Bill</title></head><body>';
            echo '<h2>'.htmlspecialchars($rest['name'] ?? 'Restaurant Name').'</h2>';
            echo '<p>'.htmlspecialchars($rest['address'] ?? 'Address').'</p>';
            echo '<p>Phone: '.htmlspecialchars($rest['phone'] ?? '').'</p>';
            echo '<p>Date: '.date('Y-m-d', strtotime($bill['closed_at'])).'</p>';
            echo '<p>Time: '.date('H:i:s', strtotime($bill['closed_at'])).'</p>';
            echo '<p>Bill No.: '.$bill['id'].'</p>';
            echo '<p>PAN/VAT: '.htmlspecialchars($rest['pan_vat'] ?? '').'</p>';
            echo '<hr>';
            echo '<table border="1" width="100%" cellspacing="0" cellpadding="5">';
            echo '<tr><th>Qty</th><th>Item</th><th>Rate (Rs.)</th><th>Total (Rs.)</th></tr>';
            foreach ($items as $item) {
                echo '<tr><td>'.$item['quantity'].'</td><td>'.htmlspecialchars($item['name']).'</td><td>'.number_format($item['price'],2).'</td><td>'.number_format($item['price']*$item['quantity'],2).'</td></tr>';
            }
            echo '</table>';
            echo '<p>Subtotal: Rs. '.number_format($subtotal,2).'</p>';
            echo '<p>VAT (13%): Rs. '.number_format($vat,2).'</p>';
            echo '<p>Grand Total: Rs. '.number_format($bill['total_amount'],2).'</p>';
            echo '<p>Paid (Cash/Card): Rs. '.number_format($bill['total_amount'],2).'</p>';
            echo '<hr>';
            echo '<p>Thank you! Please visit again.</p>';
            echo '</body></html>';
            echo '</div>';
            echo '<button class="btn btn-success mt-3" onclick="printBill()">Print Bill</button>';
            echo '<script>function printBill(){var printContents = document.getElementById(\'bill-print-area\').innerHTML;var win = window.open(\'\',\'_blank\');win.document.write(printContents);win.document.close();win.print();}</script>';
        }
    }
    ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
