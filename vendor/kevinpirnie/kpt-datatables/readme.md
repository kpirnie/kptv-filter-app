# KPT DataTables

[![GitHub Issues](https://img.shields.io/github/issues/kpirnie/kp-datatables?style=for-the-badge&logo=github&color=006400&logoColor=white&labelColor=000)](https://github.com/kpirnie/kp-datatables/issues)
[![Last Commit](https://img.shields.io/github/last-commit/kpirnie/kptv-filter-app?style=for-the-badge&labelColor=000)](https://github.com/kpirnie/kptv-filter-app/commits/main)
[![License: MIT](https://img.shields.io/badge/License-MIT-orange.svg?style=for-the-badge&logo=opensourceinitiative&logoColor=white&labelColor=000)](LICENSE)

[![PHP](https://img.shields.io/badge/Up%20To-php8.4-777BB4?logo=php&logoColor=white&style=for-the-badge&labelColor=000)](https://php.net)
[![Discord](https://img.shields.io/badge/Discord-Join-blue?logo=discord&logoColor=white&style=for-the-badge&labelColor=000)](https://discord.gg/bd4Qan3PaN)
[![Kevin Pirnie](https://img.shields.io/badge/www-KevinPirnie.com-000d2d?style=for-the-badge&labelColor=000&logoColor=white&logo=data:image/svg%2Bxml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSJ3aGl0ZSIgc3Ryb2tlLXdpZHRoPSIxLjgiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCI+CiAgPGNpcmNsZSBjeD0iMTIiIGN5PSIxMiIgcj0iMTAiLz4KICA8ZWxsaXBzZSBjeD0iMTIiIGN5PSIxMiIgcng9IjQuNSIgcnk9IjEwIi8+CiAgPGxpbmUgeDE9IjIiIHkxPSIxMiIgeDI9IjIyIiB5Mj0iMTIiLz4KICA8bGluZSB4MT0iNC41IiB5MT0iNi41IiB4Mj0iMTkuNSIgeTI9IjYuNSIvPgogIDxsaW5lIHgxPSI0LjUiIHkxPSIxNy41IiB4Mj0iMTkuNSIgeTI9IjE3LjUiLz4KPC9zdmc+Cg==)](https://kevinpirnie.com/)

Advanced PHP DataTables library with CRUD operations, search, sorting, pagination, bulk actions, JOIN support, calculated columns, footer aggregations, column filters, and multi-framework theme support (UIKit3, Bootstrap 5, Tailwind CSS, Plain).

## Features

- 🚀 **Full CRUD Operations** - Create, Read, Update, Delete with AJAX support
- 🔗 **Advanced JOIN Support** - Complex database relationships with table aliases
- 🔍 **Advanced Search** - Search all columns or specific columns with qualified column names
- 🎛️ **Column Filters** - Collapsible filter accordion with per-column filter inputs, BETWEEN date ranges, selects, and more
- 📊 **Sorting** - Multi-column sorting with visual indicators on joined tables
- 📄 **Pagination** - Configurable page sizes with first/last navigation
- ✅ **Bulk Actions** - Select multiple records for bulk operations with custom callbacks
- ✏️ **Inline Editing** - Double-click to edit fields directly in the table
- 📁 **File Uploads** - Built-in file upload handling with validation
- 🎨 **Multi-Framework Themes** - UIKit3, Bootstrap 5, Tailwind CSS, and Plain (framework-agnostic)
- 📱 **Responsive** - Mobile-friendly design
- 🎛️ **Customizable** - Extensive configuration options
- 🔧 **Chainable API** - Fluent interface for easy configuration
- 🧮 **Calculated Columns** - Computed columns with basic math operations (+, -, *, /, %) or custom SQL expressions
- 📈 **Footer Aggregations** - Sum and average calculations per page, full recordset, or both

## Requirements

- PHP 8.2 or higher
- PDO extension
- JSON extension

## Installation

Install via Composer:

```bash
composer require kevinpirnie/kpt-datatables
```

## Dependencies

This package depends on:

- [`kevinpirnie/kpt-database`](https://packagist.org/packages/kevinpirnie/kpt-database) - Database wrapper
- [`kevinpirnie/kpt-logger`](https://packagist.org/packages/kevinpirnie/kpt-logger) - Logging functionality

## Quick Start

### 1. Basic Setup

```php
<?php
require 'vendor/autoload.php';

use KPT\DataTables\DataTables;

$dbConfig = [
    'server' => 'localhost',
    'schema' => 'your_database',
    'username' => 'your_username',
    'password' => 'your_password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci'
];

$dataTable = new DataTables($dbConfig);
```

### 2. Include Required Assets

```php
echo DataTables::getCssIncludes('uikit', true, true);
echo DataTables::getJsIncludes('uikit', true, true);
```

### 3. Handle AJAX Requests

```php
if (isset($_POST['action']) || isset($_GET['action'])) {
    $dataTable->handleAjax();
}
```

### 4. Simple Table

```php
echo $dataTable
    ->theme('uikit')
    ->table('users')
    ->columns([
        'id' => 'ID',
        'name' => 'Full Name',
        'email' => 'Email Address',
        'created_at' => 'Created'
    ])
    ->sortable(['name', 'email', 'created_at'])
    ->renderDataTableComponent();
```

## Theme Support

KPT DataTables supports multiple UI frameworks through a flexible theming system:

### Available Themes

| Theme | Description | CDN Support |
|-------|-------------|-------------|
| `plain` | Framework-agnostic with `kp-dt-*` prefixed classes | No |
| `uikit` | UIKit 3 framework (default) | Yes |
| `bootstrap` | Bootstrap 5 framework | Yes |
| `tailwind` | Tailwind CSS framework | Requires compilation |

### Using Themes

```php
$dataTable->theme('bootstrap', true);
$dataTable->theme('uikit', false);
```

### Theme-Specific Assets

#### UIKit3 (Default)

```php
echo DataTables::getCssIncludes('uikit', true, true);
echo DataTables::getJsIncludes('uikit', true, true);
```

Automatically includes from CDN: UIKit CSS, UIKit JS, UIKit Icons.

#### Bootstrap 5

```php
echo DataTables::getCssIncludes('bootstrap', true, true);
echo DataTables::getJsIncludes('bootstrap', true, true);
```

Automatically includes from CDN: Bootstrap CSS, Bootstrap Icons CSS, Bootstrap Bundle JS.

#### Tailwind CSS

```php
echo DataTables::getCssIncludes('tailwind', false, true);
echo DataTables::getJsIncludes('tailwind', false, true);
```

**Tailwind Compilation Required:**

```bash
npm install
npm run build:tailwind
npm run watch:tailwind
```

#### Plain Theme

```php
echo DataTables::getCssIncludes('plain', false, true);
echo DataTables::getJsIncludes('plain', false, true);
```

Uses `kp-dt-*` prefixed classes for all elements, making it easy to integrate with any CSS framework.

## Column Filters

The filter accordion provides a collapsible panel of per-column filter inputs rendered above the table. It supports text search, selects, booleans, and date ranges.

### Basic Usage

```php
$dataTable
    ->table('orders o')
    ->join('LEFT', 'customers c', 'o.customer_id = c.id')
    ->columns([...])
    ->filter([
        'o.status'      => '=',
        'c.name'        => 'LIKE',
        'o.created_at'  => 'BETWEEN',
    ])
    ->renderDataTableComponent();
```

### Full Filter Configuration

Each filter field accepts either a shorthand operator string or a full configuration array:

```php
->filter([
    // Shorthand: field => operator
    'name' => 'LIKE',

    // Full configuration
    'status' => [
        'operator'    => '=',
        'label'       => 'Status',
        'type'        => 'select',
        'options'     => ['active' => 'Active', 'inactive' => 'Inactive'],
        'placeholder' => '',
    ],

    // Boolean field renders as Active/Inactive dropdown
    'is_active' => [
        'operator' => '=',
        'label'    => 'Active',
        'type'     => 'boolean',
    ],

    // Date range renders two side-by-side date pickers with From/To labels
    'created_at' => [
        'operator' => 'BETWEEN',
        'label'    => 'Created Between',
        'type'     => 'date',
    ],
])
```

### Supported Filter Operators

| Operator | Description | Input Rendered |
|----------|-------------|----------------|
| `=` | Exact match | Text input |
| `!=` | Not equal | Text input |
| `>` `>=` `<` `<=` | Comparisons | Text or number input |
| `LIKE` | Partial match (auto-wraps `%value%`) | Text input |
| `NOT LIKE` | Inverse partial match | Text input |
| `IN` | Comma-separated value list | Text input (hint shown) |
| `NOT IN` | Exclude comma-separated list | Text input |
| `BETWEEN` | Date or number range | Two side-by-side inputs with From/To labels |
| `REGEXP` | Regular expression match | Text input |

### Rendering the Filter Accordion

By default the filter accordion renders inside `renderDataTableComponent()` above the table. If you need control over placement — for example rendering it in a custom control panel — remove it from the default render and call it explicitly:

```php
// In your layout/control panel PHP
echo $dt->renderFilterAccordionComponent();
```

> **Note:** If you call `renderFilterAccordionComponent()` manually, remove the internal call from `renderContainer()` in `Renderer.php` to avoid rendering it twice.

### Filter Active Count Badge

When filters are applied, a badge appears on the accordion title showing the number of active filters. It resets automatically when `resetFilters()` is called (the reset button is built into the accordion header).

## Advanced Usage with JOINs

```php
$dataTable = new DataTables($dbConfig);

echo $dataTable
    ->theme('bootstrap')
    ->table('kptv_stream_other s')
    ->primaryKey('s.id')
    ->join('LEFT', 'kptv_stream_providers p', 's.p_id = p.id')
    ->columns([
        's.id' => 'ID',
        's_orig_name' => 'Original Name',
        's_stream_uri' => 'Stream URI',
        'p.sp_name' => 'Provider',
    ])
    ->columnClasses([
        's.id' => 'uk-min-width',
        's_stream_uri' => 'txt-truncate'
    ])
    ->sortable(['s_orig_name', 'p.sp_name'])
    ->filter([
        's_orig_name' => ['operator' => 'LIKE', 'label' => 'Name', 'placeholder' => 'Search by name'],
        'p.sp_name'   => ['operator' => 'LIKE', 'label' => 'Provider'],
    ])
    ->perPage(25)
    ->pageSizeOptions([25, 50, 100, 250], true)
    ->bulkActions(true)
    ->actionGroups([
        [
            'export' => [
                'icon' => 'download',
                'title' => 'Export Record',
                'class' => 'btn-export',
                'href' => '/export/{id}'
            ]
        ],
        ['delete']
    ])
    ->renderDataTableComponent();
```

## Complete Configuration Example

```php
$dataTable = new DataTables($dbConfig);

echo $dataTable
    ->theme('uikit')
    ->table('users u')
    ->primaryKey('u.user_id')
    ->join('LEFT', 'user_roles r', 'u.role_id = r.role_id')
    ->join('LEFT', 'departments d', 'u.dept_id = d.dept_id')
    ->columns([
        'u.user_id' => 'ID',
        'u.name' => 'Name',
        'u.email' => 'Email',
        'r.role_name' => 'Role',
        'd.dept_name' => 'Department',
        'u.status' => [
            'label' => 'Status',
            'type' => 'boolean'
        ]
    ])
    ->sortable(['u.name', 'u.email', 'r.role_name', 'd.dept_name'])
    ->inlineEditable(['u.name', 'u.email', 'u.status'])
    ->filter([
        'u.name'       => ['operator' => 'LIKE', 'label' => 'Name'],
        'r.role_name'  => ['operator' => 'LIKE', 'label' => 'Role'],
        'u.status'     => ['operator' => '=', 'label' => 'Status', 'type' => 'boolean'],
        'u.created_at' => ['operator' => 'BETWEEN', 'label' => 'Created Between', 'type' => 'date'],
    ])
    ->perPage(25)
    ->pageSizeOptions([10, 25, 50, 100], true)
    ->bulkActions(true, [
        'activate' => [
            'label' => 'Activate Selected',
            'icon' => 'check',
            'confirm' => 'Activate selected users?',
            'callback' => function($ids, $db, $table) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                return $db->query("UPDATE users SET status = 'active' WHERE user_id IN ({$placeholders})")
                          ->bind($ids)
                          ->execute();
            },
            'success_message' => 'Users activated successfully',
            'error_message' => 'Failed to activate users'
        ]
    ])
    ->actionGroups([
        [
            'email' => [
                'icon' => 'mail',
                'title' => 'Send Email',
                'class' => 'btn-email',
                'href' => '/email/{id}'
            ],
        ],
        ['edit', 'delete']
    ])
    ->addForm('Add New User', [
        'name' => [
            'type' => 'text',
            'label' => 'Full Name',
            'required' => true,
            'placeholder' => 'Enter full name'
        ],
        'email' => [
            'type' => 'email',
            'label' => 'Email Address',
            'required' => true,
        ],
        'role_id' => [
            'type' => 'select',
            'label' => 'Role',
            'required' => true,
            'options' => [
                '1' => 'Administrator',
                '2' => 'Editor',
                '3' => 'User'
            ]
        ],
        'status' => [
            'type' => 'boolean',
            'label' => 'Active Status',
            'value' => '1'
        ]
    ])
    ->editForm('Edit User', [
        'name' => ['type' => 'text', 'label' => 'Full Name', 'required' => true],
        'email' => ['type' => 'email', 'label' => 'Email Address', 'required' => true],
        'role_id' => [
            'type' => 'select',
            'label' => 'Role',
            'options' => ['1' => 'Administrator', '2' => 'Editor', '3' => 'User']
        ],
        'status' => ['type' => 'boolean', 'label' => 'Active Status']
    ])
    ->tableClass('uk-table uk-table-striped uk-table-hover custom-table')
    ->rowClass('custom-row')
    ->columnClasses([
        'u.name' => 'uk-text-bold',
        'u.email' => 'uk-text-primary',
        'u.status' => 'uk-text-center'
    ])
    ->fileUpload('uploads/avatars/', ['jpg', 'jpeg', 'png', 'gif'], 5242880)
    ->renderDataTableComponent();
```

## API Methods

### Core Configuration

- `theme(string $theme, bool $includeCdn = true)` - Set UI framework theme
- `table(string $tableName)` - Set the database table (supports aliases)
- `primaryKey(string $column)` - Set primary key column (supports qualified names)
- `database(array $config)` - Configure database connection
- `columns(array $columns)` - Configure table columns (supports qualified names)
- `join(string $type, string $table, string $condition)` - Add JOIN clause with alias support
- `where(array $conditions)` - Add WHERE conditions to filter records
- `filter(array $filters)` - Configure user-facing column filter accordion

### Display Options

- `sortable(array $columns)` - Set sortable columns (supports qualified names)
- `inlineEditable(array $columns)` - Set inline editable columns
- `search(bool $enabled)` - Enable/disable search
- `perPage(int $count)` - Set records per page
- `pageSizeOptions(array $options, bool $includeAll)` - Set page size options

### Actions and Forms

- `actions(string $position, bool $showEdit, bool $showDelete, array $customActions)` - Configure action buttons
- `actionGroups(array $groups)` - Configure grouped actions with separators
- `bulkActions(bool $enabled, array $actions)` - Configure bulk actions with callbacks
- `addForm(string $title, array $fields, bool $ajax)` - Configure add form
- `editForm(string $title, array $fields, bool $ajax)` - Configure edit form

### Calculations and Aggregations

- `calculatedColumn(string $alias, string $label, array $columns, string $operator)` - Add a computed column
- `calculatedColumnRaw(string $alias, string $label, string $expression)` - Add a computed column with custom SQL
- `footerAggregate(string $column, string $type, string $scope)` - Configure footer aggregation for a column
- `footerAggregateColumns(array $columns, string $type, string $scope)` - Configure footer aggregation for multiple columns

### Styling

- `tableClass(string $class)` - Set table CSS class
- `rowClass(string $class)` - Set row CSS class base
- `columnClasses(array $classes)` - Set column-specific CSS classes

### File Handling

- `fileUpload(string $path, array $extensions, int $maxSize)` - Configure file uploads

### Rendering

- `renderDataTableComponent()` - Generate complete HTML output (includes filter accordion by default)
- `handleAjax()` - Handle AJAX requests
- `renderFilterAccordionComponent()` - Render the filter accordion standalone (for custom placement)
- `renderSearchFormComponent()` - Render the search form standalone
- `renderBulkActionsComponent()` - Render the bulk actions toolbar standalone
- `renderPageSizeSelectorComponent(bool $asButtonGroup)` - Render the page size selector standalone
- `renderPaginationComponent()` - Render pagination standalone

### Static Methods

- `DataTables::getCssIncludes(string $theme, bool $includeCdn, bool $useMinified)` - Get CSS include tags
- `DataTables::getJsIncludes(string $theme, bool $includeCdn, bool $useMinified)` - Get JavaScript include tags

## Field Types

### Text Inputs

```php
'field_name' => [
    'type' => 'text', // text, email, url, tel, number, password
    'label' => 'Field Label',
    'required' => true,
    'placeholder' => 'Placeholder text',
    'class' => 'custom-css-class',
    'attributes' => ['maxlength' => '100']
]
```

### Boolean/Checkbox Fields

```php
'active' => [
    'type' => 'boolean',
    'label' => 'Active Status'
],
'newsletter' => [
    'type' => 'checkbox',
    'label' => 'Subscribe to Newsletter',
    'value' => '1'
]
```

### Select Dropdown

```php
'category' => [
    'type' => 'select',
    'label' => 'Category',
    'required' => true,
    'options' => [
        '1' => 'Category 1',
        '2' => 'Category 2',
        '3' => 'Category 3'
    ]
]
```

### Select2 - AJAX Searchable Dropdown

```php
'user_id' => [
    'type' => 'select2',
    'label' => 'User',
    'query' => 'SELECT id AS ID, u_name AS Label FROM kptv_users',
    'placeholder' => 'Select a user...',
    'required' => true,
    'min_search_chars' => 2,
    'max_results' => 50,
    'class' => 'uk-width-1-1'
]
```

**Required:** `query` must return `ID` and `Label` aliased columns.

**Query Parameter Substitution:**

```php
'city' => [
    'type' => 'select2',
    'query' => 'SELECT id AS ID, city_name AS Label FROM cities WHERE state_id = {state}',
    'placeholder' => 'Select city...'
]
```

### File Upload

```php
'document' => [
    'type' => 'file',
    'label' => 'Upload Document'
]
```

## WHERE Conditions

```php
->where([
    [
        'field' => 'status',
        'comparison' => '=',
        'value' => 'active'
    ],
    [
        'field' => 'created_at',
        'comparison' => '>=',
        'value' => '2024-01-01'
    ]
])
```

### Supported Comparison Operators

`=`, `!=`, `<>`, `>`, `<`, `>=`, `<=`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`, `REGEXP`

## Calculated Columns

```php
$dataTable
    ->table('order_items oi')
    ->columns([
        'oi.id' => 'ID',
        'oi.quantity' => 'Quantity',
        'oi.unit_price' => 'Unit Price',
    ])
    ->calculatedColumn('line_total', 'Line Total', ['oi.quantity', 'oi.unit_price'], '*')
    ->calculatedColumnRaw('profit_margin', 'Margin %', '((oi.sell_price - oi.cost_price) / oi.sell_price) * 100')
    ->renderDataTableComponent();
```

### Supported Operators

| Operator | Description    |
|----------|----------------|
| `+`      | Addition       |
| `-`      | Subtraction    |
| `*`      | Multiplication |
| `/`      | Division       |
| `%`      | Modulus        |

## Footer Aggregations

```php
$dataTable
    ->table('orders')
    ->columns([...])
    ->footerAggregate('amount', 'sum', 'both')
    ->footerAggregate('tax', 'avg', 'all')
    ->footerAggregateColumns(['amount', 'tax', 'shipping'], 'both', 'both')
    ->renderDataTableComponent();
```

### Configuration Options

**Type:** `sum`, `avg`, `both`

**Scope:** `page`, `all`, `both`

## Browser Support

- Chrome 60+
- Firefox 60+
- Safari 12+
- Edge 79+

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Testing

```bash
composer test
composer test-coverage
composer phpstan
composer cs-check
```

## Building Assets

```bash
npm install
npm run build
npm run build:js
npm run build:css
npm run watch:tailwind
```

## Security

If you discover any security-related issues, please email <security@kpirnie.com> instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- [Kevin Pirnie](https://github.com/kpirnie)
- [UIKit3](https://getuikit.com/) for the default UI framework
- [Bootstrap](https://getbootstrap.com/) for Bootstrap theme support
- [Tailwind CSS](https://tailwindcss.com/) for Tailwind theme support
- All contributors

## Support

- **Issues**: [GitHub Issues](https://github.com/kpirnie/kpt-datatables/issues)

## Roadmap

- [ ] Export functionality (CSV, Excel, PDF)
- [ ] REST API endpoints
- [x] Multi-framework theme support
- [x] Calculated columns and footer aggregations
- [x] Column filter accordion with BETWEEN date range support

---

**Made with ❤️ by [Kevin Pirnie](https://kevinpirnie.com)**
