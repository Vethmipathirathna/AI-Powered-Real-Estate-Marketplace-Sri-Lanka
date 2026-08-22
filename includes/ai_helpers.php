<?php
/**
 * RealEstateAI — AI estimator validation, persistence, and history helpers.
 */
declare(strict_types=1);

require_once __DIR__ . '/ai_categories.php';

if (!function_exists('ai_require_estimator_access')) {
    function ai_require_estimator_access(): void
    {
        require_role('BUYER', 'SELLER', 'ADMIN');
    }
}

if (!function_exists('ai_estimator_roles')) {
    /** @return list<string> */
    function ai_estimator_roles(): array
    {
        return ['BUYER', 'SELLER', 'ADMIN'];
    }
}

if (!function_exists('ai_encode_water_supply')) {
    function ai_encode_water_supply(string $value): int
    {
        return match ($value) {
            'Well' => 1,
            'Pipe-borne' => 2,
            'Both' => 3,
            default => 0,
        };
    }
}

if (!function_exists('ai_decode_water_supply')) {
    function ai_decode_water_supply(int $value): string
    {
        return match ($value) {
            1 => 'Well',
            2 => 'Pipe-borne',
            3 => 'Both',
            default => '—',
        };
    }
}

if (!function_exists('ai_encode_electricity')) {
    function ai_encode_electricity(string $value): int
    {
        return match ($value) {
            'Single phase' => 1,
            'Three phase' => 2,
            default => 0,
        };
    }
}

if (!function_exists('ai_decode_electricity')) {
    function ai_decode_electricity(int $value): string
    {
        return match ($value) {
            1 => 'Single phase',
            2 => 'Three phase',
            default => '—',
        };
    }
}

if (!function_exists('ai_area_other_value')) {
    function ai_area_other_value(): string
    {
        return '__other__';
    }
}

if (!function_exists('ai_default_form_values')) {
    /** @return array<string, mixed> */
    function ai_default_form_values(): array
    {
        return [
            'district' => '',
            'area_select' => '',
            'area_other' => '',
            'perch' => '',
            'bedrooms' => '',
            'bathrooms' => '',
            'kitchen_area_sqft' => '',
            'parking_spots' => '0',
            'has_garden' => '0',
            'has_ac' => '0',
            'water_supply' => '',
            'electricity' => '',
            'floors' => '',
            'year_built' => (string) (int) date('Y'),
        ];
    }
}

if (!function_exists('ai_resolve_area_value')) {
    function ai_resolve_area_value(array $input): string
    {
        $selected = trim((string) ($input['area_select'] ?? ''));
        $other = trim((string) ($input['area_other'] ?? ''));

        if ($selected === ai_area_other_value()) {
            return $other;
        }

        return $selected;
    }
}

if (!function_exists('ai_validate_form_input')) {
    /**
     * @param array<string, mixed> $input
     * @return array{0: array<string, mixed>|null, 1: list<string>, 2: array<string, mixed>}
     */
    function ai_validate_form_input(array $input): array
    {
        $errors = [];
        $form = array_merge(ai_default_form_values(), $input);
        $clean = [];

        $district = trim((string) ($input['district'] ?? ''));
        $form['district'] = $district;
        if ($district === '') {
            $errors[] = 'District is required.';
        } elseif (!in_array($district, ai_model_districts(), true)) {
            $errors[] = 'Please select a valid district.';
        } else {
            $clean['district'] = $district;
        }

        $areaSelect = trim((string) ($input['area_select'] ?? ''));
        $areaOther = trim((string) ($input['area_other'] ?? ''));
        $form['area_select'] = $areaSelect;
        $form['area_other'] = $areaOther;

        if ($areaSelect === '') {
            $errors[] = 'Area is required.';
        } elseif ($areaSelect === ai_area_other_value()) {
            if ($areaOther === '') {
                $errors[] = 'Please enter your area.';
            } elseif (mb_strlen($areaOther) > 150) {
                $errors[] = 'Area must be 150 characters or fewer.';
            } else {
                $clean['area'] = $areaOther;
            }
        } elseif (!in_array($areaSelect, ai_model_areas(), true)) {
            $errors[] = 'Please select a valid area.';
        } else {
            $clean['area'] = $areaSelect;
        }

        $perch = trim((string) ($input['perch'] ?? ''));
        $form['perch'] = $perch;
        if ($perch === '' || !is_numeric($perch) || (float) $perch <= 0) {
            $errors[] = 'Land size (perch) must be greater than zero.';
        } else {
            $clean['perch'] = (float) $perch;
        }

        foreach (['bedrooms', 'bathrooms', 'parking_spots'] as $field) {
            $raw = trim((string) ($input[$field] ?? ''));
            $form[$field] = $raw;
            if ($raw === '' || !ctype_digit($raw)) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' must be a whole number.';
                continue;
            }
            $value = (int) $raw;
            if ($value < 0) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' cannot be negative.';
                continue;
            }
            $clean[$field] = $value;
        }

        $kitchen = trim((string) ($input['kitchen_area_sqft'] ?? ''));
        $form['kitchen_area_sqft'] = $kitchen;
        if ($kitchen === '' || !is_numeric($kitchen) || (float) $kitchen < 0) {
            $errors[] = 'Kitchen area must be zero or greater.';
        } else {
            $clean['kitchen_area_sqft'] = (float) $kitchen;
        }

        foreach (['has_garden', 'has_ac'] as $field) {
            $raw = (string) ($input[$field] ?? '');
            $form[$field] = $raw;
            if (!in_array($raw, ['0', '1'], true)) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' must be Yes or No.';
                continue;
            }
            $clean[$field] = $raw === '1';
        }

        $water = trim((string) ($input['water_supply'] ?? ''));
        $form['water_supply'] = $water;
        if (!in_array($water, ai_water_supply_options(), true)) {
            $errors[] = 'Please select a valid water supply option.';
        } else {
            $clean['water_supply'] = $water;
        }

        $electricity = trim((string) ($input['electricity'] ?? ''));
        $form['electricity'] = $electricity;
        if (!in_array($electricity, ai_electricity_options(), true)) {
            $errors[] = 'Please select a valid electricity option.';
        } else {
            $clean['electricity'] = $electricity;
        }

        $floors = trim((string) ($input['floors'] ?? ''));
        $form['floors'] = $floors;
        if ($floors === '' || !ctype_digit($floors) || (int) $floors < 1) {
            $errors[] = 'Floors must be at least 1.';
        } else {
            $clean['floors'] = (int) $floors;
        }

        $yearBuilt = trim((string) ($input['year_built'] ?? ''));
        $form['year_built'] = $yearBuilt;
        $maxYear = (int) date('Y') + 1;
        if ($yearBuilt === '' || !ctype_digit($yearBuilt)) {
            $errors[] = 'Year built must be a valid year.';
        } else {
            $year = (int) $yearBuilt;
            if ($year < 1800 || $year > $maxYear) {
                $errors[] = 'Year built must be between 1800 and ' . $maxYear . '.';
            } else {
                $clean['year_built'] = $year;
            }
        }

        if ($errors !== []) {
            return [null, $errors, $form];
        }

        return [$clean, [], $form];
    }
}

if (!function_exists('ai_build_api_payload')) {
    /**
     * @param array<string, mixed> $clean
     * @return array<string, mixed>
     */
    function ai_build_api_payload(array $clean): array
    {
        return [
            'district' => (string) $clean['district'],
            'area' => (string) $clean['area'],
            'perch' => (float) $clean['perch'],
            'bedrooms' => (int) $clean['bedrooms'],
            'bathrooms' => (int) $clean['bathrooms'],
            'kitchen_area_sqft' => (float) $clean['kitchen_area_sqft'],
            'parking_spots' => (int) $clean['parking_spots'],
            'has_garden' => (bool) $clean['has_garden'],
            'has_ac' => (bool) $clean['has_ac'],
            'water_supply' => (string) $clean['water_supply'],
            'electricity' => (string) $clean['electricity'],
            'floors' => (int) $clean['floors'],
            'year_built' => (int) $clean['year_built'],
        ];
    }
}

if (!function_exists('ai_save_prediction')) {
    /**
     * @param array<string, mixed> $clean
     * @param array{predicted_price_lkr: float, model_version?: string} $prediction
     */
    function ai_save_prediction(PDO $pdo, int $userId, array $clean, array $prediction): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO ai_predictions (
                user_id, district, area, perch, bedrooms, bathrooms, kitchen_area_sqft,
                parking_spots, has_garden, has_ac, water_supply, electricity, floors,
                year_built, predicted_price_lkr, model_version
             ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
             )'
        );
        $stmt->execute([
            $userId,
            (string) $clean['district'],
            (string) $clean['area'],
            (float) $clean['perch'],
            (int) $clean['bedrooms'],
            (int) $clean['bathrooms'],
            (float) $clean['kitchen_area_sqft'],
            (int) $clean['parking_spots'],
            (int) ((bool) $clean['has_garden'] ? 1 : 0),
            (int) ((bool) $clean['has_ac'] ? 1 : 0),
            ai_encode_water_supply((string) $clean['water_supply']),
            ai_encode_electricity((string) $clean['electricity']),
            (int) $clean['floors'],
            (int) $clean['year_built'],
            round((float) $prediction['predicted_price_lkr'], 2),
            (string) ($prediction['model_version'] ?? 'rf_100_depth20_v1'),
        ]);
    }
}

if (!function_exists('ai_fetch_user_history')) {
    /**
     * @return list<array<string, mixed>>
     */
    function ai_fetch_user_history(PDO $pdo, int $userId, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $pdo->prepare(
            'SELECT prediction_id, district, area, perch, bedrooms, bathrooms, kitchen_area_sqft,
                    parking_spots, has_garden, has_ac, water_supply, electricity, floors,
                    year_built, predicted_price_lkr, model_version, created_at
             FROM ai_predictions
             WHERE user_id = ?
             ORDER BY created_at DESC, prediction_id DESC
             LIMIT ' . $limit
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('ai_fetch_user_prediction')) {
    /**
     * Fetch a single prediction owned by the given user.
     *
     * @return array<string, mixed>|null
     */
    function ai_fetch_user_prediction(PDO $pdo, int $userId, int $predictionId): ?array
    {
        if ($userId <= 0 || $predictionId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT prediction_id, district, area, perch, bedrooms, bathrooms, kitchen_area_sqft,
                    parking_spots, has_garden, has_ac, water_supply, electricity, floors,
                    year_built, predicted_price_lkr, created_at
             FROM ai_predictions
             WHERE prediction_id = ?
               AND user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$predictionId, $userId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('ai_format_prediction_datetime')) {
    function ai_format_prediction_datetime(?string $datetime): string
    {
        if ($datetime === null || $datetime === '') {
            return '—';
        }
        $ts = strtotime($datetime);
        return $ts === false ? '—' : date('d M Y, H:i', $ts);
    }
}

if (!function_exists('ai_property_summary')) {
    function ai_property_summary(array $row): string
    {
        $parts = [];
        if (isset($row['perch'])) {
            $parts[] = (string) $row['perch'] . ' perch';
        }
        if (isset($row['bedrooms'], $row['bathrooms'])) {
            $parts[] = (int) $row['bedrooms'] . ' bed / ' . (int) $row['bathrooms'] . ' bath';
        }
        if (isset($row['floors'])) {
            $parts[] = (int) $row['floors'] . ' floor(s)';
        }
        if (isset($row['year_built'])) {
            $parts[] = 'Built ' . (int) $row['year_built'];
        }
        return $parts !== [] ? implode(' · ', $parts) : '—';
    }
}

if (!function_exists('ai_store_result_flash')) {
    /** @param array<string, mixed> $result */
    function ai_store_result_flash(array $result): void
    {
        start_app_session();
        $_SESSION['ai_estimate_result'] = $result;
    }
}

if (!function_exists('ai_pull_result_flash')) {
    /** @return array<string, mixed>|null */
    function ai_pull_result_flash(): ?array
    {
        start_app_session();
        if (empty($_SESSION['ai_estimate_result']) || !is_array($_SESSION['ai_estimate_result'])) {
            return null;
        }
        $result = $_SESSION['ai_estimate_result'];
        unset($_SESSION['ai_estimate_result']);
        return $result;
    }
}
