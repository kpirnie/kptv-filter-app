<?php

/**
 * KPTV Stream Playlists class
 * 
 * Handles the playlist rendering
 * 
 * @since 8.4
 * @package KP Library
 * @author Kevin Pirnie <me@kpirnie.com>
 */

defined('KPTV_PATH') || die('Direct Access is not allowed!');

// make sure the class isn't already in userspace
if (! class_exists('KPTV_Stream_Playlists')) {

    /**
     * KPTV Stream Playlists class
     * 
     * Handles the playlist rendering
     * 
     * @since 8.4
     * @package KP Library
     * @author Kevin Pirnie <me@kpirnie.com>
     */
    class KPTV_Stream_Playlists extends \KPT\Database
    {
        private bool $logo = false;

        public function __construct()
        {
            parent::__construct(KPTV::get_setting('database'));
            $this->logo = filter_input(INPUT_GET, 'logo', FILTER_VALIDATE_BOOLEAN) ?? false;
        }

        /**
         * Handle user playlist route (user + which)
         * 
         * @param string $user Encrypted user ID
         * @param string $which Which streams to pull
         * @return void Outputs M3U playlist directly
         */
        public function handleUserPlaylist(string $user, string $which): void
        {

            try {
                // setup the type of stream to pull...
                $stream_type = ['live' => 0, 'series' => 5, 'vod' => 4, 'all' => 50];

                // Get playlist data
                $records = $this->getUserPlaylist($user, $stream_type[$which]);

                // Generate and output M3U
                $this->outputM3UPlaylist($records, $which, $user);
            } catch (\Throwable $e) {
                \KPT\Logger::error("User playlist generation failed", [
                    'user' => $user,
                    'which' => $which,
                    'error' => $e->getMessage()
                ]);

                $this->outputErrorResponse("Failed to generate playlist");
            }
        }

        /**
         * Handle provider playlist route (user + provider + which)
         * 
         * @param string $user Encrypted user ID
         * @param string $provider Encrypted provider ID
         * @param string $which Which streams to pull
         * @return void Outputs M3U playlist directly
         */
        public function handleProviderPlaylist(string $user, int $provider, string $which): void
        {

            try {
                // setup the type of stream to pull...
                $stream_type = ['live' => 0, 'series' => 5, 'vod' => 4, 'all' => 50];

                // Get playlist data
                $records = $this->getGetProviderPlaylist($user, $provider, $stream_type[$which]);

                // Generate and output M3U
                $this->outputM3UPlaylist($records, $which, $user);
            } catch (\Throwable $e) {
                \KPT\Logger::error("Provider playlist generation failed", [
                    'user' => $user,
                    'provider' => $provider,
                    'which' => $which,
                    'error' => $e->getMessage()
                ]);

                $this->outputErrorResponse("Failed to generate playlist");
            }
        }

        /**
         * Pull streams for a specific provider
         * 
         * @param string $user The encrypted user ID we need to pull a playlist for
         * @param string $provider The encrypted provider ID we need to pull a playlist for
         * @param int $which Which streams are we actually pulling (0=live, 5=series, etc.)
         * @return array|bool Returns matching streams or false if none found
         */
        private function getGetProviderPlaylist(string $user, int $provider, int $which): array|bool
        {

            // setup the provider and user
            $user = KPTV::decrypt($user);

            // if we need to get all
            if ($which === 50) {
                $which = [0, 4, 5];
            }

            // setup the query to run
            $sql = 'SELECT
                    a.`s_channel` As TvgChNo, 
                    a.`s_name` As TvgName, 
                    a.`s_stream_uri` As Stream, 
                    COALESCE(nullif(a.`s_tvg_id`,""), a.`s_orig_name`) As TvgID, 
                    COALESCE(nullif(a.`s_tvg_group`,""), "live") As TvgGroup,
                    COALESCE(nullif(a.`s_tvg_logo`,""), "https://cdn.kcp.im/tv/kptv-icon.svg") As TvgLogo, 
                    a.`p_id`,
                    b.`sp_priority` As TvgType,
                    a.`s_type_id` AS StreamType
                    FROM `kptv_streams` a
                    LEFT OUTER JOIN `kptv_stream_providers` b ON b.`id` = a.`p_id`
                    WHERE ( a.`p_id` = ? AND `a`.`u_id` = ? ) AND ( a.`s_active` = 1 AND %s )
                    GROUP BY a.`s_stream_uri`
                    ORDER BY TvgType, a.`s_name` ASC;';

            // if we need to get all streams
            if (is_array($which)) {
                $placeholders = str_repeat('?,', count($which) - 1) . '?';
                $sql = sprintf($sql, "a.`s_type_id` IN ($placeholders)");
            } else {
                $sql = sprintf($sql, "a.`s_type_id` = ?");
            }

            // setup the parameters
            $params = is_array($which)
                ? array_merge([$provider, $user], $which)
                : [$provider, $user, $which];

            // setup the recordset
            $rs = $this->query($sql)->bind($params)->fetch();

            // return the records
            return $rs;
        }

        /**
         * Pull streams for a user (all providers)
         * 
         * @param string $user The encrypted user ID we need to pull a playlist for
         * @param int $which Which streams are we actually pulling (0=live, 5=series, etc.)
         * @return array|bool Returns matching streams or false if none found
         */
        private function getUserPlaylist(string $user, int $which): array|bool
        {

            // setup the user
            $user = KPTV::decrypt($user);

            // if we need to get all
            if ($which === 50) {
                $which = [0, 4, 5];
            }

            // setup the query to run
            $sql = 'SELECT
                    a.`s_channel` as TvgChNo, 
                    a.`s_name` as TvgName, 
                    a.`s_stream_uri` as Stream, 
                    COALESCE(nullif(a.`s_tvg_id`,""), a.`s_orig_name`) As TvgID, 
                    COALESCE(nullif(a.`s_tvg_group`,""), "live") As TvgGroup,
                    COALESCE(nullif(a.`s_tvg_logo`,""), "https://cdn.kcp.im/tv/kptv-icon.svg") As TvgLogo, 
                    a.`p_id`,
                    b.`sp_priority` AS TvgType,
                    a.`s_type_id` AS StreamType
                    FROM `kptv_streams` a
                    LEFT OUTER JOIN `kptv_stream_providers` b ON b.`id` = a.`p_id`
                    WHERE a.`u_id` = ? AND ( a.`s_active` = 1 AND %s )
                    GROUP BY a.`s_stream_uri`
                    ORDER BY TvgType, a.`s_name` ASC;';

            // if we need to get all streams
            if (is_array($which)) {
                $placeholders = str_repeat('?,', count($which) - 1) . '?';
                $sql = sprintf($sql, "a.`s_type_id` IN ($placeholders)");
            } else {
                $sql = sprintf($sql, "a.`s_type_id` = ?");
            }

            // setup the parameters
            $params = is_array($which)
                ? array_merge([$user], $which)
                : [$user, $which];

            // setup the recordset
            $rs = $this->query($sql)->bind($params)->fetch();

            // return the records
            return $rs;
        }

        /**
         * Generate and output M3U playlist (using original working logic)
         * 
         * @param array|bool $records Stream data from database
         * @param string $which Stream type name
         * @return void Outputs M3U content directly
         */
        private function outputM3UPlaylist($records, string $which, ?string $user = null): void
        {

            // make sure there's records
            if ($records) {

                // hold the user
                $user = KPTV::decrypt($user);

                // set the mimetype and filename to download
                header('Content-Type: application/mpegurl');
                header('Content-Disposition: attachment; filename="' . $which . '.m3u8"');

                // set the expires and caching headers
                header('Expires: ' . gmdate("D, d M Y H:i:s", time() + KPTV::DAY_IN_SECONDS) . " GMT", true);
                header('Cache-Control: public, max-age=' . KPTV::DAY_IN_SECONDS, true);
                header_remove('set-cookie');

                // start the M3U no matter what
                echo "#EXTM3U" . PHP_EOL;

                // loop over the records
                foreach ($records as $rec) {

                    // start creating the EXTINF line
                    $extinf = sprintf('#EXTINF:-1 tvg-name="%s" tvg-chno="%s" tvg-type="%s"', $rec->TvgName, $rec->TvgChNo, $rec->TvgType);

                    // set the group for the m3u based on the stream type (live, series, vod)
                    $group = match ($rec->StreamType) {
                        0 => 'live',
                        5 => 'series',
                        4 => 'vod',
                        default => 'other'
                    };

                    // write the group for the m3u
                    $extinf .= sprintf(' tvg-group="%s"', $group);
                    $extinf .= sprintf(' group-title="%s"', $group);

                    // if there's a tvg-id
                    if (! empty($rec->TvgID)) {
                        $extinf .= sprintf(' tvg-id="%s"', $rec->TvgID);
                    }

                    // setup the stream logo
                    $the_logo = null;
                    if ($this->logo && 0 === $rec->StreamType) {
                        // format the channel name
                        $chan = preg_replace(
                            ['/\s*&\s*/', '/[+\s]/', '/[^a-z0-9\-]/', '/-{2,}/'],
                            ['-and-', '-', '', '-'],
                            mb_strtolower(trim($rec->TvgName))
                        );
                        $the_logo = sprintf('https://cdn.kcp.im/tv/logos/%s.png', $chan);
                    }
                    $stream_logo = ($the_logo) ?: $rec->TvgLogo ?? 'https://cdn.kcp.im/tv/kptv-logo.png';

                    // add the logo
                    $extinf .= sprintf(' tvg-logo="%s"', $stream_logo);

                    // finish up the extinf line and write it out
                    $extinf .= sprintf(', %s', $rec->TvgName);
                    echo $extinf . PHP_EOL;

                    // write out the stream url line
                    echo $rec->Stream . PHP_EOL;
                }
            }
        }

        /**
         * Output error response for playlist requests
         * 
         * @param string $message Error message
         * @return void Outputs error response
         */
        private function outputErrorResponse(string $message): void
        {

            // Set error headers
            http_response_code(500);
            header('Content-Type: application/mpegurl');

            // Output minimal M3U with error
            echo "#EXTM3U" . PHP_EOL;
            echo "# Error: {$message}" . PHP_EOL;
        }
    }
}
