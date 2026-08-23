<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_report_data.php';

require_role('ADMIN');

$flash = flash_get();
$loadError = null;

$filters = admin_resolve_report_date_filters($_GET);
$filterError = $filters['filter_error'];
$filterActive = $filters['filter_active'];
$fromDate = $filters['from_date'];
$toDate = $filters['to_date'];
$fromDisplay = $filters['from_display'];
$toDisplay = $filters['to_display'];

$lifetime = [
    'users' => 0,
    'buyers' => 0,
    'sellers' => 0,
    'admins' => 0,
    'active_users' => 0,
    'inactive_users' => 0,
    'properties' => 0,
    'available' => 0,
    'pending' => 0,
    'sold' => 0,
    'inactive_properties' => 0,
    'predictions' => 0,
    'messages' => 0,
];
$range = [
    'new_users' => 0,
    'properties_created' => 0,
    'predictions' => 0,
    'messages' => 0,
];
$aiStats = [
    'this_month' => 0,
    'average' => null,
    'minimum' => null,
    'maximum' => null,
];
$messagesThisMonth = 0;
$usersByMonth = [];
$predictionsByMonth = [];
$messagesByMonth = [];
$propertiesByDistrict = [];
$soldByDistrict = [];
$soldListings = [];

try {
    $data = admin_load_system_report_data(db(), $filterActive, $fromDate, $toDate);
    $lifetime = $data['lifetime'];
    $range = $data['range'];
    $aiStats = $data['ai_stats'];
    $messagesThisMonth = $data['messages_this_month'];
    $usersByMonth = $data['users_by_month'];
    $predictionsByMonth = $data['predictions_by_month'];
    $messagesByMonth = $data['messages_by_month'];
    $propertiesByDistrict = $data['properties_by_district'];
    $soldByDistrict = $data['sold_by_district'];
    $soldListings = $data['sold_listings'];
} catch (Throwable $e) {
    $loadError = 'Unable to load system reports right now. Please try again later.';
}

$page_title = 'System Reports | RealEstateAI';
$page_description = 'Admin system usage reports for RealEstateAI.';

$printQuery = admin_build_query([
    'from' => $filterActive && $fromDisplay !== '' ? $fromDisplay : null,
    'to' => $filterActive && $toDisplay !== '' ? $toDisplay : null,
]);
$printHref = url('admin/report_print.php' . $printQuery);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="admin-dashboard admin-reports-page">
    <div class="container">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo e(url('admin/index.php')); ?>">Admin Dashboard</a>
            <span aria-hidden="true">/</span>
            <span>System Reports</span>
        </nav>

        <div class="admin-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <p class="admin-eyebrow">Administration</p>
                <h1 class="admin-title">System Reports</h1>
                <p class="admin-welcome mb-0">Operational usage summaries for users, properties, AI predictions and messaging activity.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-auth" href="<?php echo e($printHref); ?>" target="_blank" rel="noopener noreferrer">Print Report</a>
                <a class="btn btn-outline-secondary" href="<?php echo e(url('admin/index.php')); ?>">Back to Dashboard</a>
            </div>
        </div>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($filterError !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($filterError); ?></div>
        <?php endif; ?>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($loadError); ?></div>
        <?php else: ?>
            <section class="admin-section" aria-labelledby="report-filter-heading">
                <div class="admin-section-header">
                    <h2 id="report-filter-heading" class="admin-section-title">Date Range</h2>
                    <p class="admin-section-text">Optional filter for time-based activity. Lifetime totals below remain unfiltered.</p>
                </div>
                <form class="filter-panel row g-3 align-items-end" method="get" action="">
                    <div class="col-md-4 col-lg-3">
                        <label for="from" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="from" name="from" value="<?php echo e($fromDisplay); ?>">
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <label for="to" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="to" name="to" value="<?php echo e($toDisplay); ?>">
                    </div>
                    <div class="col-md-4 col-lg-3 d-flex gap-2">
                        <button type="submit" class="btn btn-auth flex-grow-1">Apply</button>
                        <a class="btn btn-outline-secondary" href="<?php echo e(url('admin/reports.php')); ?>">Reset</a>
                    </div>
                </form>
                <?php if ($filterActive): ?>
                    <p class="text-muted small mt-3 mb-0">
                        Showing time-based activity
                        <?php if ($fromDisplay !== '' && $toDisplay !== ''): ?>
                            from <strong><?php echo e($fromDisplay); ?></strong> to <strong><?php echo e($toDisplay); ?></strong>.
                        <?php elseif ($fromDisplay !== ''): ?>
                            from <strong><?php echo e($fromDisplay); ?></strong> onwards.
                        <?php else: ?>
                            up to <strong><?php echo e($toDisplay); ?></strong>.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </section>

            <?php include __DIR__ . '/_report_sections.php'; ?>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
