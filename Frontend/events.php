<?php
/**
 * Frontend/events.php
 * Searchable Event Catalog for Evenza Public Portal
 */
$page_title = "Browse Events";
include 'connection.php';

// Fetch Categories for Dropdown Filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Build Dynamic SQL Query
$where = ["e.status = 'published'"];
$params = [];

if (!empty($_GET['search'])) {
    $where[] = "(LOWER(e.title) LIKE :search OR LOWER(e.venue) LIKE :search)";
    $params['search'] = '%' . strtolower(trim($_GET['search'])) . '%';
}

if (!empty($_GET['category']) && $_GET['category'] !== 'all') {
    $where[] = "c.name = :category";
    $params['category'] = trim($_GET['category']);
}

if (!empty($_GET['format']) && $_GET['format'] !== 'all') {
    $where[] = "e.event_type = :format";
    $params['format'] = trim($_GET['format']);
}

$whereClause = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT e.*, c.name AS category_name, col.name AS college_name
    FROM events e
    LEFT JOIN categories c ON e.category_id = c.category_id
    LEFT JOIN colleges col ON e.college_id = col.college_id
    WHERE {$whereClause}
    ORDER BY e.event_id DESC
");
$stmt->execute($params);
$events = $stmt->fetchAll();

include 'Header.php';
?>

<div class="container-xl py-5">

    <!-- PAGE HERO -->
    <div class="text-center mb-5">
        <span class="badge bg-warning text-dark font-bold px-3 py-1 rounded-pill text-xs mb-2">Live Competitions</span>
        <h1 class="fw-black text-dark display-5 mb-2">Explore College Events & Fests</h1>
        <p class="text-muted text-xs mx-auto" style="max-width: 540px;">
            Search and filter through upcoming hackathons, sports tournaments, workshops, and cultural competitions across colleges.
        </p>
    </div>

    <!-- FILTER & SEARCH BAR -->
    <div class="bg-white rounded-5 p-4 border border-dark border-2 shadow-sm mb-5" style="box-shadow: 4px 4px 0px #14171a !important;">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-12 col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0 rounded-start-pill ps-3"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control bg-light border-0 rounded-end-pill text-xs" name="search" value="<?= htmlspecialchars((string)($_GET['search'] ?? '')) ?>" placeholder="Search event title, venue, or keyword...">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select class="form-select rounded-pill text-xs bg-light border-0 px-3" name="category">
                    <option value="all">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars((string)$cat['name']) ?>" <?= (($_GET['category'] ?? '') === $cat['name']) ? 'selected' : '' ?>><?= htmlspecialchars((string)$cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select class="form-select rounded-pill text-xs bg-light border-0 px-3" name="format">
                    <option value="all">All Formats</option>
                    <option value="solo" <?= (($_GET['format'] ?? '') === 'solo') ? 'selected' : '' ?>>Solo</option>
                    <option value="team" <?= (($_GET['format'] ?? '') === 'team') ? 'selected' : '' ?>>Team</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-grid">
                <button type="submit" class="btn-capsule-dark justify-content-center py-2 text-xs">Filter Events</button>
            </div>
        </form>
    </div>

    <!-- EVENTS GRID -->
    <div class="row g-4">
        <?php if (empty($events)): ?>
            <div class="col-12 text-center py-5 text-muted">
                <i class="bi bi-search fs-1 d-block mb-2 text-muted opacity-50"></i>
                No events match your criteria. Try adjusting your search filters!
            </div>
        <?php else: ?>
            <?php foreach ($events as $e): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="bg-white rounded-5 p-4 border border-dark border-2 shadow-sm h-100 d-flex flex-direction-column justify-content-between" style="box-shadow: 4px 4px 0px #14171a !important;">
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span class="badge bg-dark text-white rounded-pill px-3 py-1 font-bold text-xs">
                                    <?= htmlspecialchars((string)($e['category_name'] ?? 'General')) ?>
                                </span>
                                <?php if ($e['event_type'] === 'team'): ?>
                                    <span class="badge bg-purple-subtle text-purple rounded-pill px-3 py-1 font-bold text-xs" style="background:#faf5ff; color:#7e22ce;">Team (<?= $e['min_team_size'] ?>-<?= $e['max_team_size'] ?>)</span>
                                <?php else: ?>
                                    <span class="badge bg-info-subtle text-info rounded-pill px-3 py-1 font-bold text-xs" style="background:#f0f9ff; color:#0369a1;">Solo</span>
                                <?php endif; ?>
                            </div>

                            <h5 class="fw-bold text-dark mb-2" style="font-size: 18px; line-height: 1.3;">
                                <?= htmlspecialchars((string)$e['title']) ?>
                            </h5>

                            <small class="text-muted text-xs d-block mb-2"><i class="bi bi-bank2 me-1"></i> Hosted by <?= htmlspecialchars((string)($e['college_name'] ?? 'College')) ?></small>

                            <p class="text-muted text-xs mb-3" style="line-height: 1.5; height: 38px; overflow: hidden;">
                                <?= htmlspecialchars(mb_strimwidth((string)$e['description'], 0, 100, '...')) ?>
                            </p>

                            <div class="text-xs text-secondary mb-3">
                                <div class="mb-1"><i class="bi bi-geo-alt me-1 text-danger"></i> <?= htmlspecialchars((string)$e['venue']) ?></div>
                                <div><i class="bi bi-calendar3 me-1 text-primary"></i> <?= date('d M Y', strtotime($e['event_date'])) ?></div>
                            </div>
                        </div>

                        <div class="pt-3 border-top border-dark border-opacity-10 d-flex align-items-center justify-content-between">
                            <a href="event_detail.php?id=<?= $e['event_id'] ?>" class="btn-capsule-dark text-xs py-2 px-4">
                                View Details & Register
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

<?php include 'Footer.php'; ?>
