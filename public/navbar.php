<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container-fluid">
    <?php
    require_once __DIR__ . '/../config.php';
    $rest = $pdo->query("SELECT name FROM restaurant_details LIMIT 1")->fetch();
    $restaurant_name = $rest ? $rest['name'] : 'Cafe POS';
    ?>
    <a class="navbar-brand fw-bold fs-3" href="/POSSYSTEM/public/dashboard.php" style="color:#fff;"><?php echo htmlspecialchars($restaurant_name); ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse d-flex align-items-center justify-content-between" id="navbarNav">
      <ul class="navbar-nav ms-auto d-flex flex-row gap-3">
        <li class="nav-item"><a class="nav-link text-white" href="/POSSYSTEM/public/dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/POSSYSTEM/reports/sales.php">Sales Report</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/POSSYSTEM/reports/inventory.php">Inventory Report</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/POSSYSTEM/public/logout.php">Logout</a></li>
      </ul>
  </div>
</nav>
