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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Mangima Resort</title>
    <link rel="stylesheet" href="/mangima_resort/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        /* Dashboard-specific styles matching login page theme */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            padding: 30px 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(46, 204, 113, 0.3);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            box-shadow: 0 10px 30px rgba(52, 152, 219, 0.3);
        }
        
        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
            box-shadow: 0 10px 30px rgba(230, 126, 34, 0.3);
        }
        
        .stat-icon {
            font-size: 35px;
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 13px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin: 5px 0;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 30px 0 20px;
            padding: 15px 20px;
            background: white;
            border-radius: 10px;
            border-left: 4px solid #2ecc71;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .section-title h3 {
            margin: 0;
            color: #2d3436;
            font-size: 18px;
        }
        
        .search-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .search-box input {
            flex: 1;
            padding: 12px 20px;
            border: 2px solid #dfe6e9;
            border-radius: 25px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s;
        }
        
        .search-box input:focus {
            border-color: #2ecc71;
        }
        
        .btn-search {
            padding: 12px 25px;
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
        }
        
        .btn-clear {
            padding: 12px 20px;
            color: #636e72;
            text-decoration: none;
            border-radius: 25px;
            transition: background 0.3s;
        }
        
        .btn-clear:hover {
            background: #f5f6fa;
        }
        
        .data-table {
            width: 100%;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .data-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table thead {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }
        
        .data-table th {
            padding: 15px;
            color: white;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .data-table th a {
            color: white;
            text-decoration: none;
        }
        
        .data-table th a:hover {
            text-decoration: underline;
        }
        
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            color: #2d3436;
        }
        
        .data-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .status-confirmed,
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending,
        .status-unpaid {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-checked_in,
        .status-checked_out {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .charts-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
            margin-top: 30px;
        }
        
        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .chart-card h4 {
            color: #2d3436;
            margin-bottom: 20px;
            text-align: center;
            font-size: 16px;
        }
        
        .no-data {
            text-align: center;
            padding: 30px;
            color: #b2bec3;
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            .charts-container {
                grid-template-columns: 1fr;
            }
            
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <h2>📊 Dashboard</h2>

    <div class="dashboard-stats">
        <?php if ($role === 'admin' || $role === 'staff'): ?>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?php echo $totalUsers; ?></div>
            </div>
        <?php endif; ?>
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-label">Total Reservations</div>
            <div class="stat-value"><?php echo $totalReservations; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value">₱<?php echo number_format($totalRevenue, 2); ?></div>
        </div>
    </div>

    <div class="section-title">
        <span>📋</span>
        <h3>Reservations (INNER JOIN users + rooms)</h3>
    </div>
    
    <div class="search-box">
        <form method="get" style="display:flex; gap:10px; flex:1; align-items:center; background:none; padding:0; box-shadow:none; margin:0;">
            <input type="text" name="search" placeholder="🔍 Search reservations..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn-search">Search</button>
            <a href="/mangima_resort/dashboard/dashboard.php" class="btn-clear">Clear</a>
        </form>
    </div>

    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th><a href="?sort=reservation_id&order=<?php echo $order==='ASC'?'DESC':'ASC'; ?>&search=<?php echo urlencode($search); ?>">ID ↕</a></th>
                    <th><a href="?sort=full_name&order=<?php echo $order==='ASC'?'DESC':'ASC'; ?>&search=<?php echo urlencode($search); ?>">Guest ↕</a></th>
                    <th><a href="?sort=room_name&order=<?php echo $order==='ASC'?'DESC':'ASC'; ?>&search=<?php echo urlencode($search); ?>">Room ↕</a></th>
                    <th><a href="?sort=check_in&order=<?php echo $order==='ASC'?'DESC':'ASC'; ?>&search=<?php echo urlencode($search); ?>">Check-in ↕</a></th>
                    <th><a href="?sort=check_out&order=<?php echo $order==='ASC'?'DESC':'ASC'; ?>&search=<?php echo urlencode($search); ?>">Check-out ↕</a></th>
                    <th><a href="?sort=status&order=<?php echo $order==='ASC'?'DESC':'ASC'; ?>&search=<?php echo urlencode($search); ?>">Status ↕</a></th>
                    <th><a href="?sort=total_price&order=<?php echo $order==='ASC'?'DESC':'ASC'; ?>&search=<?php echo urlencode($search); ?>">Price ↕</a></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reservations)): ?>
                    <tr><td colspan="7" class="no-data">No reservations found.</td></tr>
                <?php else: ?>
                    <?php foreach ($reservations as $res): ?>
                    <tr>
                        <td>#<?php echo $res['reservation_id']; ?></td>
                        <td><?php echo htmlspecialchars($res['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($res['room_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($res['check_in'])); ?></td>
                        <td><?php echo date('M d, Y', strtotime($res['check_out'])); ?></td>
                        <td><span class="status-badge status-<?php echo $res['status']; ?>"><?php echo $res['status']; ?></span></td>
                        <td><strong>₱<?php echo number_format($res['total_price'],2); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section-title">
        <span>💳</span>
        <h3>Payments (LEFT JOIN reservations)</h3>
    </div>
    
    <div class="data-table">
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
                    <tr><td colspan="8" class="no-data">No payments found.</td></tr>
                <?php else: ?>
                    <?php foreach ($payments as $pay): ?>
                    <tr>
                        <td>#<?php echo $pay['payment_id']; ?></td>
                        <td>#<?php echo $pay['reservation_id']; ?></td>
                        <td><?php echo htmlspecialchars($pay['full_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($pay['room_name'] ?? 'N/A'); ?></td>
                        <td><strong>₱<?php echo number_format($pay['amount'],2); ?></strong></td>
                        <td><?php echo htmlspecialchars($pay['payment_method']); ?></td>
                        <td><span class="status-badge status-<?php echo $pay['payment_status']; ?>"><?php echo $pay['payment_status']; ?></span></td>
                        <td><?php echo $pay['payment_date'] ? date('M d, Y h:i A', strtotime($pay['payment_date'])) : 'N/A'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="charts-container">
        <div class="chart-card">
            <h4>📊 Reservation Status</h4>
            <canvas id="pieChart"></canvas>
        </div>
        <div class="chart-card">
            <h4>📈 Revenue Per Day</h4>
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