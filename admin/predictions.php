<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ai_helpers.php';
require_once __DIR__ . '/_helpers.php';

require_role('ADMIN');

$flash = flash_get();
$loadError = null;
$perPage = 10;

$search = trim((string) ($_GET['q'] ?? ''));
$districtFilter = trim((string) ($_GET['district'] ?? 'ALL'));
$page = max(1, (int) ($_GET['page'] ?? 1));

$districts = ai_model_districts();
if ($districtFilter !== 'ALL' && !in_array($districtFilter, $districts, true)) {
    $districtFilter = 'ALL';
}

$predictions = [];
$totalRows = 0;
$totalPages = 1;

try {
    $pdo = db();
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR p.district LIKE ? OR p.area LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($districtFilter !== 'ALL') {
        $where[] = 'p.district = ?';
        $params[] = $districtFilter;
    }

    $whereSql = $where === [] ? '' : ('WHERE ' . implode(' AND ', $where));

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM ai_predictions p
         INNER JOIN users u ON u.user_id = p.user_id
         {$whereSql}"
    );
    $countStmt->execute($params);
    $totalRows = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRows / $perPage));

    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;

    $listSql = "SELECT p.prediction_id, p.district, p.area, p.perch, p.bedrooms, p.bathrooms,
                       p.floors, p.year_built, p.predicted_price_lkr, p.created_at,
                       u.full_name AS user_full_name, u.email AS user_email
                FROM ai_predictions p
                INNER JOIN users u ON u.user_id = p.user_id
                {$whereSql}
                ORDER BY p.created_at DESC, p.prediction_id DESC
                LIMIT {$perPage} OFFSET {$offset}";
    $listStmt = $pdo->prepare($listSql);
    $listStmt->execute($params);
    $predictions = $listStmt->fetchAll();
} catch (Throwable $e) {
    $loadError = 'Unable to load AI predictions right now. Please try again later.';
}

$page_title = 'AI Predictions | RealEstateAI';
$page_description = 'Admin monitoring of AI house price predictions.';

$queryBase = [
    'q' => $search !== '' ? $search : null,
    'district' => $districtFilter !== 'ALL' ? $districtFilter : null,
];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="admin-dashboard">
    <div class="container">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo e(url('admin/index.php')); ?>">Admin Dashboard</a>
            <span aria-hidden="true">/</span>
            <span>AI Predictions</span>
        </nav>

        <div class="admin-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <p class="admin-eyebrow">Administration</p>
                <h1 class="admin-title">AI Predictions</h1>
                <p class="admin-welcome mb-0">Monitor house-price estimates recorded across all users.</p>
            </div>
            <a class="btn btn-outline-secondary" href="<?php echo e(url('admin/index.php')); ?>">Back to Dashboard</a>
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
                    <div class="col-lg-6 col-md-6">
                        <label for="q" class="form-label">Search</label>
                        <input
                            type="search"
                            class="form-control"
                            id="q"
                            name="q"
                            value="<?php echo e($search); ?>"
                            placeholder="User name, email, district or area"
                        >
                    </div>
                    <div class="col-lg-3 col-md-3">
                        <label for="district" class="form-label">District</label>
                        <select class="form-select" id="district" name="district">
                            <option value="ALL"<?php echo $districtFilter === 'ALL' ? ' selected' : ''; ?>>All districts</option>
                            <?php foreach ($districts as $option): ?>
                                <option value="<?php echo e($option); ?>"<?php echo $districtFilter === $option ? ' selected' : ''; ?>>
                                    <?php echo e($option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-auth flex-grow-1">Apply</button>
                        <a class="btn btn-outline-secondary" href="<?php echo e(url('admin/predictions.php')); ?>">Reset</a>
                    </div>
                </form>
            </section>

            <section class="admin-section">
                <div class="table-panel">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <p class="mb-0 text-muted">
                            <?php echo e((string) $totalRows); ?> prediction<?php echo $totalRows === 1 ? '' : 's'; ?> found
                        </p>
                    </div>

                    <?php if ($predictions === []): ?>
                        <p class="empty-state mb-0">No AI predictions match your current search or filters.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table admin-table mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Date</th>
                                        <th scope="col">User</th>
                                        <th scope="col">District / Area</th>
                                        <th scope="col">Property Summary</th>
                                        <th scope="col">Estimated Price</th>
                                        <th scope="col">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($predictions as $row): ?>
                                        <?php $rowId = (int) ($row['prediction_id'] ?? 0); ?>
                                        <tr>
                                            <td><?php echo e(ai_format_prediction_datetime($row['created_at'] ?? null)); ?></td>
                                            <td>
                                                <div class="fw-semibold"><?php echo e((string) ($row['user_full_name'] ?? '')); ?></div>
                                                <div class="text-muted small"><?php echo e((string) ($row['user_email'] ?? '')); ?></div>
                                            </td>
                                            <td>
                                                <div><?php echo e((string) ($row['district'] ?? '—')); ?></div>
                                                <div class="text-muted small"><?php echo e((string) (($row['area'] ?? '') !== '' ? $row['area'] : '—')); ?></div>
                                            </td>
                                            <td><?php echo e(ai_property_summary($row)); ?></td>
                                            <td class="fw-semibold"><?php echo e(admin_format_lkr($row['predicted_price_lkr'] ?? 0)); ?></td>
                                            <td>
                                                <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(url('admin/prediction_view.php?id=' . $rowId)); ?>">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <nav class="admin-pagination" aria-label="AI prediction pagination">
                                <ul class="pagination mb-0">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item<?php echo $i === $page ? ' active' : ''; ?>">
                                            <a class="page-link" href="<?php echo e(url('admin/predictions.php') . admin_build_query($queryBase + ['page' => $i])); ?>">
                                                <?php echo e((string) $i); ?>
                                            </a>
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
