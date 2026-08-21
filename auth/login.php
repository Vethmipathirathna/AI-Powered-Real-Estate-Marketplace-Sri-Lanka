<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

start_app_session();

if (is_logged_in()) {
    $user = current_user();
    redirect(dashboard_path_for_role($user['role'] ?? 'BUYER'));
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        // Remember-me UI only — persistent token auth is not implemented yet.

        if ($email === '' || $password === '') {
            $errors[] = 'Email and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            try {
                $pdo = db();
                $stmt = $pdo->prepare(
                    'SELECT user_id, full_name, email, password_hash, role, status
                     FROM users
                     WHERE email = ?
                     LIMIT 1'
                );
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                $valid = is_array($user)
                    && isset($user['password_hash'])
                    && password_verify($password, (string) $user['password_hash']);

                if (!$valid) {
                    $errors[] = 'Invalid email or password.';
                } elseif (($user['status'] ?? '') !== 'ACTIVE') {
                    $errors[] = 'This account is inactive. Please contact support.';
                } else {
                    login_user([
                        'user_id' => (int) $user['user_id'],
                        'full_name' => (string) $user['full_name'],
                        'role' => (string) $user['role'],
                    ]);

                    redirect(dashboard_path_for_role((string) $user['role']));
                }
            } catch (Throwable $e) {
                $errors[] = 'Unable to log in right now. Please try again later.';
            }
        }
    }
}

$page_title = 'Login | RealEstateAI';
$page_description = 'Log in to your RealEstateAI account.';
$flash = flash_get();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="auth-page">
    <div class="container">
        <div class="auth-card">
            <h1 class="auth-title">Welcome back</h1>
            <p class="auth-subtitle">Log in to continue to your RealEstateAI dashboard.</p>

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

            <form method="post" action="" novalidate>
                <?php echo csrf_field(); ?>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required maxlength="191" value="<?php echo e($email); ?>" autocomplete="username">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                </div>

                <div class="mb-4 form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="remember_me" name="remember_me" disabled>
                    <label class="form-check-label" for="remember_me">
                        Remember me <span class="text-muted">(coming soon)</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-auth w-100">Login</button>
            </form>

            <p class="auth-switch">
                New to RealEstateAI?
                <a href="<?php echo e(url('auth/register.php')); ?>">Create an account</a>
            </p>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
