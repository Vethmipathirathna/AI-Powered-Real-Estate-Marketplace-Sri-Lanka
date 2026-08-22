<?php
/**
 * RealEstateAI — Property ownership helpers
 *
 * Canonical ownership column: properties.listed_by_user_id → users.user_id
 * Valid lister roles: SELLER, ADMIN
 */

declare(strict_types=1);

if (!function_exists('property_lister_roles')) {
    /** @return list<string> */
    function property_lister_roles(): array
    {
        return ['SELLER', 'ADMIN'];
    }
}

if (!function_exists('is_valid_property_lister_role')) {
    function is_valid_property_lister_role(?string $role): bool
    {
        if ($role === null || $role === '') {
            return false;
        }

        return in_array(strtoupper($role), property_lister_roles(), true);
    }
}

if (!function_exists('can_list_properties')) {
    /**
     * @param array{user_id?: int, role?: string}|null $user
     */
    function can_list_properties(?array $user): bool
    {
        if ($user === null) {
            return false;
        }

        return is_valid_property_lister_role((string) ($user['role'] ?? ''));
    }
}

if (!function_exists('user_owns_property_row')) {
    /**
     * @param array<string, mixed> $property Must include listed_by_user_id
     * @param array{user_id?: int}|null $user
     */
    function user_owns_property_row(array $property, ?array $user): bool
    {
        if ($user === null || empty($user['user_id'])) {
            return false;
        }

        return (int) ($property['listed_by_user_id'] ?? 0) === (int) $user['user_id'];
    }
}

if (!function_exists('can_edit_own_property')) {
    /**
     * SELLER or ADMIN may edit listing content they own.
     *
     * @param array{user_id?: int, role?: string}|null $user
     * @param array<string, mixed> $property
     */
    function can_edit_own_property(?array $user, array $property): bool
    {
        if (!can_list_properties($user)) {
            return false;
        }

        return user_owns_property_row($property, $user);
    }
}

if (!function_exists('can_manage_property')) {
    /**
     * SELLER: own listings only.
     * ADMIN: own listings + global moderation of all listings.
     *
     * @param array{user_id?: int, role?: string}|null $user
     * @param array<string, mixed> $property
     */
    function can_manage_property(?array $user, array $property): bool
    {
        if ($user === null) {
            return false;
        }

        $role = strtoupper((string) ($user['role'] ?? ''));

        if ($role === 'ADMIN') {
            return true;
        }

        if ($role === 'SELLER') {
            return user_owns_property_row($property, $user);
        }

        return false;
    }
}

if (!function_exists('can_manage_property_by_id')) {
    /**
     * @param array{user_id?: int, role?: string}|null $user
     */
    function can_manage_property_by_id(PDO $pdo, ?array $user, int $propertyId): bool
    {
        if ($user === null || $propertyId <= 0) {
            return false;
        }

        $role = strtoupper((string) ($user['role'] ?? ''));
        if ($role === 'ADMIN') {
            return true;
        }

        if ($role !== 'SELLER') {
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT listed_by_user_id FROM properties WHERE property_id = ? LIMIT 1'
        );
        $stmt->execute([$propertyId]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return false;
        }

        return user_owns_property_row($row, $user);
    }
}
