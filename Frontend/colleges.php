<?php
/**
 * Frontend/colleges.php
 * Ultra-Premium Partner Colleges Directory for Evenza (with 6-Card Pagination)
 */
$page_title = "Partner Colleges";
include 'connection.php';

// Fetch Universities for Filter Dropdown
$universities = $pdo->query("SELECT * FROM universities ORDER BY name ASC")->fetchAll();

// Dynamic Search & Filter Logic
$where = ["c.status = 'active'"];
$params = [];

if (!empty($_GET['search'])) {
    $where[] = "(LOWER(c.name) LIKE :search OR LOWER(c.email) LIKE :search)";
    $params['search'] = '%' . strtolower(trim($_GET['search'])) . '%';
}

if (!empty($_GET['university']) && $_GET['university'] !== 'all') {
    $where[] = "u.name = :university";
    $params['university'] = trim($_GET['university']);
}

$whereClause = implode(' AND ', $where);

// PAGINATION LOGIC (Default 6 colleges per page)
$limit = 6;
$page  = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$page  = max(1, $page);

// Count Total Records
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM colleges c
    LEFT JOIN universities u ON c.university_id = u.university_id
    WHERE {$whereClause}
");
$countStmt->execute($params);
$total_records = (int)$countStmt->fetchColumn();

$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;

// Fetch Paginated Active Colleges with University Name and Hosted Events Count
$sql = "
    SELECT c.*, u.name AS university_name,
    (SELECT COUNT(*) FROM events e WHERE e.college_id = c.college_id AND e.status = 'published') AS event_count
    FROM colleges c
    LEFT JOIN universities u ON c.university_id = u.university_id
    WHERE {$whereClause}
    ORDER BY event_count DESC, c.name ASC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue(':' . $key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$colleges = $stmt->fetchAll();

// Helper to build URL query strings for pagination links
function get_page_url($pageNumber) {
    $queryParams = $_GET;
    $queryParams['page'] = $pageNumber;
    return 'colleges.php?' . http_build_query($queryParams);
}

include 'Header.php';
?>

<style>
    :root {
        --bg-cream-canvas: #fdfbf7;
        --bg-dark-accent: #14171a;
        --color-gold-accent: #ffd13b;
    }

    /* HERO & SEARCH BAR STYLING */
    .colleges-hero-section {
        background-color: #fffdf7;
        padding: 52px 0 44px 0;
        position: relative;
    }

    .search-filter-card-glass {
        background: #ffffff;
        border-radius: 28px;
        padding: 16px 20px;
        border: 1.5px solid #e2e8f0;
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.04);
        transition: all 0.25s ease;
    }

    .search-filter-card-glass:focus-within {
        border-color: #14171a;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.07);
    }

    /* CANVAS GRID SECTION */
    .colleges-grid-canvas {
        background-color: var(--bg-cream-canvas);
        padding: 64px 0 80px 0;
        border-top: 1px solid #f1f5f9;
        min-height: 500px;
    }

    .college-card-bespoke {
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
        position: relative;
    }

    .college-card-bespoke:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 42px rgba(0, 0, 0, 0.08);
        border-color: #14171a;
    }

    .college-avatar-pod {
        width: 54px;
        height: 54px;
        border-radius: 16px;
        background: #ffd13b;
        border: 2px solid #14171a;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        font-weight: 800;
        color: #14171a;
        flex-shrink: 0;
        overflow: hidden;
        box-shadow: 3px 3px 0px #14171a;
    }

    .college-avatar-pod img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .university-pill-tag {
        font-size: 11px;
        font-weight: 700;
        color: #2563eb;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        padding: 3px 12px;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        max-width: 100%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .contact-info-pill-box {
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 18px;
        padding: 12px 16px;
        font-size: 12px;
        color: #475569;
    }

    .events-count-chip {
        background: #ffd13b;
        color: #14171a;
        font-size: 11px;
        font-weight: 800;
        padding: 5px 14px;
        border-radius: 9999px;
        border: 1px solid #14171a;
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
        border: 1.5px solid #e2e8f0;
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
</style>

<!-- PAGE HERO -->
<section class="colleges-hero-section">
    <div class="container-xl">
        <div class="text-center mb-4">
            <span class="badge bg-warning text-dark font-bold px-3.5 py-1.5 rounded-pill text-xs mb-2">
                <i class="bi bi-bank2 me-1"></i> Accredited Campus Network
            </span>
            <h1 class="fw-black text-dark display-5 mb-2" style="letter-spacing: -1.5px;">Partner Colleges & Institutions</h1>
            <p class="text-muted text-xs mx-auto" style="max-width: 560px;">
                Discover accredited colleges hosting inter-college fests, technical hackathons, and national competitions on Evenza.
            </p>
        </div>

        <!-- SEARCH & FILTER BAR -->
        <div class="search-filter-card-glass mb-2">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-12 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0 ps-3 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control bg-transparent border-0 text-xs py-2" name="search" value="<?= htmlspecialchars((string)($_GET['search'] ?? '')) ?>" placeholder="Search college name, location, or email...">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <select class="form-select rounded-pill text-xs bg-light border-0 px-3 py-2.5" name="university">
                        <option value="all">All Affiliated Universities</option>
                        <?php foreach ($universities as $u): ?>
                            <option value="<?= htmlspecialchars((string)$u['name']) ?>" <?= (($_GET['university'] ?? '') === $u['name']) ? 'selected' : '' ?>><?= htmlspecialchars((string)$u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <button type="submit" class="btn-capsule-dark justify-content-center py-2.5 text-xs font-bold">Filter Colleges</button>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- COLLEGES GRID CANVAS SECTION -->
<section class="colleges-grid-canvas">
    <div class="container-xl">
        
        <!-- Results Counter Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 text-xs">
            <div class="text-secondary font-medium">
                Showing <strong class="text-dark"><?= empty($colleges) ? 0 : ($offset + 1) ?></strong> to <strong class="text-dark"><?= min($offset + $limit, $total_records) ?></strong> of <strong class="text-dark"><?= number_format($total_records) ?></strong> Partner Colleges
            </div>
            <div class="badge bg-white text-dark border px-3 py-1.5 rounded-pill font-bold">
                Page <?= $page ?> of <?= $total_pages ?>
            </div>
        </div>

        <div class="row g-4">
            <?php if (empty($colleges)): ?>
                <div class="col-12 text-center py-5 text-muted">
                    <i class="bi bi-building-x fs-1 d-block mb-2 text-muted opacity-50"></i>
                    No partner colleges match your search criteria.
                </div>
            <?php else: ?>
                <?php foreach ($colleges as $c): 
                    $logo_src = !empty($c['logo']) && file_exists(__DIR__ . '/../uploads/colleges/' . $c['logo']) 
                        ? '../uploads/colleges/' . htmlspecialchars($c['logo'])
                        : '';
                    
                    // Generate 2-letter Initials for Avatar Fallback
                    $words = explode(' ', trim($c['name']));
                    $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="college-card-bespoke">
                            <div>
                                <!-- Header Logo & Name -->
                                <div class="d-flex align-items-start gap-3 mb-4">
                                    <div class="college-avatar-pod">
                                        <?php if ($logo_src): ?>
                                            <img src="<?= $logo_src ?>" alt="College Logo">
                                        <?php else: ?>
                                            <span><?= $initials ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="overflow-hidden">
                                        <h5 class="fw-black text-dark mb-1 fs-6" style="line-height: 1.35; letter-spacing: -0.3px;">
                                            <?= htmlspecialchars((string)$c['name']) ?>
                                        </h5>
                                        <span class="university-pill-tag" title="<?= htmlspecialchars((string)($c['university_name'] ?? 'Main University')) ?>">
                                            <i class="bi bi-mortarboard-fill"></i> <?= htmlspecialchars((string)($c['university_name'] ?? 'Main University')) ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Contact Info Box -->
                                <div class="contact-info-pill-box mb-4">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <i class="bi bi-envelope-fill text-primary"></i>
                                        <span class="text-truncate"><?= htmlspecialchars((string)$c['email']) ?></span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-telephone-fill text-success"></i>
                                        <span><?= htmlspecialchars((string)($c['phone'] ?? 'N/A')) ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Action Bar -->
                            <div class="pt-3 border-top border-dark border-opacity-10 d-flex align-items-center justify-content-between">
                                <span class="events-count-chip">
                                    <i class="bi bi-calendar-event me-1"></i> <?= $c['event_count'] ?> Hosted Event<?= $c['event_count'] == 1 ? '' : 's' ?>
                                </span>
                                <a href="events.php?college=<?= urlencode($c['name']) ?>" class="btn-capsule-dark text-xs py-2 px-3">
                                    Explore Fests <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- BESPOKE 6-CARD PAGINATION BAR -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-wrapper">
                <!-- Previous Button -->
                <a href="<?= get_page_url($page - 1) ?>" class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                    <i class="bi bi-arrow-left"></i> Previous
                </a>

                <!-- Number Pills -->
                <div class="d-flex align-items-center gap-2">
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a href="<?= get_page_url($p) ?>" class="page-number-pill <?= $p === $page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                </div>

                <!-- Next Button -->
                <a href="<?= get_page_url($page + 1) ?>" class="pagination-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    Next <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php include 'Footer.php'; ?>
