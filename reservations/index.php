<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db = getDB();
$role = getCurrentUserRole();
$userId = getCurrentUserId();
$msg = $_GET['msg'] ?? '';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// ---------- HANDLE FORM SUBMISSION ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save'])) {
        $user_id = ($role === 'admin' || $role === 'staff') ? ($_POST['user_id'] ?? $userId) : $userId;
        $room_id = (int)($_POST['room_id'] ?? 0);
        $check_in = $_POST['check_in'] ?? '';
        $check_out = $_POST['check_out'] ?? '';
        $status_input = $_POST['status'] ?? 'pending';
        $selected_amenities = $_POST['amenities'] ?? []; // array of amenity ids

        if ($room_id && $check_in && $check_out && strtotime($check_out) > strtotime($check_in)) {
            // Calculate nights
            $nights = (strtotime($check_out) - strtotime($check_in)) / 86400;
            // Get room price
            $roomStmt = $db->prepare("SELECT price FROM rooms WHERE room_id=?");
            $roomStmt->execute([$room_id]);
            $roomPrice = $roomStmt->fetchColumn();
            $roomTotal = $roomPrice * $nights;

            // Get amenity prices
            $amenityTotal = 0;
            if (!empty($selected_amenities)) {
                $placeholders = implode(',', array_fill(0, count($selected_amenities), '?'));
                $amStmt = $db->prepare("SELECT COALESCE(SUM(price),0) FROM amenities WHERE amenity_id IN ($placeholders)");
                $amStmt->execute($selected_amenities);
                $amenityTotal = $amStmt->fetchColumn();
            }
            $total_price = $roomTotal + $amenityTotal;

            if ($id) { // Update
                $stmt = $db->prepare("UPDATE reservations SET user_id=?, room_id=?, check_in=?, check_out=?, total_price=?, status=? WHERE reservation_id=?");
                $stmt->execute([$user_id, $room_id, $check_in, $check_out, $total_price, $status_input, $id]);
                // Update amenities: delete old and insert new
                $db->prepare("DELETE FROM reservation_amenities WHERE reservation_id=?")->execute([$id]);
                if (!empty($selected_amenities)) {
                    $ins = $db->prepare("INSERT INTO reservation_amenities (reservation_id, amenity_id) VALUES (?,?)");
                    foreach ($selected_amenities as $amId) {
                        $ins->execute([$id, $amId]);
                    }
                }
                $msg = "Reservation updated.";
            } else { // Create
                $stmt = $db->prepare("INSERT INTO reservations (user_id, room_id, check_in, check_out, total_price, status) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$user_id, $room_id, $check_in, $check_out, $total_price, $status_input]);
                $newResId = $db->lastInsertId();
                if (!empty($selected_amenities)) {
                    $ins = $db->prepare("INSERT INTO reservation_amenities (reservation_id, amenity_id) VALUES (?,?)");
                    foreach ($selected_amenities as $amId) {
                        $ins->execute([$newResId, $amId]);
                    }
                }
                $msg = "Reservation created.";
            }
            header("Location: /mangima_resort/reservations/?msg=".urlencode($msg));
            exit;
        } else {
            $msg = "Please fill all fields correctly. Check-out must be after check-in.";
        }
    } elseif (isset($_POST['cancel'])) { // Cancel reservation (user or staff)
        $resId = $_POST['reservation_id'] ?? 0;
        if ($resId) {
            // Only owner or admin/staff can cancel
            $resStmt = $db->prepare("SELECT user_id FROM reservations WHERE reservation_id=?");
            $resStmt->execute([$resId]);
            $res = $resStmt->fetch();
            if ($res && ($res['user_id'] == $userId || $role === 'admin' || $role === 'staff')) {
                $db->prepare("UPDATE reservations SET status='cancelled' WHERE reservation_id=?")->execute([$resId]);
                $msg = "Reservation cancelled.";
            } else {
                $msg = "Not authorized.";
            }
        }
        header("Location: /mangima_resort/reservations/?msg=".urlencode($msg));
        exit;
    }
}

// Fetch for edit
$editReservation = null;
if ($action === 'edit' && $id) {
    $stmt = $db->prepare("SELECT * FROM reservations WHERE reservation_id=?");
    $stmt->execute([$id]);
    $editReservation = $stmt->fetch();
    // Get assigned amenities
    $assignedAm = $db->prepare("SELECT amenity_id FROM reservation_amenities WHERE reservation_id=?");
    $assignedAm->execute([$id]);
    $assignedAmenities = $assignedAm->fetchAll(PDO::FETCH_COLUMN);
}

// List reservations based on role
if ($role === 'admin' || $role === 'staff') {
    $listStmt = $db->query("SELECT r.*, u.full_name, rm.room_name 
                            FROM reservations r
                            INNER JOIN users u ON r.user_id = u.user_id
                            INNER JOIN rooms rm ON r.room_id = rm.room_id
                            ORDER BY r.check_in DESC");
} else {
    $listStmt = $db->prepare("SELECT r.*, u.full_name, rm.room_name 
                              FROM reservations r
                              INNER JOIN users u ON r.user_id = u.user_id
                              INNER JOIN rooms rm ON r.room_id = rm.room_id
                              WHERE r.user_id = ?
                              ORDER BY r.check_in DESC");
    $listStmt->execute([$userId]);
}
$reservations = $listStmt->fetchAll();

// Get list of rooms and users for dropdowns (admin/staff)
$rooms = $db->query("SELECT room_id, room_name, status FROM rooms WHERE status='available' OR room_id = " . (int)($editReservation['room_id']??0))->fetchAll();
$users = ($role === 'admin' || $role === 'staff') ? $db->query("SELECT user_id, full_name FROM users ORDER BY full_name")->fetchAll() : [];
$amenities = $db->query("SELECT * FROM amenities ORDER BY amenity_name")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reservations - Mangima Resort</title>
    <link rel="stylesheet" href="/mangima_resort/assets/css/style.css">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <h2>Reservations</h2>
    <?php if ($msg): ?><div class="success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <h3><?php echo $editReservation ? 'Edit Reservation' : 'New Reservation'; ?></h3>
    <form method="post">
        <input type="hidden" name="id" value="<?php echo $editReservation['reservation_id'] ?? ''; ?>">
        <?php if ($role === 'admin' || $role === 'staff'): ?>
            <label>User:</label>
            <select name="user_id" required>
                <?php foreach ($users as $u): ?>
                    <option value="<?php echo $u['user_id']; ?>" <?php echo (isset($editReservation['user_id']) && $editReservation['user_id']==$u['user_id'])?'selected':''; ?>>
                        <?php echo htmlspecialchars($u['full_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <label>Room:</label>
        <select name="room_id" required>
            <?php foreach ($rooms as $r): ?>
                <option value="<?php echo $r['room_id']; ?>" <?php echo (isset($editReservation['room_id']) && $editReservation['room_id']==$r['room_id'])?'selected':''; ?>>
                    <?php echo htmlspecialchars($r['room_name']) . ' ('. $r['status'] .')'; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label>Check-in:</label>
        <input type="date" name="check_in" value="<?php echo $editReservation['check_in'] ?? ''; ?>" required>
        <label>Check-out:</label>
        <input type="date" name="check_out" value="<?php echo $editReservation['check_out'] ?? ''; ?>" required>
        <label>Status:</label>
        <select name="status">
            <option value="pending" <?php echo (isset($editReservation['status']) && $editReservation['status']=='pending')?'selected':''; ?>>Pending</option>
            <option value="confirmed" <?php echo (isset($editReservation['status']) && $editReservation['status']=='confirmed')?'selected':''; ?>>Confirmed</option>
            <option value="checked_in" <?php echo (isset($editReservation['status']) && $editReservation['status']=='checked_in')?'selected':''; ?>>Checked In</option>
            <option value="checked_out" <?php echo (isset($editReservation['status']) && $editReservation['status']=='checked_out')?'selected':''; ?>>Checked Out</option>
        </select>
        <label>Amenities (many-to-many):</label>
        <div style="max-height:120px; overflow-y:auto; border:1px solid #ccc; padding:5px;">
            <?php foreach ($amenities as $am): 
                $checked = (isset($assignedAmenities) && in_array($am['amenity_id'], $assignedAmenities)) ? 'checked' : '';
            ?>
                <label style="display:block;">
                    <input type="checkbox" name="amenities[]" value="<?php echo $am['amenity_id']; ?>" <?php echo $checked; ?>>
                    <?php echo htmlspecialchars($am['amenity_name']) . " (₱" . number_format($am['price'],2) . ")"; ?>
                </label>
            <?php endforeach; ?>
        </div>
        <button type="submit" name="save"><?php echo $editReservation ? 'Update' : 'Book'; ?></button>
        <?php if ($editReservation): ?>
            <a href="/mangima_resort/reservations/">Cancel</a>
        <?php endif; ?>
    </form>

    <h3>Reservation List</h3>
    <table>
        <thead>
            <tr><th>ID</th><th>Guest</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Total</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($reservations as $res): ?>
            <tr>
                <td><?php echo $res['reservation_id']; ?></td>
                <td><?php echo htmlspecialchars($res['full_name']); ?></td>
                <td><?php echo htmlspecialchars($res['room_name']); ?></td>
                <td><?php echo $res['check_in']; ?></td>
                <td><?php echo $res['check_out']; ?></td>
                <td>₱<?php echo number_format($res['total_price'],2); ?></td>
                <td><?php echo $res['status']; ?></td>
                <td>
                    <?php if ($role === 'admin' || $role === 'staff' || $res['user_id'] == $userId): ?>
                        <a href="?action=edit&id=<?php echo $res['reservation_id']; ?>">Edit</a>
                    <?php endif; ?>
                    <?php if ($res['status'] !== 'cancelled' && $res['status'] !== 'checked_out' && ($role === 'admin' || $role === 'staff' || $res['user_id'] == $userId)): ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Cancel this reservation?');">
                            <input type="hidden" name="reservation_id" value="<?php echo $res['reservation_id']; ?>">
                            <button type="submit" name="cancel" style="background:none; border:none; color:red; cursor:pointer; text-decoration:underline;">Cancel</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</div>
</body>
</html>