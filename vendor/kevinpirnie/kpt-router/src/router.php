<?php

/**
 * KPT Router Class
 *
 * This class provides a comprehensive routing solution with middleware support,
 * rate limiting, and view rendering capabilities.
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// if the class does not exist already
if (! class_exists('\KPT\Router', false)) {

    /**
     * KPT Router Class
     *
     * Handles HTTP routing with support for all standard methods (GET, POST, etc.),
     * middleware pipelines, rate limiting, and view rendering.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    class Router
    {
        // inherit our traits
        use RouterRateLimiter;
        use RouterMiddlewareHandler;
        use RouterRouteHandler;
        use RouterRequestProcessor;
        use RouterResponseHandler;

        /** @var string the routing base path */
        private string $basePath = '';
        private string $appPath = '';

        /** @var array trusted proxy CIDR ranges */
        private static array $trustedProxies = [];

        /**
         * Constructor
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $basePath The base path for all routes
         */
        public function __construct(string $basePath = '', string $appPath = '')
        {

            // set the base paths
            $this->basePath = self::sanitizePath($basePath);
            $this->appPath = !empty($appPath) ? rtrim($appPath, '/') : getcwd();
            $this->viewsPath = $this->appPath . '/views';
            $this->rateLimitPath = $this->appPath . '/tmp/kpt_rate_limits';

            // debug logging
            Logger::debug("Router Constructor Completed", [
                'base_path' => $this->basePath,
                'views_path' => $this->viewsPath
            ]);
        }

        /**
         * Destructor
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         */
        public function __destruct()
        {

            // clean up the arrays
            if (isset($this->routes)) {
                $this->routes = [];
            }
            if (isset($this->middlewares)) {
                $this->middlewares = [];
            }
            if (isset($this->middlewareDefinitions)) {
                $this->middlewareDefinitions = [];
            }

            // try to clean up the redis connection
            try {
                // if we have the object, close it
                if (isset($this->redis) && $this->redis) {
                    // close the redis connection
                    $this->redis->close();
                }

                // whoopsie... log an error
            } catch (\Throwable $e) {
                // error logging
                Logger::error("Router Redis Connection Close Error", [
                    'message' => $e->getMessage(),
                ]);
            }

            // debug logging
            Logger::debug("Router Destructor Completed", []);
        }

        /**
         * setTrustedProxies
         *
         * Sets the proxy CIDR ranges allowed to supply the X-Forwarded-For header
         *
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param array $cidrs Array of IPs or CIDR ranges (ex: 10.0.0.0/8, 172.16.0.0/12, ::1)
         * @return void Returns nothing
         *
         */
        public static function setTrustedProxies(array $cidrs): void
        {

            // hold the validated ranges
            $valid = [];

            // loop over the ranges passed
            foreach ($cidrs as $cidr) {
                // split the subnet and mask
                [$subnet, $bits] = array_pad(explode('/', trim((string) $cidr), 2), 2, null);

                // skip anything that isn't a valid ip
                if (! filter_var($subnet, FILTER_VALIDATE_IP)) {
                    Logger::error("Invalid Trusted Proxy", ['cidr' => $cidr]);
                    continue;
                }

                // the max mask depends on the address family
                $maxBits = str_contains($subnet, ':') ? 128 : 32;

                // skip invalid masks
                if ($bits !== null && (! ctype_digit($bits) || (int) $bits > $maxBits)) {
                    Logger::error("Invalid Trusted Proxy", ['cidr' => $cidr]);
                    continue;
                }

                // hold it, a bare ip is a single host
                $valid[] = $subnet . '/' . ($bits ?? $maxBits);
            }

            // set the trusted proxies
            self::$trustedProxies = $valid;
        }

        /**
         * getUserIp
         *
         * Gets the current users IP address, only honoring X-Forwarded-For
         * when the request comes through a trusted proxy
         *
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return string Returns a string containing the users IP address
         *
         */
        public static function getUserIp(): string
        {

            // the connecting address is the only one we can actually trust
            $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
            if (! filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
                return '';
            }

            // if it isn't a trusted proxy, or there's nothing forwarded, it's the client
            if (! self::isTrustedProxy($remoteAddr) || empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                return $remoteAddr;
            }

            // walk the forwarded chain right to left while the hops are trusted proxies
            $clientIp = $remoteAddr;
            $hops = array_reverse(array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));
            foreach ($hops as $hop) {
                // stop on anything that isn't a valid ip
                if (! filter_var($hop, FILTER_VALIDATE_IP)) {
                    break;
                }

                // this hop is the closest client so far
                $clientIp = $hop;

                // the first untrusted hop is the client
                if (! self::isTrustedProxy($hop)) {
                    break;
                }
            }

            // return the client ip
            return $clientIp;
        }

        /**
         * isTrustedProxy
         *
         * Checks if an IP address falls in one of the trusted proxy ranges
         *
         * @since 8.4
         * @access private
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param string $ip The IP address to check
         * @return bool Returns true if the IP is a trusted proxy
         *
         */
        private static function isTrustedProxy(string $ip): bool
        {

            // loop the trusted ranges and check for a match
            foreach (self::$trustedProxies as $cidr) {
                if (self::cidrMatch($ip, $cidr)) {
                    return true;
                }
            }

            // default return
            return false;
        }

        /**
         * cidrMatch
         *
         * Checks if an IP address is inside a CIDR range, IPv4 or IPv6
         *
         * @since 8.4
         * @access private
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @param string $ip The IP address to check
         * @param string $cidr The validated CIDR range
         * @return bool Returns true if the IP is in the range
         *
         */
        private static function cidrMatch(string $ip, string $cidr): bool
        {

            // split the range and get the binary addresses
            [$subnet, $bits] = explode('/', $cidr, 2);
            $ipBin = inet_pton($ip);
            $subnetBin = inet_pton($subnet);

            // different address families never match
            if ($ipBin === false || strlen($ipBin) !== strlen($subnetBin)) {
                return false;
            }

            // compare the whole bytes
            $bits = (int) $bits;
            $bytes = intdiv($bits, 8);
            if (substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
                return false;
            }

            // compare the remaining bits
            $remainder = $bits % 8;
            if ($remainder === 0) {
                return true;
            }
            $mask = (0xFF << (8 - $remainder)) & 0xFF;
            return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
        }

        /**
         * getUserUri
         *
         * Gets the current users URI that was attempted
         *
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         *
         * @return string Returns a string containing the URI
         *
         */
        public static function getUserUri(): string
        {

            // return the current URL
            // phpcs:ignore Generic.Files.LineLength.TooLong
            return filter_var(
                (
                    isset($_SERVER['HTTPS']) &&
                    $_SERVER['HTTPS'] === 'on' ? "https" : "http"
                ) . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
                FILTER_SANITIZE_URL
            );
        }

        /**
         * Sanitize path
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string|null $path Path to sanitize
         * @return string Sanitized path
         */
        public static function sanitizePath(?string $path): string
        {

            if (empty($path)) {
                return '/';
            }
            $path = preg_split('/[?#]/', $path, 2)[0];
            $path = preg_replace('#/+#', '/', $path);
            // Only normalize multiple slashes
            $path = trim($path, '/');
            return $path === '' ? '/' : '/' . $path;
        }
    }
}
