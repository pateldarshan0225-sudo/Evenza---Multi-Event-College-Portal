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
        border: 2px solid #14171a;
        box-shadow: 4px 4px 0px #ffd13b;
        color: #14171a;
        font-weight: 800;
        font-size: 12px;
        padding: 8px 20px;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        line-height: 1.2;
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

    .stat-card-bespoke {
        background: #ffffff;
        border: 2px solid #14171a;
        border-radius: 24px;
        padding: 24px 20px;
        box-shadow: 4px 4px 0px #14171a;
        text-align: center;
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }

    .stat-card-bespoke:hover {
        transform: translateY(-6px);
        box-shadow: 6px 6px 0px #ffd13b;
    }

    .stat-card-bespoke.dark-pod {
        background: #14171a;
        color: #ffffff;
    }

    .stat-card-bespoke.gold-pod {
        background: #ffd13b;
        color: #14171a;
    }

    .pillar-card-bespoke {
        background: #ffffff;
        border-radius: 28px;
        padding: 32px;
        border: 1.5px solid #eae6df;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
        height: 100%;
        transition: all 0.3s ease;
    }

    .pillar-card-bespoke:hover {
        transform: translateY(-6px);
        border-color: #14171a;
        box-shadow: 0 20px 42px rgba(0, 0, 0, 0.06);
    }

    .pillar-icon-box {
        width: 54px;
        height: 54px;
        border-radius: 18px;
        background: #ffd13b;
        border: 2px solid #14171a;
        color: #14171a;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        margin-bottom: 20px;
        box-shadow: 3px 3px 0px #14171a;
    }

    .trust-guarantee-strip {
        background: #ffffff;
        border: 2px solid #14171a;
        border-radius: 24px;
        padding: 24px 32px;
        box-shadow: 6px 6px 0px #14171a;
    }
</style>

<!-- PAGE HERO -->
<section class="about-hero-section">
    <div class="container-xl text-center">
        <div class="mb-3">
            <span class="hero-kpi-pill-badge">
                <i class="bi bi-intersect text-warning fs-6"></i> Enterprise Infrastructure • 160+ Campuses
            </span>
        </div>
        <h1 class="fw-black text-dark display-5 mb-2" style="letter-spacing: -1.5px;">About Evenza Platform</h1>
        <p class="text-muted text-xs mx-auto" style="max-width: 580px;">
            Evenza is the unified inter-college event management ecosystem connecting accredited universities, event organizers, and student competitors nationwide.
        </p>
    </div>
</section>

<!-- ABOUT GRID CANVAS -->
<section class="about-grid-canvas">
    <div class="container-xl">
        
        <!-- Mission Story & Stats Matrix -->
        <div class="row g-4 align-items-stretch mb-5">
            
            <!-- Vision Card -->
            <div class="col-12 col-lg-6">
                <div class="about-card-luxury">
                    <div>
                        <span class="badge bg-warning text-dark font-bold px-3.5 py-1.5 rounded-pill text-xs mb-3">Platform Mission</span>
                        <h2 class="fw-black text-dark mb-3 fs-3" style="letter-spacing: -0.8px;">Empowering Campus Competitions</h2>
                        <p class="text-secondary text-xs leading-relaxed mb-3">
                            Historically, students faced challenges discovering upcoming inter-college competitions across neighboring institutions, while fest organizers managed team registrations, ticket passes, and verifications manually.
                        </p>
                        <p class="text-secondary text-xs leading-relaxed mb-0">
                            Evenza streamlines event discovery, team formation, automated verification passes, and real-time organizer dashboards into one secure, unified platform.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Stats Matrix Grid -->
            <div class="col-12 col-lg-6">
                <div class="row g-3 h-100">
                    <div class="col-6">
                        <div class="stat-card-bespoke dark-pod">
                            <div class="display-6 font-black text-warning mb-1"><?= number_format($total_colleges) ?>+</div>
                            <div class="fw-bold text-white text-xs text-uppercase tracking-wider">Partner Institutions</div>
                            <small class="text-white-50" style="font-size: 10px;">Accredited Universities</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card-bespoke gold-pod">
                            <div class="display-6 font-black text-dark mb-1"><?= number_format($total_students) ?>+</div>
                            <div class="fw-bold text-dark text-xs text-uppercase tracking-wider">Student Competitors</div>
                            <small class="text-dark opacity-75" style="font-size: 10px;">Active Participants</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card-bespoke">
                            <div class="display-6 font-black text-dark mb-1"><?= number_format($total_events) ?>+</div>
                            <div class="fw-bold text-dark text-xs text-uppercase tracking-wider">Published Fests</div>
                            <small class="text-muted" style="font-size: 10px;">Hackathons & Sports Meets</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card-bespoke">
                            <div class="display-6 font-black text-dark mb-1">100%</div>
                            <div class="fw-bold text-dark text-xs text-uppercase tracking-wider">Digital Pass Entry</div>
                            <small class="text-muted" style="font-size: 10px;">Instant Verification Passes</small>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- THREE ECOSYSTEM PILLARS -->
        <div class="text-center mb-4 pt-3">
            <span class="badge bg-dark text-white font-bold px-3.5 py-1.5 rounded-pill text-xs mb-2">Architectural Pillars</span>
            <h2 class="fw-black text-dark display-6 mb-1">Designed For Every Stakeholder</h2>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-12 col-md-4">
                <div class="pillar-card-bespoke">
                    <div class="pillar-icon-box">
                        <i class="bi bi-person-workspace"></i>
                    </div>
                    <h5 class="fw-black text-dark mb-2 fs-5">For Student Competitors</h5>
                    <p class="text-secondary text-xs leading-relaxed mb-0">
                        Discover verified hackathons, sports leagues, and cultural fests. Form multi-member teams with instant invitation codes and track achievements in your profile.
                    </p>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="pillar-card-bespoke">
                    <div class="pillar-icon-box" style="background: #60a5fa; color: #ffffff;">
                        <i class="bi bi-building"></i>
                    </div>
                    <h5 class="fw-black text-dark mb-2 fs-5">For Event Organizers</h5>
                    <p class="text-secondary text-xs leading-relaxed mb-0">
                        Publish fests in minutes, manage category pricing, track live registration numbers, and scan digital student entry passes at venue gates.
                    </p>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="pillar-card-bespoke">
                    <div class="pillar-icon-box" style="background: #34d399; color: #14171a;">
                        <i class="bi bi-bank2"></i>
                    </div>
                    <h5 class="fw-black text-dark mb-2 fs-5">For Institutions</h5>
                    <p class="text-secondary text-xs leading-relaxed mb-0">
                        Showcase campus infrastructure, foster inter-college collaboration, and build institutional prestige through national competition hosting.
                    </p>
                </div>
            </div>
        </div>

        <!-- TRUST GUARANTEE STRIP -->
        <div class="trust-guarantee-strip">
            <div class="row align-items-center g-3 text-center text-md-start">
                <div class="col-12 col-md-3">
                    <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-2">
                        <i class="bi bi-shield-check text-warning fs-4"></i>
                        <span class="fw-black text-dark text-xs">100% Verified Organizers</span>
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-2">
                        <i class="bi bi-qr-code-scan text-primary fs-4"></i>
                        <span class="fw-black text-dark text-xs">Digital Verification Passes</span>
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-2">
                        <i class="bi bi-trophy text-success fs-4"></i>
                        <span class="fw-black text-dark text-xs">Transparent Competition Rules</span>
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-2">
                        <i class="bi bi-headset text-danger fs-4"></i>
                        <span class="fw-black text-dark text-xs">24/7 Desk Support</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<?php include 'Footer.php'; ?>
