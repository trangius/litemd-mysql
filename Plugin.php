<?php

declare(strict_types=1);

namespace LiteMD\Plugins\Mysql;

use LiteMD\Plugin as PluginRegistry;
use LiteMD\Config;

// ----------------------------------------------------------------------------
// MySQL plugin. Provides a shared 'database' service that other plugins can
// use via Plugin::getService('database'). Reads credentials from the main
// config.php under plugins.mysql and manages database creation.
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
    // credentials from the setup form, writes them to config.php under
    // plugins.mysql, and creates the database if it doesn't exist.
    // ----------------------------------------------------------------------------
    public static function setup(array $data = []): string
    {
        // Use form input, falling back to sensible defaults
        $host = trim((string) ($data['db_host'] ?? 'localhost')) ?: 'localhost';
        $name = trim((string) ($data['db_name'] ?? 'litemd')) ?: 'litemd';
        $user = trim((string) ($data['db_user'] ?? 'root')) ?: 'root';
        $pass = (string) ($data['db_pass'] ?? '');

        // Write DB credentials to config.php under plugins.mysql
        $configFile = dirname(__DIR__, 2) . '/config.php';
        $config = is_file($configFile) ? (array) require $configFile : [];
        if (!isset($config['plugins'])) {
            $config['plugins'] = [];
        }
        $config['plugins']['mysql'] = [
            'host' => $host,
            'name' => $name,
            'user' => $user,
            'pass' => $pass,
        ];
        self::writeMainConfig($configFile, $config);

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
                    // Remove plugins.mysql from config.php
                    $configFile = dirname(__DIR__, 2) . '/config.php';
                    if (is_file($configFile)) {
                        $config = (array) require $configFile;
                        if (isset($config['plugins']['mysql'])) {
                            unset($config['plugins']['mysql']);
                        }
                        self::writeMainConfig($configFile, $config);
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
    // Read DB credentials from config.php plugins.mysql section.
    // ----------------------------------------------------------------------------
    private static function loadConfig(): array
    {
        $pluginConfig = Config::getPluginConfig('mysql', []);

        return [
            'host' => (string) ($pluginConfig['host'] ?? 'localhost'),
            'name' => (string) ($pluginConfig['name'] ?? ''),
            'user' => (string) ($pluginConfig['user'] ?? 'root'),
            'pass' => (string) ($pluginConfig['pass'] ?? ''),
        ];
    }

    // ----------------------------------------------------------------------------
    // Write the full config array to the main config.php file.
    // Uses the same var_export_short format as save_config() in api.php.
    // ----------------------------------------------------------------------------
    private static function writeMainConfig(string $configFile, array $config): void
    {
        // Use var_export_short if available (defined in admin/api.php),
        // otherwise fall back to var_export
        if (function_exists('var_export_short')) {
            $export = "<?php\n\nreturn " . var_export_short($config) . ";\n";
        } else {
            $export = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        }

        $result = file_put_contents($configFile, $export);
        if ($result === false) {
            throw new \RuntimeException('Could not write config.php');
        }
    }
}
