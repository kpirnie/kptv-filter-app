<?php

declare(strict_types=1);

namespace KPT;

use KPT\Logger;
use Exception;
use InvalidArgumentException;

// Check if class already exists before declaring it
if (! class_exists('KPT\AjaxHandler', false)) {

    /**
     * AjaxHandler - Handles AJAX Requests for DataTables
     *
     * This class processes all AJAX requests for DataTables operations including
     * data fetching, CRUD operations, bulk actions, inline editing, and file uploads.
     * It acts as the main controller for server-side operations with enhanced security
     * and input sanitization. Always handled internally, never accessible from public files.
     *
     * @since   1.0.0
     * @author  Kevin Pirnie <me@kpirnie.com>
     * @package KPT\DataTables
     */
    class AjaxHandler
    {
        /**
         * DataTables instance containing configuration and database access
         *
         * @var DataTables
         */
        private DataTables $dataTable;

        /**
         * Allowed MIME types per file extension for upload sniffing
         *
         * @var array
         */
        private const UPLOAD_MIME_MAP = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'bmp' => ['image/bmp', 'image/x-ms-bmp'],
            'ico' => ['image/vnd.microsoft.icon', 'image/x-icon'],
            'svg' => ['image/svg+xml'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/vnd.ms-office', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
            'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/octet-stream'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
            'ppt' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/octet-stream'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
            'csv' => ['text/csv', 'text/plain', 'application/csv'],
            'txt' => ['text/plain'],
            'zip' => ['application/zip', 'application/x-zip-compressed'],
            'mp3' => ['audio/mpeg'],
            'mp4' => ['video/mp4'],
            'html' => ['text/html'],
            'htm' => ['text/html'],
        ];

        /**
         * Extensions that are never accepted, regardless of configuration
         *
         * @var array
         */
        private const UPLOAD_BLOCKED_EXTENSIONS = [
            'php',
            'php3',
            'php4',
            'php5',
            'php7',
            'php8',
            'phps',
            'pht',
            'phtml',
            'phar',
            'cgi',
            'pl',
            'py',
            'sh',
            'asp',
            'aspx',
            'jsp',
            'exe',
            'htaccess',
            'htpasswd',
        ];

        /**
         * Constructor - Initialize the AJAX handler
         *
         * @param DataTables $dataTable The DataTables instance with configuration
         */
        public function __construct(DataTables $dataTable)
        {
            $this->dataTable = $dataTable;
        }

        /**
         * Main AJAX request dispatcher with enhanced security validation
         *
         * Routes incoming AJAX requests to the appropriate handler method based
         * on the action parameter. This is the main entry point for all AJAX operations.
         * Includes whitelist validation for security.
         *
         * @param  string $action The action to perform (fetch_data, add_record, edit_record, etc.)
         * @return void
         * @throws InvalidArgumentException If the action is unknown or invalid
         */
        public function handle(string $action): void
        {
            // Whitelist of allowed actions for security
            $allowedActions = [
                'fetch_data',
                'add_record',
                'edit_record',
                'delete_record',
                'bulk_action',
                'inline_edit',
                'upload_file',
                'fetch_record',
                'action_callback',
                'fetch_aggregations',
                'fetch_select2_options',
            ];

            if (!in_array($action, $allowedActions)) {
                throw new InvalidArgumentException("Invalid action: {$action}");
            }

            // Route the request to the appropriate handler method
            switch ($action) {
                case 'fetch_data':
                    // Handle data retrieval for table display
                    $this->handleFetchData();
                    break;
                case 'fetch_record':
                    // Handle single record fetch for editing
                    $this->handleFetchRecord();
                    break;
                case 'add_record':
                    // Handle new record creation
                    $this->handleAddRecord();
                    break;
                case 'edit_record':
                    // Handle existing record updates
                    $this->handleEditRecord();
                    break;
                case 'delete_record':
                    // Handle single record deletion
                    $this->handleDeleteRecord();
                    break;
                case 'bulk_action':
                    // Handle bulk operations on multiple records
                    $this->handleBulkAction();
                    break;
                case 'inline_edit':
                    // Handle inline field editing
                    $this->handleInlineEdit();
                    break;
                case 'upload_file':
                    // Handle standalone file uploads
                    $this->handleFileUpload();
                    break;
                case 'action_callback':
                    // Handle action callbacks with full row data
                    $this->handleActionCallback();
                    break;
                case 'fetch_aggregations':
                    // Handle calculation aggregations
                    $this->handleFetchAggregations();
                    break;
                case 'fetch_select2_options':
                    // Handle Select2 options fetch
                    $this->handleFetchSelect2Options();
                    break;
            }
        }

        /**
         * Handle standalone file upload requests
         *
         * Processes file uploads that are sent separately from form submissions.
         * Validates file type, size, and moves file to configured upload directory.
         *
         * @return void (outputs JSON and exits)
         * @throws InvalidArgumentException If no file is uploaded
         */
        private function handleFileUpload(): void
        {
            // Check if file was uploaded
            if (!isset($_FILES['file']) || !is_array($_FILES['file']) || ($_FILES['file']['error'] ?? null) !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('No file uploaded');
            }

            // Process the uploaded file
            $file = $_FILES['file'];
            $uploadResult = $this->uploadFile($file);

            // Send JSON response with upload result
            header('Content-Type: application/json');
            echo json_encode($uploadResult);
            exit;
        }

        /**
         * Process file uploads in form data
         *
         * Scans $_FILES for uploaded files and processes them, updating the form data
         * with the file paths. Only fields that exist in the schema and are configured
         * as file/image fields are accepted. Used during add/edit record operations.
         *
         * @param  array  $data Form data to process
         * @param  string $form Form the upload belongs to ('add' or 'edit')
         * @return array Updated form data with file paths
         */
        private function processFileUploads(array $data, string $form): array
        {
            $schema = $this->dataTable->getTableSchema();
            $formConfig = $form === 'edit' ? $this->dataTable->getEditFormConfig() : $this->dataTable->getAddFormConfig();
            $formFields = $formConfig['fields'] ?? [];
            $unqualifiedPK = $this->getUnqualifiedPrimaryKey();

            // Loop through all uploaded files
            foreach ($_FILES as $fieldName => $file) {
                // Handle image field uploads (remove -file suffix)
                $actualFieldName = str_ends_with((string) $fieldName, '-file') ? substr((string) $fieldName, 0, -5) : (string) $fieldName;

                // Field names are identifiers only, must exist in the schema, and can't be the PK
                if (!preg_match('/^[A-Za-z0-9_]+$/', $actualFieldName) || !isset($schema[$actualFieldName]) || $actualFieldName === $unqualifiedPK) {
                    continue;
                }

                // Only accept fields configured as file or image
                $type = $formFields[$actualFieldName]['type'] ?? $schema[$actualFieldName]['override_type'] ?? $schema[$actualFieldName]['type'] ?? '';
                if (!in_array($type, ['file', 'image'], true)) {
                    continue;
                }

                if (is_array($file) && ($file['error'] ?? null) === UPLOAD_ERR_OK) {
                    $uploadResult = $this->uploadFile($file);

                    if ($uploadResult['success']) {
                        $data[$actualFieldName] = $uploadResult['file_name']; // Just filename, not full path
                    }
                }
            }

            return $data;
        }

        /**
         * Upload a single file with enhanced validation
         *
         * Handles the complete file upload process including validation of upload status,
         * file size, extension, sniffed MIME type, directory creation, and file movement
         * with security checks. Files are stored under a random name.
         *
         * @param  array $file File array from $_FILES
         * @return array Upload result with success status, file path, and message
         */
        private function uploadFile(array $file): array
        {
            // Get upload configuration
            $config = $this->dataTable->getFileUploadConfig();

            // Validate upload status
            if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
                return [
                    'success' => false,
                    'message' => 'File upload failed'
                ];
            }

            // Validate file size
            if ($file['size'] > $config['max_file_size']) {
                return [
                    'success' => false,
                    'message' => 'File size exceeds maximum allowed size'
                ];
            }

            // Extract and validate file extension, never allowing executable types
            $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            $allowed = array_map('strtolower', $config['allowed_extensions']);
            if ($extension === '' || in_array($extension, self::UPLOAD_BLOCKED_EXTENSIONS, true) || !in_array($extension, $allowed, true)) {
                return [
                    'success' => false,
                    'message' => 'File type not allowed'
                ];
            }

            // Sniff the real MIME type and make sure it matches the extension
            if (isset(self::UPLOAD_MIME_MAP[$extension])) {
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                if ($mime === false || !in_array($mime, self::UPLOAD_MIME_MAP[$extension], true)) {
                    return [
                        'success' => false,
                        'message' => 'File type not allowed'
                    ];
                }
            }

            // Ensure upload directory exists
            if (!is_dir($config['upload_path'])) {
                // Create directory with restricted permissions
                mkdir($config['upload_path'], 0750, true);
            }

            // Resolve the real upload directory
            $uploadDir = realpath($config['upload_path']);
            if ($uploadDir === false) {
                return [
                    'success' => false,
                    'message' => 'Upload directory unavailable'
                ];
            }

            // Generate random filename with optional restricted prepend
            $prepend = (string) ($_POST['prepend'] ?? '');
            if ($prepend !== '' && !preg_match('/^[A-Za-z0-9_-]{1,32}$/', $prepend)) {
                $prepend = '';
            }
            $fileName = ($prepend !== '' ? $prepend . '_' : '') . bin2hex(random_bytes(16)) . '.' . $extension;
            $filePath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

            // Make sure the final path stays inside the upload directory
            if (dirname($filePath) !== $uploadDir) {
                return [
                    'success' => false,
                    'message' => 'Invalid upload path'
                ];
            }

            // Attempt to move uploaded file to final destination
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                return [
                    'success' => true,
                    'file_path' => $filePath,
                    'file_name' => $fileName,
                    'message' => 'File uploaded successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to move uploaded file'
                ];
            }
        }

        /**
         * Handle data fetching for table display with enhanced input sanitization
         *
         * Processes requests for table data including pagination, sorting, and searching.
         * Builds and executes SQL queries based on the request parameters and returns
         * JSON response with data and metadata. All inputs are sanitized and validated.
         *
         * @return void (outputs JSON and exits)
         */
        private function handleFetchData(): void
        {
            // Extract and validate pagination parameters with bounds checking
            $defaultPerPage = $this->dataTable->getRecordsPerPage();
            $perPage = filter_var($_GET['per_page'] ?? $defaultPerPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000]]);

            // Invalid values fall back to the default; 0 (all) only when the All option is enabled
            if ($perPage === false || ($perPage === 0 && !$this->dataTable->getIncludeAllOption())) {
                $perPage = $defaultPerPage;
            }

            // Cap the page so the offset stays bounded
            $maxPage = $perPage > 0 ? max(1, intdiv(1000000, $perPage)) : 1;
            $page = $this->validateInteger($_GET['page'] ?? 1, 1, $maxPage);

            // Sanitize search inputs with proper escaping
            $search = $this->sanitizeSearchInput($_GET['search'] ?? '');
            if (mb_strlen(trim((string) ($_GET['search'] ?? ''))) < $this->dataTable->getMinSearchLength()) {
                $search = '';
            }
            $searchColumn = $this->validateSearchColumn($_GET['search_column'] ?? '');

            // Sanitize and validate sort inputs
            $sortColumn = $this->sanitizeColumnName($_GET['sort_column'] ?? '');
            $sortDirection = $this->sanitizeSortDirection($_GET['sort_direction'] ?? 'ASC');

            // Validate sort column exists in configuration
            if (!empty($sortColumn)) {
                $validColumns = array_keys($this->dataTable->getColumns());
                $sortColumnValid = in_array($sortColumn, $validColumns);

                // If not found, check if it matches an alias
                if (!$sortColumnValid) {
                    foreach ($validColumns as $column) {
                        if (stripos($column, ' AS ') !== false) {
                            $parts = explode(' AS ', $column);
                            if (count($parts) === 2) {
                                $aliasName = trim($parts[1], '`\'" ');
                                if ($aliasName === $sortColumn) {
                                    $sortColumn = $column; // Use full expression for SQL
                                    $sortColumnValid = true;
                                    break;
                                }
                            }
                        }
                    }
                }

                if (!$sortColumnValid) {
                    $sortColumn = ''; // Reset invalid column
                }
            }

            // Sanitize and validate raw filter JSON from request
            $filtersJson = $this->sanitizeJsonInput($_GET['filters'] ?? '[]');

            // Execute data query using fluent interface
            $data  = $this->executeDataQuery($search, $searchColumn, $sortColumn, $sortDirection, $page, $perPage, $filtersJson);

            // Execute count query using fluent interface
            $total = $this->executeCountQuery($search, $searchColumn, $filtersJson);

            // Extract total count from result
            $totalRecords = $total ? $total->total : 0;

            // Calculate total pages (handle division by zero for "all" records)
            $totalPages = $perPage === 0 ? 1 : ceil($totalRecords / $perPage);

            // Send JSON response with data and metadata
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $data ?: [], // Ensure array even if no data
                'total' => $totalRecords,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages
            ]);

            // Make sure we exit so nothing else gets outputted
            exit;
        }

        /**
         * Execute data query with filtering, sorting, and pagination using fluent interface
         *
         * Builds and executes a SELECT query based on the provided parameters. Handles
         * table joins, WHERE conditions, search filtering, sorting, and pagination.
         * Uses the DataTables configuration to determine which fields to select and
         * how to construct the query.
         *
         * @param  string $search        Global search term to filter results across searchable columns
         * @param  string $searchColumn  Specific column to search in (use 'all' for global search)
         * @param  string $sortColumn    Column name to sort results by
         * @param  string $sortDirection Sort direction - 'ASC' for ascending, 'DESC' for descending
         * @param  int    $page          Page number for pagination (1-based)
         * @param  int    $perPage       Number of records per page (0 for all records)
         * @param  string $filtersJson   JSON string containing filter conditions (optional)
         * @return mixed                 Query result object or false on failure
         * @since  1.0.0
         */
        private function executeDataQuery(string $search = '', string $searchColumn = '', string $sortColumn = '', string $sortDirection = 'ASC', int $page = 1, int $perPage = 25, string $filtersJson = '[]'): mixed
        {
            $selectFields = $this->getSelectFields();
            $tableName = $this->dataTable->getTableName();

            if (strpos($tableName, ' ') !== false) {
                $sql = "SELECT " . implode(', ', $selectFields) . " FROM {$tableName}";
            } else {
                $sql = "SELECT " . implode(', ', $selectFields) . " FROM `{$tableName}`";
            }

            foreach ($this->dataTable->getJoins() as $join) {
                $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
            }

            $params = [];

            // Add WHERE conditions first
            $whereClause = $this->buildWhereClause($this->dataTable->getWhereConditions(), $params);
            $hasWhere = !empty($whereClause);

            if ($hasWhere) {
                $sql .= $whereClause;
            }

            // Add search conditions
            if (!empty($search)) {
                $searchConditions = [];

                if (!empty($searchColumn) && $searchColumn !== 'all') {
                    if (strpos($searchColumn, '.') !== false) {
                        $searchConditions[] = "{$searchColumn} LIKE ? ESCAPE '!'";
                    } else {
                        $searchConditions[] = "`{$searchColumn}` LIKE ? ESCAPE '!'";
                    }
                    $params[] = "%{$search}%";
                } else {
                    foreach ($this->dataTable->getColumns() as $column => $label) {
                        if (!$this->isSearchableColumn((string) $column)) {
                            continue;
                        }
                        // For aliased columns, use only the expression part (before AS) in WHERE clause
                        $searchColumn = $column;
                        if (stripos($column, ' AS ') !== false) {
                            $parts = explode(' AS ', $column);
                            $searchColumn = trim($parts[0]); // Use only the expression part
                        }

                        if (strpos($searchColumn, '.') !== false) {
                            $searchConditions[] = "{$searchColumn} LIKE ? ESCAPE '!'";
                        } else {
                            $searchConditions[] = "`{$searchColumn}` LIKE ? ESCAPE '!'";
                        }
                        $params[] = "%{$search}%";
                    }
                }

                if (!empty($searchConditions)) {
                    $sql .= ($hasWhere ? ' AND ' : ' WHERE ') . '(' . implode(' OR ', $searchConditions) . ')';
                }
            }

            // Apply user-facing filters on top of existing where() and search conditions
            $filterClause = $this->buildFilterClause($filtersJson, $params);
            if (!empty($filterClause)) {
                // Determine correct joining keyword based on what precedes
                $sql .= ($hasWhere || !empty($searchConditions)) ? ' AND ' : ' WHERE ';
                $sql .= $filterClause;
            }

            // Add GROUP BY clause if configured
            $groupBy = $this->dataTable->getGroupBy();
            if (!empty($groupBy)) {
                $sql .= strpos($groupBy, '.') !== false ? " GROUP BY {$groupBy}" : " GROUP BY `{$groupBy}`";
            }

            // Check if sortColumn is sortable (handle both full expressions and aliases)
            $isSortable = false;
            if (!empty($sortColumn)) {
                $sortableColumns = $this->dataTable->getSortableColumns();

                if (in_array($sortColumn, $sortableColumns)) {
                    $isSortable = true;
                } else {
                    // Check if this is a full expression that matches a sortable alias
                    if (stripos($sortColumn, ' AS ') !== false) {
                        $parts = explode(' AS ', $sortColumn);
                        if (count($parts) === 2) {
                            $aliasName = trim($parts[1], '`\'" ');
                            if (in_array($aliasName, $sortableColumns)) {
                                $isSortable = true;
                            }
                        }
                    }
                }
            }

            if ($isSortable) {
                $direction = strtoupper($sortDirection) === 'DESC' ? 'DESC' : 'ASC';

                // Handle aliases - if sorting by an alias, use just the alias name in ORDER BY
                if (stripos($sortColumn, ' AS ') !== false) {
                    $parts = explode(' AS ', $sortColumn);
                    $aliasName = trim($parts[1], '`\'" ');
                    $sql .= " ORDER BY `{$aliasName}` {$direction}";
                } elseif (strpos($sortColumn, '.') !== false) {
                    $sql .= " ORDER BY {$sortColumn} {$direction}";
                } else {
                    $sql .= " ORDER BY `{$sortColumn}` {$direction}";
                }
            }

            if ($perPage > 0) {
                $offset = ($page - 1) * $perPage;
                $sql .= " LIMIT {$offset}, {$perPage}";
            }

            $query = $this->dataTable->getDatabase()->query($sql);
            if (!empty($params)) {
                $query->bind($params);
            }
            $data = $query->fetch();

            // Fetch select2 labels for display
            if ($data) {
                $columns = $this->dataTable->getColumns();
                $tableSchema = $this->dataTable->getTableSchema();

                foreach ($columns as $column => $label) {
                    $schemaKey = strpos($column, '.') !== false ? explode('.', $column)[1] : $column;
                    $fieldInfo = $tableSchema[$schemaKey] ?? [];

                    if (isset($fieldInfo['override_type']) && $fieldInfo['override_type'] === 'select2') {
                        $query = $fieldInfo['select2_query'] ?? '';
                        if ($query) {
                            $labelMap = $this->fetchSelect2Labels($data, $schemaKey, $query);

                            // Replace IDs with labels in data
                            foreach ($data as &$row) {
                                if (isset($row->$schemaKey) && isset($labelMap[$row->$schemaKey])) {
                                    $row->{$schemaKey . '_label'} = $labelMap[$row->$schemaKey];
                                }
                            }
                        }
                    }
                }
            }

            return $data;
        }


        /**
         * Execute count query for pagination metadata using fluent interface
         *
         * Builds and executes a COUNT query that matches the same filtering conditions
         * as the main data query. Used to determine total number of records for
         * pagination calculations. Includes the same JOINs and WHERE conditions
         * as the data query but returns only the count.
         *
         * @param  string $search       Global search term to filter results
         * @param  string $searchColumn Specific column to search in (use 'all' for global search)
         * @param  string $filtersJson  JSON string containing filter conditions (optional)
         * @return mixed                Query result object containing total count or false on failure
         * @since  1.0.0
         */
        private function executeCountQuery(string $search = '', string $searchColumn = '', string $filtersJson = '[]'): mixed
        {
            $tableName = $this->dataTable->getTableName();

            if (strpos($tableName, ' ') !== false) {
                $sql = "SELECT COUNT(*) as total FROM {$tableName}";
            } else {
                $sql = "SELECT COUNT(*) as total FROM `{$tableName}`";
            }

            foreach ($this->dataTable->getJoins() as $join) {
                $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
            }

            $params = [];

            // Add WHERE conditions first
            $whereClause = $this->buildWhereClause($this->dataTable->getWhereConditions(), $params);
            $hasWhere = !empty($whereClause);

            if ($hasWhere) {
                $sql .= $whereClause;
            }

            // Add search conditions
            if (!empty($search)) {
                $columns = $this->dataTable->getColumns();
                if (empty($columns)) {
                    $schema = $this->dataTable->getTableSchema();
                    $columns = array_keys($schema);
                } else {
                    $columns = array_keys($columns);
                }

                $searchConditions = [];
                if (!empty($searchColumn) && $searchColumn !== 'all') {
                    if (strpos($searchColumn, '.') !== false) {
                        $searchConditions[] = "{$searchColumn} LIKE ? ESCAPE '!'";
                    } else {
                        $searchConditions[] = "`{$searchColumn}` LIKE ? ESCAPE '!'";
                    }
                    $params[] = "%{$search}%";
                } else {
                    foreach ($columns as $column) {
                        if (!$this->isSearchableColumn((string) $column)) {
                            continue;
                        }
                        // For aliased columns, use only the expression part (before AS) in WHERE clause
                        $searchColumn = $column;
                        if (stripos($column, ' AS ') !== false) {
                            $parts = explode(' AS ', $column);
                            $searchColumn = trim($parts[0]); // Use only the expression part
                        }

                        if (strpos($searchColumn, '.') !== false) {
                            $searchConditions[] = "{$searchColumn} LIKE ? ESCAPE '!'";
                        } else {
                            $searchConditions[] = "`{$searchColumn}` LIKE ? ESCAPE '!'";
                        }
                        $params[] = "%{$search}%";
                    }
                }

                if (!empty($searchConditions)) {
                    $sql .= ($hasWhere ? ' AND ' : ' WHERE ') . '(' . implode(' OR ', $searchConditions) . ')';
                }
            }

            // Apply user-facing filters on top of existing where() and search conditions
            $filterClause = $this->buildFilterClause($filtersJson, $params);
            if (!empty($filterClause)) {
                // Determine correct joining keyword based on what precedes
                $sql .= ($hasWhere || !empty($searchConditions)) ? ' AND ' : ' WHERE ';
                $sql .= $filterClause;
            }

            // Wrap with GROUP BY subquery if configured
            $groupBy = $this->dataTable->getGroupBy();
            if (!empty($groupBy)) {
                $groupExpr = strpos($groupBy, '.') !== false ? $groupBy : "`{$groupBy}`";
                $sql = "SELECT COUNT(*) as total FROM ({$sql} GROUP BY {$groupExpr}) AS grouped";
            }

            $query = $this->dataTable->getDatabase()->query($sql);
            if (!empty($params)) {
                $query->bind($params);
            }
            return $query->single()->fetch();
        }

        /**
         * Generate SELECT field list from DataTables configuration
         *
         * Creates an array of field names for the SELECT clause based on the
         * configured columns in the DataTables instance. Handles qualified column
         * names (table.column) and preserves the exact key structure. Falls back
         * to selecting all columns (*) if no specific columns are configured.
         *
         * @return array Array of SELECT field expressions with proper aliasing
         * @since  1.0.0
         */
        private function getSelectFields(): array
        {
            $selectFields = [];
            $columns = $this->dataTable->getColumns();

            if (empty($columns)) {
                $selectFields[] = "*";
            } else {
                // Include configured display columns
                foreach ($columns as $column => $label) {
                    $selectFields[] = preg_match('/\s+AS\s+/i', $column) ? $column : "{$column} AS `{$column}`";
                }

                // Also include any fields referenced in action configurations
                $actionConfig = $this->dataTable->getActionConfig();
                if (isset($actionConfig['groups'])) {
                    foreach ($actionConfig['groups'] as $group) {
                        //if (is_array($group) && !is_numeric(key($group))) {
                        if (is_array($group)) {
                            foreach ($group as $actionKey => $action) {
                                if (isset($action['attributes'])) {
                                    foreach ($action['attributes'] as $attrName => $attrValue) {
                                        // Extract field names from placeholders like {s_stream_uri}
                                        if (preg_match_all('/\{([^}]+)\}/', $attrValue, $matches)) {
                                            foreach ($matches[1] as $field) {
                                                if ($field !== 'id' && !isset($columns[$field])) {
                                                    // Check if this field is already in selectFields as an alias
                                                    $fieldAlreadyExists = false;
                                                    foreach ($selectFields as $existingField) {
                                                        if (strpos($existingField, "AS {$field}") !== false || strpos($existingField, "AS `{$field}`") !== false) {
                                                            $fieldAlreadyExists = true;
                                                            break;
                                                        }
                                                    }
                                                    if (!$fieldAlreadyExists) {
                                                        // Add this field to select if not already included
                                                        $selectFields[] = "`{$field}`";
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                // Also check href, onclick, etc. for placeholders
                                foreach (['href', 'onclick', 'title'] as $prop) {
                                    if (isset($action[$prop]) && is_string($action[$prop])) {
                                        if (preg_match_all('/\{([^}]+)\}/', $action[$prop], $matches)) {
                                            foreach ($matches[1] as $field) {
                                                if ($field !== 'id' && !isset($columns[$field])) {
                                                    // Check if this field is already in selectFields as an alias
                                                    $fieldAlreadyExists = false;
                                                    foreach ($selectFields as $existingField) {
                                                        if (strpos($existingField, "AS {$field}") !== false || strpos($existingField, "AS `{$field}`") !== false) {
                                                            $fieldAlreadyExists = true;
                                                            break;
                                                        }
                                                    }
                                                    if (!$fieldAlreadyExists) {
                                                        // Add this field to select if not already included
                                                        $selectFields[] = "`{$field}`";
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // Remove duplicates
                $selectFields = array_unique($selectFields);
            }

            return $selectFields;
        }

        /**
         * Handle new record creation with schema validation
         *
         * Processes POST data to create a new record in the database. Validates
         * all input data against the table schema, handles file uploads, and
         * inserts the record into the base table. Excludes the primary key field
         * from insertion as it should be auto-generated.
         *
         * @return void                         Outputs JSON response and exits
         * @throws InvalidArgumentException     If no valid data provided for insertion
         * @since  1.0.0
         */
        private function handleAddRecord(): void
        {
            $data = $this->sanitizeFormData($_POST);
            $schema = $this->dataTable->getTableSchema();

            $validatedData = [];
            foreach ($data as $field => $value) {
                if (isset($schema[$field]) && $field !== $this->dataTable->getPrimaryKey()) {
                    $validatedData[$field] = $this->validateFieldValue($field, $value, $schema[$field]);
                }
            }

            $validatedData = $this->processFileUploads($validatedData, 'add');

            // Only allow configured add form fields
            $validatedData = array_intersect_key($validatedData, $this->getFormFieldWhitelist('add'));

            // Force where() scope values on insert
            foreach ($this->getScopeEqualityValues() as $scopeField => $scopeValue) {
                $validatedData[$scopeField] = $scopeValue;
            }

            if (empty($validatedData)) {
                throw new InvalidArgumentException('No valid data to insert');
            }

            $fields = array_keys($validatedData);
            $placeholders = array_fill(0, count($fields), '?');

            // Use BASE table name for INSERT (no alias)
            $query = "INSERT INTO `{$this->dataTable->getBaseTableName()}` (`" .
                implode('`, `', $fields) .
                "`) VALUES (" .
                implode(', ', $placeholders) .
                ")";

            $result = $this->dataTable->getDatabase()
                ->query($query)
                ->bind(array_values($validatedData))
                ->execute();

            $success = $result !== false;
            $message = $success ? 'Record added successfully' : 'Failed to add record';
            $insertId = $success ? $this->dataTable->getDatabase()->getLastId() : null;

            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $message,
                'id' => $insertId
            ]);
            exit;
        }

        /**
         * Handle existing record updates with enhanced validation
         *
         * Processes POST data to update an existing record in the database.
         * Validates the record ID, sanitizes and validates all field data against
         * the schema, handles file uploads, and updates the record. Respects
         * any configured WHERE conditions for security.
         *
         * @return void                         Outputs JSON response and exits
         * @throws InvalidArgumentException     If record ID is missing or invalid, or no valid data to update
         * @since  1.0.0
         */
        private function handleEditRecord(): void
        {
            $unqualifiedPK = $this->getUnqualifiedPrimaryKey();
            $id = $this->validateInteger($_POST[$unqualifiedPK] ?? null);
            if (!$id) {
                throw new InvalidArgumentException('Valid record ID is required');
            }

            $data = $this->sanitizeFormData($_POST);
            unset($data[$unqualifiedPK]);

            $schema = $this->dataTable->getTableSchema();
            $validatedData = [];

            foreach ($data as $field => $value) {
                if (isset($schema[$field]) && $field !== $unqualifiedPK) {
                    $validatedData[$field] = $this->validateFieldValue($field, $value, $schema[$field]);
                }
            }

            $validatedData = $this->processFileUploads($validatedData, 'edit');

            // Only allow configured edit form fields, never the where() scope columns
            $validatedData = array_intersect_key($validatedData, $this->getFormFieldWhitelist('edit'));
            $validatedData = array_diff_key($validatedData, $this->getScopeEqualityValues());

            if (empty($validatedData)) {
                throw new InvalidArgumentException('No valid data to update');
            }

            $fields = array_keys($validatedData);
            $setClause = implode(' = ?, ', array_map(function ($f) {
                return "`{$f}`";
            }, $fields)) . ' = ?';

            $sql = "UPDATE `{$this->dataTable->getBaseTableName()}` SET {$setClause}";
            $params = array_values($validatedData);

            // Add WHERE conditions
            $whereConditions = $this->dataTable->getWhereConditions();
            $additionalParams = [];
            $whereClause = $this->buildWhereClause($whereConditions, $additionalParams, true);

            if (!empty($whereClause)) {
                $sql .= $whereClause . " AND `{$unqualifiedPK}` = ?";
                $params = array_merge($params, $additionalParams, [$id]);
            } else {
                $sql .= " WHERE `{$unqualifiedPK}` = ?";
                $params[] = $id;
            }

            $result = $this->dataTable->getDatabase()
                ->query($sql)
                ->bind($params)
                ->execute();

            $success = $result !== false;
            $message = $success ? 'Record updated successfully' : 'Failed to update record';

            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $message
            ]);
            exit;
        }

        /**
         * Handle single record deletion with ID validation
         *
         * Deletes a specific record from the database based on the provided ID.
         * Validates the record ID and respects any configured WHERE conditions
         * for security. Returns the number of affected rows to confirm deletion.
         *
         * @return void                         Outputs JSON response and exits
         * @throws InvalidArgumentException     If record ID is missing or invalid
         * @since  1.0.0
         */
        private function handleDeleteRecord(): void
        {
            $id = $this->validateInteger($_POST['id'] ?? null);
            if (!$id) {
                throw new InvalidArgumentException('Valid record ID is required');
            }

            $unqualifiedPK = $this->getUnqualifiedPrimaryKey();
            $sql = "DELETE FROM `{$this->dataTable->getBaseTableName()}`";
            $params = [$id];

            // Add WHERE conditions
            $whereConditions = $this->dataTable->getWhereConditions();
            $additionalParams = [];
            $whereClause = $this->buildWhereClause($whereConditions, $additionalParams, true);

            if (!empty($whereClause)) {
                $sql .= $whereClause . " AND `{$unqualifiedPK}` = ?";
                $params = array_merge($additionalParams, $params);
            } else {
                $sql .= " WHERE `{$unqualifiedPK}` = ?";
            }

            $result = $this->dataTable->getDatabase()
                ->query($sql)
                ->bind($params)
                ->execute();

            $success = $result !== false && $result > 0;
            $message = $success ? 'Record deleted successfully' : ($result === 0 ? 'Record not found' : 'Failed to delete record');

            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $message,
                'affected_rows' => $result
            ]);
            exit;
        }

        /**
         * Handle single record fetch for editing
         *
         * Retrieves a specific record from the database for editing purposes.
         * Validates the record ID and respects any configured WHERE conditions.
         * Returns all fields from the base table for the specified record.
         *
         * @return void                         Outputs JSON response and exits
         * @throws InvalidArgumentException     If record ID is missing or invalid
         * @since  1.0.0
         */
        private function handleFetchRecord(): void
        {
            $id = $this->validateInteger($_GET['id'] ?? $_POST['id'] ?? null);
            if (!$id) {
                throw new InvalidArgumentException('Valid record ID is required');
            }

            $unqualifiedPK = $this->getUnqualifiedPrimaryKey();
            $primaryKey    = $this->dataTable->getPrimaryKey();
            $idColumn      = strpos($primaryKey, '.') !== false ? $unqualifiedPK : $primaryKey;

            // Columns the client may see: PK, edit form fields, and select2 {placeholder} fields
            $schema      = $this->dataTable->getTableSchema();
            $editFields  = $this->dataTable->getEditFormConfig()['fields'] ?? [];
            $visible     = [$unqualifiedPK => true];
            $serverOnly  = [];
            foreach ($editFields as $fieldName => $fieldConfig) {
                $visible[$this->getUnqualifiedFieldName((string) $fieldName)] = true;
                if (($fieldConfig['type'] ?? '') === 'select2' && !empty($fieldConfig['query'])) {
                    preg_match_all('/\{([a-zA-Z0-9_]+)\}/', (string) $fieldConfig['query'], $placeholderMatches);
                    foreach ($placeholderMatches[1] as $placeholderField) {
                        $visible[$placeholderField] = true;
                    }
                }
                // allow_on compare fields are needed server-side only
                if (!empty($fieldConfig['allow_on']['field'])) {
                    $serverOnly[(string) $fieldConfig['allow_on']['field']] = true;
                }
            }

            // Only select columns that exist on the base table
            $selectColumns = array_filter(
                array_keys($visible + $serverOnly),
                fn($column) => isset($schema[$column]) || $column === $unqualifiedPK
            );
            $selectList = implode(', ', array_map(fn($column) => "`{$column}`", $selectColumns));

            $sql    = "SELECT {$selectList} FROM `{$this->dataTable->getBaseTableName()}`";
            $params = [$id];

            $whereConditions  = $this->dataTable->getWhereConditions();
            $additionalParams = [];
            $whereClause      = $this->buildWhereClause($whereConditions, $additionalParams, true);

            if (!empty($whereClause)) {
                $sql    .= $whereClause . " AND `{$idColumn}` = ?";
                $params  = array_merge($additionalParams, $params);
            } else {
                $sql .= " WHERE `{$idColumn}` = ?";
            }

            $result  = $this->dataTable->getDatabase()->query($sql)->bind($params)->single()->fetch();
            $success = $result !== false;

            // Evaluate allow_on conditions against fetched record
            $fieldOverrides = [];
            if ($success && $result) {
                $fields      = $this->dataTable->getEditFormConfig()['fields'] ?? [];
                $recordArray = (array) $result;

                foreach ($fields as $fieldName => $fieldConfig) {
                    if (!isset($fieldConfig['allow_on'])) {
                        continue;
                    }

                    $allowOn      = $fieldConfig['allow_on'];
                    $compareField = $allowOn['field']    ?? '';
                    $compareValue = $allowOn['value']    ?? null;
                    $operator     = $allowOn['operator'] ?? '==';
                    $action       = $allowOn['action']   ?? [];

                    if (empty($compareField) || empty($action)) {
                        continue;
                    }

                    if (!$this->evaluateAllowOn($recordArray[$compareField] ?? null, $operator, $compareValue)) {
                        continue;
                    }

                    $override = [];
                    if (array_key_exists('set_value', $action)) {
                        $override['set_value'] = $action['set_value'];
                    }
                    if (!empty($action['set_attributes'])) {
                        $override['set_attributes'] = $action['set_attributes'];
                    }
                    if (!empty($action['set_classes'])) {
                        $override['set_classes'] = $action['set_classes'];
                    }
                    if (!empty($override)) {
                        $fieldOverrides[$fieldName] = $override;
                    }
                }
            }

            // Strip server-only columns before sending
            $data = $result ? array_intersect_key((array) $result, $visible) : null;

            header('Content-Type: application/json');
            echo json_encode([
                'success'        => $success,
                'message'        => $success ? 'Record fetched successfully' : 'Record not found',
                'data'           => $data,
                'field_overrides' => $fieldOverrides,
            ]);
            exit;
        }

        /**
         * Evaluate an allow_on condition
         *
         * @param  mixed  $recordValue  Value from the fetched record
         * @param  string $operator     Comparison operator
         * @param  mixed  $compareValue Value to compare against
         * @return bool
         */
        private function evaluateAllowOn(mixed $recordValue, string $operator, mixed $compareValue): bool
        {
            return match ($operator) {
                '=='     => $recordValue == $compareValue,
                '!='     => $recordValue != $compareValue,
                '>'      => $recordValue >  $compareValue,
                '>='     => $recordValue >= $compareValue,
                '<'      => $recordValue <  $compareValue,
                '<='     => $recordValue <= $compareValue,
                'IN'     => is_array($compareValue) && in_array($recordValue, $compareValue),
                'NOT IN' => is_array($compareValue) && !in_array($recordValue, $compareValue),
                default  => false,
            };
        }

        /**
         * Handle bulk actions on multiple records with enhanced security
         *
         * Processes bulk operations on multiple selected records. Validates that
         * bulk actions are enabled, the action is allowed, and the selected IDs
         * are valid. Supports built-in delete action and custom callback actions.
         * Respects configured WHERE conditions for security.
         *
         * @return void                         Outputs JSON response and exits
         * @throws InvalidArgumentException     If bulk action is invalid, not enabled, or no valid IDs provided
         * @since  1.0.0
         */
        private function handleBulkAction(): void
        {
            $bulkAction = $this->sanitizeInput($_POST['bulk_action'] ?? '');
            $selectedIds = $this->validateIdArray($_POST['selected_ids'] ?? '[]');

            if (empty($bulkAction) || empty($selectedIds)) {
                throw new InvalidArgumentException('Valid bulk action and selected IDs are required');
            }

            $bulkActions = $this->dataTable->getBulkActions();
            if (!$bulkActions['enabled']) {
                throw new InvalidArgumentException('Bulk actions are not enabled');
            }

            if (!isset($bulkActions['actions'][$bulkAction])) {
                throw new InvalidArgumentException("Unknown bulk action: {$bulkAction}");
            }

            // Drop any IDs outside the where() scope
            $selectedIds = array_keys($this->fetchScopedRows($selectedIds));
            if (empty($selectedIds)) {
                throw new InvalidArgumentException('No valid records selected');
            }

            $result = false;
            $message = '';

            switch ($bulkAction) {
                case 'delete':
                    $unqualifiedPK = $this->getUnqualifiedPrimaryKey();
                    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                    $sql = "DELETE FROM `{$this->dataTable->getBaseTableName()}`";
                    $params = $selectedIds;

                    // Add WHERE conditions
                    $whereConditions = $this->dataTable->getWhereConditions();
                    $additionalParams = [];
                    $whereClause = $this->buildWhereClause($whereConditions, $additionalParams, true);

                    if (!empty($whereClause)) {
                        $sql .= $whereClause . " AND `{$unqualifiedPK}` IN ({$placeholders})";
                        $params = array_merge($additionalParams, $selectedIds);
                    } else {
                        $sql .= " WHERE `{$unqualifiedPK}` IN ({$placeholders})";
                    }

                    $result = $this->dataTable->getDatabase()
                        ->query($sql)
                        ->bind($params)
                        ->execute();
                    $message = $result !== false ? 'Selected records deleted successfully' : 'Failed to delete selected records';
                    break;

                default:
                    $actionConfig = $bulkActions['actions'][$bulkAction];
                    if (isset($actionConfig['callback']) && is_callable($actionConfig['callback'])) {
                        $result = call_user_func(
                            $actionConfig['callback'],
                            $selectedIds,
                            $this->dataTable->getDatabase(),
                            $this->dataTable->getBaseTableName()  // Pass base table name
                        );
                        $message = $result ?
                            ($actionConfig['success_message'] ?? 'Bulk action completed successfully') : ($actionConfig['error_message'] ?? 'Bulk action failed');
                    }
                    break;
            }

            $affectedCount = is_int($result) ? $result : count($selectedIds);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => $result !== false,
                'message' => $message,
                'affected_count' => $affectedCount
            ]);
            exit;
        }

        /**
         * Handle inline field editing with enhanced validation
         *
         * Updates a single field value for a specific record through inline editing.
         * Validates that the field is configured as inline editable, the record ID
         * is valid, and the new value meets schema requirements. Supports both
         * qualified and unqualified field names.
         *
         * @return void                         Outputs JSON response and exits
         * @throws InvalidArgumentException     If record ID, field name invalid, or field not inline editable
         * @since  1.0.0
         */
        private function handleInlineEdit(): void
        {
            $id = $this->validateInteger($_POST['id'] ?? null);
            $field = trim($_POST['field'] ?? '');
            $value = $_POST['value'] ?? null;

            if (!$id || !$field) {
                throw new InvalidArgumentException('Record ID and field are required');
            }

            $columnName = strpos($field, '.') !== false ? explode('.', $field)[1] : $field;
            $inlineEditableColumns = $this->dataTable->getInlineEditableColumns();

            // Never allow the where() scope columns to change
            if (array_key_exists($columnName, $this->getScopeEqualityValues())) {
                throw new InvalidArgumentException('Field is not editable');
            }

            $schema = $this->dataTable->getTableSchema();
            if (isset($schema[$columnName])) {
                $value = $this->validateFieldValue($columnName, $value, $schema[$columnName]);
            }

            $unqualifiedPK = $this->getUnqualifiedPrimaryKey();
            $sql = "UPDATE `{$this->dataTable->getBaseTableName()}` SET `{$columnName}` = ?";
            $params = [$value, $id];

            // Add WHERE conditions
            $whereConditions = $this->dataTable->getWhereConditions();
            $additionalParams = [];
            $whereClause = $this->buildWhereClause($whereConditions, $additionalParams, true);

            if (!empty($whereClause)) {
                $sql .= $whereClause . " AND `{$unqualifiedPK}` = ?";
                $params = array_merge([$value], $additionalParams, [$id]);
            } else {
                $sql .= " WHERE `{$unqualifiedPK}` = ?";
            }

            $result = $this->dataTable->getDatabase()
                ->query($sql)
                ->bind($params)
                ->execute();

            $success = $result !== false;
            $message = $success ? 'Field updated successfully' : 'Failed to update field';

            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $message
            ]);
            exit;
        }

        /**
         * Handle action callbacks with full row data
         *
         * Executes custom callback functions for row-specific actions. Finds the
         * appropriate callback based on the action name, validates the row ID,
         * and executes the callback with the row ID, full row data, database
         * connection, and base table name.
         *
         * @return void                         Outputs JSON response and exits
         * @throws InvalidArgumentException     If action name invalid, row ID missing, or callback not found
         * @since  1.0.0
         */
        private function handleActionCallback(): void
        {
            $actionName = $this->sanitizeInput($_POST['action_name'] ?? '');
            $rowId = $this->validateInteger($_POST['row_id'] ?? null);

            if (empty($actionName) || !$rowId) {
                throw new InvalidArgumentException('Valid action and row ID are required');
            }

            // Find the action configuration
            $actionConfig = $this->dataTable->getActionConfig();
            $callback = null;

            if (isset($actionConfig['groups'])) {
                foreach ($actionConfig['groups'] as $group) {
                    if (!is_array($group)) {
                        continue;
                    }
                    // Check if this group contains our action with a callable callback
                    if (isset($group[$actionName]['callback']) && is_callable($group[$actionName]['callback'])) {
                        $callback = $group[$actionName]['callback'];
                        $callbackConfig = $group[$actionName];
                        break;
                    }
                }
            }

            if (!$callback) {
                throw new InvalidArgumentException("No callback found for action: {$actionName}");
            }

            // Re-fetch the row server-side within the where() scope
            $rows = $this->fetchScopedRows([$rowId]);
            if (!isset($rows[$rowId])) {
                throw new InvalidArgumentException('Record not found');
            }
            $rowData = $rows[$rowId];

            // Execute the callback with row ID and full row data
            $result = call_user_func(
                $callback,
                $rowId,
                $rowData,
                $this->dataTable->getDatabase(),
                $this->dataTable->getBaseTableName()
            );

            $success = $result !== false;
            $message = $success ?
                ($callbackConfig['success_message'] ?? 'Action completed successfully') : ($callbackConfig['error_message'] ?? 'Action failed');

            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $message
            ]);
            exit;
        }

        /**
         * Handle aggregation data requests for footer calculations
         *
         * Computes SUM and/or AVG for configured columns across the full
         * (filtered) recordset. Returns JSON with aggregation results.
         *
         * @return void Outputs JSON and exits
         */
        private function handleFetchAggregations(): void
        {
            $search = $this->sanitizeSearchInput($_GET['search'] ?? '');
            if (mb_strlen(trim((string) ($_GET['search'] ?? ''))) < $this->dataTable->getMinSearchLength()) {
                $search = '';
            }
            $searchColumn = $this->sanitizeColumnName($_GET['search_column'] ?? '');
            $filtersJson = $this->sanitizeJsonInput($_GET['filters'] ?? '[]');

            $aggregations = $this->dataTable->getFooterAggregations();
            $calculatedColumns = $this->dataTable->getCalculatedColumns();

            if (empty($aggregations)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'aggregations' => []]);
                exit;
            }

            $selectParts = [];
            foreach ($aggregations as $column => $config) {
                $type = $config['type'];

                // Determine SQL expression: use calculated column expression or raw column
                $sqlExpr = $column;
                if (isset($calculatedColumns[$column])) {
                    $sqlExpr = $calculatedColumns[$column]['expression'];
                } elseif (strpos($column, '.') === false) {
                    $sqlExpr = "`{$column}`";
                }

                if ($type === 'sum' || $type === 'both') {
                    $selectParts[] = "SUM({$sqlExpr}) AS `{$column}_sum`";
                }
                if ($type === 'avg' || $type === 'both') {
                    $selectParts[] = "AVG({$sqlExpr}) AS `{$column}_avg`";
                }
            }

            $tableName = $this->dataTable->getTableName();
            if (strpos($tableName, ' ') !== false) {
                $sql = "SELECT " . implode(', ', $selectParts) . " FROM {$tableName}";
            } else {
                $sql = "SELECT " . implode(', ', $selectParts) . " FROM `{$tableName}`";
            }

            foreach ($this->dataTable->getJoins() as $join) {
                $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
            }

            $params = [];
            $whereClause = $this->buildWhereClause($this->dataTable->getWhereConditions(), $params);
            $hasWhere = !empty($whereClause);

            if ($hasWhere) {
                $sql .= $whereClause;
            }

            if (!empty($search)) {
                $searchConditions = [];
                foreach ($this->dataTable->getColumns() as $col => $label) {
                    if (!$this->isSearchableColumn((string) $col)) {
                        continue;
                    }
                    $sc = $col;
                    if (stripos($col, ' AS ') !== false) {
                        $parts = explode(' AS ', $col);
                        $sc = trim($parts[0]);
                    }
                    if (strpos($sc, '.') !== false) {
                        $searchConditions[] = "{$sc} LIKE ? ESCAPE '!'";
                    } else {
                        $searchConditions[] = "`{$sc}` LIKE ? ESCAPE '!'";
                    }
                    $params[] = "%{$search}%";
                }
                if (!empty($searchConditions)) {
                    $sql .= ($hasWhere ? ' AND ' : ' WHERE ') . '(' . implode(' OR ', $searchConditions) . ')';
                }
            }

            // Apply filters to aggregation query, consistent with data query
            $filterClause = $this->buildFilterClause($filtersJson, $params);
            if (!empty($filterClause)) {
                $sql .= ($hasWhere || !empty($searchConditions)) ? ' AND ' : ' WHERE ';
                $sql .= $filterClause;
            }

            // Handle GROUP BY - must wrap as subquery for aggregating over grouped results
            $groupBy = $this->dataTable->getGroupBy();
            if (!empty($groupBy)) {
                $groupExpr = strpos($groupBy, '.') !== false ? $groupBy : "`{$groupBy}`";

                // Build inner query that produces per-group values
                $innerSelectParts = [];
                foreach ($aggregations as $column => $config) {
                    if (isset($calculatedColumns[$column])) {
                        $innerSelectParts[] = $calculatedColumns[$column]['expression'] . " AS `{$column}`";
                    } elseif (strpos($column, '.') === false) {
                        $innerSelectParts[] = "`{$column}`";
                    } else {
                        $innerSelectParts[] = $column;
                    }
                }

                $innerSql = "SELECT " . implode(', ', $innerSelectParts) . " FROM " . (strpos($this->dataTable->getTableName(), ' ') !== false ? $this->dataTable->getTableName() : "`{$this->dataTable->getTableName()}`");

                foreach ($this->dataTable->getJoins() as $join) {
                    $innerSql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
                }

                // Re-apply WHERE and search conditions
                $innerParams = [];
                $innerWhere = $this->buildWhereClause($this->dataTable->getWhereConditions(), $innerParams);
                if (!empty($innerWhere)) {
                    $innerSql .= $innerWhere;
                }

                if (!empty($search)) {
                    $innerSearchConditions = [];
                    foreach ($this->dataTable->getColumns() as $col => $label) {
                        $sc = $col;
                        if (stripos($col, ' AS ') !== false) {
                            $parts = explode(' AS ', $col);
                            $sc = trim($parts[0]);
                        }
                        if (strpos($sc, '.') !== false) {
                            $innerSearchConditions[] = "{$sc} LIKE ? ESCAPE '!'";
                        } else {
                            $innerSearchConditions[] = "`{$sc}` LIKE ? ESCAPE '!'";
                        }
                        $innerParams[] = "%{$search}%";
                    }
                    if (!empty($innerSearchConditions)) {
                        $innerSql .= (!empty($innerWhere) ? ' AND ' : ' WHERE ') . '(' . implode(' OR ', $innerSearchConditions) . ')';
                    }
                }

                // Apply filters to inner grouped subquery
                $innerFilterClause = $this->buildFilterClause($filtersJson, $innerParams);
                if (!empty($innerFilterClause)) {
                    $innerSql .= (!empty($innerWhere) || !empty($innerSearchConditions)) ? ' AND ' : ' WHERE ';
                    $innerSql .= $innerFilterClause;
                }

                $innerSql .= " GROUP BY {$groupExpr}";

                // Outer query aggregates the per-group values
                $outerSelectParts = [];
                foreach ($aggregations as $column => $config) {
                    $type = $config['type'];
                    if ($type === 'sum' || $type === 'both') {
                        $outerSelectParts[] = "SUM(`{$column}`) AS `{$column}_sum`";
                    }
                    if ($type === 'avg' || $type === 'both') {
                        $outerSelectParts[] = "AVG(`{$column}`) AS `{$column}_avg`";
                    }
                }

                $sql = "SELECT " . implode(', ', $outerSelectParts) . " FROM ({$innerSql}) AS grouped";
                $params = $innerParams;
            }

            $query = $this->dataTable->getDatabase()->query($sql);
            if (!empty($params)) {
                $query->bind($params);
            }
            $result = $query->single()->fetch();

            $output = [];
            if ($result) {
                foreach ($aggregations as $column => $config) {
                    $entry = ['column' => $column];
                    $sumKey = "{$column}_sum";
                    $avgKey = "{$column}_avg";
                    if (isset($result->$sumKey)) {
                        $entry['sum'] = (float) $result->$sumKey;
                    }
                    if (isset($result->$avgKey)) {
                        $entry['avg'] = (float) $result->$avgKey;
                    }
                    $output[$column] = $entry;
                }
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'aggregations' => $output]);
            exit;
        }

        /**
         * Get unqualified primary key column name for base table operations
         *
         * Extracts the column name from a potentially qualified primary key field.
         * If the primary key is qualified (e.g., 'users.id'), returns just the
         * column name (e.g., 'id'). Used for base table operations like INSERT,
         * UPDATE, and DELETE where table aliases are not used.
         *
         * @return string Unqualified primary key column name
         * @since  1.0.0
         */
        private function getUnqualifiedPrimaryKey(): string
        {
            $primaryKey = $this->dataTable->getPrimaryKey();

            // If qualified (s.id), extract just the column name (id)
            if (strpos($primaryKey, '.') !== false) {
                return explode('.', $primaryKey)[1];
            }
            return $primaryKey;
        }

        /**
         * Sanitize and validate form data array
         *
         * @param  array $data Raw form data
         * @return array Sanitized form data
         */
        private function sanitizeFormData(array $data): array
        {
            $sanitized = [];
            foreach ($data as $key => $value) {
                if ($key !== 'action') { // Skip action parameter
                    $sanitized[$this->sanitizeInput($key)] = $value;
                }
            }
            return $sanitized;
        }

        /**
         * Sanitize individual value based on type
         *
         * @param  mixed $value Value to sanitize
         * @return mixed Sanitized value
         */
        private function sanitizeValue($value)
        {
            if (is_string($value)) {
                return trim($value);
            }
            return $value;
        }

        /**
         * Validate and sanitize search input
         *
         * @param  string $input Raw search input
         * @return string Sanitized search input
         */
        private function sanitizeSearchInput(string $input): string
        {
            return $this->escapeLike(trim($input));
        }

        /**
         * Escape LIKE wildcards using '!' as the escape character
         *
         * Pair with `LIKE ? ESCAPE '!'` in SQL.
         *
         * @param  string $value Raw value
         * @return string Value with !, % and _ escaped
         */
        private function escapeLike(string $value): string
        {
            return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
        }

        /**
         * Sanitize column name input
         *
         * @param  string $column Raw column name
         * @return string Sanitized column name
         */
        private function sanitizeColumnName(string $column): string
        {
            return preg_replace('/[^a-zA-Z0-9_\.]/', '', $column);
        }

        /**
         * Validate a search column against the configured columns
         *
         * Only plain (non-aliased) configured column keys are searchable by name.
         *
         * @param  mixed $column Raw column name
         * @return string Configured column name, or empty string for global search
         */
        private function validateSearchColumn(mixed $column): string
        {
            if (!is_string($column) || $column === '' || $column === 'all') {
                return '';
            }

            foreach (array_keys($this->dataTable->getColumns()) as $configured) {
                if (stripos((string) $configured, ' AS ') === false && $configured === $column) {
                    return $column;
                }
            }

            return '';
        }

        /**
         * Check whether a configured column is included in global search
         *
         * @param  string $column Column key (may be "expr AS alias")
         * @return bool True if searchable
         */
        private function isSearchableColumn(string $column): bool
        {
            $searchable = $this->dataTable->getSearchableColumns();
            if (empty($searchable)) {
                return true;
            }

            // Match the full key or its alias name
            if (in_array($column, $searchable, true)) {
                return true;
            }
            if (preg_match('/\s+AS\s+(.+)$/i', $column, $matches)) {
                return in_array(trim($matches[1], '`\'" '), $searchable, true);
            }

            return false;
        }

        /**
         * Sanitize sort direction input
         *
         * @param  string $direction Raw sort direction
         * @return string Valid sort direction (ASC or DESC)
         */
        private function sanitizeSortDirection(string $direction): string
        {
            return strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        }

        /**
         * Sanitize general input string
         *
         * @param  string $input Raw input
         * @return string Sanitized input
         */
        private function sanitizeInput(string $input): string
        {
            return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', trim($input));
        }

        /**
         * Validate and sanitize a JSON filter input string
         *
         * Decodes, validates structure, whitelists operators, and sanitizes
         * field names and values before re-encoding for safe downstream use.
         *
         * @param  mixed $input Raw input value
         * @return string Sanitized JSON string
         */
        private function sanitizeJsonInput(mixed $input): string
        {
            if (!is_string($input) || strlen($input) > 10000) {
                return '[]';
            }

            $decoded = json_decode($input, true);
            if (!is_array($decoded)) {
                return '[]';
            }

            $allowedOperators = ['=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'REGEXP'];
            $sanitized = [];

            // Only configured filter fields, each with its configured operator
            $configuredOperators = [];
            foreach ($this->dataTable->getFilterConfig() as $configField => $config) {
                $configuredOperators[(string) $configField] = strtoupper(trim(is_string($config) ? $config : (string) ($config['operator'] ?? '=')));
            }

            foreach ($decoded as $filter) {
                // Must be an array with required keys
                if (!is_array($filter) || !isset($filter['field'], $filter['operator'])) {
                    continue;
                }

                // Field names: alphanumeric, underscore, dot only (supports table.column)
                $field = preg_replace('/[^a-zA-Z0-9_\.]/', '', $filter['field']);
                if (empty($field)) {
                    continue;
                }

                // Operator must be whitelisted and match the field's configured operator
                $operator = strtoupper(trim($filter['operator']));
                if (!in_array($operator, $allowedOperators, true) || !isset($configuredOperators[$field]) || $configuredOperators[$field] !== $operator) {
                    continue;
                }

                // Sanitize value - trim but preserve content for SQL binding
                $value   = isset($filter['value'])    ? trim((string)$filter['value'])    : '';
                $valueTo = isset($filter['value_to']) ? trim((string)$filter['value_to']) : '';

                $sanitized[] = [
                    'field'    => $field,
                    'operator' => $operator,
                    'value'    => $value,
                    'value_to' => $valueTo,
                ];
            }

            return json_encode($sanitized);
        }

        /**
         * Validate integer input with bounds
         *
         * @param  mixed $input Input to validate
         * @param  int   $min   Minimum allowed value
         * @param  int   $max   Maximum allowed value
         * @return int   Validated integer
         */
        private function validateInteger($input, int $min = 1, int $max = PHP_INT_MAX): int
        {
            $value = filter_var($input, FILTER_VALIDATE_INT);
            if ($value === false || $value < $min || $value > $max) {
                return $min;
            }
            return $value;
        }

        /**
         * Validate array of IDs
         *
         * @param  string $jsonIds JSON string of IDs
         * @return array  Validated array of integer IDs
         * @throws InvalidArgumentException If more than 1000 IDs are submitted
         */
        private function validateIdArray(string $jsonIds): array
        {
            $ids = json_decode($jsonIds, true);
            if (!is_array($ids)) {
                return [];
            }

            // Cap the number of IDs per request
            if (count($ids) > 1000) {
                throw new InvalidArgumentException('Too many records selected (maximum 1000)');
            }

            return array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
                return $id > 0;
            })));
        }

        /**
         * Validate field value against database schema
         *
         * @param  string $fieldName Field name
         * @param  mixed  $value     Value to validate
         * @param  array  $fieldInfo Schema information for field
         * @return mixed  Validated value
         */
        private function validateFieldValue(string $fieldName, $value, array $fieldInfo)
        {
            // Handle NULL values
            if ($value === null || $value === '') {
                if (!$fieldInfo['null']) {
                    throw new InvalidArgumentException("Field {$fieldName} cannot be null");
                }
                return null;
            }

            // Type-specific validation based on detected field type
            $fieldType = $fieldInfo['type'];

            switch ($fieldType) {
                case 'number':
                    if (!is_numeric($value)) {
                        throw new InvalidArgumentException("Field {$fieldName} must be numeric");
                    }
                    return is_float($value) ? (float)$value : (int)$value;

                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        throw new InvalidArgumentException("Field {$fieldName} must be a valid email");
                    }
                    return $value;

                case 'date':
                    if (!$this->isValidDate($value, 'Y-m-d')) {
                        throw new InvalidArgumentException("Field {$fieldName} must be a valid date (Y-m-d)");
                    }
                    return $value;

                case 'datetime-local':
                    if (!$this->isValidDate($value, 'Y-m-d\TH:i')) {
                        throw new InvalidArgumentException("Field {$fieldName} must be a valid datetime");
                    }
                    return $value;

                case 'checkbox':
                case 'boolean':
                    return $value ? 1 : 0;

                default:
                    // Just return the trimmed value, no HTML encoding
                    return is_string($value) ? trim($value) : $value;
            }
        }

        /**
         * Validate date format
         *
         * @param  string $date   Date string
         * @param  string $format Expected format
         * @return bool   True if valid date
         */
        private function isValidDate(string $date, string $format): bool
        {
            $d = \DateTime::createFromFormat($format, $date);
            return $d && $d->format($format) === $date;
        }
        /**
         * Get the allowed field names for a form
         *
         * Returns the unqualified field names configured in the add or edit
         * form (excluding display-only static fields), keyed for intersection.
         *
         * @param  string $form Form name ('add' or 'edit')
         * @return array Allowed field names as keys
         */
        private function getFormFieldWhitelist(string $form): array
        {
            $formConfig = $form === 'edit' ? $this->dataTable->getEditFormConfig() : $this->dataTable->getAddFormConfig();
            $allowed = [];

            foreach ($formConfig['fields'] ?? [] as $field => $config) {
                // static fields are display-only
                if (($config['type'] ?? '') === 'static') {
                    continue;
                }
                $allowed[$this->getUnqualifiedFieldName((string) $field)] = true;
            }

            return $allowed;
        }

        /**
         * Get base table columns pinned by where() equality conditions
         *
         * Collects `=` conditions with scalar values from the plain condition list
         * or AND groups (OR groups can't pin a value) that target the base table.
         *
         * @return array Unqualified column => required value
         */
        private function getScopeEqualityValues(): array
        {
            $conditions = $this->dataTable->getWhereConditions();
            if (empty($conditions)) {
                return [];
            }

            // Figure out the base table alias for qualified field names
            $tableParts = preg_split('/\s+/', trim($this->dataTable->getTableName()));
            $baseAlias = $tableParts[1] ?? $tableParts[0];
            $baseTable = $this->dataTable->getBaseTableName();
            $schema = $this->dataTable->getTableSchema();

            // Normalize to a list of AND groups
            if (isset($conditions[0]) && is_array($conditions[0])) {
                $groups = [$conditions];
            } else {
                $groups = [];
                foreach ($conditions as $operator => $group) {
                    if (is_array($group) && strtoupper((string) $operator) !== 'OR') {
                        $groups[] = isset($group['field']) ? [$group] : $group;
                    }
                }
            }

            $scoped = [];
            foreach ($groups as $group) {
                foreach ($group as $condition) {
                    if (!is_array($condition) || !isset($condition['field'], $condition['comparison']) || !array_key_exists('value', $condition)) {
                        continue;
                    }

                    // Only plain equality with a scalar value pins a column
                    if (trim((string) $condition['comparison']) !== '=' || !is_scalar($condition['value'])) {
                        continue;
                    }

                    // Qualified fields must belong to the base table
                    $field = (string) $condition['field'];
                    if (strpos($field, '.') !== false) {
                        $prefix = explode('.', $field)[0];
                        if ($prefix !== $baseAlias && $prefix !== $baseTable) {
                            continue;
                        }
                    }

                    $column = $this->getUnqualifiedFieldName($field);
                    if (isset($schema[$column])) {
                        $scoped[$column] = $condition['value'];
                    }
                }
            }

            return $scoped;
        }
        /**
         * Fetch rows by primary key within the configured where() scope
         *
         * Uses the same select list, joins, where() conditions and group by as the
         * table query, limited to the given IDs. IDs outside the scope are dropped.
         *
         * @param  array $ids Primary key values
         * @return array Rows as associative arrays keyed by primary key
         */
        private function fetchScopedRows(array $ids): array
        {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            if (empty($ids)) {
                return [];
            }

            // Resolve the primary key expression
            $primaryKey = $this->dataTable->getPrimaryKey();
            $tableName = $this->dataTable->getTableName();
            $tableParts = preg_split('/\s+/', trim($tableName));
            if (strpos($primaryKey, '.') !== false) {
                $pkExpr = $primaryKey;
            } elseif (isset($tableParts[1])) {
                $pkExpr = "{$tableParts[1]}.`{$primaryKey}`";
            } else {
                $pkExpr = "`{$primaryKey}`";
            }

            // Same select list as the table, plus a fixed PK alias for mapping
            $selectFields = $this->getSelectFields();
            $selectFields[] = "{$pkExpr} AS `__kpt_pk`";

            $sql = "SELECT " . implode(', ', $selectFields) . " FROM " . (strpos($tableName, ' ') !== false ? $tableName : "`{$tableName}`");

            foreach ($this->dataTable->getJoins() as $join) {
                $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
            }

            // where() scope plus the requested IDs
            $params = [];
            $whereClause = $this->buildWhereClause($this->dataTable->getWhereConditions(), $params);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql .= (!empty($whereClause) ? $whereClause . ' AND ' : ' WHERE ') . "{$pkExpr} IN ({$placeholders})";
            $params = array_merge($params, $ids);

            $groupBy = $this->dataTable->getGroupBy();
            if (!empty($groupBy)) {
                $sql .= strpos($groupBy, '.') !== false ? " GROUP BY {$groupBy}" : " GROUP BY `{$groupBy}`";
            }

            $rows = $this->dataTable->getDatabase()->query($sql)->bind($params)->fetch();

            // Key by primary key and drop the helper alias
            $mapped = [];
            foreach ($rows ?: [] as $row) {
                $rowArray = (array) $row;
                $key = (int) $rowArray['__kpt_pk'];
                unset($rowArray['__kpt_pk']);
                $mapped[$key] = $rowArray;
            }

            return $mapped;
        }

        /**
         * Build WHERE clause from conditions array
         *
         * @param  array $conditions WHERE conditions array
         * @param  array &$params    Parameters array to append to
         * @param  bool  $useBaseTable Whether to use base table (for UPDATE/DELETE)
         * @return string WHERE clause SQL
         */
        private function buildWhereClause(array $conditions, array &$params, bool $useBaseTable = false): string
        {
            if (empty($conditions)) {
                return '';
            }

            $whereParts = [];

            // Check if this is a simple indexed array of conditions (default AND)
            if (isset($conditions[0]) && is_array($conditions[0])) {
                // Numeric indexed array - treat as AND conditions
                foreach ($conditions as $condition) {
                    if (!isset($condition['field'], $condition['comparison'], $condition['value'])) {
                        continue;
                    }

                    $field = $condition['field'];
                    $comparison = strtoupper(trim($condition['comparison']));
                    $value = $condition['value'];

                    // Strip table alias for UPDATE/DELETE operations
                    if ($useBaseTable && strpos($field, '.') !== false) {
                        $field = $this->getUnqualifiedFieldName($field);
                    }

                    if (strpos($field, '.') !== false) {
                        $fieldSql = $field;
                    } else {
                        $fieldSql = "`{$field}`";
                    }

                    switch ($comparison) {
                        case 'IN':
                        case 'NOT IN':
                            if (is_array($value)) {
                                $placeholders = implode(',', array_fill(0, count($value), '?'));
                                $whereParts[] = "{$fieldSql} {$comparison} ({$placeholders})";
                                $params = array_merge($params, $value);
                            }
                            break;
                        case 'LIKE':
                        case 'NOT LIKE':
                            $whereParts[] = "{$fieldSql} {$comparison} ?";
                            $params[] = $value;
                            break;
                        default:
                            $whereParts[] = "{$fieldSql} {$comparison} ?";
                            $params[] = $value;
                            break;
                    }
                }

                return !empty($whereParts) ? ' WHERE ' . implode(' AND ', $whereParts) : '';
            }

            // Handle operator-based structure (AND/OR groups) - same logic applies
            foreach ($conditions as $operator => $conditionGroup) {
                if (!is_array($conditionGroup)) {
                    continue;
                }

                if (isset($conditionGroup['field'])) {
                    $conditionGroup = [$conditionGroup];
                }

                $groupParts = [];
                foreach ($conditionGroup as $condition) {
                    if (!isset($condition['field'], $condition['comparison'], $condition['value'])) {
                        continue;
                    }

                    $field = $condition['field'];
                    $comparison = strtoupper(trim($condition['comparison']));
                    $value = $condition['value'];

                    // Strip table alias for UPDATE/DELETE operations
                    if ($useBaseTable && strpos($field, '.') !== false) {
                        $field = $this->getUnqualifiedFieldName($field);
                    }

                    if (strpos($field, '.') !== false) {
                        $fieldSql = $field;
                    } else {
                        $fieldSql = "`{$field}`";
                    }

                    switch ($comparison) {
                        case 'IN':
                        case 'NOT IN':
                            if (is_array($value)) {
                                $placeholders = implode(',', array_fill(0, count($value), '?'));
                                $groupParts[] = "{$fieldSql} {$comparison} ({$placeholders})";
                                $params = array_merge($params, $value);
                            }
                            break;
                        case 'LIKE':
                        case 'NOT LIKE':
                            $groupParts[] = "{$fieldSql} {$comparison} ?";
                            $params[] = $value;
                            break;
                        default:
                            $groupParts[] = "{$fieldSql} {$comparison} ?";
                            $params[] = $value;
                            break;
                    }
                }

                if (!empty($groupParts)) {
                    if (strtoupper($operator) === 'OR') {
                        $whereParts[] = '(' . implode(' OR ', $groupParts) . ')';
                    } else {
                        $whereParts[] = '(' . implode(' AND ', $groupParts) . ')';
                    }
                }
            }

            return !empty($whereParts) ? ' WHERE ' . implode(' AND ', $whereParts) : '';
        }

        /**
         * Build SQL conditions from active filter parameters
         *
         * Parses the JSON filter payload and generates WHERE clause fragments.
         * Layered on top of existing where() conditions, never replacing them.
         *
         * @param  string $filtersJson JSON-encoded array of {field, operator, value} objects
         * @param  array  &$params     Parameters array to append bound values to
         * @return string SQL condition string (without WHERE keyword), empty if no filters
         */
        private function buildFilterClause(string $filtersJson, array &$params): string
        {
            $filters = json_decode($filtersJson, true);
            if (empty($filters) || !is_array($filters)) {
                return '';
            }

            $parts = [];

            foreach ($filters as $filter) {
                $field    = $filter['field']    ?? '';
                $operator = strtoupper($filter['operator'] ?? '');
                $value    = $filter['value']    ?? '';
                $valueTo  = $filter['value_to'] ?? ''; // BETWEEN upper bound

                // Skip empty filter values, but allow 0
                if ($value === '' && $valueTo === '') {
                    continue;
                }

                // Build qualified or backtick-wrapped field expression
                $fieldSql = strpos($field, '.') !== false ? $field : "`{$field}`";

                switch ($operator) {
                    case '=':
                    case '!=':
                    case '>':
                    case '>=':
                    case '<':
                    case '<=':
                        $parts[]  = "{$fieldSql} {$operator} ?";
                        $params[] = $value;
                        break;

                    case 'LIKE':
                    case 'NOT LIKE':
                        $parts[]  = "{$fieldSql} {$operator} ? ESCAPE '!'";
                        $params[] = '%' . $this->escapeLike($value) . '%';
                        break;

                    case 'IN':
                    case 'NOT IN':
                        $values = array_filter(array_map('trim', explode(',', $value)));
                        if (empty($values)) {
                            continue 2;
                        }
                        $placeholders = implode(',', array_fill(0, count($values), '?'));
                        $parts[]      = "{$fieldSql} {$operator} ({$placeholders})";
                        $params       = array_merge($params, array_values($values));
                        break;

                    case 'BETWEEN':
                        // Apply single-bound if only one side is filled
                        if ($value !== '' && $valueTo !== '') {
                            $parts[]  = "{$fieldSql} BETWEEN ? AND ?";
                            $params[] = $value;
                            $params[] = $valueTo;
                        } elseif ($value !== '') {
                            $parts[]  = "{$fieldSql} >= ?";
                            $params[] = $value;
                        } elseif ($valueTo !== '') {
                            $parts[]  = "{$fieldSql} <= ?";
                            $params[] = $valueTo;
                        }
                        break;

                    case 'REGEXP':
                        $parts[]  = "{$fieldSql} REGEXP ?";
                        $params[] = $value;
                        break;
                }
            }

            return implode(' AND ', $parts);
        }

        /**
         * Get unqualified column name from potentially qualified field
         *
         * @param  string $field Field name that may be qualified (e.g., 's.u_id')
         * @return string Unqualified column name (e.g., 'u_id')
         */
        private function getUnqualifiedFieldName(string $field): string
        {
            if (strpos($field, '.') !== false) {
                return explode('.', $field)[1];
            }
            return $field;
        }

        /**
         * Handle Select2 options fetch request
         *
         * Processes AJAX requests for Select2 dropdown options including search filtering,
         * parameter substitution from record data, and result limiting. Executes the
         * configured SQL query with ID/Label aliases and returns JSON results.
         *
         * @return void (outputs JSON and exits)
         * @throws InvalidArgumentException If query is missing or invalid
         * @since  1.2.0
         */
        private function handleFetchSelect2Options(): void
        {

            $field = $_POST['field'] ?? '';
            $form = $_POST['form'] ?? '';
            $query = $this->getSelect2Query($field, $form);
            $search = $this->sanitizeSearchInput($_POST['search'] ?? '');
            $maxResults = $this->validateInteger($_POST['max_results'] ?? 50, 0);
            $valueFilter = $_POST['value_filter'] ?? '';
            $recordDataJson = $_POST['record_data'] ?? '{}';
            Logger::debug("Select2 fetch options", [
                'query' => $query,
                'search' => $search,
                'maxResults' => $maxResults,
                'valueFilter' => $valueFilter,
                'recordData' => $recordDataJson
            ]);
            if (empty($query)) {
                throw new InvalidArgumentException('Invalid Select2 field');
            }

            // Parse record data for parameter substitution
            $recordData = json_decode($recordDataJson, true);
            if (!is_array($recordData)) {
                $recordData = [];
            }

            // Swap {field_name} placeholders for bound params from record data
            [$processedQuery, $params] = $this->substituteQueryParameters($query, $recordData);

            // Build WHERE clause for search and value filter
            $whereClauses = [];

            // Add value filter if present (for loading initial selected value)
            if (!empty($valueFilter)) {
                $whereClauses[] = "ID = ?";
                $params[] = $valueFilter;
            }

            // Add search filter if present
            if (!empty($search)) {
                // Extract the original column expression from the query before AS Label
                // Pattern: word/expression AS Label
                if (preg_match('/,\s*([a-zA-Z0-9_\.]+)\s+AS\s+[`\'"]*Label[`\'"]*\s*/i', $processedQuery, $matches)) {
                    $labelColumn = trim($matches[1]);
                } else {
                    $labelColumn = 'Label';
                }

                $whereClauses[] = "{$labelColumn} LIKE ? ESCAPE '!'";
                $params[] = "%{$search}%";
            }

            // Combine base query with WHERE clause
            $finalQuery = $processedQuery;

            if (!empty($whereClauses)) {
                // Check if query already has WHERE clause
                if (stripos($finalQuery, 'WHERE') !== false) {
                    $finalQuery .= ' AND (' . implode(' AND ', $whereClauses) . ')';
                } else {
                    $finalQuery .= ' WHERE ' . implode(' AND ', $whereClauses);
                }
            }

            // Add LIMIT if max_results is specified
            if ($maxResults > 0) {
                $finalQuery .= " LIMIT {$maxResults}";
            }

            try {
                Logger::debug("Select2 executing query", ['sql' => $finalQuery, 'params' => $params]);

                // Execute query using database connection
                $query = $this->dataTable->getDatabase()->query($finalQuery);
                if (!empty($params)) {
                    $query->bind($params);
                }
                $results = $query->fetch();

                // Ensure results is an array
                if (!$results) {
                    $results = [];
                }

                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'results' => $results
                ]);
                exit;
            } catch (\Exception $e) {
                Logger::error("Select2 query failed", [
                    'query' => $finalQuery,
                    'error' => $e->getMessage()
                ]);

                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to fetch options',
                    'results' => []
                ]);
                exit;
            }
        }

        /**
         * Fetch select2 labels for display in table
         *
         * @param  array  $rows   Data rows with IDs
         * @param  string $column Column name
         * @param  string $query  Select2 query
         * @return array  Map of ID => Label
         */
        private function fetchSelect2Labels(array $rows, string $column, string $query): array
        {
            $ids = array_unique(array_column($rows, $column));
            $ids = array_filter($ids); // Remove empty values

            if (empty($ids)) {
                return [];
            }

            // Extract original column from query for WHERE clause
            if (preg_match('/,\s*([a-zA-Z0-9_\.]+)\s+AS\s+[`\'"]*Label[`\'"]*\s*/i', $query, $matches)) {
                $labelColumn = trim($matches[1]);
            } else {
                return [];
            }

            // Build query to fetch labels for all IDs
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $fetchQuery = "{$query} WHERE ID IN ({$placeholders})";

            try {
                $results = $this->dataTable->getDatabase()
                    ->query($fetchQuery)
                    ->bind($ids)
                    ->fetch();

                // Build ID => Label map
                $labelMap = [];
                if ($results) {
                    foreach ($results as $row) {
                        $labelMap[$row->ID] = $row->Label;
                    }
                }

                return $labelMap;
            } catch (\Exception $e) {
                Logger::error("Failed to fetch select2 labels", ['error' => $e->getMessage()]);
                return [];
            }
        }

        /**
         * Substitute query parameters from record data
         *
         * Replaces {field_name} placeholders in the query with bound `?`
         * parameters, collecting their values from the provided record data
         * in placeholder order. Missing or non-scalar values bind as NULL.
         *
         * @param  string $query      SQL query with {field_name} placeholders
         * @param  array  $recordData Associative array of field => value pairs
         * @return array  [processed query, ordered bind params]
         * @since  1.2.0
         */
        private function substituteQueryParameters(string $query, array $recordData): array
        {
            $params = [];

            // Replace each placeholder occurrence with a bound param
            $processedQuery = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function (array $matches) use ($recordData, &$params): string {
                $value = $recordData[$matches[1]] ?? null;
                $params[] = is_scalar($value) ? $value : null;
                return '?';
            }, $query);

            return [$processedQuery, $params];
        }

        /**
         * Resolve the configured Select2 query for a field
         *
         * Looks the query up server-side from the add/edit form field config,
         * falling back to the table schema, so the client never supplies SQL.
         *
         * @param  string $field Field name (may be qualified, e.g. 's.u_id')
         * @param  string $form  Form the field belongs to ('add', 'edit', or empty for inline)
         * @return string Configured query, or empty string if the field isn't a select2
         * @since  1.2.0
         */
        private function getSelect2Query(string $field, string $form): string
        {
            // Field names are identifiers only
            if (!preg_match('/^[A-Za-z0-9_.]+$/', $field)) {
                return '';
            }

            // Check the matching form config first
            $formConfig = match ($form) {
                'add' => $this->dataTable->getAddFormConfig(),
                'edit' => $this->dataTable->getEditFormConfig(),
                default => [],
            };
            $fieldConfig = $formConfig['fields'][$field] ?? [];
            if (($fieldConfig['type'] ?? '') === 'select2' && !empty($fieldConfig['query'])) {
                return $fieldConfig['query'];
            }

            // Fall back to the table schema
            $schemaInfo = $this->dataTable->getTableSchema()[$this->getUnqualifiedFieldName($field)] ?? [];
            if (($schemaInfo['override_type'] ?? '') === 'select2' && !empty($schemaInfo['select2_query'])) {
                return $schemaInfo['select2_query'];
            }

            return '';
        }
    }
}
