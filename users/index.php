<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin'); // Only admin can manage users

$db = getDB();
$msg = '';

// Handle actions
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// ---------- CREATE/UPDATE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    $role_input = $_POST['role'] ?? 'user';
    $password = $_POST['password'] ?? '';

    if ($full_name && $email) {
        if ($id) { // Update
            $sql = "UPDATE users SET full_name=?, email=?, contact_number=?, role=? WHERE user_id=?";
            $params = [$full_name, $email, $contact, $role_input, $id];
            if (!empty($password)) {
                $sql = "UPDATE users SET full_name=?, email=?, contact_number=?, password=?, role=? WHERE user_id=?";
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $params = [$full_name, $email, $contact, $hashed, $role_input, $id];
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $msg = "User updated successfully.";
        } else { // Create
            if (empty($password)) {
                $msg = "Password is required for new user.";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (full_name, email, contact_number, password, role) VALUES (?,?,?,?,?)");
                $stmt->execute([$full_name, $email, $contact, $hashed, $role_input]);
                $msg = "User created successfully.";
            }
        }
        if (!$msg) $msg = "User saved.";
        header("Location: /mangima_resort/users/?msg=".urlencode($msg));
        exit;
    } else {
        $msg = "Name and email are required.";
    }
} elseif ($action === 'delete' && $id) {
    if ($id != getCurrentUserId()) {
        $stmt = $db->prepare("DELETE FROM users WHERE user_id=?");
        $stmt->execute([$id]);
        $msg = "User deleted.";
    } else {
        $msg = "Cannot delete your own account.";
    }
    header("Location: /mangima_resort/users/?msg=".urlencode($msg));
    exit;
}

// Fetch user for editing
$editUser = null;
if ($action === 'edit' && $id) {
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id=?");
    $stmt->execute([$id]);
    $editUser = $stmt->fetch();
}

// List all users
$users = $db->query("SELECT * FROM users ORDER BY user_id")->fetchAll();
$msg = $_GET['msg'] ?? $msg;

// Role counts
$adminCount = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
$staffCount = count(array_filter($users, fn($u) => $u['role'] === 'staff'));
$userCount  = count(array_filter($users, fn($u) => $u['role'] === 'user'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management — Mangima Resort</title>
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
        .users-wrapper {
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

        .page-sub {
            font-size: 13px;
            color: var(--ink-muted);
            margin-top: 6px;
        }

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

        .back-link:hover {
            background: var(--sand-dark);
            color: var(--ink);
            border-color: var(--ink-muted);
        }

        /* ─── Alert / message ───────────────────────────── */
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

        .alert-success {
            background: #e6f5ee;
            color: #1a6e3e;
            border: 1px solid #b8dfc9;
        }

        .alert-error {
            background: var(--coral-pale);
            color: var(--coral);
            border: 1px solid #f0c0bc;
        }

        /* ─── Stat mini-cards ───────────────────────────── */
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
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .mini-icon.all   { background: var(--sand-dark); }
        .mini-icon.admin { background: #fdf5e0; }
        .mini-icon.staff { background: var(--sky-pale); }
        .mini-icon.user  { background: var(--teal-pale); }

        .mini-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            color: var(--ink-muted);
        }

        .mini-value {
            font-family: 'Cormorant Garamond', serif;
            font-size: 26px;
            font-weight: 700;
            color: var(--ink);
            line-height: 1;
        }

        /* ─── Layout: form + table ──────────────────────── */
        .users-layout {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 24px;
            align-items: start;
        }

        /* ─── Form card ─────────────────────────────────── */
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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-card-header .form-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: var(--teal-pale);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .form-card-header h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 19px;
            font-weight: 600;
            color: var(--ink);
        }

        .form-card-body {
            padding: 24px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            color: var(--ink-muted);
            margin-bottom: 7px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            color: var(--ink);
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

        .form-group .hint {
            font-size: 11px;
            color: var(--ink-muted);
            margin-top: 5px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .btn-primary {
            flex: 1;
            padding: 11px 20px;
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
        }

        .btn-primary:hover {
            background: var(--teal-light);
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(45,125,111,0.25);
        }

        .btn-ghost {
            padding: 11px 18px;
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
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
        }

        .btn-ghost:hover {
            background: var(--sand-dark);
            border-color: var(--ink-muted);
            color: var(--ink);
        }

        /* ─── Role select styling ───────────────────────── */
        .role-select-wrap { position: relative; }
        .role-select-wrap select { appearance: none; padding-right: 36px; cursor: pointer; }
        .role-select-wrap::after {
            content: '▾';
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--ink-muted);
            pointer-events: none;
            font-size: 12px;
        }

        /* ─── Table card ─────────────────────────────────── */
        .table-panel {
            animation: fadeUp 0.4s ease 0.2s both;
        }

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
        }

        .section-label small {
            font-size: 12px;
            color: var(--ink-muted);
            font-weight: 400;
            margin-left: 4px;
            font-family: 'DM Sans', sans-serif;
        }

        .table-card {
            background: var(--white);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
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
            white-space: nowrap;
        }

        .table-card td {
            padding: 13px 16px;
            border-bottom: 1px solid #f2ede5;
            font-size: 14px;
            color: var(--ink-soft);
            vertical-align: middle;
        }

        .table-card tbody tr:last-child td { border-bottom: none; }
        .table-card tbody tr { transition: background 0.15s; }
        .table-card tbody tr:hover { background: var(--sand); }

        .user-id-chip {
            font-size: 12px;
            font-weight: 600;
            color: var(--ink-muted);
            background: var(--sand);
            padding: 2px 8px;
            border-radius: 4px;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal-pale), var(--teal-light));
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            color: var(--teal);
            flex-shrink: 0;
            margin-right: 8px;
            vertical-align: middle;
        }

        .user-name-cell {
            display: flex;
            align-items: center;
        }

        .user-name {
            font-weight: 500;
            color: var(--ink);
        }

        .email-cell {
            font-size: 13px;
            color: var(--ink-muted);
        }

        .contact-cell {
            font-size: 13px;
            color: var(--ink-soft);
        }

        /* ─── Role badges ────────────────────────────────── */
        .role-badge {
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

        .role-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .role-admin {
            background: #fdf5e0;
            color: var(--gold-dark);
        }
        .role-admin::before { background: var(--gold); }

        .role-staff {
            background: var(--sky-pale);
            color: #1a4f80;
        }
        .role-staff::before { background: var(--sky); }

        .role-user {
            background: var(--teal-pale);
            color: var(--teal);
        }
        .role-user::before { background: var(--teal-light); }

        /* ─── Action buttons ─────────────────────────────── */
        .action-cell {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-edit {
            padding: 5px 14px;
            font-size: 12px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            color: var(--teal);
            background: var(--teal-pale);
            border: 1px solid rgba(45,125,111,0.2);
            border-radius: 100px;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }

        .btn-edit:hover {
            background: var(--teal);
            color: var(--white);
        }

        .btn-delete {
            padding: 5px 14px;
            font-size: 12px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            color: var(--coral);
            background: var(--coral-pale);
            border: 1px solid rgba(192,84,74,0.2);
            border-radius: 100px;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }

        .btn-delete:hover {
            background: var(--coral);
            color: var(--white);
        }

        .self-tag {
            font-size: 11px;
            color: var(--ink-muted);
            font-style: italic;
        }

        /* ─── Animations ────────────────────────────────── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ─── Responsive ────────────────────────────────── */
        @media (max-width: 1024px) {
            .users-layout {
                grid-template-columns: 1fr;
            }
            .form-card { position: static; }
        }

        @media (max-width: 600px) {
            .page-title { font-size: 30px; }
            .users-wrapper { padding: 0 16px 48px; }
            .mini-stats { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="users-wrapper">

        <!-- ─── Page Header ──────────────────────────────── -->
        <div class="page-header">
            <div>
                <div class="page-eyebrow">Mangima Resort · Admin</div>
                <div class="page-title">User <span>Management</span></div>
                <div class="page-sub">Create, edit, and manage all system accounts</div>
            </div>
            <a href="/mangima_resort/dashboard/dashboard.php" class="back-link">
                ← Back to Dashboard
            </a>
        </div>

        <!-- ─── Alert ───────────────────────────────────── -->
        <?php if ($msg): ?>
            <?php $isError = stripos($msg, 'cannot') !== false || stripos($msg, 'required') !== false; ?>
            <div class="alert <?php echo $isError ? 'alert-error' : 'alert-success'; ?>">
                <?php echo $isError ? '⚠' : '✓'; ?>
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <!-- ─── Mini Stats ───────────────────────────────── -->
        <div class="mini-stats">
            <div class="mini-card">
                <div class="mini-icon all">👥</div>
                <div>
                    <div class="mini-label">Total</div>
                    <div class="mini-value"><?php echo count($users); ?></div>
                </div>
            </div>
            <div class="mini-card">
                <div class="mini-icon admin">⚙</div>
                <div>
                    <div class="mini-label">Admins</div>
                    <div class="mini-value"><?php echo $adminCount; ?></div>
                </div>
            </div>
            <div class="mini-card">
                <div class="mini-icon staff">🪪</div>
                <div>
                    <div class="mini-label">Staff</div>
                    <div class="mini-value"><?php echo $staffCount; ?></div>
                </div>
            </div>
            <div class="mini-card">
                <div class="mini-icon user">👤</div>
                <div>
                    <div class="mini-label">Guests</div>
                    <div class="mini-value"><?php echo $userCount; ?></div>
                </div>
            </div>
        </div>

        <!-- ─── Main Layout ──────────────────────────────── -->
        <div class="users-layout">

            <!-- ─── Form Card ───────────────────────────── -->
            <div class="form-card">
                <div class="form-card-header">
                    <div class="form-icon"><?php echo $editUser ? '✏️' : '➕'; ?></div>
                    <h3><?php echo $editUser ? 'Edit User' : 'Add New User'; ?></h3>
                </div>
                <div class="form-card-body">
                    <form method="post" action="<?php echo $editUser ? '?action=edit&id='.$editUser['user_id'] : ''; ?>">
                        <input type="hidden" name="id" value="<?php echo $editUser['user_id'] ?? ''; ?>">

                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name"
                                   placeholder="e.g. Juan dela Cruz"
                                   value="<?php echo htmlspecialchars($editUser['full_name'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email"
                                   placeholder="e.g. juan@email.com"
                                   value="<?php echo htmlspecialchars($editUser['email'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Contact Number</label>
                            <input type="text" name="contact_number"
                                   placeholder="e.g. 09XX XXX XXXX"
                                   value="<?php echo htmlspecialchars($editUser['contact_number'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label>Password<?php echo $editUser ? ' <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional)</span>' : ''; ?></label>
                            <input type="password" name="password"
                                   placeholder="<?php echo $editUser ? 'Leave blank to keep current' : 'Set a password…'; ?>"
                                   <?php echo $editUser ? '' : 'required'; ?>>
                            <?php if ($editUser): ?>
                                <div class="hint">Leave blank to keep the existing password unchanged.</div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label>Role</label>
                            <div class="role-select-wrap">
                                <select name="role">
                                    <option value="user"  <?php echo (isset($editUser['role']) && $editUser['role']==='user')  ? 'selected' : ''; ?>>👤 Guest / User</option>
                                    <option value="staff" <?php echo (isset($editUser['role']) && $editUser['role']==='staff') ? 'selected' : ''; ?>>🪪 Staff</option>
                                    <option value="admin" <?php echo (isset($editUser['role']) && $editUser['role']==='admin') ? 'selected' : ''; ?>>⚙ Administrator</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="save" class="btn-primary">
                                <?php echo $editUser ? '✓ Update User' : '+ Add User'; ?>
                            </button>
                            <?php if ($editUser): ?>
                                <a href="/mangima_resort/users/" class="btn-ghost">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ─── Table Panel ──────────────────────────── -->
            <div class="table-panel">
                <div class="section-label">
                    <div class="section-label-bar"></div>
                    <h3>All Users <small>— <?php echo count($users); ?> accounts</small></h3>
                </div>

                <div class="table-card">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Email</th>
                                <th>Contact</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td><span class="user-id-chip">#<?php echo $u['user_id']; ?></span></td>
                                <td>
                                    <div class="user-name-cell">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($u['full_name'], 0, 1)); ?>
                                        </div>
                                        <span class="user-name"><?php echo htmlspecialchars($u['full_name']); ?></span>
                                    </div>
                                </td>
                                <td class="email-cell"><?php echo htmlspecialchars($u['email']); ?></td>
                                <td class="contact-cell"><?php echo htmlspecialchars($u['contact_number']) ?: '—'; ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo $u['role']; ?>">
                                        <?php echo $u['role']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <a href="?action=edit&id=<?php echo $u['user_id']; ?>" class="btn-edit">Edit</a>
                                        <?php if ($u['user_id'] != getCurrentUserId()): ?>
                                            <a href="?action=delete&id=<?php echo $u['user_id']; ?>"
                                               class="btn-delete"
                                               onclick="return confirm('Delete <?php echo htmlspecialchars(addslashes($u['full_name'])); ?>? This cannot be undone.')">
                                               Delete
                                            </a>
                                        <?php else: ?>
                                            <span class="self-tag">You</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /.users-layout -->
    </div><!-- /.users-wrapper -->
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="/mangima_resort/assets/js/script.js"></script>
</body>
</html>