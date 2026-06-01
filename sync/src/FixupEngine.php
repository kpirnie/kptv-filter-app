<?php

declare(strict_types=1);

namespace Kptv\IptvSync;

use Kptv\IptvSync\KpDb;
use Kptv\IptvSync\Database\WhereClause;
use Kptv\IptvSync\Database\ComparisonOperator;
use Kptv\IptvSync\Database\OrderByClause;

class FixupEngine
{
    private int $batchSize = 1000;
    private array $ignoreFields;
    private array $fieldMapping = [
        'tvg_id' => 's_tvg_id',
        'logo' => 's_tvg_logo',
        'tvg_group' => 's_tvg_group',
        'name' => 's_name',
        'channel' => 's_channel',
        'guide' => 's_guide',
    ];

    public function __construct(
        private readonly KpDb $db,
        array $ignoreFields = []
    ) {
        $this->ignoreFields = $ignoreFields;
    }

    private function shouldFixupField(string $fieldName): bool
    {
        foreach ($this->ignoreFields as $ignoreField) {
            if ($fieldName === ($this->fieldMapping[$ignoreField] ?? null)) {
                return false;
            }
        }
        return true;
    }

    public function fixupProvider(array $provider): int
    {
        $userId = $provider['u_id'];

        $total = 0;

        if ($this->shouldFixupField('s_name')) {
            $total += $this->fixupNames($userId) ?? 0;
        }

        if ($this->shouldFixupField('s_channel')) {
            $total += $this->fixupChannels($userId) ?? 0;
        }

        $total += $this->fixupMetadata($userId) ?? 0;

        return $total;
    }

    private function fixupNames(int $userId): int
    {
        $streams = $this->db->get_all(
            table: 'streams',
            columns: ['id', 's_orig_name', 's_name', 's_type_id', 's_updated'],
            where: [
                new WhereClause('u_id', $userId, ComparisonOperator::EQ)
            ],
            order_by: [
                new OrderByClause('s_updated', 'DESC'),
                new OrderByClause('id', 'DESC')
            ]
        );

        if (empty($streams)) {
            return 0;
        }

        $customNames = [];
        foreach ($streams as $s) {
            $key = strtolower($s['s_orig_name']) . '||' . $s['s_type_id'];
            $customName = trim($s['s_name'] ?? '');
            $origName = trim($s['s_orig_name']);

            if ($customName !== '' && $customName !== $origName) {
                if (!isset($customNames[$key])) {
                    $customNames[$key] = $customName;
                }
            }
        }

        $updates = [];
        foreach ($streams as $s) {
            $key = strtolower($s['s_orig_name']) . '||' . $s['s_type_id'];
            $currentName = trim($s['s_name'] ?? '');
            $origName = trim($s['s_orig_name']);

            if (isset($customNames[$key])) {
                $bestName = $customNames[$key];

                if (($currentName === '' || $currentName === $origName) && $currentName !== $bestName) {
                    $updates[] = [$s['id'], $bestName];
                }
            }
        }

        return $this->chunkedUpdate($updates, 's_name');
    }

    private function fixupChannels(int $userId): int
    {
        $streams = $this->db->get_all(
            table: 'streams',
            columns: ['id', 's_name', 's_channel', 's_updated'],
            where: [
                new WhereClause('u_id', $userId, ComparisonOperator::EQ)
            ],
            order_by: [
                new OrderByClause('s_updated', 'DESC'),
                new OrderByClause('id', 'DESC')
            ]
        );

        if (empty($streams)) {
            return 0;
        }

        $channelMap = [];
        foreach ($streams as $s) {
            $customName = trim($s['s_name'] ?? '');
            $channel = $s['s_channel'] ?? '0';

            if ($customName !== '' && $channel !== '' && $channel !== '0') {
                $key = strtolower($customName);
                if (!isset($channelMap[$key])) {
                    $channelMap[$key] = $channel;
                }
            }
        }

        $updates = [];
        foreach ($streams as $s) {
            $customName = trim($s['s_name'] ?? '');
            $currentChannel = $s['s_channel'] ?? '0';

            if ($customName !== '') {
                $key = strtolower($customName);
                if (isset($channelMap[$key])) {
                    $bestChannel = $channelMap[$key];

                    if (($currentChannel === '' || $currentChannel === '0') && $currentChannel !== $bestChannel) {
                        $updates[] = [$s['id'], $bestChannel];
                    }
                }
            }
        }

        return $this->chunkedUpdate($updates, 's_channel');
    }

    private function fixupMetadata(int $userId): int
    {
        $shouldFixLogo = $this->shouldFixupField('s_tvg_logo');
        $shouldFixTvgId = $this->shouldFixupField('s_tvg_id');
        $shouldFixGuide = $this->shouldFixupField('s_guide');

        if (!$shouldFixLogo && !$shouldFixTvgId && !$shouldFixGuide) {
            return 0;
        }

        $columns = ['id', 's_name', 's_updated'];
        if ($shouldFixLogo) {
            $columns[] = 's_tvg_logo';
        }
        if ($shouldFixTvgId) {
            $columns[] = 's_tvg_id';
        }
        if ($shouldFixGuide) {
            $columns[] = 's_guide';
        }

        $streams = $this->db->get_all(
            table: 'streams',
            columns: $columns,
            where: [
                new WhereClause('u_id', $userId, ComparisonOperator::EQ)
            ],
            order_by: [
                new OrderByClause('s_updated', 'DESC'),
                new OrderByClause('id', 'DESC')
            ]
        );

        if (empty($streams)) {
            return 0;
        }

        $metadataMap = [];
        foreach ($streams as $s) {
            $name = trim($s['s_name'] ?? '');

            if ($name === '') {
                continue;
            }

            $key = strtolower($name);
            $logo = $shouldFixLogo ? trim($s['s_tvg_logo'] ?? '') : '';
            $tvgId = $shouldFixTvgId ? trim($s['s_tvg_id'] ?? '') : '';
            $guide = $shouldFixGuide ? trim((string)($s['s_guide'] ?? '')) : '';

            if (!isset($metadataMap[$key])) {
                $metadataMap[$key] = [
                    'logo' => $logo,
                    'tvg_id' => $tvgId,
                    'guide' => $guide
                ];
            } else {
                if ($shouldFixLogo && $metadataMap[$key]['logo'] === '' && $logo !== '') {
                    $metadataMap[$key]['logo'] = $logo;
                }
                if ($shouldFixTvgId && $metadataMap[$key]['tvg_id'] === '' && $tvgId !== '') {
                    $metadataMap[$key]['tvg_id'] = $tvgId;
                }
                if ($shouldFixGuide && $metadataMap[$key]['guide'] === '' && $guide !== '') {
                    $metadataMap[$key]['guide'] = $guide;
                }
            }
        }

        $logoUpdates = [];
        $tvgUpdates = [];
        $guideUpdates = [];

        foreach ($streams as $s) {
            $name = trim($s['s_name'] ?? '');

            if ($name === '') {
                continue;
            }

            $key = strtolower($name);

            if (!isset($metadataMap[$key])) {
                continue;
            }

            $best = $metadataMap[$key];

            if ($shouldFixLogo) {
                $currentLogo = trim($s['s_tvg_logo'] ?? '');
                if ($best['logo'] !== '' && $currentLogo !== $best['logo']) {
                    $logoUpdates[] = [$s['id'], $best['logo']];
                }
            }

            if ($shouldFixTvgId) {
                $currentTvg = trim($s['s_tvg_id'] ?? '');
                if ($best['tvg_id'] !== '' && $currentTvg !== $best['tvg_id']) {
                    $tvgUpdates[] = [$s['id'], $best['tvg_id']];
                }
            }

            if ($shouldFixGuide) {
                $currentGuide = trim((string)($s['s_guide'] ?? ''));
                if ($best['guide'] !== '' && $currentGuide !== $best['guide']) {
                    $guideUpdates[] = [$s['id'], $best['guide']];
                }
            }
        }

        $fixed = 0;
        if ($shouldFixLogo) {
            $fixed += $this->chunkedUpdate($logoUpdates, 's_tvg_logo');
        }
        if ($shouldFixTvgId) {
            $fixed += $this->chunkedUpdate($tvgUpdates, 's_tvg_id');
        }
        if ($shouldFixGuide) {
            $fixed += $this->chunkedUpdate($guideUpdates, 's_guide');
        }
        return $fixed;
    }

    private function chunkedUpdate(array $updates, string $field): int
    {
        if (empty($updates)) {
            return 0;
        }

        $total = 0;
        $chunks = array_chunk($updates, $this->batchSize);

        foreach ($chunks as $chunk) {
            foreach ($chunk as [$streamId, $value]) {
                $this->db->update(
                    table: 'streams',
                    where: [new WhereClause('id', $streamId, ComparisonOperator::EQ)],
                    data: [$field => $value, 's_updated' => date('Y-m-d H:i:s')]
                );
            }

            $total += count($chunk);
        }

        return $total;
    }
}
