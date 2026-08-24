<?php
/**
 * Frontend/colleges.php
 * Ultra-Premium Partner Colleges Directory for Evenza
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

// Fetch Active Colleges with University Name and Hosted Events Count
$stmt = $pdo->prepare("
    SELECT c.*, u.name AS university_name,
    (SELECT COUNT(*) FROM events e WHERE e.college_id = c.college_id AND e.status = 'published') AS event_count
    FROM colleges c
    LEFT JOIN universities u ON c.university_id = u.university_id
    WHERE {$whereClause}
    ORDER BY event_count DESC, c.name ASC
");
$stmt->execute($params);
$colleges = $stmt->fetchAll();

include 'Header.php';
?>

<style>
    .colleges-hero-section {
        background-color: #fffdf7;
        padding: 56px 0 64px 0;
        position: relative;
    }

    .search-filter-card {
        background: #ffffff;
        border-radius: 24px;
        padding: 20px 24px;
        border: 2px solid #14171a;
        box-shadow: 5px 5px 0px #14171a;
    }

    .college-card-luxury {
        background: #ffffff;
        border-radius: 28px;
        padding: 28px;
        border: 1.5px solid #f1f5f9;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        position: relative;
    }

    .college-card-luxury:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 42px rgba(0, 0, 0, 0.08);
        border-color: #14171a;
    }

    .college-logo-pod {
        width: 58px;
        height: 58px;
        border-radius: 18px;
        background: #fef3e2;
        border: 1.5px solid #ffd13b;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: #14171a;
        flex-shrink: 0;
        overflow: hidden;
    }

    .college-logo-pod img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .university-pill-tag {
        font-size: 11px;
        font-weight: 700;
        color: #4f46e5;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        padding: 3px 12px;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .contact-info-box {
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 18px;
        padding: 14px 16px;
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
    }
</style>

<!-- PAGE HERO -->
<section class="colleges-hero-section">
    <div class="container-xl">
        <div class="text-center mb-5">
            <span class="badge bg-warning text-dark font-bold px-3.5 py-1.5 rounded-pill text-xs mb-2">
                <i class="bi bi-bank2 me-1"></i> Accredited Network
            </span>
            <h1 class="fw-black text-dark display-5 mb-2" style="letter-spacing: -1.5px;">Partner Colleges & Institutions</h1>
            <p class="text-muted text-xs mx-auto" style="max-width: 560px;">
                Discover accredited colleges hosting inter-college fests, technical hackathons, and national competitions on Evenza.
            </p>
        </div>

        <!-- SEARCH & FILTER BAR -->
        <div class="search-filter-card mb-5">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-12 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0 rounded-start-pill ps-3"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control bg-light border-0 rounded-end-pill text-xs py-2.5" name="search" value="<?= htmlspecialchars((string)($_GET['search'] ?? '')) ?>" placeholder="Search college name, location, or email...">
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

<!-- COLLEGES GRID SECTION -->
<section class="py-5 bg-white">
    <div class="container-xl">
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
                        : 'https://ui-avatars.com/api/?name=' . urlencode($c['name']) . '&background=fef3e2&color=14171a&bold=true';
                ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="college-card-luxury">
                            <div>
                                <!-- Header Logo & Name -->
                                <div class="d-flex align-items-start gap-3 mb-4">
                                    <div class="college-logo-pod">
                                        <img src="<?= $logo_src ?>" alt="College Logo" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($c['name']) ?>&background=fef3e2&color=14171a'">
                                    </div>
                                    <div class="overflow-hidden">
                                        <h5 class="fw-black text-dark mb-1 fs-6" style="line-height: 1.35; letter-spacing: -0.3px;">
                                            <?= htmlspecialchars((string)$c['name']) ?>
                                        </h5>
                                        <span class="university-pill-tag">
                                            <i class="bi bi-mortarboard-fill"></i> <?= htmlspecialchars((string)($c['university_name'] ?? 'Main University')) ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Contact Info Box -->
                                <div class="contact-info-box mb-4">
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
    </div>
</section>

<?php include 'Footer.php'; ?>
