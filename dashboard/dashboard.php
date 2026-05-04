<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// Test if getDB() works
$db = getDB();

// Check if database connection is working
if (!$db) {
    die("Database connection failed. Check config/database.php");
}

$role = getCurrentUserRole();
$userId = getCurrentUserId();

// ---- TOTALS (visible to admin/staff) ----
$totalUsers = 0;
$totalReservations = 0;
$totalRevenue = 0;

if ($role === 'admin' || $role === 'staff') {
    $totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalReservations = $db->query("SELECT COUNT(*) FROM reservations")->fetchColumn();
    $totalRevenue = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_status='paid'")->fetchColumn();
} else {
    // For regular user, show only personal summary
    $stmt = $db->prepare("SELECT COUNT(*) FROM reservations WHERE user_id=?");
    $stmt->execute([$userId]);
    $totalReservations = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p 
                          JOIN reservations r ON p.reservation_id = r.reservation_id 
                          WHERE r.user_id=? AND p.payment_status='paid'");
    $stmt->execute([$userId]);
    $totalRevenue = $stmt->fetchColumn();
}

// ---- RESERVATIONS LIST ----
// INNER JOIN: reservations + users + rooms
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'reservation_id';
$order = $_GET['order'] ?? 'DESC';

$allowedSorts = ['reservation_id','check_in','check_out','status','full_name','room_name','total_price'];
if (!in_array($sort, $allowedSorts)) {
    $sort = 'reservation_id';
}
$order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

$sql = "SELECT r.*, u.full_name, rm.room_name 
        FROM reservations r
        INNER JOIN users u ON r.user_id = u.user_id
        INNER JOIN rooms rm ON r.room_id = rm.room_id";

$where = [];
$params = [];

// If regular user, show only own reservations
if ($role === 'user') {
    $where[] = "r.user_id = ?";
    $params[] = $userId;
}

if ($search) {
    $where[] = "(u.full_name LIKE ? OR rm.room_name LIKE ? OR r.status LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY $sort $order";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

// ---- PAYMENTS LIST (LEFT JOIN) ----
$paySql = "SELECT p.*, r.check_in, r.check_out, u.full_name, rm.room_name
           FROM payments p
           LEFT JOIN reservations r ON p.reservation_id = r.reservation_id
           LEFT JOIN users u ON r.user_id = u.user_id
           LEFT JOIN rooms rm ON r.room_id = rm.room_id";

$payWhere = [];
$payParams = [];

if ($role === 'user') {
    $payWhere[] = "r.user_id = ?";
    $payParams[] = $userId;
}

if ($search) {
    $payWhere[] = "(u.full_name LIKE ? OR rm.room_name LIKE ?)";
    $searchTerm = "%$search%";
    $payParams[] = $searchTerm;
    $payParams[] = $searchTerm;
}

if (!empty($payWhere)) {
    $paySql .= " WHERE " . implode(" AND ", $payWhere);
}

$paySql .= " ORDER BY p.payment_date DESC";
$payStmt = $db->prepare($paySql);
$payStmt->execute($payParams);
$payments = $payStmt->fetchAll();

// ---- Chart data ----
// Pie chart: reservation status counts
$statusCounts = $db->query("SELECT status, COUNT(*) as count FROM reservations GROUP BY status")->fetchAll();

// Bar chart: revenue per day (all paid payments)
$revenueStmt = $db->query("SELECT DATE(payment_date) as day, SUM(amount) as total 
                           FROM payments WHERE payment_status='paid' 
                           GROUP BY day ORDER BY day");
$revenueData = $revenueStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Mangima Resort</title>
    <link rel="stylesheet" href="/mangima_resort/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <h2>Dashboard</h2>

    <div class="stats">
        <?php if ($role === 'admin' || $role === 'staff'): ?>
            <div class="stat-box">Total Users: <?php echo $totalUsers; ?></div>
        <?php endif; ?>
        <div class="stat-box">Total Reservations: <?php echo $totalReservations; ?></div>
        <div class="stat-box">Total Revenue: ₱<?php echo number_format($totalRevenue, 2); ?></div>
    </div>

    <h3>Reservations (INNER JOIN users + rooms)</h3>
    <!-- Simple search and sorting -->
    <form method="get" style="margin-bottom:10px;">
        <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit">Search</button>
        <a href="/mangima_resort/dashboard/dashboard.php">Clear</a>
    </form>

    <table>
        <thead>
            <tr>
                <th><a href="?sort=reservation_id&order=<?php echo $order==='ASC'?'DESC':'ASC'; ?>&search=<?php echo urlencode($search); ?>">ID</a></th>
                <th><a href="?sort=full_name&order=<?php echo $order==='ASC'?'DESC':'ASC'; ?>&search=<?php echo urlencode($search); ?>">Guest</a></th>
                <th><a href="?sort=room_name&order=<?php echo $order==='ASC'?'DESC':'ASC'; ?>&search=<?php echo urlencode($search); ?>">Room</a></th>
                <th><a href="?sort=check_in&order=<?php echo $order==='ASC'?'DESC':'ASC'; ?>&search=<?php echo urlencode($search); ?>">Check-in</a></th>
                <th><a href="?sort=check_out&order=<?php echo $order==='ASC'?'DESC':'ASC'; ?>&search=<?php echo urlencode($search); ?>">Check-out</a></th>
                <th><a href="?sort=status&order=<?php echo $order==='ASC'?'DESC':'ASC'; ?>&search=<?php echo urlencode($search); ?>">Status</a></th>
                <th><a href="?sort=total_price&order=<?php echo $order==='ASC'?'DESC':'ASC'; ?>&search=<?php echo urlencode($search); ?>">Price</a></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reservations)): ?>
                <tr><td colspan="7" style="text-align:center;">No reservations found.</td></tr>
            <?php else: ?>
                <?php foreach ($reservations as $res): ?>
                <tr>
                    <td><?php echo $res['reservation_id']; ?></td>
                    <td><?php echo htmlspecialchars($res['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($res['room_name']); ?></td>
                    <td><?php echo $res['check_in']; ?></td>
                    <td><?php echo $res['check_out']; ?></td>
                    <td><?php echo $res['status']; ?></td>
                    <td>₱<?php echo number_format($res['total_price'],2); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <h3>Payments (LEFT JOIN reservations)</h3>
    <!-- LEFT JOIN explained: Shows all payments even if the corresponding reservation might be missing -->
    <table>
        <thead>
            <tr>
                <th>Payment ID</th>
                <th>Res. ID</th>
                <th>Guest</th>
                <th>Room</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($payments)): ?>
                <tr><td colspan="8" style="text-align:center;">No payments found.</td></tr>
            <?php else: ?>
                <?php foreach ($payments as $pay): ?>
                <tr>
                    <td><?php echo $pay['payment_id']; ?></td>
                    <td><?php echo $pay['reservation_id']; ?></td>
                    <td><?php echo htmlspecialchars($pay['full_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($pay['room_name'] ?? 'N/A'); ?></td>
                    <td>₱<?php echo number_format($pay['amount'],2); ?></td>
                    <td><?php echo htmlspecialchars($pay['payment_method']); ?></td>
                    <td><?php echo $pay['payment_status']; ?></td>
                    <td><?php echo $pay['payment_date']; ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="charts" style="display:flex; flex-wrap:wrap; gap:20px; margin-top:20px;">
        <div style="width:300px;">
            <canvas id="pieChart"></canvas>
        </div>
        <div style="width:500px;">
            <canvas id="barChart"></canvas>
        </div>
    </div>

    <script>
    // Pie chart for reservation status
    const statusLabels = <?php echo json_encode(array_column($statusCounts, 'status')); ?>;
    const statusData = <?php echo json_encode(array_column($statusCounts, 'count')); ?>;
    
    if (document.getElementById('pieChart')) {
        new Chart(document.getElementById('pieChart'), {
            type: 'pie',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusData,
                    backgroundColor: ['#f39c12','#2ecc71','#e74c3c','#3498db','#9b59b6']
                }]
            }
        });
    }

    // Bar chart for revenue per day
    const revDays = <?php echo json_encode(array_column($revenueData, 'day')); ?>;
    const revAmounts = <?php echo json_encode(array_column($revenueData, 'total')); ?>;
    
    if (document.getElementById('barChart')) {
        new Chart(document.getElementById('barChart'), {
            type: 'bar',
            data: {
                labels: revDays,
                datasets: [{
                    label: 'Revenue (₱)',
                    data: revAmounts,
                    backgroundColor: '#2980b9'
                }]
            },
            options: {
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
    </script>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</div>
</body>
</html>