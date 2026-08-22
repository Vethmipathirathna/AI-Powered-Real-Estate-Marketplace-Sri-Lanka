<?php
/**
 * Shared helpers for Admin User Management.
 */

declare(strict_types=1);

if (!function_exists('admin_allowed_roles')) {
    /** @return list<string> */
    function admin_allowed_roles(): array
    {
        return ['BUYER', 'SELLER', 'ADMIN'];
    }
}

if (!function_exists('admin_allowed_statuses')) {
    /** @return list<string> */
    function admin_allowed_statuses(): array
    {
        return ['ACTIVE', 'INACTIVE'];
    }
}

if (!function_exists('admin_normalize_email')) {
    function admin_normalize_email(string $email): string
    {
        return strtolower(trim($email));
    }
}

if (!function_exists('admin_format_joined')) {
    function admin_format_joined(?string $datetime): string
    {
        if ($datetime === null || $datetime === '') {
            return '—';
        }

        $timestamp = strtotime($datetime);
        return $timestamp === false ? '—' : date('d M Y', $timestamp);
    }
}

if (!function_exists('admin_count_active_admins')) {
    function admin_count_active_admins(PDO $pdo, ?int $excludeUserId = null): int
    {
        if ($excludeUserId === null) {
            $stmt = $pdo->query(
                "SELECT COUNT(*) FROM users WHERE role = 'ADMIN' AND status = 'ACTIVE'"
            );
            return (int) $stmt->fetchColumn();
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM users
             WHERE role = 'ADMIN' AND status = 'ACTIVE' AND user_id <> ?"
        );
        $stmt->execute([$excludeUserId]);
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('admin_find_user')) {
    /**
     * @return array<string, mixed>|null
     */
    function admin_find_user(PDO $pdo, int $userId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT user_id, full_name, email, phone, role, status, created_at, updated_at
             FROM users
             WHERE user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('admin_email_taken')) {
    function admin_email_taken(PDO $pdo, string $email, ?int $excludeUserId = null): bool
    {
        if ($excludeUserId === null) {
            $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            return (bool) $stmt->fetch();
        }

        $stmt = $pdo->prepare(
            'SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1'
        );
        $stmt->execute([$email, $excludeUserId]);
        return (bool) $stmt->fetch();
    }
}

if (!function_exists('admin_validate_user_fields')) {
    /**
     * @param array{
     *   full_name?: string,
     *   email?: string,
     *   phone?: string,
     *   role?: string,
     *   status?: string
     * } $data
     * @param array{require_password?: bool, password?: string, confirm_password?: string, allow_blank_password?: bool} $passwordOpts
     * @return list<string>
     */
    function admin_validate_user_fields(array $data, array $passwordOpts = []): array
    {
        $errors = [];

        $fullName = trim((string) ($data['full_name'] ?? ''));
        $email = admin_normalize_email((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $role = strtoupper(trim((string) ($data['role'] ?? '')));
        $status = strtoupper(trim((string) ($data['status'] ?? '')));

        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        } elseif (mb_strlen($fullName) > 150) {
            $errors[] = 'Full name must be 150 characters or fewer.';
        }

        if ($email === '') {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (mb_strlen($email) > 191) {
            $errors[] = 'Email must be 191 characters or fewer.';
        }

        if ($phone !== '') {
            if (mb_strlen($phone) > 30) {
                $errors[] = 'Phone number must be 30 characters or fewer.';
            } elseif (!preg_match('/^[0-9+\-\s()]{7,30}$/', $phone)) {
                $errors[] = 'Please enter a valid phone number.';
            }
        }

        if (!in_array($role, admin_allowed_roles(), true)) {
            $errors[] = 'Please select a valid role.';
        }

        if (!in_array($status, admin_allowed_statuses(), true)) {
            $errors[] = 'Please select a valid status.';
        }

        $requirePassword = (bool) ($passwordOpts['require_password'] ?? false);
        $allowBlank = (bool) ($passwordOpts['allow_blank_password'] ?? false);
        $password = (string) ($passwordOpts['password'] ?? '');
        $confirm = (string) ($passwordOpts['confirm_password'] ?? '');

        if ($requirePassword || $password !== '' || $confirm !== '') {
            if ($password === '' && !$allowBlank) {
                $errors[] = 'Password is required.';
            } elseif ($password !== '') {
                if (strlen($password) < 8) {
                    $errors[] = 'Password must be at least 8 characters long.';
                }
                if ($password !== $confirm) {
                    $errors[] = 'Password and confirm password do not match.';
                }
            }
        }

        return $errors;
    }
}

if (!function_exists('admin_role_badge_class')) {
    function admin_role_badge_class(string $role): string
    {
        return match (strtoupper($role)) {
            'ADMIN' => 'badge-role-admin',
            'SELLER' => 'badge-role-seller',
            default => 'badge-role-buyer',
        };
    }
}

if (!function_exists('admin_status_badge_class')) {
    function admin_status_badge_class(string $status): string
    {
        return strtoupper($status) === 'ACTIVE' ? 'badge-status-active' : 'badge-status-inactive';
    }
}

if (!function_exists('admin_build_query')) {
    /**
     * @param array<string, scalar|null> $params
     */
    function admin_build_query(array $params): string
    {
        $filtered = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $filtered[$key] = (string) $value;
        }

        return $filtered === [] ? '' : ('?' . http_build_query($filtered));
    }
}

if (!function_exists('admin_allowed_property_types')) {
    /** @return list<string> */
    function admin_allowed_property_types(): array
    {
        return ['HOUSE', 'APARTMENT', 'LAND', 'COMMERCIAL'];
    }
}

if (!function_exists('admin_allowed_property_statuses')) {
    /** @return list<string> */
    function admin_allowed_property_statuses(): array
    {
        return ['AVAILABLE', 'PENDING', 'SOLD', 'INACTIVE'];
    }
}

if (!function_exists('admin_property_status_badge_class')) {
    function admin_property_status_badge_class(string $status): string
    {
        return match (strtoupper($status)) {
            'AVAILABLE' => 'badge-prop-available',
            'PENDING' => 'badge-prop-pending',
            'SOLD' => 'badge-prop-sold',
            default => 'badge-prop-inactive',
        };
    }
}

if (!function_exists('admin_format_lkr')) {
    function admin_format_lkr($amount): string
    {
        return 'LKR ' . number_format((float) $amount, 2);
    }
}

if (!function_exists('admin_yes_no')) {
    function admin_yes_no($value): string
    {
        return ((int) $value) === 1 ? 'Yes' : 'No';
    }
}

if (!function_exists('admin_image_url')) {
    /**
     * Build a public URL for a stored image path without exposing filesystem roots.
     */
    function admin_image_url(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        return url(ltrim(str_replace('\\', '/', $path), '/'));
    }
}

if (!function_exists('admin_find_property')) {
    /**
     * @return array<string, mixed>|null
     */
    function admin_find_property(PDO $pdo, int $propertyId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT p.*,
                    u.full_name AS lister_name,
                    u.email AS lister_email,
                    u.phone AS lister_phone,
                    u.role AS lister_role,
                    u.status AS lister_status
             FROM properties p
             INNER JOIN users u ON u.user_id = p.listed_by_user_id
             WHERE p.property_id = ?
             LIMIT 1'
        );
        $stmt->execute([$propertyId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('admin_property_images')) {
    /**
     * @return list<array<string, mixed>>
     */
    function admin_property_images(PDO $pdo, int $propertyId): array
    {
        $stmt = $pdo->prepare(
            'SELECT image_id, image_path, is_primary, created_at
             FROM property_images
             WHERE property_id = ?
             ORDER BY is_primary DESC, image_id ASC'
        );
        $stmt->execute([$propertyId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }
}
