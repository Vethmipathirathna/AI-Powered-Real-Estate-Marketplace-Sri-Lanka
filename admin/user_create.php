<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_helpers.php';

require_role('ADMIN');

$errors = [];
$old = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'role' => 'BUYER',
    'status' => 'ACTIVE',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            'require_password' => true,
            'password' => $password,
            'confirm_password' => $confirmPassword,
        ]);

        if ($errors === []) {
            try {
                $pdo = db();

                if (admin_email_taken($pdo, $old['email'])) {
                    $errors[] = 'An account with this email already exists.';
                } else {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare(
                        'INSERT INTO users (full_name, email, password_hash, phone, role, status)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $old['full_name'],
                        $old['email'],
                        $passwordHash,
                        $old['phone'] !== '' ? $old['phone'] : null,
                        $old['role'],
                        $old['status'],
                    ]);

                    flash_set('success', 'User created successfully.');
                    redirect('admin/users.php');
                }
            } catch (Throwable $e) {
                $errors[] = 'Unable to create the user right now. Please try again later.';
            }
        }
    }
}

$flash = flash_get();
$page_title = 'Create User | RealEstateAI';
$page_description = 'Create a new RealEstateAI user account.';
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
            <span>Create User</span>
        </nav>

        <div class="admin-hero">
            <p class="admin-eyebrow">Administration</p>
            <h1 class="admin-title">Create User</h1>
            <p class="admin-welcome">Add a buyer, seller or admin account.</p>
        </div>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

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

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="col-md-6">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
                    </div>
                </div>
                <p class="form-text mb-3">Password must be at least 8 characters.</p>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <?php foreach (admin_allowed_roles() as $role): ?>
                                <option value="<?php echo e($role); ?>"<?php echo $old['role'] === $role ? ' selected' : ''; ?>><?php echo e($role); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <?php foreach (admin_allowed_statuses() as $status): ?>
                                <option value="<?php echo e($status); ?>"<?php echo $old['status'] === $status ? ' selected' : ''; ?>><?php echo e($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-auth">Create User</button>
                    <a class="btn btn-outline-secondary" href="<?php echo e(url('admin/users.php')); ?>">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
