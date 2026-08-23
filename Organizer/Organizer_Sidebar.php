<?php
/**
 * Organizer/Organizer_Sidebar.php
 * Floating Capsule Sidebar Component for College Organizer Panel
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page  = strtolower(basename($_SERVER['PHP_SELF']));
$college_name  = $_SESSION['college_name'] ?? 'College Organizer';
$college_email = $_SESSION['college_email'] ?? 'organizer@evenza.com';
$college_logo  = $_SESSION['college_logo'] ?? '';
$logo_src      = !empty($college_logo) ? '../uploads/colleges/' . htmlspecialchars($college_logo) : 'https://ui-avatars.com/api/?name=' . urlencode($college_name) . '&background=1c2024&color=ffd13b';
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
        <a href="Dashboard.php" class="berun-nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>" title="Organizer Dashboard">
            <i class="bi bi-house-door<?= $current_page === 'dashboard.php' ? '-fill' : '' ?>"></i>
        </a>
        <a href="Events.php" class="berun-nav-item <?= $current_page === 'events.php' ? 'active' : '' ?>" title="My Hosted Events">
            <i class="bi bi-calendar3"></i>
        </a>
        <a href="Registrations.php" class="berun-nav-item <?= $current_page === 'registrations.php' ? 'active' : '' ?>" title="Event Registrations">
            <i class="bi bi-person-plus<?= $current_page === 'registrations.php' ? '-fill' : '' ?>"></i>
        </a>
        <a href="Students.php" class="berun-nav-item <?= $current_page === 'students.php' ? 'active' : '' ?>" title="College Students">
            <i class="bi bi-people"></i>
        </a>
    </div>

    <div class="dropdown">
        <img src="<?= $logo_src ?>" alt="College Logo" class="berun-avatar-pill dropdown-toggle" data-bs-toggle="dropdown" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($college_name); ?>&background=1c2024&color=ffd13b'" />
        <ul class="dropdown-menu shadow-sm border-0 rounded-4 p-2">
            <li class="px-3 py-2 border-bottom">
                <span class="fw-bold text-dark d-block text-xs"><?php echo htmlspecialchars((string)$college_name); ?></span>
                <span class="text-muted text-xs"><?php echo htmlspecialchars((string)$college_email); ?></span>
            </li>
            <li><a class="dropdown-item rounded-3 py-2 text-xs mt-1" href="Profile.php"><i class="bi bi-building me-2"></i> College Profile</a></li>
            <li><a class="dropdown-item rounded-3 py-2 text-xs text-dark" href="Profile.php?tab=security"><i class="bi bi-shield-lock me-2"></i> Change Password</a></li>
            <li><a class="dropdown-item rounded-3 py-2 text-xs text-danger" href="Logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
        </ul>
    </div>
</nav>
