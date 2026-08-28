<?php
/**
 * Shared helpers for user profile management (all authenticated roles).
 */

declare(strict_types=1);

if (!function_exists('profile_normalize_email')) {
    function profile_normalize_email(string $email): string
    {
        return strtolower(trim($email));
    }
}

if (!function_exists('profile_dashboard_label')) {
    function profile_dashboard_label(string $role): string
    {
        return match (strtoupper($role)) {
            'SELLER' => 'Seller Dashboard',
            'ADMIN' => 'Admin Dashboard',
            default => 'Buyer Dashboard',
        };
    }
}

if (!function_exists('profile_find_user')) {
    /**
     * Load profile fields for the currently authenticated user only.
     *
     * @return array<string, mixed>|null
     */
    function profile_find_user(PDO $pdo, int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT user_id, full_name, email, phone, profile_image, role, status, created_at
             FROM users
             WHERE user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('profile_fetch_password_hash')) {
    function profile_fetch_password_hash(PDO $pdo, int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT password_hash FROM users WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        return is_string($hash) && $hash !== '' ? $hash : null;
    }
}

if (!function_exists('profile_email_taken')) {
    function profile_email_taken(PDO $pdo, string $email, int $excludeUserId): bool
    {
        $stmt = $pdo->prepare(
            'SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1'
        );
        $stmt->execute([$email, $excludeUserId]);

        return (bool) $stmt->fetch();
    }
}

if (!function_exists('profile_validate_personal_fields')) {
    /**
     * @param array{full_name?: string, email?: string, phone?: string} $data
     * @return list<string>
     */
    function profile_validate_personal_fields(array $data): array
    {
        $errors = [];

        $fullName = trim((string) ($data['full_name'] ?? ''));
        $email = profile_normalize_email((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));

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

        return $errors;
    }
}

if (!function_exists('profile_validate_password_change')) {
    /**
     * @return list<string>
     */
    function profile_validate_password_change(
        string $currentPassword,
        string $newPassword,
        string $confirmPassword,
        ?string $storedHash
    ): array {
        $errors = [];

        if ($storedHash === null || $storedHash === '') {
            $errors[] = 'Unable to verify your current password right now. Please try again later.';
            return $errors;
        }

        if ($currentPassword === '') {
            $errors[] = 'Current password is required.';
        } elseif (!password_verify($currentPassword, $storedHash)) {
            $errors[] = 'Current password is incorrect.';
        }

        if ($newPassword === '') {
            $errors[] = 'New password is required.';
        } elseif (strlen($newPassword) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if ($confirmPassword === '') {
            $errors[] = 'Please confirm your new password.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'New password and confirm password do not match.';
        }

        if (
            $errors === []
            && $currentPassword !== ''
            && $newPassword !== ''
            && hash_equals($currentPassword, $newPassword)
        ) {
            $errors[] = 'New password must be different from your current password.';
        }

        return $errors;
    }
}

if (!defined('PROFILE_MAX_IMAGE_BYTES')) {
    define('PROFILE_MAX_IMAGE_BYTES', 2 * 1024 * 1024);
}

if (!function_exists('profile_upload_dir')) {
    function profile_upload_dir(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'profiles';
    }
}

if (!function_exists('profile_relative_prefix')) {
    function profile_relative_prefix(): string
    {
        return 'assets/images/profiles/';
    }
}

if (!function_exists('profile_ensure_upload_dir')) {
    function profile_ensure_upload_dir(): bool
    {
        $dir = profile_upload_dir();
        if (is_dir($dir)) {
            return is_writable($dir);
        }

        return mkdir($dir, 0755, true);
    }
}

if (!function_exists('profile_allowed_image_mimes')) {
    /** @return array<string, string> */
    function profile_allowed_image_mimes(): array
    {
        return [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
    }
}

if (!function_exists('profile_is_managed_image_path')) {
    function profile_is_managed_image_path(?string $relativePath): bool
    {
        if ($relativePath === null || $relativePath === '') {
            return false;
        }

        $relativePath = str_replace('\\', '/', $relativePath);
        $prefix = profile_relative_prefix();

        if (!str_starts_with($relativePath, $prefix)) {
            return false;
        }

        $basename = basename($relativePath);

        return $basename !== ''
            && $basename !== '.'
            && $basename !== '..'
            && preg_match('/^[a-f0-9]{32}\.(jpg|jpeg|png|webp)$/i', $basename) === 1;
    }
}

if (!function_exists('profile_delete_image_file')) {
    function profile_delete_image_file(?string $relativePath): void
    {
        if (!profile_is_managed_image_path($relativePath)) {
            return;
        }

        $basename = basename(str_replace('\\', '/', (string) $relativePath));
        $full = profile_upload_dir() . DIRECTORY_SEPARATOR . $basename;

        if (is_file($full)) {
            @unlink($full);
        }
    }
}

if (!function_exists('profile_store_uploaded_image')) {
    /**
     * Validate and store one profile upload. Returns relative path or ERROR: message.
     */
    function profile_store_uploaded_image(array $file): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return 'ERROR: Please choose an image to upload.';
        }

        if ($error !== UPLOAD_ERR_OK) {
            return 'ERROR: The image failed to upload. Please try again.';
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return 'ERROR: Invalid image upload.';
        }

        if ($size <= 0 || $size > PROFILE_MAX_IMAGE_BYTES) {
            return 'ERROR: Profile image must be between 1 byte and 2 MB.';
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        $allowed = profile_allowed_image_mimes();

        if (!isset($allowed[$mime])) {
            return 'ERROR: Only JPG, JPEG, PNG and WEBP images are allowed.';
        }

        if (@getimagesize($tmp) === false) {
            return 'ERROR: Uploaded file is not a valid image.';
        }

        if (!profile_ensure_upload_dir()) {
            return 'ERROR: Unable to store profile images right now.';
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $destination = profile_upload_dir() . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmp, $destination)) {
            return 'ERROR: Unable to save uploaded image.';
        }

        return profile_relative_prefix() . $filename;
    }
}

if (!function_exists('profile_initials')) {
    function profile_initials(string $fullName): string
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return '?';
        }

        $parts = preg_split('/\s+/u', $fullName) ?: [];
        $initials = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
            if (mb_strlen($initials) >= 2) {
                break;
            }
        }

        return $initials !== '' ? $initials : '?';
    }
}

if (!function_exists('profile_image_url')) {
    function profile_image_url(?string $path): ?string
    {
        if (!function_exists('admin_image_url')) {
            require_once __DIR__ . '/../admin/_helpers.php';
        }

        return admin_image_url($path);
    }
}

if (!function_exists('profile_fetch_image_path')) {
    function profile_fetch_image_path(PDO $pdo, int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT profile_image FROM users WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $path = $stmt->fetchColumn();

        if (!is_string($path) || trim($path) === '') {
            return null;
        }

        return $path;
    }
}

if (!function_exists('profile_render_avatar')) {
    /**
     * @param array{class?: string, size?: string} $options
     */
    function profile_render_avatar(
        string $fullName,
        ?string $profileImage,
        array $options = []
    ): string {
        $class = trim((string) ($options['class'] ?? 'profile-avatar'));
        $size = trim((string) ($options['size'] ?? ''));
        $classAttr = e($class . ($size !== '' ? ' ' . $size : ''));
        $initials = e(profile_initials($fullName));
        $imageUrl = profile_image_url($profileImage);

        if ($imageUrl !== null && profile_is_managed_image_path($profileImage)) {
            return '<img class="' . $classAttr . '" src="' . e($imageUrl) . '" alt="" width="96" height="96" loading="lazy">';
        }

        return '<div class="' . $classAttr . ' profile-avatar-initials" aria-hidden="true">' . $initials . '</div>';
    }
}
