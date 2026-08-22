<?php
declare(strict_types=1);

/**
 * Admin may edit listing content and images only for properties they own
 * (listed_by_user_id = session user_id). Other listers' content remains
 * status-moderation only via Property Management / Property View.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../seller/_helpers.php';

require_role('ADMIN');

$user = current_user();
$userId = (int) ($user['user_id'] ?? 0);
$propertyId = (int) ($_GET['id'] ?? $_POST['property_id'] ?? 0);
$property = null;
$images = [];
$notFound = false;
$errors = [];

try {
    $pdo = db();
    if ($propertyId <= 0) {
        $notFound = true;
    } else {
        // Own listings only — never edit another user's property content here.
        $property = seller_find_own_property($pdo, $propertyId, $userId);
        if ($property === null) {
            $notFound = true;
        } else {
            $images = admin_property_images($pdo, $propertyId);
        }
    }
} catch (Throwable $e) {
    $errors[] = 'Unable to load this property right now.';
}

if (!$notFound && $property !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'update_details');

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request. Please try again.';
    } elseif ($action === 'set_primary') {
        $imageId = (int) ($_POST['image_id'] ?? 0);
        try {
            $pdo = db();
            $check = $pdo->prepare(
                'SELECT image_id FROM property_images pi
                 INNER JOIN properties p ON p.property_id = pi.property_id
                 WHERE pi.image_id = ? AND pi.property_id = ? AND p.listed_by_user_id = ?
                 LIMIT 1'
            );
            $check->execute([$imageId, $propertyId, $userId]);
            if (!$check->fetch()) {
                flash_set('error', 'Image not found.');
            } else {
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE property_images SET is_primary = 0 WHERE property_id = ?')->execute([$propertyId]);
                $pdo->prepare('UPDATE property_images SET is_primary = 1 WHERE image_id = ? AND property_id = ?')->execute([$imageId, $propertyId]);
                $pdo->commit();
                flash_set('success', 'Primary image updated.');
            }
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash_set('error', 'Unable to update primary image.');
        }
        redirect('admin/property_edit.php?id=' . $propertyId);
    } elseif ($action === 'delete_image') {
        $imageId = (int) ($_POST['image_id'] ?? 0);
        try {
            $pdo = db();
            $check = $pdo->prepare(
                'SELECT pi.image_id, pi.image_path, pi.is_primary
                 FROM property_images pi
                 INNER JOIN properties p ON p.property_id = pi.property_id
                 WHERE pi.image_id = ? AND pi.property_id = ? AND p.listed_by_user_id = ?
                 LIMIT 1'
            );
            $check->execute([$imageId, $propertyId, $userId]);
            $image = $check->fetch();
            if (!is_array($image)) {
                flash_set('error', 'Image not found.');
            } else {
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM property_images WHERE image_id = ? AND property_id = ?')
                    ->execute([$imageId, $propertyId]);
                seller_ensure_single_primary($pdo, $propertyId);
                $pdo->commit();
                seller_delete_image_file((string) ($image['image_path'] ?? ''));
                flash_set('success', 'Image removed.');
            }
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash_set('error', 'Unable to remove image.');
        }
        redirect('admin/property_edit.php?id=' . $propertyId);
    } elseif ($action === 'add_images') {
        try {
            $pdo = db();
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM property_images WHERE property_id = ?');
            $countStmt->execute([$propertyId]);
            $existingCount = (int) $countStmt->fetchColumn();
            $uploads = seller_collect_uploaded_images($_FILES['images'] ?? []);
            $remaining = SELLER_PROPERTY_MAX_IMAGES - $existingCount;

            if ($remaining <= 0) {
                flash_set('error', 'Image limit reached (' . SELLER_PROPERTY_MAX_IMAGES . ').');
                redirect('admin/property_edit.php?id=' . $propertyId);
            }

            if (count($uploads) > $remaining) {
                flash_set('error', 'You can add only ' . $remaining . ' more image(s).');
                redirect('admin/property_edit.php?id=' . $propertyId);
            }

            $storedPaths = [];
            $uploadErrors = [];
            foreach ($uploads as $upload) {
                $result = seller_store_uploaded_image($upload);
                if (str_starts_with($result, 'ERROR:')) {
                    $uploadErrors[] = substr($result, 7);
                    break;
                }
                $storedPaths[] = $result;
            }

            if ($uploadErrors !== []) {
                foreach ($storedPaths as $path) {
                    seller_delete_image_file($path);
                }
                flash_set('error', $uploadErrors[0]);
                redirect('admin/property_edit.php?id=' . $propertyId);
            }

            if ($storedPaths === []) {
                flash_set('error', 'No images were selected.');
                redirect('admin/property_edit.php?id=' . $propertyId);
            }

            $pdo->beginTransaction();
            $primaryCountStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM property_images WHERE property_id = ? AND is_primary = 1'
            );
            $primaryCountStmt->execute([$propertyId]);
            $hasPrimary = (int) $primaryCountStmt->fetchColumn() > 0;
            $imgStmt = $pdo->prepare(
                'INSERT INTO property_images (property_id, image_path, is_primary) VALUES (?, ?, ?)'
            );
            foreach ($storedPaths as $index => $path) {
                $isPrimary = (!$hasPrimary && $index === 0) ? 1 : 0;
                $imgStmt->execute([$propertyId, $path, $isPrimary]);
                if ($isPrimary === 1) {
                    $hasPrimary = true;
                }
            }
            seller_ensure_single_primary($pdo, $propertyId);
            $pdo->commit();
            flash_set('success', 'Images added successfully.');
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (isset($storedPaths) && is_array($storedPaths)) {
                foreach ($storedPaths as $path) {
                    seller_delete_image_file($path);
                }
            }
            flash_set('error', 'Unable to add images right now.');
        }
        redirect('admin/property_edit.php?id=' . $propertyId);
    } elseif ($action === 'update_details' && $errors === []) {
        // Never accept ownership or status changes from the form.
        unset($_POST['listed_by_user_id'], $_POST['status'], $_POST['seller_id']);
        [$fieldErrors, $data] = seller_validate_property_input($_POST);
        $errors = array_merge($errors, $fieldErrors);

        if ($errors === []) {
            try {
                $pdo = db();
                // Keep current status; Admin moderates status separately.
                $stmt = $pdo->prepare(
                    'UPDATE properties SET
                        title = ?, description = ?, district = ?, area = ?, address = ?, property_type = ?,
                        perch = ?, bedrooms = ?, bathrooms = ?, kitchen_area_sqft = ?, parking_spots = ?,
                        has_garden = ?, has_ac = ?, water_supply = ?, electricity = ?, floors = ?, year_built = ?,
                        asking_price_lkr = ?
                     WHERE property_id = ? AND listed_by_user_id = ?'
                );
                $stmt->execute([
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
                    $propertyId,
                    $userId,
                ]);

                if ($stmt->rowCount() === 0 && seller_find_own_property($pdo, $propertyId, $userId) === null) {
                    flash_set('error', 'Property not found.');
                    redirect('admin/properties.php');
                }

                flash_set('success', 'Property updated successfully.');
                redirect('admin/property_view.php?id=' . $propertyId);
            } catch (Throwable $e) {
                $errors[] = 'Unable to update the property right now.';
            }
        }
    }
}

if (!$notFound && $property !== null) {
    $images = admin_property_images(db(), $propertyId);
}

$old = [
    'title' => (string) ($property['title'] ?? ''),
    'description' => (string) ($property['description'] ?? ''),
    'district' => (string) ($property['district'] ?? ''),
    'area' => (string) ($property['area'] ?? ''),
    'address' => (string) ($property['address'] ?? ''),
    'property_type' => (string) ($property['property_type'] ?? 'HOUSE'),
    'perch' => $property !== null && $property['perch'] !== null ? (string) $property['perch'] : '',
    'bedrooms' => $property !== null && isset($property['bedrooms']) && $property['bedrooms'] !== null ? (string) $property['bedrooms'] : '',
    'bathrooms' => $property !== null && isset($property['bathrooms']) && $property['bathrooms'] !== null ? (string) $property['bathrooms'] : '',
    'kitchen_area_sqft' => $property !== null && isset($property['kitchen_area_sqft']) && $property['kitchen_area_sqft'] !== null ? (string) $property['kitchen_area_sqft'] : '',
    'parking_spots' => (string) ($property['parking_spots'] ?? '0'),
    'has_garden' => (string) ((int) ($property['has_garden'] ?? 0)),
    'has_ac' => (string) ((int) ($property['has_ac'] ?? 0)),
    'water_supply' => (string) ((int) ($property['water_supply'] ?? 0)),
    'electricity' => (string) ((int) ($property['electricity'] ?? 0)),
    'floors' => $property !== null && isset($property['floors']) && $property['floors'] !== null ? (string) $property['floors'] : '',
    'year_built' => $property !== null && isset($property['year_built']) && $property['year_built'] !== null ? (string) $property['year_built'] : '',
    'asking_price_lkr' => $property !== null && isset($property['asking_price_lkr']) ? (string) $property['asking_price_lkr'] : '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_details' && $errors !== []) {
    foreach ($old as $key => $_) {
        if (isset($_POST[$key]) && !is_array($_POST[$key])) {
            $old[$key] = (string) $_POST[$key];
        }
    }
}

$flash = flash_get();
$page_title = 'Edit Own Property | RealEstateAI';
$page_description = 'Edit your Admin-owned RealEstateAI property listing.';
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
            <span>Edit Own Property</span>
        </nav>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($notFound || $property === null): ?>
            <div class="admin-hero">
                <h1 class="admin-title">Property not found</h1>
                <p class="admin-welcome">You can only edit listing content for properties you own. Use Property View to moderate other listings' status.</p>
            </div>
            <a class="btn btn-auth" href="<?php echo e(url('admin/properties.php')); ?>">Back to Property Management</a>
        <?php else: ?>
            <div class="admin-hero">
                <p class="admin-eyebrow">Administration</p>
                <h1 class="admin-title">Edit Own Property</h1>
                <p class="admin-welcome">
                    Ownership cannot be changed.
                    Current status:
                    <span class="status-pill <?php echo e(admin_property_status_badge_class((string) $property['status'])); ?>"><?php echo e((string) $property['status']); ?></span>
                    (change status from Property View).
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

            <div class="admin-form-card admin-form-card-wide mb-4">
                <form method="post" action="" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update_details">
                    <input type="hidden" name="property_id" value="<?php echo e((string) $propertyId); ?>">

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

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-auth">Save Changes</button>
                        <a class="btn btn-outline-secondary" href="<?php echo e(url('admin/property_view.php?id=' . $propertyId)); ?>">Cancel</a>
                    </div>
                </form>
            </div>

            <div class="summary-panel property-gallery-section">
                <h2 class="summary-title">Images</h2>
                <?php if ($images === []): ?>
                    <p class="property-gallery-empty">No images have been uploaded for this property.</p>
                <?php else: ?>
                    <div class="property-gallery<?php echo count($images) === 1 ? ' is-single' : ''; ?> mb-3">
                        <?php foreach ($images as $image): ?>
                            <?php
                            $imgUrl = admin_image_url($image['image_path'] ?? null);
                            $imageId = (int) ($image['image_id'] ?? 0);
                            $isPrimary = (int) ($image['is_primary'] ?? 0) === 1;
                            ?>
                            <figure class="property-gallery-item<?php echo $isPrimary ? ' is-primary' : ''; ?>">
                                <?php if ($imgUrl !== null): ?>
                                    <img src="<?php echo e($imgUrl); ?>" alt="" loading="lazy" width="800" height="600">
                                <?php endif; ?>
                                <?php if ($isPrimary): ?>
                                    <figcaption>Primary</figcaption>
                                <?php endif; ?>
                                <div class="gallery-actions">
                                    <?php if (!$isPrimary): ?>
                                        <form method="post" action="">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="set_primary">
                                            <input type="hidden" name="property_id" value="<?php echo e((string) $propertyId); ?>">
                                            <input type="hidden" name="image_id" value="<?php echo e((string) $imageId); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Set primary</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_image">
                                        <input type="hidden" name="property_id" value="<?php echo e((string) $propertyId); ?>">
                                        <input type="hidden" name="image_id" value="<?php echo e((string) $imageId); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                    </form>
                                </div>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (count($images) < SELLER_PROPERTY_MAX_IMAGES): ?>
                    <form method="post" action="" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="add_images">
                        <input type="hidden" name="property_id" value="<?php echo e((string) $propertyId); ?>">
                        <label for="images" class="form-label">Add images</label>
                        <input type="file" class="form-control mb-2" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
                        <button type="submit" class="btn btn-auth btn-sm">Upload</button>
                    </form>
                <?php else: ?>
                    <p class="text-muted mb-0">Maximum of <?php echo e((string) SELLER_PROPERTY_MAX_IMAGES); ?> images reached.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
