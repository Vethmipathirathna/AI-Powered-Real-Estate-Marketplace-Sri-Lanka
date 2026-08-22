<?php
/**
 * Buyer favorites helpers.
 */

declare(strict_types=1);

require_once __DIR__ . '/../properties/_helpers.php';

if (!function_exists('buyer_is_buyer')) {
    /**
     * @param array{user_id?: int, role?: string}|null $user
     */
    function buyer_is_buyer(?array $user): bool
    {
        if ($user === null) {
            return false;
        }

        return strtoupper((string) ($user['role'] ?? '')) === 'BUYER';
    }
}

if (!function_exists('buyer_safe_return_path')) {
    /**
     * Whitelist internal return paths after favorite toggle (open-redirect safe).
     */
    function buyer_safe_return_path(?string $return): string
    {
        $return = trim((string) $return);
        if ($return === '') {
            return 'properties/index.php';
        }

        if (preg_match('#^https?://#i', $return) || str_contains($return, '//')) {
            return 'properties/index.php';
        }

        $return = ltrim(str_replace('\\', '/', $return), '/');

        $allowedExact = [
            'index.php',
            'index.php#featured-properties',
            'properties/index.php',
            'buyer/index.php',
            'buyer/favorites.php',
        ];

        if (in_array($return, $allowedExact, true)) {
            return $return;
        }

        if (preg_match('#^properties/index\.php(\?[a-zA-Z0-9_&=%\.\-\+]+)?$#', $return) === 1) {
            return $return;
        }

        if (preg_match('#^properties/view\.php\?id=[1-9][0-9]*$#', $return) === 1) {
            return $return;
        }

        return 'properties/index.php';
    }
}

if (!function_exists('buyer_favorite_property_ids')) {
    /**
     * Fetch all favorited property IDs for a buyer in one query (avoids N+1 on cards).
     *
     * @return list<int>
     */
    function buyer_favorite_property_ids(PDO $pdo, int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $stmt = $pdo->prepare('SELECT property_id FROM favorites WHERE user_id = ?');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $ids = [];
        foreach ($rows as $id) {
            $ids[] = (int) $id;
        }

        return $ids;
    }
}

if (!function_exists('buyer_count_available_favorites')) {
    function buyer_count_available_favorites(PDO $pdo, int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM favorites f
             INNER JOIN properties p ON p.property_id = f.property_id
             WHERE f.user_id = ? AND p.status = 'AVAILABLE'"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('buyer_fetch_favorites')) {
    /**
     * All saved properties for My Favorites (includes unavailable rows for lifecycle handling).
     *
     * @return list<array<string, mixed>>
     */
    function buyer_fetch_favorites(PDO $pdo, int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $imageSql = marketplace_primary_image_sql();
        $stmt = $pdo->prepare(
            "SELECT f.favorite_id, f.created_at AS saved_at,
                    p.property_id, p.title, p.district, p.area, p.property_type,
                    p.bedrooms, p.bathrooms, p.perch, p.asking_price_lkr, p.status,
                    u.full_name AS lister_name, u.role AS lister_role,
                    {$imageSql}
             FROM favorites f
             INNER JOIN properties p ON p.property_id = f.property_id
             INNER JOIN users u ON u.user_id = p.listed_by_user_id
             WHERE f.user_id = ?
             ORDER BY f.created_at DESC, f.favorite_id DESC"
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('buyer_toggle_favorite')) {
    /**
     * Add or remove a favorite for an AVAILABLE property.
     *
     * @return array{0: bool, 1: string} success, message
     */
    function buyer_toggle_favorite(PDO $pdo, int $userId, int $propertyId): array
    {
        if ($userId <= 0 || $propertyId <= 0) {
            return [false, 'Invalid property.'];
        }

        if (marketplace_find_available_property($pdo, $propertyId) === null) {
            return [false, 'This property cannot be saved.'];
        }

        $check = $pdo->prepare(
            'SELECT favorite_id FROM favorites WHERE user_id = ? AND property_id = ? LIMIT 1'
        );
        $check->execute([$userId, $propertyId]);
        $existing = $check->fetch();

        if (is_array($existing)) {
            $del = $pdo->prepare(
                'DELETE FROM favorites WHERE user_id = ? AND property_id = ? LIMIT 1'
            );
            $del->execute([$userId, $propertyId]);
            return [true, 'Removed from favorites.'];
        }

        try {
            $ins = $pdo->prepare(
                'INSERT INTO favorites (user_id, property_id) VALUES (?, ?)'
            );
            $ins->execute([$userId, $propertyId]);
        } catch (PDOException $e) {
            // Unique constraint — treat as already saved (idempotent).
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return [true, 'Property saved to favorites.'];
            }
            throw $e;
        }

        return [true, 'Property saved to favorites.'];
    }
}

if (!function_exists('buyer_remove_favorite')) {
    /**
     * Remove a favorite row owned by the current buyer only.
     *
     * @return array{0: bool, 1: string}
     */
    function buyer_remove_favorite(PDO $pdo, int $userId, int $propertyId): array
    {
        if ($userId <= 0 || $propertyId <= 0) {
            return [false, 'Invalid property.'];
        }

        $stmt = $pdo->prepare(
            'DELETE FROM favorites WHERE user_id = ? AND property_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $propertyId]);

        if ($stmt->rowCount() === 0) {
            return [false, 'Favorite not found.'];
        }

        return [true, 'Removed from favorites.'];
    }
}
