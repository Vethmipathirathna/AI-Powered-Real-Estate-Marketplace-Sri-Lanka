<?php
/**
 * RealEstateAI — PDO database configuration (local XAMPP development)
 *
 * Provides a shared PDO connection factory.
 * Does not run application queries.
 *
 * Local defaults assume XAMPP MySQL:
 *   host = localhost, user = root, password = (empty)
 *
 * Prefer environment variables when available. Do not hardcode production secrets.
 */

declare(strict_types=1);

if (!function_exists('db_config')) {
    /**
     * @return array{
     *     host: string,
     *     port: string,
     *     name: string,
     *     user: string,
     *     pass: string,
     *     charset: string
     * }
     */
    function db_config(): array
    {
        return [
            'host' => getenv('DB_HOST') !== false && getenv('DB_HOST') !== ''
                ? (string) getenv('DB_HOST')
                : 'localhost',
            'port' => getenv('DB_PORT') !== false && getenv('DB_PORT') !== ''
                ? (string) getenv('DB_PORT')
                : '3306',
            'name' => getenv('DB_NAME') !== false && getenv('DB_NAME') !== ''
                ? (string) getenv('DB_NAME')
                : 'realestate_ai',
            'user' => getenv('DB_USER') !== false && getenv('DB_USER') !== ''
                ? (string) getenv('DB_USER')
                : 'root',
            'pass' => getenv('DB_PASS') !== false
                ? (string) getenv('DB_PASS')
                : '',
            'charset' => getenv('DB_CHARSET') !== false && getenv('DB_CHARSET') !== ''
                ? (string) getenv('DB_CHARSET')
                : 'utf8mb4',
        ];
    }
}

if (!function_exists('db')) {
    /**
     * Return a shared PDO instance.
     *
     * @throws PDOException
     */
    function db(): PDO
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $config = db_config();

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset']
        );

        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $pdo;
    }
}
