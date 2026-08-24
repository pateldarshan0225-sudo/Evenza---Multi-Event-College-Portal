<?php
/**
 * Frontend/index.php
 * Ultra-Premium, Student-Targeted Home Page for Evenza
 */
$page_title = "Discover College Events & Fests";
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
        padding: 40px 0 80px 0;
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
        margin-bottom: 20px;
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
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }

    .hero-main-title {
        font-size: 56px;
        font-weight: 800;
        letter-spacing: -1.8px;
        color: #14171a;
        line-height: 1.12;
    }

    .highlight-marker {
        background: linear-gradient(180deg, rgba(255,255,255,0) 50%, #ffd13b 50%);
        padding: 0 6px;
    }

    .hero-subtitle {
        font-size: 16px;
        color: #4b5563;
        line-height: 1.6;
        margin: 20px 0 28px 0;
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
    }

    .hero-search-box input {
        border: none;
        outline: none;
        font-size: 14px;
        font-weight: 500;
        width: 100%;
        background: transparent;
    }

    /* Hero Blob Image Container */
    .hero-image-pod {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .hero-yellow-blob {
        width: 100%;
        max-width: 440px;
        height: 420px;
        background: #ffd13b;
        border-radius: 38% 62% 63% 37% / 41% 44% 56% 59%;
        position: absolute;
        z-index: 1;
        transform: rotate(-6deg);
    }

    .hero-student-img {
        position: relative;
        z-index: 2;
        max-height: 440px;
        object-fit: contain;
    }

    .hero-floating-card {
        position: absolute;
        bottom: 20px;
        left: -10px;
        z-index: 3;
        background: #ffffff;
        border: 2px solid #14171a;
        border-radius: 20px;
        padding: 14px 20px;
        box-shadow: 4px 4px 0px #14171a;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .avatar-stack {
        display: flex;
        align-items: center;
    }
    .avatar-stack img {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: 2px solid #ffffff;
        margin-left: -10px;
    }
    .avatar-stack img:first-child { margin-left: 0; }

    /* PARTNER UNIVERSITIES STRIP */
    .partner-strip {
        background: #ffffff;
        padding: 28px 0;
        border-top: 1px solid rgba(0,0,0,0.06);
        border-bottom: 1px solid rgba(0,0,0,0.06);
    }

    .partner-logo-item {
        font-size: 20px;
        font-weight: 800;
        color: #14171a;
        letter-spacing: -0.6px;
        opacity: 0.85;
    }

    /* WHY STUDENTS CHOOSE EVENZA */
    .student-value-section {
        background-color: #fdfbf7;
        padding: 72px 0;
    }

    .value-card-box {
        background: #ffffff;
        border-radius: 24px;
        padding: 32px 28px;
        border: 1.5px solid #e5e7eb;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
        transition: all 0.25s ease;
        height: 100%;
    }

    .value-card-box:hover {
        transform: translateY(-5px);
        border-color: #14171a;
        box-shadow: 0 18px 40px rgba(0, 0, 0, 0.07);
    }

    .value-icon-circle {
        width: 54px;
        height: 54px;
        border-radius: 16px;
        background: #fef3e2;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        margin-bottom: 20px;
    }

    /* =========================================================
       LUXURY BESPOKE CATEGORIES GRID ("BROWSE BY CATEGORY")
       ========================================================= */
    .category-section-luxury {
        background-color: #fff9ed;
        padding: 84px 0;
        position: relative;
    }

    .cat-luxury-card {
        background: #ffffff;
        border-radius: 24px;
        padding: 28px 24px;
        border: 1.5px solid #f3efe6;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.03);
        text-decoration: none;
        color: #14171a;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
        overflow: hidden;
    }

    .cat-luxury-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
        border-color: #14171a;
        color: #14171a;
    }

    .cat-icon-wrapper {
        width: 58px;
        height: 58px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin-bottom: 20px;
        transition: all 0.3s ease;
    }

    .cat-luxury-card:hover .cat-icon-wrapper {
        transform: scale(1.08) rotate(-4deg);
    }

    /* Domain Color Themes */
    .theme-tech { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
    .theme-sports { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
    .theme-mgmt { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
    .theme-gaming { background: #faf5ff; color: #9333ea; border: 1px solid #e9d5ff; }
    .theme-art { background: #fff1f2; color: #e11d48; border: 1px solid #fecdd3; }
    .theme-literary { background: #f0fdf4; color: #0d9488; border: 1px solid #99f6e4; }

    .cat-count-badge {
        font-size: 11px;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 9999px;
        background: #f4f1ea;
        color: #6b7280;
        display: inline-block;
        margin-top: 6px;
    }

    .cat-arrow-link {
        font-size: 12px;
        font-weight: 800;
        color: #14171a;
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 16px;
        transition: gap 0.2s ease;
    }

    .cat-luxury-card:hover .cat-arrow-link {
        gap: 10px;
        color: #2563eb;
    }

    /* =========================================================
       BESPOKE FEATURED EVENTS SECTION ("UPCOMING COMPETITIONS")
       ========================================================= */
    .featured-section-luxury {
        background-color: #e8f5f2;
        padding: 88px 0;
    }

    .event-card-bespoke {
        background: #ffffff;
        border-radius: 24px;
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

    .college-chip {
        background: #14171a;
        color: #ffffff;
        font-size: 11px;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .category-chip {
        background: #ffd13b;
        color: #14171a;
        font-size: 11px;
        font-weight: 800;
        padding: 4px 12px;
        border-radius: 9999px;
    }

    /* HOW IT WORKS SECTION */
    .how-it-works-section {
        background-color: #ffffff;
        padding: 80px 0;
    }

    .step-number-badge {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: #ffd13b;
        border: 2px solid #14171a;
        font-weight: 800;
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
    }

    /* HIGH IMPACT CTA BANNER */
    .cta-banner-dark {
        background: linear-gradient(135deg, #14171a 0%, #232930 100%);
        border-radius: 32px;
        padding: 56px 48px;
        color: #ffffff;
        border: 3px solid #14171a;
        box-shadow: 8px 8px 0px #ffd13b;
        position: relative;
        overflow: hidden;
    }
</style>

<!-- STUDENT HERO SECTION -->
<section class="student-hero">
    <div class="container-xl">
        <div class="row align-items-center g-5">
            
            <div class="col-12 col-lg-6">
                <!-- Live Ticker Pill -->
                <div class="live-pill-badge">
                    <span class="live-dot-pulse"></span>
                    <span>Registrations Open for 2026 Inter-College Fests</span>
                </div>

                <h1 class="hero-main-title">
                    Where Campus <br>
                    <span class="highlight-marker">Champions</span> Are Born.
                </h1>

                <p class="hero-subtitle">
                    Compete in national hackathons, cultural mega-fests, sports tournaments, and B-plan challenges across 160+ partner colleges. Win cash prizes & earn verified certificates.
                </p>

                <!-- Embedded Search Form -->
                <form action="events.php" method="GET" class="hero-search-box d-flex align-items-center gap-2 mb-4">
                    <i class="bi bi-search fs-5 text-muted ms-1"></i>
                    <input type="text" name="search" placeholder="Search hackathons, fests, robotics...">
                    <button type="submit" class="btn-capsule-dark py-2.5 px-4 text-xs font-bold flex-shrink-0">
                        Find Events <i class="bi bi-arrow-right"></i>
                    </button>
                </form>

                <!-- Social Proof Avatars -->
                <div class="d-flex align-items-center gap-3 pt-2">
                    <div class="avatar-stack">
                        <img src="https://ui-avatars.com/api/?name=Rahul+Verma&background=ffd13b&color=14171a">
                        <img src="https://ui-avatars.com/api/?name=Priya+Sharma&background=4f46e5&color=ffffff">
                        <img src="https://ui-avatars.com/api/?name=Amit+Patel&background=059669&color=ffffff">
                        <img src="https://ui-avatars.com/api/?name=Neha+Singh&background=ec4899&color=ffffff">
                    </div>
                    <div class="text-xs font-semibold text-dark">
                        <strong><?= number_format($total_students) ?>+ Students</strong> already competing this month
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="hero-image-pod">
                    <div class="hero-yellow-blob"></div>
                    <img src="https://illustrations.pouch.cool/illustration/student-girl.png" alt="Student Competitor" class="hero-student-img" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=600&auto=format&fit=crop';">
                    
                    <!-- Floating Stat Badge -->
                    <div class="hero-floating-card">
                        <div class="bg-warning p-2 rounded-circle border border-dark"><i class="bi bi-trophy-fill fs-5 text-dark"></i></div>
                        <div>
                            <div class="fw-black text-dark text-xs mb-0">₹50,00,000+ Prize Pool</div>
                            <small class="text-muted" style="font-size: 10px;">Across Hosted Fests 2026</small>
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
            <span class="badge bg-warning text-dark font-bold px-3 py-1 rounded-pill text-xs mb-2">Student Superpowers</span>
            <h2 class="fw-black text-dark display-6 mb-2">Built Specifically For College Competitors</h2>
            <p class="text-muted text-xs mx-auto" style="max-width: 520px;">
                Everything you need to discover events, build teams, compete, and showcase achievements on your resume.
            </p>
        </div>

        <div class="row g-4">
            <div class="col-12 col-md-4">
                <div class="value-card-box">
                    <div class="value-icon-circle text-warning"><i class="bi bi-trophy-fill"></i></div>
                    <h5 class="fw-bold text-dark mb-2 fs-5">Cash & Trophy Pursuits</h5>
                    <p class="text-muted text-xs mb-0 leading-relaxed">
                        Win cash rewards, gadgets, and trophies. Filter events by registration fee, prize pool, and competition format.
                    </p>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="value-card-box">
                    <div class="value-icon-circle text-primary"><i class="bi bi-people-fill"></i></div>
                    <h5 class="fw-bold text-dark mb-2 fs-5">Seamless Team Builder</h5>
                    <p class="text-muted text-xs mb-0 leading-relaxed">
                        Form your squad with team codes. Invite classmates or cross-college friends with instant multi-member registration.
                    </p>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="value-card-box">
                    <div class="value-icon-circle text-success"><i class="bi bi-patch-check-fill"></i></div>
                    <h5 class="fw-bold text-dark mb-2 fs-5">Verified Event Passes</h5>
                    <p class="text-muted text-xs mb-0 leading-relaxed">
                        Get digital participation passes instantly in your student portal. Show your QR at entry for seamless check-in.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- =========================================================
     LUXURY BESPOKE CATEGORIES GRID ("BROWSE BY CATEGORY")
     ========================================================= -->
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

<!-- =========================================================
     BESPOKE FEATURED EVENTS SECTION ("UPCOMING COMPETITIONS")
     ========================================================= -->
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
                    No events published yet. Check back soon!
                </div>
            <?php else: ?>
                <?php foreach ($events as $e): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="event-card-bespoke">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                    <span class="college-chip">
                                        <i class="bi bi-check-circle-fill text-warning" style="font-size: 10px;"></i>
                                        <?= htmlspecialchars((string)($e['college_name'] ?? 'Partner College')) ?>
                                    </span>
                                    <span class="category-chip">
                                        <?= htmlspecialchars((string)($e['category_name'] ?? 'Fest')) ?>
                                    </span>
                                </div>

                                <h5 class="fw-black text-dark mb-2" style="font-size: 19px; line-height: 1.35; letter-spacing: -0.3px;">
                                    <?= htmlspecialchars((string)$e['title']) ?>
                                </h5>

                                <p class="text-muted text-xs mb-4" style="line-height: 1.6; height: 38px; overflow: hidden;">
                                    <?= htmlspecialchars(mb_strimwidth((string)$e['description'], 0, 95, '...')) ?>
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
            <span class="badge bg-warning text-dark font-bold px-3 py-1 rounded-pill text-xs mb-2">Simple Process</span>
            <h2 class="fw-black text-dark display-6 mb-2">How Evenza Works For Students</h2>
            <p class="text-muted text-xs mx-auto" style="max-width: 500px;">Three easy steps from discovering fests to winning prizes.</p>
        </div>

        <div class="row g-4 text-center">
            <div class="col-12 col-md-4">
                <div class="p-4 bg-light rounded-5 h-100 border">
                    <div class="step-number-badge mx-auto">1</div>
                    <h5 class="fw-bold text-dark mb-2 fs-5">Find Fests & Hackathons</h5>
                    <p class="text-muted text-xs mb-0">Browse events by domain, dates, prize money, or location across partner colleges.</p>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="p-4 bg-light rounded-5 h-100 border">
                    <div class="step-number-badge mx-auto">2</div>
                    <h5 class="fw-bold text-dark mb-2 fs-5">Build Team or Go Solo</h5>
                    <p class="text-muted text-xs mb-0">Register yourself or generate a team code to invite classmates to join your squad.</p>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="p-4 bg-light rounded-5 h-100 border">
                    <div class="step-number-badge mx-auto">3</div>
                    <h5 class="fw-bold text-dark mb-2 fs-5">Compete & Get Pass</h5>
                    <p class="text-muted text-xs mb-0">Show your digital pass at venue, compete, win cash prizes, and get certificates.</p>
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
                    <span class="badge bg-warning text-dark font-bold px-3 py-1 rounded-pill text-xs mb-3">Ready to Lead Your Campus?</span>
                    <h2 class="fw-black text-white display-5 mb-2">Create Your Free Student Account Today.</h2>
                    <p class="text-secondary text-xs mb-0" style="max-width: 500px;">Join 3,200+ students competing in hackathons and cultural meets across India.</p>
                </div>
                <div class="col-12 col-lg-4 text-lg-end">
                    <a href="register.php" class="btn btn-warning rounded-pill px-5 py-3 text-dark font-black fs-6 shadow">
                        Get Started Now <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'Footer.php'; ?>
