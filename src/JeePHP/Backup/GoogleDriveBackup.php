<?php
declare(strict_types=1);

namespace JeePHP\Backup;

use RuntimeException;

/** OAuth/PKCE and resumable upload support for a private JeePHP Drive folder. */
final class GoogleDriveBackup
{
    private const API = 'https://www.googleapis.com/drive/v3';
    private const UPLOAD_API = 'https://www.googleapis.com/upload/drive/v3';
    private const SCOPE = 'https://www.googleapis.com/auth/drive.file';

    public function __construct(
        private readonly string $privateDirectory,
        private readonly string $folderName,
        private readonly int $chunkBytes = 8 * 1024 * 1024
    ) {}

    /** Copy desktop OAuth credentials privately and connect this CLI to Drive. */
    public function connect(string $credentialPath): void
    {
        $source = realpath($credentialPath);
        if ($source === false || !is_file($source) || !is_readable($source)) {
            throw new RuntimeException('OAuth client JSON file not found: ' . $credentialPath);
        }
        $json = json_decode((string) file_get_contents($source), true);
        $credentials = is_array($json) ? ($json['installed'] ?? null) : null;
        if (!is_array($credentials) || !is_string($credentials['client_id'] ?? null)) {
            throw new RuntimeException('Use the OAuth client JSON downloaded from Google Cloud Console (Desktop app type).');
        }
        $this->ensurePrivateDirectory();
        $clientFile = $this->clientFile();
        if ($source !== realpath($clientFile)) {
            if (!copy($source, $clientFile)) {
                throw new RuntimeException('Could not copy the OAuth client file into storage/private.');
            }
            @chmod($clientFile, 0600);
        }

        $server = @stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);
        if ($server === false) {
            throw new RuntimeException('Could not start the local OAuth callback: ' . $errorMessage . ' (' . $errorNumber . ').');
        }
        $address = stream_socket_get_name($server, false);
        if (!is_string($address) || !preg_match('/^127\.0\.0\.1:(\d+)$/', $address, $portMatch)) {
            fclose($server);
            throw new RuntimeException('Could not determine the local OAuth callback port.');
        }

        $redirectUri = 'http://127.0.0.1:' . $portMatch[1];
        $verifier = self::base64Url(random_bytes(48));
        $challenge = self::base64Url(hash('sha256', $verifier, true));
        $state = bin2hex(random_bytes(32));
        $authUri = (string) ($credentials['auth_uri'] ?? 'https://accounts.google.com/o/oauth2/v2/auth');
        if (!in_array($authUri, ['https://accounts.google.com/o/oauth2/v2/auth', 'https://accounts.google.com/o/oauth2/auth'], true)) {
            fclose($server);
            throw new RuntimeException('OAuth authorization URL must be a Google Accounts endpoint.');
        }
        $url = $authUri . '?' . http_build_query([
            'client_id' => $credentials['client_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        echo "Google Drive connection\n\nOpen this URL in your browser and approve JeePHP's Drive access:\n\n" . $url . "\n\nWaiting up to 5 minutes for Google to return to this computer...\n";
        @set_time_limit(360);
        $connection = @stream_socket_accept($server, 300, $peer);
        fclose($server);
        if ($connection === false) {
            throw new RuntimeException('Google sign-in timed out. Run php jeep drive:connect again.');
        }
        stream_set_timeout($connection, 10);
        $requestLine = fgets($connection, 8192) ?: '';
        while (($header = fgets($connection, 8192)) !== false && trim($header) !== '') {
            // Read the complete local callback request before responding.
        }
        $remoteAddress = is_string($peer) ? explode(':', $peer, 2)[0] : '';
        if ($remoteAddress !== '127.0.0.1' || preg_match('#^GET\s+(\S+)\s+HTTP/1\.[01]$#', trim($requestLine), $requestMatch) !== 1) {
            self::respond($connection, 400, 'Invalid callback. Return to the JeePHP terminal.');
            fclose($connection);
            throw new RuntimeException('The OAuth callback did not come from the expected local browser request.');
        }

        $query = [];
        parse_str((string) parse_url($requestMatch[1], PHP_URL_QUERY), $query);
        if (!is_string($query['state'] ?? null) || !hash_equals($state, $query['state'])) {
            self::respond($connection, 400, 'The JeePHP security state did not match. Return to the terminal and try again.');
            fclose($connection);
            throw new RuntimeException('OAuth state verification failed.');
        }
        if (isset($query['error'])) {
            self::respond($connection, 400, 'Google access was not approved. Return to the terminal.');
            fclose($connection);
            throw new RuntimeException('Google OAuth was not approved: ' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $query['error']));
        }
        $code = $query['code'] ?? null;
        if (!is_string($code) || $code === '') {
            self::respond($connection, 400, 'Google did not return an authorization code. Return to the terminal.');
            fclose($connection);
            throw new RuntimeException('Google did not return an OAuth authorization code.');
        }
        self::respond($connection, 200, 'JeePHP is connected to Google Drive. You can close this browser tab.');
        fclose($connection);

        $tokenUri = (string) ($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token');
        if ($tokenUri !== 'https://oauth2.googleapis.com/token') {
            throw new RuntimeException('OAuth token URL must be the Google OAuth endpoint.');
        }
        $post = [
            'client_id' => $credentials['client_id'],
            'code' => $code,
            'code_verifier' => $verifier,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ];
        if (is_string($credentials['client_secret'] ?? null) && $credentials['client_secret'] !== '') {
            $post['client_secret'] = $credentials['client_secret'];
        }
        $token = $this->requestForm($tokenUri, $post);
        if (!is_string($token['refresh_token'] ?? null) || $token['refresh_token'] === '') {
            throw new RuntimeException('Google did not return a refresh token. Revoke JeePHP access in your Google Account and run drive:connect again.');
        }
        $token['expires_at'] = time() + (int) ($token['expires_in'] ?? 3600);
        $token['scope'] = self::SCOPE;
        $token['folder_id'] = null;
        $this->saveToken($token);
        $folderId = $this->createFolder();
        $token['folder_id'] = $folderId;
        $this->saveToken($token);
        echo "Connected. JeePHP Backups folder is ready in Google Drive.\n";
    }

    /** Uploads the encrypted file using Drive's resumable upload endpoint. */
    public function upload(string $backupPath): array
    {
        if (!is_file($backupPath) || !is_readable($backupPath)) {
            throw new RuntimeException('Backup file not found: ' . $backupPath);
        }
        $token = $this->accessToken();
        $folderId = $this->folderId($token);
        $size = filesize($backupPath);
        if (!is_int($size) || $size < 1) {
            throw new RuntimeException('Backup file is empty or its size could not be read.');
        }
        $metadata = json_encode([
            'name' => basename($backupPath),
            'mimeType' => 'application/octet-stream',
            'parents' => [$folderId],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $url = self::UPLOAD_API . '/files?uploadType=resumable&fields=id,name,size,createdTime';
        $headers = [];
        $status = $this->curlRequest('POST', $url, $metadata, [
            'Authorization: Bearer ' . $token['access_token'],
            'Content-Type: application/json; charset=UTF-8',
            'X-Upload-Content-Type: application/octet-stream',
            'X-Upload-Content-Length: ' . $size,
        ], $headers);
        if ($status < 200 || $status >= 300 || !isset($headers['location'])) {
            throw new RuntimeException('Google Drive could not start the backup upload: ' . $this->responseMessage($status));
        }
        $sessionUrl = $headers['location'];
        if (!str_starts_with($sessionUrl, 'https://')) {
            throw new RuntimeException('Google returned an invalid upload session URL.');
        }

        $handle = @fopen($backupPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Could not read backup file for Drive upload.');
        }
        $offset = 0;
        $final = null;
        $chunkSize = max(256 * 1024, intdiv(max(256 * 1024, $this->chunkBytes), 256 * 1024) * 256 * 1024);
        try {
            while ($offset < $size) {
                if (fseek($handle, $offset) !== 0) {
                    throw new RuntimeException('Could not seek to the next backup upload chunk.');
                }
                $targetBytes = min($chunkSize, $size - $offset);
                $chunk = self::readChunk($handle, $targetBytes);
                if ($chunk === '') {
                    throw new RuntimeException('Backup file ended before the upload completed.');
                }
                $length = strlen($chunk);
                $end = $offset + $length - 1;
                $attempt = 0;
                do {
                    $uploadHeaders = [];
                    $status = $this->curlRequest('PUT', $sessionUrl, $chunk, [
                        'Authorization: Bearer ' . $this->accessToken()['access_token'],
                        'Content-Type: application/octet-stream',
                        'Content-Length: ' . $length,
                        'Content-Range: bytes ' . $offset . '-' . $end . '/' . $size,
                    ], $uploadHeaders);
                    $attempt++;
                    if ($status === 308) {
                        $range = $uploadHeaders['headers']['range'] ?? '';
                        $acknowledged = 0;
                        if ($range !== '' && preg_match('/^bytes=0-(\d+)$/', $range, $rangeMatch) === 1) {
                            $acknowledged = (int) $rangeMatch[1] + 1;
                        }
                        if ($acknowledged < $offset || $acknowledged > $end + 1) {
                            throw new RuntimeException('Google Drive returned an invalid resumable-upload range.');
                        }
                        if ($acknowledged === $offset) {
                            if ($attempt >= 4) {
                                throw new RuntimeException('Google Drive did not acknowledge any backup bytes after several retries.');
                            }
                            usleep(250000 * (2 ** ($attempt - 1)));
                            continue;
                        }
                        $offset = $acknowledged;
                        break;
                    }
                    if ($status === 200 || $status === 201) {
                        $final = json_decode((string) ($uploadHeaders['body'] ?? ''), true);
                        $offset = $size;
                        break;
                    }
                    if (($status === 0 || $status >= 500) && $attempt < 4) {
                        usleep(250000 * (2 ** ($attempt - 1)));
                        continue;
                    }
                    throw new RuntimeException('Google Drive upload failed with HTTP ' . $status . ': ' . $this->responseMessage($status, (string) ($uploadHeaders['body'] ?? '')));
                } while (true);
                if (is_array($final)) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }

        if (!is_array($final) || !is_string($final['id'] ?? null)) {
            throw new RuntimeException('Drive upload ended without a file ID.');
        }
        return $final;
    }

    public function list(): array
    {
        $token = $this->accessToken();
        $folder = $this->folderId($token);
        $files = [];
        $pageToken = null;
        do {
            $query = [
                'q' => "'" . str_replace("'", "\\'", $folder) . "' in parents and trashed = false",
                'fields' => 'nextPageToken,files(id,name,size,createdTime,webViewLink)',
                'orderBy' => 'createdTime desc',
                'pageSize' => '1000',
            ];
            if (is_string($pageToken) && $pageToken !== '') {
                $query['pageToken'] = $pageToken;
            }
            $result = $this->driveJson('GET', self::API . '/files?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
            foreach ($result['files'] ?? [] as $file) {
                if (is_array($file)) {
                    $files[] = $file;
                }
            }
            $pageToken = $result['nextPageToken'] ?? null;
        } while (is_string($pageToken) && $pageToken !== '');
        return $files;
    }

    public function delete(string $fileId): void
    {
        self::assertFileId($fileId);
        $token = $this->accessToken();
        $this->driveJson('DELETE', self::API . '/files/' . rawurlencode($fileId), null, $token['access_token']);
    }

    public function download(string $fileId, string $destination): string
    {
        self::assertFileId($fileId);
        $token = $this->accessToken();
        $directory = dirname($destination);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create the local backup folder.');
        }
        $this->ensurePrivateDirectory();
        $temporary = $destination . '.tmp';
        $handle = @fopen($temporary, 'xb');
        if ($handle === false) {
            throw new RuntimeException('Could not create downloaded backup file.');
        }
        $curl = self::curlHandle('GET', self::API . '/files/' . rawurlencode($fileId) . '?alt=media', null, [
            'Authorization: Bearer ' . $token['access_token'],
        ]);
        curl_setopt($curl, CURLOPT_FILE, $handle);
        $result = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        fclose($handle);
        if ($result === false || $status < 200 || $status >= 300) {
            @unlink($temporary);
            throw new RuntimeException('Could not download the Drive backup. HTTP ' . $status . ($error !== '' ? ': ' . $error : '.'));
        }
        if (!rename($temporary, $destination)) {
            @unlink($temporary);
            throw new RuntimeException('Could not save the downloaded backup.');
        }
        @chmod($destination, 0600);
        return $destination;
    }

    public function prune(int $keep): int
    {
        if ($keep < 1) {
            throw new RuntimeException('Keep at least one Drive backup.');
        }
        $files = array_values(array_filter($this->list(), static fn (array $file): bool =>
            is_string($file['name'] ?? null)
            && preg_match('/^jeephp-database-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}\\.sql\\.gz\\.enc$/', $file['name']) === 1
        ));
        usort($files, static fn (array $a, array $b): int => strcmp((string) ($b['createdTime'] ?? ''), (string) ($a['createdTime'] ?? '')));
        $removed = 0;
        foreach (array_slice($files, $keep) as $file) {
            if (is_string($file['id'] ?? null)) {
                $this->delete($file['id']);
                $removed++;
            }
        }
        return $removed;
    }

    public function disconnect(): void
    {
        $token = $this->readToken();
        $revoked = false;
        if (is_string($token['refresh_token'] ?? null) && $token['refresh_token'] !== '') {
            $response = [];
            $status = $this->curlRequest('POST', 'https://oauth2.googleapis.com/revoke', http_build_query([
                'token' => $token['refresh_token'],
            ], '', '&', PHP_QUERY_RFC3986), ['Content-Type: application/x-www-form-urlencoded'], $response);
            $revoked = $status >= 200 && $status < 300;
        }
        @unlink($this->tokenFile());
        @unlink($this->clientFile());
        echo "Removed JeePHP's saved Google Drive authorization from this project.\n";
        if (!$revoked && is_string($token['refresh_token'] ?? null)) {
            echo "Google could not confirm remote revocation; revoke JeePHP in your Google Account security settings too.\n";
        }
    }

    private function folderId(array $token): string
    {
        if (is_string($token['folder_id'] ?? null) && $token['folder_id'] !== '') {
            return $token['folder_id'];
        }
        $id = $this->createFolder();
        $token['folder_id'] = $id;
        $this->saveToken($token);
        return $id;
    }

    private function createFolder(): string
    {
        $result = $this->driveJson('POST', self::API . '/files?fields=id,name', [
            'name' => $this->folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);
        if (!is_string($result['id'] ?? null)) {
            throw new RuntimeException('Google Drive did not return a folder ID.');
        }
        return $result['id'];
    }

    private function driveJson(string $method, string $url, ?array $body = null, ?string $accessToken = null): array
    {
        $token = $accessToken ?? $this->accessToken()['access_token'];
        $headers = [];
        $payload = $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $status = $this->curlRequest($method, $url, $payload, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json; charset=UTF-8',
        ], $headers);
        if ($status === 204 && $method === 'DELETE') {
            return [];
        }
        $decoded = json_decode((string) ($headers['body'] ?? ''), true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            throw new RuntimeException('Google Drive API request failed with HTTP ' . $status . ': ' . $this->responseMessage($status, (string) ($headers['body'] ?? '')));
        }
        return $decoded;
    }

    private function accessToken(): array
    {
        $token = $this->readToken();
        if (!is_string($token['access_token'] ?? null)) {
            throw new RuntimeException('Google Drive is not connected. Run php jeep drive:connect path/to/oauth-client.json.');
        }
        if ((int) ($token['expires_at'] ?? 0) > time() + 60) {
            return $token;
        }
        $refresh = $token['refresh_token'] ?? null;
        if (!is_string($refresh) || $refresh === '') {
            throw new RuntimeException('Google Drive authorization expired. Run php jeep drive:connect again.');
        }
        $client = $this->clientCredentials();
        $form = [
            'client_id' => $client['client_id'],
            'refresh_token' => $refresh,
            'grant_type' => 'refresh_token',
        ];
        if (is_string($client['client_secret'] ?? null) && $client['client_secret'] !== '') {
            $form['client_secret'] = $client['client_secret'];
        }
        $updated = $this->requestForm('https://oauth2.googleapis.com/token', $form);
        $token['access_token'] = $updated['access_token'] ?? '';
        $token['expires_at'] = time() + (int) ($updated['expires_in'] ?? 3600);
        $this->saveToken($token);
        return $token;
    }

    private function clientCredentials(): array
    {
        $json = json_decode((string) @file_get_contents($this->clientFile()), true);
        $credentials = is_array($json) ? ($json['installed'] ?? null) : null;
        if (!is_array($credentials) || !is_string($credentials['client_id'] ?? null)) {
            throw new RuntimeException('Saved Google OAuth client details are missing. Run php jeep drive:connect again.');
        }
        return $credentials;
    }

    private function requestForm(string $url, array $form): array
    {
        $headers = [];
        $status = $this->curlRequest('POST', $url, http_build_query($form, '', '&', PHP_QUERY_RFC3986), [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ], $headers);
        $decoded = json_decode((string) ($headers['body'] ?? ''), true);
        if ($status < 200 || $status >= 300 || !is_array($decoded) || !is_string($decoded['access_token'] ?? null)) {
            throw new RuntimeException('Google OAuth token request failed with HTTP ' . $status . ': ' . $this->responseMessage($status, (string) ($headers['body'] ?? '')));
        }
        return $decoded;
    }

    private function curlRequest(string $method, string $url, ?string $body, array $requestHeaders, array &$response): int
    {
        $response = ['headers' => [], 'body' => ''];
        $curl = self::curlHandle($method, $url, $body, $requestHeaders);
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, static function ($handle, string $line) use (&$response): int {
            $length = strlen($line);
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $response['headers'][strtolower(trim($name))] = trim($value);
            }
            return $length;
        });
        curl_setopt($curl, CURLOPT_WRITEFUNCTION, static function ($handle, string $chunk) use (&$response): int {
            $response['body'] .= $chunk;
            return strlen($chunk);
        });
        $ok = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($ok === false) {
            $response['body'] = $error;
            return 0;
        }
        $response['location'] = $response['headers']['location'] ?? null;
        return $status;
    }

    private static function curlHandle(string $method, string $url, ?string $body, array $requestHeaders)
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Enable the PHP cURL extension to connect to Google Drive.');
        }
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Could not initialize the PHP cURL extension.');
        }
        $options = [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_CUSTOMREQUEST => $method,
        ];
        if ($body !== null && $method !== 'GET' && $method !== 'DELETE') {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($curl, $options);
        return $curl;
    }

    /** Read a full requested chunk when possible; ordinary file reads can be short. */
    private static function readChunk($handle, int $bytes): string
    {
        $chunk = '';
        while (strlen($chunk) < $bytes && !feof($handle)) {
            $part = fread($handle, $bytes - strlen($chunk));
            if ($part === false) {
                throw new RuntimeException('Could not read the next backup upload chunk.');
            }
            if ($part === '') {
                break;
            }
            $chunk .= $part;
        }
        return $chunk;
    }

    private function saveToken(array $token): void
    {
        $this->ensurePrivateDirectory();
        $temporary = $this->tokenFile() . '.tmp-' . bin2hex(random_bytes(4));
        $json = json_encode($token, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        if (file_put_contents($temporary, $json, LOCK_EX) === false) {
            @unlink($temporary);
            throw new RuntimeException('Could not save the private Google Drive token.');
        }
        // Windows does not replace an existing file with rename(). Removing the
        // old token only after the new one is complete keeps token refresh usable.
        if (PHP_OS_FAMILY === 'Windows' && is_file($this->tokenFile())) {
            @unlink($this->tokenFile());
        }
        if (!rename($temporary, $this->tokenFile())) {
            @unlink($temporary);
            throw new RuntimeException('Could not finalize the private Google Drive token.');
        }
        @chmod($this->tokenFile(), 0600);
    }

    private function readToken(): array
    {
        $token = json_decode((string) @file_get_contents($this->tokenFile()), true);
        return is_array($token) ? $token : [];
    }

    private function ensurePrivateDirectory(): void
    {
        if (!is_dir($this->privateDirectory) && !mkdir($this->privateDirectory, 0700, true) && !is_dir($this->privateDirectory)) {
            throw new RuntimeException('Could not create private storage folder: ' . $this->privateDirectory);
        }
        @chmod($this->privateDirectory, 0700);
    }

    private function clientFile(): string
    {
        return $this->privateDirectory . '/google-drive-client.json';
    }

    private function tokenFile(): string
    {
        return $this->privateDirectory . '/google-drive-token.json';
    }

    private static function assertFileId(string $fileId): void
    {
        if (!preg_match('/^[A-Za-z0-9_-]{10,200}$/', $fileId)) {
            throw new RuntimeException('Invalid Google Drive file ID.');
        }
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /** @param resource $connection */
    private static function respond($connection, int $status, string $message): void
    {
        $body = '<!doctype html><meta charset="utf-8"><title>JeePHP Drive</title><p>'
            . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        $label = $status === 200 ? 'OK' : 'Bad Request';
        fwrite($connection, 'HTTP/1.1 ' . $status . ' ' . $label . "\r\nContent-Type: text/html; charset=utf-8\r\nConnection: close\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
    }

    private function responseMessage(int $status, string $body = ''): string
    {
        $decoded = json_decode($body, true);
        $message = is_array($decoded) ? ($decoded['error']['message'] ?? $decoded['error_description'] ?? null) : null;
        if (is_string($message) && $message !== '') {
            return $message;
        }
        return $status > 0 ? 'Google returned HTTP ' . $status . '.' : 'The network request could not be completed.';
    }
}
