<?php
/**
 * RealEstateAI — Authentication helpers
 *
 * Session bootstrap, CSRF, login state, and simple role checks.
 */

declare(strict_types=1);

if (!function_exists('start_app_session')) {
    function start_app_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }
}

if (!function_exists('app_base_url')) {
    /**
     * Project base URL path ending with /.
     * Works from root and from auth/buyer/seller/admin subfolders.
     */
    function app_base_url(): string
    {
        static $base = null;

        if ($base !== null) {
            return $base;
        }

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = str_replace('\\', '/', dirname($script));
        $leaf = basename($dir);

        if (in_array($leaf, ['auth', 'buyer', 'seller', 'admin', 'properties', 'ai', 'support', 'profile'], true)) {
            $dir = dirname($dir);
        }

        $dir = rtrim($dir, '/');
        $base = ($dir === '' || $dir === '.') ? '/' : $dir . '/';

        return $base;
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return app_base_url() . ltrim($path, '/');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): void
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            header('Location: ' . $path);
            exit;
        }

        header('Location: ' . url($path));
        exit;
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        start_app_session();

        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(?string $token): bool
    {
        start_app_session();

        if ($token === null || $token === '' || empty($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals((string) $_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('flash_set')) {
    function flash_set(string $type, string $message): void
    {
        start_app_session();
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }
}

if (!function_exists('flash_get')) {
    /**
     * @return array{type: string, message: string}|null
     */
    function flash_get(): ?array
    {
        start_app_session();

        if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
            return null;
        }

        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);

        return [
            'type' => (string) ($flash['type'] ?? 'info'),
            'message' => (string) ($flash['message'] ?? ''),
        ];
    }
}

if (!function_exists('current_user')) {
    /**
     * @return array{user_id: int, full_name: string, role: string}|null
     */
    function current_user(): ?array
    {
        start_app_session();

        if (
            empty($_SESSION['user_id'])
            || empty($_SESSION['full_name'])
            || empty($_SESSION['role'])
        ) {
            return null;
        }

        return [
            'user_id' => (int) $_SESSION['user_id'],
            'full_name' => (string) $_SESSION['full_name'],
            'role' => (string) $_SESSION['role'],
        ];
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        return current_user() !== null;
    }
}

if (!function_exists('dashboard_path_for_role')) {
    function dashboard_path_for_role(string $role): string
    {
        return match (strtoupper($role)) {
            'BUYER' => 'buyer/index.php',
            'SELLER' => 'seller/index.php',
            'ADMIN' => 'admin/index.php',
            default => 'index.php',
        };
    }
}

if (!function_exists('login_user')) {
    /**
     * @param array{user_id: int|string, full_name: string, role: string} $user
     */
    function login_user(array $user, bool $remember = false): void
    {
        start_app_session();
        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) $user['user_id'];
        $_SESSION['full_name'] = (string) $user['full_name'];
        $_SESSION['role'] = strtoupper((string) $user['role']);

        auth_apply_remember_session($remember);
    }
}

if (!function_exists('auth_apply_remember_session')) {
    /**
     * Extend the session cookie lifetime when Remember Me is selected.
     * Does not store passwords or credentials in cookies.
     */
    function auth_apply_remember_session(bool $remember): void
    {
        if (!$remember || !ini_get('session.use_cookies')) {
            return;
        }

        $params = session_get_cookie_params();
        $lifetime = 60 * 60 * 24 * 30;

        setcookie(session_name(), session_id(), [
            'expires' => time() + $lifetime,
            'path' => $params['path'],
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
}

if (!function_exists('logout_user')) {
    function logout_user(): void
    {
        start_app_session();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'] ?? '',
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        session_destroy();
    }
}

if (!function_exists('auth_clear_login_session')) {
    /**
     * Drop trusted identity keys without destroying the whole session
     * (flash / CSRF can still be set for the login redirect).
     */
    function auth_clear_login_session(): void
    {
        start_app_session();
        unset($_SESSION['user_id'], $_SESSION['full_name'], $_SESSION['role']);
    }
}

if (!function_exists('auth_refresh_session_user')) {
    /**
     * Re-check the session user against `users` once per request.
     * On success, refreshes full_name and role from the database.
     *
     * @return 'ok'|'inactive'|'error'
     */
    function auth_refresh_session_user(): string
    {
        static $done = false;
        static $result = 'error';

        if ($done) {
            return $result;
        }
        $done = true;

        start_app_session();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            auth_clear_login_session();
            $result = 'inactive';
            return $result;
        }

        try {
            if (!function_exists('db')) {
                require_once __DIR__ . '/../config/database.php';
            }

            $stmt = db()->prepare(
                'SELECT user_id, full_name, role, status
                 FROM users
                 WHERE user_id = ?
                 LIMIT 1'
            );
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
        } catch (Throwable $e) {
            auth_clear_login_session();
            $result = 'error';
            return $result;
        }

        if (
            !is_array($row)
            || strtoupper((string) ($row['status'] ?? '')) !== 'ACTIVE'
        ) {
            auth_clear_login_session();
            $result = 'inactive';
            return $result;
        }

        $_SESSION['user_id'] = (int) $row['user_id'];
        $_SESSION['full_name'] = (string) $row['full_name'];
        $_SESSION['role'] = strtoupper((string) $row['role']);
        $result = 'ok';

        return $result;
    }
}

if (!function_exists('require_login')) {
    function require_login(): void
    {
        start_app_session();

        if (
            empty($_SESSION['user_id'])
            || empty($_SESSION['full_name'])
            || empty($_SESSION['role'])
        ) {
            flash_set('error', 'Please log in to continue.');
            redirect('auth/login.php');
        }

        $status = auth_refresh_session_user();
        if ($status === 'ok') {
            return;
        }

        if ($status === 'inactive') {
            flash_set('error', 'Your account is inactive. Please contact support.');
        } else {
            flash_set('error', 'Please log in to continue.');
        }

        redirect('auth/login.php');
    }
}

if (!function_exists('require_role')) {
    function require_role(string ...$roles): void
    {
        require_login();

        $user = current_user();
        $allowed = array_map('strtoupper', $roles);

        if ($user === null || !in_array($user['role'], $allowed, true)) {
            flash_set('error', 'You are not allowed to access that page.');
            redirect(dashboard_path_for_role($user['role'] ?? 'BUYER'));
        }
    }
}
