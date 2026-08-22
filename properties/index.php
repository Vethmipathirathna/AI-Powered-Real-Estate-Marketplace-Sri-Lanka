<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_helpers.php';

$filters = marketplace_parse_filters($_GET);
$properties = [];
$total = 0;
$totalPages = 1;
$districts = marketplace_sri_lanka_districts();
$loadError = null;

try {
    $pdo = db();
    $liveDistricts = marketplace_distinct_districts($pdo);
    foreach ($liveDistricts as $d) {
        if (!in_array($d, $districts, true)) {
            $districts[] = $d;
        }
    }
    sort($districts, SORT_NATURAL | SORT_FLAG_CASE);

    [$properties, $total, $totalPages, $page] = marketplace_search_properties($pdo, $filters);
    $filters['page'] = $page;
} catch (Throwable $e) {
    $loadError = 'Unable to load properties right now. Please try again later.';
}

$queryBase = marketplace_filter_query_params($filters, false);
$page_title = 'Browse Properties | RealEstateAI';
$page_description = 'Browse available houses, apartments, land and commercial properties across Sri Lanka.';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="marketplace-page">
    <div class="container">
        <div class="marketplace-hero">
            <p class="admin-eyebrow">Marketplace</p>
            <h1 class="admin-title">Browse Properties</h1>
            <p class="admin-welcome">Explore AVAILABLE listings from sellers and admins across Sri Lanka.</p>
        </div>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($loadError); ?></div>
        <?php else: ?>
            <section class="marketplace-filters summary-panel mb-4" aria-labelledby="filter-heading">
                <h2 id="filter-heading" class="summary-title">Search &amp; filters</h2>
                <form class="row g-3 align-items-end" method="get" action="">
                    <div class="col-lg-4 col-md-6">
                        <label for="q" class="form-label">Search</label>
                        <input type="search" class="form-control" id="q" name="q" value="<?php echo e($filters['q']); ?>" placeholder="Title, district, area or address" maxlength="120">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label for="district" class="form-label">District</label>
                        <select class="form-select" id="district" name="district">
                            <option value="">All districts</option>
                            <?php foreach ($districts as $district): ?>
                                <option value="<?php echo e($district); ?>"<?php echo strcasecmp($filters['district'], $district) === 0 ? ' selected' : ''; ?>><?php echo e($district); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label for="property_type" class="form-label">Property type</label>
                        <select class="form-select" id="property_type" name="property_type">
                            <option value="">ALL</option>
                            <?php foreach (marketplace_property_types() as $type): ?>
                                <option value="<?php echo e($type); ?>"<?php echo $filters['property_type'] === $type ? ' selected' : ''; ?>><?php echo e($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <label for="bedrooms" class="form-label">Bedrooms</label>
                        <select class="form-select" id="bedrooms" name="bedrooms">
                            <option value=""<?php echo $filters['bedrooms'] === '' ? ' selected' : ''; ?>>Any</option>
                            <option value="1"<?php echo $filters['bedrooms'] === '1' ? ' selected' : ''; ?>>1+</option>
                            <option value="2"<?php echo $filters['bedrooms'] === '2' ? ' selected' : ''; ?>>2+</option>
                            <option value="3"<?php echo $filters['bedrooms'] === '3' ? ' selected' : ''; ?>>3+</option>
                            <option value="4"<?php echo $filters['bedrooms'] === '4' ? ' selected' : ''; ?>>4+</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <label for="bathrooms" class="form-label">Bathrooms</label>
                        <select class="form-select" id="bathrooms" name="bathrooms">
                            <option value=""<?php echo $filters['bathrooms'] === '' ? ' selected' : ''; ?>>Any</option>
                            <option value="1"<?php echo $filters['bathrooms'] === '1' ? ' selected' : ''; ?>>1+</option>
                            <option value="2"<?php echo $filters['bathrooms'] === '2' ? ' selected' : ''; ?>>2+</option>
                            <option value="3"<?php echo $filters['bathrooms'] === '3' ? ' selected' : ''; ?>>3+</option>
                            <option value="4"<?php echo $filters['bathrooms'] === '4' ? ' selected' : ''; ?>>4+</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <label for="min_price" class="form-label">Min price (LKR)</label>
                        <input type="number" class="form-control" id="min_price" name="min_price" min="0" step="1" value="<?php echo e($filters['min_price']); ?>" placeholder="No min">
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <label for="max_price" class="form-label">Max price (LKR)</label>
                        <input type="number" class="form-control" id="max_price" name="max_price" min="0" step="1" value="<?php echo e($filters['max_price']); ?>" placeholder="No max">
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <label for="min_perch" class="form-label">Min land (perch)</label>
                        <input type="number" class="form-control" id="min_perch" name="min_perch" min="0" step="0.01" value="<?php echo e($filters['min_perch']); ?>" placeholder="Any">
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <label for="sort" class="form-label">Sort by</label>
                        <select class="form-select" id="sort" name="sort">
                            <option value="newest"<?php echo $filters['sort'] === 'newest' ? ' selected' : ''; ?>>Newest</option>
                            <option value="oldest"<?php echo $filters['sort'] === 'oldest' ? ' selected' : ''; ?>>Oldest</option>
                            <option value="price_asc"<?php echo $filters['sort'] === 'price_asc' ? ' selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_desc"<?php echo $filters['sort'] === 'price_desc' ? ' selected' : ''; ?>>Price: High to Low</option>
                        </select>
                    </div>
                    <div class="col-lg-4 col-md-8 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-auth flex-grow-1">Apply</button>
                        <a class="btn btn-outline-secondary" href="<?php echo e(url('properties/index.php')); ?>">Reset</a>
                    </div>
                </form>
            </section>

            <section class="marketplace-results" aria-live="polite">
                <p class="marketplace-count text-muted mb-3">
                    <?php echo e((string) $total); ?> listing<?php echo $total === 1 ? '' : 's'; ?> found
                </p>

                <?php if ($properties === []): ?>
                    <div class="summary-panel">
                        <p class="empty-state mb-0">No available properties match your search. Try adjusting filters or browse all listings.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($properties as $property): ?>
                            <div class="col-lg-4 col-md-6">
                                <?php include __DIR__ . '/_card.php'; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav class="admin-pagination mt-4" aria-label="Property pagination">
                            <ul class="pagination mb-0 flex-wrap">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item<?php echo $i === (int) $filters['page'] ? ' active' : ''; ?>">
                                        <a class="page-link" href="<?php echo e(url('properties/index.php') . marketplace_build_query($queryBase + ['page' => $i])); ?>"><?php echo e((string) $i); ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
