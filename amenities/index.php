<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin','staff']);

$db = getDB();
$msg = $_GET['msg'] ?? '';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// ---------- SAVE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $amenity_name = trim($_POST['amenity_name'] ?? '');
    $price = (float)($_POST['price'] ?? 0);

    if ($amenity_name && $price >= 0) {
        if ($id) {
            $stmt = $db->prepare("UPDATE amenities SET amenity_name=?, price=? WHERE amenity_id=?");
            $stmt->execute([$amenity_name, $price, $id]);
            $msg = "Amenity updated.";
        } else {
            $stmt = $db->prepare("INSERT INTO amenities (amenity_name, price) VALUES (?,?)");
            $stmt->execute([$amenity_name, $price]);
            $msg = "Amenity added.";
        }
        header("Location: /mangima_resort/amenities/?msg=".urlencode($msg));
        exit;
    } else {
        $msg = "Enter name and valid price.";
    }
} elseif ($action === 'delete' && $id) {
    $stmt = $db->prepare("DELETE FROM amenities WHERE amenity_id=?");
    $stmt->execute([$id]);
    $msg = "Amenity deleted.";
    header("Location: /mangima_resort/amenities/?msg=".urlencode($msg));
    exit;
}

$editAmenity = null;
if ($action === 'edit' && $id) {
    $stmt = $db->prepare("SELECT * FROM amenities WHERE amenity_id=?");
    $stmt->execute([$id]);
    $editAmenity = $stmt->fetch();
}

$amenities = $db->query("SELECT * FROM amenities ORDER BY amenity_id")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Amenities - Mangima Resort</title>
    <link rel="stylesheet" href="/mangima_resort/assets/css/style.css">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <h2>Amenities Management</h2>
    <?php if ($msg): ?><div class="success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <h3><?php echo $editAmenity ? 'Edit Amenity' : 'Add Amenity'; ?></h3>
    <form method="post">
        <input type="hidden" name="id" value="<?php echo $editAmenity['amenity_id'] ?? ''; ?>">
        <label>Name:</label>
        <input type="text" name="amenity_name" value="<?php echo htmlspecialchars($editAmenity['amenity_name'] ?? ''); ?>" required>
        <label>Price (₱):</label>
        <input type="number" step="0.01" name="price" value="<?php echo $editAmenity['price'] ?? ''; ?>" required>
        <button type="submit" name="save"><?php echo $editAmenity ? 'Update' : 'Add'; ?></button>
        <?php if ($editAmenity): ?><a href="/mangima_resort/amenities/">Cancel</a><?php endif; ?>
    </form>

    <h3>Amenity List</h3>
    <table>
        <thead>
            <tr><th>ID</th><th>Name</th><th>Price</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($amenities as $am): ?>
            <tr>
                <td><?php echo $am['amenity_id']; ?></td>
                <td><?php echo htmlspecialchars($am['amenity_name']); ?></td>
                <td>₱<?php echo number_format($am['price'],2); ?></td>
                <td>
                    <a href="?action=edit&id=<?php echo $am['amenity_id']; ?>">Edit</a>
                    | <a href="?action=delete&id=<?php echo $am['amenity_id']; ?>" onclick="return confirmDelete()">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</div>
<script src="/mangima_resort/assets/js/script.js"></script>
</body>
</html>