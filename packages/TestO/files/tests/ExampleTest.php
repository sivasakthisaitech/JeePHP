<?php
declare(strict_types=1);

use JeePHP\Testing\TestCase;

return static function (TestCase $test): void {
    $test->same(4, 2 + 2);
    $test->assertTrue(str_starts_with('JeePHP', 'Jee'));
};
