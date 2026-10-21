<?php
declare(strict_types=1);

namespace JeePHP\Jobs;

interface QueueableJob
{
    public function handle(array $payload = []): void;
}
