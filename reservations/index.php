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
        $user_id       = ($role === 'admin' || $role === 'staff') ? ($_POST['user_id'] ?? $userId) : $userId;
        $room_id       = (int)($_POST['room_id'] ?? 0);
        $check_in      = $_POST['check_in'] ?? '';
        $check_out     = $_POST['check_out'] ?? '';
        $guest_name    = trim($_POST['guest_name'] ?? '');
        $guest_contact = trim($_POST['guest_contact'] ?? '');
        $guest_email   = trim($_POST['guest_email'] ?? '');
        // Only admin/staff can set status; regular users are always forced to 'pending'
        $status_input  = ($role === 'admin' || $role === 'staff') ? ($_POST['status'] ?? 'pending') : 'pending';
        $selected_amenities = $_POST['amenities'] ?? [];

        if ($room_id && $check_in && $check_out && strtotime($check_out) > strtotime($check_in)) {
            $nights = (strtotime($check_out) - strtotime($check_in)) / 86400;
            $roomStmt = $db->prepare("SELECT price FROM rooms WHERE room_id=?");
            $roomStmt->execute([$room_id]);
            $roomPrice = $roomStmt->fetchColumn();
            $roomTotal = $roomPrice * $nights;

            $amenityTotal = 0;
            if (!empty($selected_amenities)) {
                $placeholders = implode(',', array_fill(0, count($selected_amenities), '?'));
                $amStmt = $db->prepare("SELECT COALESCE(SUM(price),0) FROM amenities WHERE amenity_id IN ($placeholders)");
                $amStmt->execute($selected_amenities);
                $amenityTotal = $amStmt->fetchColumn();
            }
            $total_price = $roomTotal + $amenityTotal;

            if ($id) {
                $stmt = $db->prepare("UPDATE reservations SET user_id=?, room_id=?, check_in=?, check_out=?, total_price=?, status=?, guest_name=?, guest_contact=?, guest_email=? WHERE reservation_id=?");
                $stmt->execute([$user_id, $room_id, $check_in, $check_out, $total_price, $status_input, $guest_name, $guest_contact, $guest_email, $id]);
                $db->prepare("DELETE FROM reservation_amenities WHERE reservation_id=?")->execute([$id]);
                if (!empty($selected_amenities)) {
                    $ins = $db->prepare("INSERT INTO reservation_amenities (reservation_id, amenity_id) VALUES (?,?)");
                    foreach ($selected_amenities as $amId) { $ins->execute([$id, $amId]); }
                }
                $msg = "Reservation updated successfully.";
            } else {
                $stmt = $db->prepare("INSERT INTO reservations (user_id, room_id, check_in, check_out, total_price, status, guest_name, guest_contact, guest_email) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$user_id, $room_id, $check_in, $check_out, $total_price, $status_input, $guest_name, $guest_contact, $guest_email]);
                $newResId = $db->lastInsertId();
                if (!empty($selected_amenities)) {
                    $ins = $db->prepare("INSERT INTO reservation_amenities (reservation_id, amenity_id) VALUES (?,?)");
                    foreach ($selected_amenities as $amId) { $ins->execute([$newResId, $amId]); }
                }
                $msg = "Reservation created successfully.";
            }
            header("Location: /mangima_resort/reservations/?msg=".urlencode($msg));
            exit;
        } else {
            $msg = "Please fill all fields correctly. Check-out must be after check-in.";
        }
    } elseif (isset($_POST['cancel'])) {
        $resId = $_POST['reservation_id'] ?? 0;
        if ($resId) {
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

// Fetch current user profile for pre-filling guest fields
$currentUserProfile = null;
if ($role === 'user') {
    $profileStmt = $db->prepare("SELECT full_name, contact_number, email FROM users WHERE user_id=?");
    $profileStmt->execute([$userId]);
    $currentUserProfile = $profileStmt->fetch();
}

// Fetch for edit
$editReservation = null;
$assignedAmenities = [];
if ($action === 'edit' && $id) {
    $stmt = $db->prepare("SELECT * FROM reservations WHERE reservation_id=?");
    $stmt->execute([$id]);
    $editReservation = $stmt->fetch();
    $assignedAm = $db->prepare("SELECT amenity_id FROM reservation_amenities WHERE reservation_id=?");
    $assignedAm->execute([$id]);
    $assignedAmenities = $assignedAm->fetchAll(PDO::FETCH_COLUMN);
}

// List reservations
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

$rooms     = $db->query("SELECT room_id, room_name, status FROM rooms WHERE status='available' OR room_id = " . (int)($editReservation['room_id']??0))->fetchAll();
$users     = ($role === 'admin' || $role === 'staff') ? $db->query("SELECT user_id, full_name FROM users ORDER BY full_name")->fetchAll() : [];
$amenities = $db->query("SELECT * FROM amenities ORDER BY amenity_name")->fetchAll();

// Stats
$totalRes     = count($reservations);
$confirmedRes = count(array_filter($reservations, fn($r) => $r['status'] === 'confirmed'));
$pendingRes   = count(array_filter($reservations, fn($r) => $r['status'] === 'pending'));
$cancelledRes = count(array_filter($reservations, fn($r) => $r['status'] === 'cancelled'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations — Mangima Resort</title>
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
            --purple:     #7c5cbf;
            --purple-pale:#f0ebfa;
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

        /* ─── Wrapper ───────────────────────────────────── */
        .res-wrapper {
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

        .mini-icon.all       { background: var(--sand-dark); }
        .mini-icon.confirmed { background: var(--teal-pale); }
        .mini-icon.pending   { background: var(--amber-pale); }
        .mini-icon.cancelled { background: var(--coral-pale); }

        .mini-label { font-size: 11px; font-weight: 600; letter-spacing: 1.4px; text-transform: uppercase; color: var(--ink-muted); }
        .mini-value { font-family: 'Cormorant Garamond', serif; font-size: 26px; font-weight: 700; color: var(--ink); line-height: 1; }

        /* ─── Layout ─────────────────────────────────────── */
        .page-layout {
            display: grid;
            grid-template-columns: 360px 1fr;
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

        .form-group { margin-bottom: 15px; }

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
            background: var(--white); outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--teal);
            box-shadow: 0 0 0 3px rgba(45,125,111,0.10);
        }

        .form-group input::placeholder { color: var(--ink-muted); font-size: 13px; }

        .select-wrap { position: relative; }
        .select-wrap select { appearance: none; padding-right: 36px; cursor: pointer; }
        .select-wrap::after {
            content: '▾';
            position: absolute; right: 14px; top: 50%;
            transform: translateY(-50%);
            color: var(--ink-muted); pointer-events: none; font-size: 12px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        /* ─── Amenities checklist ────────────────────────── */
        .amenities-box {
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            max-height: 150px;
            overflow-y: auto;
            background: var(--white);
            transition: border-color 0.2s;
        }

        .amenities-box:focus-within { border-color: var(--teal); }

        .amenity-check-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 14px;
            border-bottom: 1px solid #f2ede5;
            cursor: pointer;
            transition: background 0.15s;
        }

        .amenity-check-item:last-child { border-bottom: none; }
        .amenity-check-item:hover { background: var(--sand); }

        .amenity-check-item input[type="checkbox"] {
            width: 16px; height: 16px;
            accent-color: var(--teal);
            cursor: pointer;
            flex-shrink: 0;
            margin: 0;
            border: none; padding: 0;
        }

        .amenity-check-label {
            font-size: 13px;
            color: var(--ink-soft);
            flex: 1;
        }

        .amenity-check-price {
            font-size: 12px;
            font-weight: 600;
            color: var(--teal);
            font-family: 'Cormorant Garamond', serif;
            font-size: 14px;
        }

        .amenity-free-tag {
            font-size: 11px;
            font-weight: 600;
            color: var(--amber);
            background: var(--amber-pale);
            padding: 2px 7px;
            border-radius: 4px;
        }

        .form-hint { font-size: 11px; color: var(--ink-muted); margin-top: 5px; }

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

        /* ─── Cell styles ────────────────────────────────── */
        .id-chip {
            font-size: 12px; font-weight: 600; color: var(--ink-muted);
            background: var(--sand); padding: 2px 8px; border-radius: 4px;
        }

        .guest-cell { display: flex; align-items: center; gap: 8px; }

        .guest-avatar {
            width: 30px; height: 30px; border-radius: 50%;
            background: linear-gradient(135deg, var(--teal-pale), var(--teal-light));
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; color: var(--teal); flex-shrink: 0;
        }

        .guest-name { font-weight: 500; color: var(--ink); }

        .room-chip {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 13px; color: var(--teal); font-weight: 500;
        }

        .date-cell { font-size: 13px; color: var(--ink-muted); white-space: nowrap; }

        .price-cell {
            font-family: 'Cormorant Garamond', serif;
            font-size: 19px; font-weight: 700; color: var(--ink); white-space: nowrap;
        }

        /* ─── Status badges ──────────────────────────────── */
        .status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 12px; border-radius: 100px;
            font-size: 11px; font-weight: 600;
            letter-spacing: 0.5px; text-transform: capitalize;
        }

        .status-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; }

        .status-confirmed  { background: #e6f5ee; color: #1a6e3e; }
        .status-confirmed::before  { background: #2ecc71; }

        .status-pending    { background: var(--amber-pale); color: var(--amber); }
        .status-pending::before    { background: #f39c12; }

        .status-cancelled  { background: var(--coral-pale); color: var(--coral); }
        .status-cancelled::before  { background: var(--coral); }

        .status-checked_in { background: var(--sky-pale); color: #1a4f80; }
        .status-checked_in::before { background: var(--sky); }

        .status-checked_out { background: var(--purple-pale); color: var(--purple); }
        .status-checked_out::before { background: var(--purple); }

        /* ─── Action buttons ─────────────────────────────── */
        .action-cell { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }

        .btn-edit {
            padding: 5px 14px; font-size: 12px; font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            color: var(--teal); background: var(--teal-pale);
            border: 1px solid rgba(45,125,111,0.2);
            border-radius: 100px; text-decoration: none; transition: all 0.2s;
        }

        .btn-edit:hover { background: var(--teal); color: var(--white); }

        .btn-cancel-inline {
            padding: 5px 14px; font-size: 12px; font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            color: var(--coral); background: var(--coral-pale);
            border: 1px solid rgba(192,84,74,0.2);
            border-radius: 100px; cursor: pointer; transition: all 0.2s;
        }

        .btn-cancel-inline:hover { background: var(--coral); color: var(--white); }

        .no-data-row td { text-align: center; padding: 48px 20px; color: var(--ink-muted); font-size: 14px; }
        .no-data-icon { font-size: 36px; display: block; margin-bottom: 10px; opacity: 0.4; }

        /* ─── Scrollbar styling for amenities box ──────── */
        .amenities-box::-webkit-scrollbar { width: 4px; }
        .amenities-box::-webkit-scrollbar-track { background: var(--sand); }
        .amenities-box::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

        /* ─── Guest info section ────────────────────────── */
        .guest-info-section {
            background: var(--sand);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 16px 16px 4px;
            margin-bottom: 16px;
        }

        .guest-info-heading {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.6px;
            text-transform: uppercase;
            color: var(--teal);
            margin-bottom: 14px;
        }

        .guest-info-heading svg { color: var(--teal); flex-shrink: 0; }

        /* ─── Guest table cells ──────────────────────────── */
        .guest-email {
            font-size: 11px;
            color: var(--ink-muted);
            margin-top: 1px;
        }

        .contact-chip {
            display: inline-flex;
            align-items: center;
            font-size: 12px;
            font-weight: 500;
            color: var(--ink-soft);
            white-space: nowrap;
        }

        .no-contact { color: var(--ink-muted); font-size: 13px; }

        /* ─── Status read-only (user role) ──────────────── */
        .status-readonly-wrap {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .status-readonly-hint {
            font-size: 11px;
            color: var(--ink-muted);
            font-style: italic;
            margin: 0;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 1100px) { .page-layout { grid-template-columns: 1fr; } .form-card { position: static; } }
        @media (max-width: 600px)  { .page-title { font-size: 30px; } .res-wrapper { padding: 0 16px 48px; } .mini-stats { grid-template-columns: 1fr 1fr; } .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="res-wrapper">

        <!-- ─── Page Header ──────────────────────────────── -->
        <div class="page-header">
            <div>
                <div class="page-eyebrow">Mangima Resort · <?php echo ucfirst($role); ?></div>
                <div class="page-title">Reservations <span>&amp; Bookings</span></div>
                <div class="page-sub">Create and manage all guest reservations</div>
            </div>
            <a href="/mangima_resort/dashboard/dashboard.php" class="back-link">← Back to Dashboard</a>
        </div>

        <!-- ─── Alert ───────────────────────────────────── -->
        <?php if ($msg): ?>
            <?php $isError = stripos($msg, 'please') !== false || stripos($msg, 'not authorized') !== false; ?>
            <div class="alert <?php echo $isError ? 'alert-error' : 'alert-success'; ?>">
                <?php echo $isError ? '⚠' : '✓'; ?>
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <!-- ─── Mini Stats ───────────────────────────────── -->
        <div class="mini-stats">
            <div class="mini-card">
                <div class="mini-icon all">📅</div>
                <div>
                    <div class="mini-label">Total</div>
                    <div class="mini-value"><?php echo $totalRes; ?></div>
                </div>
            </div>
            <div class="mini-card">
                <div class="mini-icon confirmed">✅</div>
                <div>
                    <div class="mini-label">Confirmed</div>
                    <div class="mini-value"><?php echo $confirmedRes; ?></div>
                </div>
            </div>
            <div class="mini-card">
                <div class="mini-icon pending">⏳</div>
                <div>
                    <div class="mini-label">Pending</div>
                    <div class="mini-value"><?php echo $pendingRes; ?></div>
                </div>
            </div>
            <div class="mini-card">
                <div class="mini-icon cancelled">✕</div>
                <div>
                    <div class="mini-label">Cancelled</div>
                    <div class="mini-value"><?php echo $cancelledRes; ?></div>
                </div>
            </div>
        </div>

        <!-- ─── Main Layout ──────────────────────────────── -->
        <div class="page-layout">

            <!-- ─── Form Card ───────────────────────────── -->
            <div class="form-card">
                <div class="form-card-header">
                    <div class="form-icon"><?php echo $editReservation ? '✏️' : '📅'; ?></div>
                    <h3><?php echo $editReservation ? 'Edit Reservation' : 'New Reservation'; ?></h3>
                </div>
                <div class="form-card-body">
                    <form method="post" action="<?php echo $editReservation ? '?action=edit&id='.$editReservation['reservation_id'] : ''; ?>">
                        <input type="hidden" name="id" value="<?php echo $editReservation['reservation_id'] ?? ''; ?>">

                        <?php if ($role === 'admin' || $role === 'staff'): ?>
                        <div class="form-group">
                            <label>Guest / User</label>
                            <div class="select-wrap">
                                <select name="user_id" required>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?php echo $u['user_id']; ?>"
                                            <?php echo (isset($editReservation['user_id']) && $editReservation['user_id'] == $u['user_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- ─── Guest Contact Info ───────────────────── -->
                        <div class="guest-info-section">
                            <div class="guest-info-heading">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                Guest Contact Details
                            </div>

                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="guest_name"
                                       placeholder="Guest full name"
                                       value="<?php echo htmlspecialchars(
                                           $editReservation['guest_name']
                                           ?? $currentUserProfile['full_name']
                                           ?? ''
                                       ); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Contact Number</label>
                                <input type="text" name="guest_contact"
                                       placeholder="e.g. 09XX XXX XXXX"
                                       value="<?php echo htmlspecialchars(
                                           $editReservation['guest_contact']
                                           ?? $currentUserProfile['contact_number']
                                           ?? ''
                                       ); ?>">
                            </div>

                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="guest_email"
                                       placeholder="e.g. guest@email.com"
                                       value="<?php echo htmlspecialchars(
                                           $editReservation['guest_email']
                                           ?? $currentUserProfile['email']
                                           ?? ''
                                       ); ?>">
                            </div>
                        </div>
                        <!-- ─── End Guest Info ───────────────────────── -->

                        <div class="form-group">
                            <label>Room</label>
                            <div class="select-wrap">
                                <select name="room_id" required>
                                    <?php foreach ($rooms as $r): ?>
                                        <option value="<?php echo $r['room_id']; ?>"
                                            <?php echo (isset($editReservation['room_id']) && $editReservation['room_id'] == $r['room_id']) ? 'selected' : ''; ?>>
                                            🏠 <?php echo htmlspecialchars($r['room_name']); ?> (<?php echo $r['status']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Check-in</label>
                                <input type="date" name="check_in"
                                       value="<?php echo $editReservation['check_in'] ?? ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Check-out</label>
                                <input type="date" name="check_out"
                                       value="<?php echo $editReservation['check_out'] ?? ''; ?>" required>
                            </div>
                        </div>

                        <?php if ($role === 'admin' || $role === 'staff'): ?>
                        <div class="form-group">
                            <label>Status</label>
                            <div class="select-wrap">
                                <select name="status">
                                    <option value="pending"     <?php echo (isset($editReservation['status']) && $editReservation['status']==='pending')     ? 'selected' : ''; ?>>⏳ Pending</option>
                                    <option value="confirmed"   <?php echo (isset($editReservation['status']) && $editReservation['status']==='confirmed')   ? 'selected' : ''; ?>>✅ Confirmed</option>
                                    <option value="checked_in"  <?php echo (isset($editReservation['status']) && $editReservation['status']==='checked_in')  ? 'selected' : ''; ?>>🔑 Checked In</option>
                                    <option value="checked_out" <?php echo (isset($editReservation['status']) && $editReservation['status']==='checked_out') ? 'selected' : ''; ?>>🏁 Checked Out</option>
                                </select>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="form-group">
                            <label>Status</label>
                            <div class="status-readonly-wrap">
                                <?php
                                    $currentStatus = $editReservation['status'] ?? 'pending';
                                    $statusLabels  = [
                                        'pending'     => '⏳ Pending',
                                        'confirmed'   => '✅ Confirmed',
                                        'checked_in'  => '🔑 Checked In',
                                        'checked_out' => '🏁 Checked Out',
                                        'cancelled'   => '✕ Cancelled',
                                    ];
                                ?>
                                <span class="status-badge status-<?php echo $currentStatus; ?>">
                                    <?php echo $statusLabels[$currentStatus] ?? ucfirst($currentStatus); ?>
                                </span>
                                <p class="status-readonly-hint">Status is managed by resort staff.</p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label>Amenities <span style="font-weight:400; text-transform:none; letter-spacing:0; font-size:11px;">(optional add-ons)</span></label>
                            <div class="amenities-box">
                                <?php if (empty($amenities)): ?>
                                    <div style="padding: 14px; font-size:13px; color: var(--ink-muted);">No amenities available.</div>
                                <?php else: ?>
                                    <?php foreach ($amenities as $am):
                                        $checked = in_array($am['amenity_id'], $assignedAmenities) ? 'checked' : '';
                                    ?>
                                    <label class="amenity-check-item">
                                        <input type="checkbox" name="amenities[]"
                                               value="<?php echo $am['amenity_id']; ?>" <?php echo $checked; ?>>
                                        <span class="amenity-check-label"><?php echo htmlspecialchars($am['amenity_name']); ?></span>
                                        <?php if ((float)$am['price'] == 0): ?>
                                            <span class="amenity-free-tag">Free</span>
                                        <?php else: ?>
                                            <span class="amenity-check-price">₱<?php echo number_format($am['price'], 2); ?></span>
                                        <?php endif; ?>
                                    </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="form-hint">Total price is auto-calculated from room rate × nights + amenities.</div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="save" class="btn-primary">
                                <?php echo $editReservation ? '✓ Update Reservation' : '🏖 Book Now'; ?>
                            </button>
                            <?php if ($editReservation): ?>
                                <a href="/mangima_resort/reservations/" class="btn-ghost">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ─── Table Panel ──────────────────────────── -->
            <div class="table-panel">
                <div class="section-label">
                    <div class="section-label-bar"></div>
                    <h3>
                        <?php echo ($role === 'user') ? 'My Reservations' : 'All Reservations'; ?>
                        <small>— <?php echo $totalRes; ?> total</small>
                    </h3>
                </div>

                <div class="table-card">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Guest</th>
                                <th>Contact</th>
                                <th>Room</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reservations)): ?>
                                <tr class="no-data-row">
                                    <td colspan="9">
                                        <span class="no-data-icon">📅</span>
                                        No reservations found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reservations as $res): ?>
                                <tr>
                                    <td><span class="id-chip">#<?php echo $res['reservation_id']; ?></span></td>
                                    <td>
                                        <div class="guest-cell">
                                            <div class="guest-avatar"><?php echo strtoupper(substr($res['guest_name'] ?: $res['full_name'], 0, 1)); ?></div>
                                            <div>
                                                <div class="guest-name"><?php echo htmlspecialchars($res['guest_name'] ?: $res['full_name']); ?></div>
                                                <div class="guest-email"><?php echo htmlspecialchars($res['guest_email'] ?? ''); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="contact-cell">
                                            <?php if (!empty($res['guest_contact'])): ?>
                                                <span class="contact-chip">📞 <?php echo htmlspecialchars($res['guest_contact']); ?></span>
                                            <?php else: ?>
                                                <span class="no-contact">—</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><span class="room-chip">🏠 <?php echo htmlspecialchars($res['room_name']); ?></span></td>
                                    <td class="date-cell"><?php echo date('M d, Y', strtotime($res['check_in'])); ?></td>
                                    <td class="date-cell"><?php echo date('M d, Y', strtotime($res['check_out'])); ?></td>
                                    <td><span class="price-cell">₱<?php echo number_format($res['total_price'], 2); ?></span></td>
                                    <td><span class="status-badge status-<?php echo $res['status']; ?>"><?php echo str_replace('_', ' ', $res['status']); ?></span></td>
                                    <td>
                                        <div class="action-cell">
                                            <?php if ($role === 'admin' || $role === 'staff' || $res['user_id'] == $userId): ?>
                                                <a href="?action=edit&id=<?php echo $res['reservation_id']; ?>" class="btn-edit">Edit</a>
                                            <?php endif; ?>
                                            <?php if ($res['status'] !== 'cancelled' && $res['status'] !== 'checked_out' && ($role === 'admin' || $role === 'staff' || $res['user_id'] == $userId)): ?>
                                                <form method="post" style="display:contents;" onsubmit="return confirm('Cancel reservation #<?php echo $res['reservation_id']; ?>?');">
                                                    <input type="hidden" name="reservation_id" value="<?php echo $res['reservation_id']; ?>">
                                                    <button type="submit" name="cancel" class="btn-cancel-inline">Cancel</button>
                                                </form>
                                            <?php endif; ?>
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
    </div><!-- /.res-wrapper -->
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>