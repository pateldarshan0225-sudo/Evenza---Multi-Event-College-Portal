<?php
/**
 * Frontend/about.php
 * Ultra-Premium About Us & Platform Vision Page for Evenza
 */
$page_title = "About Us";
include 'connection.php';

// Fetch Live Metrics
$total_students = (int)($pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() ?: 3200);
$total_colleges = (int)($pdo->query("SELECT COUNT(*) FROM colleges")->fetchColumn() ?: 160);
$total_events   = (int)($pdo->query("SELECT COUNT(*) FROM events")->fetchColumn() ?: 85);

include 'Header.php';
?>

<style>
    :root {
        --bg-cream-canvas: #fdfbf7;
        --bg-dark-accent: #14171a;
        --color-gold-accent: #ffd13b;
    }

    .about-hero-section {
        background-color: #fffdf7;
        padding: 56px 0 48px 0;
        position: relative;
    }

    .hero-kpi-pill-badge {
        background: #ffffff;
        border: 1.5px solid #14171a;
        box-shadow: 3px 3px 0px #ffd13b;
        color: #14171a;
        font-weight: 800;
        font-size: 11px;
        padding: 6px 16px;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .about-grid-canvas {
        background-color: var(--bg-cream-canvas);
        padding: 64px 0 88px 0;
        border-top: 1px solid #f1f5f9;
        min-height: 500px;
    }

    .about-card-luxury {
        background: #ffffff;
        border-radius: 28px;
        padding: 40px;
        border: 1.5px solid #eae6df;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .stat-card-3d {
        background: #ffffff;
        border: 2px solid #14171a;
        border-radius: 24px;
        padding: 24px;
        box-shadow: 4px 4px 0px #14171a;
        text-align: center;
        transition: all 0.25s ease;
    }

    .stat-card-3d:hover {
        transform: translateY(-4px);
        box-shadow: 6px 6px 0px #ffd13b;
    }

    .stat-card-3d.dark {
        background: #14171a;
        color: #ffffff;
    }

    .stat-card-3d.gold {
        background: #ffd13b;
        color: #14171a;
    }
</style>

<!-- PAGE HERO -->
<section class="about-hero-section">
    <div class="container-xl text-center">
        <span class="hero-kpi-pill-badge mb-3">
            <i class="bi bi-intersect text-warning"></i> Enterprise Infrastructure • 160+ Campuses
        </span>
        <h1 class="fw-black text-dark display-5 mb-2" style="letter-spacing: -1.5px;">About Evenza Platform</h1>
        <p class="text-muted text-xs mx-auto" style="max-width: 580px;">
            Evenza is the unified inter-college event management platform bridging accredited universities, event organizers, and student competitors nationwide.
        </p>
    </div>
</section>

<!-- ABOUT GRID CANVAS -->
<section class="about-grid-canvas">
    <div class="container-xl">
        <div class="row g-4 align-items-center mb-5">
            
            <!-- Vision Card -->
            <div class="col-12 col-lg-6">
                <div class="about-card-luxury">
                    <div>
                        <span class="badge bg-warning text-dark font-bold px-3 py-1.5 rounded-pill text-xs mb-3">Mission & Purpose</span>
                        <h2 class="fw-black text-dark mb-3 fs-3" style="letter-spacing: -0.8px;">Empowering Campus Competitions</h2>
                        <p class="text-secondary text-xs leading-relaxed mb-3">
                            Historically, students faced challenges discovering upcoming inter-college competitions across neighboring institutions, while fest organizers managed team registrations, ticket passes, and verifications manually.
                        </p>
                        <p class="text-secondary text-xs leading-relaxed mb-0">
                            Evenza streamlines event discovery, team formation, automated verification passes, and real-time organizer dashboards into one secure, unified ecosystem.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Stats Matrix Grid -->
            <div class="col-12 col-lg-6">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="stat-card-3d dark">
                            <div class="display-6 font-black text-warning"><?= number_format($total_colleges) ?>+</div>
                            <small class="text-white-50 text-xs text-uppercase font-semibold tracking-wider">Partner Institutions</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card-3d gold">
                            <div class="display-6 font-black text-dark"><?= number_format($total_students) ?>+</div>
                            <small class="text-dark text-xs text-uppercase font-semibold tracking-wider">Student Competitors</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card-3d">
                            <div class="display-6 font-black text-dark"><?= number_format($total_events) ?>+</div>
                            <small class="text-muted text-xs text-uppercase font-semibold tracking-wider">Published Fests</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card-3d">
                            <div class="display-6 font-black text-dark">100%</div>
                            <small class="text-muted text-xs text-uppercase font-semibold tracking-wider">Digital Pass Entry</small>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<?php include 'Footer.php'; ?>
