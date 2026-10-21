<?php
declare(strict_types=1);

namespace JeePHP\Jobs;

use JeePHP\Database\Database;
use PDO;
use RuntimeException;
use Throwable;

final class Queue
{
    public static function dispatch(string $job, array $payload = [], int $delaySeconds = 0, int $maxAttempts = 3): int
    {
        $class = str_contains($job, '\\') ? ltrim($job, '\\') : 'App\\Jobs\\' . $job;
        if (!preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/', $class)) {
            throw new RuntimeException('Invalid queued job class name.');
        }
        if (!class_exists($class) || !is_subclass_of($class, QueueableJob::class)) {
            throw new RuntimeException($class . ' must exist and implement JeePHP\\Jobs\\QueueableJob.');
        }
        if ($delaySeconds < 0 || $delaySeconds > 31536000 || $maxAttempts < 1 || $maxAttempts > 20) {
            throw new RuntimeException('Delay must be 0..31536000 seconds and attempts must be 1..20.');
        }
        $statement = Database::connection()->prepare(
            'INSERT INTO jeep_jobs (job_class, payload, attempts, max_attempts, available_at, status, created_at) '
            . 'VALUES (:job, :payload, 0, :max_attempts, :available_at, \'queued\', UTC_TIMESTAMP())'
        );
        $statement->execute([
            'job' => $class,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
            'max_attempts' => $maxAttempts,
            'available_at' => gmdate('Y-m-d H:i:s', time() + $delaySeconds),
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function workOne(int $retryDelay = 60): bool
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->exec(
                "UPDATE jeep_jobs SET status='failed', failed_at=UTC_TIMESTAMP(), "
                . "last_error=COALESCE(last_error, 'Worker stopped while this job was reserved.') "
                . "WHERE status='reserved' AND reserved_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE) "
                . 'AND attempts >= max_attempts'
            );
            $candidate = $pdo->query(
                "SELECT id, status FROM jeep_jobs WHERE (status = 'queued' AND available_at <= UTC_TIMESTAMP()) "
                . "OR (status = 'reserved' AND reserved_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE) AND attempts < max_attempts) "
                . 'ORDER BY id ASC LIMIT 1'
            )->fetch(PDO::FETCH_ASSOC);
            if (!is_array($candidate)) {
                $pdo->commit();
                return false;
            }
            $reserve = $pdo->prepare(
                "UPDATE jeep_jobs SET status = 'reserved', reserved_at = UTC_TIMESTAMP(), attempts = attempts + 1 "
                . "WHERE id = :id AND ((status = 'queued' AND available_at <= UTC_TIMESTAMP()) "
                . "OR (status = 'reserved' AND reserved_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE) AND attempts < max_attempts))"
            );
            $reserve->execute(['id' => $candidate['id']]);
            if ($reserve->rowCount() !== 1) {
                $pdo->commit();
                return false;
            }
            $read = $pdo->prepare('SELECT * FROM jeep_jobs WHERE id = :id');
            $read->execute(['id' => $candidate['id']]);
            $job = $read->fetch(PDO::FETCH_ASSOC);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }

        if (!is_array($job)) {
            return false;
        }
        try {
            $class = (string) $job['job_class'];
            if (!class_exists($class) || !is_subclass_of($class, QueueableJob::class)) {
                throw new RuntimeException('Queued class is missing or does not implement QueueableJob: ' . $class);
            }
            $payload = json_decode((string) $job['payload'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                throw new RuntimeException('Queued job payload must decode to an array.');
            }
            (new $class())->handle($payload);
            $delete = $pdo->prepare('DELETE FROM jeep_jobs WHERE id = :id');
            $delete->execute(['id' => $job['id']]);
            return true;
        } catch (Throwable $error) {
            $attempts = (int) $job['attempts'];
            $maximum = (int) $job['max_attempts'];
            $message = $error->getMessage();
            $message = preg_replace('/\b(password|secret|token|api[_-]?key|authorization)\s*[:=]\s*[^\s,;]+/i', '$1=[redacted]', $message) ?? $message;
            $message = preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $message) ?? $message;
            $message = mb_substr($message, 0, 2000);
            if ($attempts >= $maximum) {
                $failed = $pdo->prepare(
                    "UPDATE jeep_jobs SET status = 'failed', reserved_at = NULL, failed_at = UTC_TIMESTAMP(), last_error = :error WHERE id = :id"
                );
                $failed->execute(['error' => $message, 'id' => $job['id']]);
            } else {
                $retryDelay = max(1, min($retryDelay * max(1, $attempts), 86400));
                $retry = $pdo->prepare(
                    "UPDATE jeep_jobs SET status = 'queued', reserved_at = NULL, available_at = :available_at, last_error = :error WHERE id = :id"
                );
                $retry->execute([
                    'available_at' => gmdate('Y-m-d H:i:s', time() + $retryDelay),
                    'error' => $message,
                    'id' => $job['id'],
                ]);
            }
            if (function_exists('app_log')) {
                app_log('error', 'Queue job failed.', ['job_id' => $job['id'], 'job_class' => $job['job_class'], 'error' => $message]);
            }
            return true;
        }
    }

    public static function failed(): array
    {
        $statement = Database::connection()->query("SELECT id, job_class, attempts, last_error, failed_at FROM jeep_jobs WHERE status = 'failed' ORDER BY id DESC");
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
