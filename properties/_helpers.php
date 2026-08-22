<?php
/**
 * Public marketplace helpers — AVAILABLE listings only.
 */

declare(strict_types=1);

require_once __DIR__ . '/../admin/_helpers.php';

if (!defined('MARKETPLACE_PER_PAGE')) {
    define('MARKETPLACE_PER_PAGE', 9);
}

if (!function_exists('marketplace_property_types')) {
    /** @return list<string> */
    function marketplace_property_types(): array
    {
        return admin_allowed_property_types();
    }
}

if (!function_exists('marketplace_sort_options')) {
    /**
     * User-facing sort key => SQL ORDER BY clause (fixed whitelist only).
     *
     * @return array<string, string>
     */
    function marketplace_sort_options(): array
    {
        return [
            'newest' => 'p.created_at DESC, p.property_id DESC',
            'oldest' => 'p.created_at ASC, p.property_id ASC',
            'price_asc' => 'p.asking_price_lkr ASC, p.property_id DESC',
            'price_desc' => 'p.asking_price_lkr DESC, p.property_id DESC',
        ];
    }
}

if (!function_exists('marketplace_bedroom_options')) {
    /** @return array<string, int|null> label key => min bedrooms */
    function marketplace_bedroom_options(): array
    {
        return [
            '' => null,
            '1' => 1,
            '2' => 2,
            '3' => 3,
            '4' => 4,
        ];
    }
}

if (!function_exists('marketplace_build_query')) {
    /**
     * @param array<string, scalar|null> $params
     */
    function marketplace_build_query(array $params): string
    {
        $filtered = [];
        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_string($value) && trim($value) === '') {
                continue;
            }
            $filtered[$key] = is_string($value) ? trim($value) : $value;
        }

        return $filtered === [] ? '' : ('?' . http_build_query($filtered));
    }
}

if (!function_exists('marketplace_parse_filters')) {
    /**
     * Parse and sanitize GET marketplace filters.
     *
     * @param array<string, mixed> $input
     * @return array{
     *   q: string,
     *   district: string,
     *   property_type: string,
     *   bedrooms: string,
     *   bathrooms: string,
     *   min_price: string,
     *   max_price: string,
     *   min_perch: string,
     *   sort: string,
     *   page: int,
     *   bedrooms_min: ?int,
     *   bathrooms_min: ?int,
     *   min_price_val: ?float,
     *   max_price_val: ?float,
     *   min_perch_val: ?float
     * }
     */
    function marketplace_parse_filters(array $input): array
    {
        $q = trim((string) ($input['q'] ?? ''));
        if (mb_strlen($q) > 120) {
            $q = mb_substr($q, 0, 120);
        }

        $district = trim((string) ($input['district'] ?? ''));
        if (mb_strlen($district) > 100) {
            $district = mb_substr($district, 0, 100);
        }

        $typeRaw = strtoupper(trim((string) ($input['property_type'] ?? '')));
        $propertyType = in_array($typeRaw, marketplace_property_types(), true) ? $typeRaw : '';

        $bedroomsKey = trim((string) ($input['bedrooms'] ?? ''));
        $bedroomOpts = marketplace_bedroom_options();
        if (!array_key_exists($bedroomsKey, $bedroomOpts)) {
            $bedroomsKey = '';
        }
        $bedroomsMin = $bedroomOpts[$bedroomsKey];

        $bathroomsKey = trim((string) ($input['bathrooms'] ?? ''));
        if (!array_key_exists($bathroomsKey, $bedroomOpts)) {
            $bathroomsKey = '';
        }
        $bathroomsMin = $bedroomOpts[$bathroomsKey];

        $minPriceRaw = trim((string) ($input['min_price'] ?? ''));
        $maxPriceRaw = trim((string) ($input['max_price'] ?? ''));
        $minPriceVal = null;
        $maxPriceVal = null;
        if ($minPriceRaw !== '' && is_numeric($minPriceRaw) && (float) $minPriceRaw >= 0) {
            $minPriceVal = (float) $minPriceRaw;
            $minPriceRaw = (string) (int) $minPriceVal === (string) (float) $minPriceVal
                ? (string) (int) $minPriceVal
                : rtrim(rtrim(sprintf('%.2f', $minPriceVal), '0'), '.');
        } else {
            $minPriceRaw = '';
        }
        if ($maxPriceRaw !== '' && is_numeric($maxPriceRaw) && (float) $maxPriceRaw >= 0) {
            $maxPriceVal = (float) $maxPriceRaw;
            $maxPriceRaw = (string) (int) $maxPriceVal === (string) (float) $maxPriceVal
                ? (string) (int) $maxPriceVal
                : rtrim(rtrim(sprintf('%.2f', $maxPriceVal), '0'), '.');
        } else {
            $maxPriceRaw = '';
        }
        if ($minPriceVal !== null && $maxPriceVal !== null && $minPriceVal > $maxPriceVal) {
            // Swap invalid range safely
            [$minPriceVal, $maxPriceVal] = [$maxPriceVal, $minPriceVal];
            [$minPriceRaw, $maxPriceRaw] = [$maxPriceRaw, $minPriceRaw];
        }

        $minPerchRaw = trim((string) ($input['min_perch'] ?? ''));
        $minPerchVal = null;
        if ($minPerchRaw !== '' && is_numeric($minPerchRaw) && (float) $minPerchRaw >= 0) {
            $minPerchVal = (float) $minPerchRaw;
        } else {
            $minPerchRaw = '';
        }

        $sortRaw = strtolower(trim((string) ($input['sort'] ?? 'newest')));
        $sortOptions = marketplace_sort_options();
        $sort = array_key_exists($sortRaw, $sortOptions) ? $sortRaw : 'newest';

        $page = max(1, (int) ($input['page'] ?? 1));

        return [
            'q' => $q,
            'district' => $district,
            'property_type' => $propertyType,
            'bedrooms' => $bedroomsKey,
            'bathrooms' => $bathroomsKey,
            'min_price' => $minPriceRaw,
            'max_price' => $maxPriceRaw,
            'min_perch' => $minPerchRaw,
            'sort' => $sort,
            'page' => $page,
            'bedrooms_min' => $bedroomsMin,
            'bathrooms_min' => $bathroomsMin,
            'min_price_val' => $minPriceVal,
            'max_price_val' => $maxPriceVal,
            'min_perch_val' => $minPerchVal,
        ];
    }
}

if (!function_exists('marketplace_filter_query_params')) {
    /**
     * @param array<string, mixed> $filters from marketplace_parse_filters
     * @return array<string, scalar|null>
     */
    function marketplace_filter_query_params(array $filters, bool $includePage = false): array
    {
        $params = [
            'q' => $filters['q'] !== '' ? $filters['q'] : null,
            'district' => $filters['district'] !== '' ? $filters['district'] : null,
            'property_type' => $filters['property_type'] !== '' ? $filters['property_type'] : null,
            'bedrooms' => $filters['bedrooms'] !== '' ? $filters['bedrooms'] : null,
            'bathrooms' => $filters['bathrooms'] !== '' ? $filters['bathrooms'] : null,
            'min_price' => $filters['min_price'] !== '' ? $filters['min_price'] : null,
            'max_price' => $filters['max_price'] !== '' ? $filters['max_price'] : null,
            'min_perch' => $filters['min_perch'] !== '' ? $filters['min_perch'] : null,
            'sort' => ($filters['sort'] ?? 'newest') !== 'newest' ? $filters['sort'] : null,
        ];

        if ($includePage && (int) ($filters['page'] ?? 1) > 1) {
            $params['page'] = (int) $filters['page'];
        }

        return $params;
    }
}

if (!function_exists('marketplace_build_where')) {
    /**
     * Always enforces status = AVAILABLE.
     *
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: list<mixed>}
     */
    function marketplace_build_where(array $filters): array
    {
        $where = ["p.status = 'AVAILABLE'"];
        $params = [];

        if ($filters['q'] !== '') {
            $where[] = '(p.title LIKE ? OR p.district LIKE ? OR p.area LIKE ? OR p.address LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($filters['district'] !== '') {
            $where[] = 'LOWER(p.district) = LOWER(?)';
            $params[] = $filters['district'];
        }

        if ($filters['property_type'] !== '') {
            $where[] = 'p.property_type = ?';
            $params[] = $filters['property_type'];
        }

        if ($filters['bedrooms_min'] !== null) {
            $where[] = 'p.bedrooms >= ?';
            $params[] = $filters['bedrooms_min'];
        }

        if ($filters['bathrooms_min'] !== null) {
            $where[] = 'p.bathrooms >= ?';
            $params[] = $filters['bathrooms_min'];
        }

        if ($filters['min_price_val'] !== null) {
            $where[] = 'p.asking_price_lkr >= ?';
            $params[] = $filters['min_price_val'];
        }

        if ($filters['max_price_val'] !== null) {
            $where[] = 'p.asking_price_lkr <= ?';
            $params[] = $filters['max_price_val'];
        }

        if ($filters['min_perch_val'] !== null) {
            $where[] = 'p.perch >= ?';
            $params[] = $filters['min_perch_val'];
        }

        return [implode(' AND ', $where), $params];
    }
}

if (!function_exists('marketplace_primary_image_sql')) {
    function marketplace_primary_image_sql(): string
    {
        return '(
            SELECT pi.image_path
            FROM property_images pi
            WHERE pi.property_id = p.property_id
            ORDER BY pi.is_primary DESC, pi.image_id ASC
            LIMIT 1
        ) AS primary_image';
    }
}

if (!function_exists('marketplace_count_available')) {
    function marketplace_count_available(PDO $pdo, array $filters = []): int
    {
        if ($filters === []) {
            $filters = marketplace_parse_filters([]);
        }
        [$whereSql, $params] = marketplace_build_where($filters);
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM properties p
             INNER JOIN users u ON u.user_id = p.listed_by_user_id
             WHERE {$whereSql}"
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('marketplace_search_properties')) {
    /**
     * @param array<string, mixed> $filters
     * @return array{0: list<array<string, mixed>>, 1: int, 2: int}
     */
    function marketplace_search_properties(PDO $pdo, array $filters): array
    {
        [$whereSql, $params] = marketplace_build_where($filters);
        $sortSql = marketplace_sort_options()[$filters['sort']] ?? marketplace_sort_options()['newest'];
        $perPage = MARKETPLACE_PER_PAGE;

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM properties p
             INNER JOIN users u ON u.user_id = p.listed_by_user_id
             WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, (int) $filters['page']), $totalPages);
        $offset = ($page - 1) * $perPage;

        $imageSql = marketplace_primary_image_sql();
        $listStmt = $pdo->prepare(
            "SELECT p.property_id, p.title, p.district, p.area, p.property_type,
                    p.bedrooms, p.bathrooms, p.perch, p.asking_price_lkr, p.created_at,
                    u.full_name AS lister_name, u.role AS lister_role,
                    {$imageSql}
             FROM properties p
             INNER JOIN users u ON u.user_id = p.listed_by_user_id
             WHERE {$whereSql}
             ORDER BY {$sortSql}
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $listStmt->execute($params);
        $rows = $listStmt->fetchAll();

        return [is_array($rows) ? $rows : [], $total, $totalPages, $page];
    }
}

if (!function_exists('marketplace_featured_properties')) {
    /**
     * Latest AVAILABLE properties for homepage (no duplicates from images).
     *
     * @return list<array<string, mixed>>
     */
    function marketplace_featured_properties(PDO $pdo, int $limit = 6): array
    {
        $limit = max(1, min(12, $limit));
        $imageSql = marketplace_primary_image_sql();
        $stmt = $pdo->prepare(
            "SELECT p.property_id, p.title, p.district, p.area, p.property_type,
                    p.bedrooms, p.bathrooms, p.perch, p.asking_price_lkr, p.created_at,
                    u.full_name AS lister_name, u.role AS lister_role,
                    {$imageSql}
             FROM properties p
             INNER JOIN users u ON u.user_id = p.listed_by_user_id
             WHERE p.status = 'AVAILABLE'
             ORDER BY p.created_at DESC, p.property_id DESC
             LIMIT {$limit}"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('marketplace_find_available_property')) {
    /**
     * Public detail lookup — AVAILABLE only (hides PENDING/SOLD/INACTIVE).
     *
     * @return array<string, mixed>|null
     */
    function marketplace_find_available_property(PDO $pdo, int $propertyId): ?array
    {
        if ($propertyId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT p.*,
                    u.full_name AS lister_name,
                    u.role AS lister_role
             FROM properties p
             INNER JOIN users u ON u.user_id = p.listed_by_user_id
             WHERE p.property_id = ? AND p.status = 'AVAILABLE'
             LIMIT 1"
        );
        $stmt->execute([$propertyId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('marketplace_property_images')) {
    /**
     * @return list<array<string, mixed>>
     */
    function marketplace_property_images(PDO $pdo, int $propertyId): array
    {
        return admin_property_images($pdo, $propertyId);
    }
}

if (!function_exists('marketplace_distinct_districts')) {
    /** @return list<string> */
    function marketplace_distinct_districts(PDO $pdo): array
    {
        $stmt = $pdo->query(
            "SELECT DISTINCT district
             FROM properties
             WHERE status = 'AVAILABLE' AND district <> ''
             ORDER BY district ASC"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $out = [];
        foreach ($rows as $district) {
            $district = trim((string) $district);
            if ($district !== '') {
                $out[] = $district;
            }
        }

        return $out;
    }
}

if (!function_exists('marketplace_sri_lanka_districts')) {
    /** @return list<string> */
    function marketplace_sri_lanka_districts(): array
    {
        return [
            'Colombo', 'Gampaha', 'Kalutara', 'Kandy', 'Matale', 'Nuwara Eliya',
            'Galle', 'Matara', 'Hambantota', 'Jaffna', 'Kilinochchi', 'Mannar',
            'Vavuniya', 'Mullaitivu', 'Batticaloa', 'Ampara', 'Trincomalee',
            'Kurunegala', 'Puttalam', 'Anuradhapura', 'Polonnaruwa', 'Badulla',
            'Monaragala', 'Ratnapura', 'Kegalle',
        ];
    }
}
