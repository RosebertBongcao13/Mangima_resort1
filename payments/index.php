<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin','staff']); // Only staff/admin manage payments

$db = getDB();
$msg = $_GET['msg'] ?? '';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// ---------- SAVE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $reservation_id = (int)($_POST['reservation_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $method = $_POST['payment_method'] ?? '';
    $status = $_POST['payment_status'] ?? 'unpaid';
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d H:i:s');

    if ($reservation_id && $amount > 0) {
        if ($id) {
            $stmt = $db->prepare("UPDATE payments SET reservation_id=?, amount=?, payment_method=?, payment_status=?, payment_date=? WHERE payment_id=?");
            $stmt->execute([$reservation_id, $amount, $method, $status, $payment_date, $id]);
            $msg = "Payment updated.";
        } else {
            $stmt = $db->prepare("INSERT INTO payments (reservation_id, amount, payment_method, payment_status, payment_date) VALUES (?,?,?,?,?)");
            $stmt->execute([$reservation_id, $amount, $method, $status, $payment_date]);
            $msg = "Payment recorded.";
        }
        header("Location: /mangima_resort/payments/?msg=".urlencode($msg));
        exit;
    } else {
        $msg = "Please fill all required fields.";
    }
} elseif ($action === 'delete' && $id) {
    $stmt = $db->prepare("DELETE FROM payments WHERE payment_id=?");
    $stmt->execute([$id]);
    $msg = "Payment deleted.";
    header("Location: /mangima_resort/payments/?msg=".urlencode($msg));
    exit;
}

$editPayment = null;
if ($action === 'edit' && $id) {
    $stmt = $db->prepare("SELECT * FROM payments WHERE payment_id=?");
    $stmt->execute([$id]);
    $editPayment = $stmt->fetch();
}

$payments = $db->query("SELECT p.*, r.check_in, r.check_out, u.full_name, rm.room_name 
                         FROM payments p
                         LEFT JOIN reservations r ON p.reservation_id = r.reservation_id
                         LEFT JOIN users u ON r.user_id = u.user_id
                         LEFT JOIN rooms rm ON r.room_id = rm.room_id
                         ORDER BY p.payment_date DESC")->fetchAll();

$reservationsList = $db->query("SELECT reservation_id, CONCAT('Res #', reservation_id, ' - ', check_in, ' to ', check_out) as label FROM reservations ORDER BY reservation_id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payments - Mangima Resort</title>
    <link rel="stylesheet" href="/mangima_resort/assets/css/style.css">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <h2>Payment Management</h2>
    <?php if ($msg): ?><div class="success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <h3><?php echo $editPayment ? 'Edit Payment' : 'Add Payment'; ?></h3>
    <form method="post">
        <input type="hidden" name="id" value="<?php echo $editPayment['payment_id'] ?? ''; ?>">
        <label>Reservation:</label>
        <select name="reservation_id" required>
            <option value="">-- Select --</option>
            <?php foreach ($reservationsList as $r): ?>
                <option value="<?php echo $r['reservation_id']; ?>" <?php echo (isset($editPayment['reservation_id']) && $editPayment['reservation_id']==$r['reservation_id'])?'selected':''; ?>>
                    <?php echo htmlspecialchars($r['label']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label>Amount (₱):</label>
        <input type="number" step="0.01" name="amount" value="<?php echo $editPayment['amount'] ?? ''; ?>" required>
        <label>Payment Method:</label>
        <input type="text" name="payment_method" value="<?php echo htmlspecialchars($editPayment['payment_method'] ?? ''); ?>">
        <label>Status:</label>
        <select name="payment_status">
            <option value="paid" <?php echo (isset($editPayment['payment_status']) && $editPayment['payment_status']=='paid')?'selected':''; ?>>Paid</option>
            <option value="unpaid" <?php echo (isset($editPayment['payment_status']) && $editPayment['payment_status']=='unpaid')?'selected':''; ?>>Unpaid</option>
        </select>
        <label>Payment Date:</label>
        <input type="datetime-local" name="payment_date" value="<?php echo isset($editPayment['payment_date']) ? date('Y-m-d\TH:i', strtotime($editPayment['payment_date'])) : ''; ?>">
        <button type="submit" name="save"><?php echo $editPayment ? 'Update' : 'Add'; ?></button>
        <?php if ($editPayment): ?><a href="/mangima_resort/payments/">Cancel</a><?php endif; ?>
    </form>

    <h3>Payment List</h3>
    <table>
        <thead>
            <tr><th>ID</th><th>Res. ID</th><th>Guest</th><th>Room</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($payments as $p): ?>
            <tr>
                <td><?php echo $p['payment_id']; ?></td>
                <td><?php echo $p['reservation_id']; ?></td>
                <td><?php echo htmlspecialchars($p['full_name'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($p['room_name'] ?? 'N/A'); ?></td>
                <td>₱<?php echo number_format($p['amount'],2); ?></td>
                <td><?php echo htmlspecialchars($p['payment_method']); ?></td>
                <td><?php echo $p['payment_status']; ?></td>
                <td><?php echo $p['payment_date']; ?></td>
                <td>
                    <a href="?action=edit&id=<?php echo $p['payment_id']; ?>">Edit</a>
                    | <a href="?action=delete&id=<?php echo $p['payment_id']; ?>" onclick="return confirmDelete()">Delete</a>
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