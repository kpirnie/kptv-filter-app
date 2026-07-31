<?php

declare(strict_types=1);

namespace Kptv\IptvSync;

use Kptv\IptvSync\KpDb;
use Kptv\IptvSync\Database\WhereClause;
use Kptv\IptvSync\Database\ComparisonOperator;
use Kptv\IptvSync\Parsers\ProviderFactory;

class GroupBackfill
{
    private const CHUNK = 2000;

    public function __construct(
        private readonly KpDb $db
    ) {}

    public function backfillProvider(array $provider): int
    {
        $providerId = (int)$provider['id'];
        $userId = (int)$provider['u_id'];

        $parser = ProviderFactory::create($provider);

        echo "Fetching streams from provider...\n";
        $rawStreams = $parser->fetchStreams();

        if (empty($rawStreams)) {
            echo "No streams returned from provider\n";
            return 0;
        }

        $groupByUri = [];

        foreach ($rawStreams as $stream) {
            $uri = (string)($stream['s_stream_uri'] ?? '');
            $group = trim((string)($stream['s_tvg_group'] ?? ''));

            if ($uri === '' || $group === '') {
                continue;
            }

            $groupByUri[$uri] = $group;
        }

        unset($rawStreams);
        gc_collect_cycles();

        echo sprintf("Built %s group mappings\n", number_format(count($groupByUri)));

        if (empty($groupByUri)) {
            return 0;
        }

        $updated = 0;
        $offset = 0;

        while (true) {
            $dbStreams = $this->db->get_all(
                table: 'streams',
                columns: ['id', 's_stream_uri', 's_tvg_group'],
                where: [
                    new WhereClause('u_id', $userId, ComparisonOperator::EQ),
                    new WhereClause('p_id', $providerId, ComparisonOperator::EQ)
                ],
                limit: self::CHUNK,
                offset: $offset
            );

            if (empty($dbStreams)) {
                break;
            }

            foreach ($dbStreams as $stream) {
                $uri = (string)$stream['s_stream_uri'];

                if (!isset($groupByUri[$uri])) {
                    continue;
                }

                if ((string)($stream['s_tvg_group'] ?? '') === $groupByUri[$uri]) {
                    continue;
                }

                $this->db->execute_raw(
                    "UPDATE kptv_streams SET s_tvg_group = ?, s_updated = s_updated WHERE id = ?",
                    [$groupByUri[$uri], $stream['id']]
                );

                $updated++;

                if ($updated % 500 === 0) {
                    echo sprintf("  Updated %s groups...\n", number_format($updated));
                }
            }

            $offset += self::CHUNK;
            unset($dbStreams);
            gc_collect_cycles();
        }

        echo sprintf("Updated %s stream groups\n", number_format($updated));

        return $updated;
    }
}
