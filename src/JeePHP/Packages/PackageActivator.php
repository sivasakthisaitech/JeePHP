<?php
declare(strict_types=1);

namespace JeePHP\Packages;

use RuntimeException;
use Throwable;

/** Installs reviewed optional scaffolds without overwriting application files. */
final class PackageActivator
{
    private const PACKAGES = [
        'AuthO', 'RolesO', 'MailO', 'QueueO', 'ScheduleO', 'TestO', 'UploadO',
        'CacheO', 'APIO', 'CrudO', 'ValidateO', 'LogO', 'StorageO', 'BackupO', 'LocaleO',
    ];

    public function __construct(private readonly string $root) {}

    public static function available(string $root): array
    {
        $activator = new self($root);
        return array_map(static fn (string $name): array => [
            'name' => $name,
            'active' => $activator->isActive($name),
        ], self::PACKAGES);
    }

    public function isActive(string $name): bool
    {
        return is_file($this->root . '/storage/packages/' . $name . '.active');
    }

    public function activate(string $name): void
    {
        if (!in_array($name, self::PACKAGES, true)) {
            throw new RuntimeException('Unknown package. Run php jeep package:list to see available packages.');
        }
        $manifestPath = $this->root . '/packages/' . $name . '/package.php';
        if (!is_file($manifestPath)) {
            throw new RuntimeException('Package manifest is missing: ' . $name);
        }
        if ($this->isActive($name)) {
            echo $name . " is already active. No files were changed.\n";
            return;
        }
        $manifest = require $manifestPath;
        if (!is_array($manifest)) {
            throw new RuntimeException('Package manifest must return an array: ' . $name);
        }
        $source = $this->root . '/packages/' . $name . '/files';
        if (is_dir($source)) {
            $copyFiles = [];
            $appendFiles = [];
            $migration = null;
            $this->collect($source, $source, $copyFiles, $appendFiles, $migration);
        } elseif (($manifest['scaffold'] ?? true) !== false) {
            throw new RuntimeException('Package scaffold folder is missing: ' . $name);
        } else {
            $copyFiles = [];
            $appendFiles = [];
            $migration = null;
        }
        $requires = $manifest['requires'] ?? [];
        if (!is_array($requires)) {
            throw new RuntimeException('Package dependencies must be listed as an array.');
        }
        foreach ($requires as $required) {
            if (!is_string($required) || !$this->isActive($required)) {
                throw new RuntimeException($name . ' requires ' . (string) $required . '. Activate that package first.');
            }
        }

        if (($manifest['migration'] ?? false) === true) {
            if ($migration === null) {
                throw new RuntimeException($name . ' declares a migration, but its migration template is missing.');
            }
            $migrationPath = $this->root . '/database/migrations/' . date('Ymd_His') . '_install_' . strtolower($name) . '.php';
            $copyFiles[$migrationPath] = $migration;
        }

        foreach (array_keys($copyFiles) as $target) {
            if (file_exists($target)) {
                throw new RuntimeException('Activation stopped because this file already exists: ' . $target . '. Resolve that conflict, then retry.');
            }
        }

        $composerPath = $this->root . '/composer.json';
        $composerOriginal = is_file($composerPath) ? (string) file_get_contents($composerPath) : null;
        $composerUpdated = $this->mergeComposerRequirements($composerOriginal, $manifest['composer'] ?? []);
        $created = [];
        $originalAppend = [];
        $composerChanged = false;
        try {
            foreach ($copyFiles as $target => $contents) {
                $this->write($target, $contents);
                $created[] = $target;
            }
            foreach ($appendFiles as $target => $block) {
                $existing = is_file($target) ? (string) file_get_contents($target) : "<?php\ndeclare(strict_types=1);\n\n";
                if (!str_contains($existing, '// JeePHP package: ' . $name)) {
                    $originalAppend[$target] = is_file($target) ? $existing : null;
                    $this->write($target, rtrim($existing) . "\n\n" . trim($block) . "\n");
                }
            }
            if ($composerUpdated !== null && $composerUpdated !== $composerOriginal) {
                $this->write($composerPath, $composerUpdated);
                $composerChanged = true;
            }
            $marker = $this->root . '/storage/packages/' . $name . '.active';
            $this->write($marker, 'JeePHP package ' . $name . "\nActivated: " . date(DATE_ATOM) . "\n");
            $created[] = $marker;
        } catch (Throwable $error) {
            foreach (array_reverse($created) as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            foreach ($originalAppend as $file => $contents) {
                if ($contents === null) {
                    @unlink($file);
                } else {
                    file_put_contents($file, $contents, LOCK_EX);
                }
            }
            if ($composerChanged && $composerOriginal !== null) {
                file_put_contents($composerPath, $composerOriginal, LOCK_EX);
            }
            throw $error;
        }

        echo $name . " activated. Created:\n";
        foreach ($copyFiles as $target => $_) {
            echo '  ' . substr($target, strlen($this->root) + 1) . "\n";
        }
        foreach (array_keys($appendFiles) as $target) {
            echo '  updated ' . substr($target, strlen($this->root) + 1) . "\n";
        }
        $composer = $manifest['composer'] ?? [];
        if (($composer['require'] ?? []) !== [] || ($composer['require-dev'] ?? []) !== []) {
            echo "\nNew Composer packages were added to composer.json. Run:\n  composer update\n";
        } else {
            echo "\nNext steps:\n  composer dump-autoload\n";
        }
        if (($manifest['migration'] ?? false) === true) {
            echo "  php jeep migrate\n";
        }
        $this->printPackageNextSteps($name);
    }

    private function printPackageNextSteps(string $name): void
    {
        $steps = [
            'AuthO' => 'Open /auth/register, then /auth/login. Activate MailO to send password reset emails.',
            'RolesO' => 'Assign a role with php jeep role:assign USER_ID admin, then grant roles.manage.',
            'MailO' => 'Set SMTP values in .duck and send a test with php jeep mail:test EMAIL.',
            'QueueO' => 'Create QueueableJob classes, enqueue with queue_dispatch(), and start php jeep queue:work.',
            'ScheduleO' => 'Edit config/schedule.php and run php jeep schedule:run from your OS scheduler once a minute.',
            'TestO' => 'Run php jeep test and create tests with php jeep make:test ExampleTest.',
            'UploadO' => 'Use upload_file() with MIME allow-list and a byte limit; it returns a random storage path.',
            'CacheO' => 'Use cache_remember() for time-limited results and cache_forget() when data changes.',
            'APIO' => 'Run php jeep api:token USER_ID app-name, then send Authorization: Bearer TOKEN.',
            'CrudO' => 'Generate a module with php jeep make:crud Product name,price,description.',
            'ValidateO' => 'Use validate($request->only(...), $rules) before any insert or update.',
            'LogO' => 'Write logs with app_log(level, message, context); secret-like context fields are redacted.',
            'StorageO' => 'Use store_file(), read_file(), delete_file(), and storage_url(); private is the default disk.',
            'BackupO' => 'Run php jeep backup:key-generate, then php jeep drive:connect path/to/oauth-client.json.',
            'LocaleO' => 'Use set_locale(\'ta\') and trans(\'welcome\').',
        ];
        if (isset($steps[$name])) {
            echo "\n" . $steps[$name] . "\n";
        }
    }

    private function mergeComposerRequirements(?string $contents, mixed $requirements): ?string
    {
        if (!is_array($requirements) || $requirements === []) {
            return $contents;
        }
        if ($contents === null) {
            throw new RuntimeException('composer.json is missing; cannot install package dependencies.');
        }
        $composer = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        foreach (['require', 'require-dev'] as $section) {
            foreach (($requirements[$section] ?? []) as $package => $version) {
                if (!is_string($package) || !is_string($version)) {
                    throw new RuntimeException('Invalid Composer requirement in package manifest.');
                }
                if (isset($composer[$section][$package]) && $composer[$section][$package] !== $version) {
                    throw new RuntimeException('composer.json already has a different version constraint for ' . $package . '. Resolve it manually before activation.');
                }
                $composer[$section][$package] = $version;
            }
            if (isset($composer[$section]) && is_array($composer[$section])) {
                ksort($composer[$section]);
            }
        }
        return json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    private function collect(string $base, string $directory, array &$copyFiles, array &$appendFiles, ?string &$migration): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_link($path)) {
                throw new RuntimeException('Package files cannot contain symbolic links.');
            }
            if (is_dir($path)) {
                $this->collect($base, $path, $copyFiles, $appendFiles, $migration);
                continue;
            }
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($base) + 1));
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException('Could not read package file: ' . $path);
            }
            if ($relative === 'routes.php') {
                $appendFiles[$this->root . '/routes/web.php'] = $contents;
            } elseif ($relative === 'api-routes.php') {
                $appendFiles[$this->root . '/routes/api.php'] = $contents;
            } elseif ($relative === 'helpers.php') {
                $appendFiles[$this->root . '/app/Helpers/helpers.php'] = $contents;
            } elseif ($relative === 'migration.php') {
                $migration = $contents;
            } else {
                if (!preg_match('#^(app|database|routes|config|lang|tests|public)/[A-Za-z0-9_./-]+$#', $relative)
                    || str_contains($relative, '..')) {
                    throw new RuntimeException('Invalid package target path: ' . $relative);
                }
                $copyFiles[$this->root . '/' . $relative] = $contents;
            }
        }
    }

    private function write(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create folder: ' . $directory);
        }
        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Could not write file: ' . $path);
        }
    }
}
