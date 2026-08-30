<?php
/**
 * User → Admin support messaging (property_id IS NULL only).
 *
 * Completely separate from property marketplace messaging in messaging.php.
 *
 * Multi-admin assignment policy:
 * - New support threads are assigned to the lowest active ADMIN user_id.
 * - Replies stay between that user and the assigned Admin.
 * - Clients never choose receiver_id.
 */
declare(strict_types=1);

require_once __DIR__ . '/messaging.php';

if (!function_exists('support_find_active_admin')) {
    /**
     * Deterministic active Admin: lowest user_id among ACTIVE ADMIN accounts.
     *
     * @return array{user_id: int, full_name: string}|null
     */
    function support_find_active_admin(PDO $pdo): ?array
    {
        $stmt = $pdo->query(
            "SELECT user_id, full_name
             FROM users
             WHERE role = 'ADMIN' AND status = 'ACTIVE'
             ORDER BY user_id ASC
             LIMIT 1"
        );
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }
        return [
            'user_id' => (int) $row['user_id'],
            'full_name' => (string) ($row['full_name'] ?? 'Support Admin'),
        ];
    }
}

if (!function_exists('support_user_is_active_admin')) {
    function support_user_is_active_admin(PDO $pdo, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $stmt = $pdo->prepare(
            "SELECT 1 FROM users
             WHERE user_id = ? AND role = 'ADMIN' AND status = 'ACTIVE'
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        return (bool) $stmt->fetch();
    }
}

if (!function_exists('support_user_is_buyer_or_seller')) {
    function support_user_is_buyer_or_seller(PDO $pdo, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $stmt = $pdo->prepare(
            "SELECT role FROM users WHERE user_id = ? AND status = 'ACTIVE' LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return false;
        }
        $role = strtoupper((string) ($row['role'] ?? ''));
        return $role === 'BUYER' || $role === 'SELLER';
    }
}

if (!function_exists('support_find_assigned_admin_for_user')) {
    /**
     * Find Admin already assigned to this user's support thread (first message).
     */
    function support_find_assigned_admin_for_user(PDO $pdo, int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }
        $stmt = $pdo->prepare(
            'SELECT CASE
                        WHEN m.sender_id = ? THEN m.receiver_id
                        ELSE m.sender_id
                    END AS admin_id
             FROM messages m
             WHERE m.property_id IS NULL
               AND (m.sender_id = ? OR m.receiver_id = ?)
             ORDER BY m.message_id ASC
             LIMIT 1'
        );
        $stmt->execute([$userId, $userId, $userId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }
        $adminId = (int) ($row['admin_id'] ?? 0);
        return $adminId > 0 ? $adminId : null;
    }
}

if (!function_exists('support_resolve_admin_for_user')) {
    /**
     * Existing assigned Admin, or lowest active Admin for a new thread.
     */
    function support_resolve_admin_for_user(PDO $pdo, int $userId): ?array
    {
        $assignedId = support_find_assigned_admin_for_user($pdo, $userId);
        if ($assignedId !== null) {
            $stmt = $pdo->prepare(
                'SELECT user_id, full_name, role, status FROM users WHERE user_id = ? LIMIT 1'
            );
            $stmt->execute([$assignedId]);
            $row = $stmt->fetch();
            if (is_array($row) && strtoupper((string) ($row['role'] ?? '')) === 'ADMIN') {
                return [
                    'user_id' => (int) $row['user_id'],
                    'full_name' => (string) ($row['full_name'] ?? 'Support Admin'),
                ];
            }
        }
        return support_find_active_admin($pdo);
    }
}

if (!function_exists('support_can_access_as_user')) {
    /**
     * BUYER/SELLER may only access their own support thread with assigned Admin.
     *
     * @param array{user_id?: int, role?: string} $user
     */
    function support_can_access_as_user(PDO $pdo, array $user): bool
    {
        $role = strtoupper((string) ($user['role'] ?? ''));
        $userId = (int) ($user['user_id'] ?? 0);
        if ($userId <= 0 || !in_array($role, ['BUYER', 'SELLER'], true)) {
            return false;
        }
        return support_user_is_buyer_or_seller($pdo, $userId);
    }
}

if (!function_exists('support_can_access_as_admin')) {
    /**
     * Admin may open a support conversation only if they are the assigned participant.
     *
     * @param array{user_id?: int, role?: string} $admin
     */
    function support_can_access_as_admin(PDO $pdo, array $admin, int $otherUserId): bool
    {
        $adminId = (int) ($admin['user_id'] ?? 0);
        $role = strtoupper((string) ($admin['role'] ?? ''));
        if ($adminId <= 0 || $role !== 'ADMIN' || $otherUserId <= 0) {
            return false;
        }
        if (!support_user_is_active_admin($pdo, $adminId)) {
            return false;
        }
        if (!support_user_is_buyer_or_seller($pdo, $otherUserId)) {
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT 1
             FROM messages
             WHERE property_id IS NULL
               AND (
                    (sender_id = ? AND receiver_id = ?)
                 OR (sender_id = ? AND receiver_id = ?)
               )
             LIMIT 1'
        );
        $stmt->execute([$adminId, $otherUserId, $otherUserId, $adminId]);
        return (bool) $stmt->fetch();
    }
}

if (!function_exists('support_fetch_thread')) {
    /**
     * Chronological support messages between user and admin (property_id IS NULL only).
     *
     * @return list<array<string, mixed>>
     */
    function support_fetch_thread(PDO $pdo, int $userId, int $adminId): array
    {
        if ($userId <= 0 || $adminId <= 0) {
            return [];
        }
        $stmt = $pdo->prepare(
            'SELECT message_id, sender_id, receiver_id, message_text, is_read, created_at, property_id
             FROM messages
             WHERE property_id IS NULL
               AND (
                    (sender_id = ? AND receiver_id = ?)
                 OR (sender_id = ? AND receiver_id = ?)
               )
             ORDER BY created_at ASC, message_id ASC'
        );
        $stmt->execute([$userId, $adminId, $adminId, $userId]);
        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }
        // Safety: never return rows that somehow have a property_id.
        $out = [];
        foreach ($rows as $row) {
            if (($row['property_id'] ?? null) !== null) {
                continue;
            }
            $out[] = $row;
        }
        return $out;
    }
}

if (!function_exists('support_mark_read')) {
    function support_mark_read(PDO $pdo, int $viewerId, int $otherUserId): void
    {
        if ($viewerId <= 0 || $otherUserId <= 0) {
            return;
        }
        $stmt = $pdo->prepare(
            'UPDATE messages
             SET is_read = 1
             WHERE property_id IS NULL
               AND receiver_id = ?
               AND sender_id = ?
               AND is_read = 0'
        );
        $stmt->execute([$viewerId, $otherUserId]);
    }
}

if (!function_exists('support_send_from_user')) {
    /**
     * BUYER/SELLER send support message. Receiver chosen server-side.
     *
     * @param array{user_id?: int, role?: string} $user
     * @return array{0: bool, 1: string}
     */
    function support_send_from_user(PDO $pdo, array $user, string $messageText): array
    {
        [$errors, $text] = message_validate_text($messageText);
        if ($errors !== []) {
            return [false, $errors[0]];
        }

        if (!support_can_access_as_user($pdo, $user)) {
            return [false, 'You are not allowed to contact support.'];
        }

        $userId = (int) ($user['user_id'] ?? 0);
        $admin = support_resolve_admin_for_user($pdo, $userId);
        if ($admin === null) {
            return [false, 'Support is temporarily unavailable. Please try again later.'];
        }

        $adminId = (int) $admin['user_id'];
        $stmt = $pdo->prepare(
            'INSERT INTO messages (sender_id, receiver_id, property_id, message_text, is_read)
             VALUES (?, ?, NULL, ?, 0)'
        );
        $stmt->execute([$userId, $adminId, $text]);

        return [true, 'Your message was sent to support.'];
    }
}

if (!function_exists('support_send_from_admin')) {
    /**
     * Assigned Admin replies to a BUYER/SELLER support thread.
     *
     * @param array{user_id?: int, role?: string} $admin
     * @return array{0: bool, 1: string}
     */
    function support_send_from_admin(PDO $pdo, array $admin, int $otherUserId, string $messageText): array
    {
        [$errors, $text] = message_validate_text($messageText);
        if ($errors !== []) {
            return [false, $errors[0]];
        }

        if (!support_can_access_as_admin($pdo, $admin, $otherUserId)) {
            return [false, 'You cannot reply to this support conversation.'];
        }

        $adminId = (int) ($admin['user_id'] ?? 0);
        $stmt = $pdo->prepare(
            'INSERT INTO messages (sender_id, receiver_id, property_id, message_text, is_read)
             VALUES (?, ?, NULL, ?, 0)'
        );
        $stmt->execute([$adminId, $otherUserId, $text]);

        return [true, 'Reply sent.'];
    }
}

if (!function_exists('support_unread_count_for_user')) {
    function support_unread_count_for_user(PDO $pdo, int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM messages m
             INNER JOIN users sender ON sender.user_id = m.sender_id
             WHERE m.property_id IS NULL
               AND m.receiver_id = ?
               AND m.is_read = 0
               AND sender.role = \'ADMIN\''
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('support_unread_count_for_admin')) {
    function support_unread_count_for_admin(PDO $pdo, int $adminId): int
    {
        if ($adminId <= 0) {
            return 0;
        }
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM messages m
             INNER JOIN users sender ON sender.user_id = m.sender_id
             WHERE m.property_id IS NULL
               AND m.receiver_id = ?
               AND m.is_read = 0
               AND sender.role IN (\'BUYER\', \'SELLER\')'
        );
        $stmt->execute([$adminId]);
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('support_inbox_for_admin')) {
    /**
     * Support conversations assigned to this Admin (property_id IS NULL only).
     *
     * @return list<array<string, mixed>>
     */
    function support_inbox_for_admin(PDO $pdo, int $adminId): array
    {
        if ($adminId <= 0) {
            return [];
        }

        $stmt = $pdo->prepare(
            'SELECT u.user_id AS user_id,
                    u.full_name AS user_name,
                    u.role AS user_role,
                    lm.message_text AS last_message,
                    lm.created_at AS last_activity,
                    (
                        SELECT COUNT(*)
                        FROM messages um
                        WHERE um.property_id IS NULL
                          AND um.receiver_id = ?
                          AND um.sender_id = u.user_id
                          AND um.is_read = 0
                    ) AS unread_count
             FROM (
                SELECT CASE
                           WHEN m.sender_id = ? THEN m.receiver_id
                           ELSE m.sender_id
                       END AS other_user_id,
                       MAX(m.message_id) AS last_message_id
                FROM messages m
                WHERE m.property_id IS NULL
                  AND (m.sender_id = ? OR m.receiver_id = ?)
                GROUP BY other_user_id
             ) conv
             INNER JOIN messages lm ON lm.message_id = conv.last_message_id
             INNER JOIN users u ON u.user_id = conv.other_user_id
             WHERE u.role IN (\'BUYER\', \'SELLER\')
               AND lm.property_id IS NULL
             ORDER BY lm.created_at DESC, lm.message_id DESC'
        );
        $stmt->execute([$adminId, $adminId, $adminId, $adminId]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('support_get_user_context')) {
    /**
     * @param array{user_id?: int, role?: string} $user
     * @return array{admin_id: int, admin_name: string, thread: list<array<string, mixed>>}|null
     */
    function support_get_user_context(PDO $pdo, array $user): ?array
    {
        if (!support_can_access_as_user($pdo, $user)) {
            return null;
        }
        $userId = (int) ($user['user_id'] ?? 0);
        $admin = support_resolve_admin_for_user($pdo, $userId);
        if ($admin === null) {
            return null;
        }
        $adminId = (int) $admin['user_id'];
        return [
            'admin_id' => $adminId,
            'admin_name' => (string) $admin['full_name'],
            'thread' => support_fetch_thread($pdo, $userId, $adminId),
        ];
    }
}

if (!function_exists('support_get_admin_context')) {
    /**
     * @param array{user_id?: int, role?: string} $admin
     * @return array{user_id: int, user_name: string, user_role: string, thread: list<array<string, mixed>>}|null
     */
    function support_get_admin_context(PDO $pdo, array $admin, int $otherUserId): ?array
    {
        if (!support_can_access_as_admin($pdo, $admin, $otherUserId)) {
            return null;
        }
        $stmt = $pdo->prepare(
            'SELECT user_id, full_name, role FROM users WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$otherUserId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }
        $adminId = (int) ($admin['user_id'] ?? 0);
        return [
            'user_id' => (int) $row['user_id'],
            'user_name' => (string) ($row['full_name'] ?? ''),
            'user_role' => strtoupper((string) ($row['role'] ?? '')),
            'thread' => support_fetch_thread($pdo, $otherUserId, $adminId),
        ];
    }
}
