<?php

/**
 * Static Functions
 * 
 * This is our primary static object class
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 * 
 */

// define the primary app path if not already defined
defined('KPTV_PATH') || die('Direct Access is not allowed!');

// make sure the class does not already exist
if (! class_exists('KPTV_Static')) {

    /** 
     * Class Static
     * 
     * OTV Static Objects
     * 
     * @since 8.4
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     * 
     */
    class KPTV_Static
    {

        /**
         * Just something to specify the types of guides
         * This is more for me than anyone else, that way I can 
         * easily figure out which guide to use in Emby
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         */
        public static function guide_types()
        {
            return [
                '0' => 'OTA',
                '1' => 'XFinity',
                '2' => 'Spectrum',
                '3' => 'DirectTV',
                '4' => 'Dish',
                '5' => 'AT&T',
                '6' => 'Philo',
                '7' => 'Sling',
                '8' => 'YouTube',
                '9' => 'Fubo',
                '10' => 'Hulu',
                '11' => 'Vidgo',
                '12' => 'Frndly',
                '13' => 'Samsung',
                '14' => 'Pluto',
                '15' => 'Xumo',
                '16' => 'Plex',
                '17' => 'Glorystar',
                '18' => 'Amazon',
                '19' => 'Apple',
                '20' => 'Peacock',
                '22' => 'Freevee',
                '23' => 'Tubi',
                '24' => 'Crackle',
                '25' => 'DistroTV',
                '26' => 'Redbox',
                '27' => 'Roku',
                '28' => 'Vizio',
                '29' => 'LG',

                '99' => 'Other',
            ];
        }

        /** 
         * view_configs
         * 
         * Just something to centralize the view configs
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $which The config we need
         * @param array $extras Optional named parameters (userId, action, etc.)
         * 
         * @return object This method returns an object representing the configuration needed
         * 
         */
        public static function view_configs(string $which, ...$extras): object
        {

            // if we have extras extract them into the variables
            extract($extras);
            $userForExport = $extras['userForExport'] ?? '';
            $userId = $extras['userId'] ?? 0;

            // just return the matching config we need to present
            return (object) match ($which) {
                'filters' => [
                    'bulk' => [],
                    'row' => [],
                    'form' => [
                        'sf_active' => [
                            'label' => 'Active',
                            'type' => 'boolean',
                            'required' => true,
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                        ],
                        'sf_type_id' => [
                            'label' => 'Filter Type',
                            'type' => 'select',
                            'required' => true,
                            'options' => [
                                '0' => 'Include Name (regex)',
                                '1' => 'Exclude Name',
                                '2' => 'Exclude Name (regex)',
                                '3' => 'Exclude Stream (regex)',
                                '4' => 'Exclude Group (regex)',
                            ],
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                        ],
                        'sf_filter' => [
                            'type' => 'text',
                            'label' => 'Filter',
                            'class' => 'uk-width-1-1',
                        ],
                        'u_id' => [
                            'type' => 'hidden',
                            'value' => $userId,
                            'required' => true
                        ],

                    ]
                ],
                'providers' => [
                    'bulk' => [],
                    'row' => [
                        [
                            'html1' => [
                                'location' => 'after',
                                'content' => '<br class="action-nl" />'
                            ],
                            'html2' => [
                                'location' => 'before',
                                'content' => '<strong>XC: </strong>'
                            ],
                            'exportlivexc' => [
                                'icon' => 'link',
                                'title' => 'Copy Domain',
                                'class' => 'copy-link',
                                'href' => KPTV_XC_URI,
                            ],
                            'exportseriesxc' => [
                                'icon' => 'users',
                                'title' => 'Copy Username',
                                'class' => 'copy-link',
                                'href' => '{id}',
                            ],
                            'exportvodxc' => [
                                'icon' => 'server',
                                'title' => 'Copy Password',
                                'class' => 'copy-link',
                                'href' => $userForExport,
                            ],
                        ],
                        [
                            'html1' => [
                                'location' => 'before',
                                'content' => '<strong>M3U: </strong>'
                            ],
                            'html2' => [
                                'location' => 'after',
                                'content' => '<br class="action-nl" />'
                            ],
                            'exportlive' => [
                                'icon' => 'tv',
                                'title' => 'Export Live M3U',
                                'class' => 'copy-link',
                                'href' => '' . KPTV_URI . 'playlist/' . $userForExport . '/{id}/live',
                            ],
                            'exportseries' => [
                                'icon' => 'album',
                                'title' => 'Export Series M3U',
                                'class' => 'copy-link',
                                'href' => '' . KPTV_URI . 'playlist/' . $userForExport . '/{id}/series',
                            ],
                            'exportvod' => [
                                'icon' => 'video-camera',
                                'title' => 'Export VOD M3U',
                                'class' => 'copy-link',
                                'href' => '' . KPTV_URI . 'playlist/' . $userForExport . '/{id}/vod',
                            ],
                            'exportall' => [
                                'icon' => 'thumbnails',
                                'title' => 'Export All M3U',
                                'class' => 'copy-link',
                                'href' => '' . KPTV_URI . 'playlist/' . $userForExport . '/{id}/all',
                            ],
                        ],
                        [
                            'delprovider' => [
                                'icon' => 'trash',
                                'title' => 'Delete this Provider<br />(also delete\'s all associated streams)',
                                'success_message' => 'Provider and all it\'s streams have been deleted.',
                                'error_message' => 'Failed to delete the provider.',
                                'confirm' => 'Are you want to remove this provider and all it\'s streams?',
                                'callback' => function ($rowId, $rowData, $db, $tableName) {

                                    // make sure we have a row ID
                                    if (empty($rowId)) return false;

                                    // Delete all streams for the provider first
                                    $db->query("DELETE FROM `kptv_streams` WHERE `p_id` = ?")
                                        ->bind($rowId)
                                        ->execute();
                                    $tableName = KPTV::validateTableName($tableName);
                                    // now delete the provider
                                    return $db->query("DELETE FROM {$tableName} WHERE id = ?")
                                        ->bind($rowId)
                                        ->execute() !== false;
                                },
                            ],
                        ],
                    ],
                    'form' => [

                        // defaults
                        'u_id' => [
                            'type' => 'hidden',
                            'value' => $userId,
                            'required' => true,
                            'tab' => 'details',
                        ],
                        'sp_name' => [
                            'type' => 'text',
                            'required' => true,
                            'label' => 'Name',
                            'class' => 'uk-width-1-1',
                            'tab' => 'details',
                        ],
                        'sp_type' => [
                            'type' => 'select',
                            'required' => true,
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                            'label' => 'Type',
                            'options' => [
                                0 => 'XC API',
                                1 => 'M3U',
                            ],
                            'tab' => 'details',
                        ],
                        'sp_cnx_limit' => [
                            'type' => 'text',
                            'required' => true,
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                            'label' => 'Connections',
                            'default' => 1,
                            'tab' => 'details',
                        ],

                        // subscription details
                        'sp_sub_length' => [
                            'type' => 'number',
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                            'label' => 'Sub. Length(m)',
                            'tab' => 'sub. info',
                        ],
                        'sp_expires' => [
                            'type' => 'datepicker',
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                            'label' => 'Expires',
                            'formatter' => 'YYYY-MM-DD',
                            'tab' => 'sub. info',
                        ],
                        'sp_contact' => [
                            'type' => 'text',
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                            'label' => 'Contact Info.',
                            'tab' => 'sub. info',
                        ],
                        'sp_cost' => [
                            'type' => 'text',
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                            'label' => 'Cost',
                            'tab' => 'sub. info',
                        ],

                        // credentials
                        'sp_domain' => [
                            'type' => 'url',
                            'required' => true,
                            'label' => 'Domain / URL',
                            'class' => 'uk-width-1-1',
                            'tab' => 'credentials',
                        ],
                        'sp_username' => [
                            'type' => 'text',
                            'required' => false,
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                            'label' => 'XC Username',
                            'tab' => 'credentials',
                        ],
                        'sp_password' => [
                            'type' => 'text',
                            'required' => false,
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                            'label' => 'XC Password',
                            'tab' => 'credentials',
                        ],

                        // extra info
                        'sp_stream_type' => [
                            'type' => 'select',
                            'required' => false,
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                            'label' => 'Stream Type',
                            'options' => [
                                0 => 'MPEGTS',
                                1 => 'HLS',
                            ],
                            'tab' => 'extra info.',
                        ],
                        'sp_should_filter' => [
                            'label' => 'Should Filter?',
                            'type' => 'boolean',
                            'required' => true,
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                            'tab' => 'extra info.',
                        ],
                        'sp_priority' => [
                            'type' => 'number',
                            'required' => false,
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                            'label' => 'Order Priority',
                            'default' => 1,
                            'tab' => 'extra info.',
                        ],
                        'sp_refresh_period' => [
                            'type' => 'number',
                            'required' => false,
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                            'label' => 'Refresh Period',
                            'default' => 1,
                            'tab' => 'extra info.',
                        ],

                    ]
                ],
                'streams' => [
                    'bulk' => [
                        'live' => [
                            'livestreamact' => [
                                'label' => '(De)Activate Streams',
                                'icon' => 'crosshairs',
                                'callback' => function ($selectedIds, $database, $tableName) {

                                    // make sure we have records selected
                                    if (empty($selectedIds)) return false;
                                    $tableName = KPTV::validateTableName($tableName);
                                    // setup the placeholders and the query
                                    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                                    $sql = "UPDATE {$tableName} SET s_active = NOT s_active WHERE id IN ({$placeholders})";

                                    // return the execution
                                    return $database->query($sql)
                                        ->bind($selectedIds)
                                        ->execute() !== false;
                                },
                                'confirm' => 'Are you sure you want to (de)activate these streams?',
                                'success_message' => 'Records (de)activated',
                                'error_message' => 'Failed to (de)activate'
                            ],
                            'movetoseries' => [
                                'label' => 'Move to Series Streams',
                                'icon' => 'album',
                                'confirm' => 'Move the selected records to series streams?',
                                'callback' => function ($selectedIds, $database, $tableName) {

                                    // make sure we have selected items
                                    if (empty($selectedIds)) return false;

                                    // Track success/failure
                                    $successCount = 0;

                                    try {
                                        // Process all selected IDs
                                        foreach ($selectedIds as $id) {
                                            $result = KPTV::moveToType($database, $id, 5);
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }

                                        // return
                                        return $successCount > 0;
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to series streams successfully',
                                'error_message' => 'Failed to move some or all records to series streams'
                            ],
                            'movetovod' => [
                                'label' => 'Move to VOD Streams',
                                'icon' => 'video-camera',
                                'confirm' => 'Move the selected records to vod streams?',
                                'callback' => function ($selectedIds, $database, $tableName) {

                                    // make sure we have selected items
                                    if (empty($selectedIds)) return false;

                                    // Track success/failure
                                    $successCount = 0;

                                    try {
                                        // Process all selected IDs
                                        foreach ($selectedIds as $id) {
                                            $result = KPTV::moveToType($database, $id, 4);
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }

                                        // return
                                        return $successCount > 0;
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to vod streams successfully',
                                'error_message' => 'Failed to move some or all records to vod streams'
                            ],
                            'movetoother' => [
                                'label' => 'Move to Other Streams',
                                'icon' => 'nut',
                                'confirm' => 'Move the selected records to other streams?',
                                'callback' => function ($selectedIds, $database, $tableName) {
                                    // make sure we have selected items
                                    if (empty($selectedIds)) return false;
                                    $successCount = 0;
                                    try {
                                        // Process all selected IDs
                                        foreach ($selectedIds as $id) {
                                            $result = KPTV::moveToType($database, $id, 99);
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }

                                        // return
                                        return $successCount > 0;
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to other streams successfully',
                                'error_message' => 'Failed to move some or all records to other streams'
                            ],
                        ],
                        'series' => [
                            'seriesstreamact' => [
                                'label' => '(De)Activate Streams',
                                'icon' => 'crosshairs',
                                'callback' => function ($selectedIds, $database, $tableName) {

                                    // make sure we have records selected
                                    if (empty($selectedIds)) return false;
                                    $tableName = KPTV::validateTableName($tableName);
                                    // setup the placeholders and the query
                                    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                                    $sql = "UPDATE {$tableName} SET s_active = NOT s_active WHERE id IN ({$placeholders})";

                                    // return the execution
                                    return $database->query($sql)
                                        ->bind($selectedIds)
                                        ->execute() !== false;
                                },
                                'confirm' => 'Are you sure you want to (de)activate these streams?',
                                'success_message' => 'Records (de)activated',
                                'error_message' => 'Failed to (de)activate'
                            ],
                            'movetolive' => [
                                'label' => 'Move to Live Streams',
                                'icon' => 'tv',
                                'confirm' => 'Move the selected records to live streams?',
                                'callback' => function ($selectedIds, $database, $tableName) {

                                    // make sure we have selected items
                                    if (empty($selectedIds)) return false;

                                    $successCount = 0;
                                    try {
                                        // Process all selected IDs
                                        foreach ($selectedIds as $id) {
                                            $result = KPTV::moveToType($database, $id, 0);
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }

                                        // return
                                        return $successCount > 0;
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to live streams successfully',
                                'error_message' => 'Failed to move some or all records to live streams'
                            ],
                            'movetovod' => [
                                'label' => 'Move to VOD Streams',
                                'icon' => 'video-camera',
                                'confirm' => 'Move the selected records to vod streams?',
                                'callback' => function ($selectedIds, $database, $tableName) {

                                    // make sure we have selected items
                                    if (empty($selectedIds)) return false;

                                    $successCount = 0;
                                    try {
                                        // Process all selected IDs
                                        foreach ($selectedIds as $id) {
                                            $result = KPTV::moveToType($database, $id, 4);
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }

                                        // return
                                        return $successCount > 0;
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to vod streams successfully',
                                'error_message' => 'Failed to move some or all records to vod streams'
                            ],
                            'movetoother' => [
                                'label' => 'Move to Other Streams',
                                'icon' => 'nut',
                                'confirm' => 'Move the selected records to other streams?',
                                'callback' => function ($selectedIds, $database, $tableName) {
                                    // make sure we have selected items
                                    if (empty($selectedIds)) return false;
                                    $successCount = 0;
                                    try {
                                        // Process all selected IDs
                                        foreach ($selectedIds as $id) {
                                            $result = KPTV::moveToType($database, $id, 99);
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }

                                        // return
                                        return $successCount > 0;
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to other streams successfully',
                                'error_message' => 'Failed to move some or all records to other streams'
                            ],
                        ],
                        'vod' => [

                            'movetolive' => [
                                'label' => 'Move to Live Streams',
                                'icon' => 'tv',
                                'confirm' => 'Move the selected records to live streams?',
                                'callback' => function ($selectedIds, $database, $tableName) {

                                    // make sure we have selected items
                                    if (empty($selectedIds)) return false;

                                    $successCount = 0;
                                    try {
                                        // Process all selected IDs
                                        foreach ($selectedIds as $id) {
                                            $result = KPTV::moveToType($database, $id, 0);
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }

                                        // return
                                        return $successCount > 0;
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to live streams successfully',
                                'error_message' => 'Failed to move some or all records to live streams'
                            ],
                            'movetoseries' => [
                                'label' => 'Move to Series Streams',
                                'icon' => 'album',
                                'confirm' => 'Move the selected records to series streams?',
                                'callback' => function ($selectedIds, $database, $tableName) {

                                    // make sure we have selected items
                                    if (empty($selectedIds)) return false;

                                    $successCount = 0;
                                    try {
                                        // Process all selected IDs
                                        foreach ($selectedIds as $id) {
                                            $result = KPTV::moveToType($database, $id, 5);
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }

                                        // return
                                        return $successCount > 0;
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to series streams successfully',
                                'error_message' => 'Failed to move some or all records to series streams'
                            ],
                            'movetoother' => [
                                'label' => 'Move to Other Streams',
                                'icon' => 'nut',
                                'confirm' => 'Move the selected records to other streams?',
                                'callback' => function ($selectedIds, $database, $tableName) {
                                    // make sure we have selected items
                                    if (empty($selectedIds)) return false;
                                    $successCount = 0;
                                    try {
                                        // Process all selected IDs
                                        foreach ($selectedIds as $id) {
                                            $result = KPTV::moveToType($database, $id, 99);
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }

                                        // return
                                        return $successCount > 0;
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to other streams successfully',
                                'error_message' => 'Failed to move some or all records to other streams'
                            ],
                        ],
                        'other' => [
                            'movetolive' => [
                                'label' => 'Move to Live Streams',
                                'icon' => 'tv',
                                'confirm' => 'Move the selected records to live streams?',
                                'callback' => function ($selectedIds, $database, $tableName) {

                                    // make sure we have selected items
                                    if (empty($selectedIds)) return false;

                                    $successCount = 0;
                                    try {
                                        // Process all selected IDs
                                        foreach ($selectedIds as $id) {
                                            $result = KPTV::moveToType($database, $id, 0);
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }

                                        // return
                                        return $successCount > 0;
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to live streams successfully',
                                'error_message' => 'Failed to move some or all records to live streams'
                            ],
                            'movetoseries' => [
                                'label' => 'Move to Series Streams',
                                'icon' => 'album',
                                'confirm' => 'Move the selected records to series streams?',
                                'callback' => function ($selectedIds, $database, $tableName) {

                                    // make sure we have selected items
                                    if (empty($selectedIds)) return false;
                                    $successCount = 0;
                                    try {
                                        // Process all selected IDs
                                        foreach ($selectedIds as $id) {
                                            $result = KPTV::moveToType($database, $id, 5);
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }

                                        // return
                                        return $successCount > 0;
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to series streams successfully',
                                'error_message' => 'Failed to move some or all records to series streams'
                            ],
                            'movetovod' => [
                                'label' => 'Move to VOD Streams',
                                'icon' => 'video-camera',
                                'confirm' => 'Move the selected records to vod streams?',
                                'callback' => function ($selectedIds, $database, $tableName) {

                                    // make sure we have selected items
                                    if (empty($selectedIds)) return false;
                                    $successCount = 0;
                                    try {
                                        // Process all selected IDs
                                        foreach ($selectedIds as $id) {
                                            $result = KPTV::moveToType($database, $id, 4);
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }
                                        // return
                                        return $successCount > 0;
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to vod streams successfully',
                                'error_message' => 'Failed to move some or all records to vod streams'
                            ],
                        ],
                    ],
                    'row' => [
                        'live' => [
                            'html' => [
                                'location' => 'both',
                                'content' => '<br class="action-nl" />'
                            ],
                            'moveseries' => [
                                'icon' => 'album',
                                'title' => 'Move This Stream to Series Streams',
                                'callback' => function ($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType($database, $rowId, 5);
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                            'movevod' => [
                                'icon' => 'video-camera',
                                'title' => 'Move This Stream to VOD Streams',
                                'callback' => function ($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType($database, $rowId, 4);
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                            'moveother' => [
                                'icon' => 'nut',
                                'title' => 'Move This Stream to Other Streams',
                                'callback' => function ($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType($database, $rowId, 99);
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                        ],
                        'series' => [
                            'html' => [
                                'location' => 'both',
                                'content' => '<br class="action-nl" />'
                            ],
                            'movelive' => [
                                'icon' => 'tv',
                                'title' => 'Move This Stream to Live Streams',
                                'callback' => function ($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType($database, $rowId, 0);
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                            'movevod' => [
                                'icon' => 'video-camera',
                                'title' => 'Move This Stream to VOD Streams',
                                'callback' => function ($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType($database, $rowId, 4);
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                            'moveother' => [
                                'icon' => 'nut',
                                'title' => 'Move This Stream to Other Streams',
                                'callback' => function ($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType($database, $rowId, 99);
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                        ],
                        'vod' => [
                            'html' => [
                                'location' => 'both',
                                'content' => '<br class="action-nl" />'
                            ],
                            'movelive' => [
                                'icon' => 'tv',
                                'title' => 'Move This Stream to Live Streams',
                                'callback' => function ($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType($database, $rowId, 0);
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                            'moveseries' => [
                                'icon' => 'album',
                                'title' => 'Move This Stream to Series Streams',
                                'callback' => function ($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType($database, $rowId, 5);
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                            'movevod' => [
                                'icon' => 'video-camera',
                                'title' => 'Move This Stream to VOD Streams',
                                'callback' => function ($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType($database, $rowId, 4);
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                            'moveother' => [
                                'icon' => 'nut',
                                'title' => 'Move This Stream to Other Streams',
                                'callback' => function ($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType($database, $rowId, 99);
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                        ],
                        'other' => [
                            'html' => [
                                'location' => 'both',
                                'content' => '<br class="action-nl" />'
                            ],
                            'movelive' => [
                                'icon' => 'tv',
                                'title' => 'Move This Stream to Live Streams',
                                'callback' => function ($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType($database, $rowId, 0);
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                            'moveseries' => [
                                'icon' => 'album',
                                'title' => 'Move This Stream to Series Streams',
                                'callback' => function ($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType($database, $rowId, 5);
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                            'movevod' => [
                                'icon' => 'video-camera',
                                'title' => 'Move This Stream to VOD Streams',
                                'callback' => function ($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType($database, $rowId, 4);
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                        ],
                    ],
                    'form' => [
                        // general
                        's.u_id' => [
                            'type' => 'hidden',
                            'value' => $userId,
                            'required' => true,
                            'tab' => 'general',
                        ],
                        's_name' => [
                            'type' => 'text',
                            'required' => true,
                            'class' => 'uk-width-1-1 uk-margin-bottom',
                            'label' => 'Name',
                            'tab' => 'general',
                        ],
                        's_orig_name' => [
                            'type' => 'text',
                            'required' => true,
                            'class' => 'uk-width-1-1 uk-margin-bottom',
                            'label' => 'Original Name',
                            'tab' => 'general',
                        ],
                        's_stream_uri' => [
                            'type' => 'url',
                            'required' => true,
                            'class' => 'uk-width-1-1 uk-margin-bottom',
                            'label' => 'Stream URL',
                            'tab' => 'general',
                        ],
                        // settings
                        'p_id' => [
                            'type' => 'select',
                            'required' => true,
                            'class' => 'uk-width-1-1 uk-margin-bottom',
                            'label' => 'Provider',
                            'options' => KPTV::getProviders($userId),
                            'tab' => 'settings',
                        ],
                        's_active' => [
                            'type' => 'boolean',
                            'label' => 'Stream Active?',
                            'class' => 'uk-width-1-2',
                            'tab' => 'settings',
                        ],
                        's_type_id' => [
                            'type' => 'select',
                            'label' => 'Stream Type',
                            'options' => [
                                0 => 'Live',
                                5 => 'Series',
                                4 => 'VOD',
                            ],
                            'class' => 'uk-width-1-2',
                            'tab' => 'settings',
                        ],
                        // meta
                        's_guide' => [
                            'type' => 'select',
                            'class' => 'uk-width-1-2',
                            'label' => 'Guide',
                            'options' => self::guide_types(),
                            'default' => '0',
                            'tab' => 'meta data',
                        ],
                        's_channel' => [
                            'type' => 'text',
                            'class' => 'uk-width-1-2',
                            'label' => 'Channel',
                            'default' => '0',
                            'tab' => 'meta data',
                        ],
                        's_tvg_id' => [
                            'type' => 'text',
                            'class' => 'uk-width-1-2',
                            'label' => 'TVG ID',
                            'tab' => 'meta data',
                        ],
                        's_tvg_group' => [
                            'type' => 'text',
                            'class' => 'uk-width-1-2',
                            'label' => 'TVG Group',
                            'tab' => 'meta data',
                        ],
                        's_tvg_logo' => [
                            'type' => 'image',
                            'class' => 'uk-width-1-1',
                            'label' => 'Channel Logo',
                            'tab' => 'meta data',
                        ],
                        's_extras' => [
                            'type' => 'text',
                            'class' => 'uk-width-1-1',
                            'label' => 'Attributes',
                            'tab' => 'meta data',
                        ],
                    ]
                ],
                'missing' => [
                    'bulk' => [
                        'replacedelete' => [
                            'label' => 'Delete Streams<br />(also deletes the master stream)',
                            'icon' => 'trash',
                            'confirm' => 'Are you sure you want to delete these streams?',
                            'callback' => function ($selectedIds, $db, $tableName) {
                                // make sure we have records selected
                                if (empty($selectedIds)) return false;
                                $tableName = KPTV::validateTableName($tableName);
                                // setup the placeholders and the query
                                $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                                $sql = "SELECT stream_id FROM {$tableName} WHERE id IN ({$placeholders})";

                                // get the records
                                $rs = $db->query($sql)
                                    ->bind($selectedIds)
                                    ->fetch();

                                // loop the records
                                foreach ($rs as $rec) {
                                    if ($rec->stream_id > 0) {
                                        $db->query("DELETE FROM `kptv_streams` WHERE `id` = ?")
                                            ->bind($rec->stream_id)
                                            ->execute();
                                    }
                                }

                                // return the execution
                                return $db->query("DELETE FROM {$tableName} WHERE id IN ({$placeholders})")
                                    ->bind($selectedIds)
                                    ->execute() !== false;
                            },
                            'success_message' => 'Records deleted',
                            'error_message' => 'Failed to delete the records'
                        ],
                        'clearmissing' => [
                            'label' => 'Clear Missing Streams<br />(only removes them from here)',
                            'icon' => 'ban',
                            'confirm' => 'Are you sure you want to delete these streams?',
                            'callback' => function ($selectedIds, $db, $tableName) {
                                // make sure we have records selected
                                if (empty($selectedIds)) return false;
                                $tableName = KPTV::validateTableName($tableName);
                                // setup the placeholders and the query
                                $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));

                                // return the execution
                                return $db->query("DELETE FROM {$tableName} WHERE id IN ({$placeholders})")
                                    ->bind($selectedIds)
                                    ->execute() !== false;
                            },
                            'success_message' => 'Records deleted',
                            'error_message' => 'Failed to delete the records'
                        ],
                    ],
                    'row' => [
                        [
                            'playstream' => [
                                'icon' => 'play',
                                'title' => 'Try to Play Stream',
                                'class' => 'play-stream',
                                'href' => '#{TheOrigName}',
                                'attributes' => [
                                    'data-stream-url' => '{TheStream}',
                                    'data-stream-name' => '{TheOrigName}',
                                ]
                            ],
                            'copystream' => [
                                'icon' => 'link',
                                'title' => 'Copy Stream Link',
                                'class' => 'copy-link',
                                'href' => '{TheStream}',
                            ]
                        ],
                        [
                            'deletemissing' => [
                                'icon' => 'trash',
                                'title' => 'Delete the Stream<br />(also deletes the master)',
                                'confirm' => 'Are you want to remove this stream?',
                                'callback' => function ($rowId, $rowData, $db, $tableName) {
                                    // make sure we have a row ID
                                    if (empty($rowId)) return false;

                                    // its a stream id
                                    if ($rowData["m.stream_id"] > 0) {
                                        $db->query("DELETE FROM `kptv_streams` WHERE `id` = ?")
                                            ->bind($rowData["m.stream_id"])
                                            ->execute();
                                    }

                                    // delete the missing record
                                    return $db->query("DELETE FROM `kptv_stream_missing` WHERE `id` = ?")
                                        ->bind($rowId)
                                        ->execute() !== false;
                                },
                                'success_message' => 'The stream has been deleted.',
                                'error_message' => 'Failed to delete the stream.',
                            ],
                            'clearmissing' => [
                                'icon' => 'ban',
                                'title' => 'Clear the Stream<br />(only deletes it from here)',
                                'confirm' => 'Are you want to remove this stream?',
                                'callback' => function ($rowId, $rowData, $db, $tableName) {
                                    // make sure we have a row ID
                                    if (empty($rowId)) return false;

                                    // delete the missing record
                                    return $db->query("DELETE FROM `kptv_stream_missing` WHERE `id` = ?")
                                        ->bind($rowId)
                                        ->execute() !== false;
                                },
                                'success_message' => 'The stream has been deleted.',
                                'error_message' => 'Failed to delete the stream.',
                            ],
                        ],
                    ],
                    'form' => []
                ],
                default => []
            };
        }

        /** 
         * active_link
         * 
         * Just gets if we're in a "page"
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         */
        public static function active_link(string $which): string
        {

            $route = \KPT\Router::getCurrentRoute();
            $route_path = $route->path;

            // hold the routes to match
            $routes = [
                'home' => ['/'],
                'info' => ['/users/faq', '/streams/faq', '/terms-of-use'],
                'admin' => ['/admin/users'],
                'account' => ['/users/changepass', '/users/login', '/users/register', '/users/forgot'],
                'streams' => [
                    '/streams/live/all',
                    '/streams/live/active',
                    '/streams/live/inactive',
                    '/streams/series/all',
                    '/streams/series/active',
                    '/streams/series/inactive',
                    '/streams/vod/all',
                    '/streams/vod/active',
                    '/streams/vod/inactive',
                ],
            ];

            // return the active class on the match
            return isset($routes[$which]) && in_array($route_path, $routes[$which], true)
                ? 'uk-active'
                : '';
        }

        /** 
         * open_link
         * 
         * Just gets if we're in an open "page"
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         */
        public static function open_link(string $which): string
        {

            $route = \KPT\Router::getCurrentRoute();
            $route_path = $route->path;

            // hold the routes to match
            $routes = [
                'info' => ['/users/faq', '/streams/faq', '/terms-of-use'],

                'account' => ['/users/changepass', '/users/login', '/users/register', '/users/forgot'],
                'live' => ['/streams/live/all', '/streams/live/active', '/streams/live/inactive',],
                'series' => ['/streams/series/all', '/streams/series/active', '/streams/series/inactive',],
                'vod' => ['/streams/vod/all', '/streams/vod/active', '/streams/vod/inactive',],
            ];

            // return the active class on the match
            return isset($routes[$which]) && in_array($route_path, $routes[$which])
                ? 'uk-open'
                : '';
        }

        /** 
         * get_counts
         * 
         * Gets all counts necessary for the dash
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         */
        public static function get_counts(): array
        {

            // get the users ID
            $user_id = KPTV_User::get_current_user()->id;

            // stream count sql
            $stream_ct_qry = "SELECT 
                COUNT(id) as total_streams,
                SUM(CASE WHEN s_active = 1 AND s_type_id = 0 THEN 1 ELSE 0 END) as active_live,
                SUM(CASE WHEN s_active = 1 AND s_type_id = 5 THEN 1 ELSE 0 END) as active_series,
                SUM(CASE WHEN s_active = 1 AND s_type_id = 4 THEN 1 ELSE 0 END) as active_vod
            FROM kptv_streams
            WHERE u_id = ?";

            // provider count sql
            $provider_ct_qry = "SELECT 
                sp.sp_name as provider_name,
                COUNT(s.id) as total_streams,
                SUM(CASE WHEN s.s_active = 1 AND s.s_type_id = 0 THEN 1 ELSE 0 END) as active_live,
                SUM(CASE WHEN s.s_active = 1 AND s.s_type_id = 5 THEN 1 ELSE 0 END) as active_series,
                SUM(CASE WHEN s.s_active = 1 AND s.s_type_id = 4 THEN 1 ELSE 0 END) as active_vod
            FROM kptv_stream_providers sp
            LEFT JOIN kptv_streams s ON sp.id = s.p_id AND s.u_id = ?
            WHERE sp.u_id = ?
            GROUP BY sp.id, sp.sp_name
            ORDER BY sp.sp_priority, sp.sp_name;";

            // fire up the database class
            $db = new \KPT\Database(self::get_setting('database'));

            // run the queries
            $strm_ct = $db->query($stream_ct_qry)
                ->bind([$user_id])
                ->single()
                ->fetch();
            $prov_ct = $db->query($provider_ct_qry)
                ->bind([$user_id, $user_id])
                ->fetch();

            // setup the return data
            $ret = [
                'total' => $strm_ct->total_streams,
                'live' => $strm_ct->active_live,
                'series' => $strm_ct->active_series,
                'vod' => $strm_ct->active_vod,
                'per_provider' => $prov_ct,
            ];

            // clean up
            unset($prov_ct, $strm_ct, $db);

            // return
            return $ret;
        }

        /** 
         * selected
         * 
         * Output "selected" for a drop-down
         * 
         * @since 8.3
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param mixed $current The current item
         * @param mixed $expected The expected item
         * 
         * @return string Returns the string "selected" or empty
         * 
         */
        public static function selected($current, $expected): string
        {

            // if they are equal, return selected
            return $current == $expected ? 'selected' : '';
        }

        /** 
         * message_with_redirect
         * 
         * Populate a message with our redirect
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_location The page we want to try to redirect to
         * @param string $_msg_type The type os message we should be showing
         * @param string $_msg The message content
         * 
         * @return void This method returns nothing
         * 
         */
        public static function message_with_redirect(string $_location, string $_msg_type, string $_msg): void
        {

            // setup the message
            $_SESSION['page_msg']['type'] = $_msg_type;
            $_SESSION['page_msg']['msg']  = sprintf('<p>%s</p>', $_msg);

            // build absolute URL if a relative path was passed
            if (str_starts_with($_location, '/')) {
                $_location = rtrim(KPTV_URI, '/') . $_location;
            }

            // redirect
            \KPT\Http::tryRedirect($_location);
        }

        /** 
         * get_image_path
         * 
         * Static method for formatting the path to the image we need
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_name The name of the image
         * @param string $_which Which image size do we need
         * 
         * @return string Returns the formatted path to the image
         * 
         */
        public static function get_image_path(string $_name, string $_which = 'header'): string
        {

            // return the path to the image
            return sprintf("/assets/images/%s-%s.jpg", $_name, $_which);
        }

        /** 
         * get_full_config
         * 
         * Get our full app config
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return object This method returns a standard class object of our applications configuration
         * 
         */
        public static function get_full_config(): object
        {

            static $config = null;

            if ($config !== null) {
                return $config;
            }

            // look for config outside webroot first, fall back to app root
            $configPath = defined('KPTV_CONFIG_PATH')
                ? KPTV_CONFIG_PATH
                : dirname(KPTV_PATH) . '/config.json';

            // let's check the cache
            $content = \KPT\Cache::get('kptv_config');
            if (!$content) {
                // grab it from the file itself
                $content = file_get_contents($configPath);

                // now set it to cache
                \KPT\Cache::set('kptv_config', $content, \KPT\DateTime::DAY_IN_SECONDS);
            }

            // now, decode it so we can use it
            $config = json_decode($content);
            if (!$config) {
                $config = new \stdClass();
            }

            return $config;
        }

        /** 
         * get_setting
         * 
         * Get a single setting value from our config object
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return mixed This method returns a variable value of the setting requested
         * 
         */
        public static function get_setting(string $_name)
        {

            // get all our options
            $_all_opts = self::get_full_config();

            // get the single option based on the shortname passed
            if (isset($_all_opts->{$_name})) {

                // return the property
                return $_all_opts->{$_name};
            }

            // default to returning null
            return null;
        }

        /**
         * include_css
         * 
         * Static method for including our CSS files in the header
         * 
         * @since 8.5
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return void This method returns nothing
         * 
         */
        public static function include_css(): void
        {
            // check if we're debugging or not
            if (KPTV_DEBUG) {
                // include the non-minified CSS
                echo '<link rel="stylesheet" href="/assets/css/kptv.css" />' . PHP_EOL;
                echo '<link rel="stylesheet" href="/assets/css/datatables.css" />' . PHP_EOL;
            } else {
                // include the minified CSS
                echo '<link rel="stylesheet" href="/assets/css/kptv.min.css" />' . PHP_EOL;
            }
        }

        /**
         * include_js
         * 
         * Static method for including our JS files in the footer
         * 
         * @since 8.5
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return void This method returns nothing
         * 
         */
        public static function include_js(): void
        {
            // check if we're debugging or not
            if (KPTV_DEBUG) {
                // include the non-minified JS
                echo '<script src="/assets/js/kptv.js" defer></script>' . PHP_EOL;
                echo '<script src="/assets/js/video.js" defer></script>' . PHP_EOL;
            } else {
                // include the minified JS
                echo '<script src="/assets/js/kptv.min.js" defer></script>' . PHP_EOL;
            }
        }

        /** 
         * encrypt
         * 
         * Static method for encrypting a string utilizing openssl libraries
         * if openssl is not found, will simply base64_encode the string
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_val The string to be encrypted
         * 
         * @return string Returns the encrypted or encoded string
         * 
         */
        public static function encrypt(string $_val): string
        {

            // return the encrypted string
            return \KPT\Crypto::encrypt(
                $_val,
                self::get_setting('mainkey'),
                self::get_setting('mainsecret')
            );
        }

        /** 
         * decrypt
         * 
         * Static method for decryption a string utilizing openssl libraries
         * if openssl is not found, will simply base64_decode the string
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_val The string to be encrypted
         * 
         * @return string Returns the decrypted or decoded string
         * 
         */
        public static function decrypt(string $_val): string
        {

            // return the encrypted string
            return \KPT\Crypto::decrypt(
                $_val,
                self::get_setting('mainkey'),
                self::get_setting('mainsecret')
            );
        }

        /** 
         * send_email
         * 
         * Send an email through SMTP
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array $_to Who is the email going to: email, name?
         * @param string $_subj What is the emails subject
         * @param string $_msg What is the emails message
         * 
         * @return bool Returns success or not
         * 
         */
        public static function send_email(array $_to, string $_subj, string $_msg): bool
        {

            //Create a new PHPMailer instance
            $mail = new \PHPMailer\PHPMailer\PHPMailer();

            //Tell PHPMailer to use SMTP
            $mail->isSMTP();

            // if we want to debug
            if (filter_var(self::get_setting('smtp')->debug, FILTER_VALIDATE_BOOLEAN)) {

                // set it to client and server debug
                $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
            }

            //Set the hostname of the mail server
            $mail->Host = self::get_setting('smtp')->server;

            // setup the type of SMTP security we'll use
            if (self::get_setting('smtp')->security && 'tls' === self::get_setting('smtp')->security) {

                // set to TLS
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

                // just default to SSL
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            }

            //Set the SMTP port number - likely to be 25, 465 or 587
            $mail->Port = (self::get_setting('smtp')->port) ?? 25;

            //Whether to use SMTP authentication
            $mail->SMTPAuth = true;

            //Username to use for SMTP authentication
            $mail->Username = self::get_setting('smtp')->username;

            //Password to use for SMTP authentication
            $mail->Password = self::get_setting('smtp')->password;

            //Set who the message is to be sent from
            $mail->setFrom(self::get_setting('smtp')->fromemail, self::get_setting('smtp')->fromname); // email, name

            //Set who the message is to be sent to
            $mail->addAddress($_to[0], $_to[1]);

            // set if the email s)hould be HTML or not
            $mail->isHTML(filter_var(self::get_setting('smtp')->forcehtml, FILTER_VALIDATE_BOOLEAN));

            //Set the subject line
            $mail->Subject = $_subj;

            // set the mail body
            $mail->Body = $_msg;

            //send the message, check for errors
            if (! $mail->send()) {
                \KPT\Logger::error('SMTP send failed', ['error' => $mail->ErrorInfo]);
                return false;
            } else {
                return true;
            }
        }

        /** 
         * mask_email_address
         * 
         * Mask an email address from bots
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_value The email address to mask
         * 
         * @return string The masked email address
         * 
         */
        public static function mask_email_address(string $_value): string
        {

            // hold the returnable string
            $_ret = '';

            // get the string length
            $_sl = strlen($_value);

            // loop over the string
            for ($_i = 0; $_i < $_sl; $_i++) {

                // apppend the ascii val to the returnable string
                $_ret .= '&#' . ord($_value[$_i]) . ';';
            }

            // return it
            return $_ret;
        }

        /** 
         * show_message
         * 
         * Show a UIKit based message
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_type The type of message we need to show
         * @param string $_msg The message to show
         * 
         * @return void This method returns nothing
         * 
         */
        public static function show_message(string $_type, string $_msg): void
        {

            // build out our HTML for the alerts
?>
            <div class="dark-version uk-alert uk-alert-<?php echo $_type; ?> uk-padding-small">
                <?php
                // show the icon and message based on the type
                echo match ($_type) {
                    'success' => '<span uk-icon="icon: check"></span> Yahoo!',
                    'warning' => '<span uk-icon="icon: question"></span> Hmm...',
                    'danger' => '<span uk-icon="icon: warning"></span> Uh Ohhh!',
                    'info' => '<span uk-icon="icon: info"></span> Heads Up',
                    default => '',
                };
                ?>
                <?php echo $_msg; ?>
            </div>

<?php
        }

        /** 
         * bool_to_icon
         * 
         * Just converts a boolean value to a UIKit icon
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param bool $_val The value to convert, default false
         * 
         * @return string Returns the inline icon
         * 
         */
        public static function bool_to_icon(bool $_val = false): string
        {

            // if the value is true
            if ($_val) {

                // return a check mark icon
                return '<span uk-icon="check"></span>';
            }

            // return an X icon
            return '<span uk-icon="close"></span>';
        }

        /** 
         * get_cache_prefix
         * 
         * Static method for creating a normalized global cache prefix
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string A formatted cache key based on the uri the user browsed
         * 
         **/
        public static function get_cache_prefix(): string
        {

            // set the uri
            $uri = \KPT\Http::getUserUri();

            // Remove protocol and www prefix
            $clean_uri = preg_replace('/^(https?:\/\/)?(www\.)?/', '', $uri);

            // Remove trailing slashes and paths
            $clean_uri = preg_replace('/\/.*$/', '', $clean_uri);

            // replace non-alphanumeric with underscores
            $clean_uri = preg_replace('/[^a-zA-Z0-9]/', '_', $clean_uri);

            // Remove consecutive underscores
            $clean_uri = preg_replace('/_+/', '_', $clean_uri);

            // Trim underscores from ends
            $clean_uri = trim($clean_uri, '_');

            // Ensure it starts with a letter (some cache backends require this)
            if (! preg_match('/^[A-Za-z]/', $clean_uri)) {
                $clean_uri = 'S_' . $clean_uri;
            }

            // Limit length for cache key compatibility
            $clean_uri = substr($clean_uri, 0, 20);

            // Always end with colon separator
            return $clean_uri . ':';
        }

        /** 
         * get_redirect_url
         * 
         * Static method for getting the redirect url for crud actions
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string The full URL
         * 
         **/
        public static function get_redirect_url(): string
        {

            // parse out the querystring
            $query_string = parse_url(\KPT\Http::getUserUri(), PHP_URL_QUERY) ?? '';

            // parse out the actual URL including the path browsed
            $url = parse_url(\KPT\Http::getUserUri(), PHP_URL_PATH) ?? '/';

            // return the formatted string
            return sprintf('%s?%s', $url, $query_string);
        }

        /**
         * Includes a view file with passed data
         * 
         * @param string $view_name Name of the view file (without extension)
         * @param array $data Associative array of data to pass to the view
         */
        public static function include_view(string $view_name, array $data = []): void
        {

            // Extract data to variables
            extract($data);

            // Include the view file
            include KPTV_PATH . "/views/{$view_name}.php";
        }

        /** 
         * pull_header
         * 
         * Static method for pulling the sites header
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array $data Associative array of data to pass to the view
         * 
         * @return void Returns nothing
         * 
         */
        public static function pull_header(array $data = []): void
        {

            // include the header and pass data if any
            self::include_view('wrapper/header', $data);
        }

        /** 
         * pull_footer
         * 
         * Static method for pulling the sites footer
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array $data Associative array of data to pass to the view
         * 
         * @return void Returns nothing
         * 
         */
        public static function pull_footer(array $data = [])
        {

            // include the header and pass data if any
            self::include_view('wrapper/footer', $data);
        }

        /**
         * Moves a stream from one type to another
         * 
         * @param object $db Our database object
         * @param int $id The id of the record to move
         * @param int $type The type we're moving
         * 
         * @return bool Returns the success
         */
        public static function moveToType($db, int $id, int $type = 99): bool
        {

            // Use transaction for multiple operations
            $db->transaction();
            try {

                // figure out what we're moving
                $result = match ($type) {
                    // live
                    0 => $db
                        ->query('UPDATE `kptv_streams` SET `s_type_id` = 0 WHERE `id` = ?')
                        ->bind([$id])  // Fixed: was using $which instead of $type
                        ->execute(),
                    // series
                    5 => $db
                        ->query('UPDATE `kptv_streams` SET `s_type_id` = 5 WHERE `id` = ?')
                        ->bind([$id])  // Fixed: was using $which instead of $type
                        ->execute(),
                    // vod
                    4 => $db
                        ->query('UPDATE `kptv_streams` SET `s_type_id` = 4 WHERE `id` = ?')
                        ->bind([$id])  // Fixed: was using $which instead of $type
                        ->execute(),
                    // other
                    default => $db
                        ->query('UPDATE `kptv_streams` SET `s_type_id` = 99 WHERE `id` = ?')
                        ->bind([$id])
                        ->execute(),
                };

                // Check if operation failed
                if ($result === false) {
                    $db->rollback();
                    return false;
                }

                // Commit if all successful
                $db->commit();
                return true;
            } catch (\Exception $e) {
                // Rollback on error
                $db->rollback();
                return false;
            }
        }

        /**
         * Gets all providers for a user
         * 
         * @param object $db Our database object
         * @param int $id The id of the record to move
         * @param int $type The type we're moving
         * 
         * @return bool Returns the success
         */
        public static function getProviders(int $userId): array
        {

            // setup the return
            $ret = [];

            $dbconf = self::get_setting('database') ?? (object)[];

            // fire up the database class
            $db = new \KPT\Database($dbconf);

            // setup the recordset
            $rs = $db->query("SELECT id, sp_name FROM kptv_stream_providers WHERE u_id = ?")
                ->bind([$userId])
                ->asArray()
                ->fetch();

            // loop the array, if it is an array
            if (is_array($rs)) {
                foreach ($rs as $rec) {
                    // set the array items
                    $ret[$rec['id']] = $rec['sp_name'];
                }
            }

            // return them
            return $ret;
        }

        /**
         * Gets just the provider names for a user
         * 
         * @param object $db Our database object
         * @param int $id The id of the record to move
         * @param int $type The type we're moving
         * 
         * @return bool Returns the success
         */
        public static function getProvidersNames(int $userId): array
        {

            // setup the return
            $ret = [];

            $dbconf = self::get_setting('database') ?? (object)[];

            // fire up the database class
            $db = new \KPT\Database($dbconf);

            // setup the recordset
            $rs = $db->query("SELECT id, sp_name FROM kptv_stream_providers WHERE u_id = ?")
                ->bind([$userId])
                ->asArray()
                ->fetch();

            // loop the array, if it is an array
            if (is_array($rs)) {
                foreach ($rs as $rec) {
                    // set the array items
                    $ret[$rec['sp_name']] = $rec['sp_name'];
                }
            }

            // return them
            return $ret;
        }

        /**
         * Encrypt a value and return URL-safe base64
         */
        public static function encryptForUrl(string $_val): string
        {
            return rtrim(strtr(self::encrypt($_val), '+/', '-_'), '=');
        }

        /**
         * Decrypt a URL-safe base64 encrypted value
         */
        public static function decryptFromUrl(string $_val): string
        {
            return self::decrypt(strtr($_val, '-_', '+/'));
        }

        /**
         * Validate table name against allowlist to prevent SQL injection
         */
        public static function validateTableName(string $tableName): string
        {
            $allowed = [
                'kptv_users',
                'kptv_streams',
                'kptv_stream_filters',
                'kptv_stream_providers',
                'kptv_stream_missing',
            ];

            if (!in_array($tableName, $allowed, true)) {
                throw new \InvalidArgumentException("Invalid table name: {$tableName}");
            }

            return $tableName;
        }
    }
}
