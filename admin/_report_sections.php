<?php
/**
 * Shared System Reports body sections (1–6).
 * Expects: $lifetime, $range, $aiStats, $messagesThisMonth,
 * $usersByMonth, $predictionsByMonth, $messagesByMonth,
 * $propertiesByDistrict, $soldByDistrict, $soldListings, $filterActive
 */
declare(strict_types=1);
?>
<section class="admin-section report-section" aria-labelledby="overview-heading">
    <div class="admin-section-header">
        <h2 id="overview-heading" class="admin-section-title">1. Overview</h2>
        <p class="admin-section-text">Lifetime system totals (not affected by the date filter).</p>
    </div>
    <div class="row g-3">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <span class="stat-label">Total Users</span>
                <strong class="stat-value"><?php echo e((string) $lifetime['users']); ?></strong>
                <span class="stat-subtext"><?php echo e((string) $lifetime['buyers']); ?> buyers · <?php echo e((string) $lifetime['sellers']); ?> sellers</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <span class="stat-label">Total Properties</span>
                <strong class="stat-value"><?php echo e((string) $lifetime['properties']); ?></strong>
                <span class="stat-subtext"><?php echo e((string) $lifetime['available']); ?> available · <?php echo e((string) $lifetime['pending']); ?> pending</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <span class="stat-label">Total AI Predictions</span>
                <strong class="stat-value"><?php echo e((string) $lifetime['predictions']); ?></strong>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <span class="stat-label">Total Messages</span>
                <strong class="stat-value"><?php echo e((string) $lifetime['messages']); ?></strong>
            </div>
        </div>
    </div>
</section>

<section class="admin-section report-section" aria-labelledby="user-stats-heading">
    <div class="admin-section-header">
        <h2 id="user-stats-heading" class="admin-section-title">2. User Statistics</h2>
    </div>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="summary-panel h-100">
                <h3 class="summary-title">Account summary</h3>
                <ul class="summary-list list-unstyled mb-0">
                    <li><span>Total users</span><strong><?php echo e((string) $lifetime['users']); ?></strong></li>
                    <li><span>Buyers</span><strong><?php echo e((string) $lifetime['buyers']); ?></strong></li>
                    <li><span>Sellers</span><strong><?php echo e((string) $lifetime['sellers']); ?></strong></li>
                    <li><span>Admins</span><strong><?php echo e((string) $lifetime['admins']); ?></strong></li>
                    <li><span>Active users</span><strong><?php echo e((string) $lifetime['active_users']); ?></strong></li>
                    <li><span>Inactive users</span><strong><?php echo e((string) $lifetime['inactive_users']); ?></strong></li>
                    <?php if ($filterActive): ?>
                        <li><span>New users in range</span><strong><?php echo e((string) $range['new_users']); ?></strong></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="table-panel h-100">
                <h3 class="summary-title">New users by month</h3>
                <?php if ($usersByMonth === []): ?>
                    <p class="empty-state mb-0">No user registrations found for this period.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table admin-table mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Month</th>
                                    <th scope="col">New Users</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usersByMonth as $row): ?>
                                    <tr>
                                        <td><?php echo e(admin_format_month_label($row['month_key'])); ?></td>
                                        <td><?php echo e((string) $row['total']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="admin-section report-section" aria-labelledby="property-stats-heading">
    <div class="admin-section-header">
        <h2 id="property-stats-heading" class="admin-section-title">3. Property Statistics</h2>
        <p class="admin-section-text">Current listing status and district distribution (lifetime).</p>
    </div>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="summary-panel h-100">
                <h3 class="summary-title">Counts by status</h3>
                <ul class="summary-list list-unstyled mb-0">
                    <li><span>Total Properties</span><strong><?php echo e((string) $lifetime['properties']); ?></strong></li>
                    <li><span>AVAILABLE</span><strong><?php echo e((string) $lifetime['available']); ?></strong></li>
                    <li><span>PENDING</span><strong><?php echo e((string) $lifetime['pending']); ?></strong></li>
                    <li><span>SOLD</span><strong><?php echo e((string) $lifetime['sold']); ?></strong></li>
                    <li><span>INACTIVE</span><strong><?php echo e((string) $lifetime['inactive_properties']); ?></strong></li>
                    <?php if ($filterActive): ?>
                        <li><span>Created in range</span><strong><?php echo e((string) $range['properties_created']); ?></strong></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="table-panel h-100">
                <h3 class="summary-title">Properties by District</h3>
                <?php if ($propertiesByDistrict === []): ?>
                    <p class="empty-state mb-0">No properties have been listed yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table admin-table mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">District</th>
                                    <th scope="col">Number of Properties</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($propertiesByDistrict as $row): ?>
                                    <tr>
                                        <td><?php echo e((string) ($row['district'] ?? '—')); ?></td>
                                        <td><?php echo e((string) ((int) ($row['total'] ?? 0))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="admin-section report-section" aria-labelledby="sold-listings-heading">
    <div class="admin-section-header">
        <h2 id="sold-listings-heading" class="admin-section-title">4. Sold Listings</h2>
        <p class="admin-section-text">
            Listings currently marked <strong>SOLD</strong>.
            The system does not store a separate sale price or sale date, so this section is not a sales-revenue report.
            Asking price is shown for reference only and is not actual sale revenue.
            Date-range filters do not apply here because no genuine sale timestamp exists.
        </p>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="summary-panel h-100">
                <h3 class="summary-title">Sold summary</h3>
                <ul class="summary-list list-unstyled mb-0">
                    <li><span>Total Sold Listings</span><strong><?php echo e((string) $lifetime['sold']); ?></strong></li>
                </ul>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="table-panel h-100">
                <h3 class="summary-title">Sold listings by district</h3>
                <?php if ($soldByDistrict === []): ?>
                    <p class="empty-state mb-0">No sold listings found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table admin-table mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">District</th>
                                    <th scope="col">Sold Listings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($soldByDistrict as $row): ?>
                                    <tr>
                                        <td><?php echo e((string) ($row['district'] ?? '—')); ?></td>
                                        <td><?php echo e((string) ((int) ($row['total'] ?? 0))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="table-panel">
        <h3 class="summary-title">Sold listing details</h3>
        <?php if ($soldListings === []): ?>
            <p class="empty-state mb-0">No sold listings to display.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table admin-table mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Title</th>
                            <th scope="col">District</th>
                            <th scope="col">Area</th>
                            <th scope="col">Type</th>
                            <th scope="col">Asking Price (reference)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($soldListings as $row): ?>
                            <tr>
                                <td><?php echo e((string) ($row['title'] ?? '—')); ?></td>
                                <td><?php echo e((string) ($row['district'] ?? '—')); ?></td>
                                <td><?php echo e((string) (($row['area'] ?? '') !== '' ? $row['area'] : '—')); ?></td>
                                <td><?php echo e((string) ($row['property_type'] ?? '—')); ?></td>
                                <td><?php echo e(admin_format_lkr($row['asking_price_lkr'] ?? 0)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($lifetime['sold'] > count($soldListings)): ?>
                <p class="text-muted small mt-3 mb-0">
                    Showing <?php echo e((string) count($soldListings)); ?> of <?php echo e((string) $lifetime['sold']); ?> sold listings.
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<section class="admin-section report-section" aria-labelledby="ai-stats-heading">
    <div class="admin-section-header">
        <h2 id="ai-stats-heading" class="admin-section-title">5. AI Prediction Statistics</h2>
        <?php if ($filterActive): ?>
            <p class="admin-section-text">Average / min / max and monthly counts use the selected date range. Lifetime total remains unfiltered.</p>
        <?php endif; ?>
    </div>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="summary-panel h-100">
                <h3 class="summary-title">Prediction summary</h3>
                <ul class="summary-list list-unstyled mb-0">
                    <li><span>Total predictions (lifetime)</span><strong><?php echo e((string) $lifetime['predictions']); ?></strong></li>
                    <li><span>Predictions this month</span><strong><?php echo e((string) $aiStats['this_month']); ?></strong></li>
                    <?php if ($filterActive): ?>
                        <li><span>Predictions in range</span><strong><?php echo e((string) $range['predictions']); ?></strong></li>
                    <?php endif; ?>
                    <li>
                        <span>Average predicted value<?php echo $filterActive ? ' (range)' : ''; ?></span>
                        <strong><?php echo $aiStats['average'] !== null ? e(admin_format_lkr($aiStats['average'])) : '—'; ?></strong>
                    </li>
                    <li>
                        <span>Minimum predicted value<?php echo $filterActive ? ' (range)' : ''; ?></span>
                        <strong><?php echo $aiStats['minimum'] !== null ? e(admin_format_lkr($aiStats['minimum'])) : '—'; ?></strong>
                    </li>
                    <li>
                        <span>Maximum predicted value<?php echo $filterActive ? ' (range)' : ''; ?></span>
                        <strong><?php echo $aiStats['maximum'] !== null ? e(admin_format_lkr($aiStats['maximum'])) : '—'; ?></strong>
                    </li>
                </ul>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="table-panel h-100">
                <h3 class="summary-title">Predictions by month</h3>
                <?php if ($predictionsByMonth === []): ?>
                    <p class="empty-state mb-0">No AI predictions found for this period.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table admin-table mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Month</th>
                                    <th scope="col">Predictions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($predictionsByMonth as $row): ?>
                                    <tr>
                                        <td><?php echo e(admin_format_month_label($row['month_key'])); ?></td>
                                        <td><?php echo e((string) $row['total']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="admin-section report-section" aria-labelledby="message-stats-heading">
    <div class="admin-section-header">
        <h2 id="message-stats-heading" class="admin-section-title">6. Message Activity</h2>
        <p class="admin-section-text">Aggregate activity only. Private message content is never shown.</p>
    </div>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="summary-panel h-100">
                <h3 class="summary-title">Activity summary</h3>
                <ul class="summary-list list-unstyled mb-0">
                    <li><span>Total messages (lifetime)</span><strong><?php echo e((string) $lifetime['messages']); ?></strong></li>
                    <li><span>Messages this month</span><strong><?php echo e((string) $messagesThisMonth); ?></strong></li>
                    <?php if ($filterActive): ?>
                        <li><span>Messages in range</span><strong><?php echo e((string) $range['messages']); ?></strong></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="table-panel h-100">
                <h3 class="summary-title">Messages by month</h3>
                <?php if ($messagesByMonth === []): ?>
                    <p class="empty-state mb-0">No messages found for this period.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table admin-table mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Month</th>
                                    <th scope="col">Messages</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($messagesByMonth as $row): ?>
                                    <tr>
                                        <td><?php echo e(admin_format_month_label($row['month_key'])); ?></td>
                                        <td><?php echo e((string) $row['total']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
