<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/properties/_helpers.php';
require_once __DIR__ . '/buyer/_helpers.php';

$featured = [];
$featuredError = null;
$favoritePropertyIds = [];
$homeUser = current_user();
$aiEstimatorCtaHref = $homeUser !== null && in_array(strtoupper((string) ($homeUser['role'] ?? '')), ['BUYER', 'SELLER', 'ADMIN'], true)
    ? url('ai/estimate.php')
    : url('auth/login.php');

try {
    $pdo = db();
    $featured = marketplace_featured_properties($pdo, 6);
    $user = current_user();
    if (buyer_is_buyer($user)) {
        $favoritePropertyIds = buyer_favorite_property_ids($pdo, (int) ($user['user_id'] ?? 0));
    }
} catch (Throwable $e) {
    $featuredError = 'Unable to load featured properties right now.';
}

$favoriteReturnPath = 'index.php#featured-properties';

$page_title = 'RealEstateAI | Intelligent Real Estate Marketplace';
$page_description = 'Discover properties across Sri Lanka and estimate house prices with AI-powered insights.';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<main id="main-content" class="home-page">
    <!-- Hero -->
    <section class="hero-section" aria-labelledby="hero-heading">
        <div class="hero-media" aria-hidden="true"></div>
        <div class="hero-overlay" aria-hidden="true"></div>
        <div class="container">
            <div class="hero-content">
                <p class="hero-brand hero-animate hero-animate--1">RealEstateAI</p>
                <h1 id="hero-heading" class="hero-title hero-animate hero-animate--2">Find Your Perfect Property in Sri Lanka</h1>
                <p class="hero-text hero-animate hero-animate--3">
                    Discover houses, apartments and land across the island — then estimate fair market value with AI-powered price insights built for Sri Lanka.
                </p>
                <div class="hero-actions">
                    <a href="<?php echo e(url('properties/index.php')); ?>" class="btn btn-hero-primary hero-animate hero-animate--4">Browse Properties</a>
                    <a href="<?php echo e($aiEstimatorCtaHref); ?>" class="btn btn-hero-secondary hero-animate hero-animate--5">Estimate House Price</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Property Search → marketplace -->
    <section class="search-section" id="search" aria-labelledby="search-heading">
        <div class="container">
            <div class="search-panel reveal">
                <div class="section-intro text-center">
                    <h2 id="search-heading" class="section-title">Search Properties</h2>
                    <p class="section-subtitle">Filter by district, type and budget to find the right match.</p>
                </div>
                <form class="row g-3 align-items-end search-form" action="<?php echo e(url('properties/index.php')); ?>" method="get" aria-label="Property search">
                    <div class="col-lg-3 col-md-6">
                        <label for="district" class="form-label">District</label>
                        <select class="form-select" id="district" name="district">
                            <option value="" selected>All Districts</option>
                            <?php foreach (marketplace_sri_lanka_districts() as $district): ?>
                                <option value="<?php echo e($district); ?>"><?php echo e($district); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label for="property-type" class="form-label">Property Type</label>
                        <select class="form-select" id="property-type" name="property_type">
                            <option value="" selected>Any Type</option>
                            <?php foreach (marketplace_property_types() as $type): ?>
                                <option value="<?php echo e($type); ?>"><?php echo e(ucfirst(strtolower($type))); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label for="min-price" class="form-label">Minimum Price</label>
                        <select class="form-select" id="min-price" name="min_price">
                            <option value="" selected>No Min</option>
                            <option value="5000000">LKR 5,000,000</option>
                            <option value="10000000">LKR 10,000,000</option>
                            <option value="25000000">LKR 25,000,000</option>
                            <option value="50000000">LKR 50,000,000</option>
                            <option value="100000000">LKR 100,000,000</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label for="max-price" class="form-label">Maximum Price</label>
                        <select class="form-select" id="max-price" name="max_price">
                            <option value="" selected>No Max</option>
                            <option value="15000000">LKR 15,000,000</option>
                            <option value="30000000">LKR 30,000,000</option>
                            <option value="60000000">LKR 60,000,000</option>
                            <option value="100000000">LKR 100,000,000</option>
                            <option value="250000000">LKR 250,000,000+</option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-12">
                        <button type="submit" class="btn btn-search w-100">Search Properties</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Featured Properties (AVAILABLE marketplace data) -->
    <section class="properties-section" id="featured-properties" aria-labelledby="featured-heading">
        <div class="container">
            <div class="section-intro text-center reveal">
                <h2 id="featured-heading" class="section-title">Featured Properties</h2>
                <p class="section-subtitle">Latest AVAILABLE listings on the RealEstateAI marketplace.</p>
            </div>

            <?php if ($featuredError !== null): ?>
                <div class="alert alert-warning" role="alert"><?php echo e($featuredError); ?></div>
            <?php elseif ($featured === []): ?>
                <div class="summary-panel text-center">
                    <p class="empty-state mb-3">No available properties have been published yet.</p>
                    <a class="btn btn-auth" href="<?php echo e(url('properties/index.php')); ?>">Browse Properties</a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($featured as $property): ?>
                        <div class="col-lg-4 col-md-6 reveal">
                            <?php include __DIR__ . '/properties/_card.php'; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-4 reveal">
                    <a class="btn btn-auth" href="<?php echo e(url('properties/index.php')); ?>">View All Properties</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- AI Price Estimation -->
    <section class="ai-section" id="ai-estimator" aria-labelledby="ai-heading">
        <div class="container">
            <div class="ai-panel reveal">
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <p class="ai-label">AI Price Estimator</p>
                        <h2 id="ai-heading" class="ai-title">Not Sure What Your House Is Worth?</h2>
                        <p class="ai-text">
                            Our AI model estimates property values for the Sri Lankan market using details such as
                            district/location, land size, bedrooms, bathrooms and property facilities.
                        </p>
                        <a class="btn btn-ai" href="<?php echo e($aiEstimatorCtaHref); ?>">Estimate Your Property</a>
                    </div>
                    <div class="col-lg-5">
                        <div class="ai-visual" aria-hidden="true">
                            <div class="ai-visual-card">
                                <span class="ai-visual-label">Sample Estimate</span>
                                <strong class="ai-visual-value">LKR 42.8M</strong>
                                <span class="ai-visual-meta">Demo only — based on location &amp; property features</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us -->
    <section class="why-section" id="about" aria-labelledby="about-heading">
        <div class="container">
            <div class="section-intro text-center reveal">
                <h2 id="about-heading" class="section-title">Why Choose Us</h2>
                <p class="section-subtitle">A smarter way to buy, sell and value property in Sri Lanka.</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-3 col-md-6 reveal">
                    <div class="feature-item">
                        <div class="feature-icon" aria-hidden="true">AI</div>
                        <h3 class="feature-title">AI-Powered Price Estimation</h3>
                        <p class="feature-text">Get data-driven value estimates based on key property characteristics.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 reveal">
                    <div class="feature-item">
                        <div class="feature-icon" aria-hidden="true">SR</div>
                        <h3 class="feature-title">Easy Property Search</h3>
                        <p class="feature-text">Filter listings by district, type and budget with a clear search experience.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 reveal">
                    <div class="feature-item">
                        <div class="feature-icon" aria-hidden="true">CM</div>
                        <h3 class="feature-title">Direct Buyer &amp; Seller Communication</h3>
                        <p class="feature-text">Connect directly with interested parties without unnecessary middle steps.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 reveal">
                    <div class="feature-item">
                        <div class="feature-icon" aria-hidden="true">SC</div>
                        <h3 class="feature-title">Secure Property Marketplace</h3>
                        <p class="feature-text">A trusted platform designed for transparent and secure property dealings.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="steps-section" id="how-it-works" aria-labelledby="steps-heading">
        <div class="container">
            <div class="section-intro text-center reveal">
                <h2 id="steps-heading" class="section-title">How It Works</h2>
                <p class="section-subtitle">Three simple steps to get started.</p>
            </div>
            <div class="row g-4 justify-content-center">
                <div class="col-lg-4 col-md-6 reveal">
                    <div class="step-item">
                        <span class="step-number" aria-hidden="true">1</span>
                        <h3 class="step-title">Search or List a Property</h3>
                        <p class="step-text">Browse available listings or publish your own property on the marketplace.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 reveal">
                    <div class="step-item">
                        <span class="step-number" aria-hidden="true">2</span>
                        <h3 class="step-title">Get AI Price Estimate</h3>
                        <p class="step-text">Use property details to generate an AI-assisted market value estimate.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 reveal">
                    <div class="step-item">
                        <span class="step-number" aria-hidden="true">3</span>
                        <h3 class="step-title">Connect with Buyers or Sellers</h3>
                        <p class="step-text">Reach out, negotiate and move forward with confidence.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
