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

    <!-- Typography is served locally through evenza-premium.css. -->

    <!-- Bootstrap 5 CSS & Icons -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/fonts/bootstrap-icons/bootstrap-icons.min.css">

    <!-- Tab Authentication Client Helper -->
    <script src="../assets/js/tab-auth.js"></script>

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

        .btn-signup-luxury {
            background-color: #ffd13b;
            color: #14171a !important;
            border: 2px solid #14171a;
            border-radius: 9999px;
            padding: 8px 22px;
            font-size: 13.5px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 3px 3px 0px #14171a;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .btn-signup-luxury.dropdown-toggle::after {
            display: none !important;
        }
        .btn-signup-luxury:hover, .btn-signup-luxury:focus, .show > .btn-signup-luxury {
            background-color: #14171a;
            color: #ffd13b !important;
            border-color: #14171a;
            transform: translateY(-2px);
            box-shadow: 5px 5px 0px #14171a;
        }
        .btn-signup-luxury:hover i, .show > .btn-signup-luxury i {
            color: #ffd13b !important;
        }

        .signup-menu-card {
            border-radius: 14px;
            padding: 10px 12px;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }
        .signup-menu-card:hover {
            background: #fffdf7;
            border-color: #14171a;
            box-shadow: 3px 3px 0px #ffd13b;
            transform: translateX(3px);
        }
    </style>
    <link rel="stylesheet" href="../assets/css/responsive-fixes.css">
<link rel="stylesheet" href="../assets/css/evenza-premium.css">
    <link rel="stylesheet" href="../assets/css/inline-validation.css">
    <script src="../assets/js/inline-validation.js" defer></script>
</head>
<?php if (!empty($homepage_design)): ?>
    <link rel="stylesheet" href="../assets/css/homepage.css">
<?php endif; ?>
<body<?= !empty($homepage_design) ? ' class="evenza-public evenza-home"' : ' class="evenza-public"' ?>>
    <a class="evenza-skip-link" href="#public-content">Skip to content</a>

    <!-- NAVBAR HEADER -->
    <header class="evenza-navbar">
        <div class="container-xl">
            <div class="evenza-navbar-row d-flex align-items-center justify-content-between">
                
                <!-- Logo Brand -->
                <a href="index.php" class="d-inline-flex align-items-center text-decoration-none">
                    <img src="../assets/images/evenza-logo.svg" alt="Evenza Portal Logo" height="42" style="height: 42px; width: auto;" />
                </a>

                <!-- Desktop Nav Links -->
                <nav class="d-none d-xl-flex align-items-center gap-1" aria-label="Main navigation">
                    <a href="index.php" class="evenza-nav-link <?= $current_page === 'index.php' ? 'active' : '' ?>">Home</a>
                    <a href="colleges.php" class="evenza-nav-link <?= $current_page === 'colleges.php' ? 'active' : '' ?>">Colleges</a>
                    <a href="events.php" class="evenza-nav-link <?= $current_page === 'events.php' ? 'active' : '' ?>">Events</a>
                    <a href="categories.php" class="evenza-nav-link <?= $current_page === 'categories.php' ? 'active' : '' ?>">Categories</a>
                    <a href="about.php" class="evenza-nav-link <?= $current_page === 'about.php' ? 'active' : '' ?>">About Us</a>
                    <a href="contact.php" class="evenza-nav-link <?= $current_page === 'contact.php' ? 'active' : '' ?>">Contact Us</a>
                </nav>

                <!-- Right Auth Action Buttons -->
                <div class="evenza-auth-actions d-flex align-items-center gap-3">
                    <button class="btn btn-outline-dark d-xl-none rounded-pill" type="button" data-bs-toggle="collapse" data-bs-target="#evenzaMobileNav" aria-expanded="false" aria-controls="evenzaMobileNav" aria-label="Toggle navigation">
                        <i class="bi bi-list" aria-hidden="true"></i>
                    </button>
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
                                    <li><a class="dropdown-item rounded-3 py-2 text-xs" href="../Organizer/Dashboard.php"><i class="bi bi-building me-2"></i> Organizer Portal</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item rounded-3 py-2 text-xs" href="../Student/Dashboard.php"><i class="bi bi-journal-bookmark me-2"></i> Student Portal</a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item rounded-3 py-2 text-xs text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="evenza-nav-link text-dark font-bold px-3">Login</a>
                        
                        <!-- SIGN UP DROPDOWN MENU (STUDENT, COLLEGE, UNIVERSITY) -->
                        <div class="dropdown">
                            <button class="btn-signup-luxury dropdown-toggle" type="button" id="signUpDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="signup-button-icon"><i class="bi bi-person-plus" aria-hidden="true"></i></span>
                                <span>Sign Up</span>
                                <i class="bi bi-chevron-down signup-chevron" aria-hidden="true"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end border-2 border-dark rounded-4 p-2 mt-2 shadow-lg" aria-labelledby="signUpDropdown">
                                <li class="px-3 py-2 border-bottom mb-2">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="fw-black text-dark text-xs text-uppercase tracking-wider">Join Evenza</span>
                                        <i class="bi bi-stars text-muted" aria-hidden="true"></i>
                                    </div>
                                    <small class="text-muted d-block mt-0.5" style="font-size: 11px;">Choose the account that fits you</small>
                                </li>
                                <li>
                                    <a class="dropdown-item signup-menu-card p-2.5 d-flex align-items-center gap-3" href="register.php">
                                        <div class="rounded-circle bg-warning text-dark border border-dark d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                                            <i class="bi bi-mortarboard-fill fs-6"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark text-xs">Student account</div>
                                            <small class="text-muted d-block" style="font-size: 11px;">Discover events and join your campus community</small>
                                        </div>
                                    <i class="bi bi-arrow-up-right signup-menu-arrow" aria-hidden="true"></i></a>
                                </li>
                                <li>
                                    <a class="dropdown-item signup-menu-card p-2.5 d-flex align-items-center gap-3" href="register_college.php">
                                        <div class="rounded-circle bg-warning text-dark border border-dark d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                                            <i class="bi bi-building-fill fs-6"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark text-xs">College account</div>
                                            <small class="text-muted d-block" style="font-size: 11px;">Publish events and manage registrations</small>
                                        </div>
                                    <i class="bi bi-arrow-up-right signup-menu-arrow" aria-hidden="true"></i></a>
                                </li>
                                <li>
                                    <a class="dropdown-item signup-menu-card p-2.5 d-flex align-items-center gap-3" href="register_university.php">
                                        <div class="rounded-circle bg-warning text-dark border border-dark d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                                            <i class="bi bi-bank2 fs-6"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark text-xs">University account</div>
                                            <small class="text-muted d-block" style="font-size: 11px;">Connect your institution and colleges</small>
                                        </div>
                                    <i class="bi bi-arrow-up-right signup-menu-arrow" aria-hidden="true"></i></a>
                                </li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
            <nav class="collapse d-xl-none mt-3" id="evenzaMobileNav" aria-label="Mobile navigation">
                <div class="d-flex flex-wrap gap-2 p-3 bg-white border rounded-4">
                    <?php foreach (['index.php' => 'Home', 'colleges.php' => 'Colleges', 'events.php' => 'Events', 'categories.php' => 'Categories', 'about.php' => 'About Us', 'contact.php' => 'Contact Us'] as $href => $label): ?>
                        <a class="evenza-nav-link <?= $current_page === $href ? 'active' : '' ?>" href="<?= $href ?>"<?= $current_page === $href ? ' aria-current="page"' : '' ?>><?= $label ?></a>
                    <?php endforeach; ?>
                </div>
            </nav>
        </div>
    </header>
