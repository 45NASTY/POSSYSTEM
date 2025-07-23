<?php
session_start();
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Page</title>
    <link rel="stylesheet" href="../public/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            /* Use the same background and font as other pages */
        }
        .report-card {
            background: rgba(255,255,255,0.92);
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.12);
            padding: 40px 32px;
            max-width: 900px;
            margin: 60px auto;
            text-align: center;
            border: 1px solid #e3e3e3;
        }
        .report-icon {
            font-size: 48px;
            color: #2c3e50;
            margin-bottom: 18px;
        }
        .report-links {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-bottom: 32px;
        }
        .report-link {
            font-size: 1.1rem;
            padding: 10px 28px;
            border-radius: 8px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include '../public/navbar.php'; ?>
    <div class="container">
        <div class="report-card">
            <div class="report-icon">
                <i class="fa-solid fa-chart-line"></i>
            </div>
            <h2 style="font-size:2.3rem;">Report Page</h2>
            <p class="mb-4">View sales and inventory reports here. Select a report below:</p>
            <div class="report-links">
                <a href="sales.php" class="btn btn-primary report-link">
                    <i class="fa-solid fa-file-invoice-dollar"></i> Sales Report
                </a>
                <a href="inventory.php" class="btn btn-secondary report-link">
                    <i class="fa-solid fa-boxes-stacked"></i> Inventory Report
                </a>
                <a href="attendance.php" class="btn btn-success report-link">
                    <i class="fa-solid fa-user-check"></i> Attendance Report
                </a>
            </div>
        </div>
    </div>
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>
