<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$admin_email = isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : 'admin@evenza.com';
// Email se pehle wala part name ke roop me nikaal lete hain (fallback)
$admin_name  = isset($_SESSION['admin_name']) 
                ? $_SESSION['admin_name'] 
                : ucfirst(explode('@', $admin_email)[0]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <script>
    (function() {
      try {
        var t = localStorage.getItem('theme');
        var d = window.matchMedia('(prefers-color-scheme: dark)').matches;
        var theme = t || (d ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
      } catch (e) {}
    })();
  <style>
    .btn-visit-website-luxury {
        background: #ffffff;
        color: #14171a !important;
        border: 2px solid #14171a;
        border-radius: 9999px;
        padding: 7px 18px;
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 3px 3px 0px #14171a;
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        white-space: nowrap;
    }
    .btn-visit-website-luxury:hover {
        background: #ffd13b;
        color: #14171a !important;
        transform: translateY(-2px);
        box-shadow: 5px 5px 0px #14171a;
    }
  </style>
</head>
<body>

<header class="pc-header">
  <div class="header-wrapper flex max-sm:px-[15px] px-[25px] grow">
    <!-- [Mobile Media Block] start -->
    <div class="me-auto pc-mob-drp">
      <ul class="inline-flex *:min-h-header-height *:inline-flex *:items-center">
        <!-- Menu collapse Icon -->
        <li class="pc-h-item pc-sidebar-collapse max-lg:hidden lg:inline-flex">
          <a href="#" class="pc-head-link ltr:!ml-0 rtl:!mr-0" id="sidebar-hide">
            <i data-feather="menu"></i>
          </a>
        </li>
        <li class="pc-h-item pc-sidebar-popup lg:hidden">
          <a href="#" class="pc-head-link ltr:!ml-0 rtl:!mr-0" id="mobile-collapse">
            <i data-feather="menu"></i>
          </a>
        </li>
      </ul>
    </div>
    <!-- [Mobile Media Block end] -->

    <div class="ms-auto d-flex align-items-center">
      <!-- Visit Main Website Button -->
      <a href="../Frontend/index.php" class="btn-visit-website-luxury me-3">
        <i class="bi bi-globe2 text-warning"></i> Visit Main Website <i class="bi bi-arrow-up-right text-muted opacity-60"></i>
      </a>

      <ul class="inline-flex *:min-h-header-height *:inline-flex *:items-center">

        <!-- User Profile -->
        <li class="dropdown pc-h-item header-user-profile">
          <a class="pc-head-link dropdown-toggle arrow-none me-0" data-pc-toggle="dropdown" href="#" role="button"
            aria-haspopup="false" data-pc-auto-close="outside" aria-expanded="false">
            <i data-feather="user"></i>
          </a>
          <div class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown p-2 overflow-hidden">
            <div class="dropdown-header flex items-center justify-between py-4 px-5 bg-primary-500">
              <div class="flex mb-1 items-center">
                <div class="shrink-0">
                  <img src="../assets/images/user/avatar-2.jpg" alt="user-image" class="w-10 rounded-full" />
                </div>
                <div class="grow ms-3">
                  <h6 class="mb-1 text-white"><?php echo htmlspecialchars($admin_name); ?> 🖖</h6>
                  <span class="text-white"><?php echo htmlspecialchars($admin_email); ?></span>
                </div>
              </div>
            </div>
            <div class="dropdown-body py-4 px-5">
              <div class="profile-notification-scroll position-relative" style="max-height: calc(100vh - 225px)">

                <!-- Visit Main Website -->
                <a href="../Frontend/index.php" class="dropdown-item">
                  <span>
                    <i class="bi bi-globe me-2 text-warning"></i>
                    <span>Visit Main Website</span>
                  </span>
                </a>

                <!-- Change Password (working) -->
                <a href="change_password.php" class="dropdown-item">
                  <span>
                    <svg class="pc-icon text-muted me-2 inline-block">
                      <use xlink:href="#custom-lock-outline"></use>
                    </svg>
                    <span>Change Password</span>
                  </span>
                </a>

                <div class="grid my-3">
                  <a href="logout.php" class="btn btn-primary flex items-center justify-center">
                    <svg class="pc-icon me-2 w-[22px] h-[22px]">
                      <use xlink:href="#custom-logout-1-outline"></use>
                    </svg>
                    Logout
                  </a>
                </div>
              </div>
            </div>
          </div>
        </li>

      </ul>
    </div>
  </div>
</header>

<script>
function changeAdminTheme(theme) {
    if (typeof layout_change === 'function') {
        layout_change(theme);
    }
    localStorage.setItem("theme", theme);
}

function resetAdminTheme() {
    localStorage.removeItem("theme");
    if (typeof layout_change_default === 'function') {
        layout_change_default();
    }
}
</script>
</body>
</html>
