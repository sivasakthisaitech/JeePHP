<?php
declare(strict_types=1);

namespace JeePHP\Packages;

use RuntimeException;

/** Copies reviewed, built-in package scaffolds into the current application. */
final class PackageActivator
{
    private const PACKAGES = ['AuthO', 'RolesO'];

    public function __construct(private readonly string $root) {}

    public function activate(string $name): void
    {
        if (!in_array($name, self::PACKAGES, true)) {
            throw new RuntimeException('Unknown package. Available packages: ' . implode(', ', self::PACKAGES));
        }

        $source = $this->root . '/packages/' . $name . '/files';
        if (!is_dir($source)) {
            throw new RuntimeException('Package scaffold files are missing: ' . $source);
        }
        $marker = $this->root . '/storage/packages/' . $name . '.active';
        if (is_file($marker)) {
            echo $name . " is already active. No files were changed.\n";
            return;
        }

        $copyFiles = [];
        $appendFiles = [];
        $migration = null;
        $this->collect($source, $source, $copyFiles, $appendFiles, $migration);
        $migrationPath = $this->root . '/database/migrations/' . date('Ymd_His') . '_install_' . strtolower($name) . '.php';
        $copyFiles[$migrationPath] = $migration ?? throw new RuntimeException('Package migration template is missing.');

        foreach (array_keys($copyFiles) as $target) {
            if (file_exists($target)) {
                throw new RuntimeException('Activation stopped because this file already exists: ' . $target . '. Back it up and resolve the conflict, then run activate again.');
            }
        }

        $created = [];
        $originalAppend = [];
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
            $this->write($marker, 'JeePHP package ' . $name . "\nActivated: " . date(DATE_ATOM) . "\n");
        } catch (\Throwable $error) {
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
            throw $error;
        }

        echo $name . " activated. Created:\n";
        foreach ($copyFiles as $target => $_) {
            echo '  ' . substr($target, strlen($this->root) + 1) . "\n";
        }
        foreach (array_keys($appendFiles) as $target) {
            echo '  updated ' . substr($target, strlen($this->root) + 1) . "\n";
        }
        echo "\nNext steps:\n  composer dump-autoload\n  php jeep migrate\n";
        if ($name === 'AuthO') {
            echo "  Open /auth/register to create an account, then use /auth/login.\n";
        } else {
            echo "  Assign roles and permissions using the generated RolesO helper functions.\n";
        }
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
            } elseif ($relative === 'helpers.php') {
                $appendFiles[$this->root . '/app/Helpers/helpers.php'] = $contents;
            } elseif ($relative === 'migration.php') {
                $migration = $contents;
            } else {
                if (!preg_match('#^(app|database)/[A-Za-z0-9_./-]+$#', $relative) || str_contains($relative, '..')) {
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
