<?php
require_once __DIR__ . '/../includes/event_lifecycle.php';
$active_event_sql = evenza_active_event_sql();
require_once __DIR__ . '/../includes/connection.php';
$conn = get_evenza_connection();

/**
 * Frontend/events.php
 * Ultra-Premium Searchable Event Catalog for Evenza (Full College Name Display & 6-Event Pagination)
 */
$page_title = "Browse Events";
require_once __DIR__ . '/connection.php';

// Fetch Categories for Dropdown Filter
$res_cat = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");
$categories = [];
if ($res_cat) {
    while ($r = mysqli_fetch_assoc($res_cat)) {
        $categories[] = $r;
    }
}

// Dynamic Search & Filter Query Builder
$where = ["{$active_event_sql}"];

if (!empty($_GET['search'])) {
    $search_term = mysqli_real_escape_string($conn, strtolower(trim($_GET['search'])));
    $where[] = "(LOWER(e.title) LIKE '%$search_term%' OR LOWER(e.venue) LIKE '%$search_term%' OR LOWER(col.name) LIKE '%$search_term%' OR LOWER(c.name) LIKE '%$search_term%')";
}

if (!empty($_GET['category']) && $_GET['category'] !== 'all') {
    $cat_filter = mysqli_real_escape_string($conn, trim($_GET['category']));
    $where[] = "c.name = '$cat_filter'";
}

if (!empty($_GET['format']) && $_GET['format'] !== 'all') {
    $format_filter = mysqli_real_escape_string($conn, trim($_GET['format']));
    $where[] = "e.event_type = '$format_filter'";
}

if (!empty($_GET['college']) && $_GET['college'] !== 'all') {
    $college_filter = mysqli_real_escape_string($conn, trim($_GET['college']));
    $where[] = "col.name = '$college_filter'";
}

$whereClause = implode(' AND ', $where);

// PAGINATION LOGIC (Default 6 events per page)
$limit = 6;
$page  = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$page  = max(1, $page);

// Count Total Matching Events
$count_sql = "
    SELECT COUNT(*) AS total
    FROM events e
    LEFT JOIN categories c ON e.category_id = c.category_id
    LEFT JOIN colleges col ON e.college_id = col.college_id
    WHERE {$whereClause}
";
$count_res = mysqli_query($conn, $count_sql);
$count_row = $count_res ? mysqli_fetch_assoc($count_res) : null;
$total_records = (int)($count_row['total'] ?? 0);

$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;

// Fetch Paginated Events (Newest / Latest Events First)
$sql = "
    SELECT e.*, c.name AS category_name, col.name AS college_name
    FROM events e
    LEFT JOIN categories c ON e.category_id = c.category_id
    LEFT JOIN colleges col ON e.college_id = col.college_id
    WHERE {$whereClause}
    ORDER BY e.event_id DESC
    LIMIT $limit OFFSET $offset
";

$res_events = mysqli_query($conn, $sql);
$events = [];
if ($res_events) {
    while ($r = mysqli_fetch_assoc($res_events)) {
        $events[] = $r;
    }
}

// Helper to build URL query strings for pagination links
function get_events_page_url($pageNumber) {
    $queryParams = $_GET;
    $queryParams['page'] = $pageNumber;
    return 'events.php?' . http_build_query($queryParams);
}

include 'Header.php';
?>

<style>
    :root {
        --bg-cream-canvas: #fdfbf7;
        --bg-dark-accent: #14171a;
        --color-gold-accent: #ffd13b;
    }

    /* HERO & BESPOKE SEARCH CAPSULE STYLING */
    .events-hero-section {
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

    .search-filter-capsule-luxury {
        background: #ffffff;
        border-radius: 9999px;
        padding: 10px 14px 10px 24px;
        border: 2px solid #14171a;
        box-shadow: 6px 6px 0px #14171a;
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .search-filter-capsule-luxury:focus-within,
    .search-filter-capsule-luxury:hover {
        box-shadow: 8px 8px 0px #ffd13b;
        border-color: #14171a;
    }

    .search-filter-capsule-luxury input.form-control {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        outline: none !important;
        font-size: 13px;
        color: #14171a;
    }

    .search-filter-capsule-luxury select.form-select {
        background-color: #ffffff !important;
        border: 1.5px solid #cbd5e1 !important;
        border-radius: 12px !important;
        padding: 8px 32px 8px 14px !important;
        font-size: 13px !important;
        font-weight: 700 !important;
        color: #0f172a !important;
        cursor: pointer;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04) !important;
        transition: all 0.2s ease !important;
        appearance: none !important;
        -webkit-appearance: none !important;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%230f172a' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E") !important;
        background-repeat: no-repeat !important;
        background-position: right 12px center !important;
    }

    .search-filter-capsule-luxury input.form-control:focus {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        outline: none !important;
    }

    .search-filter-capsule-luxury select.form-select:focus,
    .search-filter-capsule-luxury select.form-select:hover {
        border-color: #0f172a !important;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08) !important;
        outline: none !important;
    }

    select.form-select option, .form-select option {
        background-color: #ffffff !important;
        color: #0f172a !important;
        font-size: 13px !important;
        font-weight: 700 !important;
        padding: 10px 14px !important;
    }

    select.form-select option:hover,
    select.form-select option:focus,
    select.form-select option:checked {
        background-color: #ffd13b !important;
        color: #0f172a !important;
    }

    .search-filter-capsule-luxury .input-group-text {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        color: #14171a;
        font-size: 15px;
    }

    .btn-search-capsule-dark {
        background: #14171a;
        color: #ffffff;
        border-radius: 9999px;
        font-size: 12px;
        font-weight: 800;
        padding: 10px 24px;
        border: 1.5px solid #14171a;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .btn-search-capsule-dark:hover {
        background: #ffd13b;
        color: #14171a;
        border-color: #14171a;
    }

    /* CANVAS GRID SECTION */
    .events-grid-canvas {
        background-color: var(--bg-cream-canvas);
        padding: 64px 0 88px 0;
        border-top: 1px solid #f1f5f9;
        min-height: 500px;
    }

    .event-card-bespoke {
        background: #ffffff;
        border-radius: 28px;
        padding: 28px;
        border: 1.5px solid #eae6df;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
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
        padding: 4px 14px;
        border-radius: 9999px;
        border: 1.5px solid #14171a;
    }

    .format-chip {
        background: #eff6ff;
        color: #2563eb;
        font-size: 11px;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 9999px;
        border: 1px solid #bfdbfe;
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

    .venue-date-pill-box {
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 18px;
        padding: 12px 16px;
        font-size: 12px;
        color: #475569;
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
<link rel="stylesheet" href="../assets/css/select2.min.css">
<link rel="stylesheet" href="../assets/css/college-filters.css">

<!-- PAGE HERO -->
<section class="events-hero-section">
    <div class="container-xl">
        <div class="text-center mb-4">
            <span class="hero-kpi-pill-badge mb-3">
                <i class="bi bi-fire text-warning"></i> 85+ Live Fests • 160+ Partner Campuses
            </span>
            <h1 class="fw-black text-dark display-5 mb-2" style="letter-spacing: -1.5px;">Explore College Events & Fests</h1>
            <p class="text-muted text-xs mx-auto" style="max-width: 580px;">
                Search and filter through upcoming hackathons, sports tournaments, workshops, and cultural competitions across colleges.
            </p>
        </div>

        <!-- BESPOKE LUXURY SEARCH & FILTER CAPSULE -->
        <div class="search-filter-capsule-luxury mx-auto" style="max-width: 980px;">
            <form method="GET" class="row g-2 align-items-center" id="eventsFilterForm">
                <div class="col-12 col-md-5">
                    <div class="input-group college-search-field">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <div class="college-search-content">
                            <label for="eventSearchInput" class="college-filter-label">Find your next event</label>
                            <input id="eventSearchInput" type="text" class="form-control" name="search" autocomplete="off" value="<?= htmlspecialchars((string)($_GET['search'] ?? '')) ?>" placeholder="Event title, venue or keyword">
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="university-filter-field">
                        <label for="eventCategoryFilter" class="college-filter-label">Event category</label>
                        <select id="eventCategoryFilter" class="form-select text-xs py-2" name="category" data-styled-filter data-filter-kind="categories" data-filter-icon="bi bi-grid">
                            <option value="all">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars((string)$cat['name']) ?>" <?= (($_GET['category'] ?? '') === $cat['name']) ? 'selected' : '' ?>><?= htmlspecialchars((string)$cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-2">
                    <div class="university-filter-field">
                        <label for="eventFormatFilter" class="college-filter-label">Participation</label>
                        <select id="eventFormatFilter" class="form-select text-xs py-2" name="format" data-styled-filter data-filter-kind="formats" data-filter-icon="bi bi-people">
                            <option value="all">All Formats</option>
                            <option value="solo" <?= (($_GET['format'] ?? '') === 'solo') ? 'selected' : '' ?>>Solo</option>
                            <option value="team" <?= (($_GET['format'] ?? '') === 'team') ? 'selected' : '' ?>>Team</option>
                        </select>
                    </div>
                </div>
                <div class="col-12 col-md-2 text-end">
                    <button type="submit" class="btn-search-capsule-dark w-100">
                        Filter <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- EVENTS GRID CANVAS -->
<section class="events-grid-canvas" id="eventsGridCanvas">
    <div class="container-xl" id="ajaxEventsContainer">
        
        <!-- Executive Results Counter Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-dark text-white font-bold px-3 py-1.5 rounded-pill text-xs">
                    Showing <?= empty($events) ? 0 : ($offset + 1) ?> – <?= min($offset + $limit, $total_records) ?>
                </span>
                <span class="text-secondary text-xs font-semibold">
                    of <strong class="text-dark"><?= number_format($total_records) ?></strong> Published Events
                </span>
            </div>
            <div class="badge bg-warning text-dark border border-dark px-3.5 py-1.5 rounded-pill text-xs font-bold shadow-sm">
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
                                <!-- Top Row Badges -->
                                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                    <span class="category-chip">
                                        <?= htmlspecialchars((string)($e['category_name'] ?? 'Fest')) ?>
                                    </span>
                                    <span class="format-chip">
                                        <i class="bi bi-person-fill me-1"></i> <?= ucfirst($e['event_type']) ?> <?= $e['event_type'] === 'team' ? '(' . $e['min_team_size'] . '-' . $e['max_team_size'] . ')' : '' ?>
                                    </span>
                                </div>

                                <!-- Event Title -->
                                <h5 class="event-title-clamp">
                                    <?= htmlspecialchars((string)$e['title']) ?>
                                </h5>

                                <!-- FULL COLLEGE NAME HOST ROW -->
                                <div class="college-host-row mb-3">
                                    <i class="bi bi-bank2 text-warning me-1.5"></i>
                                    Hosted by <strong class="text-dark"><?= htmlspecialchars((string)($e['college_name'] ?? 'Partner College')) ?></strong>
                                </div>

                                <!-- Description -->
                                <p class="event-desc-clamp">
                                    <?= htmlspecialchars((string)$e['description']) ?>
                                </p>

                                <!-- Venue & Date -->
                                <div class="venue-date-pill-box mb-4 d-flex align-items-center justify-content-between text-xs">
                                    <div class="text-truncate me-2"><i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= htmlspecialchars((string)$e['venue']) ?></div>
                                    <div class="text-nowrap"><i class="bi bi-calendar-event-fill text-primary me-1"></i> <?= date('d M Y', strtotime($e['event_date'])) ?></div>
                                </div>
                            </div>

                            <!-- Footer Action Bar -->
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

<!-- SEAMLESS LIVE REAL-TIME AJAX SEARCH & PAGINATION SCRIPT -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('ajaxEventsContainer');
    const filterForm = document.getElementById('eventsFilterForm');
    const searchInput = filterForm ? filterForm.querySelector('input[name="search"]') : null;
    const selects = filterForm ? filterForm.querySelectorAll('select') : [];

    let debounceTimer = null;

    function fetchFilteredEvents(url, pushHistory = true) {
        if (!container) return;
        container.style.opacity = '0.4';

        fetch(url)
            .then(response => response.text())
            .then(htmlText => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(htmlText, 'text/html');
                const newContent = doc.getElementById('ajaxEventsContainer');

                if (newContent) {
                    container.innerHTML = newContent.innerHTML;
                    if (pushHistory) {
                        history.replaceState({}, '', url);
                    }
                    bindEventsAjaxPagination();
                }
            })
            .catch(err => console.error('Events Live Search AJAX error:', err))
            .finally(() => {
                container.style.opacity = '1';
            });
    }

    function triggerLiveSearch() {
        if (!filterForm) return;
        const formData = new FormData(filterForm);
        const searchParams = new URLSearchParams();
        
        for (const [key, value] of formData.entries()) {
            if (value && value !== 'all') {
                searchParams.set(key, value);
            }
        }
        searchParams.set('page', '1');

        const targetUrl = 'events.php?' + searchParams.toString();
        fetchFilteredEvents(targetUrl, true);
    }

    // Live debounced search on typing (250ms)
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(triggerLiveSearch, 250);
        });
    }

    // Live filter on select dropdown changes
    selects.forEach(function(selectEl) {
        selectEl.addEventListener('change', function () {
            triggerLiveSearch();
        });
    });

    // Prevent full-page form submit on Enter key
    if (filterForm) {
        filterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            triggerLiveSearch();
        });
    }

    function bindEventsAjaxPagination() {
        if (!container) return;

        const links = container.querySelectorAll('.ajax-events-page-link');
        links.forEach(function (link) {
            link.addEventListener('click', function (e) {
                if (this.classList.contains('disabled')) return;
                e.preventDefault();

                const targetUrl = this.getAttribute('href');
                if (!targetUrl) return;

                fetchFilteredEvents(targetUrl, true);
                document.getElementById('eventsGridCanvas').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }

    bindEventsAjaxPagination();
});
</script>
<script src="../assets/js/jquery.min.js"></script>
<script src="../assets/js/select2.min.js"></script>
<script src="../assets/js/college-filters.js"></script>

<?php include 'Footer.php'; ?>
