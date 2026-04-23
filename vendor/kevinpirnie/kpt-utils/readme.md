# KPT Utils

[![PHP](https://img.shields.io/badge/Up%20To-php8.4-777BB4?logo=php&logoColor=white&style=for-the-badge&labelColor=000)](https://php.net)
[![GitHub Issues](https://img.shields.io/github/issues/kpirnie/kp-php-utils?style=for-the-badge&logo=github&color=006400&logoColor=white&labelColor=000)](https://github.com/kpirnie/kp-php-utils/issues)
[![Last Commit](https://img.shields.io/github/last-commit/kpirnie/kp-php-utils?style=for-the-badge&labelColor=000)](https://github.com/kpirnie/kp-php-utils/commits/main)
[![License: MIT](https://img.shields.io/badge/License-MIT-orange.svg?style=for-the-badge&logo=opensourceinitiative&logoColor=white&labelColor=000)](LICENSE)
[![Kevin Pirnie](https://img.shields.io/badge/-KevinPirnie.com-000d2d?style=for-the-badge&labelColor=000&logoColor=white&logo=data:image/svg%2Bxml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSJ3aGl0ZSIgc3Ryb2tlLXdpZHRoPSIxLjgiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCI+CiAgPGNpcmNsZSBjeD0iMTIiIGN5PSIxMiIgcj0iMTAiLz4KICA8ZWxsaXBzZSBjeD0iMTIiIGN5PSIxMiIgcng9IjQuNSIgcnk9IjEwIi8+CiAgPGxpbmUgeDE9IjIiIHkxPSIxMiIgeDI9IjIyIiB5Mj0iMTIiLz4KICA8bGluZSB4MT0iNC41IiB5MT0iNi41IiB4Mj0iMTkuNSIgeTI9IjYuNSIvPgogIDxsaW5lIHgxPSI0LjUiIHkxPSIxNy41IiB4Mj0iMTkuNSIgeTI9IjE3LjUiLz4KPC9zdmc+Cg==)](https://kevinpirnie.com/)

A modern PHP 8.2+ utility library.

## Requirements

- PHP >= 8.2
- ext-curl
- ext-intl
- ext-openssl
- ext-sodium

## Installation

```bash
composer require kevinpirnie/kpt-utils
```

---

## Classes

---

### `KPT\Sanitize`

Transforms and cleans input values. All methods are static.

| Method | Description |
|--------|-------------|
| `string()` | Sanitize a plain-text string — strips tags, encodes special characters, trims whitespace |
| `textarea()` | Sanitize a textarea value with newlines preserved |
| `html()` | Strip disallowed tags then HTML-encode the result |
| `int()` | Sanitize an integer with optional min/max bounds |
| `float()` | Sanitize a float with configurable decimal separator |
| `bool()` | Sanitize a boolean — accepts true/false, 1/0, yes/no, on/off |
| `email()` | Sanitize and validate an email address — returns lowercased address or empty string |
| `url()` | Sanitize and validate a URL — returns validated URL or empty string |
| `ip()` | Sanitize and validate an IP address (v4 or v6) |
| `domain()` | Sanitize a domain name — strips invalid characters |
| `phone()` | Sanitize a phone number — optionally return digits only |
| `macAddress()` | Sanitize and normalize a MAC address to lowercase colon-delimited format |
| `uuid()` | Sanitize and validate a UUID (v1–v5) |
| `hexColor()` | Sanitize a hex color — returns with leading # or empty string |
| `base64()` | Sanitize a base64-encoded string — supports URL-safe alphabet |
| `slug()` | Sanitize a URL slug — lowercases, strips invalid characters, normalizes hyphens |
| `username()` | Sanitize a username with optional min/max length bounds |
| `filename()` | Sanitize a filename — prevents directory traversal, collapses dots |
| `path()` | Sanitize a filesystem path — resolves real path, optional base directory restriction |
| `json()` | Sanitize and decode a JSON string — returns decoded value or null |
| `xml()` | Sanitize an XML string — strips illegal characters, validates structure |
| `date()` | Sanitize a date string to a normalized format |
| `svg()` | Sanitize an SVG string — removes dangerous elements, event attributes, and javascript: URIs |
| `printable()` | Strip non-printable and control characters |
| `alphanumeric()` | Sanitize to alphanumeric characters only — optionally preserve spaces |
| `whitelist()` | Ensure a value exists within an allowed list — returns default when not found |
| `truncate()` | Sanitize and enforce a maximum character length |
| `array()` | Sanitize each element in an array with a given callable |
| `map()` | Sanitize a key=value map applying per-key sanitizer rules |
| `input()` | Pull and sanitize a scalar from a superglobal via filter_input |
| `inputArray()` | Pull and sanitize multiple keys from a superglobal |
| `attr()` | Encode a value for safe HTML attribute output |
| `js()` | Encode a value for safe inline JavaScript string output |
| `css()` | Sanitize a value for safe use as a CSS property value |
| `clamp()` | Clamp a numeric value between a min and max |
| `port()` | Sanitize and validate a port number (0–65535) |

---

### `KPT\Validate`

Returns `bool` for all validation checks unless otherwise noted. All methods are static.

| Method | Description |
|--------|-------------|
| `required()` | Validate that a value is not empty — treats 0 and '0' as non-empty |
| `isString()` | Validate that a value is a string |
| `isInt()` | Validate that a value is an integer |
| `isFloat()` | Validate that a value is a float |
| `isNumeric()` | Validate that a value is numeric (int or float) |
| `isBool()` | Validate that a value is boolean or boolean-like |
| `isArray()` | Validate that a value is an array |
| `isNull()` | Validate that a value is null |
| `isScalar()` | Validate that a value is a scalar |
| `minLength()` | Validate string meets a minimum length |
| `maxLength()` | Validate string does not exceed a maximum length |
| `lengthBetween()` | Validate string length is between min and max |
| `exactLength()` | Validate string is exactly a given length |
| `contains()` | Validate string contains a given substring |
| `startsWith()` | Validate string starts with a given substring |
| `endsWith()` | Validate string ends with a given substring |
| `matches()` | Validate string matches a regular expression |
| `alpha()` | Validate string contains only alphabetic characters |
| `alphanumeric()` | Validate string contains only alphanumeric characters |
| `noWhitespace()` | Validate string contains no whitespace |
| `passwordStrength()` | Validate password meets minimum strength requirements |
| `slug()` | Validate string is a valid slug |
| `username()` | Validate string is a valid username |
| `name()` | Validate a person's name — allows Unicode letters, hyphens, apostrophes, spaces |
| `confirmed()` | Validate two values match — useful for password confirmation |
| `semver()` | Validate string is a valid semantic version |
| `email()` | Validate an email address |
| `url()` | Validate a URL |
| `urlReachable()` | Validate a URL responds with HTTP 200 — performs a network request |
| `ip()` | Validate an IP address |
| `domain()` | Validate a domain name — optionally performs a DNS lookup |
| `port()` | Validate a port number (0–65535) |
| `macAddress()` | Validate a MAC address |
| `uuid()` | Validate a UUID (v1–v5) |
| `phone()` | Validate a phone number against ITU-T E.164 digit count bounds |
| `hexColor()` | Validate a hex color code |
| `rgbColor()` | Validate an RGB or RGBA color string |
| `hslColor()` | Validate an HSL or HSLA color string |
| `color()` | Validate any supported color format (hex, rgb, rgba, hsl, hsla) |
| `base64()` | Validate a base64-encoded string |
| `latitude()` | Validate a GPS latitude value (-90 to 90) |
| `longitude()` | Validate a GPS longitude value (-180 to 180) |
| `coordinates()` | Validate a latitude/longitude coordinate pair |
| `countryCode()` | Validate an ISO 3166-1 alpha-2 country code |
| `languageCode()` | Validate an IETF BCP 47 language code |
| `timezone()` | Validate a timezone identifier against PHP's known list |
| `zipCode()` | Validate a US ZIP code |
| `postalCode()` | Validate a postal code for a given country |
| `isbn()` | Validate an ISBN-10 or ISBN-13 |
| `creditCard()` | Validate a credit card number via the Luhn algorithm |
| `date()` | Validate a date string against a given format |
| `time()` | Validate a time string against a given format |
| `datetime()` | Validate a datetime string against a given format |
| `dateBefore()` | Validate a date falls before a given date |
| `dateAfter()` | Validate a date falls after a given date |
| `dateBetween()` | Validate a date falls within a given range |
| `minAge()` | Validate a date of birth represents a minimum age |
| `maxAge()` | Validate a date of birth does not exceed a maximum age |
| `min()` | Validate a number is greater than or equal to a minimum |
| `max()` | Validate a number is less than or equal to a maximum |
| `between()` | Validate a number falls within a range |
| `positive()` | Validate a number is positive (> 0) |
| `negative()` | Validate a number is negative (< 0) |
| `nonNegative()` | Validate a number is zero or positive (>= 0) |
| `decimalPlaces()` | Validate a float has no more than a given number of decimal places |
| `divisibleBy()` | Validate a number is divisible by a given divisor |
| `fileExists()` | Validate a file exists and is readable |
| `dirExists()` | Validate a directory exists and is readable |
| `fileExtension()` | Validate a file has an allowed extension |
| `fileSize()` | Validate a file does not exceed a maximum size |
| `fileMime()` | Validate a file's MIME type is in an allowed list |
| `arrayNotEmpty()` | Validate an array is not empty |
| `arrayMinCount()` | Validate an array contains at least N elements |
| `arrayMaxCount()` | Validate an array contains no more than N elements |
| `arrayHasKey()` | Validate an array contains a specific key |
| `arrayHasKeys()` | Validate an array contains all specified keys |
| `arrayAll()` | Validate every element in an array passes a callable |
| `arrayAny()` | Validate at least one element in an array passes a callable |
| `inArray()` | Validate a value exists within an array |
| `notInArray()` | Validate a value does not exist within an array |
| `equals()` | Validate strict equality |
| `notEquals()` | Validate strict inequality |
| `instanceOf()` | Validate a value is an instance of a given class |
| `requiredIf()` | Validate a value is non-empty when a condition is true |
| `requiredUnless()` | Validate a value is non-empty when a condition is false |
| `json()` | Validate a JSON string |
| `xml()` | Validate an XML string |
| `svg()` | Validate an SVG string |
| `map()` | Validate a key=value map — returns array of failed field names |
| `passes()` | Validate a map — returns true when all rules pass |

---

### `KPT\Cast`

Explicit type casting with safety. Unlike `Sanitize`, no cleaning or validation is performed — values are purely converted between types. When `$nullable` is false (default), failed casts return a typed zero-value. When `$nullable` is true, failed casts return null. All methods are static.

| Method | Description |
|--------|-------------|
| `toInt()` | Cast a value to int — non-numeric values return 0 or null |
| `toFloat()` | Cast a value to float — non-numeric values return 0.0 or null |
| `toBool()` | Cast a value to bool — recognises true/false, 1/0, yes/no, on/off — unrecognised strings return false or null |
| `toString()` | Cast a value to string — supports __toString() objects, wraps booleans as 'true'/'false' — arrays and non-stringable objects return '' or null |
| `toArray()` | Cast a value to array — passes arrays through, decodes JSON strings, casts objects via (array), wraps scalars in a single-element array |
| `toCollection()` | Cast a value to a KPT\Collection — converts to array first via toArray() |
| `toUnsignedInt()` | Cast a value to a non-negative int — negative values are clamped to 0 |
| `toPositiveInt()` | Cast a value to a positive int — zero and negative values return 1 or null |
| `intOrNull()` | Cast to int or return null on failure — convenience wrapper around toInt($value, true) |
| `floatOrNull()` | Cast to float or return null on failure — convenience wrapper around toFloat($value, true) |
| `boolOrNull()` | Cast to bool or return null on failure — convenience wrapper around toBool($value, true) |
| `stringOrNull()` | Cast to string or return null on failure — convenience wrapper around toString($value, true) |
| `arrayOrNull()` | Cast to array or return null on failure — convenience wrapper around toArray($value, true) |

---

### `KPT\Crypto`

Authenticated encryption, hashing, and secure random generation. Requires `ext-openssl` and `ext-sodium`. All methods are static.

| Method | Description |
|--------|-------------|
| `encrypt()` | Encrypt a string using AES-256-GCM with HKDF key derivation — for machine-generated keys |
| `decrypt()` | Decrypt a payload produced by `encrypt()` |
| `encryptWithPassphrase()` | Encrypt using Argon2id key stretching then AES-256-GCM — for human-provided passphrases |
| `decryptWithPassphrase()` | Decrypt a payload produced by `encryptWithPassphrase()` |
| `hash()` | Hash a value using a given algorithm |
| `hmac()` | Generate an HMAC for a value |
| `timingSafeEquals()` | Timing-safe string comparison — prevents timing side-channel attacks |
| `generateKey()` | Generate a cryptographically secure hex-encoded key |
| `generateToken()` | Generate a URL-safe cryptographically secure token |
| `generatePassword()` | Generate a cryptographically secure password from a full symbol set |
| `generateRandString()` | Generate a cryptographically secure alphanumeric string |

---

### `KPT\Str`

String inspection, search, transformation, and masking utilities. All methods are static.

| Method | Description |
|--------|-------------|
| `strContainsAny()` | Check whether a string contains any of the given substrings — case-insensitive |
| `strContainsAnyRegex()` | Check whether a string matches any of the given regex patterns |
| `containsWord()` | Check whether a string contains a given whole word — respects punctuation boundaries |
| `isEmpty()` | Check whether a value is empty, null, or the literal string 'null' |
| `isBlank()` | Strictly check whether a value is blank — preserves 0, '0', and false as non-blank |
| `truncate()` | Truncate a string to a maximum number of characters |
| `excerpt()` | Truncate a string to a maximum length, breaking at a word boundary |
| `toTitleCase()` | Convert a string to Title Case |
| `toCamelCase()` | Convert a string to camelCase |
| `toStudlyCase()` | Convert a string to StudlyCase (PascalCase) |
| `toSnakeCase()` | Convert a string to snake_case — configurable delimiter |
| `toKebabCase()` | Convert a string to kebab-case |
| `mask()` | Mask part of a string for safe display — configurable start, end, and mask character |
| `random()` | Generate a random string of a given length from a configurable alphabet — not cryptographically secure |
| `slug()` | Convert a string to a URL-friendly slug — delegates to Sanitize::slug() |
| `between()` | Extract the string between two substrings |
| `wrap()` | Wrap a string with a prefix and optional suffix |
| `wordCount()` | Count the words in a string, respecting Unicode characters |
| `padLeft()` | Pad a string on the left to a given length — multibyte-safe |
| `padRight()` | Pad a string on the right to a given length — multibyte-safe |
| `padBoth()` | Pad a string on both sides to a given length — multibyte-safe |

---

### `KPT\Arr`

Array search, sorting, conversion, and dot-notation utilities. All methods are static.

| Method | Description |
|--------|-------------|
| `findInArray()` | Check whether any element in an array contains a substring — case-insensitive partial match |
| `arrayKeyContainsSubset()` | Find the first element whose key contains a given substring |
| `sortMultiDim()` | Sort a multi-dimensional array in place by a shared subkey |
| `objectToArray()` | Recursively convert an object and any nested objects to an array |
| `flatten()` | Flatten a multi-dimensional array to a single level or a given depth |
| `pluck()` | Pluck a column of values from a multi-dimensional array — optionally keyed by another column |
| `groupBy()` | Group a multi-dimensional array by a field or callback result |
| `only()` | Return only the elements whose keys are in a given list |
| `except()` | Return all elements except those whose keys are in a given list |
| `dotNotationFlatten()` | Flatten a nested array into dot-notation keys |
| `dotNotationExpand()` | Expand a flat dot-notation array into a nested array |
| `isAssoc()` | Check whether an array is associative |
| `first()` | Get the first element — optionally matching a callback |
| `last()` | Get the last element — optionally matching a callback |
| `wrap()` | Ensure a value is an array — wraps scalars, passes arrays through, returns empty array for null |
| `zip()` | Zip one or more arrays together by index |
| `chunk()` | Split an array into chunks of a given size — preserves keys within each chunk |
| `shuffle()` | Return a shuffled copy of an array without modifying the original |

---

### `KPT\Num`

Number formatting and conversion utilities. Requires `ext-intl` for currency and percent formatting. All methods are static.

| Method | Description |
|--------|-------------|
| `ordinal()` | Format an integer as an ordinal number string (1st, 2nd, 3rd) — handles 11th/12th/13th correctly |
| `formatBytes()` | Format a byte count as a human-readable string — scales from bytes to petabytes |
| `formatCurrency()` | Format a number as a localized currency string via ICU |
| `formatPercent()` | Format a number as a localized percentage string via ICU |
| `toRoman()` | Convert an integer (1–3999) to a Roman numeral string |
| `fromRoman()` | Convert a Roman numeral string to an integer |
| `clamp()` | Clamp a numeric value between a minimum and maximum |

---

### `KPT\DateTime`

Date, time, and human-readable duration utilities. All methods are static.

#### Constants

| Constant | Value |
|----------|-------|
| `MINUTE_IN_SECONDS` | 60 |
| `HOUR_IN_SECONDS` | 3,600 |
| `DAY_IN_SECONDS` | 86,400 |
| `WEEK_IN_SECONDS` | 604,800 |
| `MONTH_IN_SECONDS` | 2,592,000 |
| `YEAR_IN_SECONDS` | 31,536,000 |

#### Methods

| Method | Description |
|--------|-------------|
| `now()` | Get the current datetime in a given format (default 'Y-m-d H:i:s') |
| `timeAgo()` | Return a human-readable "time ago" string — singular/plural labels from seconds to months |
| `humanDiff()` | Return a human-readable difference between two datetime strings |
| `isWeekend()` | Check whether a datetime falls on a weekend |
| `isWeekday()` | Check whether a datetime falls on a weekday |
| `startOfDay()` | Get the start of the day (midnight) for a given datetime |
| `endOfDay()` | Get the end of the day (23:59:59) for a given datetime |
| `addDays()` | Add a number of days to a datetime string |
| `subDays()` | Subtract a number of days from a datetime string |
| `addMonths()` | Add a number of months to a datetime string |
| `subMonths()` | Subtract a number of months from a datetime string |
| `addYears()` | Add a number of years to a datetime string |
| `subYears()` | Subtract a number of years from a datetime string |
| `addHours()` | Add a number of hours to a datetime string |
| `subHours()` | Subtract a number of hours from a datetime string |
| `addMinutes()` | Add a number of minutes to a datetime string |
| `subMinutes()` | Subtract a number of minutes from a datetime string |
| `isBetween()` | Check whether a datetime falls between two other datetimes — inclusive |
| `toDateTimeImmutable()` | Convert a datetime string to a DateTimeImmutable instance — returns null on failure |

---

### `KPT\Http`

HTTP request inspection and network utilities. All methods are static.

| Method | Description |
|--------|-------------|
| `tryRedirect()` | Attempt an HTTP redirect with a JavaScript fallback when headers have already been sent |
| `getUserUri()` | Get the current request URI, sanitized via `Sanitize::url()` |
| `getUserIp()` | Get the client's public IP — checks proxy headers, validates against private/reserved ranges |
| `getUserAgent()` | Get the client's User-Agent string, sanitized via `Sanitize::string()` |
| `getUserReferer()` | Get the HTTP referer for the current request, sanitized via `Sanitize::url()` |
| `cidrMatch()` | Check whether an IP address (v4 or v6) falls within a CIDR range |
| `isAjax()` | Check whether the current request was made via XMLHttpRequest |
| `isHttps()` | Check whether the current request is served over HTTPS |
| `method()` | Get the current HTTP request method as an uppercase string |
| `isMethod()` | Check whether the current request uses a given HTTP method |
| `isBot()` | Check whether the current request appears to be from a bot or crawler — checks User-Agent against known signatures |

---

### `KPT\Session`

Full-featured session lifecycle and data management. All methods are static. Supports dot-notation keys for nested access throughout.

| Method | Description |
|--------|-------------|
| `start()` | Start the session — registers a shutdown function to prevent lock contention |
| `close()` | Write and close the session without destroying it — releases the session lock |
| `destroy()` | Destroy the session entirely — clears data and expires the client cookie |
| `regenerate()` | Regenerate the session ID — call after privilege changes to prevent session fixation |
| `isActive()` | Check whether the session is currently active |
| `getId()` | Get the current session ID |
| `setId()` | Set the session ID before the session is started |
| `set()` | Set a session value — supports dot-notation for nested keys |
| `get()` | Get a session value — supports dot-notation, returns default when absent |
| `has()` | Check whether a session key exists and is not null |
| `remove()` | Remove a session key — supports dot-notation |
| `clear()` | Clear all session data |
| `all()` | Retrieve all session data as an array |
| `flash()` | Store a flash value that survives exactly one subsequent retrieval |
| `getFlash()` | Retrieve a flash value and remove it from the session |
| `hasFlash()` | Check whether a flash value exists |
| `allFlash()` | Retrieve all pending flash values and clear them |

---

### `KPT\Curl`

HTTP client modelled after WordPress's HTTP API. All requests return a consistent response array. All methods are static.

#### Response array shape

```php
[
    'headers'  => ['header-name' => 'value'],
    'body'     => '...',
    'response' => ['code' => 200, 'message' => 'OK'],
    'cookies'  => ['name' => 'value'],
    'error'    => '',
]
```

#### Options array shape

```php
[
    'method'      => 'GET',
    'timeout'     => 5,
    'redirection' => 5,
    'headers'     => ['X-Custom' => 'value'],
    'body'        => 'string or array (form-encoded)',
    'cookies'     => ['name' => 'value'],
    'sslverify'   => true,
    'user-agent'  => 'KPT-Utils/1.0',
    'auth'        => ['user', 'pass', 'basic|digest|bearer'],
    'decompress'  => true,
]
```

#### Methods

| Method | Description |
|--------|-------------|
| `request()` | Perform an HTTP request with full options control |
| `get()` | Perform a GET request |
| `post()` | Perform a POST request |
| `put()` | Perform a PUT request |
| `patch()` | Perform a PATCH request |
| `delete()` | Perform a DELETE request |
| `head()` | Perform a HEAD request |
| `safeGet()` | GET request — rejects private and loopback addresses |
| `safePost()` | POST request — rejects private and loopback addresses |
| `safePut()` | PUT request — rejects private and loopback addresses |
| `safePatch()` | PATCH request — rejects private and loopback addresses |
| `safeDelete()` | DELETE request — rejects private and loopback addresses |
| `withBaseUrl()` | Return a callable that prepends a base URL to all requests |
| `multiGet()` | Perform multiple GET requests concurrently |
| `multiPost()` | Perform multiple POST requests concurrently |
| `multiRequest()` | Perform multiple mixed-method requests concurrently with optional concurrency limit |
| `retrieveBody()` | Get the response body |
| `retrieveHeaders()` | Get all response headers |
| `retrieveHeader()` | Get a single response header by name — case-insensitive |
| `retrieveResponseCode()` | Get the HTTP response code |
| `retrieveResponseMessage()` | Get the HTTP response message |
| `retrieveCookies()` | Get all response cookies |
| `isError()` | Check whether the response represents an error |
| `getError()` | Get the error message from a failed response |

---

### `KPT\Collection`

An immutable fluent array wrapper implementing `Countable`, `IteratorAggregate`, and `ArrayAccess`. All transformation methods return a new `Collection` instance — the original is never modified. Instantiate via `Collection::make()`.

| Method | Description |
|--------|-------------|
| `make()` | Create a new Collection from an array — the only instantiation method |
| `filter()` | Filter items through a callback — removes falsy values when no callback is provided |
| `map()` | Apply a callback to every item |
| `reduce()` | Reduce the collection to a single value |
| `chunk()` | Chunk into smaller Collections of a given size — returns a Collection of Collections |
| `groupBy()` | Group items into Collections keyed by a field or callback result |
| `pluck()` | Pluck values for a given field — optionally keyed by another field |
| `sort()` | Sort with an optional comparison callback |
| `sortBy()` | Sort by a field value — supports numeric and string comparison |
| `reverse()` | Reverse the order of items |
| `unique()` | Return only unique items — optionally de-duplicated by field |
| `flatten()` | Flatten nested arrays to a single level or a given depth |
| `merge()` | Merge additional items or another Collection |
| `push()` | Append one or more values |
| `prepend()` | Prepend a value with an optional key |
| `take()` | Take the first N items |
| `skip()` | Skip the first N items |
| `values()` | Reset keys to sequential integers |
| `keys()` | Get the keys as a new Collection |
| `only()` | Return only items whose keys are in a given list |
| `except()` | Return all items except those whose keys are in a given list |
| `where()` | Filter items where a field equals a given value |
| `zip()` | Zip the collection with one or more arrays |
| `first()` | Get the first item or the first item matching a callback |
| `last()` | Get the last item or the last item matching a callback |
| `sum()` | Sum the values or the values of a given field |
| `avg()` | Average the values or the values of a given field |
| `min()` | Get the minimum value or the minimum value of a given field |
| `max()` | Get the maximum value or the maximum value of a given field |
| `contains()` | Check whether the collection contains a value or a matching item |
| `each()` | Iterate for side effects — returning false from the callback stops iteration |
| `isEmpty()` | Check whether the collection is empty |
| `isNotEmpty()` | Check whether the collection is not empty |
| `toArray()` | Convert to a plain array — recursively unwraps nested Collections |
| `toJson()` | Convert to a JSON string |
| `count()` | Return the number of items (`Countable`) |
| `getIterator()` | Return an iterator for use in foreach loops (`IteratorAggregate`) |
| `flatMap()` | Map over items and flatten the result by one level — lazy |
| `mapWithKeys()` | Map over items re-keying the result via the callback — lazy |
| `tap()` | Pass each item through a callback for side effects without interrupting the pipeline — lazy |
| `pipe()` | Pass the entire Collection into a callable and return the result — terminal |

---

### `KPT\Env`

`.env` file parser with variable interpolation and typed accessors. Supports `${VAR}` and `$VAR` interpolation syntax, single/double-quoted values, inline comments, and the `export` prefix. All methods are static.

| Method | Description |
|--------|-------------|
| `load()` | Load and parse a .env file — supports override and putenv flags |
| `get()` | Get a raw value — falls back to `getenv()` when the key is not in loaded data |
| `getString()` | Get a value cast to string |
| `getInt()` | Get a value cast to int |
| `getFloat()` | Get a value cast to float |
| `getBool()` | Get a value cast to bool — truthy strings: true, 1, yes, on |
| `getArray()` | Get a delimited value as an array — configurable delimiter |
| `has()` | Check whether a key is present — also checks the system environment |
| `required()` | Get a value that must be present — throws `RuntimeException` when absent |
| `set()` | Set a value at runtime — does not persist to the .env file |
| `all()` | Return all loaded variables as an associative array |
| `flush()` | Clear all loaded variables — useful for testing or reloading |

---

### `KPT\Token`

CSRF token generation/verification and signed URL utilities. Backed by `KPT\Session` for storage and `KPT\Crypto` for generation and signing. All methods are static.

| Method | Description |
|--------|-------------|
| `generate()` | Generate and store a CSRF token with a configurable expiry |
| `verify()` | Verify a CSRF token using timing-safe comparison — single-use by default |
| `has()` | Check whether a stored token exists and has not expired |
| `invalidate()` | Invalidate a stored token without verifying it |
| `field()` | Generate an HTML hidden input field containing a fresh CSRF token |
| `signUrl()` | Generate a signed URL with an embedded expiry timestamp |
| `verifySignedUrl()` | Verify a signed URL — checks expiry then validates the HMAC signature |

---

### `KPT\Paginator`

A flexible pagination utility that works with arrays, iterators, and `KPT\Collection` instances. Provides numeric page data, URL generation via string template or callable, and a structured page window for rendering pagination UI. Instantiate via `Paginator::make()` or `Paginator::fromTotal()`.

| Method | Description |
|--------|-------------|
| `make()` | Create a Paginator from a full dataset — slices to the current page automatically |
| `fromTotal()` | Create a Paginator from pre-sliced items and a known total — for database-driven pagination |
| `items()` | Get the items for the current page as a plain array |
| `collection()` | Get the items for the current page as a `KPT\Collection` |
| `total()` | Get the total number of items across all pages |
| `perPage()` | Get the number of items per page |
| `currentPage()` | Get the current page number |
| `totalPages()` | Get the total number of pages |
| `firstItem()` | Get the 1-based index of the first item on the current page |
| `lastItem()` | Get the 1-based index of the last item on the current page |
| `count()` | Get the number of items on the current page |
| `hasPages()` | Check whether there are multiple pages |
| `hasPreviousPage()` | Check whether there is a previous page |
| `hasNextPage()` | Check whether there is a next page |
| `onFirstPage()` | Check whether the current page is the first page |
| `onLastPage()` | Check whether the current page is the last page |
| `url()` | Generate the URL for a given page number |
| `previousPageUrl()` | Generate the URL for the previous page |
| `nextPageUrl()` | Generate the URL for the next page |
| `firstPageUrl()` | Generate the URL for the first page |
| `lastPageUrl()` | Generate the URL for the last page |
| `pageWindow()` | Return a window of page numbers around the current page with `'...'` gap markers |
| `links()` | Return the page window as structured link data — each entry includes page, url, active, and gap |
| `toArray()` | Return the full paginator state as an associative array — useful for API responses |
| `toJson()` | Return the full paginator state as a JSON string |

---

### `KPT\Cli`

A CLI utility providing ANSI output, styled text, tables, progress indicators, interactive prompts, and argument parsing. All methods are static. ANSI output is automatically disabled when not running in a TTY or when the `NO_COLOR` environment variable is set.

#### Output

| Method | Description |
|--------|-------------|
| `line()` | Write a line to STDOUT |
| `write()` | Write text to STDOUT without a trailing newline |
| `newLine()` | Write one or more blank lines |
| `error()` | Write a line to STDERR in red |
| `style()` | Wrap text in ANSI style codes — returns plain text when ANSI is disabled |
| `success()` | Write a success message in green with a ✔ prefix |
| `warning()` | Write a warning message in yellow with a ⚠ prefix |
| `info()` | Write an info message in cyan with an ℹ prefix |
| `header()` | Write a bold header line with an underline rule |
| `comment()` | Write a comment in dim text with a # prefix |
| `table()` | Render a formatted table with column alignment and optional borders |
| `rule()` | Render a horizontal rule spanning the terminal width |
| `clear()` | Clear the terminal screen |
| `cursorUp()` | Move the cursor up N lines |
| `eraseLine()` | Erase the current line |
| `abort()` | Write an error message and terminate with a non-zero exit code |

#### Progress

| Method | Description |
|--------|-------------|
| `progress()` | Render a determinate progress bar — call repeatedly with increasing values |
| `spinner()` | Render one frame of an indeterminate spinner — call in a loop |
| `spinnerClear()` | Clear the spinner line and optionally print a completion message |

#### Input

| Method | Description |
|--------|-------------|
| `ask()` | Prompt for text input with an optional default |
| `secret()` | Prompt for masked input — characters are not echoed to the terminal |
| `confirm()` | Prompt for a yes/no confirmation with an optional default |
| `select()` | Present a single-select menu and return the chosen value |
| `multiSelect()` | Present a multi-select menu and return an array of chosen values |

#### Argument parsing

| Method | Description |
|--------|-------------|
| `parseArgs()` | Parse argv into named args, flags, and positional args |
| `hasFlag()` | Check whether a flag is present in a parsed argument set |
| `getArg()` | Get a named argument value from a parsed argument set |
| `getPositional()` | Get a positional argument by index from a parsed argument set |

#### Configuration

| Method | Description |
|--------|-------------|
| `setAnsi()` | Enable or disable ANSI color/style output |
| `isAnsi()` | Check whether ANSI output is currently enabled |
| `terminalWidth()` | Get the terminal width in columns — falls back to 80 |

---

### `KPT\Uuid`

UUID generation (v1, v3, v4, v5, v7) and validation (v1–v7) with standard, compact, and binary output formats. All methods are static.

#### Format constants

| Constant | Description |
|----------|-------------|
| `FORMAT_STANDARD` | Standard hyphenated format: `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx` |
| `FORMAT_COMPACT` | Compact format without hyphens: `xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx` |
| `FORMAT_BINARY` | Raw 16-byte binary string |

#### Namespace constants (RFC 4122)

| Constant | Description |
|----------|-------------|
| `NS_DNS` | DNS namespace UUID |
| `NS_URL` | URL namespace UUID |
| `NS_OID` | OID namespace UUID |
| `NS_X500` | X.500 namespace UUID |

#### Methods

| Method | Description |
|--------|-------------|
| `v1()` | Generate a version 1 UUID — time-based with randomized node |
| `v3()` | Generate a version 3 UUID — name-based, MD5 hashed |
| `v4()` | Generate a version 4 UUID — cryptographically random |
| `v5()` | Generate a version 5 UUID — name-based, SHA-1 hashed |
| `v7()` | Generate a version 7 UUID — millisecond time-ordered, ideal for database keys |
| `isValid()` | Validate a UUID string (v1–v7) — case-insensitive |
| `isVersion()` | Validate a UUID of a specific version |
| `isCompact()` | Validate a compact UUID (32 hex characters, no hyphens) |
| `toCompact()` | Convert a standard UUID to compact format |
| `toBinary()` | Convert a standard UUID to raw binary (16 bytes) |
| `fromCompact()` | Convert a compact UUID to standard hyphenated format |
| `fromBinary()` | Convert raw binary (16 bytes) to standard hyphenated format |
| `version()` | Extract the version number from a UUID string |
| `timestamp()` | Extract the Unix timestamp in milliseconds from a v7 UUID |

---

### `KPT\Pipeline`

A simple immutable pipeline for passing a value through a series of callables. Supports both array-based and fluent chained stage addition, exception handling, and both returning and side-effect terminal operations. Instantiate via `Pipeline::send()`.

| Method | Description |
|--------|-------------|
| `send()` | Begin a new pipeline with a given value |
| `through()` | Set the pipeline stages from an array of callables — replaces any existing stages |
| `pipe()` | Add a single stage to the pipeline — chainable |
| `catch()` | Register an exception handler — receives the exception and current value, return value becomes the result |
| `thenReturn()` | Execute the pipeline and return the final value |
| `then()` | Execute the pipeline and pass the result to a final callable |
| `thenDo()` | Execute the pipeline for side effects only — return value is discarded |

---

### `KPT\Retry`

A retry utility with exponential backoff, jitter, exception class filtering, and a per-attempt callback. Supports both fluent configuration and a static convenience method for simple cases. Instantiate via `Retry::operation()` or use `Retry::attempt()` for quick one-liners.

| Method | Description |
|--------|-------------|
| `operation()` | Begin a new Retry for the given callable |
| `times()` | Set the maximum number of attempts including the first try |
| `waitMs()` | Set the base delay between attempts in milliseconds |
| `exponential()` | Enable exponential backoff with a configurable multiplier and delay cap |
| `withJitter()` | Enable or disable random jitter on the delay — prevents thundering herd |
| `retryOn()` | Retry only when the exception matches one of the given classes |
| `dontRetryOn()` | Never retry when the exception matches one of the given classes — takes precedence over retryOn() |
| `onRetry()` | Register a callback called after each failed attempt — receives exception, attempt number, and next delay |
| `run()` | Execute the operation retrying on failure — throws the last exception when all attempts are exhausted |
| `runOrDefault()` | Execute the operation and return a default value when all attempts fail — never throws |
| `runOrNull()` | Execute the operation and return null when all attempts fail — convenience wrapper around runOrDefault() |
| `attempt()` | Static convenience method — retry a callable with default or specified settings |

---

### `KPT\Stopwatch`

A simple execution timer with millisecond precision and memory tracking. Supports start/stop/lap/reset lifecycle, human-readable output, and a static convenience method for measuring callables. Instantiate via `Stopwatch::start()` or `new Stopwatch()`.

| Method | Description |
|--------|-------------|
| `start()` | Create and immediately start a new Stopwatch — static factory |
| `begin()` | Start or restart the stopwatch — clears existing laps and stop time |
| `stop()` | Stop the stopwatch |
| `lap()` | Record a lap time with an optional label — elapsed is measured from start, not previous lap |
| `reset()` | Reset the stopwatch to its initial state |
| `elapsed()` | Get elapsed time in milliseconds — returns time to stop() if stopped, or time since start() if running |
| `elapsedSeconds()` | Get elapsed time in seconds |
| `elapsedHuman()` | Get elapsed time as a human-readable string — scales from ms through minutes |
| `laps()` | Get all recorded laps — each entry contains label, time, elapsed, and memory |
| `fastestLap()` | Get the fastest lap by elapsed time — returns null when no laps recorded |
| `slowestLap()` | Get the slowest lap by elapsed time — returns null when no laps recorded |
| `memoryUsage()` | Get current memory usage in bytes |
| `peakMemoryUsage()` | Get peak memory usage in bytes |
| `memoryDelta()` | Get memory consumed since the stopwatch was started in bytes |
| `memoryHuman()` | Get memory usage as a human-readable string via Num::formatBytes() |
| `measure()` | Static — measure the execution time of a callable — returns result, elapsed ms, and memory delta |
| `isRunning()` | Check whether the stopwatch is currently running |
| `hasStarted()` | Check whether the stopwatch has been started |

---

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Author

**Kevin Pirnie** - [iam@kevinpirnie.com](mailto:iam@kevinpirnie.com)

## Support

- [Issues](https://github.com/kpirnie/kp-php-utils/issues)
- [PayPal](https://www.paypal.biz/kevinpirnie)
- [Ko-fi](https://ko-fi.com/kevinpirnie)