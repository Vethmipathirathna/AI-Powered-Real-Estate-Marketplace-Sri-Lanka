<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_role('ADMIN');

$user = current_user();
$flash = flash_get();
$page_title = 'Admin Dashboard | RealEstateAI';
$page_description = 'Admin dashboard placeholder for RealEstateAI.';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="placeholder-page">
    <div class="container">
        <div class="placeholder-card">
            <?php if ($flash !== null): ?>
                <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert">
                    <?php echo e($flash['message']); ?>
                </div>
            <?php endif; ?>
            <p class="placeholder-label">Admin area</p>
            <h1 class="placeholder-title">Welcome, <?php echo e($user['full_name'] ?? ''); ?></h1>
            <p class="placeholder-text">
                This is a protected admin placeholder page. User and property management tools will be added later.
            </p>
            <a class="btn btn-auth" href="<?php echo e(url('index.php')); ?>">Back to Home</a>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
