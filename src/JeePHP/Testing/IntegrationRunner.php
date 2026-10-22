<?php
declare(strict_types=1);

namespace JeePHP\Testing;

use JeePHP\Api\ApiTokens;
use JeePHP\Backup\BackupCrypto;
use JeePHP\Backup\DatabaseBackup;
use JeePHP\Database\Database;
use JeePHP\Database\Migrator;
use JeePHP\Packages\CrudGenerator;
use JeePHP\Packages\PackageActivator;
use PDO;
use RuntimeException;
use Throwable;

/** Destructive HTTP/MySQL smoke suite: permitted only in a disposable test project/database. */
final class IntegrationRunner
{
    private PDO $db;
    private string $url;
    private array $cookies = [];
    private int $failures = 0;
    private string $csrf = '';

    public function __construct(private readonly string $root) {}

    public function run(string $baseUrl): int
    {
        try {
            $this->preflight($baseUrl);
            $this->setup();
            $this->check('HTTP target', fn () => $this->verifyTarget());
            if ($this->failures > 0) {
                throw new RuntimeException('HTTP target check failed; stopping before HTTP writes.');
            }
            $this->check('routes and CSRF', fn () => $this->routes());
            $this->check('migrations and rollback', fn () => $this->migrations());
            $this->check('CRUD over HTTP', fn () => $this->crud());
            $this->check('AuthO register and login', fn () => $this->auth());
            $this->check('RolesO permissions', fn () => $this->roles());
            $this->check('APIO bearer abilities', fn () => $this->api());
            $this->check('UploadO MIME validation', fn () => $this->upload());
            $this->check('encrypted MySQL backup and restore', fn () => $this->backup());
        } catch (Throwable $error) {
            $this->failures++;
            fwrite(STDERR, 'STOP: ' . $error->getMessage() . "\n");
        }
        echo "Integration result: " . ($this->failures === 0 ? 'PASS' : $this->failures . ' failure(s)') . "\n";
        return $this->failures === 0 ? 0 : 1;
    }

    private function preflight(string $baseUrl): void
    {
        if (getenv('APP_ENV') !== 'testing' || preg_match('/^jeephp_test_[a-z0-9_]+$/', (string) getenv('DB_NAME')) !== 1) {
            throw new RuntimeException('Refused: set APP_ENV=testing and DB_NAME=jeephp_test_<name> in this disposable project .duck.');
        }
        $this->db = Database::connection();
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
            || $this->db->query('SELECT DATABASE()')->fetchColumn() !== getenv('DB_NAME')) {
            throw new RuntimeException('Refused: connected MySQL database does not match DB_NAME.');
        }
        $parts = parse_url($baseUrl);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || !in_array(strtolower($parts['host'] ?? ''), ['localhost', '127.0.0.1', '::1'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new RuntimeException('Refused: --base-url must be an http(s) loopback URL for this test project.');
        }
        $this->url = rtrim($baseUrl, '/');
        if ((string) getenv('APP_URL') !== $this->url) {
            throw new RuntimeException('Refused: .duck APP_URL must match --base-url exactly (without a trailing slash).');
        }
        if (!is_file($this->root . '/tests/integration/IntegrationProbeController.php')) {
            throw new RuntimeException('Integration fixture is missing. Use the full JeePHP release archive.');
        }
        echo 'Project: ' . $this->root . "\nDatabase: " . getenv('DB_NAME') . "\nURL: " . $this->url . "\n";
    }

    private function setup(): void
    {
        $packages = new PackageActivator($this->root);
        foreach (['AuthO', 'RolesO', 'APIO', 'StorageO', 'UploadO', 'ValidateO', 'CrudO', 'BackupO'] as $name) {
            if (!$packages->isActive($name)) {
                $packages->activate($name);
            }
        }
        $controller = $this->root . '/app/Controllers/IntegrationProbeController.php';
        $fixture = (string) file_get_contents($this->root . '/tests/integration/IntegrationProbeController.php');
        if (is_file($controller) && file_get_contents($controller) !== $fixture) {
            throw new RuntimeException('Existing IntegrationProbeController.php differs from fixture; use a fresh test project.');
        }
        if (!is_file($controller) && file_put_contents($controller, $fixture, LOCK_EX) === false) {
            throw new RuntimeException('Cannot install integration test controller.');
        }
        $this->appendRoutes('routes/web.php', [
            "route('/__jee_test/health', 'IntegrationProbeController@health');",
            "route('/__jee_test/token', 'IntegrationProbeController@token');",
            "route('/__jee_test/dynamic/:id', 'IntegrationProbeController@dynamic');",
            "route('/__jee_test/protected', 'IntegrationProbeController@protected');",
            "route('POST /__jee_test/upload', 'IntegrationProbeController@upload');",
        ]);
        $this->appendRoutes('routes/api.php', ["route('POST /api/__jee_test/echo', 'IntegrationProbeController@api');"]);
        if (!is_file($this->root . '/app/Models/IntegrationItem.php')) {
            (new CrudGenerator($this->root))->generate('IntegrationItem', ['name', 'note']);
        } elseif (!str_contains((string) file_get_contents($this->root . '/routes/web.php'), '// JeePHP CRUD: integration_items')) {
            throw new RuntimeException('IntegrationItem model exists without generated test routes; use a fresh test project.');
        }
        (new Migrator($this->root . '/database/migrations'))->run();
    }

    private function appendRoutes(string $relative, array $routes): void
    {
        $file = $this->root . '/' . $relative;
        $content = (string) file_get_contents($file);
        $marker = '// JeePHP integration fixture v1';
        if (!str_contains($content, $marker)) {
            if (str_contains($content, '/__jee_test/')) {
                throw new RuntimeException('Existing integration route conflicts in ' . $relative);
            }
            if (file_put_contents($file, rtrim($content) . "\n\n" . $marker . "\n" . implode("\n", $routes) . "\n", LOCK_EX) === false) {
                throw new RuntimeException('Cannot append fixture routes to ' . $relative);
            }
        }
    }

    private function verifyTarget(): void
    {
        $result = $this->http('GET', '/__jee_test/health');
        $this->expect($result['status'] === 200, 'Health route HTTP 200; got ' . $result['status'] . ' ' . substr($result['body'], 0, 180));
        $json = $this->decode($result);
        $this->expect(($json['project'] ?? '') === hash('sha256', (string) realpath($this->root)), 'URL points to another project.');
        $this->expect(($json['php'] ?? '') === PHP_VERSION, 'Apache and CLI use different PHP versions.');
    }

    private function routes(): void
    {
        $this->status('GET', '/', 200);
        $response = $this->http('GET', '/__jee_test/dynamic/42');
        $this->expect($response['status'] === 200 && ($this->decode($response)['id'] ?? '') === '42', 'Dynamic route did not resolve id.');
        $this->status('GET', '/__jee_test/missing', 404);
        $token = $this->http('GET', '/__jee_test/token');
        $this->expect($token['status'] === 200, 'Token route failed.');
        $this->csrf = (string) ($this->decode($token)['token'] ?? '');
        $this->expect(preg_match('/^[a-f0-9]{64}$/', $this->csrf) === 1, 'Invalid CSRF token.');
        $this->status('POST', '/integration_items', 419, http_build_query(['name' => 'blocked', 'note' => 'csrf']), ['Content-Type: application/x-www-form-urlencoded']);
    }

    private function migrations(): void
    {
        $migrator = new Migrator($this->root . '/database/migrations');
        $this->expect($migrator->run() === [], 'Second migration run must be idempotent.');
        $name = 'integration_rollback_' . bin2hex(random_bytes(5));
        $filename = $this->root . '/database/migrations/99991231_235959_' . $name . '.php';
        if (is_file($filename)) { throw new RuntimeException('Temporary migration collision.'); }
        $code = "<?php\ndeclare(strict_types=1);\nreturn new class implements \\JeePHP\\Database\\Migration {\n"
            . "public function up(\\PDO \$db): void { \$db->exec('CREATE TABLE `{$name}` (`id` INT PRIMARY KEY)'); }\n"
            . "public function down(\\PDO \$db): void { \$db->exec('DROP TABLE `{$name}`'); }\n};\n";
        if (file_put_contents($filename, $code, LOCK_EX) === false) { throw new RuntimeException('Cannot write temporary migration.'); }
        try {
            $applied = $migrator->run();
            $this->expect($applied === [basename($filename, '.php')], 'Temporary migration was not the only pending migration.');
            $this->expect($this->tableExists($name), 'Temporary migration did not create table.');
            $rolled = $migrator->rollback();
            $this->expect($rolled === $applied && !$this->tableExists($name), 'Rollback did not remove temporary table.');
        } finally {
            @unlink($filename);
        }
    }

    private function crud(): void
    {
        $name = 'jee_it_' . bin2hex(random_bytes(5));
        $this->status('GET', '/integration_items/create', 200);
        $this->status('POST', '/integration_items', 302, $this->form(['name' => $name, 'note' => 'original']));
        $id = (int) $this->db->query("SELECT MAX(id) FROM integration_items WHERE name = " . $this->db->quote($name))->fetchColumn();
        $this->expect($id > 0, 'CRUD create did not persist record.');
        $this->expect(str_contains($this->http('GET', '/integration_items')['body'], $name), 'CRUD index omitted record.');
        $this->expect(str_contains($this->http('GET', '/integration_items/' . $id)['body'], $name), 'CRUD show omitted record.');
        $this->status('POST', '/integration_items/' . $id . '/update', 302, $this->form(['name' => $name, 'note' => 'changed']));
        $this->expect($this->db->query('SELECT note FROM integration_items WHERE id = ' . $id)->fetchColumn() === 'changed', 'CRUD update failed.');
        $this->status('POST', '/integration_items/' . $id . '/delete', 302, $this->form([]));
        $this->expect($this->db->query('SELECT COUNT(*) FROM integration_items WHERE id = ' . $id)->fetchColumn() == 0, 'CRUD delete failed.');
    }

    private function auth(): void
    {
        $this->status('GET', '/auth/register', 200);
        $this->email = 'jee-it-' . bin2hex(random_bytes(7)) . '@example.test';
        $this->password = 'Integration!Pass' . bin2hex(random_bytes(5));
        $this->status('POST', '/auth/register', 302, $this->form(['name' => 'Integration Test', 'email' => $this->email, 'password' => $this->password]));
        $this->userId = (int) $this->db->query('SELECT id FROM auth_users WHERE email = ' . $this->db->quote($this->email))->fetchColumn();
        $this->expect($this->userId > 0, 'AuthO registration did not create user.');
        $this->refreshCsrf();
        $this->status('GET', '/auth/account', 200);
        $this->status('POST', '/auth/logout', 302, $this->form([]));
        $this->refreshCsrf();
        $this->status('POST', '/auth/login', 401, $this->form(['email' => $this->email, 'password' => 'wrong-password']));
        $this->status('POST', '/auth/login', 302, $this->form(['email' => $this->email, 'password' => $this->password]));
        $this->refreshCsrf();
        $this->status('GET', '/auth/account', 200);
    }

    private string $email = '';
    private string $password = '';
    private int $userId = 0;

    private function roles(): void
    {
        $this->status('GET', '/roles', 403);
        $this->status('GET', '/__jee_test/protected', 403);
        $role = 'it_' . bin2hex(random_bytes(5));
        $roleId = $this->insertId('roles', ['name' => $role]);
        $managerId = $this->permissionId('roles.manage');
        $viewId = $this->permissionId('integration.view');
        $this->insertId('user_roles', ['user_id' => $this->userId, 'role_id' => $roleId]);
        $this->insertId('role_permissions', ['role_id' => $roleId, 'permission_id' => $managerId]);
        $this->insertId('role_permissions', ['role_id' => $roleId, 'permission_id' => $viewId]);
        $this->status('GET', '/roles', 200);
        $this->status('GET', '/__jee_test/protected', 200);
        $createdRole = 'it_' . bin2hex(random_bytes(5));
        $this->status('POST', '/roles/create', 302, $this->form(['name' => $createdRole]));
        $this->expect($this->db->query('SELECT COUNT(*) FROM roles WHERE name = ' . $this->db->quote($createdRole))->fetchColumn() == 1, 'RolesO role creation failed.');
    }

    private function api(): void
    {
        $this->status('GET', '/api/status', 200);
        $this->status('POST', '/api/__jee_test/echo', 401, json_encode(['name' => 'test'], JSON_THROW_ON_ERROR), ['Content-Type: application/json', 'X-CSRF-Token: ' . $this->csrf]);
        $weak = ApiTokens::create($this->userId, 'test-weak', ['other.read']);
        $strong = ApiTokens::create($this->userId, 'test-strong', ['integration.read']);
        $json = json_encode(['name' => 'FrogO'], JSON_THROW_ON_ERROR);
        $this->status('POST', '/api/__jee_test/echo', 403, $json, ['Content-Type: application/json', 'Authorization: Bearer ' . $weak]);
        $response = $this->http('POST', '/api/__jee_test/echo', $json, ['Content-Type: application/json', 'Authorization: Bearer ' . $strong]);
        $data = $this->decode($response);
        $this->expect($response['status'] === 200 && ($data['user_id'] ?? 0) === $this->userId && ($data['name'] ?? '') === 'FrogO', 'API bearer or JSON body failed.');
        $this->status('POST', '/api/__jee_test/echo', 400, '{bad', ['Content-Type: application/json', 'Authorization: Bearer ' . $strong]);
    }

    private function upload(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLttAAAAABJRU5ErkJggg==', true);
        $this->expect(is_string($png), 'PNG fixture decode failed.');
        $boundary = 'jeep' . bin2hex(random_bytes(9));
        $make = fn (string $filename, string $type, string $body): string => "--{$boundary}\r\nContent-Disposition: form-data; name=\"_token\"\r\n\r\n" . $this->csrf
            . "\r\n--{$boundary}\r\nContent-Disposition: form-data; name=\"photo\"; filename=\"{$filename}\"\r\nContent-Type: {$type}\r\n\r\n" . $body . "\r\n--{$boundary}--\r\n";
        $headers = ['Content-Type: multipart/form-data; boundary=' . $boundary];
        $response = $this->http('POST', '/__jee_test/upload', $make('pixel.png', 'image/png', $png), $headers);
        $path = (string) ($this->decode($response)['path'] ?? '');
        $this->expect($response['status'] === 200 && preg_match('#^integration/[a-f0-9]{32}\.png$#', $path) === 1, 'Valid image upload failed.');
        $stored = $this->root . '/public/storage/' . $path;
        try {
            $this->expect(is_file($stored) && file_get_contents($stored) === $png, 'Stored upload bytes differ.');
        } finally {
            @unlink($stored);
        }
        $this->status('POST', '/__jee_test/upload', 422, $make('not-png.png', 'image/png', 'plain text, not PNG'), $headers);
    }

    private function backup(): void
    {
        $backup = new DatabaseBackup($this->root . '/storage/backups');
        $key = BackupCrypto::generateKey();
        $name = 'it_backup_' . bin2hex(random_bytes(5));
        $id = $this->insertId('integration_items', ['name' => $name, 'note' => 'before']);
        $path = $backup->create(true, $key);
        try {
            $this->expect(str_ends_with($path, '.sql.gz.enc') && is_file($path), 'Encrypted backup was not created.');
            $this->db->exec('UPDATE integration_items SET note = ' . $this->db->quote('after') . ' WHERE id = ' . $id);
            $this->expect($backup->restore($path, $key) > 0, 'Restore executed no statements.');
            $this->expect($this->db->query('SELECT note FROM integration_items WHERE id = ' . $id)->fetchColumn() === 'before', 'Restored data did not match backup.');
        } finally {
            @unlink($path);
        }
    }

    private function tableExists(string $table): bool
    {
        $query = $this->db->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1');
        $query->execute(['table' => $table]);
        return $query->fetchColumn() !== false;
    }

    private function insertId(string $table, array $values): int
    {
        $columns = array_keys($values);
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $columns) . '`) VALUES (:' . implode(', :', $columns) . ')';
        $this->db->prepare($sql)->execute($values);
        return (int) $this->db->lastInsertId();
    }

    private function permissionId(string $name): int
    {
        $found = $this->db->query('SELECT id FROM permissions WHERE name = ' . $this->db->quote($name))->fetchColumn();
        return $found === false ? $this->insertId('permissions', ['name' => $name]) : (int) $found;
    }

    private function refreshCsrf(): void
    {
        $response = $this->http('GET', '/__jee_test/token');
        $this->expect($response['status'] === 200, 'Could not refresh CSRF token.');
        $this->csrf = (string) ($this->decode($response)['token'] ?? '');
    }

    private function form(array $values): string
    {
        return http_build_query(['_token' => $this->csrf] + $values);
    }

    private function check(string $label, callable $test): void
    {
        try {
            $test();
            echo '[PASS] ' . $label . "\n";
        } catch (Throwable $error) {
            $this->failures++;
            fwrite(STDERR, '[FAIL] ' . $label . ': ' . $error->getMessage() . "\n");
        }
    }

    private function expect(bool $condition, string $message): void
    {
        if (!$condition) { throw new RuntimeException($message); }
    }

    private function status(string $method, string $path, int $expected, ?string $body = null, array $headers = []): void
    {
        if ($body !== null && $headers === [] && $method === 'POST') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        $response = $this->http($method, $path, $body, $headers);
        $this->expect($response['status'] === $expected, "{$method} {$path}: expected {$expected}, got {$response['status']}; " . substr($response['body'], 0, 240));
    }

    private function decode(array $response): array
    {
        $value = json_decode($response['body'], true);
        if (!is_array($value)) { throw new RuntimeException('Expected JSON: ' . substr($response['body'], 0, 240)); }
        return $value;
    }

    private function http(string $method, string $path, ?string $body = null, array $headers = []): array
    {
        if ($this->cookies !== []) {
            $headers[] = 'Cookie: ' . implode('; ', array_map(static fn ($name, $value): string => $name . '=' . $value, array_keys($this->cookies), $this->cookies));
        }
        $options = ['method' => $method, 'header' => implode("\r\n", $headers), 'ignore_errors' => true,
            'follow_location' => 0, 'timeout' => 12];
        if ($body !== null) { $options['content'] = $body; }
        $context = stream_context_create(['http' => $options]);
        $response = @file_get_contents($this->url . $path, false, $context);
        if ($response === false) { throw new RuntimeException("Cannot reach {$this->url}{$path}. Start Apache or php artisan serve and check APP_URL."); }
        $rawHeaders = $http_response_header ?? [];
        preg_match('/^HTTP\/\S+\s+(\d{3})/', (string) ($rawHeaders[0] ?? ''), $match);
        foreach ($rawHeaders as $header) {
            if (preg_match('/^Set-Cookie:\s*([^=;\s]+)=([^;\s]*)/i', $header, $cookie) === 1) {
                $this->cookies[$cookie[1]] = $cookie[2];
            }
        }
        return ['status' => (int) ($match[1] ?? 0), 'body' => $response];
    }
}
