<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/property_ownership.php';
require_once __DIR__ . '/_helpers.php';

require_role('ADMIN');

$flash = null;
$loadError = null;
$perPage = 10;

$search = trim((string) ($_GET['q'] ?? ''));
$typeFilter = strtoupper(trim((string) ($_GET['type'] ?? 'ALL')));
$statusFilter = strtoupper(trim((string) ($_GET['status'] ?? 'ALL')));
$page = max(1, (int) ($_GET['page'] ?? 1));

if (!in_array($typeFilter, ['ALL', ...admin_allowed_property_types()], true)) {
    $typeFilter = 'ALL';
}
if (!in_array($statusFilter, ['ALL', ...admin_allowed_property_statuses()], true)) {
    $statusFilter = 'ALL';
}

// Status update via POST only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $redirSearch = trim((string) ($_POST['q'] ?? ''));
    $redirType = strtoupper(trim((string) ($_POST['type'] ?? 'ALL')));
    $redirStatus = strtoupper(trim((string) ($_POST['status_filter'] ?? 'ALL')));
    $redirPage = max(1, (int) ($_POST['page'] ?? 1));

    if (!in_array($redirType, ['ALL', ...admin_allowed_property_types()], true)) {
        $redirType = 'ALL';
    }
    if (!in_array($redirStatus, ['ALL', ...admin_allowed_property_statuses()], true)) {
        $redirStatus = 'ALL';
    }

    $redirQuery = admin_build_query([
        'q' => $redirSearch !== '' ? $redirSearch : null,
        'type' => $redirType !== 'ALL' ? $redirType : null,
        'status' => $redirStatus !== 'ALL' ? $redirStatus : null,
        'page' => $redirPage > 1 ? $redirPage : null,
    ]);

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash_set('error', 'Invalid request. Please try again.');
        redirect('admin/properties.php' . $redirQuery);
    }

    $propertyId = (int) ($_POST['property_id'] ?? 0);
    $newStatus = strtoupper(trim((string) ($_POST['new_status'] ?? '')));

    try {
        $pdo = db();
        $property = $propertyId > 0 ? admin_find_property($pdo, $propertyId) : null;

        if ($property === null) {
            flash_set('error', 'Property not found.');
        } elseif (!in_array($newStatus, admin_allowed_property_statuses(), true)) {
            flash_set('error', 'Invalid property status.');
        } else {
            $stmt = $pdo->prepare('UPDATE properties SET status = ? WHERE property_id = ?');
            $stmt->execute([$newStatus, $propertyId]);
            flash_set('success', 'Property status updated to ' . $newStatus . '.');
        }
    } catch (Throwable $e) {
        flash_set('error', 'Unable to update property status right now. Please try again later.');
    }

    redirect('admin/properties.php' . $redirQuery);
}

$properties = [];
$totalProperties = 0;
$totalPages = 1;

try {
    $pdo = db();
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(p.title LIKE ? OR p.district LIKE ? OR p.area LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
        $like = '%' . $search . '%';
        $params = array_merge($params, [$like, $like, $like, $like, $like]);
    }

    if ($typeFilter !== 'ALL') {
        $where[] = 'p.property_type = ?';
        $params[] = $typeFilter;
    }

    if ($statusFilter !== 'ALL') {
        $where[] = 'p.status = ?';
        $params[] = $statusFilter;
    }

    $whereSql = $where === [] ? '' : ('WHERE ' . implode(' AND ', $where));

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
                       p.status, p.created_at,
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
                ORDER BY p.created_at DESC, p.property_id DESC
                LIMIT {$perPage} OFFSET {$offset}";
    $listStmt = $pdo->prepare($listSql);
    $listStmt->execute($params);
    $properties = $listStmt->fetchAll();
} catch (Throwable $e) {
    $loadError = 'Unable to load properties right now. Please try again later.';
}

$flash = flash_get();
$page_title = 'Manage Properties | RealEstateAI';
$page_description = 'Admin property listing moderation for RealEstateAI.';

$queryBase = [
    'q' => $search !== '' ? $search : null,
    'type' => $typeFilter !== 'ALL' ? $typeFilter : null,
    'status' => $statusFilter !== 'ALL' ? $statusFilter : null,
];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="admin-dashboard">
    <div class="container">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo e(url('admin/index.php')); ?>">Admin Dashboard</a>
            <span aria-hidden="true">/</span>
            <span>Property Management</span>
        </nav>

        <div class="admin-hero">
            <p class="admin-eyebrow">Administration</p>
            <h1 class="admin-title">Property Management</h1>
            <p class="admin-welcome">Review listings, inspect details and moderate publication status.</p>
        </div>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($loadError); ?></div>
        <?php else: ?>
            <section class="admin-section">
                <form class="filter-panel row g-3 align-items-end" method="get" action="">
                    <div class="col-lg-5 col-md-6">
                        <label for="q" class="form-label">Search</label>
                        <input type="search" class="form-control" id="q" name="q" value="<?php echo e($search); ?>" placeholder="Title, district, area, lister name or email">
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
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="ALL"<?php echo $statusFilter === 'ALL' ? ' selected' : ''; ?>>ALL</option>
                            <?php foreach (admin_allowed_property_statuses() as $status): ?>
                                <option value="<?php echo e($status); ?>"<?php echo $statusFilter === $status ? ' selected' : ''; ?>><?php echo e($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-12 d-flex gap-2">
                        <button type="submit" class="btn btn-auth flex-grow-1">Apply</button>
                        <a class="btn btn-outline-secondary" href="<?php echo e(url('admin/properties.php')); ?>">Reset</a>
                    </div>
                </form>
            </section>

            <section class="admin-section">
                <div class="table-panel">
                    <p class="mb-3 text-muted"><?php echo e((string) $totalProperties); ?> listing<?php echo $totalProperties === 1 ? '' : 's'; ?> found</p>

                    <?php if ($properties === []): ?>
                        <p class="empty-state mb-0">No property listings found.</p>
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
                                        <th scope="col">Status</th>
                                        <th scope="col">Created</th>
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
                                                        <img class="property-thumb" src="<?php echo e($thumb); ?>" alt="" width="56" height="42" loading="lazy">
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
                                            <td><span class="status-pill <?php echo e(admin_property_status_badge_class($rowStatus)); ?>"><?php echo e($rowStatus); ?></span></td>
                                            <td><?php echo e(admin_format_joined($row['created_at'] ?? null)); ?></td>
                                            <td>
                                                <div class="action-stack">
                                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(url('admin/property_view.php?id=' . $rowId)); ?>">View</a>
                                                    <form method="post" action="" class="status-inline-form">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="property_id" value="<?php echo e((string) $rowId); ?>">
                                                        <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?php echo e($search); ?>"><?php endif; ?>
                                                        <?php if ($typeFilter !== 'ALL'): ?><input type="hidden" name="type" value="<?php echo e($typeFilter); ?>"><?php endif; ?>
                                                        <?php if ($statusFilter !== 'ALL'): ?><input type="hidden" name="status_filter" value="<?php echo e($statusFilter); ?>"><?php endif; ?>
                                                        <?php if ($page > 1): ?><input type="hidden" name="page" value="<?php echo e((string) $page); ?>"><?php endif; ?>
                                                        <label class="visually-hidden" for="status-<?php echo e((string) $rowId); ?>">Change status</label>
                                                        <select class="form-select form-select-sm" id="status-<?php echo e((string) $rowId); ?>" name="new_status">
                                                            <?php foreach (admin_allowed_property_statuses() as $status): ?>
                                                                <option value="<?php echo e($status); ?>"<?php echo $rowStatus === $status ? ' selected' : ''; ?>><?php echo e($status); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" class="btn btn-sm btn-auth">Update</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <nav class="admin-pagination" aria-label="Property pagination">
                                <ul class="pagination mb-0">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item<?php echo $i === $page ? ' active' : ''; ?>">
                                            <a class="page-link" href="<?php echo e(url('admin/properties.php') . admin_build_query($queryBase + ['page' => $i])); ?>"><?php echo e((string) $i); ?></a>
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
