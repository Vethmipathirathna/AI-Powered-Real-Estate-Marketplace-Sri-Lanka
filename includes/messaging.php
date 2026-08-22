<?php
/**
 * Property-related messaging helpers.
 *
 * Conversation key: (property_id, buyer user_id, lister user_id)
 * Lister is always properties.listed_by_user_id (SELLER or ADMIN).
 */

declare(strict_types=1);

require_once __DIR__ . '/property_ownership.php';

if (!defined('MESSAGE_MAX_LENGTH')) {
    define('MESSAGE_MAX_LENGTH', 2000);
}

if (!function_exists('messages_path_for_role')) {
    function messages_path_for_role(string $role): string
    {
        return match (strtoupper($role)) {
            'BUYER' => 'buyer/messages.php',
            'SELLER' => 'seller/messages.php',
            'ADMIN' => 'admin/messages.php',
            default => 'index.php',
        };
    }
}

if (!function_exists('message_conversation_path_for_role')) {
    function message_conversation_path_for_role(string $role, int $propertyId, ?int $buyerId = null): string
    {
        $propertyId = max(0, $propertyId);
        $buyerId = max(0, (int) $buyerId);
        return match (strtoupper($role)) {
            'BUYER' => 'buyer/conversation.php?property_id=' . $propertyId,
            'SELLER' => 'seller/conversation.php?property_id=' . $propertyId . '&buyer_id=' . $buyerId,
            'ADMIN' => 'admin/conversation.php?property_id=' . $propertyId . '&buyer_id=' . $buyerId,
            default => 'index.php',
        };
    }
}

if (!function_exists('message_format_datetime')) {
    function message_format_datetime(?string $datetime): string
    {
        if ($datetime === null || $datetime === '') {
            return '—';
        }
        $ts = strtotime($datetime);
        return $ts === false ? '—' : date('d M Y, H:i', $ts);
    }
}

if (!function_exists('message_preview')) {
    function message_preview(?string $text, int $max = 120): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text) ?? '');
        if ($text === '') {
            return '—';
        }
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        return mb_substr($text, 0, $max - 1) . '…';
    }
}

if (!function_exists('message_validate_text')) {
    /**
     * @return array{0: list<string>, 1: string}
     */
    function message_validate_text(string $raw): array
    {
        $errors = [];
        $text = trim($raw);
        if ($text === '') {
            $errors[] = 'Message cannot be empty.';
        } elseif (mb_strlen($text) > MESSAGE_MAX_LENGTH) {
            $errors[] = 'Message must be ' . MESSAGE_MAX_LENGTH . ' characters or fewer.';
        }
        return [$errors, $text];
    }
}

if (!function_exists('message_find_property_row')) {
    /**
     * @return array<string, mixed>|null
     */
    function message_find_property_row(PDO $pdo, int $propertyId): ?array
    {
        if ($propertyId <= 0) {
            return null;
        }
        $stmt = $pdo->prepare(
            'SELECT p.property_id, p.title, p.status, p.district, p.asking_price_lkr, p.listed_by_user_id,
                    u.full_name AS lister_name, u.role AS lister_role
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

if (!function_exists('message_property_available_for_new_contact')) {
    function message_property_available_for_new_contact(?array $property): bool
    {
        return $property !== null && strtoupper((string) ($property['status'] ?? '')) === 'AVAILABLE';
    }
}

if (!function_exists('message_is_valid_lister')) {
    function message_is_valid_lister(?array $property): bool
    {
        if ($property === null) {
            return false;
        }
        return is_valid_property_lister_role((string) ($property['lister_role'] ?? ''));
    }
}

if (!function_exists('message_conversation_exists')) {
    function message_conversation_exists(PDO $pdo, int $propertyId, int $buyerId, int $listerId): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM messages
             WHERE property_id = ?
               AND (
                    (sender_id = ? AND receiver_id = ?)
                 OR (sender_id = ? AND receiver_id = ?)
               )
             LIMIT 1'
        );
        $stmt->execute([$propertyId, $buyerId, $listerId, $listerId, $buyerId]);
        return (bool) $stmt->fetch();
    }
}

if (!function_exists('message_resolve_buyer_participant')) {
    /**
     * Resolve the BUYER participant for a property conversation.
     * For BUYER role, returns own id. For lister, requires buyer_id param.
     */
    function message_resolve_buyer_participant(array $user, ?int $buyerIdParam, int $listerId): ?int
    {
        $role = strtoupper((string) ($user['role'] ?? ''));
        $userId = (int) ($user['user_id'] ?? 0);
        if ($role === 'BUYER') {
            return $userId > 0 ? $userId : null;
        }
        if ($buyerIdParam !== null && $buyerIdParam > 0) {
            return $buyerIdParam;
        }
        return null;
    }
}

if (!function_exists('message_can_access_conversation')) {
    /**
     * Verify participant membership for an existing or starting conversation.
     *
     * @param array{user_id?: int, role?: string} $user
     */
    function message_can_access_conversation(
        PDO $pdo,
        array $user,
        int $propertyId,
        ?int $buyerIdParam = null,
        bool $allowNewContact = false
    ): bool {
        $property = message_find_property_row($pdo, $propertyId);
        if ($property === null || !message_is_valid_lister($property)) {
            return false;
        }

        $role = strtoupper((string) ($user['role'] ?? ''));
        $userId = (int) ($user['user_id'] ?? 0);
        $listerId = (int) ($property['listed_by_user_id'] ?? 0);

        if ($role === 'BUYER') {
            if ($userId <= 0 || $listerId <= 0 || $userId === $listerId) {
                return false;
            }
            $exists = message_conversation_exists($pdo, $propertyId, $userId, $listerId);
            if ($exists) {
                return true;
            }
            return $allowNewContact && message_property_available_for_new_contact($property);
        }

        if ($role === 'SELLER') {
            if ($userId !== $listerId) {
                return false;
            }
            $buyerId = message_resolve_buyer_participant($user, $buyerIdParam, $listerId);
            if ($buyerId === null || $buyerId <= 0) {
                return false;
            }
            return message_conversation_exists($pdo, $propertyId, $buyerId, $listerId)
                && message_user_is_buyer($pdo, $buyerId);
        }

        if ($role === 'ADMIN') {
            // Admin messaging: own listed properties only — not global moderation access.
            if ($userId !== $listerId) {
                return false;
            }
            $buyerId = message_resolve_buyer_participant($user, $buyerIdParam, $listerId);
            if ($buyerId === null || $buyerId <= 0) {
                return false;
            }
            return message_conversation_exists($pdo, $propertyId, $buyerId, $listerId)
                && message_user_is_buyer($pdo, $buyerId);
        }

        return false;
    }
}

if (!function_exists('message_user_is_buyer')) {
    function message_user_is_buyer(PDO $pdo, int $userId): bool
    {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return is_array($row) && strtoupper((string) ($row['role'] ?? '')) === 'BUYER';
    }
}

if (!function_exists('message_get_context')) {
    /**
     * @param array{user_id?: int, role?: string} $user
     * @return array<string, mixed>|null
     */
    function message_get_context(
        PDO $pdo,
        array $user,
        int $propertyId,
        ?int $buyerIdParam = null,
        bool $allowNewContact = false
    ): ?array {
        if (!message_can_access_conversation($pdo, $user, $propertyId, $buyerIdParam, $allowNewContact)) {
            return null;
        }

        $property = message_find_property_row($pdo, $propertyId);
        if ($property === null) {
            return null;
        }

        $role = strtoupper((string) ($user['role'] ?? ''));
        $userId = (int) ($user['user_id'] ?? 0);
        $listerId = (int) ($property['listed_by_user_id'] ?? 0);
        $buyerId = $role === 'BUYER' ? $userId : (int) $buyerIdParam;

        $stmt = $pdo->prepare("SELECT full_name, role FROM users WHERE user_id = ? LIMIT 1");
        $stmt->execute([$buyerId]);
        $buyer = $stmt->fetch();

        return [
            'property' => $property,
            'property_id' => $propertyId,
            'buyer_id' => $buyerId,
            'buyer_name' => is_array($buyer) ? (string) ($buyer['full_name'] ?? '') : '',
            'buyer_role' => is_array($buyer) ? (string) ($buyer['role'] ?? '') : '',
            'lister_id' => $listerId,
            'lister_name' => (string) ($property['lister_name'] ?? ''),
            'lister_role' => (string) ($property['lister_role'] ?? ''),
            'is_available' => message_property_available_for_new_contact($property),
            'viewer_role' => $role,
            'viewer_id' => $userId,
        ];
    }
}

if (!function_exists('message_fetch_thread')) {
    /**
     * @return list<array<string, mixed>>
     */
    function message_fetch_thread(PDO $pdo, int $propertyId, int $buyerId, int $listerId): array
    {
        $stmt = $pdo->prepare(
            'SELECT message_id, sender_id, receiver_id, message_text, is_read, created_at
             FROM messages
             WHERE property_id = ?
               AND (
                    (sender_id = ? AND receiver_id = ?)
                 OR (sender_id = ? AND receiver_id = ?)
               )
             ORDER BY created_at ASC, message_id ASC'
        );
        $stmt->execute([$propertyId, $buyerId, $listerId, $listerId, $buyerId]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('message_mark_read')) {
    function message_mark_read(PDO $pdo, int $viewerId, int $propertyId, int $otherUserId): void
    {
        $stmt = $pdo->prepare(
            'UPDATE messages
             SET is_read = 1
             WHERE property_id = ?
               AND receiver_id = ?
               AND sender_id = ?
               AND is_read = 0'
        );
        $stmt->execute([$propertyId, $viewerId, $otherUserId]);
    }
}

if (!function_exists('message_send')) {
    /**
     * @param array{user_id?: int, role?: string} $user
     * @return array{0: bool, 1: string}
     */
    function message_send(
        PDO $pdo,
        array $user,
        int $propertyId,
        string $messageText,
        ?int $buyerIdParam = null
    ): array {
        [$errors, $text] = message_validate_text($messageText);
        if ($errors !== []) {
            return [false, $errors[0]];
        }

        $property = message_find_property_row($pdo, $propertyId);
        if ($property === null || !message_is_valid_lister($property)) {
            return [false, 'Property not found.'];
        }

        $role = strtoupper((string) ($user['role'] ?? ''));
        $userId = (int) ($user['user_id'] ?? 0);
        $listerId = (int) ($property['listed_by_user_id'] ?? 0);
        $buyerId = $role === 'BUYER' ? $userId : (int) $buyerIdParam;
        $exists = message_conversation_exists($pdo, $propertyId, $buyerId, $listerId);

        if (!$exists && !message_property_available_for_new_contact($property)) {
            return [false, 'This property is not available for new conversations.'];
        }

        if ($role === 'BUYER') {
            if ($userId === $listerId) {
                return [false, 'Invalid conversation.'];
            }
            if (!$exists && !message_property_available_for_new_contact($property)) {
                return [false, 'This property is not available for new conversations.'];
            }
            if (!message_can_access_conversation($pdo, $user, $propertyId, null, true)) {
                return [false, 'You cannot message this lister.'];
            }
            $receiverId = $listerId;
        } elseif ($role === 'SELLER' || $role === 'ADMIN') {
            if ($userId !== $listerId) {
                return [false, 'You cannot access this conversation.'];
            }
            if ($buyerId <= 0 || !message_user_is_buyer($pdo, $buyerId)) {
                return [false, 'Invalid conversation participant.'];
            }
            if (!$exists) {
                return [false, 'Conversation not found.'];
            }
            $receiverId = $buyerId;
        } else {
            return [false, 'You are not allowed to send messages.'];
        }

        // Ignore spoofed receiver from POST — always derived above.
        $stmt = $pdo->prepare(
            'INSERT INTO messages (sender_id, receiver_id, property_id, message_text, is_read)
             VALUES (?, ?, ?, ?, 0)'
        );
        $stmt->execute([$userId, $receiverId, $propertyId, $text]);

        return [true, 'Message sent.'];
    }
}

if (!function_exists('message_unread_count')) {
    function message_unread_count(PDO $pdo, int $userId, string $role): int
    {
        $role = strtoupper($role);
        if ($role === 'BUYER') {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM messages m
                 INNER JOIN properties p ON p.property_id = m.property_id
                 WHERE m.receiver_id = ?
                   AND m.is_read = 0
                   AND p.listed_by_user_id = m.sender_id
                   AND (
                        m.sender_id = p.listed_by_user_id
                        AND EXISTS (
                            SELECT 1 FROM messages mx
                            WHERE mx.property_id = m.property_id
                              AND (
                                   (mx.sender_id = ? AND mx.receiver_id = p.listed_by_user_id)
                                OR (mx.sender_id = p.listed_by_user_id AND mx.receiver_id = ?)
                              )
                        )
                   )'
            );
            $stmt->execute([$userId, $userId, $userId]);
            return (int) $stmt->fetchColumn();
        }

        if ($role === 'SELLER' || $role === 'ADMIN') {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM messages m
                 INNER JOIN properties p ON p.property_id = m.property_id
                 INNER JOIN users sender ON sender.user_id = m.sender_id
                 WHERE m.receiver_id = ?
                   AND m.is_read = 0
                   AND p.listed_by_user_id = ?
                   AND sender.role = \'BUYER\''
            );
            $stmt->execute([$userId, $userId]);
            return (int) $stmt->fetchColumn();
        }

        return 0;
    }
}

if (!function_exists('message_summaries_for_buyer')) {
    /**
     * @return list<array<string, mixed>>
     */
    function message_summaries_for_buyer(PDO $pdo, int $buyerId): array
    {
        $stmt = $pdo->prepare(
            'SELECT p.property_id, p.title, p.status,
                    u.full_name AS lister_name, u.role AS lister_role,
                    lm.message_text AS last_message,
                    lm.created_at AS last_activity,
                    (
                        SELECT COUNT(*)
                        FROM messages um
                        WHERE um.property_id = p.property_id
                          AND um.receiver_id = ?
                          AND um.is_read = 0
                          AND um.sender_id = p.listed_by_user_id
                    ) AS unread_count
             FROM (
                SELECT property_id, MAX(message_id) AS last_message_id
                FROM messages
                WHERE property_id IS NOT NULL
                  AND (sender_id = ? OR receiver_id = ?)
                GROUP BY property_id
             ) conv
             INNER JOIN messages lm ON lm.message_id = conv.last_message_id
             INNER JOIN properties p ON p.property_id = conv.property_id
             INNER JOIN users u ON u.user_id = p.listed_by_user_id
             WHERE EXISTS (
                SELECT 1 FROM messages chk
                WHERE chk.property_id = p.property_id
                  AND (
                       (chk.sender_id = ? AND chk.receiver_id = p.listed_by_user_id)
                    OR (chk.sender_id = p.listed_by_user_id AND chk.receiver_id = ?)
                  )
             )
             ORDER BY lm.created_at DESC, p.property_id DESC'
        );
        $stmt->execute([$buyerId, $buyerId, $buyerId, $buyerId, $buyerId]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('message_summaries_for_lister')) {
    /**
     * Conversations on properties listed by the current user (SELLER or ADMIN lister only).
     *
     * @return list<array<string, mixed>>
     */
    function message_summaries_for_lister(PDO $pdo, int $listerId): array
    {
        $stmt = $pdo->prepare(
            'SELECT p.property_id, p.title, p.status,
                    buyer.user_id AS buyer_id,
                    buyer.full_name AS buyer_name,
                    lm.message_text AS last_message,
                    lm.created_at AS last_activity,
                    (
                        SELECT COUNT(*)
                        FROM messages um
                        WHERE um.property_id = p.property_id
                          AND um.receiver_id = ?
                          AND um.is_read = 0
                          AND um.sender_id = buyer.user_id
                    ) AS unread_count
             FROM (
                SELECT m.property_id,
                       CASE WHEN m.sender_id = p2.listed_by_user_id THEN m.receiver_id ELSE m.sender_id END AS buyer_id,
                       MAX(m.message_id) AS last_message_id
                FROM messages m
                INNER JOIN properties p2 ON p2.property_id = m.property_id
                WHERE p2.listed_by_user_id = ?
                  AND m.property_id IS NOT NULL
                GROUP BY m.property_id, buyer_id
             ) conv
             INNER JOIN messages lm ON lm.message_id = conv.last_message_id
             INNER JOIN properties p ON p.property_id = conv.property_id
             INNER JOIN users buyer ON buyer.user_id = conv.buyer_id
             WHERE buyer.role = \'BUYER\'
               AND EXISTS (
                    SELECT 1 FROM messages chk
                    WHERE chk.property_id = conv.property_id
                      AND (
                           (chk.sender_id = conv.buyer_id AND chk.receiver_id = ?)
                        OR (chk.sender_id = ? AND chk.receiver_id = conv.buyer_id)
                      )
               )
             ORDER BY lm.created_at DESC, p.property_id DESC'
        );
        $stmt->execute([$listerId, $listerId, $listerId, $listerId]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('message_safe_return_path')) {
    function message_safe_return_path(?string $return, string $role): string
    {
        $return = trim((string) $return);
        $return = ltrim(str_replace('\\', '/', $return), '/');
        if (preg_match('#^https?://#i', $return) || str_contains($return, '//')) {
            return messages_path_for_role($role);
        }
        $allowed = [
            'buyer/messages.php',
            'buyer/conversation.php',
            'seller/messages.php',
            'seller/conversation.php',
            'admin/messages.php',
            'admin/conversation.php',
            'properties/view.php',
        ];
        foreach ($allowed as $prefix) {
            if ($return === $prefix || str_starts_with($return, $prefix . '?')) {
                return $return;
            }
        }
        return messages_path_for_role($role);
    }
}
