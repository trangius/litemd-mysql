<?php

declare(strict_types=1);

namespace LiteMD\Plugins\Mysql;

use LiteMD\Plugin as PluginRegistry;

// ----------------------------------------------------------------------------
// MySQL plugin. Provides a shared 'database' service that other plugins can
// use via Plugin::getService('database'). Reads credentials from
// admin/config.php and manages database creation/deletion.
// ----------------------------------------------------------------------------
class Plugin
{
    private static ?\PDO $pdo = null;

    // ----------------------------------------------------------------------------
    // Plugin metadata shown in the admin Plugins tab.
    // ----------------------------------------------------------------------------
    public static function meta(): array
    {
        return [
            'name'        => 'MySQL',
            'version'     => '1.0',
            'description' => 'MySQL database connection service for plugins that need a database.',
            'author'      => 'LiteMD',
            'requires'    => [],
            'setup_fields' => [
                ['name' => 'db_name', 'label' => 'Database name',  'type' => 'text', 'default' => 'litemd', 'required' => true],
                ['name' => 'db_host', 'label' => 'Host',           'type' => 'text', 'default' => 'localhost'],
                ['name' => 'db_user', 'label' => 'MySQL user',     'type' => 'text', 'default' => 'root'],
                ['name' => 'db_pass', 'label' => 'MySQL password', 'type' => 'password', 'default' => ''],
            ],
        ];
    }

    // ----------------------------------------------------------------------------
    // Runs once when the user clicks Install. Receives user-provided DB
    // credentials from the setup form, writes them to admin/config.php,
    // and creates the database if it doesn't exist. Returns a status message.
    // ----------------------------------------------------------------------------
    public static function setup(array $data = []): string
    {
        // Use form input, falling back to sensible defaults
        $host = trim((string) ($data['db_host'] ?? 'localhost')) ?: 'localhost';
        $name = trim((string) ($data['db_name'] ?? 'litemd')) ?: 'litemd';
        $user = trim((string) ($data['db_user'] ?? 'root')) ?: 'root';
        $pass = (string) ($data['db_pass'] ?? '');

        // Write DB credentials to admin/config.php
        $configFile = self::configFile();
        $config = is_file($configFile) ? (array) require $configFile : [];
        $config['DB_HOST'] = $host;
        $config['DB_NAME'] = $name;
        $config['DB_USER'] = $user;
        $config['DB_PASS'] = $pass;
        self::writeConfig($config);

        // Connect to MySQL (without dbname) to check if the database exists
        $pdo = new \PDO(
            'mysql:host=' . $host . ';charset=utf8mb4',
            $user,
            $pass,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        // Check if database already exists
        $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
        $stmt->execute([$name]);
        $exists = (bool) $stmt->fetch();

        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $name . '`');

        if ($exists) {
            return 'Connected to existing database "' . $name . '".';
        }
        return 'Created new database "' . $name . '".';
    }

    // ----------------------------------------------------------------------------
    // Returns cleanup actions shown before uninstall confirmation.
    // ----------------------------------------------------------------------------
    public static function uninstall(): array
    {
        return [
            [
                'description' => 'Remove DB credentials from config. The database itself is NOT deleted — drop it manually in MySQL if needed.',
                'destructive' => false,
                'execute'     => function () {
                    // Remove DB keys from admin/config.php
                    $configFile = self::configFile();
                    if (is_file($configFile)) {
                        $raw = (array) require $configFile;
                        unset($raw['DB_HOST'], $raw['DB_NAME'], $raw['DB_USER'], $raw['DB_PASS']);
                        self::writeConfig($raw);
                    }

                    // Clear the cached connection
                    self::$pdo = null;
                },
            ],
        ];
    }

    // ----------------------------------------------------------------------------
    // Runs on every request. Registers the 'database' service so other plugins
    // can get a PDO connection via Plugin::getService('database').
    // ----------------------------------------------------------------------------
    public static function register(): void
    {
        PluginRegistry::addService('database', [self::class, 'connect']);
    }

    // ----------------------------------------------------------------------------
    // Return a shared PDO instance, creating it on first call. Same singleton
    // pattern and options as the original src/Database.php.
    // ----------------------------------------------------------------------------
    public static function connect(): \PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $config = self::loadConfig();

        $dsn = 'mysql:host=' . $config['host']
             . ';dbname=' . $config['name']
             . ';charset=utf8mb4';

        self::$pdo = new \PDO($dsn, $config['user'], $config['pass'], [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$pdo;
    }

    // ----------------------------------------------------------------------------
    // Read DB credentials from admin/config.php and return as a clean array.
    // ----------------------------------------------------------------------------
    private static function loadConfig(): array
    {
        $configFile = self::configFile();
        if (!is_file($configFile)) {
            throw new \RuntimeException('Database config (admin/config.php) not found.');
        }

        $raw = (array) require $configFile;

        return [
            'host' => (string) ($raw['DB_HOST'] ?? 'localhost'),
            'name' => (string) ($raw['DB_NAME'] ?? ''),
            'user' => (string) ($raw['DB_USER'] ?? 'root'),
            'pass' => (string) ($raw['DB_PASS'] ?? ''),
        ];
    }

    // ----------------------------------------------------------------------------
    // Absolute path to admin/config.php.
    // ----------------------------------------------------------------------------
    private static function configFile(): string
    {
        return dirname(__DIR__, 2) . '/admin/config.php';
    }

    // ----------------------------------------------------------------------------
    // Write the config array back to admin/config.php using var_export.
    // ----------------------------------------------------------------------------
    private static function writeConfig(array $config): void
    {
        $export = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        file_put_contents(self::configFile(), $export);
    }
}
