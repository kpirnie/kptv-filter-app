# KPT Utils

[![PHP](https://img.shields.io/badge/Up%20To-php8.4-777BB4?logo=php&logoColor=white&style=for-the-badge&labelColor=000)](https://php.net)
[![GitHub Issues](https://img.shields.io/github/issues/kpirnie/kp-php-utils?style=for-the-badge&logo=github&color=006400&logoColor=white&labelColor=000)](https://github.com/kpirnie/kp-php-utils/issues)
[![Last Commit](https://img.shields.io/github/last-commit/kpirnie/kp-php-utils?style=for-the-badge&labelColor=000)](https://github.com/kpirnie/kp-php-utils/commits/main)
[![License: MIT](https://img.shields.io/badge/License-MIT-orange.svg?style=for-the-badge&logo=opensourceinitiative&logoColor=white&labelColor=000)](LICENSE)
[![Kevin Pirnie](https://img.shields.io/badge/-KevinPirnie.com-000d2d?style=for-the-badge&labelColor=000&logoColor=white&logo=data:image/svg%2Bxml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSJ3aGl0ZSIgc3Ryb2tlLXdpZHRoPSIxLjgiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCI+CiAgPGNpcmNsZSBjeD0iMTIiIGN5PSIxMiIgcj0iMTAiLz4KICA8ZWxsaXBzZSBjeD0iMTIiIGN5PSIxMiIgcng9IjQuNSIgcnk9IjEwIi8+CiAgPGxpbmUgeDE9IjIiIHkxPSIxMiIgeDI9IjIyIiB5Mj0iMTIiLz4KICA8bGluZSB4MT0iNC41IiB5MT0iNi41IiB4Mj0iMTkuNSIgeTI9IjYuNSIvPgogIDxsaW5lIHgxPSI0LjUiIHkxPSIxNy41IiB4Mj0iMTkuNSIgeTI9IjE3LjUiLz4KPC9zdmc+Cg==)](https://kevinpirnie.com/)

A modern PHP 8.2+ utility library.

## Requirements

- PHP >= 8.2
- Extensions: none required (optional: `redis`, `memcached`, `apcu` for cache-adjacent tooling)

## Installation

```bash
composer require kevinpirnie/kpt-utils
```

## Usage

```php
use KPT\Sanitize;
use KPT\Validate;

// Sanitize
$email = Sanitize::email($_POST['email']);
$age   = Sanitize::int($_POST['age'], 0, 120);

// Validate
if (Validate::email($email) && Validate::between($age, 18, 120)) {
    // ...
}

// Map sanitization
$clean = Sanitize::map($_POST, [
    'name'  => [[Sanitize::class, 'string'], []],
    'email' => [Sanitize::class, 'email'],
    'age'   => [[Sanitize::class, 'int'], [0, 120]],
]);

// Map validation
$errors = Validate::map($clean, [
    'name'  => fn($v) => Validate::minLength($v, 2),
    'email' => fn($v) => Validate::email($v),
    'age'   => fn($v) => Validate::between($v, 18, 120),
]);
```

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
