<?php
declare(strict_types=1);

namespace JeePHP\Testing;

use RuntimeException;
use Throwable;

final class TestRunner
{
    public function run(string $directory): int
    {
        if (!is_dir($directory)) {
            throw new RuntimeException('Tests folder not found. Create one with php jeep make:test ExampleTest.');
        }
        $files = glob(rtrim($directory, '/\\') . '/*Test.php') ?: [];
        sort($files);
        if ($files === []) {
            echo "No *Test.php files found. Create a test with php jeep make:test ExampleTest.\n";
            return 0;
        }

        $failed = 0;
        $assertions = 0;
        foreach ($files as $file) {
            $name = basename($file);
            try {
                $test = require $file;
                if (!is_callable($test)) {
                    throw new RuntimeException('Test file must return a callable that receives JeePHP\\Testing\\TestCase.');
                }
                $case = new TestCase();
                $test($case);
                $assertions += $case->assertionCount();
                echo 'PASS ' . $name . ' (' . $case->assertionCount() . " assertions)\n";
            } catch (Throwable $error) {
                $failed++;
                echo 'FAIL ' . $name . ': ' . $error->getMessage() . "\n";
            }
        }
        echo count($files) . ' tests, ' . $assertions . ' assertions, ' . $failed . " failures\n";
        return $failed === 0 ? 0 : 1;
    }
}
