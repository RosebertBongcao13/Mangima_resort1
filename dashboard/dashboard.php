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
$statusCounts = $db->query("SELECT status, COUNT(*) as count FROM reservations GROUP BY status")->fetchAll();

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
    <title>Dashboard — Mangima Resort</title>
    <link rel="stylesheet" href="/mangima_resort/assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --sand:       #f5f0e8;
            --sand-dark:  #ede6d6;
            --ink:        #1a1714;
            --ink-soft:   #3d3630;
            --ink-muted:  #7a6f65;
            --gold:       #c9a84c;
            --gold-light: #e8c97a;
            --gold-dark:  #a07828;
            --teal:       #2d7d6f;
            --teal-light: #3fa091;
            --teal-pale:  #e8f5f3;
            --coral:      #c0544a;
            --coral-pale: #faeeed;
            --sky:        #3a7bbf;
            --sky-pale:   #eaf2fb;
            --white:      #ffffff;
            --border:     #e0d8cc;
            --shadow-sm:  0 2px 8px rgba(26,23,20,0.07);
            --shadow-md:  0 8px 32px rgba(26,23,20,0.10);
            --shadow-lg:  0 20px 60px rgba(26,23,20,0.14);
            --radius:     14px;
            --radius-sm:  8px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--sand);
            color: var(--ink);
            font-family: 'DM Sans', sans-serif;
            font-size: 15px;
            line-height: 1.6;
        }

        /* ─── Page wrapper ─────────────────────────────── */
        .dash-wrapper {
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 28px 60px;
        }

        /* ─── Page header ──────────────────────────────── */
        .dash-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            padding: 40px 0 28px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 36px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .dash-header-left {}

        .dash-eyebrow {
            font-family: 'DM Sans', sans-serif;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: var(--gold-dark);
            margin-bottom: 4px;
        }

        .dash-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 42px;
            font-weight: 600;
            color: var(--ink);
            line-height: 1.1;
            letter-spacing: -0.5px;
        }

        .dash-title span {
            color: var(--teal);
        }

        .dash-date {
            font-size: 13px;
            color: var(--ink-muted);
            font-weight: 400;
            margin-top: 6px;
        }

        .dash-role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            background: var(--ink);
            color: var(--gold-light);
            border: 1px solid var(--ink-soft);
        }

        /* ─── Stat cards ───────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
            margin-bottom: 44px;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 28px 28px 24px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
        }

        .stat-card.gold::before  { background: linear-gradient(90deg, var(--gold), var(--gold-light)); }
        .stat-card.teal::before  { background: linear-gradient(90deg, var(--teal), var(--teal-light)); }
        .stat-card.coral::before { background: linear-gradient(90deg, var(--coral), #e08078); }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }

        .stat-icon-wrap {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 18px;
        }

        .stat-card.gold  .stat-icon-wrap { background: #fdf5e0; }
        .stat-card.teal  .stat-icon-wrap { background: var(--teal-pale); }
        .stat-card.coral .stat-icon-wrap { background: var(--coral-pale); }

        .stat-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1.8px;
            text-transform: uppercase;
            color: var(--ink-muted);
            margin-bottom: 6px;
        }

        .stat-value {
            font-family: 'Cormorant Garamond', serif;
            font-size: 40px;
            font-weight: 700;
            color: var(--ink);
            line-height: 1;
        }

        .stat-card.teal  .stat-value { color: var(--teal); }
        .stat-card.coral .stat-value { color: var(--coral); }
        .stat-card.gold  .stat-value { color: var(--gold-dark); }

        .stat-sub {
            font-size: 12px;
            color: var(--ink-muted);
            margin-top: 8px;
        }

        /* ─── Section label ────────────────────────────── */
        .section-label {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .section-label-bar {
            width: 4px;
            height: 24px;
            border-radius: 2px;
            background: linear-gradient(180deg, var(--teal), var(--teal-light));
            flex-shrink: 0;
        }

        .section-label h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 22px;
            font-weight: 600;
            color: var(--ink);
            letter-spacing: -0.2px;
        }

        .section-label small {
            font-size: 12px;
            color: var(--ink-muted);
            font-weight: 400;
            margin-left: 4px;
            font-family: 'DM Sans', sans-serif;
        }

        /* ─── Search bar ───────────────────────────────── */
        .search-row {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .search-field {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 220px;
            background: var(--white);
            border: 1.5px solid var(--border);
            border-radius: 100px;
            padding: 0 20px;
            transition: border-color 0.2s;
        }

        .search-field:focus-within {
            border-color: var(--teal);
            box-shadow: 0 0 0 3px rgba(45,125,111,0.10);
        }

        .search-field svg {
            flex-shrink: 0;
            color: var(--ink-muted);
        }

        .search-field input {
            border: none;
            outline: none;
            background: transparent;
            padding: 11px 0;
            font-size: 14px;
            font-family: 'DM Sans', sans-serif;
            color: var(--ink);
            width: 100%;
        }

        .search-field input::placeholder { color: var(--ink-muted); }

        .btn-primary {
            padding: 11px 24px;
            background: var(--teal);
            color: var(--white);
            border: none;
            border-radius: 100px;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.3px;
            transition: background 0.2s, transform 0.2s, box-shadow 0.2s;
            white-space: nowrap;
        }

        .btn-primary:hover {
            background: var(--teal-light);
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(45,125,111,0.25);
        }

        .btn-ghost {
            padding: 11px 20px;
            background: transparent;
            color: var(--ink-muted);
            border: 1.5px solid var(--border);
            border-radius: 100px;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
        }

        .btn-ghost:hover {
            background: var(--sand-dark);
            border-color: var(--ink-muted);
            color: var(--ink);
        }

        /* ─── Data tables ──────────────────────────────── */
        .table-card {
            background: var(--white);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 40px;
        }

        .table-card table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-card thead tr {
            background: var(--sand);
            border-bottom: 1.5px solid var(--border);
        }

        .table-card th {
            padding: 13px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--ink-muted);
        }

        .table-card th a {
            color: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: color 0.2s;
        }

        .table-card th a:hover { color: var(--teal); }

        .table-card td {
            padding: 13px 16px;
            border-bottom: 1px solid #f2ede5;
            font-size: 14px;
            color: var(--ink-soft);
            vertical-align: middle;
        }

        .table-card tbody tr:last-child td { border-bottom: none; }

        .table-card tbody tr {
            transition: background 0.15s;
        }

        .table-card tbody tr:hover { background: var(--sand); }

        .res-id {
            font-family: 'DM Sans', sans-serif;
            font-size: 12px;
            font-weight: 600;
            color: var(--ink-muted);
            background: var(--sand);
            padding: 2px 8px;
            border-radius: 4px;
        }

        .guest-name {
            font-weight: 500;
            color: var(--ink);
        }

        .room-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            color: var(--teal);
            font-weight: 500;
        }

        .date-cell {
            font-size: 13px;
            color: var(--ink-muted);
            white-space: nowrap;
        }

        .price-cell {
            font-family: 'Cormorant Garamond', serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--ink);
            white-space: nowrap;
        }

        .method-chip {
            display: inline-flex;
            align-items: center;
            font-size: 12px;
            font-weight: 500;
            color: var(--ink-muted);
            background: var(--sand);
            padding: 3px 10px;
            border-radius: 6px;
            border: 1px solid var(--border);
        }

        /* ─── Status badges ────────────────────────────── */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: capitalize;
        }

        .status-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .status-confirmed, .status-paid {
            background: #e6f5ee;
            color: #1a6e3e;
        }
        .status-confirmed::before, .status-paid::before { background: #2ecc71; }

        .status-pending, .status-unpaid {
            background: #fef7e6;
            color: #8a6200;
        }
        .status-pending::before, .status-unpaid::before { background: #f39c12; }

        .status-cancelled {
            background: #fdecea;
            color: #922b21;
        }
        .status-cancelled::before { background: #e74c3c; }

        .status-checked_in {
            background: var(--sky-pale);
            color: #1a4f80;
        }
        .status-checked_in::before { background: var(--sky); }

        .status-checked_out {
            background: #f0eef8;
            color: #5b4a9e;
        }
        .status-checked_out::before { background: #9b59b6; }

        /* ─── No data ───────────────────────────────────── */
        .no-data-row td {
            text-align: center;
            padding: 48px 20px;
            color: var(--ink-muted);
            font-size: 14px;
        }

        .no-data-icon {
            font-size: 36px;
            display: block;
            margin-bottom: 10px;
            opacity: 0.4;
        }

        /* ─── Charts ───────────────────────────────────── */
        .charts-grid {
            display: grid;
            grid-template-columns: 5fr 8fr;
            gap: 20px;
            margin-bottom: 50px;
        }

        .chart-card {
            background: var(--white);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            padding: 28px;
        }

        .chart-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        .chart-card-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 19px;
            font-weight: 600;
            color: var(--ink);
        }

        .chart-tag {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--ink-muted);
            background: var(--sand);
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid var(--border);
        }

        /* ─── Divider ───────────────────────────────────── */
        .section-divider {
            height: 1px;
            background: var(--border);
            margin: 0 0 36px;
        }

        /* ─── Responsive ────────────────────────────────── */
        @media (max-width: 900px) {
            .charts-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 600px) {
            .dash-title { font-size: 30px; }
            .stats-grid { grid-template-columns: 1fr; }
            .table-card { font-size: 13px; }
            .table-card th, .table-card td { padding: 10px 12px; }
            .dash-wrapper { padding: 0 16px 48px; }
        }

        /* ─── Animations ────────────────────────────────── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .stat-card { animation: fadeUp 0.4s ease both; }
        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.12s; }
        .stat-card:nth-child(3) { animation-delay: 0.19s; }

        .table-card { animation: fadeUp 0.4s ease 0.25s both; }
        .chart-card { animation: fadeUp 0.4s ease 0.35s both; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="dash-wrapper">

        <!-- ─── Page Header ─────────────────────────────── -->
        <div class="dash-header">
            <div class="dash-header-left">
                <div class="dash-eyebrow">Mangima Resort</div>
                <div class="dash-title">Overview <span>&amp; Analytics</span></div>
                <div class="dash-date">
                    <?php echo date('l, F j, Y'); ?> &nbsp;·&nbsp; <?php echo date('g:i A'); ?>
                </div>
            </div>
            <div class="dash-role-badge">
                <?php if ($role === 'admin'): ?>
                    ⚙ Administrator
                <?php elseif ($role === 'staff'): ?>
                    🪪 Staff
                <?php else: ?>
                    👤 Guest
                <?php endif; ?>
            </div>
        </div>

        <!-- ─── Stat Cards ──────────────────────────────── -->
        <div class="stats-grid">
            <?php if ($role === 'admin' || $role === 'staff'): ?>
            <div class="stat-card gold">
                <div class="stat-icon-wrap">👥</div>
                <div class="stat-label">Registered Users</div>
                <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
                <div class="stat-sub">All accounts in system</div>
            </div>
            <?php endif; ?>

            <div class="stat-card teal">
                <div class="stat-icon-wrap">📅</div>
                <div class="stat-label">
                    <?php echo ($role === 'user') ? 'My Reservations' : 'Total Reservations'; ?>
                </div>
                <div class="stat-value"><?php echo number_format($totalReservations); ?></div>
                <div class="stat-sub">All time bookings</div>
            </div>

            <div class="stat-card coral">
                <div class="stat-icon-wrap">💰</div>
                <div class="stat-label">
                    <?php echo ($role === 'user') ? 'My Total Paid' : 'Total Revenue'; ?>
                </div>
                <div class="stat-value">₱<?php echo number_format($totalRevenue, 0); ?></div>
                <div class="stat-sub">From confirmed payments</div>
            </div>
        </div>

        <!-- ─── Reservations Table ──────────────────────── -->
        <div class="section-label">
            <div class="section-label-bar"></div>
            <h3>Reservations <small>— guest &amp; room details</small></h3>
        </div>

        <div class="search-row">
            <form method="get" style="display:contents;">
                <div class="search-field">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" name="search" placeholder="Search guest, room, or status…" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button type="submit" class="btn-primary">Search</button>
                <a href="/mangima_resort/dashboard/dashboard.php" class="btn-ghost">Clear</a>
            </form>
        </div>

        <div class="table-card">
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
                        <tr class="no-data-row">
                            <td colspan="7">
                                <span class="no-data-icon">📋</span>
                                No reservations found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reservations as $res): ?>
                        <tr>
                            <td><span class="res-id">#<?php echo $res['reservation_id']; ?></span></td>
                            <td><span class="guest-name"><?php echo htmlspecialchars($res['full_name']); ?></span></td>
                            <td><span class="room-chip">🏠 <?php echo htmlspecialchars($res['room_name']); ?></span></td>
                            <td class="date-cell"><?php echo date('M d, Y', strtotime($res['check_in'])); ?></td>
                            <td class="date-cell"><?php echo date('M d, Y', strtotime($res['check_out'])); ?></td>
                            <td><span class="status-badge status-<?php echo $res['status']; ?>"><?php echo $res['status']; ?></span></td>
                            <td class="price-cell">₱<?php echo number_format($res['total_price'],2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ─── Payments Table ──────────────────────────── -->
        <div class="section-label">
            <div class="section-label-bar" style="background: linear-gradient(180deg, var(--gold), var(--gold-light));"></div>
            <h3>Payments <small>— transaction records</small></h3>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Pay ID</th>
                        <th>Res. ID</th>
                        <th>Guest</th>
                        <th>Room</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date &amp; Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr class="no-data-row">
                            <td colspan="8">
                                <span class="no-data-icon">💳</span>
                                No payments found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $pay): ?>
                        <tr>
                            <td><span class="res-id">#<?php echo $pay['payment_id']; ?></span></td>
                            <td><span class="res-id">#<?php echo $pay['reservation_id']; ?></span></td>
                            <td><span class="guest-name"><?php echo htmlspecialchars($pay['full_name'] ?? 'N/A'); ?></span></td>
                            <td><span class="room-chip">🏠 <?php echo htmlspecialchars($pay['room_name'] ?? 'N/A'); ?></span></td>
                            <td class="price-cell">₱<?php echo number_format($pay['amount'],2); ?></td>
                            <td><span class="method-chip"><?php echo htmlspecialchars($pay['payment_method']); ?></span></td>
                            <td><span class="status-badge status-<?php echo $pay['payment_status']; ?>"><?php echo $pay['payment_status']; ?></span></td>
                            <td class="date-cell"><?php echo $pay['payment_date'] ? date('M d, Y · g:i A', strtotime($pay['payment_date'])) : '—'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ─── Charts ──────────────────────────────────── -->
        <div class="section-label">
            <div class="section-label-bar" style="background: linear-gradient(180deg, var(--coral), #e08078);"></div>
            <h3>Analytics <small>— visual breakdown</small></h3>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-card-header">
                    <div class="chart-card-title">Booking Status</div>
                    <div class="chart-tag">Pie Chart</div>
                </div>
                <canvas id="pieChart"></canvas>
            </div>
            <div class="chart-card">
                <div class="chart-card-header">
                    <div class="chart-card-title">Revenue Per Day</div>
                    <div class="chart-tag">Bar Chart</div>
                </div>
                <canvas id="barChart"></canvas>
            </div>
        </div>

    </div><!-- /.dash-wrapper -->
</div><!-- /.container -->

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.color = '#7a6f65';

// ─── Pie Chart ───────────────────────────────────────
const statusLabels = <?php echo json_encode(array_column($statusCounts, 'status')); ?>;
const statusData   = <?php echo json_encode(array_column($statusCounts, 'count')); ?>;

if (document.getElementById('pieChart')) {
    new Chart(document.getElementById('pieChart'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData,
                backgroundColor: ['#f39c12','#2d7d6f','#c0544a','#3a7bbf','#9b59b6'],
                borderColor: '#ffffff',
                borderWidth: 3,
                hoverOffset: 6
            }]
        },
        options: {
            cutout: '62%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 16,
                        usePointStyle: true,
                        pointStyleWidth: 10,
                        font: { size: 12 }
                    }
                }
            }
        }
    });
}

// ─── Bar Chart ───────────────────────────────────────
const revDays    = <?php echo json_encode(array_column($revenueData, 'day')); ?>;
const revAmounts = <?php echo json_encode(array_column($revenueData, 'total')); ?>;

if (document.getElementById('barChart')) {
    new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: {
            labels: revDays,
            datasets: [{
                label: 'Revenue (₱)',
                data: revAmounts,
                backgroundColor: 'rgba(45,125,111,0.15)',
                borderColor: '#2d7d6f',
                borderWidth: 2,
                borderRadius: 6,
                borderSkipped: false,
                hoverBackgroundColor: 'rgba(45,125,111,0.28)'
            }]
        },
        options: {
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: {
                        font: { size: 11 },
                        callback: v => '₱' + Number(v).toLocaleString()
                    }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ₱' + Number(ctx.raw).toLocaleString('en-PH', {minimumFractionDigits:2})
                    }
                }
            }
        }
    });
}
</script>
</body>
</html>