<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /possystem/index.php');
    exit;
}

// Handle add/edit/delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['category_name']);
        $stmt = $pdo->prepare("INSERT INTO menu_categories (name) VALUES (?)");
        $stmt->execute([$name]);
    }
    if (isset($_POST['add_item'])) {
        $name = trim($_POST['item_name']);
        $category_id = $_POST['category_id'];
        $price = $_POST['price'];
        $stmt = $pdo->prepare("INSERT INTO menu_items (name, category_id, price) VALUES (?, ?, ?)");
        $stmt->execute([$name, $category_id, $price]);
    }
    if (isset($_POST['edit_item'])) {
        $id = $_POST['item_id'];
        $name = trim($_POST['item_name']);
        $category_id = $_POST['category_id'];
        $price = $_POST['price'];
        $stmt = $pdo->prepare("UPDATE menu_items SET name=?, category_id=?, price=? WHERE id=?");
        $stmt->execute([$name, $category_id, $price, $id]);
    }
    if (isset($_POST['delete_item'])) {
        $id = $_POST['item_id'];
        $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id=?");
        $stmt->execute([$id]);
    }
    header('Location: menu.php');
    exit;
}

// Fetch categories and items
$categories = $pdo->query("SELECT * FROM menu_categories ORDER BY name")->fetchAll();
$items = $pdo->query("SELECT mi.*, mc.name as category FROM menu_items mi JOIN menu_categories mc ON mi.category_id = mc.id ORDER BY mc.name, mi.name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../public/style.css" rel="stylesheet">
</head>
<body>
<?php include '../public/navbar.php'; ?>
<div class='container mt-4' style="max-width: 1200px;">
  <div class="card p-5 mb-5 bg-light bg-opacity-75 shadow-lg" style="font-size: 1.25rem;">
    <h2 class="mb-4" style="font-size:2rem;">Menu Categories</h2>
    <form method="post" class="row g-4 mb-3">
        <div class="col-md-4"><input type="text" name="category_name" class="form-control form-control-lg" placeholder="New Category" required></div>
        <div class="col-md-2"><button type="submit" name="add_category" class="btn btn-primary btn-lg">Add Category</button></div>
    </form>
    <ul class="list-group mb-4">
        <?php foreach ($categories as $cat): ?>
            <li class="list-group-item fs-5"><?php echo htmlspecialchars($cat['name']); ?></li>
        <?php endforeach; ?>
    </ul>
  </div>
  <div class="card p-5 bg-light bg-opacity-75 shadow-lg" style="font-size: 1.25rem; overflow-x:auto;">
    <h2 class="mb-4" style="font-size:2rem;">Menu Items</h2>
    <form method="post" class="row g-4 mb-3">
        <div class="col-md-3"><input type="text" name="item_name" class="form-control form-control-lg" placeholder="Item Name" required></div>
        <div class="col-md-3">
            <select name="category_id" class="form-select form-select-lg" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><input type="number" step="0.01" name="price" class="form-control form-control-lg" placeholder="Price" required></div>
        <div class="col-md-2"><button type="submit" name="add_item" class="btn btn-success btn-lg">Add Item</button></div>
    </form>
    <div style="width:100%; overflow-x:auto;">
      <table class="table table-bordered table-hover bg-white bg-opacity-75" style="font-size:1.15rem; width:100%; min-width:900px;">
          <thead>
              <tr>
                  <th>Name</th>
                  <th>Category</th>
                  <th>Price</th>
                  <th>Actions</th>
              </tr>
          </thead>
          <tbody>
          <?php foreach ($items as $item): ?>
              <tr>
                  <form method="post" class="d-flex flex-wrap">
                      <td><input type="text" name="item_name" value="<?php echo htmlspecialchars($item['name']); ?>" class="form-control form-control-lg" required></td>
                      <td>
                          <select name="category_id" class="form-select form-select-lg" required>
                              <?php foreach ($categories as $cat): ?>
                                  <option value="<?php echo $cat['id']; ?>" <?php if ($cat['id'] == $item['category_id']) echo 'selected'; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                              <?php endforeach; ?>
                          </select>
                      </td>
                      <td><input type="number" step="0.01" name="price" value="<?php echo $item['price']; ?>" class="form-control form-control-lg" required></td>
                      <td>
                          <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                          <button type="submit" name="edit_item" class="btn btn-primary btn-lg me-2 mb-2">Save</button>
                          <button type="submit" name="delete_item" class="btn btn-danger btn-lg mb-2" onclick="return confirm('Delete this item?')">Delete</button>
                      </td>
                  </form>
              </tr>
          <?php endforeach; ?>
          </tbody>
      </table>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>