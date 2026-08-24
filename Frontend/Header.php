<?php
/**
 * Frontend/Header.php
 * Navigation Header for Evenza Public Portal
 */
include_once __DIR__ . '/frontend_auth.php';
$current_page = strtolower(basename($_SERVER['PHP_SELF']));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' | Evenza' : 'Evenza - Inter-College Event Management Platform' ?></title>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --bg-cream: #fef9ef;
            --bg-canvas: #fdfbf7;
            --bg-dark: #14171a;
            --bg-dark-hover: #22272c;
            --color-yellow: #ffd13b;
            --color-mint: #e6f4f1;
            --color-text-dark: #14171a;
            --color-text-muted: #6b7280;
            --font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            --card-radius: 24px;
            --shadow-subtle: 0 4px 24px rgba(0, 0, 0, 0.03);
            --shadow-elevated: 0 12px 32px rgba(0, 0, 0, 0.06);
        }

        * { box-sizing: border-box; }

        body {
            background-color: var(--bg-canvas);
            font-family: var(--font-family);
            color: var(--color-text-dark);
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }

        /* Top Navbar Styling */
        .evenza-navbar {
            background: transparent;
            padding: 24px 0;
            transition: all 0.3s ease;
        }

        .evenza-logo-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--color-text-dark);
        }

        .evenza-logo-box {
            border: 2px solid var(--color-text-dark);
            border-radius: 12px;
            padding: 4px 12px;
            font-weight: 800;
            font-size: 18px;
            letter-spacing: -0.4px;
            background: #ffffff;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .evenza-nav-link {
            font-weight: 600;
            font-size: 14px;
            color: #4b5563;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 9999px;
            transition: all 0.2s ease;
        }

        .evenza-nav-link:hover, .evenza-nav-link.active {
            color: var(--color-text-dark);
            background: rgba(0, 0, 0, 0.04);
        }

        .btn-capsule-dark {
            background-color: var(--bg-dark);
            color: #ffffff !important;
            border: none;
            border-radius: 9999px;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-capsule-dark:hover {
            background-color: var(--bg-dark-hover);
            transform: translateY(-1px);
        }

        .btn-capsule-outline {
            background-color: transparent;
            color: var(--color-text-dark) !important;
            border: 1.5px solid var(--color-text-dark);
            border-radius: 9999px;
            padding: 9px 22px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-capsule-outline:hover {
            background-color: var(--color-text-dark);
            color: #ffffff !important;
        }
    </style>
</head>
<body>

    <!-- NAVBAR HEADER -->
    <header class="evenza-navbar">
        <div class="container-xl">
            <div class="d-flex align-items-center justify-content-between">
                
                <!-- Logo Brand -->
                <a href="index.php" class="evenza-logo-brand">
                    <div class="evenza-logo-box">
                        <i class="bi bi-intersect text-warning"></i> Evenza
                    </div>
                </a>

                <!-- Desktop Nav Links -->
                <nav class="d-none d-lg-flex align-items-center gap-1">
                    <a href="index.php" class="evenza-nav-link <?= $current_page === 'index.php' ? 'active' : '' ?>">Home</a>
                    <a href="colleges.php" class="evenza-nav-link <?= $current_page === 'colleges.php' ? 'active' : '' ?>">Colleges</a>
                    <a href="events.php" class="evenza-nav-link <?= $current_page === 'events.php' ? 'active' : '' ?>">Events</a>
                    <a href="categories.php" class="evenza-nav-link <?= $current_page === 'categories.php' ? 'active' : '' ?>">Categories</a>
                    <a href="about.php" class="evenza-nav-link <?= $current_page === 'about.php' ? 'active' : '' ?>">About Us</a>
                    <a href="contact.php" class="evenza-nav-link <?= $current_page === 'contact.php' ? 'active' : '' ?>">Contact Us</a>
                </nav>

                <!-- Right Auth Action Buttons -->
                <div class="d-flex align-items-center gap-3">
                    <?php if ($is_logged_in): ?>
                        <div class="dropdown">
                            <button class="btn-capsule-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> <?= htmlspecialchars((string)$user_name) ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 p-2 mt-2">
                                <li class="px-3 py-2 border-bottom">
                                    <span class="fw-bold text-dark text-xs d-block"><?= htmlspecialchars((string)$user_name) ?></span>
                                    <span class="text-muted text-xs"><?= ucfirst((string)$user_role) ?> Account</span>
                                </li>
                                <?php if ($user_role === 'admin'): ?>
                                    <li><a class="dropdown-item rounded-3 py-2 text-xs" href="../Admin/Dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Admin Portal</a></li>
                                <?php elseif ($user_role === 'organizer'): ?>
                                    <li><a class="dropdown-item rounded-3 py-2 text-xs" href="../Organizer/Dashboard.php"><i class="bi bi-building me-2"></i> Organizer Command Center</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item rounded-3 py-2 text-xs" href="dashboard.php"><i class="bi bi-journal-bookmark me-2"></i> Student Portal</a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item rounded-3 py-2 text-xs text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="evenza-nav-link text-dark font-bold px-3">Login</a>
                        <a href="register.php" class="btn-capsule-outline">Sign Up</a>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </header>
