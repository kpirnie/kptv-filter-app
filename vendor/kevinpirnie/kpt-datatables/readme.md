# KPT DataTables

[![GitHub Issues](https://img.shields.io/github/issues/kpirnie/kp-datatables?style=for-the-badge&logo=github&color=006400&logoColor=white&labelColor=000)](https://github.com/kpirnie/kp-datatables/issues)
[![Last Commit](https://img.shields.io/github/last-commit/kpirnie/kptv-filter-app?style=for-the-badge&labelColor=000)](https://github.com/kpirnie/kptv-filter-app/commits/main)
[![License: MIT](https://img.shields.io/badge/License-MIT-orange.svg?style=for-the-badge&logo=opensourceinitiative&logoColor=white&labelColor=000)](LICENSE)

[![PHP](https://img.shields.io/badge/Up%20To-php8.4-777BB4?logo=php&logoColor=white&style=for-the-badge&labelColor=000)](https://php.net)
[![Discord](https://img.shields.io/badge/Discord-Join-blue?logo=discord&logoColor=white&style=for-the-badge&labelColor=000)](https://discord.gg/bd4Qan3PaN)
[![Kevin Pirnie](https://img.shields.io/badge/-KevinPirnie.com-000d2d?style=for-the-badge&labelColor=000&logoColor=white&logo=data:image/svg%2Bxml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSJ3aGl0ZSIgc3Ryb2tlLXdpZHRoPSIxLjgiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCI+CiAgPGNpcmNsZSBjeD0iMTIiIGN5PSIxMiIgcj0iMTAiLz4KICA8ZWxsaXBzZSBjeD0iMTIiIGN5PSIxMiIgcng9IjQuNSIgcnk9IjEwIi8+CiAgPGxpbmUgeDE9IjIiIHkxPSIxMiIgeDI9IjIyIiB5Mj0iMTIiLz4KICA8bGluZSB4MT0iNC41IiB5MT0iNi41IiB4Mj0iMTkuNSIgeTI9IjYuNSIvPgogIDxsaW5lIHgxPSI0LjUiIHkxPSIxNy41IiB4Mj0iMTkuNSIgeTI9IjE3LjUiLz4KPC9zdmc+Cg==)](https://kevinpirnie.com/)

Advanced PHP DataTables library with full CRUD operations, multi-table JOIN support, per-column filter accordion, calculated columns, footer aggregations, inline editing, Select2 AJAX dropdowns, file uploads, bulk actions, tabbed modal forms, and a theme system covering UIKit3, Bootstrap 5, Tailwind CSS, and a framework-agnostic Plain theme. All rendering is server-side PHP with client-side interactivity handled by a zero-dependency vanilla JS class.

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Dependencies](#dependencies)
- [Quick Start](#quick-start)
- [Asset Inclusion](#asset-inclusion)
- [AJAX Handling](#ajax-handling)
- [Themes](#themes)
- [Core Configuration Methods](#core-configuration-methods)
  - [table()](#table)
  - [primaryKey()](#primarykey)
  - [database()](#database)
  - [columns()](#columns)
  - [join()](#join)
  - [where()](#where)
  - [filter()](#filter)
  - [sortable()](#sortable)
  - [inlineEditable()](#inlineeditable)
  - [perPage()](#perpage)
  - [pageSizeOptions()](#pagesizeoptions)
  - [search()](#search)
  - [defaultSort()](#defaultsort)
  - [groupBy()](#groupby)
- [Action Configuration](#action-configuration)
  - [actions()](#actions)
  - [actionGroups()](#actiongroups)
- [Bulk Actions](#bulk-actions)
- [Modal Forms](#modal-forms)
  - [addForm()](#addform)
  - [editForm()](#editform)
  - [Field Types](#field-types)
  - [Tabbed Forms](#tabbed-forms)
  - [allow_on Field Overrides](#allow_on-field-overrides)
- [Calculated Columns](#calculated-columns)
- [Footer Aggregations](#footer-aggregations)
- [Styling](#styling)
  - [tableClass()](#tableclass)
  - [rowClass()](#rowclass)
  - [columnClasses()](#columnclasses)
- [File Uploads](#file-uploads)
- [Rendering](#rendering)
- [Static Methods](#static-methods)
- [Standalone Component Renderers](#standalone-component-renderers)
- [Filter Accordion Reference](#filter-accordion-reference)
- [WHERE Conditions Reference](#where-conditions-reference)
- [Column Definition Reference](#column-definition-reference)
- [JavaScript API](#javascript-api)
- [Building Assets](#building-assets)
- [Testing](#testing)
- [Security](#security)
- [Contributing](#contributing)
- [License](#license)

---

## Requirements

- PHP 8.2 or higher
- PDO extension
- JSON extension
- MySQL / MariaDB database

---

## Installation

```bash
composer require kevinpirnie/kpt-datatables
```

---

## Dependencies

| Package | Purpose |
|---|---|
| [`kevinpirnie/kpt-database`](https://packagist.org/packages/kevinpirnie/kpt-database) | PDO database wrapper with fluent query builder |
| [`kevinpirnie/kpt-logger`](https://packagist.org/packages/kevinpirnie/kpt-logger) | Internal debug/error logging |

---

## Quick Start

```php
<?php
require 'vendor/autoload.php';

use KPT\DataTables;

// Database connection configuration
$dbConfig = [
    'server'    => 'localhost',
    'schema'    => 'my_database',
    'username'  => 'db_user',
    'password'  => 'db_pass',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];

$dt = new DataTables($dbConfig);

// Handle all AJAX requests before any HTML output
if (isset($_POST['action']) || isset($_GET['action'])) {
    $dt->table('users')
       ->handleAjax();
}

// Render CSS and JS assets in your <head> / before </body>
echo DataTables::getCssIncludes('uikit', true, true);
echo DataTables::getJsIncludes('uikit', true, true);

// Render the table
echo $dt
    ->theme('uikit')
    ->table('users')
    ->columns([
        'id'         => 'ID',
        'name'       => 'Full Name',
        'email'      => 'Email',
        'created_at' => 'Created',
    ])
    ->sortable(['name', 'email', 'created_at'])
    ->renderDataTableComponent();
```

---

## Asset Inclusion

Assets must be included before the rendered table HTML. The static helper methods handle framework CDN links, theme-specific CSS, and all library JS in one call.

```php
// In <head>
echo DataTables::getCssIncludes(string $theme, bool $includeCdn, bool $useMinified);

// Before </body>
echo DataTables::getJsIncludes(string $theme, bool $includeCdn, bool $useMinified);
```

| Parameter | Type | Description |
|---|---|---|
| `$theme` | string | `'uikit'`, `'bootstrap'`, `'tailwind'`, `'plain'` |
| `$includeCdn` | bool | Include framework assets from CDN |
| `$useMinified` | bool | Use minified CSS/JS versions |

**UIKit example:**

```php
echo DataTables::getCssIncludes('uikit', true, true);
// Outputs: UIKit CDN CSS + /vendor/.../uikit.min.css

echo DataTables::getJsIncludes('uikit', true, true);
// Outputs: UIKit CDN JS + UIKit Icons JS + kpt-datatables.min.js
```

**Bootstrap example:**

```php
echo DataTables::getCssIncludes('bootstrap', true, true);
// Outputs: Bootstrap CDN CSS + Bootstrap Icons CDN CSS + /vendor/.../bootstrap.min.css

echo DataTables::getJsIncludes('bootstrap', true, true);
// Outputs: Bootstrap Bundle CDN JS + kpt-datatables.min.js
```

**Tailwind / Plain (no CDN):**

```php
echo DataTables::getCssIncludes('tailwind', false, true);
echo DataTables::getJsIncludes('tailwind', false, true);
```

> Tailwind CSS must be compiled separately. See [Building Assets](#building-assets).

---

## AJAX Handling

All CRUD operations, search, pagination, sorting, filtering, and bulk actions are handled server-side through a single AJAX endpoint — the same URL that renders the page. The AJAX handler must be invoked before any HTML output.

```php
$dt = new DataTables($dbConfig);

// Call handleAjax() with the same chain you use for rendering
if (isset($_POST['action']) || isset($_GET['action'])) {
    $dt->theme('bootstrap')
       ->table('orders o')
       ->primaryKey('o.id')
       ->join('LEFT', 'customers c', 'o.customer_id = c.id')
       ->columns([...])
       ->addForm('Add Order', [...])
       ->editForm('Edit Order', [...])
       ->handleAjax();
}

// Then render below
echo $dt->renderDataTableComponent();
```

The `handleAjax()` method internally routes the `action` parameter to the appropriate handler and outputs JSON before calling `exit`. Supported actions dispatched automatically by the JS layer:

| Action | Trigger |
|---|---|
| `fetch_data` | Page load, sort, search, filter, pagination |
| `fetch_record` | Edit button click (loads record into modal) |
| `add_record` | Add form submit |
| `edit_record` | Edit form submit |
| `delete_record` | Delete confirmation |
| `bulk_action` | Bulk action execute button |
| `inline_edit` | Inline field save |
| `upload_file` | Standalone file upload during inline image edit |
| `fetch_aggregations` | After each data load when footer aggregations are configured |
| `fetch_select2_options` | Select2 dropdown search |
| `action_callback` | Custom row action with PHP callback |

---

## Themes

The theme is set via the fluent `theme()` method. It configures all CSS class mappings used throughout rendering and controls which CDN assets are included.

```php
$dt->theme(string $theme, bool $includeCdn = true)
```

| Theme | Description |
|---|---|
| `'uikit'` | UIKit 3 (default) |
| `'bootstrap'` | Bootstrap 5 |
| `'tailwind'` | Tailwind CSS (requires compilation) |
| `'plain'` | Framework-agnostic, `kp-dt-*` classes only |

```php
// UIKit with CDN
$dt->theme('uikit');

// Bootstrap without CDN (you load Bootstrap yourself)
$dt->theme('bootstrap', false);

// Tailwind (CDN not applicable)
$dt->theme('tailwind', false);

// Plain
$dt->theme('plain');
```

---

## Core Configuration Methods

All methods return `$this` for fluent chaining unless otherwise noted.

### table()

Sets the primary database table. Supports table aliases. Auto-loads the table schema from the database for field type detection and form generation.

```php
->table(string $tableName)
```

```php
// Simple
->table('users')

// With alias (required when using JOIN)
->table('users u')
->table('kptv_stream_other s')
```

When an alias is used, the **base table name** (without alias) is automatically tracked separately for `INSERT`, `UPDATE`, and `DELETE` operations.

---

### primaryKey()

Overrides the auto-detected primary key. Supports qualified (aliased) names.

```php
->primaryKey(string $column)
```

```php
->primaryKey('id')
->primaryKey('u.user_id')
->primaryKey('s.id')
```

The library automatically strips the table prefix when building `WHERE id = ?` clauses for mutations.

---

### database()

Configures or replaces the database connection after construction.

```php
->database(array $config)
```

```php
->database([
    'server'    => 'localhost',
    'schema'    => 'my_db',
    'username'  => 'root',
    'password'  => 'secret',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
])
```

---

### columns()

Defines which columns to display. Keys are database column names (supports dot notation for joined tables and `expression AS alias` syntax). Values are display labels or full configuration arrays.

```php
->columns(array $columns)
```

**Simple format:**

```php
->columns([
    'id'         => 'ID',
    'u.name'     => 'Full Name',
    'u.email'    => 'Email',
    'r.role_name' => 'Role',
])
```

**Enhanced format** (type overrides, options, form classes):

```php
->columns([
    'id'       => 'ID',
    'u.name'   => 'Full Name',
    'u.email'  => ['label' => 'Email Address', 'type' => 'email'],
    'status'   => [
        'label'   => 'Status',
        'type'    => 'boolean',
    ],
    'category' => [
        'label'   => 'Category',
        'type'    => 'select',
        'options' => ['1' => 'News', '2' => 'Blog', '3' => 'Event'],
    ],
    'user_id'  => [
        'label'           => 'Assigned User',
        'type'            => 'select2',
        'query'           => 'SELECT id AS ID, u_name AS Label FROM users',
        'placeholder'     => 'Search users...',
        'min_search_chars' => 2,
        'max_results'     => 50,
    ],
    'created_at' => [
        'label'     => 'Created',
        'type'      => 'datepicker',
        'formatter' => 'MM/DD/YYYY',
    ],
])
```

See [Column Definition Reference](#column-definition-reference) for all supported type overrides.

---

### join()

Adds a SQL JOIN clause. Multiple joins are supported and applied to all data, count, and aggregation queries.

```php
->join(string $type, string $table, string $condition)
```

| Parameter | Description |
|---|---|
| `$type` | `'LEFT'`, `'RIGHT'`, `'INNER'`, `'FULL OUTER'` |
| `$table` | Table name, optionally with alias (e.g., `'users u'`) |
| `$condition` | Raw ON condition (e.g., `'o.user_id = u.id'`) |

```php
->table('orders o')
->join('LEFT', 'customers c', 'o.customer_id = c.id')
->join('LEFT', 'products p', 'o.product_id = p.id')
->join('INNER', 'order_status s', 'o.status_id = s.id')
```

---

### where()

Adds static server-side WHERE conditions that always apply to data queries, count queries, and mutations. These are invisible to the user.

```php
->where(array $conditions)
```

Each condition requires `field`, `comparison`, and `value` keys.

```php
->where([
    ['field' => 'status',     'comparison' => '=',  'value' => 'active'],
    ['field' => 'deleted_at', 'comparison' => '=',  'value' => null],
    ['field' => 'created_at', 'comparison' => '>=', 'value' => '2024-01-01'],
])
```

**Supported comparison operators:** `=`, `!=`, `<>`, `>`, `<`, `>=`, `<=`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`, `REGEXP`

For `IN` / `NOT IN` pass an array as the value:

```php
->where([
    ['field' => 'role_id', 'comparison' => 'IN', 'value' => [1, 2, 3]],
])
```

WHERE conditions applied through `where()` are also appended to `UPDATE` and `DELETE` queries for security, ensuring mutations cannot affect records outside the configured scope.

---

### filter()

Configures the user-facing collapsible filter accordion rendered above the table. Each key is a database column (dot notation supported). Values can be a shorthand operator string or a full configuration array.

```php
->filter(array $filters)
```

**Shorthand:**

```php
->filter([
    'name'       => 'LIKE',
    'status'     => '=',
    'created_at' => 'BETWEEN',
])
```

**Full configuration:**

```php
->filter([
    'o.status' => [
        'operator'    => '=',
        'label'       => 'Order Status',
        'type'        => 'select',
        'options'     => ['pending' => 'Pending', 'shipped' => 'Shipped', 'delivered' => 'Delivered'],
        'placeholder' => '',
    ],
    'c.name' => [
        'operator'    => 'LIKE',
        'label'       => 'Customer Name',
        'placeholder' => 'Search by name...',
    ],
    'is_active' => [
        'operator' => '=',
        'label'    => 'Active',
        'type'     => 'boolean',
    ],
    'created_at' => [
        'operator' => 'BETWEEN',
        'label'    => 'Date Range',
        'type'     => 'date',
    ],
])
```

See [Filter Accordion Reference](#filter-accordion-reference) for all supported operators and input types.

---

### sortable()

Defines which columns produce clickable sort headers. Pass column names exactly as they appear in `columns()` keys, or use alias names for aliased expressions.

```php
->sortable(array $columns)
```

```php
->sortable(['name', 'email', 'created_at'])

// With joined/aliased columns
->sortable(['u.name', 'u.email', 'r.role_name', 'd.dept_name'])
```

Clicking a sortable header cycles `ASC → DESC → ASC`. Sort icons update accordingly.

---

### inlineEditable()

Marks specific columns as double-click-to-edit. The appropriate inline editor (text input, select, boolean toggle, image uploader, etc.) is determined from the column's type in the schema or `columns()` override.

```php
->inlineEditable(array $columns)
```

```php
->inlineEditable(['name', 'email', 'status'])

// Qualified names
->inlineEditable(['u.name', 'u.email', 'u.status'])
```

Supported inline edit types: `text`, `email`, `number`, `date`, `datetime-local`, `textarea`, `select`, `select2`, `boolean`, `image`, `datepicker`.

---

### perPage()

Sets the initial (default) number of records displayed per page.

```php
->perPage(int $count)
```

```php
->perPage(25)   // default
->perPage(50)
->perPage(100)
```

---

### pageSizeOptions()

Configures the options available in the per-page selector. When `$includeAll` is `true`, an "All records" option (value `0`) is appended.

```php
->pageSizeOptions(array $options, bool $includeAll = true)
```

```php
->pageSizeOptions([10, 25, 50, 100], true)
->pageSizeOptions([25, 50, 100, 250], false)
```

The per-page selector renders as a `<select>` dropdown by default, or as a button group when `renderPageSizeSelectorComponent(true)` is called.

---

### search()

Enables or disables the global search input. Enabled by default.

```php
->search(bool $enabled = true)
```

```php
->search(true)   // default
->search(false)  // hide search completely
```

---

### defaultSort()

Sets the column and direction used for initial data load. Without this, data loads in database natural order.

```php
->defaultSort(string $column, string $direction = 'ASC')
```

```php
->defaultSort('created_at', 'DESC')
->defaultSort('u.name', 'ASC')
```

---

### groupBy()

Adds a `GROUP BY` clause to data and count queries. When a group-by is active, the count query wraps the grouped result in a subquery to return the true number of groups. Aggregation queries also wrap accordingly.

```php
->groupBy(string $column)
```

```php
->groupBy('user_id')
->groupBy('o.status')
```

---

## Action Configuration

### actions()

Configures the built-in action column (edit / delete buttons) with placement and visibility.

```php
->actions(string $position = 'end', bool $showEdit = true, bool $showDelete = true, array $customActions = [])
```

```php
->actions('end', true, true)    // Both buttons at end (default)
->actions('start', true, false) // Edit only at start
->actions('end', false, true)   // Delete only at end
```

---

### actionGroups()

Replaces the default edit/delete buttons with a flexible group system that supports built-in actions, custom link actions, PHP callback actions, and raw HTML injection — all with configurable ordering and separators.

```php
->actionGroups(array $groups)
```

Each element of `$groups` is either:

- An **array of built-in action keys** (`'edit'`, `'delete'`)
- An **associative array** of custom action configurations

**Built-in actions:**

```php
->actionGroups([
    ['edit', 'delete'],
])
```

**Custom link action:**

```php
->actionGroups([
    [
        'view' => [
            'icon'  => 'search',
            'title' => 'View Record',
            'class' => 'btn-view',
            'href'  => '/records/{id}',
        ],
    ],
    ['edit', 'delete'],
])
```

**Placeholder substitution** — `{id}` and `{column_name}` placeholders in `href`, `title`, `onclick`, and `attributes` values are replaced with the row's actual data:

```php
->actionGroups([
    [
        'export' => [
            'icon'       => 'download',
            'title'      => 'Export {name}',
            'href'       => '/export/{id}?ref={order_ref}',
            'class'      => 'btn-export',
            'attributes' => [
                'data-id'  => '{id}',
                'data-ref' => '{order_ref}',
            ],
        ],
    ],
    ['edit', 'delete'],
])
```

**PHP callback action** (server-side execution):

```php
->actionGroups([
    [
        'approve' => [
            'icon'            => 'check',
            'title'           => 'Approve',
            'class'           => 'btn-approve',
            'confirm'         => 'Approve this record?',
            'success_message' => 'Record approved',
            'error_message'   => 'Approval failed',
            'callback'        => function($rowId, $rowData, $db, $table) {
                return $db->query("UPDATE `{$table}` SET status = 'approved' WHERE id = ?")
                          ->bind([$rowId])
                          ->execute();
            },
        ],
    ],
    ['edit', 'delete'],
])
```

**HTML injection** — inject arbitrary HTML before or after any action or group:

```php
->actionGroups([
    [
        'html1' => [
            'location' => 'before',
            'content'  => '<span class="divider">|</span>',
        ],
        'view' => ['icon' => 'search', 'href' => '/view/{id}'],
    ],
    ['edit', 'delete'],
])
```

---

## Bulk Actions

Enables a checkbox column and a toolbar for performing operations on multiple selected records simultaneously.

```php
->bulkActions(bool $enabled = true, array $actions = [])
```

With default delete-only:

```php
->bulkActions(true)
```

With custom actions:

```php
->bulkActions(true, [
    'activate' => [
        'label'           => 'Activate Selected',
        'icon'            => 'check',
        'confirm'         => 'Activate selected records?',
        'success_message' => 'Records activated',
        'error_message'   => 'Activation failed',
        'callback'        => function($ids, $db, $table) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            return $db->query("UPDATE `{$table}` SET active = 1 WHERE id IN ({$placeholders})")
                      ->bind($ids)
                      ->execute();
        },
    ],
    'archive' => [
        'label'    => 'Archive Selected',
        'icon'     => 'folder',
        'confirm'  => 'Archive selected records?',
        'callback' => function($ids, $db, $table) {
            // ...
        },
    ],
])
```

The callback signature is `function(array $ids, Database $db, string $baseTableName): bool|int`.

Clicking a row (outside the action/check cell) toggles its checkbox. A "select all" checkbox in the header selects the entire current page.

---

## Modal Forms

### addForm()

Configures the "Add Record" modal and its form fields.

```php
->addForm(string $title, array $fields, bool $ajax = true, string $class = '')
```

```php
->addForm('Add New User', [
    'name' => [
        'type'        => 'text',
        'label'       => 'Full Name',
        'required'    => true,
        'placeholder' => 'Enter full name',
    ],
    'email' => [
        'type'     => 'email',
        'label'    => 'Email Address',
        'required' => true,
    ],
    'role_id' => [
        'type'    => 'select',
        'label'   => 'Role',
        'options' => ['1' => 'Admin', '2' => 'Editor', '3' => 'User'],
    ],
    'status' => [
        'type'  => 'boolean',
        'label' => 'Active',
        'value' => '1',
    ],
])
```

---

### editForm()

Configures the "Edit Record" modal. The primary key field is automatically injected as a hidden input and populated by the JS `fetch_record` AJAX call.

```php
->editForm(string $title, array $fields, bool $ajax = true, string $class = '')
```

```php
->editForm('Edit User', [
    'name'    => ['type' => 'text',    'label' => 'Full Name', 'required' => true],
    'email'   => ['type' => 'email',   'label' => 'Email',     'required' => true],
    'role_id' => [
        'type'    => 'select',
        'label'   => 'Role',
        'options' => ['1' => 'Admin', '2' => 'Editor', '3' => 'User'],
    ],
    'status'  => ['type' => 'boolean', 'label' => 'Active'],
])
```

---

### Field Types

All field configurations share common keys:

| Key | Type | Description |
|---|---|---|
| `type` | string | Field type (see table below) |
| `label` | string | Display label |
| `required` | bool | Add required attribute and asterisk |
| `placeholder` | string | Placeholder text |
| `value` | mixed | Default value |
| `default` | mixed | Alias for value |
| `disabled` | bool | Disable the field |
| `class` | string | Extra CSS class on the wrapper |
| `attributes` | array | Additional HTML attributes |
| `options` | array | `value => label` pairs for select/radio |

**Supported types:**

| Type | Renders As | Notes |
|---|---|---|
| `text` | `<input type="text">` | Default fallback |
| `email` | `<input type="email">` | |
| `number` | `<input type="number">` | |
| `url` | `<input type="url">` | |
| `tel` | `<input type="tel">` | |
| `password` | `<input type="password">` | |
| `hidden` | `<input type="hidden">` | No label or wrapper rendered |
| `textarea` | `<textarea>` | |
| `boolean` | `<select>` with Active/Inactive | Stores `1` / `0` |
| `checkbox` | `<input type="checkbox">` | Value `1` when checked |
| `radio` | Radio button group | Requires `options` |
| `select` | `<select>` dropdown | Requires `options` |
| `select2` | AJAX searchable dropdown | Requires `query` |
| `file` | `<input type="file">` | |
| `image` | URL input + file upload + preview | |
| `datepicker` | Styled date picker with format support | |
| `static` | Read-only `<p>` element | Not submitted; renders `content` key or DB value |

**select2 field extra keys:**

| Key | Type | Default | Description |
|---|---|---|---|
| `query` | string | — | SQL query returning `ID` and `Label` columns |
| `placeholder` | string | `'Select...'` | Dropdown placeholder |
| `min_search_chars` | int | `0` | Minimum characters before search fires |
| `max_results` | int | `50` | Maximum results returned |

```php
'user_id' => [
    'type'             => 'select2',
    'label'            => 'Assigned User',
    'query'            => 'SELECT id AS ID, CONCAT(first, " ", last) AS Label FROM users WHERE active = 1',
    'placeholder'      => 'Search users...',
    'min_search_chars' => 2,
    'max_results'      => 25,
    'required'         => true,
]
```

**Query parameter substitution** — use `{field_name}` placeholders in `select2` queries to filter options based on other fields in the same record:

```php
'city_id' => [
    'type'  => 'select2',
    'query' => 'SELECT id AS ID, city_name AS Label FROM cities WHERE state_id = {state_id}',
]
```

**datepicker field extra keys:**

| Key | Type | Default | Description |
|---|---|---|---|
| `formatter` | string | `'YYYY-MM-DD'` | Display format using `YYYY`, `MM`, `DD` tokens |

```php
'birth_date' => [
    'type'      => 'datepicker',
    'label'     => 'Date of Birth',
    'formatter' => 'MM/DD/YYYY',
]
```

Values are stored internally as `YYYY-MM-DD` regardless of display format.

**static field extra keys:**

| Key | Type | Description |
|---|---|---|
| `content` | string | Hardcoded text to display; omit to auto-populate from the DB record |

```php
'created_by' => [
    'type'    => 'static',
    'label'   => 'Created By',
    'content' => '', // empty = populated from DB on edit
]
```

---

### Tabbed Forms

Any form field can be assigned to a named tab by adding a `tab` key. Fields without a `tab` key are grouped under a "General" tab that is prepended automatically.

```php
->addForm('Add User', [
    'name'  => ['type' => 'text', 'label' => 'Name'],
    'email' => ['type' => 'email', 'label' => 'Email'],

    'bio'   => ['type' => 'textarea', 'label' => 'Bio',   'tab' => 'Profile'],
    'photo' => ['type' => 'image',    'label' => 'Photo', 'tab' => 'Profile'],

    'role_id'    => ['type' => 'select',  'label' => 'Role',    'tab' => 'Permissions', 'options' => [...]],
    'department' => ['type' => 'select',  'label' => 'Dept',    'tab' => 'Permissions', 'options' => [...]],
])
```

Tab navigation is rendered using the active theme's tab component (UIKit `uk-tab`, Bootstrap `nav-tabs`, or the custom `kp-dt-tabs` plain/tailwind component).

---

### allow_on Field Overrides

Fields in the edit form can be conditionally modified when a specific condition on the fetched record is met. The server evaluates the condition during `fetch_record` and sends field overrides back to the client.

```php
'field_name' => [
    'type'     => 'text',
    'label'    => 'Some Field',
    'allow_on' => [
        'field'    => 'status',      // Field from the fetched record to evaluate
        'operator' => '==',          // Comparison operator
        'value'    => 'approved',    // Value to compare against
        'action'   => [
            'set_value'      => 'auto-filled',               // Force value
            'set_attributes' => ['readonly' => 'readonly'],  // Add/remove attributes
            'set_classes'    => ['uk-text-muted'],           // Add CSS classes
        ],
    ],
]
```

**Supported operators for allow_on:** `==`, `!=`, `>`, `>=`, `<`, `<=`, `IN`, `NOT IN`

To remove an attribute set `null` or `false` as its value in `set_attributes`.

---

## Calculated Columns

Computed columns are added to the SELECT as SQL expressions and rendered in the table like any other column. They cannot be edited inline.

### calculatedColumn()

Builds the expression from an array of column names joined by an operator.

```php
->calculatedColumn(string $alias, string $label, array $columns, string $operator = '+')
```

| Operator | |
|---|---|
| `+` | Addition |
| `-` | Subtraction |
| `*` | Multiplication |
| `/` | Division |
| `%` | Modulus |

```php
->calculatedColumn('line_total', 'Line Total', ['quantity', 'unit_price'], '*')
->calculatedColumn('profit',     'Profit',     ['revenue', 'cost'],        '-')
```

### calculatedColumnRaw()

Accepts a raw SQL expression for complex calculations.

```php
->calculatedColumnRaw(string $alias, string $label, string $expression)
```

```php
->calculatedColumnRaw('margin_pct', 'Margin %',
    '((sell_price - cost_price) / sell_price) * 100')

->calculatedColumnRaw('full_name', 'Full Name',
    'CONCAT(u.first_name, " ", u.last_name)')
```

---

## Footer Aggregations

Calculates `SUM` and/or `AVG` per column and displays the results in the table footer. Two scopes are supported: **page** (calculated client-side from the current page data) and **all** (calculated server-side across the full filtered recordset).

### footerAggregate()

```php
->footerAggregate(string $column, string $type = 'sum', string $scope = 'both', string $label = '')
```

| Parameter | Options |
|---|---|
| `$type` | `'sum'`, `'avg'`, `'both'` |
| `$scope` | `'page'`, `'all'`, `'both'` |

```php
->footerAggregate('amount',   'sum',  'both')
->footerAggregate('tax',      'avg',  'all')
->footerAggregate('quantity', 'both', 'page', 'Page Totals')
```

### footerAggregateColumns()

Applies the same type and scope to multiple columns at once.

```php
->footerAggregateColumns(array $columns, string $type = 'sum', string $scope = 'both', string $label = '')
```

```php
->footerAggregateColumns(['amount', 'tax', 'shipping'], 'sum', 'both')
```

Aggregation columns work with calculated columns — pass the alias name:

```php
->calculatedColumn('line_total', 'Line Total', ['quantity', 'unit_price'], '*')
->footerAggregate('line_total', 'sum', 'both')
```

---

## Styling

### tableClass()

Overrides the default CSS classes on the `<table>` element.

```php
->tableClass(string $class)
```

```php
->tableClass('uk-table uk-table-striped uk-table-hover my-custom-table')
->tableClass('table table-dark table-sm')
```

---

### rowClass()

Sets a base CSS class applied to every `<tr>`. The record's primary key value is appended, e.g., `my-row-42`.

```php
->rowClass(string $class)
```

```php
->rowClass('data-row')
// Produces: class="data-row-42 row-select"
```

---

### columnClasses()

Applies CSS classes to specific `<td>` (and `<th>`) elements by column key.

```php
->columnClasses(array $classes)
```

```php
->columnClasses([
    'id'      => 'uk-table-shrink',
    'u.name'  => 'uk-text-bold',
    'u.email' => 'uk-text-primary',
    'status'  => 'uk-text-center',
    's_stream_uri' => 'txt-truncate',
])
```

---

## File Uploads

Configures server-side validation for file uploads submitted through `file` or `image` form fields, or via inline image editing.

```php
->fileUpload(string $uploadPath = 'uploads/', array $allowedExtensions = [], int $maxFileSize = 10485760)
```

| Parameter | Default | Description |
|---|---|---|
| `$uploadPath` | `'uploads/'` | Destination directory (created if absent) |
| `$allowedExtensions` | `['jpg','jpeg','png','gif','pdf','doc','docx']` | Whitelist of extensions (without dot) |
| `$maxFileSize` | `10485760` (10 MB) | Maximum size in bytes |

```php
->fileUpload('uploads/avatars/', ['jpg', 'jpeg', 'png', 'gif', 'webp'], 5242880)
->fileUpload('uploads/documents/', ['pdf', 'doc', 'docx', 'xls', 'xlsx'])
```

Uploaded files are stored with a `uniqid()` prefix to prevent collisions. The stored filename (not path) is saved to the database field.

---

## Rendering

### renderDataTableComponent()

Renders the complete DataTable HTML: the container, filter accordion, table (with header, body, footer, aggregation rows), all modals, and the JS initialization script. This is the primary output method.

```php
echo $dt->renderDataTableComponent();
```

---

## Static Methods

### getCssIncludes()

```php
DataTables::getCssIncludes(string $theme = 'uikit', bool $includeCdn = true, bool $useMinified = false): string
```

### getJsIncludes()

```php
DataTables::getJsIncludes(string $theme = 'uikit', bool $includeCdn = true, bool $useMinified = false): string
```

---

## Standalone Component Renderers

Individual components can be rendered outside `renderDataTableComponent()` for custom layouts.

```php
// Filter accordion panel (omit from renderDataTableComponent() to avoid duplicates)
echo $dt->renderFilterAccordionComponent();

// Search form input + reset button
echo $dt->renderSearchFormComponent();

// Bulk actions toolbar (add button + bulk operation buttons)
echo $dt->renderBulkActionsComponent();

// Per-page selector as dropdown (default)
echo $dt->renderPageSizeSelectorComponent();

// Per-page selector as button group
echo $dt->renderPageSizeSelectorComponent(true);

// Pagination list + record info text
echo $dt->renderPaginationComponent();
```

> When calling `renderFilterAccordionComponent()` manually, remove the internal call by ensuring the filter accordion is not rendered inside `renderContainer()`. Rendering it twice causes duplicate DOM IDs.

---

## Filter Accordion Reference

### Operators

| Operator | SQL Behavior | Input Rendered |
|---|---|---|
| `=` | Exact match | Text input |
| `!=` | Not equal | Text input |
| `>`, `>=`, `<`, `<=` | Numeric/date comparison | Text or number input |
| `LIKE` | Partial match (`%value%` auto-wrapped) | Text input |
| `NOT LIKE` | Inverse partial match | Text input |
| `IN` | Comma-separated value list | Text input (hint shown) |
| `NOT IN` | Exclude comma-separated list | Text input |
| `BETWEEN` | Date or number range | Two side-by-side inputs with From/To labels |
| `REGEXP` | Regular expression match | Text input |

### Input Types

| Type | Renders |
|---|---|
| `text` (default) | `<input type="text">` |
| `number` | `<input type="number">` |
| `date` | `<input type="date">` |
| `datetime-local` | `<input type="datetime-local">` |
| `email` | `<input type="email">` |
| `boolean` | `<select>` with All / Active / Inactive |
| `select` | `<select>` — requires `options` in config |

### Active Filter Badge

When filters are applied, a count badge appears in the accordion header. The badge updates automatically as filters are added or removed and clears when `resetFilters()` is called.

---

## WHERE Conditions Reference

`where()` conditions differ from `filter()` conditions:

| | `where()` | `filter()` |
|---|---|---|
| Who sets it | Developer | End user (via accordion UI) |
| Persists across requests | Always | Only while inputs are filled |
| Applied to mutations | Yes (UPDATE, DELETE) | No |
| Visible to user | No | Yes |

Both are applied simultaneously — `where()` conditions are evaluated first, filter conditions are appended with `AND`.

---

## Column Definition Reference

When using the enhanced column format (array value instead of string label), the following keys control display and form behavior:

| Key | Description |
|---|---|
| `label` | Display label in table header and form |
| `type` | Override the auto-detected field type for display and inline editing |
| `options` | `value => label` map for `select` type display and inline editing |
| `query` | SQL query for `select2` type (must return `ID` and `Label` columns) |
| `min_search_chars` | Minimum characters before select2 search fires |
| `max_results` | Maximum select2 results |
| `formatter` | Date format string for `datepicker` type |
| `class` | Extra CSS class on the column's form wrapper |
| `attributes` | Extra HTML attributes on the column's form input |
| `placeholder` | Placeholder text |

**Auto-detected types from MySQL schema:**

| MySQL Column Type | Detected As |
|---|---|
| `tinyint(1)`, `boolean`, `bit(1)` | `boolean` |
| `int`, `bigint`, `smallint`, etc. | `number` |
| `decimal`, `float`, `double` | `number` |
| `datetime`, `timestamp` | `datetime-local` |
| `date` | `date` |
| `time` | `time` |
| `text`, `longtext`, `mediumtext` | `textarea` |
| `enum` | `select` |
| `varchar` | `text` (or `email` if column name contains "email") |

---

## JavaScript API

The `DataTablesJS` class is instantiated automatically by the PHP `renderInitScript()` output and exposed on `window.DataTables`. You can call its methods directly from inline event handlers or your own JS.

### Core Methods

| Method | Description |
|---|---|
| `DataTables.loadData()` | Reload table data with current search/sort/filter/page state |
| `DataTables.goToPage(page)` | Navigate to a specific page number |
| `DataTables.resetSearch()` | Clear search input and reload |
| `DataTables.applyFilters()` | Read filter inputs and reload |
| `DataTables.resetFilters()` | Clear all filter inputs and reload |
| `DataTables.changePageSize(size, event)` | Change records per page |

### CRUD Methods

| Method | Description |
|---|---|
| `DataTables.showAddModal(event)` | Open the add record modal |
| `DataTables.showEditModal(id)` | Fetch record and open edit modal |
| `DataTables.showDeleteModal(id)` | Open delete confirmation modal |
| `DataTables.confirmDelete()` | Execute the pending delete |
| `DataTables.submitAddForm(event)` | Submit the add form via AJAX |
| `DataTables.submitEditForm(event)` | Submit the edit form via AJAX |

### Selection and Bulk

| Method | Description |
|---|---|
| `DataTables.toggleSelectAll(checkbox)` | Toggle all row checkboxes |
| `DataTables.toggleRowSelection(checkbox)` | Toggle a single row checkbox |
| `DataTables.executeBulkAction()` | Execute action from `<select>` bulk action UI |
| `DataTables.executeBulkActionDirect(action, event)` | Execute a named bulk action from a button |

### Notifications and Modals

| Method | Description |
|---|---|
| `DataTables.showNotification(message, status)` | Show themed notification (`'success'`, `'danger'`, `'warning'`) |
| `DataTables.showModal(modalId)` | Open a modal by ID |
| `DataTables.hideModal(modalId)` | Close a modal by ID |
| `DataTables.showConfirm(message)` | Show themed confirm dialog, returns `Promise` |

### Global Helper Objects

| Object | Purpose |
|---|---|
| `KPDataTablesPlain` | Modal and notification helpers for plain/tailwind themes |
| `KPDataTablesBootstrap` | Bootstrap Toast and modal confirm helper |
| `KPDataTablesDatepicker` | Date formatting and ISO parsing utilities |
| `KPTSelect2` | AJAX-powered Select2 class (no jQuery) |

---

## Building Assets

Node.js tooling is used to compile and minify JS and CSS. Install dependencies first:

```bash
npm install
```

### Available npm Scripts

| Command | Description |
|---|---|
| `npm run build` | Compile Tailwind CSS, then minify all JS and CSS |
| `npm run build:js` | Minify JS bundle only |
| `npm run build:css` | Minify all theme CSS files only |
| `npm run build:tailwind` | Compile Tailwind CSS from `tailwind.src.css` |
| `npm run watch:tailwind` | Watch and recompile Tailwind CSS on changes |
| `npm run dev:tailwind` | One-shot Tailwind compile without minification |
| `npm run dev` | Tailwind compile + minify all |

### Output Locations

| Asset | Output |
|---|---|
| JS bundle | `src/assets/js/dist/kpt-datatables.min.js` |
| CSS (per theme) | `src/assets/css/dist/{theme}.min.css` |
| Tailwind source | `src/assets/css/themes/tailwind.css` |

---

## Testing

```bash
# Run all tests
composer test

# Run with HTML coverage report
composer test-coverage

# Code style check (PSR-12)
composer cs-check

# Code style auto-fix
composer cs-fix
```

Tests cover UIKit, Bootstrap, Tailwind, and Plain theme rendering as well as AJAX handler routing. The CI matrix runs against PHP 8.2, 8.3, and 8.4 with both lowest and highest dependency sets.

---

## Security

- All GET/POST inputs are sanitized before use in SQL queries.
- All SQL values are passed through PDO bound parameters — no string interpolation of user input.
- Column names, sort directions, and filter operators are validated against whitelists.
- The `where()` conditions are appended to all mutations, scoping UPDATE and DELETE to only records matching the developer-defined filter.
- Allowed AJAX actions are validated against a whitelist before dispatch.
- Inline editable columns are validated server-side before any UPDATE is executed.
- File uploads validate extension and size server-side independently of the client.

For security-related issues, email **security@kpirnie.com** rather than opening a GitHub issue.

---

## Full Configuration Example

```php
<?php
require 'vendor/autoload.php';

use KPT\DataTables;

$dbConfig = [
    'server'    => 'localhost',
    'schema'    => 'my_app',
    'username'  => 'db_user',
    'password'  => 'db_pass',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];

$dt = new DataTables($dbConfig);

if (isset($_POST['action']) || isset($_GET['action'])) {
    $dt->theme('uikit')
       ->table('orders o')
       ->primaryKey('o.id')
       ->join('LEFT', 'customers c', 'o.customer_id = c.id')
       ->join('LEFT', 'products p',  'o.product_id  = p.id')
       ->where([
           ['field' => 'o.deleted_at', 'comparison' => '=', 'value' => null],
       ])
       ->addForm('New Order', [
           'customer_id' => [
               'type'             => 'select2',
               'label'            => 'Customer',
               'query'            => 'SELECT id AS ID, company_name AS Label FROM customers WHERE active = 1',
               'placeholder'      => 'Search customers...',
               'min_search_chars' => 2,
               'required'         => true,
           ],
           'product_id' => [
               'type'    => 'select',
               'label'   => 'Product',
               'options' => ['1' => 'Widget A', '2' => 'Widget B'],
               'required' => true,
           ],
           'quantity'   => ['type' => 'number', 'label' => 'Quantity', 'required' => true],
           'status'     => ['type' => 'boolean', 'label' => 'Active'],
           'notes'      => ['type' => 'textarea', 'label' => 'Notes', 'tab' => 'Notes'],
           'attachment' => ['type' => 'file',     'label' => 'Attach', 'tab' => 'Notes'],
       ])
       ->editForm('Edit Order', [
           'customer_id' => [
               'type'    => 'select2',
               'label'   => 'Customer',
               'query'   => 'SELECT id AS ID, company_name AS Label FROM customers',
               'required' => true,
           ],
           'quantity'   => ['type' => 'number',   'label' => 'Quantity'],
           'status'     => ['type' => 'boolean',  'label' => 'Active'],
           'notes'      => ['type' => 'textarea', 'label' => 'Notes', 'tab' => 'Notes'],
       ])
       ->handleAjax();
}

echo DataTables::getCssIncludes('uikit', true, true);

echo $dt
    ->theme('uikit')
    ->table('orders o')
    ->primaryKey('o.id')
    ->join('LEFT', 'customers c', 'o.customer_id = c.id')
    ->join('LEFT', 'products p',  'o.product_id  = p.id')
    ->where([
        ['field' => 'o.deleted_at', 'comparison' => '=', 'value' => null],
    ])
    ->columns([
        'o.id'           => 'Order #',
        'c.company_name' => 'Customer',
        'p.product_name' => 'Product',
        'o.quantity'     => 'Qty',
        'o.unit_price'   => 'Unit Price',
        'o.status'       => ['label' => 'Status', 'type' => 'boolean'],
        'o.created_at'   => 'Ordered',
    ])
    ->calculatedColumn('line_total', 'Line Total', ['o.quantity', 'o.unit_price'], '*')
    ->footerAggregate('o.quantity', 'sum', 'page')
    ->footerAggregate('line_total', 'sum', 'both', 'Totals')
    ->sortable(['c.company_name', 'p.product_name', 'o.quantity', 'o.created_at'])
    ->inlineEditable(['o.quantity', 'o.status'])
    ->filter([
        'c.company_name' => ['operator' => 'LIKE',    'label' => 'Customer'],
        'o.status'       => ['operator' => '=',       'label' => 'Status', 'type' => 'boolean'],
        'o.created_at'   => ['operator' => 'BETWEEN', 'label' => 'Order Date', 'type' => 'date'],
    ])
    ->defaultSort('o.created_at', 'DESC')
    ->perPage(25)
    ->pageSizeOptions([25, 50, 100], true)
    ->bulkActions(true, [
        'cancel' => [
            'label'    => 'Cancel Selected',
            'icon'     => 'close',
            'confirm'  => 'Cancel selected orders?',
            'callback' => function($ids, $db, $table) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                return $db->query("UPDATE `{$table}` SET status = 0 WHERE id IN ({$ph})")
                          ->bind($ids)->execute();
            },
        ],
    ])
    ->actionGroups([
        [
            'invoice' => [
                'icon'  => 'print',
                'title' => 'Print Invoice',
                'href'  => '/invoice/{id}',
                'class' => 'btn-invoice',
            ],
        ],
        ['edit', 'delete'],
    ])
    ->columnClasses([
        'o.id'       => 'uk-table-shrink',
        'o.status'   => 'uk-text-center',
        'line_total' => 'uk-text-right',
    ])
    ->fileUpload('uploads/attachments/', ['pdf', 'doc', 'docx', 'png', 'jpg'], 10485760)
    ->renderDataTableComponent();

echo DataTables::getJsIncludes('uikit', true, true);
```

---

## Browser Support

Chrome 60+, Firefox 60+, Safari 12+, Edge 79+

---

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/my-feature`)
3. Commit your changes (`git commit -m 'Add my feature'`)
4. Push to the branch (`git push origin feature/my-feature`)
5. Open a Pull Request

---

## Roadmap

- [ ] CSV / Excel / PDF export
- [ ] REST API endpoints
- [x] Multi-framework theme support
- [x] Calculated columns and footer aggregations
- [x] Column filter accordion with BETWEEN date range support
- [x] Select2 AJAX searchable dropdowns
- [x] Tabbed modal forms
- [x] Conditional field overrides (`allow_on`)
- [x] Custom datepicker with format tokens

---

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.

---

## Support

- **Issues**: [GitHub Issues](https://github.com/kpirnie/kp-datatables/issues)
- **Discord**: [Join the server](https://discord.gg/bd4Qan3PaN)

---

**Made with ❤️ by [Kevin Pirnie](https://kevinpirnie.com)**
