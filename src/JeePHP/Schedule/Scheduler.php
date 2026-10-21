<?php
declare(strict_types=1);

namespace JeePHP\Schedule;

use JeePHP\Jobs\Queue;
use JeePHP\Jobs\QueueableJob;
use RuntimeException;

final class Scheduler
{
    public function __construct(private readonly string $root) {}

    public function run(): int
    {
        $configPath = $this->root . '/config/schedule.php';
        if (!is_file($configPath)) {
            throw new RuntimeException('Schedule config is missing. Run php jeep activate ScheduleO.');
        }
        $tasks = require $configPath;
        if (!is_array($tasks)) {
            throw new RuntimeException('config/schedule.php must return an array of scheduled tasks.');
        }
        $statePath = $this->root . '/storage/schedule/state.json';
        $directory = dirname($statePath);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create schedule state folder.');
        }
        $lock = fopen($statePath . '.lock', 'c+');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('A schedule:run command is already running.');
        }
        try {
            $state = json_decode((string) @file_get_contents($statePath), true);
            $state = is_array($state) ? $state : [];
            $now = time();
            $queued = 0;
            foreach ($tasks as $index => $task) {
                if (!is_array($task)) {
                    throw new RuntimeException('Each schedule item must be an array.');
                }
                $job = $task['job'] ?? null;
                $interval = filter_var($task['every_seconds'] ?? null, FILTER_VALIDATE_INT);
                if (!is_string($job) || $interval === false || $interval < 60 || $interval > 31536000) {
                    throw new RuntimeException('Schedule tasks need a job class and every_seconds between 60 and 31536000.');
                }
                $key = hash('sha256', $index . ':' . $job);
                if ($now - (int) ($state[$key] ?? 0) < $interval) {
                    continue;
                }
                $payload = $task['payload'] ?? [];
                if (!is_array($payload)) {
                    throw new RuntimeException('Schedule task payload must be an array.');
                }
                if (function_exists('queue_dispatch') && is_file($this->root . '/storage/packages/QueueO.active')) {
                    queue_dispatch($job, $payload, 0, (int) ($task['max_attempts'] ?? 3));
                } else {
                    $class = str_contains($job, '\\') ? ltrim($job, '\\') : 'App\\Jobs\\' . $job;
                    if (!class_exists($class) || !is_subclass_of($class, QueueableJob::class)) {
                        throw new RuntimeException('Scheduled job must exist and implement QueueableJob: ' . $class);
                    }
                    (new $class())->handle($payload);
                }
                $state[$key] = $now;
                $queued++;
            }
            $temporary = $statePath . '.tmp';
            if (file_put_contents($temporary, json_encode($state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), LOCK_EX) === false
                || !rename($temporary, $statePath)) {
                @unlink($temporary);
                throw new RuntimeException('Could not save schedule run state.');
            }
            @chmod($statePath, 0600);
            return $queued;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
