<?php

declare(strict_types=1);

namespace Kptv\IptvSync\Parsers;

class XtremeCodesProvider extends BaseProvider
{
    private string $apiLive;
    private string $apiSeries;
    private string $apiVod;
    private string $apiLiveCats;
    private string $apiSeriesCats;
    private string $apiVodCats;
    private string $apiSeriesInfo;
    private string $streamLive;
    private string $streamSeries;
    private string $streamVod;
    private array $categoryMaps = [];
    private array $seriesModified = [];

    public function __construct(array $provider)
    {
        parent::__construct($provider);

        $api = "{$this->domain}/player_api.php?username={$this->username}&password={$this->password}&action=";

        $this->apiLive = "{$api}get_live_streams";
        $this->apiSeries = "{$api}get_series";
        $this->apiVod = "{$api}get_vod_streams";
        $this->apiLiveCats = "{$api}get_live_categories";
        $this->apiSeriesCats = "{$api}get_series_categories";
        $this->apiVodCats = "{$api}get_vod_categories";
        $this->apiSeriesInfo = "{$api}get_series_info&series_id=%s";

        $this->streamLive = "{$this->domain}/live/{$this->username}/{$this->password}/%s.{$this->streamTypeExt}";
        $this->streamSeries = "{$this->domain}/series/{$this->username}/{$this->password}/%s.%s";
        $this->streamVod = "{$this->domain}/movie/{$this->username}/{$this->password}/%s.%s";
    }

    public function fetchStreams(): array
    {
        echo "Fetching streams from Xtreme Codes API...\n";
        $allStreams = [];

        // load in the categories
        $this->loadCategories();

        // Fetch Live Streams
        try {
            echo "Fetching live streams...\n";
            $liveStreams = $this->fetchApi($this->apiLive, 0);
            echo sprintf("Retrieved %s live streams\n", number_format(count($liveStreams)));
            $allStreams = [...$allStreams, ...$liveStreams];
            sleep(1);
        } catch (\Exception $e) {
            echo "⚠️  Error fetching live streams: {$e->getMessage()}\n";
        }

        // Fetch VOD Streams
        try {
            echo "Fetching VOD streams...\n";
            $vodStreams = $this->fetchApi($this->apiVod, 4);
            echo sprintf("Retrieved %s VOD streams\n", number_format(count($vodStreams)));
            $allStreams = [...$allStreams, ...$vodStreams];
            sleep(1);
        } catch (\Exception $e) {
            echo "⚠️  Error fetching VOD streams: {$e->getMessage()}\n";
        }

        // Fetch Series
        try {
            echo "Fetching series...\n";
            $seriesStreams = $this->fetchApi($this->apiSeries, 10);
            echo sprintf("Retrieved %s series\n", number_format(count($seriesStreams)));
            $allStreams = [...$allStreams, ...$seriesStreams];
        } catch (\Exception $e) {
            echo "⚠️  Error fetching series: {$e->getMessage()}\n";
        }

        echo sprintf("Total streams retrieved: %s\n", number_format(count($allStreams)));
        return $allStreams;
    }

    private function fetchApi(string $url, int $streamType): array
    {
        //if ($streamType === 4) {
        //    return [];
        //}

        $response = $this->makeRequest($url);
        $data = json_decode($response, true);

        if (!is_array($data)) {
            // Surface the actual response for diagnosis
            $preview = substr($response, 0, 300);
            throw new \RuntimeException(
                "Invalid API response - expected array, got: {$preview}"
            );
        }

        $streams = [];
        $skipped = 0;

        foreach ($data as $item) {

            //$itemType = $item['stream_type'] ?? null;
            //if ($itemType === 'movie' || $itemType === 4 || $itemType === '4') {
            //    continue;
            //}
            //$catName = strtolower($item['category_name'] ?? '');
            //if (str_contains($catName, 'vod') || str_contains($catName, 'movie') || str_contains($catName, 'film')) {
            //    continue;
            //}

            $streamId = $item['stream_id'] ?? $item['series_id'] ?? null;

            if ($streamId === null) {
                $skipped++;
                continue;
            }

            $uri = match ($streamType) {
                0 => sprintf($this->streamLive, $streamId),
                4 => sprintf(
                    $this->streamVod,
                    $streamId,
                    (string)($item['container_extension'] ?? '') ?: $this->streamTypeExt
                ),
                10 => sprintf($this->apiSeriesInfo, $streamId),
                default => ''
            };

            if (empty($uri)) {
                $skipped++;
                continue;
            }

            $name = $item['name'] ?? '';
            $typeId = $streamType;
            if ($streamType !== 10 && str_contains(strtolower($name), '24/7')) {
                $typeId = 5;
                $item['category_name'] = '24/7 Channels';
            }

            $catId = (string)($item['category_id'] ?? '');
            $catName = $this->categoryMaps[$streamType][$catId] ?? ($item['category_name'] ?? null);

            if ($streamType === 10) {
                $this->seriesModified[(string)$streamId] = (string)($item['last_modified'] ?? '');
            }

            $streams[] = [
                's_type_id' => $typeId,
                's_orig_name' => $name,
                's_tvg_id' => $item['epg_channel_id'] ?? $item['tmdb_id'] ?? null,
                's_stream_uri' => $uri,
                's_tvg_group' => $catName,
                's_tvg_logo' => $item['stream_icon'] ?? $item['cover'] ?? null,
                's_extras' => $streamType === 10 ? (string)$streamId : null
            ];
        }

        if ($skipped > 0) {
            echo sprintf("  Skipped %s items (missing stream_id)\n", number_format($skipped));
        }

        return $streams;
    }

    private function loadCategories(): void
    {
        $sources = [
            0 => $this->apiLiveCats,
            4 => $this->apiVodCats,
            10 => $this->apiSeriesCats,
        ];

        foreach ($sources as $typeId => $url) {
            $this->categoryMaps[$typeId] = [];

            try {
                $data = json_decode($this->makeRequest($url), true);
            } catch (\Exception $e) {
                echo "⚠️  Error fetching categories for type {$typeId}: {$e->getMessage()}\n";
                continue;
            }

            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $cat) {
                $catId = (string)($cat['category_id'] ?? '');
                $catName = trim((string)($cat['category_name'] ?? ''));

                if ($catId === '' || $catName === '') {
                    continue;
                }

                $this->categoryMaps[$typeId][$catId] = $catName;
            }
        }

        echo sprintf(
            "Loaded categories: %s live, %s vod, %s series\n",
            number_format(count($this->categoryMaps[0])),
            number_format(count($this->categoryMaps[4])),
            number_format(count($this->categoryMaps[10]))
        );
    }

    public function getSeriesModified(): array
    {
        return $this->seriesModified;
    }

    public function fetchSeriesModified(): array
    {
        $data = json_decode($this->makeRequest($this->apiSeries), true);

        if (!is_array($data)) {
            return [];
        }

        $modified = [];

        foreach ($data as $item) {
            $seriesId = $item['series_id'] ?? $item['stream_id'] ?? null;

            if ($seriesId === null) {
                continue;
            }

            $modified[(string)$seriesId] = (string)($item['last_modified'] ?? '');
        }

        return $modified;
    }

    public function fetchSeriesInfo(array $seriesIds): array
    {
        $urls = [];

        foreach ($seriesIds as $seriesId) {
            $urls[(string)$seriesId] = sprintf($this->apiSeriesInfo, $seriesId);
        }

        $out = [];

        foreach ($this->makeMultiRequest($urls) as $seriesId => $body) {
            $data = json_decode((string)$body, true);
            $out[(string)$seriesId] = is_array($data) ? $data : [];
        }

        return $out;
    }

    public function buildEpisodeUri(string $episodeId, string $extension): string
    {
        return sprintf($this->streamSeries, $episodeId, $extension !== '' ? $extension : 'mkv');
    }
}
