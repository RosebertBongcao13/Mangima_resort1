<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin','staff']); // Staff and admin

$db = getDB();
$msg = $_GET['msg'] ?? '';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// ---------- SAVE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $room_name = trim($_POST['room_name'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $status = $_POST['status'] ?? 'available';

    if ($room_name && $type && $capacity > 0 && $price > 0) {
        if ($id) {
            $stmt = $db->prepare("UPDATE rooms SET room_name=?, type=?, capacity=?, price=?, status=? WHERE room_id=?");
            $stmt->execute([$room_name, $type, $capacity, $price, $status, $id]);
            $msg = "Room updated.";
        } else {
            $stmt = $db->prepare("INSERT INTO rooms (room_name, type, capacity, price, status) VALUES (?,?,?,?,?)");
            $stmt->execute([$room_name, $type, $capacity, $price, $status]);
            $msg = "Room created.";
        }
        header("Location: /mangima_resort/rooms/?msg=".urlencode($msg));
        exit;
    } else {
        $msg = "Please fill all fields correctly.";
    }
} elseif ($action === 'delete' && $id) {
    $stmt = $db->prepare("DELETE FROM rooms WHERE room_id=?");
    $stmt->execute([$id]);
    $msg = "Room deleted.";
    header("Location: /mangima_resort/rooms/?msg=".urlencode($msg));
    exit;
}

$editRoom = null;
if ($action === 'edit' && $id) {
    $stmt = $db->prepare("SELECT * FROM rooms WHERE room_id=?");
    $stmt->execute([$id]);
    $editRoom = $stmt->fetch();
}

$rooms = $db->query("SELECT * FROM rooms ORDER BY room_id")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Rooms - Mangima Resort</title>
    <link rel="stylesheet" href="/mangima_resort/assets/css/style.css">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <h2>Room Management</h2>
    <?php if ($msg): ?><div class="success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <h3><?php echo $editRoom ? 'Edit Room' : 'Add New Room'; ?></h3>
    <form method="post">
        <input type="hidden" name="id" value="<?php echo $editRoom['room_id'] ?? ''; ?>">
        <label>Room Name:</label>
        <input type="text" name="room_name" value="<?php echo htmlspecialchars($editRoom['room_name'] ?? ''); ?>" required>
        <label>Type:</label>
        <input type="text" name="type" value="<?php echo htmlspecialchars($editRoom['type'] ?? ''); ?>" required>
        <label>Capacity:</label>
        <input type="number" name="capacity" value="<?php echo $editRoom['capacity'] ?? ''; ?>" required>
        <label>Price per Night (₱):</label>
        <input type="number" step="0.01" name="price" value="<?php echo $editRoom['price'] ?? ''; ?>" required>
        <label>Status:</label>
        <select name="status">
            <option value="available" <?php echo (isset($editRoom['status']) && $editRoom['status']=='available')?'selected':''; ?>>Available</option>
            <option value="occupied" <?php echo (isset($editRoom['status']) && $editRoom['status']=='occupied')?'selected':''; ?>>Occupied</option>
            <option value="maintenance" <?php echo (isset($editRoom['status']) && $editRoom['status']=='maintenance')?'selected':''; ?>>Maintenance</option>
        </select>
        <button type="submit" name="save"><?php echo $editRoom ? 'Update' : 'Add'; ?></button>
        <?php if ($editRoom): ?><a href="/mangima_resort/rooms/">Cancel</a><?php endif; ?>
    </form>

    <h3>Room List</h3>
    <table>
        <thead>
            <tr><th>ID</th><th>Name</th><th>Type</th><th>Cap</th><th>Price</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($rooms as $r): ?>
            <tr>
                <td><?php echo $r['room_id']; ?></td>
                <td><?php echo htmlspecialchars($r['room_name']); ?></td>
                <td><?php echo htmlspecialchars($r['type']); ?></td>
                <td><?php echo $r['capacity']; ?></td>
                <td>₱<?php echo number_format($r['price'],2); ?></td>
                <td><?php echo $r['status']; ?></td>
                <td>
                    <a href="?action=edit&id=<?php echo $r['room_id']; ?>">Edit</a>
                    | <a href="?action=delete&id=<?php echo $r['room_id']; ?>" onclick="return confirmDelete()">Delete</a>
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