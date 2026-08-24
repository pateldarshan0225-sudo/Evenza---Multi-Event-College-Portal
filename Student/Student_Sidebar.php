<?php
/**
 * Student/Student_Sidebar.php
 * Floating Capsule Sidebar Component for Student Portal
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page  = strtolower(basename($_SERVER['PHP_SELF']));
$student_name  = $_SESSION['student_name'] ?? 'Student User';
$student_email = $_SESSION['student_email'] ?? 'student@college.edu';
$avatar_url    = 'https://ui-avatars.com/api/?name=' . urlencode($student_name) . '&background=14171a&color=ffd13b&bold=true';
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
        border: 2px solid #14171a;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        cursor: pointer;
        background: #ffffff;
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
        <a href="Dashboard.php" class="berun-nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>" title="Student Dashboard">
            <i class="bi bi-house-door<?= $current_page === 'dashboard.php' ? '-fill' : '' ?>"></i>
        </a>
        <a href="MyEvents.php" class="berun-nav-item <?= $current_page === 'myevents.php' ? 'active' : '' ?>" title="My Registered Events & Passes">
            <i class="bi bi-ticket-perforated<?= $current_page === 'myevents.php' ? '-fill' : '' ?>"></i>
        </a>
        <a href="MyTeams.php" class="berun-nav-item <?= $current_page === 'myteams.php' ? 'active' : '' ?>" title="My Teams & Squads">
            <i class="bi bi-people<?= $current_page === 'myteams.php' ? '-fill' : '' ?>"></i>
        </a>
    </div>

    <div class="dropdown">
        <img src="<?= $avatar_url ?>" alt="<?= htmlspecialchars((string)$student_name) ?>" class="berun-avatar-pill dropdown-toggle" data-bs-toggle="dropdown" />
        <ul class="dropdown-menu shadow-sm border-0 rounded-4 p-2">
            <li class="px-3 py-2 border-bottom">
                <span class="fw-bold text-dark d-block text-xs"><?= htmlspecialchars((string)$student_name); ?></span>
                <span class="text-muted text-xs"><?= htmlspecialchars((string)$student_email); ?></span>
            </li>
            <li><a class="dropdown-item rounded-3 py-2 text-xs text-dark mt-1" href="Profile.php"><i class="bi bi-person-badge me-2"></i> My Account Profile</a></li>
            <li><a class="dropdown-item rounded-3 py-2 text-xs text-dark" href="Profile.php?tab=security"><i class="bi bi-shield-lock me-2"></i> Security & Password</a></li>
            <li><a class="dropdown-item rounded-3 py-2 text-xs text-danger" href="Logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
        </ul>
    </div>
</nav>
