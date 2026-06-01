<?php

declare(strict_types=1);

namespace Kptv\IptvSync;

use Kptv\IptvSync\KpDb;
use Kptv\IptvSync\Database\WhereClause;
use Kptv\IptvSync\Database\ComparisonOperator;
use Kptv\IptvSync\Parsers\ProviderFactory;

class MissingChecker
{
    public function __construct(
        private readonly KpDb $db,
        private readonly bool $checkAll = false
    ) {}

    public function checkProvider(array $provider): array
    {
        $providerId = $provider['id'];
        $userId = $provider['u_id'];

        // Get streams from provider
        $parser = ProviderFactory::create($provider);
        $providerStreams = $parser->fetchStreams();

        // Build URI and name lookups.
        // URI normalization handles common transient URL differences
        // (e.g. rotating token/expires/signature params).
        $providerUris = [];
        $providerNames = [];

        foreach ($providerStreams as $providerStream) {
            $uri = (string)($providerStream['s_stream_uri'] ?? '');
            if ($uri !== '') {
                $providerUris[$uri] = true;

                $normalizedUri = $this->normalizeUri($uri);
                if ($normalizedUri !== '') {
                    $providerUris[$normalizedUri] = true;
                }
            }

            $name = $this->normalizeName((string)($providerStream['s_orig_name'] ?? ''));
            if ($name !== '') {
                $providerNames[$name] = true;
            }
        }

        // Build where clauses
        $where = [
            new WhereClause('u_id', $userId, ComparisonOperator::EQ),
            new WhereClause('p_id', $providerId, ComparisonOperator::EQ)
        ];

        // Only filter by active if not checking all
        if (!$this->checkAll) {
            $where[] = new WhereClause('s_active', 1, ComparisonOperator::EQ);
        }

        // Scan streams in chunks to reduce peak memory
        $missing = [];
        $offset = 0;
        $chunkSize = 2000;

        while (true) {
            $dbStreams = $this->db->get_all(
                table: 'streams',
                columns: ['id', 's_stream_uri', 's_orig_name'],
                where: $where,
                limit: $chunkSize,
                offset: $offset
            );

            if (empty($dbStreams)) {
                break;
            }

            foreach ($dbStreams as $stream) {
                $dbUri = (string)($stream['s_stream_uri'] ?? '');
                $dbName = $this->normalizeName((string)($stream['s_orig_name'] ?? ''));

                $existsByUri = false;
                if ($dbUri !== '') {
                    $existsByUri = isset($providerUris[$dbUri]);

                    if (!$existsByUri) {
                        $normalizedDbUri = $this->normalizeUri($dbUri);
                        if ($normalizedDbUri !== '') {
                            $existsByUri = isset($providerUris[$normalizedDbUri]);
                        }
                    }
                }

                $existsByName = $dbName !== '' && isset($providerNames[$dbName]);

                if (!$existsByUri && !$existsByName) {
                    $missing[] = $stream;
                }
            }

            if (count($dbStreams) < $chunkSize) {
                break;
            }

            $offset += $chunkSize;
            unset($dbStreams);
        }

        // Record missing streams
        if (!empty($missing)) {
            $this->recordMissing($userId, $providerId, $missing);
        }

        return $missing;
    }

    private function normalizeName(string $name): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? ''));
    }

    private function normalizeUri(string $uri): string
    {
        $uri = trim(html_entity_decode($uri, ENT_QUOTES | ENT_HTML5));
        if ($uri === '') {
            return '';
        }

        $parts = parse_url($uri);
        if ($parts === false) {
            return $uri;
        }

        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) . '://' : '';
        $host = isset($parts['host']) ? strtolower($parts['host']) : '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = $parts['user'] ?? null;
        $pass = $parts['pass'] ?? null;

        $auth = '';
        if ($user !== null) {
            $auth = $user;
            if ($pass !== null) {
                $auth .= ':' . $pass;
            }
            $auth .= '@';
        }

        $path = $parts['path'] ?? '';
        $path = preg_replace('#/+#', '/', $path) ?? $path;

        if ($path !== '/' && $path !== '') {
            $path = rtrim($path, '/');
        }

        $query = '';
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $queryParams);

            foreach (['token', 'auth', 'auth_token', 'expires', 'expiry', 'exp', 'signature', 'sig', 'hash', 'key'] as $volatileKey) {
                unset($queryParams[$volatileKey]);
            }

            if (!empty($queryParams)) {
                ksort($queryParams);
                $query = '?' . http_build_query($queryParams);
            }
        }

        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . $auth . $host . $port . $path . $query . $fragment;
    }

    private function recordMissing(int $userId, int $providerId, array $missing): void
    {
        $records = [];

        foreach ($missing as $stream) {
            $records[] = [
                'u_id' => $userId,
                'p_id' => $providerId,
                'stream_id' => $stream['id'],
                'other_id' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ];
        }

        if (!empty($records)) {
            $this->db->insert_many(
                table: 'stream_missing',
                data: $records,
                ignore_duplicates: true
            );
        }
    }
}
