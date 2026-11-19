<?php
require_once __DIR__ . '/../includes/event_lifecycle.php';
$active_event_sql = evenza_active_event_sql();
/**
 * Frontend/index.php
 * Ultra-Premium, Student-Targeted Home Page for Evenza
 */
$homepage_design = true;
$page_title = "Discover College Events & Competitions";
include 'connection.php';
$conn = get_evenza_connection();
include 'Header.php';

// Fetch Live Metrics
$res_s = mysqli_query($conn, "SELECT COUNT(*) AS total FROM students");
$row_s = $res_s ? mysqli_fetch_assoc($res_s) : null;
$total_students = (int)($row_s['total'] ?? 3200);

$res_c = mysqli_query($conn, "SELECT COUNT(*) AS total FROM colleges");
$row_c = $res_c ? mysqli_fetch_assoc($res_c) : null;
$total_colleges = (int)($row_c['total'] ?? 160);

$res_e = mysqli_query($conn, "SELECT COUNT(*) AS total FROM events e WHERE {$active_event_sql}");
$row_e = $res_e ? mysqli_fetch_assoc($res_e) : null;
$total_events   = (int)($row_e['total'] ?? 0);

// Fetch Categories for Grid
$res_cat = mysqli_query($conn, "
    SELECT c.*, (SELECT COUNT(*) FROM events e WHERE e.category_id = c.category_id AND {$active_event_sql}) AS event_count
    FROM categories c
    ORDER BY event_count DESC
    LIMIT 6
");
$categories = [];
if ($res_cat) {
    while ($r = mysqli_fetch_assoc($res_cat)) {
        $categories[] = $r;
    }
}

// Fetch Featured Events
$res_ev = mysqli_query($conn, "
    SELECT e.*, c.name AS category_name, col.name AS college_name, col.logo AS college_logo
    FROM events e
    LEFT JOIN categories c ON e.category_id = c.category_id
    LEFT JOIN colleges col ON e.college_id = col.college_id
    WHERE {$active_event_sql}
    ORDER BY e.event_id DESC
    LIMIT 6
");
$events = [];
if ($res_ev) {
    while ($r = mysqli_fetch_assoc($res_ev)) {
        $events[] = $r;
    }
}
?>



<!-- STUDENT HERO SECTION -->
<main id="main-content">
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
                    Your next big moment.<br><span class="highlight-marker">Starts here.</span>
                </h1>

                <p class="hero-subtitle">
                    Discover campus festivals, take on new challenges, and find your people. Explore competitions, workshops, and experiences across colleges.
                </p>

                <!-- Embedded Hero Search Box -->
                <form action="events.php" method="GET" class="hero-search-box mb-4">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-search text-dark me-3 fs-6"></i>
                        <input type="text" name="search" autocomplete="off" aria-label="Search events" placeholder="Find your next event?" aria-label="Search events" required>
                        <button type="submit" class="btn-capsule-dark py-2.5 px-4 text-xs font-bold text-nowrap ms-2">
                            Explore Events
                        </button>
                    </div>
                </form>

                <!-- Social Proof Avatars -->
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-group-stack">
                        <img src="../assets/images/user/avatar-1.jpg" class="avatar-stack-item" alt="Student Competitor">
                        <img src="../assets/images/user/avatar-2.jpg" class="avatar-stack-item" alt="Student Competitor">
                        <img src="../assets/images/user/avatar-3.jpg" class="avatar-stack-item" alt="Student Competitor">
                        <img src="../assets/images/user/avatar-4.jpg" class="avatar-stack-item" alt="Student Competitor">
                    </div>
                    <div>
                        <div class="fw-black text-dark text-xs mb-0">Joined by <span data-count="<?= $total_students ?>"><?= number_format($total_students) ?></span>+ Student Competitors</div>
                        <small class="text-muted text-xs">From <?= number_format($total_colleges) ?>+ Accredited Campus Networks</small>
                    </div>
                </div>

            </div>

            <!-- Right Showcase Pod -->
            <div class="col-12 col-lg-6">
                <div class="hero-showcase-pod">
                    
                    <!-- Top Floating Pill -->
                    <div class="hero-badge-top-right">
                        <i class="bi bi-building-check"></i> <?= number_format($total_colleges) ?>+ Partner Institutions
                    </div>

                    <!-- Framed Executive Photo Pod -->
                    <div class="hero-image-frame">
                        <img src="../assets/images/brand/campus-community.jpg" width="1000" height="667" fetchpriority="high" decoding="async" alt="College Student Competitors">
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

                                <div class="event-meta p-3 bg-light rounded-4 mb-4 d-flex align-items-center justify-content-between text-xs text-secondary">
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
                <div class="journey-card p-4 bg-light rounded-5 h-100 border">
                    <div class="step-number-badge mx-auto">1</div>
                    <h5 class="fw-bold text-dark mb-2 fs-5">Find Fests & Hackathons</h5>
                    <p class="text-muted text-xs mb-0">Browse events by domain, dates, prize money, or venue location across partner institutions.</p>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="journey-card p-4 bg-light rounded-5 h-100 border">
                    <div class="step-number-badge mx-auto">2</div>
                    <h5 class="fw-bold text-dark mb-2 fs-5">Build Team or Go Solo</h5>
                    <p class="text-muted text-xs mb-0">Register individually or generate a team code to invite classmates to join your squad.</p>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="journey-card p-4 bg-light rounded-5 h-100 border">
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
                    <p class="text-secondary text-xs mb-0" style="max-width: 500px;">Join <?= number_format($total_students) ?>+ students competing in hackathons, cultural meets, and sports tournaments across India.</p>
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

</main>
<?php include 'Footer.php'; ?>
