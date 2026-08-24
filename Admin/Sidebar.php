<?php 
/**
 * Sidebar.php
 * Unified Be.run Capsule Sidebar Component.
 * Automatically highlights the active menu item based on the current PHP script name.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = strtolower(basename($_SERVER['PHP_SELF'])); 
$admin_name   = $_SESSION['admin_name'] ?? 'Admin';
$admin_email  = $_SESSION['admin_email'] ?? 'admin@evenza.com';
?>

<style>
    .berun-sidebar-capsule {
        background: #ffffff;
        border-radius: 40px;
        width: 68px;
        padding: 20px 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-start;
        gap: 18px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
        flex-shrink: 0;
        position: sticky;
        top: 20px;
        align-self: flex-start;
        z-index: 100;
    }

    .berun-nav-group {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 16px;
        width: 100%;
    }

    .berun-sidebar-capsule .dropdown {
        margin-top: 4px;
    }

    .berun-nav-item {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #72757c;
        text-decoration: none;
        font-size: 18px;
        transition: all 0.2s ease;
        position: relative;
    }

    .berun-nav-item:hover {
        color: #1c2024;
        background: rgba(0, 0, 0, 0.04);
    }

    .berun-nav-item.active {
        background-color: #1c2024;
        color: #ffd13b !important;
        box-shadow: 0 6px 16px rgba(28, 32, 36, 0.25);
    }

    .berun-avatar-pill {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #ffffff;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        cursor: pointer;
    }

    @media (max-width: 768px) {
        .berun-sidebar-capsule {
            width: 100%;
            flex-direction: row;
            height: 60px;
            padding: 0 16px;
            border-radius: 9999px;
            position: relative;
            top: 0;
        }
        .berun-nav-group {
            flex-direction: row;
            justify-content: space-around;
            width: auto;
            flex-grow: 1;
        }
    }
</style>

<nav class="berun-sidebar-capsule">
    <div class="berun-nav-group">
        <a href="Dashboard.php" class="berun-nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>" title="Dashboard">
            <i class="bi bi-house-door<?= $current_page === 'dashboard.php' ? '-fill' : '' ?>"></i>
        </a>
        <a href="alluniversity.php" class="berun-nav-item <?= $current_page === 'alluniversity.php' ? 'active' : '' ?>" title="Universities">
            <i class="bi bi-globe"></i>
        </a>
        <a href="Colleges.php" class="berun-nav-item <?= $current_page === 'colleges.php' ? 'active' : '' ?>" title="Colleges">
            <i class="bi bi-building"></i>
        </a>
        <a href="allstudents.php" class="berun-nav-item <?= $current_page === 'allstudents.php' ? 'active' : '' ?>" title="Students">
            <i class="bi bi-people"></i>
        </a>
        <a href="event_cat.php" class="berun-nav-item <?= $current_page === 'event_cat.php' ? 'active' : '' ?>" title="Categories">
            <i class="bi bi-grid-fill"></i>
        </a>
        <a href="all_events.php" class="berun-nav-item <?= $current_page === 'all_events.php' ? 'active' : '' ?>" title="Events">
            <i class="bi bi-calendar3"></i>
        </a>
        <a href="allregistrations.php" class="berun-nav-item <?= $current_page === 'allregistrations.php' ? 'active' : '' ?>" title="Registrations">
            <i class="bi bi-person-plus<?= $current_page === 'allregistrations.php' ? '-fill' : '' ?>"></i>
        </a>
        <a href="allteams.php" class="berun-nav-item <?= in_array($current_page, ['allteams.php', 'teamdetails.php', 'teammembers.php'], true) ? 'active' : '' ?>" title="Teams">
            <i class="bi bi-mortarboard<?= in_array($current_page, ['allteams.php', 'teamdetails.php', 'teammembers.php'], true) ? '-fill' : '' ?>"></i>
        </a>
        <a href="../Frontend/index.php" class="berun-nav-item text-warning" title="Visit Main Website Home">
            <i class="bi bi-globe"></i>
        </a>
    </div>

    <div class="dropdown">
        <img src="../assets/images/user/avatar-2.jpg" alt="Admin Avatar" class="berun-avatar-pill dropdown-toggle" data-bs-toggle="dropdown" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($admin_name); ?>&background=1c2024&color=ffd13b'" />
        <ul class="dropdown-menu shadow-sm border-0 rounded-4 p-2">
            <li class="px-3 py-2 border-bottom">
                <span class="fw-bold text-dark d-block text-xs"><?php echo htmlspecialchars((string)$admin_name); ?></span>
                <span class="text-muted text-xs"><?php echo htmlspecialchars((string)$admin_email); ?></span>
            </li>
            <li><a class="dropdown-item rounded-3 py-2 text-xs mt-1" href="../Frontend/index.php"><i class="bi bi-globe me-2 text-warning"></i> Visit Main Website</a></li>
            <li><a class="dropdown-item rounded-3 py-2 text-xs" href="change_password.php"><i class="bi bi-lock me-2"></i> Change Password</a></li>
            <li><a class="dropdown-item rounded-3 py-2 text-xs text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
        </ul>
    </div>
</nav>