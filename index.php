<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<main id="main-content">
    <!-- Hero -->
    <section class="hero-section" aria-labelledby="hero-heading">
        <div class="hero-media" aria-hidden="true"></div>
        <div class="hero-overlay" aria-hidden="true"></div>
        <div class="container">
            <div class="hero-content">
                <p class="hero-brand">RealEstateAI</p>
                <h1 id="hero-heading" class="hero-title">Find Your Perfect Property in Sri Lanka</h1>
                <p class="hero-text">
                    Discover houses, apartments and land across the island — then estimate fair market value with AI-powered price insights built for Sri Lanka.
                </p>
                <div class="hero-actions">
                    <a href="#featured-properties" class="btn btn-hero-primary">Browse Properties</a>
                    <a href="#ai-estimator" class="btn btn-hero-secondary">Estimate House Price</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Property Search (UI only — no database search yet) -->
    <section class="search-section" id="search" aria-labelledby="search-heading">
        <div class="container">
            <div class="search-panel">
                <div class="section-intro text-center">
                    <h2 id="search-heading" class="section-title">Search Properties</h2>
                    <p class="section-subtitle">Filter by district, type and budget to find the right match.</p>
                </div>
                <form class="row g-3 align-items-end search-form" action="#" method="get" aria-label="Property search">
                    <div class="col-lg-3 col-md-6">
                        <label for="district" class="form-label">District</label>
                        <select class="form-select" id="district" name="district">
                            <option value="" selected>All Districts</option>
                            <option value="colombo">Colombo</option>
                            <option value="gampaha">Gampaha</option>
                            <option value="kalutara">Kalutara</option>
                            <option value="kandy">Kandy</option>
                            <option value="galle">Galle</option>
                            <option value="matara">Matara</option>
                            <option value="kurunegala">Kurunegala</option>
                            <option value="jaffna">Jaffna</option>
                            <option value="anuradhapura">Anuradhapura</option>
                            <option value="ratnapura">Ratnapura</option>
                            <option value="badulla">Badulla</option>
                            <option value="nuwara-eliya">Nuwara Eliya</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label for="property-type" class="form-label">Property Type</label>
                        <select class="form-select" id="property-type" name="property_type">
                            <option value="" selected>Any Type</option>
                            <option value="house">House</option>
                            <option value="apartment">Apartment</option>
                            <option value="land">Land</option>
                            <option value="commercial">Commercial</option>
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
                <p class="search-note">Demo search panel — database filtering will be connected in a later stage.</p>
            </div>
        </div>
    </section>

    <!-- Featured Properties (demo/frontend placeholder data only) -->
    <section class="properties-section" id="featured-properties" aria-labelledby="featured-heading">
        <div class="container">
            <div class="section-intro text-center">
                <h2 id="featured-heading" class="section-title">Featured Properties</h2>
                <p class="section-subtitle">Sample listings for demonstration — not live marketplace data.</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <article class="property-card">
                        <div class="property-media">
                            <img
                                class="property-image"
                                src="https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=80"
                                alt="Modern two-storey house exterior in Colombo 7"
                                width="900"
                                height="600"
                                loading="lazy"
                            >
                        </div>
                        <div class="property-body">
                            <h3 class="property-title">Modern Two-Storey House in Colombo 7</h3>
                            <p class="property-location">Colombo, Western Province</p>
                            <p class="property-price">LKR 85,000,000</p>
                            <ul class="property-meta list-unstyled">
                                <li><span>4</span> Bedrooms</li>
                                <li><span>3</span> Bathrooms</li>
                                <li><span>3,200</span> sq.ft</li>
                            </ul>
                            <!-- Future route: properties/view.php -->
                            <a href="#" class="btn btn-property">View Details</a>
                        </div>
                    </article>
                </div>
                <div class="col-lg-4 col-md-6">
                    <article class="property-card">
                        <div class="property-media">
                            <img
                                class="property-image"
                                src="https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=900&q=80"
                                alt="Bright sea-view apartment living space in Galle Face"
                                width="900"
                                height="600"
                                loading="lazy"
                            >
                        </div>
                        <div class="property-body">
                            <h3 class="property-title">Sea-View Apartment in Galle Face</h3>
                            <p class="property-location">Colombo, Western Province</p>
                            <p class="property-price">LKR 62,500,000</p>
                            <ul class="property-meta list-unstyled">
                                <li><span>3</span> Bedrooms</li>
                                <li><span>2</span> Bathrooms</li>
                                <li><span>1,450</span> sq.ft</li>
                            </ul>
                            <!-- Future route: properties/view.php -->
                            <a href="#" class="btn btn-property">View Details</a>
                        </div>
                    </article>
                </div>
                <div class="col-lg-4 col-md-6">
                    <article class="property-card">
                        <div class="property-media">
                            <img
                                class="property-image"
                                src="https://images.unsplash.com/photo-1564013799919-ab600027ffc6?auto=format&fit=crop&w=900&q=80"
                                alt="Colonial-style bungalow near Kandy Lake"
                                width="900"
                                height="600"
                                loading="lazy"
                            >
                        </div>
                        <div class="property-body">
                            <h3 class="property-title">Colonial Bungalow near Kandy Lake</h3>
                            <p class="property-location">Kandy, Central Province</p>
                            <p class="property-price">LKR 48,000,000</p>
                            <ul class="property-meta list-unstyled">
                                <li><span>5</span> Bedrooms</li>
                                <li><span>3</span> Bathrooms</li>
                                <li><span>4,100</span> sq.ft</li>
                            </ul>
                            <!-- Future route: properties/view.php -->
                            <a href="#" class="btn btn-property">View Details</a>
                        </div>
                    </article>
                </div>
                <div class="col-lg-4 col-md-6">
                    <article class="property-card">
                        <div class="property-media">
                            <img
                                class="property-image"
                                src="https://images.unsplash.com/photo-1613490493576-7fde63acd811?auto=format&fit=crop&w=900&q=80"
                                alt="Contemporary beach house in Unawatuna"
                                width="900"
                                height="600"
                                loading="lazy"
                            >
                        </div>
                        <div class="property-body">
                            <h3 class="property-title">Beach House in Unawatuna</h3>
                            <p class="property-location">Galle, Southern Province</p>
                            <p class="property-price">LKR 55,750,000</p>
                            <ul class="property-meta list-unstyled">
                                <li><span>3</span> Bedrooms</li>
                                <li><span>2</span> Bathrooms</li>
                                <li><span>2,400</span> sq.ft</li>
                            </ul>
                            <!-- Future route: properties/view.php -->
                            <a href="#" class="btn btn-property">View Details</a>
                        </div>
                    </article>
                </div>
                <div class="col-lg-4 col-md-6">
                    <article class="property-card">
                        <div class="property-media">
                            <img
                                class="property-image"
                                src="https://images.unsplash.com/photo-1570129477492-45c003edd2be?auto=format&fit=crop&w=900&q=80"
                                alt="Spacious family home exterior in Negombo"
                                width="900"
                                height="600"
                                loading="lazy"
                            >
                        </div>
                        <div class="property-body">
                            <h3 class="property-title">Spacious Family Home in Negombo</h3>
                            <p class="property-location">Gampaha, Western Province</p>
                            <p class="property-price">LKR 32,900,000</p>
                            <ul class="property-meta list-unstyled">
                                <li><span>4</span> Bedrooms</li>
                                <li><span>2</span> Bathrooms</li>
                                <li><span>2,800</span> sq.ft</li>
                            </ul>
                            <!-- Future route: properties/view.php -->
                            <a href="#" class="btn btn-property">View Details</a>
                        </div>
                    </article>
                </div>
                <div class="col-lg-4 col-md-6">
                    <article class="property-card">
                        <div class="property-media">
                            <img
                                class="property-image"
                                src="https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=900&q=80"
                                alt="Open residential land plot in Kaduwela"
                                width="900"
                                height="600"
                                loading="lazy"
                            >
                        </div>
                        <div class="property-body">
                            <h3 class="property-title">Residential Land Plot in Kaduwela</h3>
                            <p class="property-location">Colombo, Western Province</p>
                            <p class="property-price">LKR 18,500,000</p>
                            <ul class="property-meta list-unstyled">
                                <li><span>0</span> Bedrooms</li>
                                <li><span>0</span> Bathrooms</li>
                                <li><span>20</span> Perches</li>
                            </ul>
                            <!-- Future route: properties/view.php -->
                            <a href="#" class="btn btn-property">View Details</a>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <!-- AI Price Estimation intro (no AI functionality yet) -->
    <section class="ai-section" id="ai-estimator" aria-labelledby="ai-heading">
        <div class="container">
            <div class="ai-panel">
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <p class="ai-label">AI Price Estimator</p>
                        <h2 id="ai-heading" class="ai-title">Not Sure What Your House Is Worth?</h2>
                        <p class="ai-text">
                            Our upcoming AI model will estimate property values for the Sri Lankan market using details such as
                            district/location, land size, bedrooms, bathrooms and property facilities.
                        </p>
                        <!-- Future route: AI estimator page -->
                        <a href="#" class="btn btn-ai">Estimate Your Property</a>
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
            <div class="section-intro text-center">
                <h2 id="about-heading" class="section-title">Why Choose Us</h2>
                <p class="section-subtitle">A smarter way to buy, sell and value property in Sri Lanka.</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="feature-item">
                        <div class="feature-icon" aria-hidden="true">AI</div>
                        <h3 class="feature-title">AI-Powered Price Estimation</h3>
                        <p class="feature-text">Get data-driven value estimates based on key property characteristics.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-item">
                        <div class="feature-icon" aria-hidden="true">SR</div>
                        <h3 class="feature-title">Easy Property Search</h3>
                        <p class="feature-text">Filter listings by district, type and budget with a clear search experience.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-item">
                        <div class="feature-icon" aria-hidden="true">CM</div>
                        <h3 class="feature-title">Direct Buyer &amp; Seller Communication</h3>
                        <p class="feature-text">Connect directly with interested parties without unnecessary middle steps.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
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
            <div class="section-intro text-center">
                <h2 id="steps-heading" class="section-title">How It Works</h2>
                <p class="section-subtitle">Three simple steps to get started.</p>
            </div>
            <div class="row g-4 justify-content-center">
                <div class="col-lg-4 col-md-6">
                    <div class="step-item">
                        <span class="step-number" aria-hidden="true">1</span>
                        <h3 class="step-title">Search or List a Property</h3>
                        <p class="step-text">Browse available listings or publish your own property on the marketplace.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="step-item">
                        <span class="step-number" aria-hidden="true">2</span>
                        <h3 class="step-title">Get AI Price Estimate</h3>
                        <p class="step-text">Use property details to generate an AI-assisted market value estimate.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
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

<?php include 'includes/footer.php'; ?>
