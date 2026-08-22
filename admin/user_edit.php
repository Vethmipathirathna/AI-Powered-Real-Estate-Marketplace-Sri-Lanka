<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_helpers.php';

require_role('ADMIN');

$current = current_user();
$errors = [];
$userId = (int) ($_GET['id'] ?? $_POST['user_id'] ?? 0);
$target = null;
$notFound = false;

try {
    $pdo = db();
    if ($userId <= 0) {
        $notFound = true;
    } else {
        $target = admin_find_user($pdo, $userId);
        if ($target === null) {
            $notFound = true;
        }
    }
} catch (Throwable $e) {
    $errors[] = 'Unable to load this user right now. Please try again later.';
}

$old = [
    'full_name' => (string) ($target['full_name'] ?? ''),
    'email' => (string) ($target['email'] ?? ''),
    'phone' => (string) ($target['phone'] ?? ''),
    'role' => (string) ($target['role'] ?? 'BUYER'),
    'status' => (string) ($target['status'] ?? 'ACTIVE'),
];

$isSelf = !$notFound && $target !== null && (int) $target['user_id'] === (int) ($current['user_id'] ?? 0);

if (!$notFound && $target !== null && $_SERVER['REQUEST_METHOD'] === 'POST' && $errors === []) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $old['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
        $old['email'] = admin_normalize_email((string) ($_POST['email'] ?? ''));
        $old['phone'] = trim((string) ($_POST['phone'] ?? ''));
        $old['role'] = strtoupper(trim((string) ($_POST['role'] ?? '')));
        $old['status'] = strtoupper(trim((string) ($_POST['status'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        $errors = admin_validate_user_fields($old, [
            'require_password' => false,
            'allow_blank_password' => true,
            'password' => $password,
            'confirm_password' => $confirmPassword,
        ]);

        if ($isSelf && $old['role'] !== 'ADMIN') {
            $errors[] = 'You cannot change your own role away from ADMIN.';
            $old['role'] = 'ADMIN';
        }

        if ($isSelf && $old['status'] !== 'ACTIVE') {
            $errors[] = 'You cannot deactivate your own admin account.';
            $old['status'] = 'ACTIVE';
        }

        if ($errors === []) {
            try {
                $pdo = db();

                if (admin_email_taken($pdo, $old['email'], $userId)) {
                    $errors[] = 'An account with this email already exists.';
                } else {
                    $originalRole = strtoupper((string) $target['role']);
                    $originalStatus = strtoupper((string) $target['status']);

                    $wouldLoseAdmin =
                        $originalRole === 'ADMIN'
                        && $originalStatus === 'ACTIVE'
                        && (
                            $old['role'] !== 'ADMIN'
                            || $old['status'] !== 'ACTIVE'
                        )
                        && admin_count_active_admins($pdo, $userId) < 1;

                    if ($wouldLoseAdmin) {
                        $errors[] = 'The system must keep at least one active admin account.';
                    } else {
                        if ($password !== '') {
                            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare(
                                'UPDATE users
                                 SET full_name = ?, email = ?, phone = ?, role = ?, status = ?, password_hash = ?
                                 WHERE user_id = ?'
                            );
                            $stmt->execute([
                                $old['full_name'],
                                $old['email'],
                                $old['phone'] !== '' ? $old['phone'] : null,
                                $old['role'],
                                $old['status'],
                                $passwordHash,
                                $userId,
                            ]);
                        } else {
                            $stmt = $pdo->prepare(
                                'UPDATE users
                                 SET full_name = ?, email = ?, phone = ?, role = ?, status = ?
                                 WHERE user_id = ?'
                            );
                            $stmt->execute([
                                $old['full_name'],
                                $old['email'],
                                $old['phone'] !== '' ? $old['phone'] : null,
                                $old['role'],
                                $old['status'],
                                $userId,
                            ]);
                        }

                        // Keep current session name in sync if editing self.
                        if ($isSelf) {
                            $_SESSION['full_name'] = $old['full_name'];
                            $_SESSION['role'] = $old['role'];
                        }

                        flash_set('success', 'User updated successfully.');
                        redirect('admin/users.php');
                    }
                }
            } catch (Throwable $e) {
                $errors[] = 'Unable to update the user right now. Please try again later.';
            }
        }
    }
}

$flash = flash_get();
$page_title = 'Edit User | RealEstateAI';
$page_description = 'Edit a RealEstateAI user account.';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="admin-dashboard">
    <div class="container">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo e(url('admin/index.php')); ?>">Admin Dashboard</a>
            <span aria-hidden="true">/</span>
            <a href="<?php echo e(url('admin/users.php')); ?>">User Management</a>
            <span aria-hidden="true">/</span>
            <span>Edit User</span>
        </nav>

        <div class="admin-hero">
            <p class="admin-eyebrow">Administration</p>
            <h1 class="admin-title">Edit User</h1>
            <p class="admin-welcome">
                <?php if ($notFound): ?>
                    The requested user could not be found.
                <?php else: ?>
                    Update account details for <?php echo e($old['full_name']); ?>.
                <?php endif; ?>
            </p>
        </div>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($notFound): ?>
            <div class="alert alert-warning" role="alert">Invalid user ID. Please return to User Management and try again.</div>
            <a class="btn btn-auth" href="<?php echo e(url('admin/users.php')); ?>">Back to User Management</a>
        <?php else: ?>
            <?php if ($errors !== []): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="admin-form-card">
                <form method="post" action="" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="user_id" value="<?php echo e((string) $userId); ?>">

                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required maxlength="150" value="<?php echo e($old['full_name']); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required maxlength="191" value="<?php echo e($old['email']); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone <span class="text-muted">(optional)</span></label>
                        <input type="text" class="form-control" id="phone" name="phone" maxlength="30" value="<?php echo e($old['phone']); ?>">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required<?php echo $isSelf ? ' disabled' : ''; ?>>
                                <?php foreach (admin_allowed_roles() as $role): ?>
                                    <option value="<?php echo e($role); ?>"<?php echo $old['role'] === $role ? ' selected' : ''; ?>><?php echo e($role); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($isSelf): ?>
                                <input type="hidden" name="role" value="ADMIN">
                                <div class="form-text">You cannot change your own role away from ADMIN.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required<?php echo $isSelf ? ' disabled' : ''; ?>>
                                <?php foreach (admin_allowed_statuses() as $status): ?>
                                    <option value="<?php echo e($status); ?>"<?php echo $old['status'] === $status ? ' selected' : ''; ?>><?php echo e($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($isSelf): ?>
                                <input type="hidden" name="status" value="ACTIVE">
                                <div class="form-text">You cannot deactivate your own admin account.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">New Password <span class="text-muted">(optional)</span></label>
                            <input type="password" class="form-control" id="password" name="password" minlength="8" autocomplete="new-password">
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password">
                        </div>
                    </div>
                    <p class="form-text mb-4">Leave blank to keep the current password. Existing password hashes are never shown.</p>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-auth">Save Changes</button>
                        <a class="btn btn-outline-secondary" href="<?php echo e(url('admin/users.php')); ?>">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
