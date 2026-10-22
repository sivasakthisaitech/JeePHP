<?php
declare(strict_types=1);

use JeePHP\Security\Authorization;
use JeePHP\Security\AuthRateLimiter;
use JeePHP\Testing\TestCase;

return static function (TestCase $test): void {
    $test->assertTrue(Authorization::owns(['user_id' => '42'], 42));
    $test->assertTrue(Authorization::owns(['customer_id' => 42], 42, 'customer_id'));
    foreach ([null, [], ['user_id' => '41'], ['user_id' => 0], ['user_id' => '042'], ['user_id' => ['42']]] as $record) {
        $test->same(false, Authorization::owns($record, 42), 'Only a record belonging to the current user is accessible.');
    }

    $first = AuthRateLimiter::bucketKey('login-ip', '192.0.2.1', 900, 899);
    $test->same($first, AuthRateLimiter::bucketKey('login-ip', '192.0.2.1', 900, 850));
    $test->assertTrue($first !== AuthRateLimiter::bucketKey('login-ip', '192.0.2.1', 900, 900));
    $test->assertTrue($first !== AuthRateLimiter::bucketKey('login-ip', '192.0.2.2', 900, 899));
    $test->assertTrue($first !== AuthRateLimiter::bucketKey('reset-ip', '192.0.2.1', 900, 899));

    $server = $_SERVER;
    try {
        $_SERVER['REMOTE_ADDR'] = '192.0.2.25';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.40';
        $test->same('192.0.2.25', AuthRateLimiter::clientIp(), 'A spoofable forwarded header must not change the bucket.');
        $_SERVER['REMOTE_ADDR'] = 'not-an-ip';
        $test->same('unknown', AuthRateLimiter::clientIp());
    } finally {
        $_SERVER = $server;
    }
};
