<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['role'] ?? 'guest';
$fullName = $_SESSION['full_name'] ?? '';
?>
<header style="background:#f0f0f0; padding:10px; margin-bottom:20px;">
    <strong>Mangima Resort</strong> |
    <a href="/mangima_resort/dashboard/dashboard.php">Dashboard</a>

    <?php if ($role === 'admin'): ?>
        | <a href="/mangima_resort/users/">Users</a>
    <?php endif; ?>

    <?php if ($role === 'admin' || $role === 'staff'): ?>
        | <a href="/mangima_resort/rooms/">Rooms</a>
        | <a href="/mangima_resort/amenities/">Amenities</a>
        | <a href="/mangima_resort/payments/">Payments</a>
    <?php endif; ?>

    | <a href="/mangima_resort/reservations/">Reservations</a>

    <span style="float:right;">
        <?php echo htmlspecialchars($fullName); ?> (<?php echo $role; ?>)
        | <a href="/mangima_resort/auth/logout.php">Logout</a>
    </span>
</header>