<?php
// includes/header.php
// Assumes session is already started and auth functions are available
$currentRole = function_exists('getCurrentUserRole') ? getCurrentUserRole() : '';
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

// Helper to mark active nav link
function isActive(string $dir): string {
    global $currentDir;
    return $currentDir === $dir ? 'nav-link--active' : '';
}
?>

<header class="site-header">
    <div class="site-header__inner">

        <!-- ─── Brand ─────────────────────────────────── -->
        <a href="/mangima_resort/dashboard/dashboard.php" class="brand">
            <span class="brand__icon">🌴</span>
            <span class="brand__name">Mangima <em>Resort</em></span>
        </a>

        <!-- ─── Hamburger (mobile) ────────────────────── -->
        <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

        <!-- ─── Nav + user ────────────────────────────── -->
        <div class="header-right" id="headerRight">

            <nav class="site-nav" aria-label="Main navigation">
                <a href="/mangima_resort/dashboard/dashboard.php"
                   class="nav-link <?php echo isActive('dashboard'); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                    Dashboard
                </a>

                <?php if ($currentRole === 'admin'): ?>
                <a href="/mangima_resort/users/"
                   class="nav-link <?php echo isActive('users'); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Users
                </a>
                <?php endif; ?>

                <?php if ($currentRole === 'admin' || $currentRole === 'staff'): ?>
                <a href="/mangima_resort/rooms/"
                   class="nav-link <?php echo isActive('rooms'); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    Rooms
                </a>

                <a href="/mangima_resort/amenities/"
                   class="nav-link <?php echo isActive('amenities'); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    Amenities
                </a>

                <a href="/mangima_resort/payments/"
                   class="nav-link <?php echo isActive('payments'); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    Payments
                </a>
                <?php endif; ?>

                <a href="/mangima_resort/reservations/"
                   class="nav-link <?php echo isActive('reservations'); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Reservations
                </a>
            </nav>

            <!-- ─── User pill ──────────────────────────── -->
            <?php if (function_exists('getCurrentUserId')): ?>
            <div class="user-menu">
                <div class="user-pill">
                    <div class="user-pill__avatar">
                        <?php
                        // Get first letter of user name if available
                        $displayName = $_SESSION['full_name'] ?? $_SESSION['email'] ?? 'User';
                        echo strtoupper(substr($displayName, 0, 1));
                        ?>
                    </div>
                    <div class="user-pill__info">
                        <span class="user-pill__name"><?php echo htmlspecialchars($displayName); ?></span>
                        <span class="user-pill__role"><?php echo ucfirst($currentRole); ?></span>
                    </div>
                    <svg class="user-pill__chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                </div>

                <div class="user-dropdown" id="userDropdown">
                    <div class="user-dropdown__header">
                        <div class="user-dropdown__avatar">
                            <?php echo strtoupper(substr($displayName, 0, 1)); ?>
                        </div>
                        <div>
                            <div class="user-dropdown__name"><?php echo htmlspecialchars($displayName); ?></div>
                            <div class="user-dropdown__role-badge role-<?php echo $currentRole; ?>"><?php echo ucfirst($currentRole); ?></div>
                        </div>
                    </div>
                    <div class="user-dropdown__divider"></div>
                    <a href="/mangima_resort/auth/logout.php" class="user-dropdown__item user-dropdown__item--danger">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        Logout
                    </a>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /.header-right -->
    </div><!-- /.site-header__inner -->
</header>

<style>
    /* ═══════════════════════════════════════════════════
       SITE HEADER — Mangima Resort
    ═══════════════════════════════════════════════════ */
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
        --white:      #ffffff;
        --border:     #e0d8cc;
        --header-h:   68px;
    }

    /* ─── Reset body top padding ──────────────────── */
    body { padding-top: var(--header-h); }

    /* ─── Header shell ────────────────────────────── */
    .site-header {
        position: fixed;
        top: 0; left: 0; right: 0;
        height: var(--header-h);
        background: rgba(255, 252, 247, 0.92);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border-bottom: 1px solid var(--border);
        z-index: 1000;
        box-shadow: 0 2px 16px rgba(26,23,20,0.06);
    }

    .site-header__inner {
        max-width: 1320px;
        margin: 0 auto;
        padding: 0 28px;
        height: 100%;
        display: flex;
        align-items: center;
        gap: 0;
    }

    /* ─── Brand ───────────────────────────────────── */
    .brand {
        display: flex;
        align-items: center;
        gap: 9px;
        text-decoration: none;
        flex-shrink: 0;
        margin-right: 32px;
    }

    .brand__icon {
        font-size: 22px;
        line-height: 1;
        filter: drop-shadow(0 1px 2px rgba(0,0,0,0.12));
    }

    .brand__name {
        font-family: 'Cormorant Garamond', serif;
        font-size: 20px;
        font-weight: 600;
        color: var(--ink);
        letter-spacing: -0.3px;
        white-space: nowrap;
    }

    .brand__name em {
        font-style: normal;
        color: var(--teal);
    }

    /* ─── Header right (nav + user) ───────────────── */
    .header-right {
        display: flex;
        align-items: center;
        gap: 6px;
        flex: 1;
    }

    /* ─── Nav ─────────────────────────────────────── */
    .site-nav {
        display: flex;
        align-items: center;
        gap: 2px;
        flex: 1;
    }

    .nav-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 13px;
        border-radius: 8px;
        font-family: 'DM Sans', sans-serif;
        font-size: 13px;
        font-weight: 500;
        color: var(--ink-muted);
        text-decoration: none;
        white-space: nowrap;
        transition: color 0.18s, background 0.18s;
        position: relative;
    }

    .nav-link svg {
        opacity: 0.6;
        transition: opacity 0.18s;
        flex-shrink: 0;
    }

    .nav-link:hover {
        color: var(--ink);
        background: var(--sand-dark);
    }

    .nav-link:hover svg { opacity: 1; }

    .nav-link--active {
        color: var(--teal);
        background: var(--teal-pale);
        font-weight: 600;
    }

    .nav-link--active svg { opacity: 1; color: var(--teal); }

    .nav-link--active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 13px; right: 13px;
        height: 2px;
        border-radius: 2px 2px 0 0;
        background: var(--teal);
    }

    /* ─── User menu ───────────────────────────────── */
    .user-menu {
        position: relative;
        margin-left: auto;
        flex-shrink: 0;
    }

    .user-pill {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 6px 12px 6px 6px;
        border-radius: 100px;
        border: 1.5px solid var(--border);
        background: var(--white);
        cursor: pointer;
        transition: all 0.2s;
        user-select: none;
    }

    .user-pill:hover {
        border-color: var(--teal);
        box-shadow: 0 2px 10px rgba(45,125,111,0.12);
    }

    .user-pill__avatar {
        width: 30px; height: 30px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--teal-pale), var(--teal-light));
        display: flex; align-items: center; justify-content: center;
        font-size: 13px; font-weight: 700;
        color: var(--teal);
        flex-shrink: 0;
        font-family: 'DM Sans', sans-serif;
    }

    .user-pill__info {
        display: flex;
        flex-direction: column;
        gap: 0;
        line-height: 1.2;
    }

    .user-pill__name {
        font-family: 'DM Sans', sans-serif;
        font-size: 13px;
        font-weight: 600;
        color: var(--ink);
        max-width: 120px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .user-pill__role {
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: var(--ink-muted);
    }

    .user-pill__chevron {
        color: var(--ink-muted);
        transition: transform 0.2s;
        flex-shrink: 0;
    }

    .user-menu.open .user-pill__chevron { transform: rotate(180deg); }
    .user-menu.open .user-pill { border-color: var(--teal); box-shadow: 0 2px 10px rgba(45,125,111,0.12); }

    /* ─── Dropdown ────────────────────────────────── */
    .user-dropdown {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        min-width: 220px;
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 12px 40px rgba(26,23,20,0.14);
        overflow: hidden;
        opacity: 0;
        transform: translateY(-8px) scale(0.97);
        pointer-events: none;
        transition: opacity 0.18s ease, transform 0.18s ease;
        transform-origin: top right;
        z-index: 100;
    }

    .user-menu.open .user-dropdown {
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: all;
    }

    .user-dropdown__header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px;
    }

    .user-dropdown__avatar {
        width: 38px; height: 38px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--teal-pale), var(--teal-light));
        display: flex; align-items: center; justify-content: center;
        font-size: 16px; font-weight: 700; color: var(--teal);
        font-family: 'DM Sans', sans-serif;
        flex-shrink: 0;
    }

    .user-dropdown__name {
        font-family: 'DM Sans', sans-serif;
        font-size: 14px; font-weight: 600; color: var(--ink);
        max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }

    .user-dropdown__role-badge {
        display: inline-flex;
        align-items: center;
        margin-top: 3px;
        font-size: 10px; font-weight: 600;
        letter-spacing: 1px; text-transform: uppercase;
        padding: 2px 8px; border-radius: 100px;
    }

    .role-admin { background: #fdf5e0; color: var(--gold-dark); }
    .role-staff { background: #eaf2fb; color: #1a4f80; }
    .role-user  { background: var(--teal-pale); color: var(--teal); }

    .user-dropdown__divider {
        height: 1px;
        background: var(--border);
        margin: 0;
    }

    .user-dropdown__item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        font-family: 'DM Sans', sans-serif;
        font-size: 13px; font-weight: 500;
        color: var(--ink-muted);
        text-decoration: none;
        transition: background 0.15s, color 0.15s;
    }

    .user-dropdown__item:hover { background: var(--sand); color: var(--ink); }

    .user-dropdown__item--danger { color: var(--coral); }
    .user-dropdown__item--danger:hover { background: var(--coral-pale); color: var(--coral); }

    /* ─── Hamburger (mobile) ──────────────────────── */
    .nav-toggle {
        display: none;
        flex-direction: column;
        justify-content: center;
        gap: 5px;
        width: 38px; height: 38px;
        padding: 8px;
        border: 1.5px solid var(--border);
        border-radius: 8px;
        background: transparent;
        cursor: pointer;
        margin-left: auto;
        transition: border-color 0.2s;
    }

    .nav-toggle:hover { border-color: var(--teal); }

    .nav-toggle span {
        display: block;
        height: 2px;
        border-radius: 2px;
        background: var(--ink-muted);
        transition: all 0.25s;
    }

    .nav-toggle.active span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
    .nav-toggle.active span:nth-child(2) { opacity: 0; }
    .nav-toggle.active span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

    /* ─── Mobile styles ───────────────────────────── */
    @media (max-width: 860px) {
        body { padding-top: var(--header-h); }

        .nav-toggle { display: flex; }

        .header-right {
            position: fixed;
            top: var(--header-h);
            left: 0; right: 0;
            background: var(--white);
            border-bottom: 1px solid var(--border);
            box-shadow: 0 8px 24px rgba(26,23,20,0.10);
            padding: 16px 20px 20px;
            flex-direction: column;
            align-items: stretch;
            gap: 4px;
            transform: translateY(-110%);
            opacity: 0;
            transition: transform 0.28s ease, opacity 0.28s ease;
            z-index: 999;
        }

        .header-right.open {
            transform: translateY(0);
            opacity: 1;
        }

        .site-nav {
            flex-direction: column;
            align-items: stretch;
            gap: 2px;
        }

        .nav-link {
            padding: 10px 14px;
            font-size: 14px;
            border-radius: 10px;
        }

        .nav-link--active::after { display: none; }

        .user-menu {
            margin-left: 0;
            margin-top: 8px;
            padding-top: 12px;
            border-top: 1px solid var(--border);
        }

        .user-pill { justify-content: flex-start; }

        .user-dropdown {
            position: static;
            opacity: 1;
            transform: none;
            pointer-events: all;
            box-shadow: none;
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-top: 8px;
        }

        .user-pill__chevron { display: none; }
    }

    @media (max-width: 480px) {
        .site-header__inner { padding: 0 16px; }
        .brand__name { font-size: 17px; }
    }
</style>

<script>
(function () {
    const toggle   = document.getElementById('navToggle');
    const right    = document.getElementById('headerRight');
    const userMenu = document.querySelector('.user-menu');
    const userPill = document.querySelector('.user-pill');

    // Mobile nav toggle
    if (toggle && right) {
        toggle.addEventListener('click', () => {
            const open = right.classList.toggle('open');
            toggle.classList.toggle('active', open);
            toggle.setAttribute('aria-expanded', open);
        });
    }

    // User dropdown toggle
    if (userPill && userMenu) {
        userPill.addEventListener('click', (e) => {
            e.stopPropagation();
            userMenu.classList.toggle('open');
        });

        document.addEventListener('click', (e) => {
            if (!userMenu.contains(e.target)) {
                userMenu.classList.remove('open');
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') userMenu.classList.remove('open');
        });
    }
})();
</script>