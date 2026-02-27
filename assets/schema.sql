SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


DELIMITER $$
DROP PROCEDURE IF EXISTS `CleanupStreams`$$
CREATE PROCEDURE `CleanupStreams` ()   BEGIN
    START TRANSACTION;

    -- -- Remove streams whose provider no longer exists
    DELETE FROM kptv_streams
    WHERE NOT EXISTS (
        SELECT 1
        FROM kptv_stream_providers
        WHERE kptv_stream_providers.id = kptv_streams.p_id
    );

    -- -- Deduplicate by stream URI, keep newest
    DELETE s1
    FROM kptv_streams s1
    LEFT JOIN (
        SELECT MAX(id) AS max_id, s_stream_uri
        FROM kptv_streams
        GROUP BY s_stream_uri
    ) s2 ON s1.id = s2.max_id
    WHERE s2.max_id IS NULL;

    -- -- Clear the staging table
    TRUNCATE TABLE kptv_stream_temp;

    COMMIT;
END$$

DROP PROCEDURE IF EXISTS `ResetStreamIDs`$$
CREATE PROCEDURE `ResetStreamIDs` ()   BEGIN
    DECLARE v_max_id BIGINT;
    DECLARE v_next_val BIGINT;
    DECLARE v_sql TEXT;

    SET FOREIGN_KEY_CHECKS = 0;

    -- -- Strip auto_increment so we can drop PK
    ALTER TABLE kptv_streams MODIFY id BIGINT NOT NULL;
    ALTER TABLE kptv_streams DROP PRIMARY KEY;

    -- -- Renumber sequentially
    SET @counter = 0;
    UPDATE kptv_streams
    SET id = (@counter := @counter + 1)
    ORDER BY id;

    -- -- Restore PK and auto_increment
    ALTER TABLE kptv_streams
    MODIFY id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY;

    SELECT COALESCE(MAX(id), 0) INTO v_max_id FROM kptv_streams;
    SET v_next_val = v_max_id + 1;
    SET v_sql = CONCAT('ALTER TABLE kptv_streams AUTO_INCREMENT = ', v_next_val);

    PREPARE stmt FROM v_sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;

    SET FOREIGN_KEY_CHECKS = 1;
END$$

DELIMITER ;

DROP TABLE IF EXISTS `kptv_streams`;
CREATE TABLE `kptv_streams` (
  `id` bigint(20) NOT NULL,
  `u_id` bigint(20) NOT NULL,
  `p_id` bigint(20) NOT NULL DEFAULT 0,
  `s_type_id` tinyint(1) NOT NULL DEFAULT 0,
  `s_active` tinyint(1) NOT NULL DEFAULT 0,
  `s_channel` varchar(32) NOT NULL DEFAULT '0',
  `s_name` varchar(1024) NOT NULL,
  `s_orig_name` varchar(1024) NOT NULL,
  `s_stream_uri` varchar(2048) NOT NULL DEFAULT '',
  `s_tvg_id` varchar(1024) DEFAULT NULL,
  `s_tvg_group` varchar(1024) DEFAULT NULL,
  `s_tvg_logo` varchar(2048) DEFAULT NULL,
  `s_extras` varchar(2048) DEFAULT NULL,
  `s_created` datetime NOT NULL DEFAULT current_timestamp(),
  `s_updated` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `kptv_stream_filters`;
CREATE TABLE `kptv_stream_filters` (
  `id` bigint(20) NOT NULL,
  `u_id` bigint(20) NOT NULL,
  `sf_active` tinyint(1) NOT NULL DEFAULT 1,
  `sf_type_id` tinyint(1) NOT NULL DEFAULT 0,
  `sf_filter` varchar(1024) NOT NULL,
  `sf_created` datetime NOT NULL DEFAULT current_timestamp(),
  `sf_updated` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `kptv_stream_missing`;
CREATE TABLE `kptv_stream_missing` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `u_id` bigint(20) NOT NULL,
  `p_id` bigint(20) NOT NULL,
  `stream_id` bigint(20) NOT NULL DEFAULT 0,
  `other_id` bigint(20) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `kptv_stream_providers`;
CREATE TABLE `kptv_stream_providers` (
  `id` bigint(20) NOT NULL,
  `u_id` bigint(20) NOT NULL,
  `sp_should_filter` tinyint(1) NOT NULL DEFAULT 1,
  `sp_priority` int(4) NOT NULL DEFAULT 99,
  `sp_name` varchar(256) NOT NULL,
  `sp_cnx_limit` int(4) NOT NULL DEFAULT 1,
  `sp_sub_length` int(4) NOT NULL DEFAULT 0,
  `sp_contact` varchar(256) DEFAULT NULL,
  `sp_cost` float NOT NULL DEFAULT 0,
  `sp_expires` date DEFAULT NULL,
  `sp_type` tinyint(1) NOT NULL DEFAULT 0,
  `sp_domain` varchar(256) NOT NULL,
  `sp_username` varchar(1024) DEFAULT NULL,
  `sp_password` varchar(1024) DEFAULT NULL,
  `sp_stream_type` tinyint(1) NOT NULL DEFAULT 0,
  `sp_refresh_period` int(4) NOT NULL DEFAULT 1,
  `sp_last_synced` datetime DEFAULT NULL,
  `sp_added` datetime NOT NULL DEFAULT current_timestamp(),
  `sp_updated` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `kptv_stream_temp`;
CREATE TABLE `kptv_stream_temp` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `u_id` bigint(20) NOT NULL,
  `p_id` bigint(20) NOT NULL,
  `s_type_id` tinyint(4) NOT NULL DEFAULT 0,
  `s_orig_name` varchar(1024) NOT NULL,
  `s_stream_uri` varchar(2048) NOT NULL DEFAULT '',
  `s_tvg_id` varchar(512) DEFAULT NULL,
  `s_tvg_logo` varchar(2048) DEFAULT NULL,
  `s_extras` varchar(2048) DEFAULT NULL,
  `s_group` varchar(1024) DEFAULT NULL,
  `s_orig_name_lower` varchar(255) GENERATED ALWAYS AS (lcase(`s_orig_name`)) VIRTUAL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `kptv_users`;
CREATE TABLE `kptv_users` (
  `id` bigint(20) NOT NULL,
  `u_role` tinyint(2) NOT NULL DEFAULT 0,
  `u_active` tinyint(1) NOT NULL DEFAULT 0,
  `u_name` varchar(128) NOT NULL,
  `u_pass` char(97) NOT NULL,
  `u_hash` char(64) NOT NULL,
  `u_email` varchar(512) NOT NULL,
  `u_lname` varchar(128) DEFAULT NULL,
  `u_fname` varchar(128) DEFAULT NULL,
  `u_created` datetime NOT NULL DEFAULT current_timestamp(),
  `u_updated` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `login_attempts` tinyint(4) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


ALTER TABLE `kptv_streams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_uid` (`u_id`),
  ADD KEY `idx_pid` (`p_id`),
  ADD KEY `idx_stypeid` (`s_type_id`),
  ADD KEY `idx_sactive` (`s_active`),
  ADD KEY `idx_schannel` (`s_channel`),
  ADD KEY `idx_sactive_stvgid` (`s_active`,`s_tvg_id`(255)),
  ADD KEY `idx_sname_supdated` (`s_name`(255),`s_updated`);
ALTER TABLE `kptv_streams` ADD FULLTEXT KEY `idx_ft_sname` (`s_name`);
ALTER TABLE `kptv_streams` ADD FULLTEXT KEY `idx_ft_sorigname` (`s_orig_name`);

ALTER TABLE `kptv_stream_filters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_uid_sfactive_sftypeid` (`u_id`,`sf_active`,`sf_type_id`);

ALTER TABLE `kptv_stream_missing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_uid` (`u_id`),
  ADD KEY `idx_pid` (`p_id`),
  ADD KEY `idx_streamid` (`stream_id`),
  ADD KEY `idx_otherid` (`other_id`);

ALTER TABLE `kptv_stream_providers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_uid` (`u_id`),
  ADD KEY `idx_spname` (`sp_name`);

ALTER TABLE `kptv_stream_temp`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `kptv_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_uname` (`u_name`),
  ADD UNIQUE KEY `idx_uemail` (`u_email`),
  ADD KEY `idx_uactive` (`u_active`);


ALTER TABLE `kptv_streams`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `kptv_stream_filters`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `kptv_stream_missing`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `kptv_stream_providers`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `kptv_stream_temp`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `kptv_users`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

COMMIT;
