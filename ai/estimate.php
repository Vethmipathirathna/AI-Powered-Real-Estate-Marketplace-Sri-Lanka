<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ai_helpers.php';
require_once __DIR__ . '/../includes/ai_client.php';
require_once __DIR__ . '/../admin/_helpers.php';

ai_require_estimator_access();

$user = current_user();
$userId = (int) ($user['user_id'] ?? 0);
$flash = flash_get();
$form = ai_default_form_values();
$errors = [];
$result = ai_pull_result_flash();
$serviceMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash_set('error', 'Invalid request. Please try again.');
        redirect('ai/estimate.php');
    }

    [$clean, $errors, $form] = ai_validate_form_input($_POST);
    if ($clean !== null) {
        $apiResult = ai_api_predict(ai_build_api_payload($clean));
        if ($apiResult['ok']) {
            try {
                ai_save_prediction(db(), $userId, $clean, [
                    'predicted_price_lkr' => (float) $apiResult['predicted_price_lkr'],
                    'model_version' => (string) ($apiResult['model_version'] ?? 'rf_100_depth20_v1'),
                ]);
            } catch (Throwable $e) {
                // Prediction succeeded; only history persist failed. Survive PRG via flash.
                flash_set(
                    'warning',
                    'Your estimate was calculated successfully, but it could not be saved to Prediction History.'
                );
            }

            ai_store_result_flash([
                'inputs' => $clean,
                'predicted_price_lkr' => (float) $apiResult['predicted_price_lkr'],
                'predicted_price_formatted' => (string) ($apiResult['predicted_price_formatted'] ?? admin_format_lkr($apiResult['predicted_price_lkr'])),
                'model_version' => (string) ($apiResult['model_version'] ?? 'rf_100_depth20_v1'),
            ]);
            redirect('ai/estimate.php');
        }

        if (($apiResult['status'] ?? '') === 'validation' && !empty($apiResult['details'])) {
            foreach ($apiResult['details'] as $detail) {
                $errors[] = (string) $detail;
            }
        } else {
            $serviceMessage = (string) ($apiResult['error'] ?? 'The AI prediction service is temporarily unavailable. Please try again later.');
        }
    }
}

$districts = ai_model_districts();
$areas = ai_model_areas();
$areaOtherValue = ai_area_other_value();
$showAreaOtherField = ($form['area_select'] ?? '') === $areaOtherValue;
$page_title = 'AI House Price Estimator | RealEstateAI';
$page_description = 'AI-powered house price estimate for Sri Lankan properties.';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="admin-dashboard ai-estimator-page">
    <div class="container">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo e(url(dashboard_path_for_role($user['role'] ?? 'BUYER'))); ?>">Dashboard</a>
            <span aria-hidden="true">/</span>
            <span>AI House Price Estimator</span>
        </nav>

        <div class="admin-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <p class="admin-eyebrow">AI tools</p>
                <h1 class="admin-title">AI House Price Estimator</h1>
                <p class="admin-welcome mb-0">AI-powered estimate based on property characteristics.</p>
            </div>
            <a class="btn btn-outline-secondary" href="<?php echo e(url('ai/history.php')); ?>">Prediction History</a>
        </div>

        <?php if ($flash !== null): ?>
            <?php
            $flashAlertClass = match ((string) ($flash['type'] ?? '')) {
                'success' => 'success',
                'warning' => 'warning',
                default => 'danger',
            };
            ?>
            <div class="alert alert-<?php echo e($flashAlertClass); ?>" role="alert">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($serviceMessage !== null): ?>
            <div class="alert alert-warning" role="alert"><?php echo e($serviceMessage); ?></div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="alert alert-danger" role="alert">
                <p class="mb-2">Please correct the following:</p>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo e($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($result !== null): ?>
            <?php $inputs = is_array($result['inputs'] ?? null) ? $result['inputs'] : []; ?>
            <section class="ai-result-card summary-panel mb-4" aria-labelledby="ai-result-heading">
                <p class="admin-eyebrow mb-2">Estimated Property Value</p>
                <h2 id="ai-result-heading" class="ai-result-price mb-3">
                    <?php echo e((string) ($result['predicted_price_formatted'] ?? admin_format_lkr($result['predicted_price_lkr'] ?? 0))); ?>
                </h2>
                <dl class="detail-grid ai-result-grid mb-3">
                    <div><dt>District</dt><dd><?php echo e((string) ($inputs['district'] ?? '—')); ?></dd></div>
                    <div><dt>Area</dt><dd><?php echo e((string) ($inputs['area'] ?? '—')); ?></dd></div>
                    <div><dt>Land size</dt><dd><?php echo e(isset($inputs['perch']) ? (string) $inputs['perch'] . ' perch' : '—'); ?></dd></div>
                    <div><dt>Bedrooms / Bathrooms</dt><dd><?php echo e((string) ($inputs['bedrooms'] ?? '—') . ' / ' . (string) ($inputs['bathrooms'] ?? '—')); ?></dd></div>
                </dl>
                <p class="ai-disclaimer mb-0">
                    This AI estimate is for informational purposes only and may differ from the actual market value.
                </p>
            </section>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <section class="summary-panel">
                    <h2 class="summary-title">Property details</h2>
                    <p class="text-muted small mb-0">
                        Enter your property location and features to receive an AI-generated price estimate.
                    </p>
                    <form method="post" action="<?php echo e(url('ai/estimate.php')); ?>" class="ai-estimate-form" novalidate>
                        <?php echo csrf_field(); ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="district" class="form-label">District</label>
                                <select class="form-select" id="district" name="district" required>
                                    <option value="">Select district</option>
                                    <?php foreach ($districts as $district): ?>
                                        <option value="<?php echo e($district); ?>"<?php echo $form['district'] === $district ? ' selected' : ''; ?>><?php echo e($district); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="area_select" class="form-label">Area</label>
                                <select class="form-select" id="area_select" name="area_select" required data-other-value="<?php echo e($areaOtherValue); ?>">
                                    <option value="">Select area</option>
                                    <?php foreach ($areas as $area): ?>
                                        <option value="<?php echo e($area); ?>"<?php echo ($form['area_select'] ?? '') === $area ? ' selected' : ''; ?>><?php echo e($area); ?></option>
                                    <?php endforeach; ?>
                                    <option value="<?php echo e($areaOtherValue); ?>"<?php echo ($form['area_select'] ?? '') === $areaOtherValue ? ' selected' : ''; ?>>Other Area</option>
                                </select>
                            </div>
                            <div class="col-12 ai-area-other-wrap"<?php echo $showAreaOtherField ? '' : ' hidden'; ?>>
                                <label for="area_other" class="form-label">Enter your area</label>
                                <input type="text" class="form-control" id="area_other" name="area_other" maxlength="150" value="<?php echo e((string) ($form['area_other'] ?? '')); ?>" placeholder="e.g. Gampaha">
                                <div class="form-text">If your area is not listed, enter it here. You can also choose the closest available area from the list for a more reliable estimate.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="perch" class="form-label">Land size (perch)</label>
                                <input type="number" class="form-control" id="perch" name="perch" min="0.01" step="0.01" required value="<?php echo e((string) $form['perch']); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="bedrooms" class="form-label">Bedrooms</label>
                                <input type="number" class="form-control" id="bedrooms" name="bedrooms" min="0" step="1" required value="<?php echo e((string) $form['bedrooms']); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="bathrooms" class="form-label">Bathrooms</label>
                                <input type="number" class="form-control" id="bathrooms" name="bathrooms" min="0" step="1" required value="<?php echo e((string) $form['bathrooms']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="kitchen_area_sqft" class="form-label">Kitchen area (sq.ft)</label>
                                <input type="number" class="form-control" id="kitchen_area_sqft" name="kitchen_area_sqft" min="0" step="0.01" required value="<?php echo e((string) $form['kitchen_area_sqft']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="parking_spots" class="form-label">Parking spots</label>
                                <input type="number" class="form-control" id="parking_spots" name="parking_spots" min="0" step="1" required value="<?php echo e((string) $form['parking_spots']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="has_garden" class="form-label">Garden</label>
                                <select class="form-select" id="has_garden" name="has_garden" required>
                                    <option value="0"<?php echo ($form['has_garden'] ?? '') === '0' ? ' selected' : ''; ?>>No</option>
                                    <option value="1"<?php echo ($form['has_garden'] ?? '') === '1' ? ' selected' : ''; ?>>Yes</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="has_ac" class="form-label">Air conditioning</label>
                                <select class="form-select" id="has_ac" name="has_ac" required>
                                    <option value="0"<?php echo ($form['has_ac'] ?? '') === '0' ? ' selected' : ''; ?>>No</option>
                                    <option value="1"<?php echo ($form['has_ac'] ?? '') === '1' ? ' selected' : ''; ?>>Yes</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="water_supply" class="form-label">Water supply</label>
                                <select class="form-select" id="water_supply" name="water_supply" required>
                                    <option value="">Select option</option>
                                    <?php foreach (ai_water_supply_options() as $option): ?>
                                        <option value="<?php echo e($option); ?>"<?php echo ($form['water_supply'] ?? '') === $option ? ' selected' : ''; ?>><?php echo e($option); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="electricity" class="form-label">Electricity</label>
                                <select class="form-select" id="electricity" name="electricity" required>
                                    <option value="">Select option</option>
                                    <?php foreach (ai_electricity_options() as $option): ?>
                                        <option value="<?php echo e($option); ?>"<?php echo ($form['electricity'] ?? '') === $option ? ' selected' : ''; ?>><?php echo e($option); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="floors" class="form-label">Floors</label>
                                <input type="number" class="form-control" id="floors" name="floors" min="1" step="1" required value="<?php echo e((string) $form['floors']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="year_built" class="form-label">Year built</label>
                                <input type="number" class="form-control" id="year_built" name="year_built" min="1800" max="<?php echo e((string) ((int) date('Y') + 1)); ?>" step="1" required value="<?php echo e((string) $form['year_built']); ?>">
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-auth">Get AI Estimate</button>
                        </div>
                    </form>
                </section>
            </div>
            <div class="col-lg-5">
                <section class="summary-panel h-100">
                    <h2 class="summary-title">About This Estimate</h2>
                    <p class="mb-3">Enter the property details to receive an estimated property value based on the information provided.</p>
                    <p class="ai-disclaimer mb-0">Please note: The estimated value is for informational purposes only. The actual market value may vary depending on property condition, exact location, market demand, and other factors.</p>
                </section>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
