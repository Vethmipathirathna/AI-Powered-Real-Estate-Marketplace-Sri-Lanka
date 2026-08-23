<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_report_data.php';

require_role('ADMIN');

$loadError = null;

$filters = admin_resolve_report_date_filters($_GET);
$filterError = $filters['filter_error'];
$filterActive = $filters['filter_active'];
$fromDate = $filters['from_date'];
$toDate = $filters['to_date'];
$fromDisplay = $filters['from_display'];
$toDisplay = $filters['to_display'];

$lifetime = [];
$range = [];
$aiStats = [];
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

$reportGeneratedAt = date('j F Y, g:i A');
$reportPeriodLabel = admin_report_period_label($filterActive, $fromDisplay, $toDisplay);

$backQuery = admin_build_query([
    'from' => $fromDisplay !== '' ? $fromDisplay : null,
    'to' => $toDisplay !== '' ? $toDisplay : null,
]);
$backHref = url('admin/reports.php' . $backQuery);

$page_title = 'System Usage Report | RealEstateAI';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e(url('assets/css/style.css')); ?>">
</head>
<body class="report-document-body">
    <div class="print-actions">
        <button type="button" class="btn btn-auth" onclick="printReport()">Print / Save as PDF</button>
        <a class="btn btn-outline-secondary" href="<?php echo e($backHref); ?>">Back to Reports</a>
    </div>

    <article class="report-document" id="main-content">
        <header class="report-document-header">
            <p class="report-document-brand">RealEstateAI</p>
            <h1 class="report-document-title">System Usage Report</h1>
            <p class="report-document-meta"><span>Generated:</span> <?php echo e($reportGeneratedAt); ?></p>
            <p class="report-document-meta"><span>Report Period:</span> <?php echo e($reportPeriodLabel); ?></p>
        </header>

        <?php if ($filterError !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($filterError); ?></div>
        <?php endif; ?>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($loadError); ?></div>
        <?php else: ?>
            <?php include __DIR__ . '/_report_sections.php'; ?>
        <?php endif; ?>

        <footer class="report-document-footer">
            RealEstateAI — System Usage Report
        </footer>
    </article>
    <script>
        function printReport() {
            const actions = document.querySelector('.print-actions');

            if (actions) {
                actions.style.setProperty('display', 'none', 'important');
            }

            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    window.print();
                });
            });
        }

        window.addEventListener('beforeprint', function () {
            const actions = document.querySelector('.print-actions');
            if (actions) {
                actions.style.setProperty('display', 'none', 'important');
            }
        });

        window.addEventListener('afterprint', function () {
            const actions = document.querySelector('.print-actions');
            if (actions) {
                actions.style.removeProperty('display');
            }
        });
    </script>
</body>
</html>
