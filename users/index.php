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
                // Update password if provided
                $sql = "UPDATE users SET full_name=?, email=?, contact_number=?, password=?, role=? WHERE user_id=?";
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $params = [$full_name, $email, $contact, $hashed, $role_input, $id];
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $msg = "User updated.";
        } else { // Create
            if (empty($password)) {
                $msg = "Password is required for new user.";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (full_name, email, contact_number, password, role) VALUES (?,?,?,?,?)");
                $stmt->execute([$full_name, $email, $contact, $hashed, $role_input]);
                $msg = "User created.";
            }
        }
        if (!$msg) $msg = "User saved.";
        header("Location: /mangima_resort/users/?msg=".urlencode($msg));
        exit;
    } else {
        $msg = "Name and email are required.";
    }
} elseif ($action === 'delete' && $id) {
    // Delete user (not yourself for safety)
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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Users - Mangima Resort</title>
    <link rel="stylesheet" href="/mangima_resort/assets/css/style.css">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <h2>Users Management</h2>
    <?php if ($msg): ?>
        <div class="success"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <!-- Form for add/edit -->
    <h3><?php echo $editUser ? 'Edit User' : 'Add New User'; ?></h3>
    <form method="post">
        <input type="hidden" name="id" value="<?php echo $editUser['user_id'] ?? ''; ?>">
        <label>Full Name:</label>
        <input type="text" name="full_name" value="<?php echo htmlspecialchars($editUser['full_name'] ?? ''); ?>" required>
        <label>Email:</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($editUser['email'] ?? ''); ?>" required>
        <label>Contact Number:</label>
        <input type="text" name="contact_number" value="<?php echo htmlspecialchars($editUser['contact_number'] ?? ''); ?>">
        <label>Password: <?php echo $editUser ? '(leave blank to keep unchanged)' : ''; ?></label>
        <input type="password" name="password" <?php echo $editUser ? '' : 'required'; ?>>
        <label>Role:</label>
        <select name="role">
            <option value="user"  <?php echo (isset($editUser['role']) && $editUser['role']=='user')?'selected':''; ?>>User</option>
            <option value="staff" <?php echo (isset($editUser['role']) && $editUser['role']=='staff')?'selected':''; ?>>Staff</option>
            <option value="admin" <?php echo (isset($editUser['role']) && $editUser['role']=='admin')?'selected':''; ?>>Admin</option>
        </select>
        <button type="submit" name="save"><?php echo $editUser ? 'Update' : 'Add'; ?></button>
        <?php if ($editUser): ?>
            <a href="/mangima_resort/users/">Cancel</a>
        <?php endif; ?>
    </form>

    <h3>User List</h3>
    <table>
        <thead>
            <tr><th>ID</th><th>Name</th><th>Email</th><th>Contact</th><th>Role</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?php echo $u['user_id']; ?></td>
                <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td><?php echo htmlspecialchars($u['contact_number']); ?></td>
                <td><?php echo $u['role']; ?></td>
                <td>
                    <a href="?action=edit&id=<?php echo $u['user_id']; ?>">Edit</a>
                    <?php if ($u['user_id'] != getCurrentUserId()): ?>
                        | <a href="?action=delete&id=<?php echo $u['user_id']; ?>" onclick="return confirmDelete()">Delete</a>
                    <?php endif; ?>
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