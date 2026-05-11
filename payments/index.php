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
    $reservation_id = (int)($_POST['reservation_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $method = $_POST['payment_method'] ?? '';
    $status = $_POST['payment_status'] ?? 'unpaid';
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d H:i:s');

    if ($reservation_id && $amount > 0) {
        if ($id) {
            $stmt = $db->prepare("UPDATE payments SET reservation_id=?, amount=?, payment_method=?, payment_status=?, payment_date=? WHERE payment_id=?");
            $stmt->execute([$reservation_id, $amount, $method, $status, $payment_date, $id]);
            $msg = "Payment updated successfully.";
        } else {
            $stmt = $db->prepare("INSERT INTO payments (reservation_id, amount, payment_method, payment_status, payment_date) VALUES (?,?,?,?,?)");
            $stmt->execute([$reservation_id, $amount, $method, $status, $payment_date]);
            $msg = "Payment recorded successfully.";
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

// Stats
$totalPayments  = count($payments);
$paidPayments   = count(array_filter($payments, fn($p) => $p['payment_status'] === 'paid'));
$unpaidPayments = $totalPayments - $paidPayments;
$totalRevenue   = array_sum(array_map(fn($p) => $p['payment_status'] === 'paid' ? $p['amount'] : 0, $payments));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments Management — Mangima Resort</title>
    <link rel="stylesheet" href="/mangima_resort/assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
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
            --amber:      #d4860a;
            --amber-pale: #fef3e2;
            --white:      #ffffff;
            --border:     #e0d8cc;
            --shadow-sm:  0 2px 8px rgba(26,23,20,0.07);
            --shadow-md:  0 8px 32px rgba(26,23,20,0.10);
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

        .payments-wrapper {
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 28px 60px;
        }

        /* ─── Page header ───────────────────────────────── */
        .page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            padding: 40px 0 28px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 36px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .page-eyebrow {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: var(--gold-dark);
            margin-bottom: 4px;
        }

        .page-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 42px;
            font-weight: 600;
            color: var(--ink);
            line-height: 1.1;
            letter-spacing: -0.5px;
        }

        .page-title span { color: var(--teal); }
        .page-sub { font-size: 13px; color: var(--ink-muted); margin-top: 6px; }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 13px;
            font-weight: 500;
            color: var(--ink-muted);
            text-decoration: none;
            padding: 9px 18px;
            border: 1.5px solid var(--border);
            border-radius: 100px;
            transition: all 0.2s;
        }

        .back-link:hover { background: var(--sand-dark); color: var(--ink); border-color: var(--ink-muted); }

        /* ─── Alert ─────────────────────────────────────── */
        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 24px;
            animation: fadeUp 0.3s ease;
        }

        .alert-success { background: #e6f5ee; color: #1a6e3e; border: 1px solid #b8dfc9; }
        .alert-error   { background: var(--coral-pale); color: var(--coral); border: 1px solid #f0c0bc; }

        /* ─── Mini stats ─────────────────────────────────── */
        .mini-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 14px;
            margin-bottom: 36px;
        }

        .mini-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: var(--shadow-sm);
            animation: fadeUp 0.4s ease both;
        }

        .mini-card:nth-child(1) { animation-delay: 0.05s; }
        .mini-card:nth-child(2) { animation-delay: 0.10s; }
        .mini-card:nth-child(3) { animation-delay: 0.15s; }
        .mini-card:nth-child(4) { animation-delay: 0.20s; }

        .mini-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }

        .mini-icon.all     { background: var(--sand-dark); }
        .mini-icon.paid    { background: var(--teal-pale); }
        .mini-icon.unpaid  { background: var(--amber-pale); }
        .mini-icon.revenue { background: var(--coral-pale); }

        .mini-label { font-size: 11px; font-weight: 600; letter-spacing: 1.4px; text-transform: uppercase; color: var(--ink-muted); }
        .mini-value { font-family: 'Cormorant Garamond', serif; font-size: 26px; font-weight: 700; color: var(--ink); line-height: 1; }

        /* ─── Layout ─────────────────────────────────────── */
        .page-layout {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 24px;
            align-items: start;
        }

        /* ─── Form card ──────────────────────────────────── */
        .form-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            position: sticky;
            top: 24px;
            animation: fadeUp 0.4s ease 0.1s both;
        }

        .form-card-header {
            padding: 20px 24px 18px;
            border-bottom: 1px solid var(--border);
            background: var(--sand);
            display: flex; align-items: center; gap: 10px;
        }

        .form-icon {
            width: 34px; height: 34px;
            border-radius: 8px;
            background: var(--teal-pale);
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
        }

        .form-card-header h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 19px; font-weight: 600; color: var(--ink);
        }

        .form-card-body { padding: 24px; }

        .form-group { margin-bottom: 16px; }

        .form-group label {
            display: block;
            font-size: 11px; font-weight: 600;
            letter-spacing: 1.4px; text-transform: uppercase;
            color: var(--ink-muted); margin-bottom: 7px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: 'DM Sans', sans-serif;
            font-size: 14px; color: var(--ink);
            background: var(--white);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--teal);
            box-shadow: 0 0 0 3px rgba(45,125,111,0.10);
        }

        .form-group input::placeholder { color: var(--ink-muted); font-size: 13px; }

        .input-prefix-wrap { position: relative; }
        .input-prefix-wrap .prefix {
            position: absolute; left: 14px; top: 50%;
            transform: translateY(-50%);
            font-size: 14px; color: var(--ink-muted);
            pointer-events: none; font-weight: 500;
        }
        .input-prefix-wrap input { padding-left: 28px; }

        .select-wrap { position: relative; }
        .select-wrap select { appearance: none; padding-right: 36px; cursor: pointer; }
        .select-wrap::after {
            content: '▾';
            position: absolute; right: 14px; top: 50%;
            transform: translateY(-50%);
            color: var(--ink-muted); pointer-events: none; font-size: 12px;
        }

        .form-actions {
            display: flex; gap: 10px;
            margin-top: 20px; padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .btn-primary {
            flex: 1; padding: 11px 20px;
            background: var(--teal); color: var(--white);
            border: none; border-radius: 100px;
            cursor: pointer; font-family: 'DM Sans', sans-serif;
            font-size: 13px; font-weight: 600;
            transition: background 0.2s, transform 0.2s, box-shadow 0.2s;
        }

        .btn-primary:hover {
            background: var(--teal-light);
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(45,125,111,0.25);
        }

        .btn-ghost {
            padding: 11px 18px; background: transparent;
            color: var(--ink-muted); border: 1.5px solid var(--border);
            border-radius: 100px; cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px; font-weight: 500;
            text-decoration: none; transition: all 0.2s;
            display: inline-flex; align-items: center; white-space: nowrap;
        }

        .btn-ghost:hover { background: var(--sand-dark); border-color: var(--ink-muted); color: var(--ink); }

        /* ─── Table panel ────────────────────────────────── */
        .table-panel { animation: fadeUp 0.4s ease 0.2s both; }

        .section-label { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }

        .section-label-bar {
            width: 4px; height: 24px; border-radius: 2px;
            background: linear-gradient(180deg, var(--teal), var(--teal-light));
            flex-shrink: 0;
        }

        .section-label h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 22px; font-weight: 600; color: var(--ink);
        }

        .section-label small {
            font-size: 12px; color: var(--ink-muted);
            font-weight: 400; margin-left: 4px; font-family: 'DM Sans', sans-serif;
        }

        .table-card {
            background: var(--white); border-radius: var(--radius);
            border: 1px solid var(--border); box-shadow: var(--shadow-sm); overflow: hidden;
        }

        .table-card table { width: 100%; border-collapse: collapse; }

        .table-card thead tr { background: var(--sand); border-bottom: 1.5px solid var(--border); }

        .table-card th {
            padding: 13px 16px; text-align: left;
            font-size: 11px; font-weight: 600;
            letter-spacing: 1.5px; text-transform: uppercase;
            color: var(--ink-muted); white-space: nowrap;
        }

        .table-card td {
            padding: 13px 16px; border-bottom: 1px solid #f2ede5;
            font-size: 14px; color: var(--ink-soft); vertical-align: middle;
        }

        .table-card tbody tr:last-child td { border-bottom: none; }
        .table-card tbody tr { transition: background 0.15s; }
        .table-card tbody tr:hover { background: var(--sand); }

        .id-chip {
            font-size: 12px; font-weight: 600; color: var(--ink-muted);
            background: var(--sand); padding: 2px 8px; border-radius: 4px;
        }

        .guest-name { font-weight: 500; color: var(--ink); }

        .room-chip {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 13px; color: var(--teal); font-weight: 500;
        }

        .method-chip {
            display: inline-flex; align-items: center;
            font-size: 12px; font-weight: 500; color: var(--ink-muted);
            background: var(--sand); padding: 3px 10px;
            border-radius: 6px; border: 1px solid var(--border);
        }

        .price-cell {
            font-family: 'Cormorant Garamond', serif;
            font-size: 19px; font-weight: 700; color: var(--ink); white-space: nowrap;
        }

        .date-cell { font-size: 13px; color: var(--ink-muted); white-space: nowrap; }

        /* ─── Status badges ──────────────────────────────── */
        .status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 12px; border-radius: 100px;
            font-size: 11px; font-weight: 600;
            letter-spacing: 0.5px; text-transform: capitalize;
        }

        .status-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; }

        .status-paid   { background: #e6f5ee; color: #1a6e3e; }
        .status-paid::before { background: #2ecc71; }

        .status-unpaid { background: var(--amber-pale); color: var(--amber); }
        .status-unpaid::before { background: #f39c12; }

        /* ─── Action buttons ─────────────────────────────── */
        .action-cell { display: flex; align-items: center; gap: 6px; }

        .btn-edit {
            padding: 5px 14px; font-size: 12px; font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            color: var(--teal); background: var(--teal-pale);
            border: 1px solid rgba(45,125,111,0.2);
            border-radius: 100px; text-decoration: none; transition: all 0.2s;
        }

        .btn-edit:hover { background: var(--teal); color: var(--white); }

        .btn-delete {
            padding: 5px 14px; font-size: 12px; font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            color: var(--coral); background: var(--coral-pale);
            border: 1px solid rgba(192,84,74,0.2);
            border-radius: 100px; text-decoration: none; transition: all 0.2s;
        }

        .btn-delete:hover { background: var(--coral); color: var(--white); }

        .no-data-row td { text-align: center; padding: 48px 20px; color: var(--ink-muted); font-size: 14px; }
        .no-data-icon { font-size: 36px; display: block; margin-bottom: 10px; opacity: 0.4; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 1024px) { .page-layout { grid-template-columns: 1fr; } .form-card { position: static; } }
        @media (max-width: 600px)  { .page-title { font-size: 30px; } .payments-wrapper { padding: 0 16px 48px; } .mini-stats { grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="payments-wrapper">

        <!-- ─── Page Header ──────────────────────────────── -->
        <div class="page-header">
            <div>
                <div class="page-eyebrow">Mangima Resort · Staff &amp; Admin</div>
                <div class="page-title">Payment <span>Management</span></div>
                <div class="page-sub">Record, update, and track all guest payments</div>
            </div>
            <a href="/mangima_resort/dashboard/dashboard.php" class="back-link">← Back to Dashboard</a>
        </div>

        <!-- ─── Alert ───────────────────────────────────── -->
        <?php if ($msg): ?>
            <?php $isError = stripos($msg, 'please') !== false || stripos($msg, 'required') !== false; ?>
            <div class="alert <?php echo $isError ? 'alert-error' : 'alert-success'; ?>">
                <?php echo $isError ? '⚠' : '✓'; ?>
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <!-- ─── Mini Stats ───────────────────────────────── -->
        <div class="mini-stats">
            <div class="mini-card">
                <div class="mini-icon all">💳</div>
                <div>
                    <div class="mini-label">Total</div>
                    <div class="mini-value"><?php echo $totalPayments; ?></div>
                </div>
            </div>
            <div class="mini-card">
                <div class="mini-icon paid">✅</div>
                <div>
                    <div class="mini-label">Paid</div>
                    <div class="mini-value"><?php echo $paidPayments; ?></div>
                </div>
            </div>
            <div class="mini-card">
                <div class="mini-icon unpaid">⏳</div>
                <div>
                    <div class="mini-label">Unpaid</div>
                    <div class="mini-value"><?php echo $unpaidPayments; ?></div>
                </div>
            </div>
            <div class="mini-card">
                <div class="mini-icon revenue">💰</div>
                <div>
                    <div class="mini-label">Revenue</div>
                    <div class="mini-value">₱<?php echo number_format($totalRevenue, 0); ?></div>
                </div>
            </div>
        </div>

        <!-- ─── Main Layout ──────────────────────────────── -->
        <div class="page-layout">

            <!-- ─── Form Card ───────────────────────────── -->
            <div class="form-card">
                <div class="form-card-header">
                    <div class="form-icon"><?php echo $editPayment ? '✏️' : '💳'; ?></div>
                    <h3><?php echo $editPayment ? 'Edit Payment' : 'Add Payment'; ?></h3>
                </div>
                <div class="form-card-body">
                    <form method="post" action="<?php echo $editPayment ? '?action=edit&id='.$editPayment['payment_id'] : ''; ?>">
                        <input type="hidden" name="id" value="<?php echo $editPayment['payment_id'] ?? ''; ?>">

                        <div class="form-group">
                            <label>Reservation</label>
                            <div class="select-wrap">
                                <select name="reservation_id" required>
                                    <option value="">— Select Reservation —</option>
                                    <?php foreach ($reservationsList as $r): ?>
                                        <option value="<?php echo $r['reservation_id']; ?>"
                                            <?php echo (isset($editPayment['reservation_id']) && $editPayment['reservation_id'] == $r['reservation_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($r['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Amount</label>
                            <div class="input-prefix-wrap">
                                <span class="prefix">₱</span>
                                <input type="number" step="0.01" min="0.01" name="amount"
                                       placeholder="0.00"
                                       value="<?php echo $editPayment['amount'] ?? ''; ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Payment Method</label>
                            <input type="text" name="payment_method"
                                   placeholder="e.g. Cash, GCash, Card…"
                                   value="<?php echo htmlspecialchars($editPayment['payment_method'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <div class="select-wrap">
                                <select name="payment_status">
                                    <option value="paid"   <?php echo (isset($editPayment['payment_status']) && $editPayment['payment_status']==='paid')   ? 'selected' : ''; ?>>✅ Paid</option>
                                    <option value="unpaid" <?php echo (isset($editPayment['payment_status']) && $editPayment['payment_status']==='unpaid') ? 'selected' : ''; ?>>⏳ Unpaid</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Payment Date &amp; Time</label>
                            <input type="datetime-local" name="payment_date"
                                   value="<?php echo isset($editPayment['payment_date']) ? date('Y-m-d\TH:i', strtotime($editPayment['payment_date'])) : ''; ?>">
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="save" class="btn-primary">
                                <?php echo $editPayment ? '✓ Update Payment' : '+ Record Payment'; ?>
                            </button>
                            <?php if ($editPayment): ?>
                                <a href="/mangima_resort/payments/" class="btn-ghost">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ─── Table Panel ──────────────────────────── -->
            <div class="table-panel">
                <div class="section-label">
                    <div class="section-label-bar"></div>
                    <h3>All Payments <small>— <?php echo $totalPayments; ?> records</small></h3>
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
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr class="no-data-row">
                                    <td colspan="9">
                                        <span class="no-data-icon">💳</span>
                                        No payments recorded yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td><span class="id-chip">#<?php echo $p['payment_id']; ?></span></td>
                                    <td><span class="id-chip">#<?php echo $p['reservation_id']; ?></span></td>
                                    <td><span class="guest-name"><?php echo htmlspecialchars($p['full_name'] ?? 'N/A'); ?></span></td>
                                    <td><span class="room-chip">🏠 <?php echo htmlspecialchars($p['room_name'] ?? 'N/A'); ?></span></td>
                                    <td><span class="price-cell">₱<?php echo number_format($p['amount'], 2); ?></span></td>
                                    <td><span class="method-chip"><?php echo htmlspecialchars($p['payment_method'] ?: '—'); ?></span></td>
                                    <td><span class="status-badge status-<?php echo $p['payment_status']; ?>"><?php echo $p['payment_status']; ?></span></td>
                                    <td class="date-cell"><?php echo $p['payment_date'] ? date('M d, Y · g:i A', strtotime($p['payment_date'])) : '—'; ?></td>
                                    <td>
                                        <div class="action-cell">
                                            <a href="?action=edit&id=<?php echo $p['payment_id']; ?>" class="btn-edit">Edit</a>
                                            <a href="?action=delete&id=<?php echo $p['payment_id']; ?>"
                                               class="btn-delete"
                                               onclick="return confirm('Delete payment #<?php echo $p['payment_id']; ?>? This cannot be undone.')">
                                               Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /.page-layout -->
    </div><!-- /.payments-wrapper -->
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="/mangima_resort/assets/js/script.js"></script>
</body>
</html>