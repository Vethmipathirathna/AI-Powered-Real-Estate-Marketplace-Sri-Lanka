<?php
$user = current_user();
$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$isHome = str_ends_with($scriptName, '/index.php') && !preg_match('#/(auth|buyer|seller|admin|properties|ai|support)/#', $scriptName);
$isProperties = str_contains($scriptName, '/properties/');
$isBuyerFavorites = str_contains($scriptName, '/buyer/favorites.php');
$isMessagesPage = preg_match('#/(buyer|seller)/messages\.php$#', $scriptName) === 1
    || str_contains($scriptName, '/admin/messages.php')
    || (
        (str_contains($scriptName, '/buyer/conversation.php')
            || str_contains($scriptName, '/seller/conversation.php')
            || str_contains($scriptName, '/admin/conversation.php'))
        && !str_contains($scriptName, '/support_conversation.php')
    );
$isAiEstimator = str_contains($scriptName, '/ai/estimate.php');
$isAiHistory = str_contains($scriptName, '/ai/history.php');
$isAdminPredictions = str_contains($scriptName, '/admin/predictions.php')
    || str_contains($scriptName, '/admin/prediction_view.php');
$isAdminReports = str_contains($scriptName, '/admin/reports.php')
    || str_contains($scriptName, '/admin/report_print.php');
$isAdminUsers = str_contains($scriptName, '/admin/users.php')
    || str_contains($scriptName, '/admin/user_create.php')
    || str_contains($scriptName, '/admin/user_edit.php');
$isAdminManageProperties = str_contains($scriptName, '/admin/properties.php')
    || str_contains($scriptName, '/admin/property_');
$isAdminSoldHistory = str_contains($scriptName, '/admin/sold_history.php');
$isAdminSupport = str_contains($scriptName, '/admin/support.php')
    || str_contains($scriptName, '/admin/support_conversation.php');
$isAdminMessages = str_contains($scriptName, '/admin/messages.php')
    || str_contains($scriptName, '/admin/conversation.php');
$isSupportPage = str_contains($scriptName, '/support/')
    || $isAdminSupport;
$isSeller = $user !== null && strtoupper((string) ($user['role'] ?? '')) === 'SELLER';
$homeHref = url('index.php');
$propertiesHref = url('properties/index.php');
$isBuyer = $user !== null && strtoupper((string) ($user['role'] ?? '')) === 'BUYER';
$isAdmin = $user !== null && strtoupper((string) ($user['role'] ?? '')) === 'ADMIN';
$userRole = strtoupper((string) ($user['role'] ?? ''));
$messagesHref = match ($userRole) {
    'BUYER' => url('buyer/messages.php'),
    'SELLER' => url('seller/messages.php'),
    'ADMIN' => url('admin/messages.php'),
    default => null,
};
$canUseAiEstimator = $user !== null && in_array($userRole, ['BUYER', 'SELLER', 'ADMIN'], true);
$aiEstimatorHref = $canUseAiEstimator ? url('ai/estimate.php') : url('auth/login.php');
$canContactSupport = $isBuyer || $isSeller;
$isAdminDropdownActive = $isAdmin
    && ($isAdminUsers || $isAdminManageProperties || $isAdminSoldHistory || $isAdminPredictions || $isAdminSupport || $isAdminMessages);
$showPublicAboutContact = !$isAdmin && !$isBuyer;
$isBuyerSupport = str_contains($scriptName, '/support/');
$isProfilePage = str_contains($scriptName, '/profile/');
$navProfileImage = null;

if ($user !== null) {
    require_once __DIR__ . '/profile_helpers.php';
    require_once __DIR__ . '/../config/database.php';

    try {
        $navProfileImage = profile_fetch_image_path(db(), (int) ($user['user_id'] ?? 0));
    } catch (Throwable $e) {
        $navProfileImage = null;
    }
}
?>
<header class="site-header">
    <nav class="navbar navbar-expand-lg navbar-light" aria-label="Primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo e($homeHref); ?>">
                <span class="brand-mark">RealEstateAI</span>
            </a>
            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNavbar"
                aria-controls="mainNavbar"
                aria-expanded="false"
                aria-label="Toggle navigation"
            >
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav mx-auto align-items-lg-center<?php echo $isBuyer ? ' navbar-nav-compact' : ''; ?>">
                    <li class="nav-item">
                        <a class="nav-link<?php echo $isHome ? ' active' : ''; ?>"<?php echo $isHome ? ' aria-current="page"' : ''; ?> href="<?php echo e($homeHref); ?>">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $isProperties ? ' active' : ''; ?>"<?php echo $isProperties ? ' aria-current="page"' : ''; ?> href="<?php echo e($propertiesHref); ?>">Properties</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-nowrap<?php echo $isAiEstimator ? ' active' : ''; ?>"<?php echo $isAiEstimator ? ' aria-current="page"' : ''; ?> href="<?php echo e($aiEstimatorHref); ?>">AI Price Estimator</a>
                    </li>

                    <?php if ($showPublicAboutContact): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo e($homeHref); ?>#about">About</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo e($homeHref); ?>#contact">Contact</a>
                        </li>
                    <?php endif; ?>

                    <?php if ($isBuyer): ?>
                        <li class="nav-item">
                            <a class="nav-link text-nowrap<?php echo $isBuyerFavorites ? ' active' : ''; ?>"<?php echo $isBuyerFavorites ? ' aria-current="page"' : ''; ?> href="<?php echo e(url('buyer/favorites.php')); ?>">My Favorites</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-nowrap<?php echo $isAiHistory ? ' active' : ''; ?>"<?php echo $isAiHistory ? ' aria-current="page"' : ''; ?> href="<?php echo e(url('ai/history.php')); ?>">Prediction History</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?php echo $isBuyerSupport ? ' active' : ''; ?>"<?php echo $isBuyerSupport ? ' aria-current="page"' : ''; ?> href="<?php echo e(url('support/index.php')); ?>">Help</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?php echo $isMessagesPage ? ' active' : ''; ?>"<?php echo $isMessagesPage ? ' aria-current="page"' : ''; ?> href="<?php echo e(url('buyer/messages.php')); ?>">Messages</a>
                        </li>
                    <?php endif; ?>

                    <?php if ($canUseAiEstimator && !$isAdmin && !$isBuyer): ?>
                        <li class="nav-item">
                            <a class="nav-link text-nowrap<?php echo $isAiHistory ? ' active' : ''; ?>"<?php echo $isAiHistory ? ' aria-current="page"' : ''; ?> href="<?php echo e(url('ai/history.php')); ?>">Prediction History</a>
                        </li>
                    <?php endif; ?>

                    <?php if ($isAdmin): ?>
                        <li class="nav-item">
                            <a class="nav-link<?php echo $isAdminReports ? ' active' : ''; ?>"<?php echo $isAdminReports ? ' aria-current="page"' : ''; ?> href="<?php echo e(url('admin/reports.php')); ?>">Reports</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a
                                class="nav-link dropdown-toggle text-nowrap<?php echo $isAdminDropdownActive ? ' active' : ''; ?>"
                                href="#"
                                id="adminNavDropdown"
                                role="button"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                                aria-haspopup="true"
                                aria-label="Admin menu"
                            >
                                Admin
                            </a>
                            <ul class="dropdown-menu dropdown-menu-admin" aria-labelledby="adminNavDropdown">
                                <li>
                                    <a class="dropdown-item<?php echo $isAdminUsers ? ' active' : ''; ?>"<?php echo $isAdminUsers ? ' aria-current="page"' : ''; ?> href="<?php echo e(url('admin/users.php')); ?>">Manage Users</a>
                                </li>
                                <li>
                                    <a class="dropdown-item<?php echo $isAdminManageProperties ? ' active' : ''; ?>"<?php echo $isAdminManageProperties ? ' aria-current="page"' : ''; ?> href="<?php echo e(url('admin/properties.php')); ?>">Manage Properties</a>
                                </li>
                                <li>
                                    <a class="dropdown-item<?php echo $isAdminSoldHistory ? ' active' : ''; ?>"<?php echo $isAdminSoldHistory ? ' aria-current="page"' : ''; ?> href="<?php echo e(url('admin/sold_history.php')); ?>">Sold History</a>
                                </li>
                                <li>
                                    <a class="dropdown-item<?php echo $isAdminPredictions ? ' active' : ''; ?>"<?php echo $isAdminPredictions ? ' aria-current="page"' : ''; ?> href="<?php echo e(url('admin/predictions.php')); ?>">AI Predictions</a>
                                </li>
                                <li>
                                    <a class="dropdown-item<?php echo $isAdminSupport ? ' active' : ''; ?>"<?php echo $isAdminSupport ? ' aria-current="page"' : ''; ?> href="<?php echo e(url('admin/support.php')); ?>">Support Inbox</a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item<?php echo $isAdminMessages ? ' active' : ''; ?>"<?php echo $isAdminMessages ? ' aria-current="page"' : ''; ?> href="<?php echo e(url('admin/messages.php')); ?>">My Listing Messages</a>
                                </li>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <?php if ($canContactSupport && !$isBuyer): ?>
                        <li class="nav-item">
                            <a class="nav-link<?php echo $isSupportPage ? ' active' : ''; ?>"<?php echo $isSupportPage ? ' aria-current="page"' : ''; ?> href="<?php echo e(url('support/index.php')); ?>">Help</a>
                        </li>
                    <?php endif; ?>

                    <?php if ($messagesHref !== null && !$isAdmin && !$isBuyer): ?>
                        <li class="nav-item">
                            <a class="nav-link<?php echo $isMessagesPage ? ' active' : ''; ?>"<?php echo $isMessagesPage ? ' aria-current="page"' : ''; ?> href="<?php echo e($messagesHref); ?>">Messages</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <div class="nav-actions d-flex align-items-center gap-2">
                    <?php if ($user !== null): ?>
                        <a
                            class="nav-user-name<?php echo $isProfilePage ? ' active' : ''; ?>"
                            href="<?php echo e(url('profile/index.php')); ?>"
                            title="My Profile"
                            <?php echo $isProfilePage ? ' aria-current="page"' : ''; ?>
                        >
                            <span class="nav-user-avatar-wrap" aria-hidden="true">
                                <?php echo profile_render_avatar((string) ($user['full_name'] ?? ''), $navProfileImage, ['class' => 'nav-user-avatar']); ?>
                            </span>
                            <span class="nav-user-label"><?php echo e($user['full_name']); ?></span>
                        </a>
                        <a class="btn btn-nav-register" href="<?php echo e(url(dashboard_path_for_role($user['role']))); ?>">Dashboard</a>
                        <a class="btn btn-nav-login" href="<?php echo e(url('auth/logout.php')); ?>">Logout</a>
                    <?php else: ?>
                        <a class="btn btn-nav-login" href="<?php echo e(url('auth/login.php')); ?>">Login</a>
                        <a class="btn btn-nav-register" href="<?php echo e(url('auth/register.php')); ?>">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>
