<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_helpers.php';

require_role('ADMIN');

$flash = flash_get();
$loadError = null;
$filterError = null;
$perPage = 10;

$search = trim((string) ($_GET['q'] ?? ''));
$typeFilter = strtoupper(trim((string) ($_GET['type'] ?? 'ALL')));
$districtFilter = trim((string) ($_GET['district'] ?? 'ALL'));
$listerFilter = (int) ($_GET['lister'] ?? 0);
$fromInput = trim((string) ($_GET['from'] ?? ''));
$toInput = trim((string) ($_GET['to'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));

if (!in_array($typeFilter, ['ALL', ...admin_allowed_property_types()], true)) {
    $typeFilter = 'ALL';
}

$fromDate = admin_parse_report_date($fromInput !== '' ? $fromInput : null);
$toDate = admin_parse_report_date($toInput !== '' ? $toInput : null);

if ($fromInput !== '' && $fromDate === false) {
    $filterError = 'Please enter a valid start date (YYYY-MM-DD).';
    $fromInput = '';
    $fromDate = null;
}
if ($toInput !== '' && $toDate === false) {
    $filterError = $filterError === null ? 'Please enter a valid end date (YYYY-MM-DD).' : $filterError . ' Please enter a valid end date (YYYY-MM-DD).';
    $toInput = '';
    $toDate = null;
}
if ($fromDate !== null && $toDate !== null && $fromDate > $toDate) {
    $filterError = 'Start date cannot be after end date.';
}

$properties = [];
$totalProperties = 0;
$totalPages = 1;
$districtOptions = [];
$listerOptions = [];

try {
    $pdo = db();

    $districtStmt = $pdo->query(
        "SELECT DISTINCT district
         FROM properties
         WHERE status = 'SOLD' AND district <> ''
         ORDER BY district ASC"
    );
    $districtOptions = array_column($districtStmt->fetchAll(), 'district');

    $listerStmt = $pdo->query(
        "SELECT DISTINCT u.user_id, u.full_name, u.email
         FROM properties p
         INNER JOIN users u ON u.user_id = p.listed_by_user_id
         WHERE p.status = 'SOLD'
         ORDER BY u.full_name ASC, u.user_id ASC"
    );
    $listerOptions = $listerStmt->fetchAll();

    $where = ['p.status = ?'];
    $params = ['SOLD'];

    if ($search !== '') {
        $where[] = '(p.title LIKE ? OR p.district LIKE ? OR p.area LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
        $like = '%' . $search . '%';
        $params = array_merge($params, [$like, $like, $like, $like, $like]);
    }

    if ($typeFilter !== 'ALL') {
        $where[] = 'p.property_type = ?';
        $params[] = $typeFilter;
    }

    if ($districtFilter !== '' && $districtFilter !== 'ALL') {
        $where[] = 'p.district = ?';
        $params[] = $districtFilter;
    }

    if ($listerFilter > 0) {
        $where[] = 'p.listed_by_user_id = ?';
        $params[] = $listerFilter;
    }

    if ($filterError === null && $fromDate !== null) {
        $where[] = 'DATE(p.updated_at) >= ?';
        $params[] = $fromDate;
    }

    if ($filterError === null && $toDate !== null) {
        $where[] = 'DATE(p.updated_at) <= ?';
        $params[] = $toDate;
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM properties p
         INNER JOIN users u ON u.user_id = p.listed_by_user_id
         {$whereSql}"
    );
    $countStmt->execute($params);
    $totalProperties = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalProperties / $perPage));

    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;

    $listSql = "SELECT p.property_id, p.title, p.district, p.property_type, p.asking_price_lkr,
                       p.status, p.updated_at, p.listed_by_user_id,
                       u.full_name AS lister_name, u.email AS lister_email, u.role AS lister_role,
                       (
                           SELECT pi.image_path
                           FROM property_images pi
                           WHERE pi.property_id = p.property_id
                           ORDER BY pi.is_primary DESC, pi.image_id ASC
                           LIMIT 1
                       ) AS primary_image
                FROM properties p
                INNER JOIN users u ON u.user_id = p.listed_by_user_id
                {$whereSql}
                ORDER BY p.updated_at DESC, p.property_id DESC
                LIMIT {$perPage} OFFSET {$offset}";
    $listStmt = $pdo->prepare($listSql);
    $listStmt->execute($params);
    $properties = $listStmt->fetchAll();
} catch (Throwable $e) {
    $loadError = 'Unable to load sold property history right now. Please try again later.';
}

$page_title = 'Sold History | RealEstateAI';
$page_description = 'Admin view of all sold property listings on RealEstateAI.';

$queryBase = [
    'q' => $search !== '' ? $search : null,
    'type' => $typeFilter !== 'ALL' ? $typeFilter : null,
    'district' => $districtFilter !== '' && $districtFilter !== 'ALL' ? $districtFilter : null,
    'lister' => $listerFilter > 0 ? $listerFilter : null,
    'from' => $fromInput !== '' ? $fromInput : null,
    'to' => $toInput !== '' ? $toInput : null,
];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="admin-dashboard">
    <div class="container">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo e(url('admin/index.php')); ?>">Admin Dashboard</a>
            <span aria-hidden="true">/</span>
            <span>Sold History</span>
        </nav>

        <div class="admin-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <p class="admin-eyebrow">Administration</p>
                <h1 class="admin-title">Sold History</h1>
                <p class="admin-welcome mb-0">All sold listings across sellers and admin listers. Dates reflect each listing&apos;s last update time.</p>
            </div>
            <a class="btn btn-outline-secondary" href="<?php echo e(url('admin/properties.php')); ?>">Manage Properties</a>
        </div>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($filterError !== null): ?>
            <div class="alert alert-warning" role="alert"><?php echo e($filterError); ?></div>
        <?php endif; ?>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($loadError); ?></div>
        <?php else: ?>
            <section class="admin-section">
                <form class="filter-panel row g-3 align-items-end" method="get" action="">
                    <div class="col-lg-4 col-md-6">
                        <label for="q" class="form-label">Search</label>
                        <input type="search" class="form-control" id="q" name="q" value="<?php echo e($search); ?>" placeholder="Title, lister name, email or district">
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <label for="type" class="form-label">Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="ALL"<?php echo $typeFilter === 'ALL' ? ' selected' : ''; ?>>ALL</option>
                            <?php foreach (admin_allowed_property_types() as $type): ?>
                                <option value="<?php echo e($type); ?>"<?php echo $typeFilter === $type ? ' selected' : ''; ?>><?php echo e($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <label for="district" class="form-label">District</label>
                        <select class="form-select" id="district" name="district">
                            <option value="ALL"<?php echo $districtFilter === 'ALL' || $districtFilter === '' ? ' selected' : ''; ?>>ALL</option>
                            <?php foreach ($districtOptions as $district): ?>
                                <option value="<?php echo e((string) $district); ?>"<?php echo $districtFilter === (string) $district ? ' selected' : ''; ?>><?php echo e((string) $district); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <label for="lister" class="form-label">Seller / Lister</label>
                        <select class="form-select" id="lister" name="lister">
                            <option value="0">ALL</option>
                            <?php foreach ($listerOptions as $lister): ?>
                                <?php $listerId = (int) ($lister['user_id'] ?? 0); ?>
                                <option value="<?php echo e((string) $listerId); ?>"<?php echo $listerFilter === $listerId ? ' selected' : ''; ?>>
                                    <?php echo e((string) ($lister['full_name'] ?? '')); ?> (<?php echo e((string) ($lister['email'] ?? '')); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <label for="from" class="form-label">Updated from</label>
                        <input type="date" class="form-control" id="from" name="from" value="<?php echo e($fromInput); ?>">
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <label for="to" class="form-label">Updated to</label>
                        <input type="date" class="form-control" id="to" name="to" value="<?php echo e($toInput); ?>">
                    </div>
                    <div class="col-lg-4 col-md-12 d-flex gap-2">
                        <button type="submit" class="btn btn-auth flex-grow-1">Apply</button>
                        <a class="btn btn-outline-secondary" href="<?php echo e(url('admin/sold_history.php')); ?>">Reset</a>
                    </div>
                </form>
            </section>

            <section class="admin-section">
                <div class="table-panel">
                    <p class="mb-3 text-muted"><?php echo e((string) $totalProperties); ?> sold listing<?php echo $totalProperties === 1 ? '' : 's'; ?> found</p>

                    <?php if ($properties === []): ?>
                        <p class="empty-state mb-0">No sold properties have been recorded yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table admin-table mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">ID</th>
                                        <th scope="col">Property</th>
                                        <th scope="col">Listed By</th>
                                        <th scope="col">District</th>
                                        <th scope="col">Type</th>
                                        <th scope="col">Asking Price</th>
                                        <th scope="col">Sold/Updated Date</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($properties as $row): ?>
                                        <?php
                                        $rowId = (int) ($row['property_id'] ?? 0);
                                        $rowStatus = (string) ($row['status'] ?? '');
                                        $thumb = admin_image_url($row['primary_image'] ?? null);
                                        ?>
                                        <tr>
                                            <td><?php echo e((string) $rowId); ?></td>
                                            <td>
                                                <div class="property-cell">
                                                    <?php if ($thumb !== null): ?>
                                                        <img class="property-thumb" src="<?php echo e($thumb); ?>" alt="<?php echo e((string) ($row['title'] ?? 'Property')); ?>" width="56" height="42" loading="lazy">
                                                    <?php else: ?>
                                                        <div class="property-thumb property-thumb-placeholder" aria-hidden="true">No image</div>
                                                    <?php endif; ?>
                                                    <span class="property-cell-title"><?php echo e((string) ($row['title'] ?? '')); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div><?php echo e((string) ($row['lister_name'] ?? '')); ?></div>
                                                <div class="text-muted small"><?php echo e((string) ($row['lister_email'] ?? '')); ?></div>
                                                <div class="text-muted small"><?php echo e((string) ($row['lister_role'] ?? '')); ?></div>
                                            </td>
                                            <td><?php echo e((string) ($row['district'] ?? '')); ?></td>
                                            <td><?php echo e((string) ($row['property_type'] ?? '')); ?></td>
                                            <td><?php echo e(admin_format_lkr($row['asking_price_lkr'] ?? 0)); ?></td>
                                            <td><?php echo e(admin_format_joined($row['updated_at'] ?? null)); ?></td>
                                            <td><span class="status-pill <?php echo e(admin_property_status_badge_class($rowStatus)); ?>"><?php echo e($rowStatus); ?></span></td>
                                            <td>
                                                <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(url('admin/property_view.php?id=' . $rowId)); ?>">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <nav class="admin-pagination" aria-label="Sold property pagination">
                                <ul class="pagination mb-0">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item<?php echo $i === $page ? ' active' : ''; ?>">
                                            <a class="page-link" href="<?php echo e(url('admin/sold_history.php') . admin_build_query($queryBase + ['page' => $i])); ?>"><?php echo e((string) $i); ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
