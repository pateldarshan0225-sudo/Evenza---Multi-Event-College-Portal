<?php
/**
 * Frontend/index.php
 * Evenza Home Page - Designed to match Course Desk / Be.run aesthetic
 */
$page_title = "Home";
include 'connection.php';
include 'Header.php';

// Fetch Live Metrics
$total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$total_colleges = $pdo->query("SELECT COUNT(*) FROM colleges")->fetchColumn();
$total_events   = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();

// Fetch Categories for Grid
$categories = $pdo->query("
    SELECT c.*, (SELECT COUNT(*) FROM events e WHERE e.category_id = c.category_id) AS event_count
    FROM categories c
    ORDER BY event_count DESC
    LIMIT 5
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
    /* HERO SECTION STYLING */
    .hero-section {
        background-color: var(--bg-cream);
        padding: 40px 0 60px 0;
        position: relative;
        overflow: hidden;
    }

    .hero-headline {
        font-size: 52px;
        font-weight: 800;
        letter-spacing: -1.5px;
        color: #14171a;
        line-height: 1.15;
    }

    .hero-subtext {
        font-size: 16px;
        color: #6b7280;
        line-height: 1.6;
        margin: 20px 0 32px 0;
        max-width: 480px;
    }

    .hero-blob-pod {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .hero-blob-bg {
        width: 100%;
        max-width: 440px;
        height: 400px;
        background: #ffd13b;
        border-radius: 40% 60% 70% 30% / 40% 50% 60% 70%;
        position: absolute;
        z-index: 1;
        transform: rotate(-5deg);
    }

    .hero-student-img {
        position: relative;
        z-index: 2;
        max-height: 420px;
        object-fit: contain;
    }

    .hero-badge-circle {
        position: absolute;
        top: 20px;
        right: 40px;
        z-index: 3;
        width: 70px;
        height: 70px;
        background: #ffd13b;
        border: 2px dashed #14171a;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        text-align: center;
        transform: rotate(12deg);
    }

    /* PARTNER UNIVERSITIES STRIP */
    .partner-strip {
        padding: 30px 0;
        border-bottom: 1px solid rgba(0,0,0,0.06);
    }

    .partner-logo-text {
        font-size: 20px;
        font-weight: 800;
        color: #374151;
        letter-spacing: -0.5px;
    }

    /* OBSIDIAN FEATURE HIGHLIGHTS BAR */
    .feature-bar-dark {
        background-color: #1e293b;
        color: #ffffff;
        padding: 36px 0;
    }

    .feature-box-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: #ffd13b;
        flex-shrink: 0;
    }

    /* CATEGORY CARDS SECTION */
    .category-section {
        background-color: #fef3e2;
        padding: 64px 0;
    }

    .cat-card-item {
        background: #ffffff;
        border-radius: 20px;
        padding: 24px;
        text-align: center;
        border: 2px solid #14171a;
        box-shadow: 4px 4px 0px #14171a;
        transition: all 0.2s ease;
        text-decoration: none;
        color: #14171a;
        display: block;
    }

    .cat-card-item:hover {
        transform: translateY(-4px);
        box-shadow: 6px 6px 0px #14171a;
        color: #14171a;
    }

    .cat-card-icon {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: #14171a;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 14px auto;
        font-size: 20px;
    }

    /* SOFT MINT FEATURED SECTION */
    .featured-section {
        background-color: #e6f4f1;
        padding: 72px 0;
    }

    .event-card-mint {
        background: #ffffff;
        border-radius: 24px;
        padding: 28px;
        border: 2px solid #14171a;
        box-shadow: 4px 4px 0px #14171a;
        transition: all 0.25s ease;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
    }

    .event-card-mint:hover {
        transform: translateY(-4px);
        box-shadow: 8px 8px 0px #14171a;
    }

    .star-rating { color: #f59e0b; font-size: 13px; }
</style>

<!-- HERO SECTION -->
<section class="hero-section">
    <div class="container-xl">
        <div class="row align-items-center g-4">
            
            <div class="col-12 col-lg-6">
                <h1 class="hero-headline">
                    Discover & Join <br>College Events <span class="text-warning">Without Limits</span>
                </h1>
                <p class="hero-subtext">
                    Evenza connects partner universities, colleges, organizers, and students nationwide. Register for hackathons, cultural fests, sports tournaments, and workshops in one place.
                </p>

                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <a href="events.php" class="btn-capsule-dark py-3 px-4 fs-6">
                        Explore Events <i class="bi bi-plus-lg ms-1"></i>
                    </a>
                </div>

                <!-- Stats Bar -->
                <div class="row g-3 mt-4 pt-3 border-top border-secondary border-opacity-10">
                    <div class="col-4">
                        <div class="fs-4 font-black text-dark"><?= number_format($total_students > 0 ? $total_students : 3200) ?>+</div>
                        <small class="text-muted text-xs font-semibold text-uppercase">Students Joined</small>
                    </div>
                    <div class="col-4">
                        <div class="fs-4 font-black text-dark">100%</div>
                        <small class="text-muted text-xs font-semibold text-uppercase">Verified Fests</small>
                    </div>
                    <div class="col-4">
                        <div class="fs-4 font-black text-dark"><?= number_format($total_colleges > 0 ? $total_colleges : 160) ?>+</div>
                        <small class="text-muted text-xs font-semibold text-uppercase">Partner Colleges</small>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="hero-blob-pod">
                    <div class="hero-blob-bg"></div>
                    <img src="https://illustrations.pouch.cool/illustration/student-girl.png" alt="Student" class="hero-student-img" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=600&auto=format&fit=crop';">
                    <div class="hero-badge-circle">
                        <span>★ Live<br>Fests</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- PARTNER UNIVERSITIES STRIP -->
<section class="partner-strip">
    <div class="container-xl">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-4">
            <span class="text-muted text-xs font-bold text-uppercase tracking-wider">Accredited by 160+ Partner Universities</span>
            <div class="d-flex align-items-center gap-5 flex-wrap">
                <span class="partner-logo-text"><i class="bi bi-meta me-1"></i> Meta</span>
                <span class="partner-logo-text"><i class="bi bi-bank me-1"></i> Stanford</span>
                <span class="partner-logo-text"><i class="bi bi-building-columns me-1"></i> ILLINOIS</span>
                <span class="partner-logo-text"><i class="bi bi-mortarboard me-1"></i> Duke</span>
                <span class="partner-logo-text"><i class="bi bi-award me-1"></i> Penn</span>
            </div>
        </div>
    </div>
</section>

<!-- OBSIDIAN FEATURE HIGHLIGHTS BAR -->
<section class="feature-bar-dark">
    <div class="container-xl">
        <div class="row g-4">
            <div class="col-12 col-md-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="feature-box-icon"><i class="bi bi-bank2"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1 text-white fs-6">Best University Network</h6>
                        <p class="mb-0 text-secondary text-xs">Direct event hosting by verified top engineering & management colleges.</p>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="feature-box-icon"><i class="bi bi-shield-check"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1 text-white fs-6">Transparent Guidelines</h6>
                        <p class="mb-0 text-secondary text-xs">Clear team rules, verified registration fees, and instant receipt generation.</p>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="feature-box-icon"><i class="bi bi-trophy"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1 text-white fs-6">Certified Participation</h6>
                        <p class="mb-0 text-secondary text-xs">Earn official certificates and win cash prizes in college fests.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CATEGORY CARDS SECTION -->
<section class="category-section">
    <div class="container-xl">
        <div class="text-center mb-5">
            <h2 class="fw-black text-dark display-6 mb-2">More Events From Categories</h2>
            <p class="text-muted text-xs">Browse inter-college events by your field of interest</p>
        </div>

        <div class="row g-3 justify-content-center">
            <?php 
            $catIcons = ['bi bi-code-slash', 'bi bi-briefcase', 'bi bi-palette', 'bi bi-camera', 'bi bi-controller'];
            foreach ($categories as $idx => $cat): 
                $icon = $catIcons[$idx % count($catIcons)];
            ?>
                <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                    <a href="events.php?category=<?= urlencode($cat['name']) ?>" class="cat-card-item">
                        <div class="cat-card-icon"><i class="<?= $icon ?>"></i></div>
                        <h6 class="fw-bold mb-1 text-dark text-xs"><?= htmlspecialchars((string)$cat['name']) ?></h6>
                        <small class="text-muted" style="font-size: 11px;"><?= $cat['event_count'] ?> Events Available</small>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- SOFT MINT FEATURED EVENTS SECTION -->
<section class="featured-section">
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-end mb-5 flex-wrap gap-3">
            <div>
                <h2 class="fw-black text-dark display-6 mb-2">Featured College Events</h2>
                <p class="text-muted text-xs mb-0">Handpicked upcoming competitions, hackathons, and cultural fests</p>
            </div>
            <a href="events.php" class="btn-capsule-outline py-2 px-4 text-xs">View All Events <i class="bi bi-arrow-right"></i></a>
        </div>

        <div class="row g-4">
            <?php if (empty($events)): ?>
                <div class="col-12 text-center py-5 text-muted">
                    <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                    No events published yet. Check back soon!
                </div>
            <?php else: ?>
                <?php foreach ($events as $e): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="event-card-mint">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span class="badge bg-dark text-white rounded-pill px-3 py-1 font-bold text-xs">
                                        <?= htmlspecialchars((string)($e['college_name'] ?? 'Partner College')) ?>
                                    </span>
                                    <div class="star-rating"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></div>
                                </div>

                                <h5 class="fw-bold text-dark mb-2" style="font-size: 18px; line-height: 1.3;">
                                    <?= htmlspecialchars((string)$e['title']) ?>
                                </h5>

                                <p class="text-muted text-xs mb-3" style="line-height: 1.5; height: 38px; overflow: hidden;">
                                    <?= htmlspecialchars(mb_strimwidth((string)$e['description'], 0, 100, '...')) ?>
                                </p>

                                <div class="text-xs text-secondary mb-3">
                                    <i class="bi bi-geo-alt me-1 text-danger"></i> <?= htmlspecialchars((string)$e['venue']) ?> | 
                                    <i class="bi bi-calendar3 me-1 text-primary"></i> <?= date('d M Y', strtotime($e['event_date'])) ?>
                                </div>
                            </div>

                            <div class="pt-3 border-top border-dark border-opacity-10 d-flex align-items-center justify-content-between">
                                <a href="event_detail.php?id=<?= $e['event_id'] ?>" class="btn-capsule-dark text-xs py-2 px-4">
                                    Register Now
                                </a>
                                <span class="fw-bold text-dark text-xs">
                                    <?= (float)($e['registration_fee']) > 0 ? '₹' . number_format((float)$e['registration_fee'], 2) : 'Free Event' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include 'Footer.php'; ?>
