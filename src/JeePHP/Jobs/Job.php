<?php
declare(strict_types=1);

namespace JeePHP\Jobs;

interface Job
{
    public function handle(): void;
}
