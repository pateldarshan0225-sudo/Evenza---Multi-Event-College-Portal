<?php
/**
 * Frontend/colleges.php
 * Partner Colleges Directory for Evenza Public Portal
 */
$page_title = "Partner Colleges";
include 'connection.php';

// Fetch Active Colleges with University Name and Hosted Events Count
$colleges = $pdo->query("
    SELECT c.*, u.name AS university_name,
    (SELECT COUNT(*) FROM events e WHERE e.college_id = c.college_id) AS event_count
    FROM colleges c
    LEFT JOIN universities u ON c.university_id = u.university_id
    WHERE c.status = 'active'
    ORDER BY c.name ASC
")->fetchAll();

include 'Header.php';
?>

<div class="container-xl py-5">
    
    <!-- PAGE HERO -->
    <div class="text-center mb-5">
        <span class="badge bg-warning text-dark font-bold px-3 py-1 rounded-pill text-xs mb-2">Accredited Partners</span>
        <h1 class="fw-black text-dark display-5 mb-2">Partner Colleges & Institutions</h1>
        <p class="text-muted text-xs mx-auto" style="max-width: 540px;">
            Explore affiliated colleges hosting inter-college fests, technical hackathons, and national competitions on Evenza.
        </p>
    </div>

    <!-- COLLEGES GRID -->
    <div class="row g-4">
        <?php if (empty($colleges)): ?>
            <div class="col-12 text-center py-5 text-muted">
                <i class="bi bi-building-x fs-1 d-block mb-2 text-muted opacity-50"></i>
                No partner colleges listed yet.
            </div>
        <?php else: ?>
            <?php foreach ($colleges as $c): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="bg-white rounded-5 p-4 border border-dark border-2 shadow-sm h-100 d-flex flex-direction-column justify-content-between" style="box-shadow: 4px 4px 0px #14171a !important;">
                        <div>
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="bg-warning-subtle text-dark p-3 rounded-4 fs-3">
                                    <i class="bi bi-building"></i>
                                </div>
                                <div>
                                    <h5 class="fw-black text-dark mb-0 fs-6"><?= htmlspecialchars((string)$c['name']) ?></h5>
                                    <small class="text-muted" style="font-size: 11px;">Affiliated: <strong><?= htmlspecialchars((string)($c['university_name'] ?? 'Main University')) ?></strong></small>
                                </div>
                            </div>

                            <div class="text-xs text-secondary mb-3">
                                <div class="mb-1"><i class="bi bi-envelope me-1"></i> <?= htmlspecialchars((string)$c['email']) ?></div>
                                <div><i class="bi bi-telephone me-1"></i> <?= htmlspecialchars((string)($c['phone'] ?? 'N/A')) ?></div>
                            </div>
                        </div>

                        <div class="pt-3 border-top border-dark border-opacity-10 d-flex align-items-center justify-content-between">
                            <span class="badge bg-dark text-white rounded-pill px-3 py-1 font-bold text-xs">
                                <?= $c['event_count'] ?> Hosted Events
                            </span>
                            <a href="events.php?college=<?= urlencode($c['name']) ?>" class="btn-capsule-outline py-1 px-3 text-xs">
                                View Events &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php include 'Footer.php'; ?>
