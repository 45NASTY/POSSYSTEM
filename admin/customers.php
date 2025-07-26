<?php
require_once __DIR__ . '/../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: /possystem/index.php');
    exit;
}
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user || $user['role'] !== 'admin') {
    header('Location: /possystem/index.php');
    exit;
}

// Handle add/edit actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add customer
    if (isset($_POST['add_customer'])) {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $credit_limit = floatval($_POST['credit_limit']);
        $stmt = $pdo->prepare("INSERT INTO customers (name, phone, email, credit_limit) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $phone, $email, $credit_limit]);
        header('Location: customers.php');
        exit;
    }
    // Update credit limit
    if (isset($_POST['update_credit_limit'])) {
        $id = $_POST['customer_id'];
        $credit_limit = floatval($_POST['credit_limit']);
        $stmt = $pdo->prepare("UPDATE customers SET credit_limit=? WHERE id=?");
        $stmt->execute([$credit_limit, $id]);
        header('Location: customers.php');
        exit;
    }
    // Update pending credit
    if (isset($_POST['update_pending_credit'])) {
        $id = $_POST['customer_id'];
        $pending_credit = floatval($_POST['pending_credit']);
        // Update pending_credit directly in customers table
        $stmt = $pdo->prepare("UPDATE customers SET pending_credit=? WHERE id=?");
        $stmt->execute([$pending_credit, $id]);
        header('Location: customers.php');
        exit;
    }
}

// Fetch customers and their credit info
$stmt = $pdo->prepare("SELECT c.*, c.pending_credit FROM customers c ORDER BY c.name");
$stmt->execute();
$customers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Customer Management</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='../public/style.css' rel='stylesheet'>
</head>
<body>
<nav class="navbar navbar-expand-lg mb-4">
  <div class="container-fluid">
    <?php
    require_once __DIR__ . '/../config.php';
    $rest = $pdo->query("SELECT name FROM restaurant_details LIMIT 1")->fetch();
    $restaurant_name = $rest ? $rest['name'] : 'Cafe POS';
    ?>
    <a class="navbar-brand" href="/possystem/public/dashboard.php"><?php echo htmlspecialchars($restaurant_name); ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="#navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="/possystem/public/dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/restaurant.php">Restaurant</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/admin/createstaff.php">Create Staff</a></li>
        <li class="nav-item"><a class="nav-link active" href="/possystem/admin/customers.php">Customers</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/tables.php">Tables</a></li>
        <li class="nav-item"><a class="nav-link" href="/possystem/public/logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class='container mt-4' style="max-width: 1200px;">
  <div class="card p-5 mb-5 bg-light bg-opacity-75 shadow-lg" style="font-size: 1.25rem;">
    <h2 class="mb-4" style="font-size:2rem;">Register Regular Customer</h2>
    <form method='post' class='row g-4'>
        <div class='col-md-3'><input type='text' name='name' class='form-control form-control-lg' placeholder='Name' required></div>
        <div class='col-md-2'><input type='text' name='phone' class='form-control form-control-lg' placeholder='Phone' required></div>
        <div class='col-md-3'><input type='email' name='email' class='form-control form-control-lg' placeholder='Email' required></div>
        <div class='col-md-2'><input type='number' step='0.01' name='credit_limit' class='form-control form-control-lg' placeholder='Credit Limit' required></div>
        <div class='col-md-2'><button type='submit' name='add_customer' class='btn btn-success btn-lg'>Register</button></div>
    </form>
  </div>
  <div class="card p-5 bg-light bg-opacity-75 shadow-lg" style="font-size: 1.25rem; overflow-x:auto;">
    <h2 class="mb-4" style="font-size:2rem;">Regular Customers</h2>
    <div class="mb-3">
      <input type="text" id="customerSearch" class="form-control form-control-lg" placeholder="Search customers by name, phone, or email...">
    </div>
    <div style="width:100%; overflow-x:auto;">
      <table id="customersTable" class='table table-bordered table-hover bg-white bg-opacity-75' style="font-size:1.15rem; width:100%; min-width:900px;">
          <thead>
              <tr>
                  <th>Name</th>
                  <th>Phone</th>
                  <th>Email</th>
                  <th>Credit Limit</th>
                  <th>Pending Credit</th>
                  <th>Update Credit Limit</th>
                  <th>Update Pending Credit</th>
                  <th>Edit Phone</th>
                  <th>Print Credit Bill</th>
              </tr>
          </thead>
          <tbody id="customersTableBody">
          <?php foreach ($customers as $cust): ?>
              <tr>
                  <td><?php echo htmlspecialchars($cust['name']); ?></td>
                 <td><?php echo htmlspecialchars($cust['phone']); ?></td>
                 <td><?php echo htmlspecialchars($cust['email']); ?></td>
                 <td><?php echo number_format($cust['credit_limit'],2); ?></td>
                 <td><?php echo number_format($cust['pending_credit'],2); ?></td>
                 <td>
                     <form method='post' class='d-flex flex-wrap'>
                         <input type='hidden' name='customer_id' value='<?php echo $cust['id']; ?>'>
                         <input type='number' step='0.01' name='credit_limit' value='<?php echo $cust['credit_limit']; ?>' class='form-control form-control-lg me-2 mb-2' required>
                         <button type='submit' name='update_credit_limit' class='btn btn-primary btn-lg'>Update</button>
                     </form>
                 </td>
                 <td>
                     <form method='post' class='d-flex flex-wrap'>
                         <input type='hidden' name='customer_id' value='<?php echo $cust['id']; ?>'>
                         <input type='number' step='0.01' name='pending_credit' value='<?php echo $cust['pending_credit']; ?>' class='form-control form-control-lg me-2 mb-2' required>
                         <button type='submit' name='update_pending_credit' class='btn btn-info btn-lg'>Update</button>
                     </form>
                 </td>
                 <td>
                     <button type="button" class="btn btn-warning btn-lg" data-bs-toggle="modal" data-bs-target="#editPhoneModal<?php echo $cust['id']; ?>">Update</button>
                     <!-- Modal -->
                     <div class="modal fade" id="editPhoneModal<?php echo $cust['id']; ?>" tabindex="-1" aria-labelledby="editPhoneModalLabel<?php echo $cust['id']; ?>" aria-hidden="true">
                       <div class="modal-dialog">
                         <div class="modal-content">
                           <form method="post">
                             <div class="modal-header">
                               <h5 class="modal-title" id="editPhoneModalLabel<?php echo $cust['id']; ?>">Edit Phone Number</h5>
                               <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                             </div>
                             <div class="modal-body">
                               <input type="hidden" name="customer_id" value="<?php echo $cust['id']; ?>">
                               <div class="mb-3">
                                 <label class="form-label">Current Number</label>
                                 <input type="text" class="form-control" value="<?php echo htmlspecialchars($cust['phone']); ?>" readonly>
                               </div>
                               <div class="mb-3">
                                 <label class="form-label">New Number</label>
                                 <input type="text" name="phone" class="form-control" required pattern="[0-9+\- ]{5,20}" title="Enter a valid phone number">
                               </div>
                             </div>
                             <div class="modal-footer">
                               <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                               <button type="submit" name="update_phone" class="btn btn-primary">Change Phone Number</button>
                             </div>
                           </form>
                         </div>
                       </div>
                     </div>
                 </td>
                 <td>
                     <?php
                     // Find latest credit bill for this customer
                     $creditBillStmt = $pdo->prepare("SELECT id FROM bills WHERE customer_id = ? AND payment_type = 'credit' ORDER BY closed_at DESC LIMIT 1");
                     $creditBillStmt->execute([$cust['id']]);
                     $creditBillId = $creditBillStmt->fetchColumn();
                     if ($creditBillId) {
                         echo '<a href="/possystem/public/printbill.php?bill_id=' . $creditBillId . '&autoprint=1" class="btn btn-secondary btn-lg" target="_blank">Print Bill</a>';
                     } else {
                         echo '<button class="btn btn-secondary btn-lg" disabled>No Bill</button>';
                     }
                     ?>
                 </td>
              </tr>
          <?php endforeach; ?>
          </tbody>
      </table>
      <nav>
        <ul class="pagination justify-content-center" id="pagination"></ul>
      </nav>
    </div>
  </div>
</div>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
<script>
// Client-side search and pagination for customers table
const searchInput = document.getElementById('customerSearch');
const table = document.getElementById('customersTable');
const tbody = document.getElementById('customersTableBody');
const rows = Array.from(tbody.getElementsByTagName('tr'));
const rowsPerPage = 5;
let filteredRows = rows;
let currentPage = 1;

function renderTablePage(page) {
  tbody.innerHTML = '';
  const start = (page - 1) * rowsPerPage;
  const end = start + rowsPerPage;
  filteredRows.slice(start, end).forEach(row => tbody.appendChild(row));
  renderPagination(page);
}

function renderPagination(page) {
  const pagination = document.getElementById('pagination');
  pagination.innerHTML = '';
  const totalPages = Math.ceil(filteredRows.length / rowsPerPage) || 1;
  for (let i = 1; i <= totalPages; i++) {
    const li = document.createElement('li');
    li.className = 'page-item' + (i === page ? ' active' : '');
    const a = document.createElement('a');
    a.className = 'page-link';
    a.href = '#';
    a.textContent = i;
    a.onclick = function(e) {
      e.preventDefault();
      currentPage = i;
      renderTablePage(currentPage);
    };
    li.appendChild(a);
    pagination.appendChild(li);
  }
}

function filterRows() {
  const query = searchInput.value.toLowerCase();
  filteredRows = rows.filter(row => {
    const cells = row.getElementsByTagName('td');
    return (
      cells[0].textContent.toLowerCase().includes(query) ||
      cells[1].textContent.toLowerCase().includes(query) ||
      cells[2].textContent.toLowerCase().includes(query)
    );
  });
  currentPage = 1;
  renderTablePage(currentPage);
}

searchInput.addEventListener('input', filterRows);

// Initial render
filterRows();
</script>
</body>
</html>
