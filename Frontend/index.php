<?php
/**
 * Frontend/index.php
 * Ultra-Premium, Student-Targeted Home Page for Evenza
 */
$page_title = "Discover College Events & Competitions";
include 'connection.php';
include 'Header.php';

// Fetch Live Metrics
$total_students = (int)($pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() ?: 3200);
$total_colleges = (int)($pdo->query("SELECT COUNT(*) FROM colleges")->fetchColumn() ?: 160);
$total_events   = (int)($pdo->query("SELECT COUNT(*) FROM events")->fetchColumn() ?: 85);

// Fetch Categories for Grid
$categories = $pdo->query("
    SELECT c.*, (SELECT COUNT(*) FROM events e WHERE e.category_id = c.category_id AND e.status = 'published') AS event_count
    FROM categories c
    ORDER BY event_count DESC
    LIMIT 6
")->fetchAll();

// Fetch Featured Events
$events = $pdo->query("
    SELECT e.*, c.name AS category_name, col.name AS college_name, col.logo AS college_logo
    FROM events e
    LEFT JOIN categories c ON e.category_id = c.category_id
    LEFT JOIN colleges col ON e.college_id = col.college_id
    WHERE e.status = 'published'
    ORDER BY e.event_id DESC
    LIMIT 6
")->fetchAll();
?>

<style>
    :root {
        --bg-cream: #fffdf7;
        --bg-card: #fef6e2;
        --bg-dark: #14171a;
        --color-yellow: #ffd13b;
        --color-mint: #e6f4f1;
        --color-indigo: #4f46e5;
    }

    /* HERO SECTION STYLING */
    .student-hero {
        background-color: var(--bg-cream);
        padding: 48px 0 88px 0;
        position: relative;
        overflow: hidden;
    }

    .live-pill-badge {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
        font-size: 12px;
        font-weight: 700;
        padding: 6px 16px;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 22px;
    }

    .live-dot-pulse {
        width: 8px;
        height: 8px;
        background-color: #ef4444;
        border-radius: 50%;
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
        animation: pulse-red 1.5s infinite;
    }

    @keyframes pulse-red {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
    }

    .hero-main-title {
        font-size: 58px;
        font-weight: 800;
        letter-spacing: -2px;
        color: #14171a;
        line-height: 1.1;
    }

    .highlight-marker {
        background: linear-gradient(180deg, rgba(255,255,255,0) 50%, #ffd13b 50%);
        padding: 0 8px;
        border-radius: 6px;
    }

    .hero-subtitle {
        font-size: 16px;
        color: #4b5563;
        line-height: 1.65;
        margin: 22px 0 30px 0;
        max-width: 520px;
        font-weight: 500;
    }

    /* Embedded Hero Search Widget */
    .hero-search-box {
        background: #ffffff;
        border: 2px solid #14171a;
        border-radius: 24px;
        padding: 10px 12px 10px 20px;
        box-shadow: 5px 5px 0px #14171a;
        max-width: 540px;
        transition: all 0.2s ease;
    }

    .hero-search-box:focus-within {
        box-shadow: 7px 7px 0px #14171a;
        transform: translateY(-2px);
    }

    .hero-search-box input {
        border: none;
        outline: none;
        font-size: 14px;
        font-weight: 500;
        width: 100%;
        background: transparent;
    }

    /* BESPOKE HERO SHOWCASE POD */
    .hero-showcase-pod {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 10px;
    }

    .hero-image-frame {
        width: 100%;
        max-width: 480px;
        height: 380px;
        border-radius: 36px;
        border: 3px solid #14171a;
        box-shadow: 10px 10px 0px #ffd13b;
        overflow: hidden;
        position: relative;
        background: #14171a;
    }

    .hero-image-frame img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.4s ease;
    }

    .hero-image-frame:hover img {
        transform: scale(1.04);
    }

    .hero-badge-top-right {
        position: absolute;
        top: -12px;
        right: 10px;
        background: #ffd13b;
        color: #14171a;
        border: 2px solid #14171a;
        border-radius: 9999px;
        padding: 8px 18px;
        font-size: 12px;
        font-weight: 800;
        box-shadow: 4px 4px 0px #14171a;
        z-index: 10;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .hero-floating-card {
        position: absolute;
        bottom: -20px;
        left: -10px;
        background: #ffffff;
        border: 2px solid #14171a;
        border-radius: 20px;
        padding: 14px 20px;
        box-shadow: 6px 6px 0px #14171a;
        display: flex;
        align-items: center;
        gap: 14px;
        z-index: 10;
        max-width: 280px;
    }

    .avatar-group-stack {
        display: flex;
        align-items: center;
    }

    .avatar-stack-item {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        border: 2px solid #ffffff;
        margin-left: -10px;
        object-fit: cover;
    }
    .avatar-stack-item:first-child { margin-left: 0; }

    /* PARTNER STRIP */
    .partner-strip {
        background-color: #ffffff;
        border-top: 1px solid #f3f4f6;
        border-bottom: 1px solid #f3f4f6;
        padding: 28px 0;
    }

    .partner-logo-item {
        font-size: 15px;
        font-weight: 800;
        color: #6b7280;
        letter-spacing: -0.5px;
    }

    /* VALUE CARDS SECTION */
    .student-value-section {
        padding: 88px 0;
        background-color: #ffffff;
    }

    .value-card-box {
        background: var(--bg-cream);
        border: 2px solid #14171a;
        border-radius: 24px;
        padding: 32px;
        height: 100%;
        box-shadow: 5px 5px 0px #14171a;
        transition: all 0.25s ease;
    }

    .value-card-box:hover {
        transform: translateY(-4px);
        box-shadow: 8px 8px 0px #ffd13b;
    }

    .value-icon-circle {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        background: #ffffff;
        border: 2px solid #14171a;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        margin-bottom: 20px;
        box-shadow: 3px 3px 0px #14171a;
    }

    /* BESPOKE CATEGORIES SHOWCASE */
    .category-section-luxury {
        background-color: #fdfbf7;
        padding: 88px 0;
        border-top: 1px solid #f1f5f9;
        border-bottom: 1px solid #f1f5f9;
    }

    .cat-luxury-card {
        background: #ffffff;
        border: 2px solid #14171a;
        border-radius: 24px;
        padding: 24px 20px;
        text-decoration: none;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        box-shadow: 4px 4px 0px #14171a;
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
    }

    .cat-luxury-card:hover {
        transform: translateY(-6px);
        box-shadow: 6px 6px 0px #ffd13b;
    }

    .cat-icon-wrapper {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        border: 2px solid #14171a;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        margin-bottom: 16px;
    }

    .theme-tech { background: #ffd13b; color: #14171a; }
    .theme-sports { background: #60a5fa; color: #ffffff; }
    .theme-mgmt { background: #f472b6; color: #ffffff; }
    .theme-gaming { background: #a78bfa; color: #ffffff; }
    .theme-art { background: #34d399; color: #14171a; }
    .theme-literary { background: #fb923c; color: #ffffff; }

    .cat-count-badge {
        font-size: 11px;
        font-weight: 700;
        color: #4b5563;
        background: #f3f4f6;
        padding: 3px 10px;
        border-radius: 9999px;
        display: inline-block;
    }

    .cat-arrow-link {
        font-size: 12px;
        font-weight: 800;
        color: #14171a;
        margin-top: 16px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    /* BESPOKE FEATURED EVENTS */
    .featured-section-luxury {
        padding: 88px 0;
        background-color: var(--color-mint);
    }

    .event-card-bespoke {
        background: #ffffff;
        border-radius: 26px;
        padding: 28px;
        border: 1.5px solid #dbece9;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
    }

    .event-card-bespoke:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 42px rgba(0, 0, 0, 0.09);
        border-color: #14171a;
    }

    .category-chip {
        background: #ffd13b;
        color: #14171a;
        font-size: 11px;
        font-weight: 800;
        padding: 4px 12px;
        border-radius: 9999px;
        border: 1.5px solid #14171a;
    }

    .college-host-row {
        font-size: 12px;
        color: #475569;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 14px;
        padding: 8px 14px;
        line-height: 1.4;
    }

    .event-title-clamp {
        font-size: 19px;
        font-weight: 800;
        color: #14171a;
        line-height: 1.35;
        letter-spacing: -0.3px;
        min-height: 52px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        margin-bottom: 8px;
    }

    .event-desc-clamp {
        font-size: 13px;
        line-height: 1.55;
        color: #64748b;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        min-height: 40px;
        margin-bottom: 16px;
    }

    /* HOW IT WORKS SECTION */
    .how-it-works-section {
        padding: 88px 0;
        background: #ffffff;
    }

    .step-number-badge {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: #ffd13b;
        color: #14171a;
        border: 2px solid #14171a;
        font-weight: 800;
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
        box-shadow: 3px 3px 0px #14171a;
    }

    /* CTA BANNER DARK */
    .cta-banner-dark {
        background-color: #14171a;
        border-radius: 32px;
        padding: 56px 48px;
        position: relative;
        overflow: hidden;
        border: 2px solid #14171a;
        box-shadow: 8px 8px 0px #ffd13b;
    }
</style>

<!-- STUDENT HERO SECTION -->
<section class="student-hero">
    <div class="container-xl">
        <div class="row align-items-center g-5">
            
            <!-- Left Hero Content -->
            <div class="col-12 col-lg-6">
                
                <div class="live-pill-badge">
                    <span class="live-dot-pulse"></span>
                    <span>Live Competitions & Fests Open for Registration</span>
                </div>

                <h1 class="hero-main-title">
                    Discover & Compete in Top <span class="highlight-marker">College Fests</span>
                </h1>

                <p class="hero-subtitle">
                    Evenza connects ambitious students with accredited technical hackathons, cultural festivals, sports tournaments, and business meets across India.
                </p>

                <!-- Embedded Hero Search Box -->
                <form action="events.php" method="GET" class="hero-search-box mb-4">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-search text-dark me-3 fs-6"></i>
                        <input type="text" name="search" autocomplete="off" placeholder="Search hackathons, fests, workshops, or partner institutions..." required>
                        <button type="submit" class="btn-capsule-dark py-2.5 px-4 text-xs font-bold text-nowrap ms-2">
                            Explore Events
                        </button>
                    </div>
                </form>

                <!-- Social Proof Avatars -->
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-group-stack">
                        <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&auto=format&fit=crop" class="avatar-stack-item" alt="Student Competitor">
                        <img src="https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?w=100&auto=format&fit=crop" class="avatar-stack-item" alt="Student Competitor">
                        <img src="https://images.unsplash.com/photo-1517841905240-472988babdf9?w=100&auto=format&fit=crop" class="avatar-stack-item" alt="Student Competitor">
                        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&auto=format&fit=crop" class="avatar-stack-item" alt="Student Competitor">
                    </div>
                    <div>
                        <div class="fw-black text-dark text-xs mb-0">Joined by <?= number_format($total_students) ?>+ Student Competitors</div>
                        <small class="text-muted text-xs">From <?= number_format($total_colleges) ?>+ Accredited Campus Networks</small>
                    </div>
                </div>

            </div>

            <!-- Right Showcase Pod -->
            <div class="col-12 col-lg-6">
                <div class="hero-showcase-pod">
                    
                    <!-- Top Floating Pill -->
                    <div class="hero-badge-top-right">
                        <i class="bi bi-building-check"></i> 160+ Partner Institutions
                    </div>

                    <!-- Framed Executive Photo Pod -->
                    <div class="hero-image-frame">
                        <img src="https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=800&auto=format&fit=crop" alt="College Student Competitors">
                    </div>
                    
                    <!-- Floating Stat Badge -->
                    <div class="hero-floating-card">
                        <div class="bg-warning p-2.5 rounded-circle border border-dark d-flex align-items-center justify-content-center" style="width:44px; height:44px;">
                            <i class="bi bi-trophy-fill fs-5 text-dark"></i>
                        </div>
                        <div>
                            <div class="fw-black text-dark text-xs mb-0">₹50,00,000+ Prize Pool</div>
                            <small class="text-muted" style="font-size: 11px;">Across National College Fests 2026</small>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</section>

<!-- ACCREDITED PARTNER UNIVERSITIES STRIP -->
<section class="partner-strip">
    <div class="container-xl">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-4">
            <span class="text-muted text-xs font-bold text-uppercase tracking-wider">Trusted by Students Across Top Campuses</span>
            <div class="d-flex align-items-center gap-5 flex-wrap">
                <span class="partner-logo-item"><i class="bi bi-bank me-1 text-primary"></i> Stanford</span>
                <span class="partner-logo-item"><i class="bi bi-meta me-1 text-info"></i> Meta</span>
                <span class="partner-logo-item"><i class="bi bi-building-columns me-1 text-danger"></i> ILLINOIS</span>
                <span class="partner-logo-item"><i class="bi bi-mortarboard me-1 text-success"></i> Duke</span>
                <span class="partner-logo-item"><i class="bi bi-award me-1 text-warning"></i> Penn</span>
            </div>
        </div>
    </div>
</section>

<!-- WHY STUDENTS CHOOSE EVENZA -->
<section class="student-value-section">
    <div class="container-xl">
        <div class="text-center mb-5">
            <span class="badge bg-warning text-dark font-bold px-3 py-1 rounded-pill text-xs mb-2">Platform Capabilities</span>
            <h2 class="fw-black text-dark display-6 mb-2">Built Specifically For College Competitors</h2>
            <p class="text-muted text-xs mx-auto" style="max-width: 520px;">
                Everything you need to discover events, build teams, compete, and showcase achievements in your academic profile.
            </p>
        </div>

        <div class="row g-4">
            <div class="col-12 col-md-4">
                <div class="value-card-box">
                    <div class="value-icon-circle text-warning"><i class="bi bi-trophy-fill"></i></div>
                    <h5 class="fw-bold text-dark mb-2 fs-5">Verified Competitions</h5>
                    <p class="text-muted text-xs mb-0 leading-relaxed">
                        Access verified inter-college hackathons, cultural festivals, and sports meets with transparent prize structures and guidelines.
                    </p>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="value-card-box">
                    <div class="value-icon-circle text-primary"><i class="bi bi-people-fill"></i></div>
                    <h5 class="fw-bold text-dark mb-2 fs-5">Team & Solo Sign-ups</h5>
                    <p class="text-muted text-xs mb-0 leading-relaxed">
                        Register individually or form multi-member team squads using instant invitation codes for seamless registration.
                    </p>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="value-card-box">
                    <div class="value-icon-circle text-success"><i class="bi bi-patch-check-fill"></i></div>
                    <h5 class="fw-bold text-dark mb-2 fs-5">Digital Verification Passes</h5>
                    <p class="text-muted text-xs mb-0 leading-relaxed">
                        Receive instant digital verification passes in your student portal for smooth entry check-ins at host college venues.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- BROWSE BY CATEGORY -->
<section class="category-section-luxury">
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-end mb-5 flex-wrap gap-3">
            <div>
                <span class="badge bg-dark text-white font-bold px-3.5 py-1.5 rounded-pill text-xs mb-2">
                    <i class="bi bi-lightning-charge-fill text-warning me-1"></i> Popular Domains
                </span>
                <h2 class="fw-black text-dark display-6 mb-0">Browse By Category</h2>
            </div>
            <a href="categories.php" class="btn-capsule-outline text-xs py-2 px-4">
                All Categories <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            <?php 
            $catConfigs = [
                ['icon' => 'bi bi-code-slash', 'theme' => 'theme-tech'],
                ['icon' => 'bi bi-trophy', 'theme' => 'theme-sports'],
                ['icon' => 'bi bi-briefcase', 'theme' => 'theme-mgmt'],
                ['icon' => 'bi bi-controller', 'theme' => 'theme-gaming'],
                ['icon' => 'bi bi-palette', 'theme' => 'theme-art'],
                ['icon' => 'bi bi-book', 'theme' => 'theme-literary']
            ];

            foreach ($categories as $idx => $cat): 
                $cfg = $catConfigs[$idx % count($catConfigs)];
            ?>
                <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                    <a href="events.php?category=<?= urlencode($cat['name']) ?>" class="cat-luxury-card">
                        <div>
                            <div class="cat-icon-wrapper <?= $cfg['theme'] ?>">
                                <i class="<?= $cfg['icon'] ?>"></i>
                            </div>

                            <h6 class="fw-black text-dark mb-1 fs-6" style="letter-spacing: -0.3px;">
                                <?= htmlspecialchars((string)$cat['name']) ?>
                            </h6>
                            
                            <span class="cat-count-badge">
                                <?= $cat['event_count'] ?> Active Fest<?= $cat['event_count'] == 1 ? '' : 's' ?>
                            </span>
                        </div>

                        <div class="cat-arrow-link">
                            Explore <i class="bi bi-arrow-right"></i>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- FEATURED EVENTS SECTION -->
<section class="featured-section-luxury">
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-end mb-5 flex-wrap gap-3">
            <div>
                <span class="badge bg-warning text-dark font-bold px-3.5 py-1.5 rounded-pill text-xs mb-2">
                    🔥 Live Sign-ups
                </span>
                <h2 class="fw-black text-dark display-6 mb-1">Upcoming Competitions & Fests</h2>
                <p class="text-muted text-xs mb-0">Handpicked national hackathons, cultural meets, and workshops across India</p>
            </div>
            <a href="events.php" class="btn-capsule-dark py-2.5 px-4 text-xs">
                Explore All Events <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            <?php if (empty($events)): ?>
                <div class="col-12 text-center py-5 text-muted">
                    <i class="bi bi-calendar-x fs-1 d-block mb-2 text-muted opacity-50"></i>
                    No events are currently scheduled. Check back soon for upcoming competitions!
                </div>
            <?php else: ?>
                <?php foreach ($events as $e): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="event-card-bespoke">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                    <span class="category-chip">
                                        <?= htmlspecialchars((string)($e['category_name'] ?? 'Fest')) ?>
                                    </span>
                                    <span class="badge bg-white text-dark border border-secondary text-xs px-2.5 py-1 rounded-pill">
                                        <?= ucfirst($e['event_type']) ?>
                                    </span>
                                </div>

                                <h5 class="event-title-clamp">
                                    <?= htmlspecialchars((string)$e['title']) ?>
                                </h5>

                                <div class="college-host-row mb-3">
                                    <i class="bi bi-bank2 text-warning me-1.5"></i>
                                    Hosted by <strong class="text-dark"><?= htmlspecialchars((string)($e['college_name'] ?? 'Partner College')) ?></strong>
                                </div>

                                <p class="event-desc-clamp">
                                    <?= htmlspecialchars((string)$e['description']) ?>
                                </p>

                                <div class="p-3 bg-light rounded-4 mb-4 d-flex align-items-center justify-content-between text-xs text-secondary">
                                    <div><i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= htmlspecialchars((string)$e['venue']) ?></div>
                                    <div><i class="bi bi-calendar-event-fill text-primary me-1"></i> <?= date('d M Y', strtotime($e['event_date'])) ?></div>
                                </div>
                            </div>

                            <div class="pt-3 border-top border-dark border-opacity-10 d-flex align-items-center justify-content-between">
                                <a href="event_detail.php?id=<?= $e['event_id'] ?>" class="btn-capsule-dark text-xs py-2.5 px-4">
                                    Register Now <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                                <span class="fw-black text-dark text-xs fs-6">
                                    <?= (float)($e['registration_fee']) > 0 ? '₹' . number_format((float)$e['registration_fee'], 2) : 'Free Entry' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- HOW IT WORKS SECTION (3-STEP FLOW) -->
<section class="how-it-works-section">
    <div class="container-xl">
        <div class="text-center mb-5">
            <span class="badge bg-warning text-dark font-bold px-3 py-1 rounded-pill text-xs mb-2">Streamlined Process</span>
            <h2 class="fw-black text-dark display-6 mb-2">How Evenza Works For Students</h2>
            <p class="text-muted text-xs mx-auto" style="max-width: 500px;">Three easy steps from discovering fests to winning prizes.</p>
        </div>

        <div class="row g-4 text-center">
            <div class="col-12 col-md-4">
                <div class="p-4 bg-light rounded-5 h-100 border">
                    <div class="step-number-badge mx-auto">1</div>
                    <h5 class="fw-bold text-dark mb-2 fs-5">Find Fests & Hackathons</h5>
                    <p class="text-muted text-xs mb-0">Browse events by domain, dates, prize money, or venue location across partner institutions.</p>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="p-4 bg-light rounded-5 h-100 border">
                    <div class="step-number-badge mx-auto">2</div>
                    <h5 class="fw-bold text-dark mb-2 fs-5">Build Team or Go Solo</h5>
                    <p class="text-muted text-xs mb-0">Register individually or generate a team code to invite classmates to join your squad.</p>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="p-4 bg-light rounded-5 h-100 border">
                    <div class="step-number-badge mx-auto">3</div>
                    <h5 class="fw-bold text-dark mb-2 fs-5">Compete & Access Pass</h5>
                    <p class="text-muted text-xs mb-0">Present your digital pass at the venue, compete, win cash prizes, and earn certificates.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- HIGH IMPACT CTA BANNER -->
<section class="py-5 bg-white">
    <div class="container-xl">
        <div class="cta-banner-dark">
            <div class="row align-items-center g-4">
                <div class="col-12 col-lg-8">
                    <span class="badge bg-warning text-dark font-bold px-3 py-1 rounded-pill text-xs mb-3">Campus Leadership</span>
                    <h2 class="fw-black text-white display-5 mb-2">Create Your Free Student Account Today.</h2>
                    <p class="text-secondary text-xs mb-0" style="max-width: 500px;">Join 3,200+ students competing in hackathons, cultural meets, and sports tournaments across India.</p>
                </div>
                <div class="col-12 col-lg-4 text-lg-end">
                    <a href="register.php" class="btn btn-warning rounded-pill px-5 py-3 text-dark font-black fs-6 shadow">
                        Register Student Account <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'Footer.php'; ?>
