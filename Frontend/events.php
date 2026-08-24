<?php
/**
 * Frontend/events.php
 * Ultra-Premium Searchable Event Catalog for Evenza (with Seamless AJAX Pagination)
 */
$page_title = "Browse Events";
include 'connection.php';

// Fetch Categories for Dropdown Filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Dynamic Search & Filter Query Builder
$where = ["e.status = 'published'"];
$params = [];

if (!empty($_GET['search'])) {
    $where[] = "(LOWER(e.title) LIKE :search1 OR LOWER(e.venue) LIKE :search2 OR LOWER(col.name) LIKE :search3 OR LOWER(c.name) LIKE :search4)";
    $term = '%' . strtolower(trim($_GET['search'])) . '%';
    $params['search1'] = $term;
    $params['search2'] = $term;
    $params['search3'] = $term;
    $params['search4'] = $term;
}

if (!empty($_GET['category']) && $_GET['category'] !== 'all') {
    $where[] = "c.name = :category";
    $params['category'] = trim($_GET['category']);
}

if (!empty($_GET['format']) && $_GET['format'] !== 'all') {
    $where[] = "e.event_type = :format";
    $params['format'] = trim($_GET['format']);
}

if (!empty($_GET['college']) && $_GET['college'] !== 'all') {
    $where[] = "col.name = :college";
    $params['college'] = trim($_GET['college']);
}

$whereClause = implode(' AND ', $where);

// PAGINATION LOGIC (9 events per page)
$limit = 9;
$page  = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$page  = max(1, $page);

// Count Total Matching Events
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM events e
    LEFT JOIN categories c ON e.category_id = c.category_id
    LEFT JOIN colleges col ON e.college_id = col.college_id
    WHERE {$whereClause}
");
$countStmt->execute($params);
$total_records = (int)$countStmt->fetchColumn();

$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;

// Fetch Paginated Events
$sql = "
    SELECT e.*, c.name AS category_name, col.name AS college_name
    FROM events e
    LEFT JOIN categories c ON e.category_id = c.category_id
    LEFT JOIN colleges col ON e.college_id = col.college_id
    WHERE {$whereClause}
    ORDER BY e.event_id DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue(':' . $key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$events = $stmt->fetchAll();

// Helper to build URL query strings for pagination links
function get_events_page_url($pageNumber) {
    $queryParams = $_GET;
    $queryParams['page'] = $pageNumber;
    return 'events.php?' . http_build_query($queryParams);
}

include 'Header.php';
?>

<style>
    .events-hero-section {
        background-color: #fffdf7;
        padding: 52px 0 44px 0;
        position: relative;
    }

    .search-filter-card-glass {
        background: #ffffff;
        border-radius: 28px;
        padding: 18px 22px;
        border: 1.5px solid #e2e8f0;
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.04);
        transition: all 0.25s ease;
    }

    .search-filter-card-glass:focus-within {
        border-color: #14171a;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.07);
    }

    .events-grid-canvas {
        background-color: #e8f5f2;
        padding: 64px 0 88px 0;
        border-top: 1px solid #d8ece8;
        min-height: 500px;
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
        margin-bottom: 18px;
    }

    /* BESPOKE PAGINATION CONTROLS */
    .pagination-wrapper {
        margin-top: 56px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: gap-3;
    }

    .pagination-btn {
        background: #ffffff;
        color: #14171a;
        border: 1.5px solid #14171a;
        border-radius: 9999px;
        padding: 8px 20px;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .pagination-btn:hover:not(.disabled) {
        background: #14171a;
        color: #ffffff;
        transform: translateY(-2px);
    }

    .pagination-btn.disabled {
        opacity: 0.4;
        cursor: not-allowed;
        pointer-events: none;
        border-color: #cbd5e1;
    }

    .page-number-pill {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        border: 1.5px solid #cbd5e1;
        background: #ffffff;
        color: #14171a;
        font-weight: 700;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .page-number-pill:hover, .page-number-pill.active {
        background: #14171a;
        color: #ffd13b;
        border-color: #14171a;
        box-shadow: 0 4px 12px rgba(20, 23, 26, 0.15);
    }

    #ajaxEventsContainer {
        transition: opacity 0.2s ease;
    }
</style>

<!-- PAGE HERO -->
<section class="events-hero-section">
    <div class="container-xl">
        <div class="text-center mb-4">
            <span class="badge bg-warning text-dark font-bold px-3.5 py-1.5 rounded-pill text-xs mb-2">
                🔥 Live Sign-ups
            </span>
            <h1 class="fw-black text-dark display-5 mb-2" style="letter-spacing: -1.5px;">Explore College Events & Fests</h1>
            <p class="text-muted text-xs mx-auto" style="max-width: 540px;">
                Search and filter through upcoming hackathons, sports tournaments, workshops, and cultural competitions across colleges.
            </p>
        </div>

        <!-- FILTER & SEARCH BAR -->
        <div class="search-filter-card-glass mb-2">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0 ps-3 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control bg-transparent border-0 text-xs py-2" name="search" value="<?= htmlspecialchars((string)($_GET['search'] ?? '')) ?>" placeholder="Search event title, venue, or keyword...">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select rounded-pill text-xs bg-light border-0 px-3 py-2.5" name="category">
                        <option value="all">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars((string)$cat['name']) ?>" <?= (($_GET['category'] ?? '') === $cat['name']) ? 'selected' : '' ?>><?= htmlspecialchars((string)$cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select rounded-pill text-xs bg-light border-0 px-3 py-2.5" name="format">
                        <option value="all">All Formats</option>
                        <option value="solo" <?= (($_GET['format'] ?? '') === 'solo') ? 'selected' : '' ?>>Solo</option>
                        <option value="team" <?= (($_GET['format'] ?? '') === 'team') ? 'selected' : '' ?>>Team</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <button type="submit" class="btn-capsule-dark justify-content-center py-2.5 text-xs font-bold">Filter Events</button>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- EVENTS GRID CANVAS -->
<section class="events-grid-canvas" id="eventsGridCanvas">
    <div class="container-xl" id="ajaxEventsContainer">
        
        <!-- Counter Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 text-xs">
            <div class="text-secondary font-medium">
                Showing <strong class="text-dark"><?= empty($events) ? 0 : ($offset + 1) ?></strong> to <strong class="text-dark"><?= min($offset + $limit, $total_records) ?></strong> of <strong class="text-dark"><?= number_format($total_records) ?></strong> Published Events
            </div>
            <div class="badge bg-white text-dark border px-3 py-1.5 rounded-pill font-bold">
                Page <?= $page ?> of <?= $total_pages ?>
            </div>
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

                                <h5 class="event-title-clamp">
                                    <?= htmlspecialchars((string)$e['title']) ?>
                                </h5>

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

        <!-- PAGINATION BAR -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-wrapper">
                <a href="<?= get_events_page_url($page - 1) ?>" class="pagination-btn ajax-events-page-link <?= $page <= 1 ? 'disabled' : '' ?>">
                    <i class="bi bi-arrow-left"></i> Previous
                </a>

                <div class="d-flex align-items-center gap-2">
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a href="<?= get_events_page_url($p) ?>" class="page-number-pill ajax-events-page-link <?= $p === $page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                </div>

                <a href="<?= get_events_page_url($page + 1) ?>" class="pagination-btn ajax-events-page-link <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    Next <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        <?php endif; ?>

    </div>
</section>

<!-- SEAMLESS AJAX PAGINATION SCRIPT -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('ajaxEventsContainer');

    function bindEventsAjaxPagination() {
        if (!container) return;

        const links = container.querySelectorAll('.ajax-events-page-link');
        links.forEach(function (link) {
            link.addEventListener('click', function (e) {
                if (this.classList.contains('disabled')) return;
                e.preventDefault();

                const targetUrl = this.getAttribute('href');
                if (!targetUrl) return;

                container.style.opacity = '0.4';

                fetch(targetUrl)
                    .then(response => response.text())
                    .then(htmlText => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(htmlText, 'text/html');
                        const newContent = doc.getElementById('ajaxEventsContainer');

                        if (newContent) {
                            container.innerHTML = newContent.innerHTML;
                            history.pushState({}, '', targetUrl);
                            bindEventsAjaxPagination();
                            document.getElementById('eventsGridCanvas').scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    })
                    .catch(err => {
                        console.error('AJAX Pagination error:', err);
                        window.location.href = targetUrl;
                    })
                    .finally(() => {
                        container.style.opacity = '1';
                    });
            });
        });
    }

    bindEventsAjaxPagination();
});
</script>

<?php include 'Footer.php'; ?>
