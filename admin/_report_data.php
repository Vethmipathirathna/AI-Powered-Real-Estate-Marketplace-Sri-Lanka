<?php
/**
 * Shared System Reports data loading (used by reports.php and report_print.php).
 * Calculations must stay identical across both pages.
 */
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

if (!function_exists('admin_resolve_report_date_filters')) {
    /**
     * @param array<string, mixed> $get
     * @return array{
     *   filter_error: ?string,
     *   filter_active: bool,
     *   from_date: ?string,
     *   to_date: ?string,
     *   from_display: string,
     *   to_display: string
     * }
     */
    function admin_resolve_report_date_filters(array $get): array
    {
        $fromRaw = trim((string) ($get['from'] ?? ''));
        $toRaw = trim((string) ($get['to'] ?? ''));

        $fromDate = admin_parse_report_date($fromRaw !== '' ? $fromRaw : null);
        $toDate = admin_parse_report_date($toRaw !== '' ? $toRaw : null);

        $filterError = null;
        $filterActive = false;
        $fromDisplay = '';
        $toDisplay = '';

        if ($fromDate === false || $toDate === false) {
            $filterError = 'Please enter valid dates in YYYY-MM-DD format.';
            $fromDate = null;
            $toDate = null;
        } elseif ($fromDate !== null && $toDate !== null && $fromDate > $toDate) {
            $filterError = 'From Date cannot be after To Date.';
            $fromDate = null;
            $toDate = null;
        } else {
            $filterActive = $fromDate !== null || $toDate !== null;
            $fromDisplay = is_string($fromDate) ? $fromDate : '';
            $toDisplay = is_string($toDate) ? $toDate : '';
        }

        if ($filterError !== null) {
            $fromDisplay = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromRaw) === 1 ? $fromRaw : '';
            $toDisplay = preg_match('/^\d{4}-\d{2}-\d{2}$/', $toRaw) === 1 ? $toRaw : '';
        }

        return [
            'filter_error' => $filterError,
            'filter_active' => $filterActive,
            'from_date' => is_string($fromDate) ? $fromDate : null,
            'to_date' => is_string($toDate) ? $toDate : null,
            'from_display' => $fromDisplay,
            'to_display' => $toDisplay,
        ];
    }
}

if (!function_exists('admin_report_period_label')) {
    function admin_report_period_label(bool $filterActive, string $fromDisplay, string $toDisplay): string
    {
        if (!$filterActive) {
            return 'Lifetime';
        }
        if ($fromDisplay !== '' && $toDisplay !== '') {
            return $fromDisplay . ' to ' . $toDisplay;
        }
        if ($fromDisplay !== '') {
            return $fromDisplay . ' onwards';
        }
        return 'Up to ' . $toDisplay;
    }
}

if (!function_exists('admin_load_system_report_data')) {
    /**
     * Load system usage report aggregates. Logic must match the original reports.php queries.
     *
     * @return array{
     *   lifetime: array<string, int>,
     *   range: array<string, int>,
     *   ai_stats: array{this_month: int, average: ?float, minimum: ?float, maximum: ?float},
     *   messages_this_month: int,
     *   users_by_month: list<array{month_key: string, total: int}>,
     *   predictions_by_month: list<array{month_key: string, total: int}>,
     *   messages_by_month: list<array{month_key: string, total: int}>,
     *   properties_by_district: list<array<string, mixed>>,
     *   sold_by_district: list<array<string, mixed>>,
     *   sold_listings: list<array<string, mixed>>
     * }
     */
    function admin_load_system_report_data(
        PDO $pdo,
        bool $filterActive,
        ?string $fromDate,
        ?string $toDate
    ): array {
        $lifetime = [
            'users' => 0,
            'buyers' => 0,
            'sellers' => 0,
            'admins' => 0,
            'active_users' => 0,
            'inactive_users' => 0,
            'properties' => 0,
            'available' => 0,
            'pending' => 0,
            'sold' => 0,
            'inactive_properties' => 0,
            'predictions' => 0,
            'messages' => 0,
        ];

        $range = [
            'new_users' => 0,
            'properties_created' => 0,
            'predictions' => 0,
            'messages' => 0,
        ];

        $aiStats = [
            'this_month' => 0,
            'average' => null,
            'minimum' => null,
            'maximum' => null,
        ];

        $lifetime['users'] = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $lifetime['properties'] = (int) $pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn();
        $lifetime['predictions'] = (int) $pdo->query('SELECT COUNT(*) FROM ai_predictions')->fetchColumn();
        $lifetime['messages'] = (int) $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();

        $roleStmt = $pdo->query('SELECT role, COUNT(*) AS total FROM users GROUP BY role');
        foreach ($roleStmt->fetchAll() as $row) {
            $role = strtoupper((string) ($row['role'] ?? ''));
            $total = (int) ($row['total'] ?? 0);
            if ($role === 'BUYER') {
                $lifetime['buyers'] = $total;
            } elseif ($role === 'SELLER') {
                $lifetime['sellers'] = $total;
            } elseif ($role === 'ADMIN') {
                $lifetime['admins'] = $total;
            }
        }

        $userStatusStmt = $pdo->query('SELECT status, COUNT(*) AS total FROM users GROUP BY status');
        foreach ($userStatusStmt->fetchAll() as $row) {
            $status = strtoupper((string) ($row['status'] ?? ''));
            $total = (int) ($row['total'] ?? 0);
            if ($status === 'ACTIVE') {
                $lifetime['active_users'] = $total;
            } elseif ($status === 'INACTIVE') {
                $lifetime['inactive_users'] = $total;
            }
        }

        $propStatusStmt = $pdo->query('SELECT status, COUNT(*) AS total FROM properties GROUP BY status');
        foreach ($propStatusStmt->fetchAll() as $row) {
            $status = strtoupper((string) ($row['status'] ?? ''));
            $total = (int) ($row['total'] ?? 0);
            if ($status === 'AVAILABLE') {
                $lifetime['available'] = $total;
            } elseif ($status === 'PENDING') {
                $lifetime['pending'] = $total;
            } elseif ($status === 'SOLD') {
                $lifetime['sold'] = $total;
            } elseif ($status === 'INACTIVE') {
                $lifetime['inactive_properties'] = $total;
            }
        }

        $districtStmt = $pdo->query(
            'SELECT district, COUNT(*) AS total
             FROM properties
             GROUP BY district
             ORDER BY total DESC, district ASC'
        );
        $propertiesByDistrict = $districtStmt->fetchAll();

        $soldDistrictStmt = $pdo->query(
            "SELECT district, COUNT(*) AS total
             FROM properties
             WHERE status = 'SOLD'
             GROUP BY district
             ORDER BY total DESC, district ASC"
        );
        $soldByDistrict = $soldDistrictStmt->fetchAll();

        $soldListStmt = $pdo->query(
            "SELECT property_id, title, district, area, property_type, asking_price_lkr
             FROM properties
             WHERE status = 'SOLD'
             ORDER BY district ASC, title ASC, property_id DESC
             LIMIT 50"
        );
        $soldListings = $soldListStmt->fetchAll();

        $rangeFrom = $filterActive ? $fromDate : null;
        $rangeTo = $filterActive ? $toDate : null;

        $range['new_users'] = admin_report_count_between($pdo, 'users', $rangeFrom, $rangeTo);
        $range['properties_created'] = admin_report_count_between($pdo, 'properties', $rangeFrom, $rangeTo);
        $range['predictions'] = admin_report_count_between($pdo, 'ai_predictions', $rangeFrom, $rangeTo);
        $range['messages'] = admin_report_count_between($pdo, 'messages', $rangeFrom, $rangeTo);

        $usersByMonth = admin_report_monthly_counts($pdo, 'users', $rangeFrom, $rangeTo);
        $predictionsByMonth = admin_report_monthly_counts($pdo, 'ai_predictions', $rangeFrom, $rangeTo);
        $messagesByMonth = admin_report_monthly_counts($pdo, 'messages', $rangeFrom, $rangeTo);

        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $aiStats['this_month'] = admin_report_count_between($pdo, 'ai_predictions', $monthStart, $monthEnd);
        $messagesThisMonth = admin_report_count_between($pdo, 'messages', $monthStart, $monthEnd);

        $aiWhere = [];
        $aiParams = [];
        if ($rangeFrom !== null) {
            $aiWhere[] = 'created_at >= ?';
            $aiParams[] = $rangeFrom . ' 00:00:00';
        }
        if ($rangeTo !== null) {
            $aiWhere[] = 'created_at <= ?';
            $aiParams[] = $rangeTo . ' 23:59:59';
        }
        $aiWhereSql = $aiWhere === [] ? '' : ('WHERE ' . implode(' AND ', $aiWhere));
        $aiAggStmt = $pdo->prepare(
            "SELECT AVG(predicted_price_lkr) AS avg_price,
                    MIN(predicted_price_lkr) AS min_price,
                    MAX(predicted_price_lkr) AS max_price,
                    COUNT(*) AS total
             FROM ai_predictions
             {$aiWhereSql}"
        );
        $aiAggStmt->execute($aiParams);
        $aiAgg = $aiAggStmt->fetch();
        if (is_array($aiAgg) && (int) ($aiAgg['total'] ?? 0) > 0) {
            $aiStats['average'] = (float) $aiAgg['avg_price'];
            $aiStats['minimum'] = (float) $aiAgg['min_price'];
            $aiStats['maximum'] = (float) $aiAgg['max_price'];
        }

        return [
            'lifetime' => $lifetime,
            'range' => $range,
            'ai_stats' => $aiStats,
            'messages_this_month' => $messagesThisMonth,
            'users_by_month' => is_array($usersByMonth) ? $usersByMonth : [],
            'predictions_by_month' => is_array($predictionsByMonth) ? $predictionsByMonth : [],
            'messages_by_month' => is_array($messagesByMonth) ? $messagesByMonth : [],
            'properties_by_district' => is_array($propertiesByDistrict) ? $propertiesByDistrict : [],
            'sold_by_district' => is_array($soldByDistrict) ? $soldByDistrict : [],
            'sold_listings' => is_array($soldListings) ? $soldListings : [],
        ];
    }
}
