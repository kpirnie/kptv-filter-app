<?php

/**
 * KPTV XtreamCodes API Emulation
 * 
 * Full Xtream Codes API compatibility for IPTV apps
 * 
 * @since 8.4
 * @package KP Library
 * @author Kevin Pirnie <me@kpirnie.com>
 */

defined('KPTV_PATH') || die('Direct Access is not allowed!');

if (! class_exists('KPTV_Xtream_API')) {

    class KPTV_Xtream_API extends \KPT\Database
    {

        private const TYPE_LIVE = 0;
        private const TYPE_VOD = 4;
        private const TYPE_SERIES = 5;

        private ?int $userId = null;
        private ?int $providerId = null;
        private ?object $userRecord = null;

        private bool $logo = false;

        public function __construct()
        {
            parent::__construct(KPTV::get_setting('database'));
            $this->logo = \KPT\Sanitize::input(INPUT_GET, 'logo', FILTER_VALIDATE_BOOLEAN) ?? false;
        }

        /**
         * Main request handler for Xtream Codes API
         */
        public function handleRequest(): void
        {

            // hold the action to take
            $action = \KPT\Sanitize::string($_GET['action'] ?? $_POST['action'] ?? '', false, false);

            // 
            try {
                // Authenticate user first
                if (! $this->authenticateUser()) {
                    $this->sendError('Authentication failed', 401);
                    return;
                }

                // If no action, return user/server info (standard XC behavior)
                if (empty($action)) {
                    $this->getUserInfo();
                    return;
                }

                // match the action
                match ($action) {
                    // Live streams
                    'get_live_categories' => $this->getLiveCategories(),
                    'get_live_streams' => $this->getLiveStreams(),

                    // VOD
                    'get_vod_categories' => $this->getVodCategories(),
                    'get_vod_streams' => $this->getVodStreams(),
                    'get_vod_info' => $this->getVodInfo(),

                    // Series
                    'get_series_categories' => $this->getSeriesCategories(),
                    'get_series' => $this->getSeries(),
                    'get_series_info' => $this->getSeriesInfo(),

                    // EPG
                    'get_short_epg', 'get_simple_data_table' => $this->getShortEpg(),

                    // Panel API
                    'get_panel' => $this->getUserInfo(),

                    default => $this->sendError('Unknown action', 400),
                };
            } catch (\Throwable $e) {
                \KPT\Logger::error("XtreamAPI error", [
                    'action' => $action,
                    'error' => $e->getMessage()
                ]);

                $this->sendError("API error occurred", 500);
            }
        }

        /**
         * Authenticate user from username/password or user parameter
         */
        private function authenticateUser(): bool
        {

            // Try standard XC format: username & password
            $username = \KPT\Sanitize::username($_GET['username'] ?? $_POST['username'] ?? '');
            // password is an encrypted URL-safe base64 token - must preserve -_= chars
            $password = $_GET['password'] ?? $_POST['password'] ?? '';
            $password = preg_replace('/[^A-Za-z0-9\-_=]/', '', $password);

            // Also support legacy format: user parameter (encrypted user ID)
            $userParam = \KPT\Sanitize::username($_GET['user'] ?? $_POST['user'] ?? '');

            // Provider filter from GET param (legacy support)
            $providerParam = \KPT\Cast::intOrNull($_GET['provider'] ?? $_POST['provider'] ?? null);

            // Method 1
            if (!empty($userParam)) {
                $result = $this->query('SELECT id FROM kptv_users WHERE export_token = ? AND u_active = 1')
                    ->bind([$userParam])
                    ->single()
                    ->fetch();
                if ($result) {
                    $this->userId = (int)$result->id;
                    if ($providerParam !== null) {
                        $this->providerId = $providerParam;
                    }
                    return $this->validateUserId();
                }
            }

            // Method 2
            if (!empty($password)) {
                $result = $this->query('SELECT id FROM kptv_users WHERE export_token = ? AND u_active = 1')
                    ->bind([$password])
                    ->single()
                    ->fetch();
                if ($result) {
                    $this->userId = (int)$result->id;
                    if (!empty($username) && is_numeric($username)) {
                        $this->providerId = (int)$username;
                    }
                    return $this->validateUserId();
                }
            }

            return false;
        }

        /**
         * Validate that the user ID exists and is active
         */
        private function validateUserId(): bool
        {
            if (!$this->userId) {
                return false;
            }

            // get the users record
            $user = $this->query('SELECT id, u_name, u_email, u_created FROM kptv_users WHERE id = ? AND u_active = 1')
                ->bind([$this->userId])
                ->single()
                ->fetch();

            if ($user) {
                $this->userRecord = $user;
                return true;
            }

            return false;
        }

        /**
         * Get user/server info (called when no action specified)
         */
        private function getUserInfo(): void
        {

            $serverInfo = [
                'url' => rtrim(KPTV_URI, '/'),
                'port' => '443',
                'https_port' => '443',
                'server_protocol' => 'https',
                'rtmp_port' => '1935',
                'timezone' => 'America/New_York',
                'timestamp_now' => time(),
                'time_now' => date('Y-m-d H:i:s'),
            ];

            $userInfo = [
                'username' => $_GET['username'] ?? $_POST['username'] ?? '',
                'password' => $_GET['password'] ?? $_POST['password'] ?? '',
                'message' => 'Welcome to KPTV Stream Manager',
                'auth' => 1,
                'status' => 'Active',
                'exp_date' => strtotime('+1 year'),
                'is_trial' => '0',
                'active_cons' => '0',
                'created_at' => strtotime($this->userRecord->u_created ?? 'now'),
                'max_connections' => '1',
                'allowed_output_formats' => ['m3u8', 'ts', 'rtmp'],
            ];

            $this->sendSuccess([
                'user_info' => $userInfo,
                'server_info' => $serverInfo,
            ]);
        }

        /**
         * Get live stream categories
         */
        private function getLiveCategories(): void
        {
            $categories = $this->getCategories(self::TYPE_LIVE);
            $this->sendSuccess($categories);
        }

        /**
         * Get VOD categories
         */
        private function getVodCategories(): void
        {
            $categories = $this->getCategories(self::TYPE_VOD);
            $this->sendSuccess($categories);
        }

        /**
         * Get series categories
         */
        private function getSeriesCategories(): void
        {
            $categories = $this->getCategories(self::TYPE_SERIES);
            $this->sendSuccess($categories);
        }

        /**
         * Get categories for a stream type
         */
        private function getCategories(int $streamType): array
        {

            $sql = 'SELECT DISTINCT 
                    s_tvg_group as category_name
                    FROM kptv_streams 
                    WHERE u_id = ? AND s_active = 1 AND s_type_id = ?';

            $params = [$this->userId, $streamType];

            if ($this->providerId !== null) {
                $sql .= ' AND p_id = ?';
                $params[] = $this->providerId;
            }

            $sql .= ' ORDER BY s_tvg_group ASC';

            $results = $this->query($sql)->bind($params)->fetch();

            if (!$results) {
                return [];
            }

            $categories = [];
            $id = 1;

            foreach ($results as $row) {
                $catName = !empty($row->category_name) ? $row->category_name : 'Uncategorized';
                $categories[] = [
                    'category_id' => (string)$id,
                    'category_name' => $catName,
                    'parent_id' => 0,
                ];
                $id++;
            }

            return $categories;
        }

        /**
         * Get live streams
         */
        private function getLiveStreams(): void
        {

            $categoryId = \KPT\Cast::intOrNull($_GET['category_id'] ?? $_POST['category_id'] ?? null);


            $sql = 'SELECT
                    a.id as stream_id,
                    a.s_channel as num,
                    a.s_name as name,
                    a.s_stream_uri as direct_source,
                    a.s_tvg_id as epg_channel_id,
                    COALESCE(NULLIF(a.s_tvg_logo, ""), "https://cdn.kcp.im/tv/kptv-icon.svg") as stream_icon,
                    a.s_tvg_group as category_name,
                    b.sp_priority as stream_type,
                    a.s_extras as custom_sid
                    FROM kptv_streams a
                    LEFT OUTER JOIN kptv_stream_providers b ON b.id = a.p_id
                    WHERE a.u_id = ? AND a.s_active = 1 AND a.s_type_id = ?';

            $params = [$this->userId, self::TYPE_LIVE];

            if ($this->providerId !== null) {
                $sql .= ' AND a.p_id = ?';
                $params[] = $this->providerId;
            }

            if ($categoryId !== null) {
                $categories = $this->getCategories(self::TYPE_LIVE);
                if (isset($categories[$categoryId - 1])) {
                    $sql .= ' AND a.s_tvg_group = ?';
                    $params[] = $categories[$categoryId - 1]['category_name'];
                }
            }

            $sql .= ' ORDER BY b.sp_priority, a.s_name ASC';

            $results = $this->query($sql)->bind($params)->fetch();

            if (!$results) {
                $this->sendSuccess([]);
                return;
            }

            $streams = [];
            $catMap = $this->buildCategoryMap(self::TYPE_LIVE);

            foreach ($results as $row) {
                // setup the stream logo
                $the_logo = null;
                if ($this->logo) {
                    $the_logo = \KPTV::getLogoFromName($row->name);
                }
                $stream_logo = ($the_logo) ?: $row->stream_icon ?? 'https://cdn.kcp.im/tv/kptv-logo.png';

                $catName = !empty($row->category_name) ? $row->category_name : 'Uncategorized';
                $streams[] = [
                    'num' => (int)$row->num,
                    'name' => $row->name,
                    'stream_type' => 'live',
                    'stream_id' => (int)$row->stream_id,
                    'stream_icon' => $stream_logo,
                    'epg_channel_id' => $row->epg_channel_id,
                    'added' => time(),
                    'category_id' => (string)($catMap[$catName] ?? 1),
                    'category_name' => $catName,
                    'tv_archive' => 1,
                    'direct_source' => $row->direct_source,
                    'tv_archive_duration' => 0,
                    'custom_sid' => $row->custom_sid ?? '',
                ];
            }

            $this->sendSuccess($streams);
        }

        /**
         * Get VOD streams
         */
        private function getVodStreams(): void
        {
            $categoryId = \KPT\Cast::intOrNull($_GET['category_id'] ?? $_POST['category_id'] ?? null);

            $sql = 'SELECT
                    a.id as stream_id,
                    a.s_channel as num,
                    a.s_name as name,
                    a.s_stream_uri as direct_source,
                    COALESCE(NULLIF(a.s_tvg_logo, ""), "https://cdn.kcp.im/tv/kptv-icon.svg") as stream_icon,
                    a.s_tvg_group as category_name,
                    b.sp_priority as stream_type,
                    a.s_extras as container_extension
                    FROM kptv_streams a
                    LEFT OUTER JOIN kptv_stream_providers b ON b.id = a.p_id
                    WHERE a.u_id = ? AND a.s_active = 1 AND a.s_type_id = ?';

            $params = [$this->userId, self::TYPE_VOD];

            if ($this->providerId !== null) {
                $sql .= ' AND a.p_id = ?';
                $params[] = $this->providerId;
            }

            if ($categoryId !== null) {
                $categories = $this->getCategories(self::TYPE_VOD);
                if (isset($categories[$categoryId - 1])) {
                    $sql .= ' AND a.s_tvg_group = ?';
                    $params[] = $categories[$categoryId - 1]['category_name'];
                }
            }

            $sql .= ' ORDER BY b.sp_priority, a.s_name ASC';

            $results = $this->query($sql)->bind($params)->fetch();

            if (!$results) {
                $this->sendSuccess([]);
                return;
            }

            $streams = [];
            $catMap = $this->buildCategoryMap(self::TYPE_VOD);

            foreach ($results as $row) {
                $catName = !empty($row->category_name) ? $row->category_name : 'Uncategorized';

                $extension = $row->container_extension;
                if (empty($extension)) {
                    $extension = pathinfo(parse_url($row->direct_source, PHP_URL_PATH), PATHINFO_EXTENSION);
                    if (empty($extension)) {
                        $extension = 'mp4';
                    }
                }

                $streams[] = [
                    'num' => (int)$row->num,
                    'name' => $row->name,
                    'stream_type' => 'movie',
                    'stream_id' => (int)$row->stream_id,
                    'stream_icon' => $row->stream_icon,
                    'added' => time(),
                    'category_id' => (string)($catMap[$catName] ?? 1),
                    'category_name' => $catName,
                    'container_extension' => $extension,
                    'direct_source' => $row->direct_source,
                ];
            }

            $this->sendSuccess($streams);
        }

        /**
         * Get VOD info for a specific stream
         */
        private function getVodInfo(): void
        {

            $vodId = \KPT\Cast::intOrNull($_GET['vod_id'] ?? $_POST['vod_id'] ?? null);
            if (!$vodId) {
                $this->sendError('vod_id required', 400);
                return;
            }

            $sql = 'SELECT
                    a.id as stream_id,
                    a.s_name as name,
                    a.s_stream_uri as stream_url,
                    COALESCE(NULLIF(a.s_tvg_logo, ""), "https://cdn.kcp.im/tv/kptv-icon.svg") as stream_icon,
                    a.s_tvg_group as category_name,
                    a.s_extras as container_extension
                    FROM kptv_streams a
                    WHERE a.id = ? AND a.u_id = ? AND a.s_active = 1 AND a.s_type_id = ?';

            $result = $this->query($sql)
                ->bind([$vodId, $this->userId, self::TYPE_VOD])
                ->single()
                ->fetch();

            if (!$result) {
                $this->sendSuccess(['info' => []]);
                return;
            }

            $extension = $result->container_extension;
            if (empty($extension)) {
                $extension = pathinfo(parse_url($result->stream_url, PHP_URL_PATH), PATHINFO_EXTENSION);
                if (empty($extension)) {
                    $extension = 'mp4';
                }
            }

            $info = [
                'info' => [
                    'movie_image' => $result->stream_icon,
                    'name' => $result->name,
                    'stream_id' => (int)$result->stream_id,
                    'container_extension' => $extension,
                    'category_name' => $result->category_name ?? 'Uncategorized',
                ],
                'movie_data' => [
                    'stream_id' => (int)$result->stream_id,
                    'name' => $result->name,
                    'container_extension' => $extension,
                ],
            ];

            $this->sendSuccess($info);
        }

        /**
         * Get series list
         */
        private function getSeries(): void
        {

            $categoryId = \KPT\Cast::intOrNull($_GET['category_id'] ?? $_POST['category_id'] ?? null);

            $sql = 'SELECT
                    a.id as series_id,
                    a.s_channel as num,
                    a.s_name as name,
                    a.s_stream_uri as direct_source,
                    COALESCE(NULLIF(a.s_tvg_logo, ""), "https://cdn.kcp.im/tv/kptv-icon.svg") as cover,
                    a.s_tvg_group as category_name,
                    b.sp_priority as stream_type
                    FROM kptv_streams a
                    LEFT OUTER JOIN kptv_stream_providers b ON b.id = a.p_id
                    WHERE a.u_id = ? AND a.s_active = 1 AND a.s_type_id = ?';

            $params = [$this->userId, self::TYPE_SERIES];

            if ($this->providerId !== null) {
                $sql .= ' AND a.p_id = ?';
                $params[] = $this->providerId;
            }

            if ($categoryId !== null) {
                $categories = $this->getCategories(self::TYPE_SERIES);
                if (isset($categories[$categoryId - 1])) {
                    $sql .= ' AND a.s_tvg_group = ?';
                    $params[] = $categories[$categoryId - 1]['category_name'];
                }
            }

            $sql .= ' ORDER BY b.sp_priority, a.s_name ASC';

            $results = $this->query($sql)->bind($params)->fetch();

            if (!$results) {
                $this->sendSuccess([]);
                return;
            }

            $series = [];
            $catMap = $this->buildCategoryMap(self::TYPE_SERIES);

            $idx = 1;
            foreach ($results as $row) {
                $catName = !empty($row->category_name) ? $row->category_name : 'Uncategorized';
                $series[] = [
                    'num' => $idx,
                    'name' => $row->name,
                    'series_id' => (int)$row->series_id,
                    'cover' => $row->cover,
                    'plot' => '',
                    'cast' => '',
                    'director' => '',
                    'genre' => $catName,
                    'release_date' => '',
                    'last_modified' => date('Y-m-d H:i:s'),
                    'rating' => '',
                    'rating_5based' => 0,
                    'backdrop_path' => [],
                    'youtube_trailer' => '',
                    'episode_run_time' => '',
                    'category_id' => (string)($catMap[$catName] ?? 1),
                    'category_name' => $catName,
                    'direct_source' => $row->direct_source,
                ];
                ++$idx;
            }

            $this->sendSuccess($series);
        }

        /**
         * Get series info (episodes)
         */
        private function getSeriesInfo(): void
        {

            $seriesId = \KPT\Cast::intOrNull($_GET['series_id'] ?? $_POST['series_id'] ?? null);

            if (!$seriesId) {
                $this->sendError('series_id required', 400);
                return;
            }

            $sql = 'SELECT
                    a.id as stream_id,
                    a.s_name as name,
                    a.s_stream_uri as stream_url,
                    COALESCE(NULLIF(a.s_tvg_logo, ""), "https://cdn.kcp.im/tv/kptv-icon.svg") as cover,
                    a.s_tvg_group as category_name,
                    a.s_extras as container_extension
                    FROM kptv_streams a
                    WHERE a.id = ? AND a.u_id = ? AND a.s_active = 1 AND a.s_type_id = ?';

            $result = $this->query($sql)
                ->bind([$seriesId, $this->userId, self::TYPE_SERIES])
                ->single()
                ->fetch();

            if (!$result) {
                $this->sendSuccess(['info' => [], 'episodes' => []]);
                return;
            }

            $extension = $result->container_extension;
            if (empty($extension)) {
                $extension = pathinfo(parse_url($result->stream_url, PHP_URL_PATH), PATHINFO_EXTENSION);
                if (empty($extension)) {
                    $extension = 'mkv';
                }
            }

            $info = [
                'seasons' => [],
                'info' => [
                    'name' => $result->name,
                    'cover' => $result->cover,
                    'plot' => '',
                    'cast' => '',
                    'director' => '',
                    'genre' => $result->category_name ?? '',
                    'release_date' => '',
                    'backdrop_path' => [],
                    'youtube_trailer' => '',
                    'category_id' => '1',
                    'category_name' => $result->category_name ?? 'Uncategorized',
                ],
                'episodes' => [
                    '1' => [
                        [
                            'id' => (string)$result->stream_id,
                            'episode_num' => 1,
                            'title' => $result->name,
                            'container_extension' => $extension,
                            'info' => [
                                'movie_image' => $result->cover,
                                'name' => $result->name,
                            ],
                            'custom_sid' => '',
                            'added' => time(),
                            'season' => 1,
                            'direct_source' => '0',
                        ],
                    ],
                ],
            ];

            $this->sendSuccess($info);
        }

        /**
         * Get short EPG (empty placeholder)
         */
        private function getShortEpg(): void
        {
            // Return empty EPG data
            $this->sendSuccess([
                'epg_listings' => [],
            ]);
        }

        /**
         * Build category name to ID map
         */
        private function buildCategoryMap(int $streamType): array
        {
            $categories = $this->getCategories($streamType);
            $map = [];

            foreach ($categories as $cat) {
                $map[$cat['category_name']] = $cat['category_id'];
            }

            return $map;
        }

        /**
         * Send success response
         */
        private function sendSuccess($data): void
        {
            header('Content-Type: application/json');
            header('Cache-Control: no-cache, must-revalidate');
            header('Access-Control-Allow-Origin: *');
            http_response_code(200);
            $flags = JSON_UNESCAPED_SLASHES;
            if (defined('KPTV_DEBUG') && KPTV_DEBUG) {
                $flags |= JSON_PRETTY_PRINT;
            }
            echo json_encode($data, $flags);
            exit;
        }

        /**
         * Send error response
         */
        private function sendError(string $message, int $code = 400): void
        {
            header('Content-Type: application/json');
            header('Access-Control-Allow-Origin: *');
            http_response_code($code);
            echo json_encode([
                'user_info' => [
                    'auth' => 0,
                    'status' => 'Disabled',
                    'message' => $message,
                ],
            ], (defined('KPTV_DEBUG') && KPTV_DEBUG) ? JSON_PRETTY_PRINT : 0);
            exit;
        }

        /**
         * Handle stream playback redirect
         * Redirects to the actual stream URL
         */
        public function handleStreamRedirect(string $username, string $password, string $streamId): void
        {

            // Strip extension from streamId (e.g., "2000597.ts" -> "2000597")
            $streamId = preg_replace('/\.[a-zA-Z0-9]+$/', '', $streamId);

            // Authenticate - username is provider ID, password is encrypted user
            $_GET['username'] = $username;
            $_GET['password'] = $password;
            $_POST['username'] = $username;
            $_POST['password'] = $password;


            if (!$this->authenticateUser()) {
                http_response_code(401);
                die('Unauthorized');
            }

            // Look up the stream
            $sql = 'SELECT s_stream_uri FROM kptv_streams WHERE id = ? AND u_id = ? AND s_active = 1';
            $params = [$streamId, $this->userId];

            if ($this->providerId !== null) {
                $sql .= ' AND p_id = ?';
                $params[] = $this->providerId;
            }

            $result = $this->query($sql)
                ->bind($params)
                ->single()
                ->fetch();

            if (!$result || empty($result->s_stream_uri)) {
                http_response_code(404);
                die('Stream not found');
            }

            // Redirect to actual stream URL
            header('Location: ' . $result->s_stream_uri, true, 302);
            exit;
        }

        /**
         * Handle XMLTV EPG request
         */
        public function handleXmltvRequest(): void
        {
            if (!$this->authenticateUser()) {
                http_response_code(401);
                die('Unauthorized');
            }

            header('Content-Type: text/xml; charset=utf-8');
            header('Cache-Control: no-cache');
            echo '<?xml version="1.0" encoding="utf-8"?>' . PHP_EOL;
            echo '<!DOCTYPE tv SYSTEM "xmltv.dtd">' . PHP_EOL;
            echo '<tv generator-info-name="KPTV Stream Manager" source-info-name="KPTV Stream Manager">';

            // loop streams and output basic channel entries so the app has something to map
            $sql = 'SELECT s_tvg_id, s_name, s_tvg_logo FROM kptv_streams 
            WHERE u_id = ? AND s_active = 1 AND s_type_id = 0 AND s_tvg_id != ""
            GROUP BY s_tvg_id';
            $results = $this->query($sql)->bind([$this->userId])->fetch();

            if ($results) {
                foreach ($results as $row) {
                    echo sprintf(
                        '<channel id="%s"><display-name>%s</display-name><icon src="%s" /></channel>',
                        htmlspecialchars($row->s_tvg_id),
                        htmlspecialchars($row->s_name),
                        htmlspecialchars($row->s_tvg_logo ?? '')
                    ) . PHP_EOL;
                }
            }

            echo '</tv>' . PHP_EOL;
            exit;
        }
    }
}
