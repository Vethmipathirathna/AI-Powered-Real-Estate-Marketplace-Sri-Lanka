<?php
/**
 * Property detail sidebar — AI Price Insight card.
 *
 * Expects:
 * - $property (array)
 * - $propertyId (int)
 * - $aiInsight (array|null)
 * - $currentUser (array|null)
 */
declare(strict_types=1);

$askingPrice = (float) ($property['asking_price_lkr'] ?? 0);
$canEstimate = ai_user_can_estimate($currentUser ?? null);
$insightStatus = '';
$insightResult = null;
$insightErrors = [];
$insightMessage = '';

if (is_array($aiInsight ?? null)) {
    $insightStatus = (string) ($aiInsight['status'] ?? '');
    $insightResult = is_array($aiInsight['result'] ?? null) ? $aiInsight['result'] : null;
    $insightErrors = is_array($aiInsight['errors'] ?? null) ? $aiInsight['errors'] : [];
    $insightMessage = is_string($aiInsight['message'] ?? null) ? $aiInsight['message'] : '';
}
?>
<section class="summary-panel mb-4" aria-labelledby="ai-price-insight-heading">
    <h2 id="ai-price-insight-heading" class="summary-title">AI Price Insight</h2>
    <p class="text-muted small mb-3">
        Compare the listing price with an AI-generated estimate based on this property's details.
    </p>

    <?php if ($insightStatus === 'error'): ?>
        <div class="alert alert-warning py-2 px-3 small mb-3" role="alert">
            <?php echo e($insightMessage !== '' ? $insightMessage : 'Unable to generate an AI estimate right now.'); ?>
            <?php if ($insightErrors !== []): ?>
                <ul class="mb-0 mt-2 ps-3">
                    <?php foreach ($insightErrors as $error): ?>
                        <li><?php echo e((string) $error); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <dl class="ai-insight-rows mb-3">
        <div class="ai-insight-row">
            <dt>Listed Price</dt>
            <dd><?php echo e(admin_format_lkr($askingPrice)); ?></dd>
        </div>
        <?php if ($insightStatus === 'success' && is_array($insightResult)): ?>
            <div class="ai-insight-row">
                <dt>AI Estimated Price</dt>
                <dd><?php echo e((string) ($insightResult['predicted_price_formatted'] ?? admin_format_lkr($insightResult['predicted_price_lkr'] ?? 0))); ?></dd>
            </div>
            <div class="ai-insight-row">
                <dt>Difference</dt>
                <dd><?php echo e((string) ($insightResult['comparison_label'] ?? '—')); ?></dd>
            </div>
        <?php endif; ?>
    </dl>

    <?php if ($canEstimate): ?>
        <form method="post" action="<?php echo e(url('properties/view.php?id=' . $propertyId)); ?>" class="mb-3">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="form_action" value="ai_price_insight">
            <button type="submit" class="btn btn-auth w-100">Estimate This Property</button>
        </form>
    <?php elseif (($currentUser ?? null) === null): ?>
        <a class="btn btn-outline-secondary w-100 mb-3" href="<?php echo e(url('auth/login.php?return=' . rawurlencode('properties/view.php?id=' . $propertyId))); ?>">
            Login to Get AI Price Insight
        </a>
    <?php endif; ?>

    <p class="ai-disclaimer mb-0">
        AI estimates are provided for informational purposes only and may differ from actual market value.
    </p>
</section>
