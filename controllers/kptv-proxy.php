<?php

/**
 * Live Stream Proxy for CORS bypass
 * Handles HLS, MPEG-TS, and live video streams
 * PHP 8.4 compatible - NO CACHING/DOWNLOADS
 */

// no direct access
defined('KPTV_PATH') || die('Direct Access is not allowed!');

// Configuration
define('ALLOWED_DOMAINS', [
    // Add your allowed stream domains here for security
    // Example: 'stream.example.com',
    // Leave empty to allow all (not recommended for production)
]);

define('MAX_REDIRECTS', 5);
define('STREAM_CHUNK_SIZE', 4096); // 4KB chunks for streaming
define('PROXY_CONNECT_TIMEOUT', 5);
define('PROXY_REQUEST_TIMEOUT', 12);
define('PROXY_SSL_VERIFY', true);
define('ALLOWED_HTTP_METHODS', ['GET', 'HEAD', 'OPTIONS']);
define('MAX_PROXY_URL_LENGTH', 2048);

/**
 * KPTV_Proxy
 * 
 * Live Stream Proxy for CORS bypass
 * Handles HLS, MPEG-TS, and live video streams
 * PHP 8.4 compatible - NO CACHING/DOWNLOADS
 */
class KPTV_Proxy
{
    private string $url;
    private string $requestMethod = 'GET';

    /**
     * Main handler
     */
    public function handleStreamPlayback(): void
    {
        try {

            // Restrict methods to avoid abuse
            $this->requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
            if (!in_array($this->requestMethod, ALLOWED_HTTP_METHODS, true)) {
                header('Allow: ' . implode(', ', ALLOWED_HTTP_METHODS));
                $this->sendError('Method not allowed', 405);
                return;
            }

            // Handle OPTIONS request for CORS
            if ($this->requestMethod === 'OPTIONS') {
                $this->handleCorsOptions();
                return;
            }

            // Get and validate URL
            $this->url = trim((string)($_GET['url'] ?? ''));

            if ($this->url === '') {
                $this->sendError('No URL provided', 400);
                return;
            }

            // check the length
            if (strlen($this->url) > MAX_PROXY_URL_LENGTH) {
                $this->sendError('URL too long', 414);
                return;
            }

            // Validate URL
            if (!filter_var($this->url, FILTER_VALIDATE_URL)) {
                $this->sendError('Invalid URL', 400);
                return;
            }

            // make sure we're using a valid protocol
            $parsedUrl = parse_url($this->url);
            $scheme = strtolower($parsedUrl['scheme'] ?? '');
            if (!in_array($scheme, ['http', 'https'], true)) {
                $this->sendError('Unsupported URL scheme', 400);
                return;
            }

            // Handle OPTIONS request for CORS
            if (isset($parsedUrl['user']) || isset($parsedUrl['pass'])) {
                $this->sendError('Credentials in URL are not allowed', 400);
                return;
            }

            if (!$this->isPublicHost((string)($parsedUrl['host'] ?? ''))) {
                $this->sendError('Blocked target host', 403);
                return;
            }

            // Check domain whitelist if configured
            if (!empty(ALLOWED_DOMAINS) && !$this->isDomainAllowed()) {
                $this->sendError('Domain not allowed', 403);
                return;
            }

            // Determine content type and handle accordingly
            $urlPath = parse_url($this->url, PHP_URL_PATH);
            $extension = strtolower(pathinfo((string)$urlPath, PATHINFO_EXTENSION));

            // match the extension to determine how to handle the stream
            match ($extension) {
                'm3u8' => $this->handleM3U8(),
                'ts' => $this->streamDirect('video/mp2t'),
                default => $this->streamDirect(),
            };
        } catch (Exception $e) {
            error_log('Stream proxy error: ' . $e->getMessage());
            $this->sendError('Proxy error', 500);
        }
    }

    /**
     * Handle M3U8 playlist files (process URLs but don't cache)
     */
    private function handleM3U8(): void
    {
        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => MAX_REDIRECTS,
            CURLOPT_CONNECTTIMEOUT => PROXY_CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => PROXY_REQUEST_TIMEOUT,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => PROXY_SSL_VERIFY,
            CURLOPT_SSL_VERIFYHOST => PROXY_SSL_VERIFY ? 2 : 0,
            CURLOPT_HTTPHEADER => [
                'Connection: keep-alive',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept: application/vnd.apple.mpegurl, application/x-mpegURL, */*',
            ],
            CURLOPT_NOBODY => $this->requestMethod === 'HEAD',
        ]);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 400 || $content === false) {
            $this->sendError('Failed to fetch playlist', 502);
            return;
        }

        // Send response with appropriate headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
        header('Access-Control-Allow-Headers: Range, Origin, Content-Type');
        header('Access-Control-Expose-Headers: Content-Length, Content-Range');
        header('Content-Type: application/vnd.apple.mpegurl');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        if ($this->requestMethod === 'HEAD') {
            return;
        }

        // Process playlist - convert relative URLs to absolute via proxy
        echo $this->processM3U8((string)$content);
    }

    /**
     * Process M3U8 content to proxy URLs
     */
    private function processM3U8(string $content): string
    {
        $lines = explode("\n", $content);
        $baseUrl = dirname($this->url) . '/';
        $processed = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Keep empty lines and comments as-is
            if ($line === '' || str_starts_with($line, '#')) {
                $processed[] = $line;
                continue;
            }

            // Handle protocol-relative URLs
            if (str_starts_with($line, '//')) {
                $origin = parse_url($this->url);
                $line = ($origin['scheme'] ?? 'https') . ':' . $line;
            }

            // Convert to absolute URL if relative
            if (!filter_var($line, FILTER_VALIDATE_URL)) {
                if (str_starts_with($line, '/')) {
                    $parsed = parse_url($this->url);
                    $line = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . $line;
                } else {
                    $line = $baseUrl . $line;
                }
            }

            // Proxy the URL
            $processed[] = '/proxy/stream?url=' . urlencode($line);
        }

        return implode("\n", $processed);
    }

    /**
     * Stream content directly without buffering
     */
    private function streamDirect(?string $forceContentType = null): void
    {
        // Build request headers
        $requestHeaders = [];

        // Pass through range header if present and valid
        if (isset($_SERVER['HTTP_RANGE'])) {
            $range = trim((string)$_SERVER['HTTP_RANGE']);
            if (preg_match('/^bytes=\d*-\d*(,\d*-\d*)*$/', $range)) {
                $requestHeaders[] = 'Range: ' . $range;
            }
        }

        // Add user agent
        $requestHeaders[] = 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

        // Initialize cURL
        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => MAX_REDIRECTS,
            CURLOPT_CONNECTTIMEOUT => PROXY_CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => PROXY_REQUEST_TIMEOUT,
            CURLOPT_BUFFERSIZE => STREAM_CHUNK_SIZE,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => PROXY_SSL_VERIFY,
            CURLOPT_SSL_VERIFYHOST => PROXY_SSL_VERIFY ? 2 : 0,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_HEADERFUNCTION => [$this, 'captureResponseHeader'],
            CURLOPT_WRITEFUNCTION => [$this, 'streamResponseBody'],
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => [$this, 'checkAbort'],
            CURLOPT_NOBODY => $this->requestMethod === 'HEAD',
        ]);

        // Disable output buffering for live streaming
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Set CORS headers first
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
        header('Access-Control-Allow-Headers: Range, Origin, Content-Type');
        header('Access-Control-Expose-Headers: Content-Length, Content-Range');

        // Prevent caching for live streams
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Override content type if specified
        if ($forceContentType) {
            header('Content-Type: ' . $forceContentType);
        }

        // Execute the stream
        curl_exec($ch);

        if (curl_errno($ch)) {
            error_log('cURL error: ' . curl_error($ch));
        }

        curl_close($ch);
    }

    /**
     * Capture response headers from origin
     */
    private function captureResponseHeader($ch, string $header): int
    {
        $len = strlen($header);
        $header = trim($header);

        if ($header === '') {
            return $len;
        }

        // Parse header
        if (preg_match('/^HTTP\//', $header)) {
            // Status line
            $parts = explode(' ', $header, 3);
            if (isset($parts[1])) {
                http_response_code((int)$parts[1]);
            }
        } else {
            // Regular header
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $name = trim($parts[0]);
                $value = trim($parts[1]);

                // Pass through certain headers
                $passthrough = [
                    'content-type',
                    'content-length',
                    'content-range',
                    'accept-ranges',
                    'etag',
                    'last-modified',
                ];

                if (in_array(strtolower($name), $passthrough, true)) {
                    header($name . ': ' . $value);
                }
            }
        }

        return $len;
    }

    /**
     * Stream response body directly to client
     */
    private function streamResponseBody($ch, string $data): int
    {
        if (connection_aborted()) {
            return 0;
        }

        echo $data;
        flush();

        return strlen($data);
    }

    /**
     * Check if connection was aborted
     */
    private function checkAbort($ch, $downloadTotal, $downloadNow, $uploadTotal, $uploadNow): int
    {
        if (connection_aborted()) {
            return 1; // Abort cURL
        }
        return 0; // Continue
    }

    /**
     * Handle CORS preflight OPTIONS request
     */
    private function handleCorsOptions(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
        header('Access-Control-Allow-Headers: Range, Origin, Content-Type, Accept');
        header('Access-Control-Max-Age: 86400');
        header('Content-Length: 0');
        http_response_code(204);
    }

    /**
     * Check if domain is allowed
     */
    private function isDomainAllowed(): bool
    {
        if (empty(ALLOWED_DOMAINS)) {
            return true; // Allow all if not configured
        }

        $host = parse_url($this->url, PHP_URL_HOST);
        return is_string($host) && in_array($host, ALLOWED_DOMAINS, true);
    }

    /**
     * Send error response
     */
    private function sendError(string $message, int $code): void
    {
        http_response_code($code);
        header('Content-Type: text/plain');
        header('Access-Control-Allow-Origin: *');
        echo $message;
    }

    /**
     * Block localhost/private/reserved destinations to reduce SSRF risk.
     */
    private function isPublicHost(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host);
        }

        if (strtolower($host) === 'localhost') {
            return false;
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if ($records === false || $records === []) {
            return false;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if ($ip !== null && !$this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private function isPublicIp(string $ip): bool
    {
        return (bool)filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}

// Initialize and run
//$proxy = new LiveStreamProxy();
//$proxy->handleStreamPlayback();