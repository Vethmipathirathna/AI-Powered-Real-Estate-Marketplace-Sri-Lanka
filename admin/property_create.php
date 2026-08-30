<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../seller/_helpers.php';

require_role('ADMIN');

$user = current_user();
$userId = (int) ($user['user_id'] ?? 0);
$errors = [];
$old = [
    'title' => '',
    'description' => '',
    'district' => '',
    'area' => '',
    'address' => '',
    'property_type' => 'HOUSE',
    'perch' => '',
    'bedrooms' => '',
    'bathrooms' => '',
    'kitchen_area_sqft' => '',
    'parking_spots' => '0',
    'has_garden' => '0',
    'has_ac' => '0',
    'water_supply' => '0',
    'electricity' => '0',
    'floors' => '',
    'year_built' => '',
    'asking_price_lkr' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        // Ownership and status come from the server only — never from the client.
        unset($_POST['listed_by_user_id'], $_POST['status'], $_POST['property_id'], $_POST['seller_id']);

        foreach ($old as $key => $_) {
            if (isset($_POST[$key]) && !is_array($_POST[$key])) {
                $old[$key] = (string) $_POST[$key];
            }
        }

        [$fieldErrors, $data] = seller_validate_property_input($_POST);
        $errors = array_merge($errors, $fieldErrors);

        $uploads = seller_collect_uploaded_images($_FILES['images'] ?? []);
        if (count($uploads) > SELLER_PROPERTY_MAX_IMAGES) {
            $errors[] = 'You can upload a maximum of ' . SELLER_PROPERTY_MAX_IMAGES . ' images.';
        }

        $storedPaths = [];
        if ($errors === []) {
            foreach ($uploads as $upload) {
                $result = seller_store_uploaded_image($upload);
                if (str_starts_with($result, 'ERROR:')) {
                    $errors[] = substr($result, 7);
                    break;
                }
                $storedPaths[] = $result;
            }
        }

        if ($errors === []) {
            try {
                $pdo = db();
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'INSERT INTO properties (
                        listed_by_user_id, title, description, district, area, address, property_type,
                        perch, bedrooms, bathrooms, kitchen_area_sqft, parking_spots,
                        has_garden, has_ac, water_supply, electricity, floors, year_built,
                        asking_price_lkr, status
                     ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?,
                        ?, ?
                     )'
                );
                $stmt->execute([
                    $userId, // authenticated Admin session only
                    $data['title'],
                    $data['description'],
                    $data['district'],
                    $data['area'],
                    $data['address'],
                    $data['property_type'],
                    $data['perch'],
                    $data['bedrooms'],
                    $data['bathrooms'],
                    $data['kitchen_area_sqft'],
                    $data['parking_spots'],
                    $data['has_garden'],
                    $data['has_ac'],
                    $data['water_supply'],
                    $data['electricity'],
                    $data['floors'],
                    $data['year_built'],
                    $data['asking_price_lkr'],
                    'AVAILABLE', // Admin is the moderator; own listings skip PENDING review
                ]);

                $propertyId = (int) $pdo->lastInsertId();
                $imgStmt = $pdo->prepare(
                    'INSERT INTO property_images (property_id, image_path, is_primary) VALUES (?, ?, ?)'
                );

                foreach ($storedPaths as $index => $path) {
                    $imgStmt->execute([$propertyId, $path, $index === 0 ? 1 : 0]);
                }

                $pdo->commit();
                flash_set('success', 'Your property listing was created and is AVAILABLE.');
                redirect('admin/property_view.php?id=' . $propertyId);
            } catch (Throwable $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                foreach ($storedPaths as $path) {
                    seller_delete_image_file($path);
                }
                $errors[] = 'Unable to create the property right now. Please try again later.';
            }
        } else {
            foreach ($storedPaths as $path) {
                seller_delete_image_file($path);
            }
        }
    }
}

$page_title = 'Add Own Property | RealEstateAI';
$page_description = 'Create a RealEstateAI property listing under your Admin account.';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="admin-dashboard">
    <div class="container">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo e(url('admin/index.php')); ?>">Admin Dashboard</a>
            <span aria-hidden="true">/</span>
            <a href="<?php echo e(url('admin/properties.php')); ?>">Property Management</a>
            <span aria-hidden="true">/</span>
            <span>Add Own Property</span>
        </nav>

        <div class="admin-hero">
            <p class="admin-eyebrow">Administration</p>
            <h1 class="admin-title">Add Own Property</h1>
            <p class="admin-welcome">
                This listing will be owned by your Admin account and published as AVAILABLE.
            </p>
        </div>

        <?php if ($errors !== []): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo e($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="admin-form-card admin-form-card-wide">
            <form method="post" action="" enctype="multipart/form-data" novalidate>
                <?php echo csrf_field(); ?>

                <h2 class="summary-title">Basic details</h2>
                <div class="mb-3">
                    <label for="title" class="form-label">Title</label>
                    <input type="text" class="form-control" id="title" name="title" required maxlength="200" value="<?php echo e($old['title']); ?>">
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo e($old['description']); ?></textarea>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="district" class="form-label">District</label>
                        <input type="text" class="form-control" id="district" name="district" required maxlength="100" value="<?php echo e($old['district']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="area" class="form-label">Area / locality</label>
                        <input type="text" class="form-control" id="area" name="area" maxlength="150" value="<?php echo e($old['area']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="property_type" class="form-label">Property type</label>
                        <select class="form-select" id="property_type" name="property_type" required>
                            <?php foreach (admin_allowed_property_types() as $type): ?>
                                <option value="<?php echo e($type); ?>"<?php echo $old['property_type'] === $type ? ' selected' : ''; ?>><?php echo e($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="address" class="form-label">Address</label>
                    <input type="text" class="form-control" id="address" name="address" maxlength="255" value="<?php echo e($old['address']); ?>">
                </div>

                <h2 class="summary-title">Property features</h2>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label for="perch" class="form-label">Land size (perch)</label>
                        <input type="number" class="form-control" id="perch" name="perch" min="0" step="0.01" value="<?php echo e($old['perch']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="bedrooms" class="form-label">Bedrooms</label>
                        <input type="number" class="form-control" id="bedrooms" name="bedrooms" min="0" step="1" value="<?php echo e($old['bedrooms']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="bathrooms" class="form-label">Bathrooms</label>
                        <input type="number" class="form-control" id="bathrooms" name="bathrooms" min="0" step="1" value="<?php echo e($old['bathrooms']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="kitchen_area_sqft" class="form-label">Kitchen area (sq.ft)</label>
                        <input type="number" class="form-control" id="kitchen_area_sqft" name="kitchen_area_sqft" min="0" step="0.01" value="<?php echo e($old['kitchen_area_sqft']); ?>">
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label for="parking_spots" class="form-label">Parking spots</label>
                        <input type="number" class="form-control" id="parking_spots" name="parking_spots" min="0" step="1" value="<?php echo e($old['parking_spots']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="floors" class="form-label">Floors</label>
                        <input type="number" class="form-control" id="floors" name="floors" min="0" step="1" value="<?php echo e($old['floors']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="year_built" class="form-label">Year built</label>
                        <input type="number" class="form-control" id="year_built" name="year_built" min="1800" step="1" value="<?php echo e($old['year_built']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="asking_price_lkr" class="form-label">Asking price (LKR)</label>
                        <input type="number" class="form-control" id="asking_price_lkr" name="asking_price_lkr" required min="1" step="0.01" value="<?php echo e($old['asking_price_lkr']); ?>">
                    </div>
                </div>
                <div class="row g-3 mb-4">
                    <?php
                    $checks = [
                        'has_garden' => 'Garden',
                        'has_ac' => 'Air conditioning',
                        'water_supply' => 'Water supply',
                        'electricity' => 'Electricity',
                    ];
                    foreach ($checks as $name => $label):
                    ?>
                        <div class="col-6 col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="<?php echo e($name); ?>" name="<?php echo e($name); ?>"<?php echo $old[$name] === '1' ? ' checked' : ''; ?>>
                                <label class="form-check-label" for="<?php echo e($name); ?>"><?php echo e($label); ?></label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h2 class="summary-title">Images</h2>
                <div class="mb-4">
                    <label for="images" class="form-label">Upload images (optional, max <?php echo e((string) SELLER_PROPERTY_MAX_IMAGES); ?>)</label>
                    <input type="file" class="form-control" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
                    <div class="form-text">JPEG, PNG or WEBP. Max 2 MB each. First image becomes primary.</div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-auth">Create Listing</button>
                    <a class="btn btn-outline-secondary" href="<?php echo e(url('admin/properties.php')); ?>">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
