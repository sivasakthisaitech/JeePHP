<?php
declare(strict_types=1);

use JeePHP\Security\Platform;
use JeePHP\Testing\TestCase;

return static function (TestCase $test): void {
    foreach ([
        [80199, false, null],
        [80234, false, '8.2.35'], [80235, true, '8.2.35'],
        [80335, false, '8.3.36'], [80336, true, '8.3.36'],
        [80426, false, '8.4.27'], [80427, true, '8.4.27'],
        [80511, false, '8.5.12'], [80512, true, '8.5.12'],
        [80600, false, null],
    ] as [$version, $patched, $minimum]) {
        $test->same($patched, Platform::isPatched($version), 'Incorrect patch boundary for ' . $version);
        $test->same($minimum, Platform::minimum($version));
    }

    $composer = json_decode((string) file_get_contents(dirname(__DIR__) . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    $test->same('>=8.2.35 <8.3 || >=8.3.36 <8.4 || >=8.4.27 <8.5 || >=8.5.12 <8.6', $composer['require']['php']);
};
