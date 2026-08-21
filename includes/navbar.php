<?php
$user = current_user();
$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$isHome = str_ends_with($scriptName, '/index.php') && !preg_match('#/(auth|buyer|seller|admin|properties)/#', $scriptName);
$homeHref = url('index.php');
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
                        <a class="nav-link" href="<?php echo e($homeHref); ?>#featured-properties">Properties</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo e($homeHref); ?>#ai-estimator">AI Price Estimator</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo e($homeHref); ?>#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo e($homeHref); ?>#contact">Contact</a>
                    </li>
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
