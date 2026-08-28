<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/profile_helpers.php';
require_once __DIR__ . '/../admin/_helpers.php';

require_login();

$sessionUser = current_user();
$userId = (int) ($sessionUser['user_id'] ?? 0);
$role = strtoupper((string) ($sessionUser['role'] ?? 'BUYER'));

$errors = [];
$passwordErrors = [];
$photoErrors = [];
$profile = null;
$loadError = null;

$form = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
];

try {
    $pdo = db();
    $profile = profile_find_user($pdo, $userId);

    if ($profile === null) {
        $loadError = 'Your profile could not be found. Please contact support.';
    } else {
        $form = [
            'full_name' => (string) ($profile['full_name'] ?? ''),
            'email' => (string) ($profile['email'] ?? ''),
            'phone' => (string) ($profile['phone'] ?? ''),
        ];
    }
} catch (Throwable $e) {
    $loadError = 'Unable to load your profile right now. Please try again later.';
}

if ($profile !== null && $_SERVER['REQUEST_METHOD'] === 'POST' && $loadError === null) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $action = trim((string) ($_POST['form_action'] ?? ''));

        if ($action === 'update_profile') {
            $form['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
            $form['email'] = profile_normalize_email((string) ($_POST['email'] ?? ''));
            $form['phone'] = trim((string) ($_POST['phone'] ?? ''));

            $errors = profile_validate_personal_fields($form);

            if ($errors === []) {
                try {
                    if (profile_email_taken($pdo, $form['email'], $userId)) {
                        $errors[] = 'An account with this email already exists.';
                    } else {
                        $stmt = $pdo->prepare(
                            'UPDATE users
                             SET full_name = ?, email = ?, phone = ?
                             WHERE user_id = ?'
                        );
                        $stmt->execute([
                            $form['full_name'],
                            $form['email'],
                            $form['phone'] !== '' ? $form['phone'] : null,
                            $userId,
                        ]);

                        login_user([
                            'user_id' => $userId,
                            'full_name' => $form['full_name'],
                            'role' => $role,
                        ]);

                        flash_set('success', 'Profile updated successfully.');
                        redirect('profile/index.php');
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Unable to update your profile right now. Please try again later.';
                }
            }
        } elseif ($action === 'change_password') {
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            try {
                $storedHash = profile_fetch_password_hash($pdo, $userId);
                $passwordErrors = profile_validate_password_change(
                    $currentPassword,
                    $newPassword,
                    $confirmPassword,
                    $storedHash
                );

                if ($passwordErrors === []) {
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

                    if ($passwordHash === false) {
                        $passwordErrors[] = 'Unable to change your password right now. Please try again later.';
                    } else {
                        $stmt = $pdo->prepare(
                            'UPDATE users SET password_hash = ? WHERE user_id = ?'
                        );
                        $stmt->execute([$passwordHash, $userId]);

                        login_user([
                            'user_id' => $userId,
                            'full_name' => (string) ($sessionUser['full_name'] ?? $form['full_name']),
                            'role' => $role,
                        ]);

                        flash_set('success', 'Password changed successfully.');
                        redirect('profile/index.php');
                    }
                }
            } catch (Throwable $e) {
                $passwordErrors[] = 'Unable to change your password right now. Please try again later.';
            }
        } elseif ($action === 'upload_photo') {
            try {
                $uploadResult = profile_store_uploaded_image($_FILES['profile_photo'] ?? []);

                if (str_starts_with($uploadResult, 'ERROR:')) {
                    $photoErrors[] = substr($uploadResult, 7);
                } else {
                    $previousImage = is_string($profile['profile_image'] ?? null)
                        ? (string) $profile['profile_image']
                        : null;

                    $stmt = $pdo->prepare(
                        'UPDATE users SET profile_image = ? WHERE user_id = ?'
                    );
                    $stmt->execute([$uploadResult, $userId]);

                    if ($stmt->rowCount() !== 1) {
                        profile_delete_image_file($uploadResult);
                        $photoErrors[] = 'Unable to save your profile photo right now. Please try again later.';
                    } else {
                        profile_delete_image_file($previousImage);
                        flash_set('success', 'Profile photo updated successfully.');
                        redirect('profile/index.php');
                    }
                }
            } catch (Throwable $e) {
                $photoErrors[] = 'Unable to upload your profile photo right now. Please try again later.';
            }
        } elseif ($action === 'remove_photo') {
            try {
                $currentImage = is_string($profile['profile_image'] ?? null)
                    ? (string) $profile['profile_image']
                    : null;

                if ($currentImage === null || trim($currentImage) === '') {
                    $photoErrors[] = 'You do not have a profile photo to remove.';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE users SET profile_image = NULL WHERE user_id = ?'
                    );
                    $stmt->execute([$userId]);

                    if ($stmt->rowCount() !== 1) {
                        $photoErrors[] = 'Unable to remove your profile photo right now. Please try again later.';
                    } else {
                        profile_delete_image_file($currentImage);
                        flash_set('success', 'Profile photo removed successfully.');
                        redirect('profile/index.php');
                    }
                }
            } catch (Throwable $e) {
                $photoErrors[] = 'Unable to remove your profile photo right now. Please try again later.';
            }
        } else {
            $errors[] = 'Invalid form submission.';
        }
    }
}

if ($profile !== null && $loadError === null && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $profile = profile_find_user($pdo, $userId) ?? $profile;
    $form = [
        'full_name' => (string) ($profile['full_name'] ?? ''),
        'email' => (string) ($profile['email'] ?? ''),
        'phone' => (string) ($profile['phone'] ?? ''),
    ];
} elseif ($profile !== null && $loadError === null && ($photoErrors !== [] || $passwordErrors !== [])) {
    $profile = profile_find_user($pdo, $userId) ?? $profile;
}

$flash = flash_get();
$page_title = 'My Profile | RealEstateAI';
$page_description = 'View and manage your RealEstateAI account profile.';
$dashboardPath = dashboard_path_for_role($role);
$dashboardLabel = profile_dashboard_label($role);
$profileImage = is_string($profile['profile_image'] ?? null) ? (string) $profile['profile_image'] : null;
$hasProfilePhoto = $profileImage !== null && trim($profileImage) !== '' && profile_is_managed_image_path($profileImage);
$displayName = (string) ($profile['full_name'] ?? $form['full_name']);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="admin-dashboard">
    <div class="container">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo e(url($dashboardPath)); ?>"><?php echo e($dashboardLabel); ?></a>
            <span aria-hidden="true">/</span>
            <span>My Profile</span>
        </nav>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($loadError); ?></div>
        <?php else: ?>
            <?php if ($photoErrors !== []): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($photoErrors as $error): ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <section class="profile-header-card summary-panel mb-4" aria-label="Profile summary">
                <div class="profile-header-inner">
                    <div class="profile-header-photo">
                        <?php echo profile_render_avatar($displayName, $profileImage, ['class' => 'profile-avatar profile-avatar-lg']); ?>
                        <div class="profile-photo-actions">
                            <form method="post" action="" enctype="multipart/form-data" class="profile-photo-upload-form">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="form_action" value="upload_photo">
                                <input
                                    type="file"
                                    class="visually-hidden"
                                    id="profile_photo"
                                    name="profile_photo"
                                    accept="image/jpeg,image/png,image/webp"
                                    onchange="this.form.submit()"
                                >
                                <label for="profile_photo" class="btn btn-sm btn-outline-secondary profile-photo-btn">Change Photo</label>
                            </form>
                            <?php if ($hasProfilePhoto): ?>
                                <form method="post" action="" class="profile-photo-remove-form">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="form_action" value="remove_photo">
                                    <button type="submit" class="btn btn-sm btn-link profile-photo-remove-btn">Remove Photo</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="profile-header-meta">
                        <h1 class="profile-header-name"><?php echo e($displayName); ?></h1>
                        <div class="profile-header-badges">
                            <span class="status-pill <?php echo e(admin_role_badge_class((string) ($profile['role'] ?? ''))); ?>">
                                <?php echo e((string) ($profile['role'] ?? '')); ?>
                            </span>
                            <span class="status-pill <?php echo e(admin_status_badge_class((string) ($profile['status'] ?? ''))); ?>">
                                <?php echo e((string) ($profile['status'] ?? '')); ?>
                            </span>
                        </div>
                        <p class="profile-header-email mb-0"><?php echo e((string) ($profile['email'] ?? '')); ?></p>
                    </div>
                </div>
            </section>

            <div class="row g-4">
                <div class="col-lg-5">
                    <section class="summary-panel h-100" aria-labelledby="account-info-heading">
                        <h2 id="account-info-heading" class="admin-section-title">Account Information</h2>
                        <dl class="profile-details">
                            <div class="profile-details-row">
                                <dt>Phone Number</dt>
                                <dd><?php echo e((string) (($profile['phone'] ?? '') !== '' ? $profile['phone'] : '—')); ?></dd>
                            </div>
                            <div class="profile-details-row">
                                <dt>Member Since</dt>
                                <dd><?php echo e(admin_format_joined($profile['created_at'] ?? null)); ?></dd>
                            </div>
                        </dl>
                    </section>
                </div>

                <div class="col-lg-7">
                    <section class="admin-form-card mb-4" aria-labelledby="edit-profile-heading">
                        <h2 id="edit-profile-heading" class="admin-section-title">Edit Profile</h2>
                        <p class="text-muted small mb-4">Update your name, email address, or phone number.</p>

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
                            <input type="hidden" name="form_action" value="update_profile">

                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required maxlength="150" value="<?php echo e($form['full_name']); ?>" autocomplete="name">
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required maxlength="191" value="<?php echo e($form['email']); ?>" autocomplete="email">
                            </div>

                            <div class="mb-4">
                                <label for="phone" class="form-label">Phone Number <span class="text-muted">(optional)</span></label>
                                <input type="text" class="form-control" id="phone" name="phone" maxlength="30" value="<?php echo e($form['phone']); ?>" autocomplete="tel">
                            </div>

                            <button type="submit" class="btn btn-auth">Save Changes</button>
                        </form>
                    </section>

                    <section class="admin-form-card" aria-labelledby="change-password-heading">
                        <h2 id="change-password-heading" class="admin-section-title">Change Password</h2>
                        <p class="text-muted small mb-4">Enter your current password, then choose a new password.</p>

                        <?php if ($passwordErrors !== []): ?>
                            <div class="alert alert-danger" role="alert">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($passwordErrors as $error): ?>
                                        <li><?php echo e($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="" novalidate>
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="form_action" value="change_password">

                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
                                <div class="form-text">Must be at least 8 characters long.</div>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
                            </div>

                            <button type="submit" class="btn btn-auth">Change Password</button>
                        </form>
                    </section>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
