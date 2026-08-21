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
$old = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'role' => 'BUYER',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $old['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
        $old['email'] = trim((string) ($_POST['email'] ?? ''));
        $old['phone'] = trim((string) ($_POST['phone'] ?? ''));
        $old['role'] = strtoupper(trim((string) ($_POST['role'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($old['full_name'] === '') {
            $errors[] = 'Full name is required.';
        } elseif (mb_strlen($old['full_name']) > 150) {
            $errors[] = 'Full name must be 150 characters or fewer.';
        }

        if ($old['email'] === '') {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (mb_strlen($old['email']) > 191) {
            $errors[] = 'Email must be 191 characters or fewer.';
        }

        if ($old['phone'] !== '') {
            if (mb_strlen($old['phone']) > 30) {
                $errors[] = 'Phone number must be 30 characters or fewer.';
            } elseif (!preg_match('/^[0-9+\-\s()]{7,30}$/', $old['phone'])) {
                $errors[] = 'Please enter a valid phone number.';
            }
        }

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Password and confirm password do not match.';
        }

        if (!in_array($old['role'], ['BUYER', 'SELLER'], true)) {
            $errors[] = 'Please select a valid account type.';
            $old['role'] = 'BUYER';
        }

        if ($errors === []) {
            try {
                $pdo = db();

                $check = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
                $check->execute([$old['email']]);

                if ($check->fetch()) {
                    $errors[] = 'An account with this email already exists.';
                } else {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                    $insert = $pdo->prepare(
                        'INSERT INTO users (full_name, email, password_hash, phone, role, status)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $insert->execute([
                        $old['full_name'],
                        $old['email'],
                        $passwordHash,
                        $old['phone'] !== '' ? $old['phone'] : null,
                        $old['role'],
                        'ACTIVE',
                    ]);

                    flash_set('success', 'Registration successful. Please log in.');
                    redirect('auth/login.php');
                }
            } catch (Throwable $e) {
                $errors[] = 'Unable to complete registration right now. Please try again later.';
            }
        }
    }
}

$page_title = 'Register | RealEstateAI';
$page_description = 'Create a RealEstateAI buyer or seller account.';
$flash = flash_get();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="auth-page">
    <div class="container">
        <div class="auth-card">
            <h1 class="auth-title">Create your account</h1>
            <p class="auth-subtitle">Join RealEstateAI as a buyer or seller. Admin accounts are not available through public registration.</p>

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
                    <label for="full_name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" required maxlength="150" value="<?php echo e($old['full_name']); ?>">
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required maxlength="191" value="<?php echo e($old['email']); ?>">
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Phone <span class="text-muted">(optional)</span></label>
                    <input type="text" class="form-control" id="phone" name="phone" maxlength="30" value="<?php echo e($old['phone']); ?>" placeholder="0771234567">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required minlength="8" autocomplete="new-password">
                    <div class="form-text">At least 8 characters.</div>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
                </div>

                <div class="mb-4">
                    <label for="role" class="form-label">Account Type</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="BUYER"<?php echo $old['role'] === 'BUYER' ? ' selected' : ''; ?>>Buyer</option>
                        <option value="SELLER"<?php echo $old['role'] === 'SELLER' ? ' selected' : ''; ?>>Seller</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-auth w-100">Register</button>
            </form>

            <p class="auth-switch">
                Already have an account?
                <a href="<?php echo e(url('auth/login.php')); ?>">Log in</a>
            </p>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
