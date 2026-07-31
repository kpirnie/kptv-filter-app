<?php

declare(strict_types=1);

namespace Kptv\IptvSync;

use Kptv\IptvSync\KpDb;
use Kptv\IptvSync\Database\WhereClause;
use Kptv\IptvSync\Database\ComparisonOperator;
use Kptv\IptvSync\Parsers\ProviderFactory;
use Kptv\IptvSync\Parsers\XtremeCodesProvider;

class EpisodeSync
{
    private const TYPE_SERIES = 10;
    private const CHUNK = 50;

    public function __construct(
        private readonly KpDb $db,
        private readonly bool $force = false
    ) {}

    public function syncProvider(array $provider): int
    {
        $providerId = (int)$provider['id'];
        $userId = (int)$provider['u_id'];

        $parser = ProviderFactory::create($provider);

        if (!$parser instanceof XtremeCodesProvider) {
            echo "Not an Xtreme Codes provider - skipping\n";
            return 0;
        }

        $parents = $this->db->get_all(
            table: 'streams',
            columns: ['id', 's_extras', 's_series_modified'],
            where: [
                new WhereClause('u_id', $userId, ComparisonOperator::EQ),
                new WhereClause('p_id', $providerId, ComparisonOperator::EQ),
                new WhereClause('s_type_id', self::TYPE_SERIES, ComparisonOperator::EQ),
                new WhereClause('s_active', 1, ComparisonOperator::EQ)
            ]
        );

        if (empty($parents)) {
            echo "No active series for this provider\n";
            return 0;
        }

        echo sprintf("Found %s active series\n", number_format(count($parents)));

        $modified = $parser->fetchSeriesModified();
        $stale = [];

        foreach ($parents as $parent) {
            $seriesId = trim((string)($parent['s_extras'] ?? ''));

            if ($seriesId === '') {
                continue;
            }

            $current = $modified[$seriesId] ?? '';
            $stored = (string)($parent['s_series_modified'] ?? '');

            if (!$this->force && $stored !== '' && $stored === $current) {
                continue;
            }

            $stale[$seriesId] = ['id' => (int)$parent['id'], 'modified' => $current];
        }

        unset($parents, $modified);

        if (empty($stale)) {
            echo "All series episode lists are current\n";
            return 0;
        }

        echo sprintf("Refreshing episodes for %s series\n", number_format(count($stale)));

        $total = 0;

        foreach (array_chunk(array_keys($stale), self::CHUNK) as $batch) {
            foreach ($parser->fetchSeriesInfo($batch) as $seriesId => $info) {
                if (empty($info['episodes']) || !is_array($info['episodes'])) {
                    continue;
                }

                $total += $this->writeEpisodes(
                    $userId,
                    $providerId,
                    $stale[$seriesId]['id'],
                    $stale[$seriesId]['modified'],
                    $info['episodes'],
                    $parser
                );
            }

            echo sprintf("  %s episodes written...\n", number_format($total));
            gc_collect_cycles();
        }

        return $total;
    }

    private function writeEpisodes(
        int $userId,
        int $providerId,
        int $streamId,
        string $modified,
        array $seasons,
        XtremeCodesProvider $parser
    ): int {
        $records = [];

        foreach ($seasons as $seasonKey => $episodes) {
            if (!is_array($episodes)) {
                continue;
            }

            foreach ($episodes as $ep) {
                $episodeId = (string)($ep['id'] ?? '');

                if ($episodeId === '') {
                    continue;
                }

                $info = is_array($ep['info'] ?? null) ? $ep['info'] : [];
                $ext = (string)($ep['container_extension'] ?? '');

                $records[] = [
                    'u_id' => $userId,
                    'p_id' => $providerId,
                    's_id' => $streamId,
                    'se_prov_ep_id' => $episodeId,
                    'se_season' => (int)($ep['season'] ?? $info['season'] ?? $seasonKey),
                    'se_episode_num' => (int)($ep['episode_num'] ?? 0),
                    'se_title' => (string)($ep['title'] ?? $info['name'] ?? ''),
                    'se_container_ext' => $ext,
                    'se_stream_uri' => $parser->buildEpisodeUri($episodeId, $ext),
                    'se_plot' => $info['plot'] ?? null,
                    'se_duration' => $info['duration'] ?? null,
                    'se_duration_secs' => (int)($info['duration_secs'] ?? 0),
                    'se_bitrate' => (int)($info['bitrate'] ?? 0),
                    'se_rating' => (float)($info['rating'] ?? 0),
                    'se_release_date' => $info['releasedate'] ?? $info['release_date'] ?? null,
                    'se_tmdb_id' => isset($info['tmdb_id']) ? (string)$info['tmdb_id'] : null,
                    'se_cover' => $info['movie_image'] ?? null,
                    'se_cover_big' => $info['cover_big'] ?? null,
                    'se_video' => isset($info['video']) ? json_encode($info['video']) : null,
                    'se_audio' => isset($info['audio']) ? json_encode($info['audio']) : null,
                    'se_custom_sid' => isset($ep['custom_sid']) ? (string)$ep['custom_sid'] : null,
                    'se_direct_source' => isset($ep['direct_source']) ? (string)$ep['direct_source'] : null,
                    'se_added' => (int)($ep['added'] ?? 0),
                    'se_extras' => json_encode($ep)
                ];
            }
        }

        if (empty($records)) {
            return 0;
        }

        $this->db->delete(
            table: 'stream_episodes',
            where: [new WhereClause('s_id', $streamId, ComparisonOperator::EQ)]
        );

        $this->db->insert_many(
            table: 'stream_episodes',
            data: $records,
            ignore_duplicates: true,
            batch_size: 500
        );

        $this->db->execute_raw(
            "UPDATE kptv_streams SET s_series_modified = ?, s_updated = s_updated WHERE id = ?",
            [$modified, $streamId]
        );

        return count($records);
    }
}
