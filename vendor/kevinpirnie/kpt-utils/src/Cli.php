<?php

/**
 * CLI Functions
 *
 * This is our primary CLI utility class
 *
 * @since      8.2
 * @author     Kevin Pirnie <me@kpirnie.com>
 * @package    KP Library
 */

declare(strict_types=1);

// namespace this class
namespace KPT;

// make sure the class does not already exist
if (! class_exists('\KPT\Cli')) {

    /**
     * Cli
     *
     * A modern PHP 8.2+ CLI utility providing ANSI output, styled text,
     * tables, determinate and indeterminate progress indicators, interactive
     * prompts, select menus, and argument parsing.
     *
     * @package    KP Library
     * @author     Kevin Pirnie <me@kpirnie.com>
     * @copyright  2026 Kevin Pirnie
     * @license    MIT
     */
    class Cli
    {
        // -------------------------------------------------------------------------
        // ANSI color/style constants
        // -------------------------------------------------------------------------

        // Styles
        public const RESET     = "\033[0m";
        public const BOLD      = "\033[1m";
        public const DIM       = "\033[2m";
        public const ITALIC    = "\033[3m";
        public const UNDERLINE = "\033[4m";
        public const BLINK     = "\033[5m";
        public const REVERSE   = "\033[7m";
        public const HIDDEN    = "\033[8m";

        // Foreground colors
        public const BLACK   = "\033[30m";
        public const RED     = "\033[31m";
        public const GREEN   = "\033[32m";
        public const YELLOW  = "\033[33m";
        public const BLUE    = "\033[34m";
        public const MAGENTA = "\033[35m";
        public const CYAN    = "\033[36m";
        public const WHITE   = "\033[37m";

        // Bright foreground colors
        public const BRIGHT_BLACK   = "\033[90m";
        public const BRIGHT_RED     = "\033[91m";
        public const BRIGHT_GREEN   = "\033[92m";
        public const BRIGHT_YELLOW  = "\033[93m";
        public const BRIGHT_BLUE    = "\033[94m";
        public const BRIGHT_MAGENTA = "\033[95m";
        public const BRIGHT_CYAN    = "\033[96m";
        public const BRIGHT_WHITE   = "\033[97m";

        // Background colors
        public const BG_BLACK   = "\033[40m";
        public const BG_RED     = "\033[41m";
        public const BG_GREEN   = "\033[42m";
        public const BG_YELLOW  = "\033[43m";
        public const BG_BLUE    = "\033[44m";
        public const BG_MAGENTA = "\033[45m";
        public const BG_CYAN    = "\033[46m";
        public const BG_WHITE   = "\033[47m";

        // -------------------------------------------------------------------------
        // Internal state
        // -------------------------------------------------------------------------

        /** @var bool Whether ANSI output is enabled */
        private static bool $ansi = true;

        /** @var resource Input stream handle */
        private static mixed $stdin = null;

        /** @var int Current spinner frame index */
        private static int $spinnerFrame = 0;

        /** @var array Spinner frame characters */
        private static array $spinnerFrames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

        // -------------------------------------------------------------------------
        // Configuration
        // -------------------------------------------------------------------------

        /**
         * Enable or disable ANSI color/style output.
         *
         * Automatically disabled when not running in a TTY or when the
         * NO_COLOR environment variable is set.
         *
         * @param  bool  $enabled
         * @return void
         */
        public static function setAnsi(bool $enabled): void
        {
            self::$ansi = $enabled;
        }

        /**
         * Check whether ANSI output is currently enabled.
         *
         * @return bool
         */
        public static function isAnsi(): bool
        {
            return self::$ansi
                && ! isset($_SERVER['NO_COLOR'])
                && (str_contains(PHP_OS, 'WIN') ? true : function_exists('posix_isatty') && posix_isatty(STDOUT));
        }

        // -------------------------------------------------------------------------
        // Basic output
        // -------------------------------------------------------------------------

        /**
         * Write a line to STDOUT.
         *
         * @param  string  $text
         * @return void
         */
        public static function line(string $text = ''): void
        {
            fwrite(STDOUT, $text . PHP_EOL);
        }

        /**
         * Write text to STDOUT without a trailing newline.
         *
         * @param  string  $text
         * @return void
         */
        public static function write(string $text): void
        {
            fwrite(STDOUT, $text);
        }

        /**
         * Write a blank line to STDOUT.
         *
         * @param  int  $count  Number of blank lines.
         * @return void
         */
        public static function newLine(int $count = 1): void
        {
            fwrite(STDOUT, str_repeat(PHP_EOL, max(1, $count)));
        }

        /**
         * Write a line to STDERR.
         *
         * @param  string  $text
         * @return void
         */
        public static function error(string $text): void
        {
            fwrite(STDERR, self::style($text, self::RED) . PHP_EOL);
        }

        // -------------------------------------------------------------------------
        // Styled output
        // -------------------------------------------------------------------------

        /**
         * Wrap text in ANSI style codes.
         *
         * Returns the plain text when ANSI output is disabled.
         *
         * @param  string  $text
         * @param  string  ...$styles  One or more ANSI constants.
         * @return string
         */
        public static function style(string $text, string ...$styles): string
        {
            if (! self::isAnsi()) {
                return $text;
            }

            return implode('', $styles) . $text . self::RESET;
        }

        /**
         * Write a success message in green.
         *
         * @param  string  $text
         * @return void
         */
        public static function success(string $text): void
        {
            self::line(self::style('✔ ' . $text, self::GREEN));
        }

        /**
         * Write a warning message in yellow.
         *
         * @param  string  $text
         * @return void
         */
        public static function warning(string $text): void
        {
            self::line(self::style('⚠ ' . $text, self::YELLOW));
        }

        /**
         * Write an info message in cyan.
         *
         * @param  string  $text
         * @return void
         */
        public static function info(string $text): void
        {
            self::line(self::style('ℹ ' . $text, self::CYAN));
        }

        /**
         * Write a bold header line.
         *
         * @param  string  $text
         * @return void
         */
        public static function header(string $text): void
        {
            self::newLine();
            self::line(self::style($text, self::BOLD, self::BRIGHT_WHITE));
            self::line(self::style(str_repeat('─', mb_strlen($text)), self::DIM));
        }

        /**
         * Write a comment in dim text.
         *
         * @param  string  $text
         * @return void
         */
        public static function comment(string $text): void
        {
            self::line(self::style('# ' . $text, self::DIM));
        }

        // -------------------------------------------------------------------------
        // Tables
        // -------------------------------------------------------------------------

        /**
         * Render a table to STDOUT.
         *
         * @param  array  $headers  Column header labels.
         * @param  array  $rows     Array of rows — each row is an indexed or associative array.
         * @param  bool   $borders  Render outer borders (default true).
         * @return void
         */
        public static function table(array $headers, array $rows, bool $borders = true): void
        {
            // Normalise rows to indexed arrays aligned with headers
            $normalized = array_map(function (array $row) use ($headers): array {
                if (array_keys($row) !== range(0, count($row) - 1)) {
                    // Associative row — align by header key
                    return array_map(fn(string $h): string => (string) ($row[$h] ?? ''), $headers);
                }

                return array_map('strval', $row);
            }, $rows);

            // Calculate column widths from headers and all row values
            $widths = array_map('mb_strlen', $headers);

            foreach ($normalized as $row) {
                foreach ($row as $i => $cell) {
                    $widths[$i] = max($widths[$i] ?? 0, mb_strlen($cell));
                }
            }

            $separator = '─';
            $divider   = self::style(
                '+' . implode('+', array_map(fn(int $w): string => str_repeat($separator, $w + 2), $widths)) . '+',
                self::DIM
            );

            if ($borders) {
                self::line($divider);
            }

            // Header row
            $headerCells = array_map(
                fn(string $h, int $w): string => ' ' . self::style(str_pad($h, $w), self::BOLD) . ' ',
                $headers,
                $widths
            );

            self::line(self::style('|', self::DIM) . implode(self::style(
                '|',
                self::DIM
            ), $headerCells) . self::style('|', self::DIM));
            self::line($divider);

            // Data rows
            foreach ($normalized as $row) {
                $cells = array_map(
                    fn(string $cell, int $w): string => ' ' . str_pad($cell, $w) . ' ',
                    $row,
                    $widths
                );

                self::line(self::style('|', self::DIM) . implode(self::style(
                    '|',
                    self::DIM
                ), $cells) . self::style('|', self::DIM));
            }

            if ($borders) {
                self::line($divider);
            }
        }

        // -------------------------------------------------------------------------
        // Progress — determinate
        // -------------------------------------------------------------------------

        /**
         * Render a determinate progress bar.
         *
         * Call repeatedly with increasing $current values.
         * Call with $current === $total to finalize and move to a new line.
         *
         * @param  int     $current  Current progress value.
         * @param  int     $total    Total value representing 100%.
         * @param  int     $width    Width of the bar in characters (default 40).
         * @param  string  $label    Optional label displayed after the bar.
         * @return void
         */
        public static function progress(int $current, int $total, int $width = 40, string $label = ''): void
        {
            $total    = max(1, $total);
            $current  = max(0, min($current, $total));
            $percent  = (int) round(($current / $total) * 100);
            $filled   = (int) round(($current / $total) * $width);
            $empty    = $width - $filled;

            $bar = self::style(str_repeat('█', $filled), self::GREEN)
                . self::style(str_repeat('░', $empty), self::DIM);

            $suffix = $label !== '' ? ' ' . $label : '';
            $line   = sprintf("\r[%s] %3d%%%s", $bar, $percent, $suffix);

            self::write($line);

            // Move to a new line when complete
            if ($current >= $total) {
                self::newLine();
            }
        }

        // -------------------------------------------------------------------------
        // Progress — indeterminate (spinner)
        // -------------------------------------------------------------------------

        /**
         * Render one frame of an indeterminate spinner.
         *
         * Call in a loop while waiting for an operation to complete.
         * Call self::spinnerClear() when done.
         *
         * @param  string  $label  Message displayed beside the spinner.
         * @return void
         */
        public static function spinner(string $label = 'Working...'): void
        {
            $frame = self::$spinnerFrames[self::$spinnerFrame % count(self::$spinnerFrames)];
            self::$spinnerFrame++;

            self::write("\r" . self::style($frame, self::CYAN) . ' ' . $label);
        }

        /**
         * Clear the spinner line and optionally print a completion message.
         *
         * @param  string  $message  Printed after clearing the spinner (default '').
         * @return void
         */
        public static function spinnerClear(string $message = ''): void
        {
            // Overwrite the spinner line with spaces then carriage-return
            self::write("\r" . str_repeat(' ', 60) . "\r");

            if ($message !== '') {
                self::line($message);
            }

            self::$spinnerFrame = 0;
        }

        // -------------------------------------------------------------------------
        // Input
        // -------------------------------------------------------------------------

        /**
         * Prompt the user for text input.
         *
         * @param  string  $question  The prompt text.
         * @param  string  $default   Returned when the user submits an empty response.
         * @return string
         */
        public static function ask(string $question, string $default = ''): string
        {
            $suffix = $default !== '' ? self::style(' [' . $default . ']', self::DIM) : '';
            self::write(self::style($question, self::BOLD) . $suffix . ' ');

            $input = trim(fgets(self::stdin()) ?: '');

            return $input !== '' ? $input : $default;
        }

        /**
         * Prompt the user for a secret (masked) input.
         *
         * Characters are not echoed to the terminal.
         * Falls back to plain ask() on Windows where stty is unavailable.
         *
         * @param  string  $question  The prompt text.
         * @return string
         */
        public static function secret(string $question): string
        {
            self::write(self::style($question, self::BOLD) . ' ');

            if (str_contains(PHP_OS, 'WIN')) {
                // Windows: no stty — fall back to visible input
                return trim(fgets(self::stdin()) ?: '');
            }

            // Disable terminal echo, read input, restore echo
            shell_exec('stty -echo');
            $input = trim(fgets(self::stdin()) ?: '');
            shell_exec('stty echo');

            // Move to a new line since the user's Enter was suppressed
            self::newLine();

            return $input;
        }

        /**
         * Prompt the user for a yes/no confirmation.
         *
         * @param  string  $question  The prompt text.
         * @param  bool    $default   Default answer (true = yes).
         * @return bool
         */
        public static function confirm(string $question, bool $default = true): bool
        {
            $hint   = $default ? 'Y/n' : 'y/N';
            $suffix = self::style(' [' . $hint . ']', self::DIM);
            self::write(self::style($question, self::BOLD) . $suffix . ' ');

            $input = strtolower(trim(fgets(self::stdin()) ?: ''));

            if ($input === '') {
                return $default;
            }

            return in_array($input, ['y', 'yes'], true);
        }

        /**
         * Present a single-select menu and return the chosen value.
         *
         * @param  string  $question  The prompt text.
         * @param  array   $choices   Indexed or associative array of choices.
         * @param  mixed   $default   Default value when the user submits empty input.
         * @return mixed              The selected value (or key for associative arrays).
         */
        public static function select(string $question, array $choices, mixed $default = null): mixed
        {
            self::line(self::style($question, self::BOLD));

            $keys   = array_keys($choices);
            $values = array_values($choices);

            foreach ($values as $i => $label) {
                $marker = self::style((string) ($i + 1) . '.', self::CYAN);
                self::line('  ' . $marker . ' ' . $label);
            }

            // Resolve the default display value
            $defaultDisplay = '';

            if ($default !== null) {
                $defaultIdx     = array_search($default, $keys, true);
                $defaultDisplay = $defaultIdx !== false ? (string) ($defaultIdx + 1) : '';
            }

            while (true) {
                $input = self::ask('Enter number', $defaultDisplay);
                $index = (int) $input - 1;

                if (isset($keys[$index])) {
                    return is_int($keys[$index]) ? $values[$index] : $keys[$index];
                }

                self::warning('Invalid selection — please enter a number between 1 and ' . count($choices));
            }
        }

        /**
         * Present a multi-select menu and return an array of chosen values.
         *
         * The user enters comma-separated numbers to select multiple items.
         *
         * @param  string  $question   The prompt text.
         * @param  array   $choices    Indexed or associative array of choices.
         * @param  array   $defaults   Pre-selected values.
         * @return array               Selected values (or keys for associative arrays).
         */
        public static function multiSelect(string $question, array $choices, array $defaults = []): array
        {
            self::line(self::style($question, self::BOLD));
            self::line(self::style('  Enter comma-separated numbers (e.g. 1,3,4)', self::DIM));

            $keys   = array_keys($choices);
            $values = array_values($choices);

            foreach ($values as $i => $label) {
                $isDefault = in_array(is_int($keys[$i]) ? $values[$i] : $keys[$i], $defaults, true);
                $marker    = self::style((string) ($i + 1) . '.', self::CYAN);
                $check     = $isDefault ? self::style('✔', self::GREEN) . ' ' : '  ';
                self::line('  ' . $marker . ' ' . $check . $label);
            }

            while (true) {
                $input   = self::ask('Selection');
                $parts   = array_filter(array_map('trim', explode(',', $input)));
                $selected = [];
                $valid    = true;

                foreach ($parts as $part) {
                    $index = (int) $part - 1;

                    if (! isset($keys[$index])) {
                        self::warning('Invalid selection: ' . $part);
                        $valid = false;
                        break;
                    }

                    $selected[] = is_int($keys[$index]) ? $values[$index] : $keys[$index];
                }

                if ($valid) {
                    return ! empty($selected) ? $selected : $defaults;
                }
            }
        }

        // -------------------------------------------------------------------------
        // Argument parsing
        // -------------------------------------------------------------------------

        /**
         * Parse a argv-style argument list into named args, flags, and positional args.
         *
         * Named args:   --foo=bar  or  --foo bar
         * Flags:        --verbose  or  -v
         * Short flags:  -abc  (equivalent to -a -b -c)
         * Positional:   any value not preceded by -- or -
         *
         * @param  array|null  $argv  Argument list (defaults to $_SERVER['argv'] sans script name).
         * @return array{args:array,flags:array,positional:array}
         */
        public static function parseArgs(?array $argv = null): array
        {
            $argv ??= array_slice($_SERVER['argv'] ?? [], 1);

            $args       = [];
            $flags      = [];
            $positional = [];
            $count      = count($argv);

            for ($i = 0; $i < $count; $i++) {
                $token = $argv[$i];

                // Long option: --foo=bar or --foo bar
                if (str_starts_with($token, '--')) {
                    $part = substr($token, 2);

                    if (str_contains($part, '=')) {
                        [$key, $val] = explode('=', $part, 2);
                        $args[$key]  = $val;
                    } elseif (isset($argv[$i + 1]) && ! str_starts_with($argv[$i + 1], '-')) {
                        // Next token is the value
                        $args[$part] = $argv[++$i];
                    } else {
                        // Standalone --flag treated as boolean true
                        $flags[$part] = true;
                    }

                    continue;
                }

                // Short option: -v or -abc (clustered flags)
                if (str_starts_with($token, '-') && strlen($token) > 1) {
                    $chars = str_split(substr($token, 1));

                    foreach ($chars as $char) {
                        $flags[$char] = true;
                    }

                    continue;
                }

                // Positional argument
                $positional[] = $token;
            }

            return compact('args', 'flags', 'positional');
        }

        /**
         * Check whether a flag is present in a parsed argument set.
         *
         * @param  array   $parsed  Result of parseArgs().
         * @param  string  $flag    Flag name without leading dashes.
         * @return bool
         */
        public static function hasFlag(array $parsed, string $flag): bool
        {
            return ! empty($parsed['flags'][$flag]);
        }

        /**
         * Get a named argument value from a parsed argument set.
         *
         * @param  array   $parsed   Result of parseArgs().
         * @param  string  $name     Argument name without leading dashes.
         * @param  mixed   $default  Returned when the argument is absent.
         * @return mixed
         */
        public static function getArg(array $parsed, string $name, mixed $default = null): mixed
        {
            return $parsed['args'][$name] ?? $default;
        }

        /**
         * Get a positional argument by index from a parsed argument set.
         *
         * @param  array  $parsed   Result of parseArgs().
         * @param  int    $index    Zero-based position index.
         * @param  mixed  $default  Returned when the index is absent.
         * @return mixed
         */
        public static function getPositional(array $parsed, int $index, mixed $default = null): mixed
        {
            return $parsed['positional'][$index] ?? $default;
        }

        // -------------------------------------------------------------------------
        // Utilities
        // -------------------------------------------------------------------------

        /**
         * Clear the terminal screen.
         *
         * @return void
         */
        public static function clear(): void
        {
            self::write("\033[2J\033[H");
        }

        /**
         * Move the cursor up N lines.
         *
         * @param  int  $lines
         * @return void
         */
        public static function cursorUp(int $lines = 1): void
        {
            self::write("\033[" . max(1, $lines) . "A");
        }

        /**
         * Erase the current line.
         *
         * @return void
         */
        public static function eraseLine(): void
        {
            self::write("\033[2K\r");
        }

        /**
         * Get the terminal width in columns.
         *
         * Falls back to 80 when the width cannot be determined.
         *
         * @return int
         */
        public static function terminalWidth(): int
        {
            if (function_exists('shell_exec')) {
                $width = (int) shell_exec('tput cols 2>/dev/null');

                if ($width > 0) {
                    return $width;
                }
            }

            return (int) ($_SERVER['COLUMNS'] ?? 80);
        }

        /**
         * Render a horizontal rule spanning the terminal width.
         *
         * @param  string  $char  Character to repeat (default '─').
         * @return void
         */
        public static function rule(string $char = '─'): void
        {
            self::line(self::style(str_repeat($char, self::terminalWidth()), self::DIM));
        }

        /**
         * Terminate the process with an error message and a non-zero exit code.
         *
         * @param  string  $message
         * @param  int     $code     Exit code (default 1).
         * @return never
         */
        public static function abort(string $message, int $code = 1): never
        {
            self::error($message);
            exit($code);
        }

        // -------------------------------------------------------------------------
        // Private helpers
        // -------------------------------------------------------------------------

        /**
         * Return the STDIN stream handle, opening it if necessary.
         *
         * @return resource
         */
        private static function stdin(): mixed
        {
            if (self::$stdin === null) {
                self::$stdin = fopen('php://stdin', 'r');
            }

            return self::$stdin;
        }
    }
}
