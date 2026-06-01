<?php

declare(strict_types=1);

namespace Kptv\IptvSync\Parsers;

use Kptv\IptvSync\CurlClient;

abstract class BaseProvider
{
    protected string  $domain;
    protected ?string $username;
    protected ?string $password;
    protected string  $streamTypeExt;
    protected int     $cnxLimit;

    protected CurlClient $curl;

    public function __construct(
        protected readonly array $provider
    ) {
        $this->domain        = $provider['sp_domain'];
        $this->username      = $provider['sp_username'] ?? null;
        $this->password      = $provider['sp_password'] ?? null;
        $this->streamTypeExt = ((int)($provider['sp_stream_type'] ?? 0)) === 0 ? 'ts' : 'm3u8';
        $this->cnxLimit      = max(1, (int)($provider['sp_cnx_limit'] ?? 1));

        $this->curl = new CurlClient(cnxLimit: $this->cnxLimit);
    }

    abstract public function fetchStreams(): array;

    /**
     * Fetch a single URL. Returns response body as string.
     */
    protected function makeRequest(string $url): string
    {
        return $this->curl->get($url);
    }

    /**
     * Fetch multiple URLs in parallel, rolling window capped at cnxLimit.
     * Returns array keyed by the same keys as $urls.
     *
     * @param  array<int|string, string> $urls
     * @return array<int|string, string>
     */
    protected function makeMultiRequest(array $urls): array
    {
        return $this->curl->getMulti($urls);
    }
}