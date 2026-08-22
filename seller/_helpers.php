<?php
/**
 * Seller property helpers — validation, ownership fetch, image handling.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/property_ownership.php';
require_once __DIR__ . '/../admin/_helpers.php';

if (!defined('SELLER_PROPERTY_MAX_IMAGES')) {
    define('SELLER_PROPERTY_MAX_IMAGES', 5);
}

if (!defined('SELLER_PROPERTY_MAX_IMAGE_BYTES')) {
    define('SELLER_PROPERTY_MAX_IMAGE_BYTES', 2 * 1024 * 1024); // 2 MB
}

if (!function_exists('seller_property_upload_dir')) {
    function seller_property_upload_dir(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'properties';
    }
}

if (!function_exists('seller_property_relative_prefix')) {
    function seller_property_relative_prefix(): string
    {
        return 'assets/images/properties/';
    }
}

if (!function_exists('seller_ensure_upload_dir')) {
    function seller_ensure_upload_dir(): bool
    {
        $dir = seller_property_upload_dir();
        if (is_dir($dir)) {
            return is_writable($dir);
        }

        return mkdir($dir, 0755, true);
    }
}

if (!function_exists('seller_find_own_property')) {
    /**
     * @return array<string, mixed>|null
     */
    function seller_find_own_property(PDO $pdo, int $propertyId, int $userId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT *
             FROM properties
             WHERE property_id = ? AND listed_by_user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$propertyId, $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('seller_bool_from_post')) {
    function seller_bool_from_post(mixed $value): int
    {
        if (is_array($value)) {
            return 0;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
    }
}

if (!function_exists('seller_nullable_decimal')) {
    function seller_nullable_decimal(string $raw): ?float
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (!is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }
}

if (!function_exists('seller_nullable_int')) {
    function seller_nullable_int(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (!ctype_digit($raw) && !(str_starts_with($raw, '-') && ctype_digit(substr($raw, 1)))) {
            // Allow numeric strings like "3"
            if (!is_numeric($raw) || str_contains($raw, '.')) {
                return null;
            }
        }

        return (int) $raw;
    }
}

if (!function_exists('seller_validate_property_input')) {
    /**
     * @param array<string, mixed> $input
     * @return array{0: list<string>, 1: array<string, mixed>}
     */
    function seller_validate_property_input(array $input): array
    {
        $errors = [];
        $data = [];

        $data['title'] = trim((string) ($input['title'] ?? ''));
        $data['description'] = trim((string) ($input['description'] ?? ''));
        $data['district'] = trim((string) ($input['district'] ?? ''));
        $data['area'] = trim((string) ($input['area'] ?? ''));
        $data['address'] = trim((string) ($input['address'] ?? ''));
        $data['property_type'] = strtoupper(trim((string) ($input['property_type'] ?? '')));

        if ($data['title'] === '') {
            $errors[] = 'Title is required.';
        } elseif (mb_strlen($data['title']) > 200) {
            $errors[] = 'Title must be 200 characters or fewer.';
        }

        if ($data['district'] === '') {
            $errors[] = 'District is required.';
        } elseif (mb_strlen($data['district']) > 100) {
            $errors[] = 'District must be 100 characters or fewer.';
        }

        if ($data['area'] !== '' && mb_strlen($data['area']) > 150) {
            $errors[] = 'Area must be 150 characters or fewer.';
        }

        if ($data['address'] !== '' && mb_strlen($data['address']) > 255) {
            $errors[] = 'Address must be 255 characters or fewer.';
        }

        if (!in_array($data['property_type'], admin_allowed_property_types(), true)) {
            $errors[] = 'Please select a valid property type.';
        }

        $priceRaw = trim((string) ($input['asking_price_lkr'] ?? ''));
        if ($priceRaw === '' || !is_numeric($priceRaw)) {
            $errors[] = 'Asking price must be a valid number.';
            $data['asking_price_lkr'] = null;
        } else {
            $price = (float) $priceRaw;
            if ($price <= 0) {
                $errors[] = 'Asking price must be greater than zero.';
            }
            $data['asking_price_lkr'] = $price;
        }

        $perchRaw = trim((string) ($input['perch'] ?? ''));
        if ($perchRaw === '') {
            $data['perch'] = null;
        } elseif (!is_numeric($perchRaw) || (float) $perchRaw < 0) {
            $errors[] = 'Land size (perch) must be zero or greater.';
            $data['perch'] = null;
        } else {
            $data['perch'] = (float) $perchRaw;
        }

        foreach (['bedrooms', 'bathrooms', 'parking_spots', 'floors'] as $intField) {
            $raw = trim((string) ($input[$intField] ?? ''));
            if ($raw === '') {
                $data[$intField] = $intField === 'parking_spots' ? 0 : null;
                continue;
            }
            if (!is_numeric($raw) || str_contains($raw, '.') || (int) $raw < 0) {
                $errors[] = ucfirst(str_replace('_', ' ', $intField)) . ' must be zero or greater.';
                $data[$intField] = $intField === 'parking_spots' ? 0 : null;
            } else {
                $data[$intField] = (int) $raw;
            }
        }

        $kitchenRaw = trim((string) ($input['kitchen_area_sqft'] ?? ''));
        if ($kitchenRaw === '') {
            $data['kitchen_area_sqft'] = null;
        } elseif (!is_numeric($kitchenRaw) || (float) $kitchenRaw < 0) {
            $errors[] = 'Kitchen area must be zero or greater.';
            $data['kitchen_area_sqft'] = null;
        } else {
            $data['kitchen_area_sqft'] = (float) $kitchenRaw;
        }

        $yearRaw = trim((string) ($input['year_built'] ?? ''));
        if ($yearRaw === '') {
            $data['year_built'] = null;
        } elseif (!ctype_digit($yearRaw)) {
            $errors[] = 'Year built must be a valid year.';
            $data['year_built'] = null;
        } else {
            $year = (int) $yearRaw;
            $currentYear = (int) date('Y');
            if ($year < 1800 || $year > ($currentYear + 1)) {
                $errors[] = 'Year built must be between 1800 and ' . ($currentYear + 1) . '.';
            }
            $data['year_built'] = $year;
        }

        $data['has_garden'] = seller_bool_from_post($input['has_garden'] ?? '0');
        $data['has_ac'] = seller_bool_from_post($input['has_ac'] ?? '0');
        $data['water_supply'] = seller_bool_from_post($input['water_supply'] ?? '0');
        $data['electricity'] = seller_bool_from_post($input['electricity'] ?? '0');

        $data['description'] = $data['description'] !== '' ? $data['description'] : null;
        $data['area'] = $data['area'] !== '' ? $data['area'] : null;
        $data['address'] = $data['address'] !== '' ? $data['address'] : null;

        return [$errors, $data];
    }
}

if (!function_exists('seller_allowed_image_mimes')) {
    /** @return array<string, string> mime => extension */
    function seller_allowed_image_mimes(): array
    {
        return [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
    }
}

if (!function_exists('seller_collect_uploaded_images')) {
    /**
     * Normalize $_FILES['images'] into a list of file arrays.
     *
     * @return list<array<string, mixed>>
     */
    function seller_collect_uploaded_images(array $filesField): array
    {
        $out = [];
        if (!isset($filesField['name'])) {
            return $out;
        }

        if (!is_array($filesField['name'])) {
            if ((int) ($filesField['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $out[] = $filesField;
            }
            return $out;
        }

        $count = count($filesField['name']);
        for ($i = 0; $i < $count; $i++) {
            if ((int) ($filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name' => $filesField['name'][$i] ?? '',
                'type' => $filesField['type'][$i] ?? '',
                'tmp_name' => $filesField['tmp_name'][$i] ?? '',
                'error' => $filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $filesField['size'][$i] ?? 0,
            ];
        }

        return $out;
    }
}

if (!function_exists('seller_store_uploaded_image')) {
    /**
     * Validate and store one uploaded image. Returns relative path or error string prefixed with ERROR:.
     */
    function seller_store_uploaded_image(array $file): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return 'ERROR: One of the images failed to upload.';
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return 'ERROR: Invalid image upload.';
        }

        if ($size <= 0 || $size > SELLER_PROPERTY_MAX_IMAGE_BYTES) {
            return 'ERROR: Each image must be between 1 byte and 2 MB.';
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        $allowed = seller_allowed_image_mimes();

        if (!isset($allowed[$mime])) {
            return 'ERROR: Only JPEG, PNG and WEBP images are allowed.';
        }

        if (@getimagesize($tmp) === false) {
            return 'ERROR: Uploaded file is not a valid image.';
        }

        if (!seller_ensure_upload_dir()) {
            return 'ERROR: Unable to store images right now.';
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $destination = seller_property_upload_dir() . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmp, $destination)) {
            return 'ERROR: Unable to save uploaded image.';
        }

        return seller_property_relative_prefix() . $filename;
    }
}

if (!function_exists('seller_delete_image_file')) {
    function seller_delete_image_file(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        $relativePath = str_replace('\\', '/', $relativePath);
        $prefix = seller_property_relative_prefix();
        if (!str_starts_with($relativePath, $prefix)) {
            return;
        }

        $basename = basename($relativePath);
        if ($basename === '' || $basename === '.' || $basename === '..') {
            return;
        }

        $full = seller_property_upload_dir() . DIRECTORY_SEPARATOR . $basename;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}

if (!function_exists('seller_ensure_single_primary')) {
    function seller_ensure_single_primary(PDO $pdo, int $propertyId): void
    {
        $stmt = $pdo->prepare(
            'SELECT image_id, is_primary
             FROM property_images
             WHERE property_id = ?
             ORDER BY is_primary DESC, image_id ASC'
        );
        $stmt->execute([$propertyId]);
        $images = $stmt->fetchAll();

        if ($images === []) {
            return;
        }

        $primaryId = null;
        foreach ($images as $image) {
            if ((int) ($image['is_primary'] ?? 0) === 1) {
                $primaryId = (int) $image['image_id'];
                break;
            }
        }

        if ($primaryId === null) {
            $primaryId = (int) $images[0]['image_id'];
        }

        $pdo->prepare('UPDATE property_images SET is_primary = 0 WHERE property_id = ?')
            ->execute([$propertyId]);
        $pdo->prepare('UPDATE property_images SET is_primary = 1 WHERE image_id = ? AND property_id = ?')
            ->execute([$primaryId, $propertyId]);
    }
}
