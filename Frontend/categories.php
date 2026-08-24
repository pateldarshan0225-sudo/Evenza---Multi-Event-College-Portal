<?php
/**
 * Frontend/categories.php
 * Ultra-Premium Domain Categories Showcase for Evenza
 */
$page_title = "Event Categories";
include 'connection.php';

// Fetch Categories with Active Event Counts
$categories = $pdo->query("
    SELECT c.*, 
    (SELECT COUNT(*) FROM events e WHERE e.category_id = c.category_id AND e.status = 'published') AS event_count
    FROM categories c
    ORDER BY event_count DESC, c.name ASC
")->fetchAll();

// Domain-Specific Icon & Accent Color Mapping
$domain_styles = [
    'Technical Events'      => ['icon' => 'bi-code-slash',          'bg' => '#ffd13b', 'color' => '#14171a', 'desc' => 'Coding Sprints, Hackathons, AI Challenges & Web Dev Battles.'],
    'Management Events'     => ['icon' => 'bi-briefcase-fill',      'bg' => '#60a5fa', 'color' => '#ffffff', 'desc' => 'Shark Tank Pitches, Case Studies, Stock Trading & B-Plan Meets.'],
    'Cultural Events'       => ['icon' => 'bi-music-note-beamed',   'bg' => '#f472b6', 'color' => '#ffffff', 'desc' => 'Battle of the Bands, Dance Fests, Fashion Walks & Drama.'],
    'E-Sports & Gaming'     => ['icon' => 'bi-controller',          'bg' => '#a78bfa', 'color' => '#ffffff', 'desc' => 'Valorant, BGMI, EA FC, FIFA & LAN Gaming Tournaments.'],
    'Sports Events'         => ['icon' => 'bi-trophy-fill',         'bg' => '#34d399', 'color' => '#14171a', 'desc' => 'Cricket Leagues, Football Cups, Badminton & Athletics Meets.'],
    'Literary Events'       => ['icon' => 'bi-book-fill',           'bg' => '#fb923c', 'color' => '#ffffff', 'desc' => 'Parliamentary Debates, Poetry Slams, Quiz & Creative Writing.'],
    'Art & Design'          => ['icon' => 'bi-palette-fill',        'bg' => '#2dd4bf', 'color' => '#14171a', 'desc' => 'UI/UX Designathons, Canvas Painting, Photography & Sketching.'],
    'Workshops & Seminars'  => ['icon' => 'bi-easel-fill',          'bg' => '#818cf8', 'color' => '#ffffff', 'desc' => 'Hands-on Tech Masterclasses, Leadership Talks & Expert Panels.'],
    'Social & Fun Events'   => ['icon' => 'bi-balloon-fill',        'bg' => '#facc15', 'color' => '#14171a', 'desc' => 'Treasure Hunts, Fun Fairs, Stand-up Comedy & Flash Mobs.']
];

include 'Header.php';
?>

<style>
    :root {
        --bg-cream-canvas: #fdfbf7;
        --bg-dark-accent: #14171a;
        --color-gold-accent: #ffd13b;
    }

    .categories-hero-section {
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

    .categories-grid-canvas {
        background-color: var(--bg-cream-canvas);
        padding: 64px 0 88px 0;
        border-top: 1px solid #f1f5f9;
        min-height: 500px;
    }

    .category-card-bespoke {
        background: #ffffff;
        border-radius: 28px;
        padding: 32px;
        border: 1.5px solid #eae6df;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        position: relative;
    }

    .category-card-bespoke:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 42px rgba(0, 0, 0, 0.08);
        border-color: #14171a;
    }

    .domain-icon-pod {
        width: 60px;
        height: 60px;
        border-radius: 20px;
        border: 2px solid #14171a;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        flex-shrink: 0;
        box-shadow: 4px 4px 0px #14171a;
    }

    .events-count-badge {
        background: #f8fafc;
        color: #14171a;
        border: 1px solid #e2e8f0;
        font-size: 11px;
        font-weight: 800;
        padding: 5px 14px;
        border-radius: 9999px;
    }

    .events-count-badge.active-fests {
        background: #ffd13b;
        color: #14171a;
        border: 1.5px solid #14171a;
    }
</style>

<!-- PAGE HERO -->
<section class="categories-hero-section">
    <div class="container-xl text-center">
        <span class="hero-kpi-pill-badge mb-3">
            <i class="bi bi-tags-fill text-warning"></i> <?= count($categories) ?> Active Domains • 85+ Published Competitions
        </span>
        <h1 class="fw-black text-dark display-5 mb-2" style="letter-spacing: -1.5px;">Explore Event Categories</h1>
        <p class="text-muted text-xs mx-auto" style="max-width: 580px;">
            Find hackathons, cultural festivals, esports tournaments, and executive business meets tailored to your skillset.
        </p>
    </div>
</section>

<!-- CATEGORIES GRID CANVAS -->
<section class="categories-grid-canvas">
    <div class="container-xl">
        <div class="row g-4">
            <?php foreach ($categories as $c): 
                $catName = $c['name'];
                $style = $domain_styles[$catName] ?? [
                    'icon' => 'bi-star-fill', 
                    'bg'   => '#ffd13b', 
                    'color'=> '#14171a', 
                    'desc' => 'Explore inter-college events and competitions in ' . htmlspecialchars($catName) . '.'
                ];
            ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="category-card-bespoke">
                        <div>
                            <!-- Header Icon Pod & Count Badge -->
                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <div class="domain-icon-pod" style="background: <?= $style['bg'] ?>; color: <?= $style['color'] ?>;">
                                    <i class="bi <?= $style['icon'] ?>"></i>
                                </div>
                                <span class="events-count-badge <?= $c['event_count'] > 0 ? 'active-fests' : '' ?>">
                                    <i class="bi bi-calendar-event me-1"></i> <?= $c['event_count'] ?> Published Event<?= $c['event_count'] == 1 ? '' : 's' ?>
                                </span>
                            </div>

                            <!-- Category Name & Description -->
                            <h4 class="fw-black text-dark mb-2 fs-5" style="letter-spacing: -0.3px;">
                                <?= htmlspecialchars((string)$c['name']) ?>
                            </h4>
                            <p class="text-muted text-xs mb-4" style="line-height: 1.6; min-height: 38px;">
                                <?= htmlspecialchars($style['desc']) ?>
                            </p>
                        </div>

                        <!-- Footer Action Bar -->
                        <div class="pt-3 border-top border-dark border-opacity-10 d-flex align-items-center justify-content-between">
                            <a href="events.php?category=<?= urlencode($c['name']) ?>" class="btn-capsule-dark text-xs py-2.5 px-4 w-100 justify-content-center">
                                Explore <?= htmlspecialchars((string)$c['name']) ?> <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php include 'Footer.php'; ?>
