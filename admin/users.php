<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_helpers.php';

require_role('ADMIN');

$current = current_user();
$flash = null;
$loadError = null;
$perPage = 10;

$search = trim((string) ($_GET['q'] ?? ''));
$roleFilter = strtoupper(trim((string) ($_GET['role'] ?? 'ALL')));
$statusFilter = strtoupper(trim((string) ($_GET['status'] ?? 'ALL')));
$page = max(1, (int) ($_GET['page'] ?? 1));

if (!in_array($roleFilter, ['ALL', ...admin_allowed_roles()], true)) {
    $roleFilter = 'ALL';
}
if (!in_array($statusFilter, ['ALL', ...admin_allowed_statuses()], true)) {
    $statusFilter = 'ALL';
}

// Status toggle via POST only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    $redirSearch = trim((string) ($_POST['q'] ?? ''));
    $redirRole = strtoupper(trim((string) ($_POST['role'] ?? 'ALL')));
    $redirStatus = strtoupper(trim((string) ($_POST['status'] ?? 'ALL')));
    $redirPage = max(1, (int) ($_POST['page'] ?? 1));

    if (!in_array($redirRole, ['ALL', ...admin_allowed_roles()], true)) {
        $redirRole = 'ALL';
    }
    if (!in_array($redirStatus, ['ALL', ...admin_allowed_statuses()], true)) {
        $redirStatus = 'ALL';
    }

    $redirQuery = admin_build_query([
        'q' => $redirSearch !== '' ? $redirSearch : null,
        'role' => $redirRole !== 'ALL' ? $redirRole : null,
        'status' => $redirStatus !== 'ALL' ? $redirStatus : null,
        'page' => $redirPage > 1 ? $redirPage : null,
    ]);

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash_set('error', 'Invalid request. Please try again.');
        redirect('admin/users.php' . $redirQuery);
    }

    $targetId = (int) ($_POST['user_id'] ?? 0);

    try {
        $pdo = db();
        $target = $targetId > 0 ? admin_find_user($pdo, $targetId) : null;

        if ($target === null) {
            flash_set('error', 'User not found.');
        } else {
            $isSelf = (int) $target['user_id'] === (int) ($current['user_id'] ?? 0);
            $currentStatus = strtoupper((string) $target['status']);
            $currentRole = strtoupper((string) $target['role']);
            $newStatus = $currentStatus === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';

            if ($isSelf && $newStatus === 'INACTIVE') {
                flash_set('error', 'You cannot deactivate your own admin account.');
            } elseif (
                $currentRole === 'ADMIN'
                && $currentStatus === 'ACTIVE'
                && $newStatus === 'INACTIVE'
                && admin_count_active_admins($pdo) <= 1
            ) {
                flash_set('error', 'The system must keep at least one active admin account.');
            } else {
                $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE user_id = ?');
                $stmt->execute([$newStatus, $targetId]);
                flash_set(
                    'success',
                    $newStatus === 'ACTIVE'
                        ? 'User activated successfully.'
                        : 'User deactivated successfully.'
                );
            }
        }
    } catch (Throwable $e) {
        flash_set('error', 'Unable to update user status right now. Please try again later.');
    }

    redirect('admin/users.php' . $redirQuery);
}

$users = [];
$totalUsers = 0;
$totalPages = 1;

try {
    $pdo = db();
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(full_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($roleFilter !== 'ALL') {
        $where[] = 'role = ?';
        $params[] = $roleFilter;
    }

    if ($statusFilter !== 'ALL') {
        $where[] = 'status = ?';
        $params[] = $statusFilter;
    }

    $whereSql = $where === [] ? '' : ('WHERE ' . implode(' AND ', $where));

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users {$whereSql}");
    $countStmt->execute($params);
    $totalUsers = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalUsers / $perPage));

    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;

    $listSql = "SELECT user_id, full_name, email, phone, role, status, created_at
                FROM users
                {$whereSql}
                ORDER BY created_at DESC, user_id DESC
                LIMIT {$perPage} OFFSET {$offset}";
    $listStmt = $pdo->prepare($listSql);
    $listStmt->execute($params);
    $users = $listStmt->fetchAll();
} catch (Throwable $e) {
    $loadError = 'Unable to load users right now. Please try again later.';
}

$flash = flash_get();
$page_title = 'Manage Users | RealEstateAI';
$page_description = 'Admin user management for RealEstateAI accounts.';

$queryBase = [
    'q' => $search !== '' ? $search : null,
    'role' => $roleFilter !== 'ALL' ? $roleFilter : null,
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
            <span>User Management</span>
        </nav>

        <div class="admin-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <p class="admin-eyebrow">Administration</p>
                <h1 class="admin-title">User Management</h1>
                <p class="admin-welcome">Search, filter and manage RealEstateAI accounts.</p>
            </div>
            <a class="btn btn-auth" href="<?php echo e(url('admin/user_create.php')); ?>">Create User</a>
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
                        <input type="search" class="form-control" id="q" name="q" value="<?php echo e($search); ?>" placeholder="Name, email or phone">
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role">
                            <?php foreach (['ALL', 'BUYER', 'SELLER', 'ADMIN'] as $option): ?>
                                <option value="<?php echo e($option); ?>"<?php echo $roleFilter === $option ? ' selected' : ''; ?>><?php echo e($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <?php foreach (['ALL', 'ACTIVE', 'INACTIVE'] as $option): ?>
                                <option value="<?php echo e($option); ?>"<?php echo $statusFilter === $option ? ' selected' : ''; ?>><?php echo e($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-12 d-flex gap-2">
                        <button type="submit" class="btn btn-auth flex-grow-1">Apply</button>
                        <a class="btn btn-outline-secondary" href="<?php echo e(url('admin/users.php')); ?>">Reset</a>
                    </div>
                </form>
            </section>

            <section class="admin-section">
                <div class="table-panel">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <p class="mb-0 text-muted"><?php echo e((string) $totalUsers); ?> user<?php echo $totalUsers === 1 ? '' : 's'; ?> found</p>
                    </div>

                    <?php if ($users === []): ?>
                        <p class="empty-state mb-0">No users match your current search or filters.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table admin-table mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">ID</th>
                                        <th scope="col">Full Name</th>
                                        <th scope="col">Email</th>
                                        <th scope="col">Phone</th>
                                        <th scope="col">Role</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Joined</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $row): ?>
                                        <?php
                                        $rowId = (int) ($row['user_id'] ?? 0);
                                        $rowRole = (string) ($row['role'] ?? '');
                                        $rowStatus = (string) ($row['status'] ?? '');
                                        $isSelf = $rowId === (int) ($current['user_id'] ?? 0);
                                        ?>
                                        <tr>
                                            <td><?php echo e((string) $rowId); ?></td>
                                            <td>
                                                <?php echo e((string) ($row['full_name'] ?? '')); ?>
                                                <?php if ($isSelf): ?>
                                                    <span class="text-muted">(you)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo e((string) ($row['email'] ?? '')); ?></td>
                                            <td><?php echo e((string) (($row['phone'] ?? '') !== '' ? $row['phone'] : '—')); ?></td>
                                            <td><span class="status-pill <?php echo e(admin_role_badge_class($rowRole)); ?>"><?php echo e($rowRole); ?></span></td>
                                            <td><span class="status-pill <?php echo e(admin_status_badge_class($rowStatus)); ?>"><?php echo e($rowStatus); ?></span></td>
                                            <td><?php echo e(admin_format_joined($row['created_at'] ?? null)); ?></td>
                                            <td>
                                                <div class="action-stack">
                                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(url('admin/user_edit.php?id=' . $rowId)); ?>">Edit</a>
                                                    <form method="post" action="" class="d-inline">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="user_id" value="<?php echo e((string) $rowId); ?>">
                                                        <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?php echo e($search); ?>"><?php endif; ?>
                                                        <?php if ($roleFilter !== 'ALL'): ?><input type="hidden" name="role" value="<?php echo e($roleFilter); ?>"><?php endif; ?>
                                                        <?php if ($statusFilter !== 'ALL'): ?><input type="hidden" name="status" value="<?php echo e($statusFilter); ?>"><?php endif; ?>
                                                        <?php if ($page > 1): ?><input type="hidden" name="page" value="<?php echo e((string) $page); ?>"><?php endif; ?>
                                                        <button
                                                            type="submit"
                                                            class="btn btn-sm <?php echo $rowStatus === 'ACTIVE' ? 'btn-outline-danger' : 'btn-outline-success'; ?>"
                                                            <?php echo $isSelf && $rowStatus === 'ACTIVE' ? 'disabled title="You cannot deactivate your own account"' : ''; ?>
                                                        >
                                                            <?php echo $rowStatus === 'ACTIVE' ? 'Deactivate' : 'Activate'; ?>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <nav class="admin-pagination" aria-label="User pagination">
                                <ul class="pagination mb-0">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item<?php echo $i === $page ? ' active' : ''; ?>">
                                            <a class="page-link" href="<?php echo e(url('admin/users.php') . admin_build_query($queryBase + ['page' => $i])); ?>"><?php echo e((string) $i); ?></a>
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
