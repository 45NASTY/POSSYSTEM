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

// Fetch recently checked out bills (online/offline/split)
$stmt = $pdo->prepare("SELECT b.id, b.table_id, b.total_amount, b.payment_type, b.closed_at, t.table_number FROM bills b JOIN tables t ON b.table_id = t.id WHERE b.status = 'closed' AND (b.payment_type = 'online' OR b.payment_type = 'offline' OR b.payment_type = 'split') ORDER BY b.closed_at DESC LIMIT 20");
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
            <li class="nav-item"><a class="nav-link" href="/possystem/public/logout.php">Logout</a></li>
          </ul>
        </div>
      </div>
    </nav>
    <div class='container mt-4'>
    <h3>Recently Checked Out Bills (Online/Offline/Split)</h3>
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
            echo '<div id="bill-print-area" class="receipt-preview">';
            echo '<div class="center">';
            echo '<strong>'.htmlspecialchars($rest['name'] ?? 'Restaurant Name').'</strong><br>';
            echo (isset($rest['address']) ? htmlspecialchars($rest['address']).'<br>' : '');
            echo 'Phone: '.htmlspecialchars($rest['phone'] ?? '').'<br>';
            echo 'PAN No: '.htmlspecialchars($rest['pan_vat'] ?? '').'<br>';
            echo '</div>';
            echo '<div class="line"></div>';
            echo 'Date: '.date('Y-m-d', strtotime($bill['closed_at'])).' &nbsp; Time: '.date('H:i:s', strtotime($bill['closed_at'])).'<br>';
            echo 'Bill No.: '.$bill['id'].'<br>';
            echo 'Table: '.htmlspecialchars($bill['table_number']).'<br>';
            if (!empty($bill['customer_name'])) echo 'Customer: '.htmlspecialchars($bill['customer_name']).'<br>';
            echo '<div class="line"></div>';
            echo '<table cellspacing="0" cellpadding="0">';
            echo '<tr><td>Qty</td><td>Item</td><td style="text-align:right;">Rate</td><td style="text-align:right;">Total</td></tr>';
            foreach ($items as $item) {
                echo '<tr>';
                echo '<td>'.(int)$item['quantity'].'</td>';
                echo '<td>'.htmlspecialchars($item['name']).'</td>';
                echo '<td style="text-align:right;">'.number_format($item['price'],2).'</td>';
                echo '<td style="text-align:right;">'.number_format($item['price']*$item['quantity'],2).'</td>';
                echo '</tr>';
            }
            echo '</table>';
            echo '<div class="line"></div>';
            echo '<table class="totals">';
            echo '<tr><td>Subtotal</td><td style="text-align:right;">'.number_format($subtotal,2).'</td></tr>';
            echo '<tr><td>VAT (13%)</td><td style="text-align:right;">'.number_format($vat,2).'</td></tr>';
            echo '<tr><td><strong>Grand Total</strong></td><td style="text-align:right;"><strong>'.number_format($bill['total_amount'],2).'</strong></td></tr>';
            echo '</table>';
            if ($bill['payment_type'] === 'split') {
                $split_stmt = $pdo->prepare("SELECT bp.*, c.name as customer_name FROM bill_payments bp LEFT JOIN customers c ON bp.customer_id = c.id WHERE bp.bill_id = ?");
                $split_stmt->execute([$bill_id]);
                $splits = $split_stmt->fetchAll();
                echo '<div class="line"></div>';
                echo '<div><strong>Split Payment:</strong></div>';
                echo '<table>';
                foreach ($splits as $sp) {
                    echo '<tr>';
                    echo '<td>'.ucfirst($sp['payment_type']).'</td>';
                    echo '<td style="text-align:right;">'.number_format($sp['amount'],2).'</td>';
                    echo '<td>'.($sp['payment_type']==='credit' ? htmlspecialchars($sp['customer_name']) : '').'</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<div class="line"></div>';
                echo 'Paid ('.ucfirst($bill['payment_type']).'): '.number_format($bill['total_amount'],2).'<br>';
            }
            echo '<div class="line"></div>';
            echo '<div class="center">Thank you! Please visit again.</div>';
            echo '</div>';
            echo '<button class="btn btn-success mt-3" onclick="printBill()">Print Bill</button>';
            // Print styles for receipt
            echo '<style>\n.receipt-preview { max-width: 400px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #0001; padding: 16px; font-family: monospace; font-size: 14px; } .receipt-preview .center { text-align: center; } .receipt-preview .line { border-top: 2.5px dashed #111; margin: 12px 0; } .receipt-preview table { width: 100%; } .receipt-preview td { vertical-align: top; } .receipt-preview .totals td { padding-top: 5px; } @media print { body * { visibility: hidden !important; } #bill-print-area, #bill-print-area * { visibility: visible !important; } #bill-print-area { position: absolute; left: 0; top: 0; width: 58mm !important; min-width: 0 !important; max-width: none !important; font-size: 12px !important; box-shadow: none !important; border-radius: 0 !important; background: #fff !important; padding: 10px !important; } .receipt-preview .line { border-top: 2.5px dashed #111 !important; margin: 12px 0 !important; } } </style>';
            echo "<script>function printBill(){var printContents = document.getElementById('bill-print-area').innerHTML;var win = window.open('', '_blank');win.document.write('<html><head><title>Receipt</title></head><body style=\"margin:0;padding:0;\">'+printContents+'</body></html>');win.document.close();win.print();}</script>";
        }
    }
    ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
