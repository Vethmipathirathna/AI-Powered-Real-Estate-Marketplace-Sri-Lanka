<?php
$user = current_user();
$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$isHome = str_ends_with($scriptName, '/index.php') && !preg_match('#/(auth|buyer|seller|admin|properties)/#', $scriptName);
$isProperties = str_contains($scriptName, '/properties/');
$isBuyerFavorites = str_contains($scriptName, '/buyer/favorites.php');
$isMessagesPage = preg_match('#/(buyer|seller)/messages\.php$#', $scriptName) === 1
    || str_contains($scriptName, '/admin/messages.php')
    || str_contains($scriptName, '/conversation.php');
$isAiEstimator = str_contains($scriptName, '/ai/estimate.php');
$isAiHistory = str_contains($scriptName, '/ai/history.php');
$isAdminPredictions = str_contains($scriptName, '/admin/predictions.php')
    || str_contains($scriptName, '/admin/prediction_view.php');
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
$messagesLabel = $userRole === 'ADMIN' ? 'My Messages' : 'Messages';
$canUseAiEstimator = $user !== null && in_array($userRole, ['BUYER', 'SELLER', 'ADMIN'], true);
$aiEstimatorHref = $canUseAiEstimator ? url('ai/estimate.php') : url('auth/login.php');
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
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link<?php echo $isHome ? ' active' : ''; ?>"<?php echo $isHome ? ' aria-current="page"' : ''; ?> href="<?php echo e($homeHref); ?>">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $isProperties ? ' active' : ''; ?>"<?php echo $isProperties ? ' aria-current="page"' : ''; ?> href="<?php echo e($propertiesHref); ?>">Properties</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $isAiEstimator ? ' active' : ''; ?>"<?php echo $isAiEstimator ? ' aria-current="page"' : ''; ?> href="<?php echo e($aiEstimatorHref); ?>">AI Price Estimator</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo e($homeHref); ?>#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo e($homeHref); ?>#contact">Contact</a>
                    </li>
                    <?php if ($isBuyer): ?>
                        <li class="nav-item">
                            <a class="nav-link<?php echo $isBuyerFavorites ? ' active' : ''; ?>"<?php echo $isBuyerFavorites ? ' aria-current="page"' : ''; ?> href="<?php echo e(url('buyer/favorites.php')); ?>">My Favorites</a>
                        </li>
                    <?php endif; ?>
                    <?php if ($canUseAiEstimator): ?>
                        <li class="nav-item">
                            <a class="nav-link<?php echo $isAiHistory ? ' active' : ''; ?>"<?php echo $isAiHistory ? ' aria-current="page"' : ''; ?> href="<?php echo e(url('ai/history.php')); ?>">Prediction History</a>
                        </li>
                    <?php endif; ?>
                    <?php if ($isAdmin): ?>
                        <li class="nav-item">
                            <a class="nav-link<?php echo $isAdminPredictions ? ' active' : ''; ?>"<?php echo $isAdminPredictions ? ' aria-current="page"' : ''; ?> href="<?php echo e(url('admin/predictions.php')); ?>">AI Predictions</a>
                        </li>
                    <?php endif; ?>
                    <?php if ($messagesHref !== null): ?>
                        <li class="nav-item">
                            <a class="nav-link<?php echo $isMessagesPage ? ' active' : ''; ?>"<?php echo $isMessagesPage ? ' aria-current="page"' : ''; ?> href="<?php echo e($messagesHref); ?>"><?php echo e($messagesLabel); ?></a>
                        </li>
                    <?php endif; ?>
                </ul>
                <div class="nav-actions d-flex align-items-center gap-2">
                    <?php if ($user !== null): ?>
                        <span class="nav-user-name d-none d-lg-inline"><?php echo e($user['full_name']); ?></span>
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
