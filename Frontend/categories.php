<?php
/**
 * Frontend/categories.php
 * Categories Showcase Page for Evenza Public Portal
 */
$page_title = "Event Categories";
include 'connection.php';

// Fetch Categories with Active Event Counts
$categories = $pdo->query("
    SELECT c.*, (SELECT COUNT(*) FROM events e WHERE e.category_id = c.category_id AND e.status = 'published') AS event_count
    FROM categories c
    ORDER BY c.name ASC
")->fetchAll();

include 'Header.php';
?>

<div class="container-xl py-5">
    
    <div class="text-center mb-5">
        <span class="badge bg-warning text-dark font-bold px-3 py-1 rounded-pill text-xs mb-2">Explore By Domain</span>
        <h1 class="fw-black text-dark display-5 mb-2">Event Categories</h1>
        <p class="text-muted text-xs mx-auto" style="max-width: 540px;">
            Find hackathons, cultural festivals, sports meets, and business competitions tailored to your skillset.
        </p>
    </div>

    <div class="row g-4">
        <?php foreach ($categories as $idx => $c): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="bg-white rounded-5 p-4 border border-dark border-2 shadow-sm h-100 d-flex flex-direction-column justify-content-between" style="box-shadow: 4px 4px 0px #14171a !important;">
                    <div>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="bg-dark text-warning p-3 rounded-4 fs-3">
                                <i class="bi bi-tag-fill"></i>
                            </div>
                            <div>
                                <h5 class="fw-black text-dark mb-0 fs-6"><?= htmlspecialchars((string)$c['name']) ?></h5>
                                <small class="text-muted" style="font-size: 11px;"><?= $c['event_count'] ?> Active Published Events</small>
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 border-top border-dark border-opacity-10 d-flex align-items-center justify-content-between">
                        <a href="events.php?category=<?= urlencode($c['name']) ?>" class="btn-capsule-dark py-2 px-4 text-xs">
                            Browse <?= htmlspecialchars((string)$c['name']) ?> &rarr;
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<?php include 'Footer.php'; ?>
