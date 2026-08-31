-- =====================================================================
-- TECHBISS — complete database schema
-- MySQL 5.7+ / MariaDB 10.4+   |   InnoDB   |   utf8mb4
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Access control
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(80)  NOT NULL,
  `slug`        VARCHAR(80)  NOT NULL,
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `is_system`   TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL,
  `updated_at`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `slug`        VARCHAR(100) NOT NULL,
  `group_name`  VARCHAR(60)  NOT NULL DEFAULT 'General',
  `sort_order`  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_slug` (`slug`),
  KEY `ix_permissions_group` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id`       INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  KEY `ix_rp_permission` (`permission_id`),
  CONSTRAINT `fk_rp_role`       FOREIGN KEY (`role_id`)       REFERENCES `roles` (`id`)       ON DELETE CASCADE,
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admins` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id`       INT UNSIGNED NOT NULL,
  `name`          VARCHAR(120) NOT NULL,
  `email`         VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `avatar`        VARCHAR(255) NOT NULL DEFAULT '',
  `job_title`     VARCHAR(120) NOT NULL DEFAULT '',
  `bio`           TEXT         NULL,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `last_login_at` DATETIME     NULL,
  `last_login_ip` VARCHAR(45)  NOT NULL DEFAULT '',
  `created_at`    DATETIME     NOT NULL,
  `updated_at`    DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_email` (`email`),
  KEY `ix_admins_role` (`role_id`),
  CONSTRAINT `fk_admins_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier` VARCHAR(190) NOT NULL,
  `ip_address` VARCHAR(45)  NOT NULL,
  `successful` TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_attempts_lookup` (`identifier`, `created_at`),
  KEY `ix_attempts_ip` (`ip_address`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`    INT UNSIGNED NULL,
  `admin_name`  VARCHAR(120) NOT NULL DEFAULT '',
  `action`      VARCHAR(80)  NOT NULL,
  `entity_type` VARCHAR(80)  NOT NULL DEFAULT '',
  `entity_id`   INT UNSIGNED NULL,
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `ip_address`  VARCHAR(45)  NOT NULL DEFAULT '',
  `created_at`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_logs_admin` (`admin_id`),
  KEY `ix_logs_entity` (`entity_type`, `entity_id`),
  KEY `ix_logs_created` (`created_at`),
  CONSTRAINT `fk_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Settings, media, navigation
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_name`  VARCHAR(60)  NOT NULL DEFAULT 'general',
  `key_name`    VARCHAR(120) NOT NULL,
  `value`       LONGTEXT     NULL,
  `type`        VARCHAR(20)  NOT NULL DEFAULT 'text',
  `label`       VARCHAR(190) NOT NULL DEFAULT '',
  `hint`        VARCHAR(255) NOT NULL DEFAULT '',
  `sort_order`  INT          NOT NULL DEFAULT 0,
  `updated_at`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`key_name`),
  KEY `ix_settings_group` (`group_name`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `media` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_name`   VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `path`        VARCHAR(500) NOT NULL,
  `thumb_path`  VARCHAR(500) NOT NULL DEFAULT '',
  `mime_type`   VARCHAR(100) NOT NULL,
  `extension`   VARCHAR(10)  NOT NULL,
  `size_bytes`  INT UNSIGNED NOT NULL DEFAULT 0,
  `width`       INT UNSIGNED NULL,
  `height`      INT UNSIGNED NULL,
  `alt_text`    VARCHAR(255) NOT NULL DEFAULT '',
  `folder`      VARCHAR(60)  NOT NULL DEFAULT 'general',
  `uploaded_by` INT UNSIGNED NULL,
  `created_at`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_media_path` (`path`),
  KEY `ix_media_folder` (`folder`),
  KEY `ix_media_created` (`created_at`),
  CONSTRAINT `fk_media_admin` FOREIGN KEY (`uploaded_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `navigation` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `menu`        VARCHAR(40)  NOT NULL DEFAULT 'primary',
  `parent_id`   INT UNSIGNED NULL,
  `label`       VARCHAR(120) NOT NULL,
  `url`         VARCHAR(500) NOT NULL DEFAULT '',
  `link_type`   ENUM('internal','external','route') NOT NULL DEFAULT 'internal',
  `description` VARCHAR(190) NOT NULL DEFAULT '',
  `icon`        VARCHAR(60)  NOT NULL DEFAULT '',
  `target`      VARCHAR(10)  NOT NULL DEFAULT '_self',
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `is_button`   TINYINT(1)   NOT NULL DEFAULT 0,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL,
  `updated_at`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_nav_menu` (`menu`, `sort_order`),
  KEY `ix_nav_parent` (`parent_id`),
  CONSTRAINT `fk_nav_parent` FOREIGN KEY (`parent_id`) REFERENCES `navigation` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Editorial pages & homepage sections
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pages` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`            VARCHAR(190) NOT NULL,
  `title`           VARCHAR(190) NOT NULL,
  `eyebrow`         VARCHAR(120) NOT NULL DEFAULT '',
  `subtitle`        VARCHAR(500) NOT NULL DEFAULT '',
  `content`         LONGTEXT     NULL,
  `template`        VARCHAR(40)  NOT NULL DEFAULT 'default',
  `hero_image`      VARCHAR(500) NOT NULL DEFAULT '',
  `seo_title`       VARCHAR(190) NOT NULL DEFAULT '',
  `seo_description` VARCHAR(320) NOT NULL DEFAULT '',
  `og_image`        VARCHAR(500) NOT NULL DEFAULT '',
  `noindex`         TINYINT(1)   NOT NULL DEFAULT 0,
  `is_published`    TINYINT(1)   NOT NULL DEFAULT 1,
  `is_system`       TINYINT(1)   NOT NULL DEFAULT 0,
  `sort_order`      INT          NOT NULL DEFAULT 0,
  `created_at`      DATETIME     NOT NULL,
  `updated_at`      DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pages_slug` (`slug`),
  KEY `ix_pages_published` (`is_published`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reusable content blocks (hero, problem list, trust points, CTA bands…)
CREATE TABLE IF NOT EXISTS `page_sections` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_key`    VARCHAR(60)  NOT NULL DEFAULT 'home',
  `section_key` VARCHAR(60)  NOT NULL,
  `eyebrow`     VARCHAR(120) NOT NULL DEFAULT '',
  `heading`     VARCHAR(255) NOT NULL DEFAULT '',
  `subheading`  VARCHAR(500) NOT NULL DEFAULT '',
  `body`        TEXT         NULL,
  `cta_label`   VARCHAR(80)  NOT NULL DEFAULT '',
  `cta_url`     VARCHAR(500) NOT NULL DEFAULT '',
  `image`       VARCHAR(500) NOT NULL DEFAULT '',
  `is_published` TINYINT(1)  NOT NULL DEFAULT 1,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL,
  `updated_at`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sections_key` (`page_key`, `section_key`),
  KEY `ix_sections_page` (`page_key`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Repeating items inside a section (problem bullets, benefit list, chain steps…)
CREATE TABLE IF NOT EXISTS `section_items` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `section_id`  INT UNSIGNED NOT NULL,
  `title`       VARCHAR(190) NOT NULL DEFAULT '',
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `icon`        VARCHAR(60)  NOT NULL DEFAULT '',
  `value`       VARCHAR(60)  NOT NULL DEFAULT '',
  `url`         VARCHAR(500) NOT NULL DEFAULT '',
  `is_published` TINYINT(1)  NOT NULL DEFAULT 1,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_items_section` (`section_id`, `sort_order`),
  CONSTRAINT `fk_items_section` FOREIGN KEY (`section_id`) REFERENCES `page_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Headline numbers shown across the site (admin supplied, never invented)
CREATE TABLE IF NOT EXISTS `stats` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `label`       VARCHAR(120) NOT NULL,
  `value`       VARCHAR(30)  NOT NULL,
  `prefix`      VARCHAR(10)  NOT NULL DEFAULT '',
  `suffix`      VARCHAR(10)  NOT NULL DEFAULT '',
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `is_published` TINYINT(1)  NOT NULL DEFAULT 1,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL,
  `updated_at`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_stats_published` (`is_published`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "How it works" process steps
CREATE TABLE IF NOT EXISTS `process_steps` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `step_number` VARCHAR(6)   NOT NULL DEFAULT '01',
  `title`       VARCHAR(190) NOT NULL,
  `description` TEXT         NULL,
  `icon`        VARCHAR(60)  NOT NULL DEFAULT '',
  `duration`    VARCHAR(60)  NOT NULL DEFAULT '',
  `is_published` TINYINT(1)  NOT NULL DEFAULT 1,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL,
  `updated_at`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_steps_published` (`is_published`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Services
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `services` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`            VARCHAR(190) NOT NULL,
  `name`            VARCHAR(190) NOT NULL,
  `tagline`         VARCHAR(255) NOT NULL DEFAULT '',
  `short_description` VARCHAR(500) NOT NULL DEFAULT '',
  `description`     LONGTEXT     NULL,
  `icon`            VARCHAR(60)  NOT NULL DEFAULT 'spark',
  `image`           VARCHAR(500) NOT NULL DEFAULT '',
  `accent`          VARCHAR(20)  NOT NULL DEFAULT 'cyan',
  `deliverables`    TEXT         NULL,
  `process_note`    TEXT         NULL,
  `starting_price`  DECIMAL(10,2) NULL,
  `price_note`      VARCHAR(120) NOT NULL DEFAULT '',
  `seo_title`       VARCHAR(190) NOT NULL DEFAULT '',
  `seo_description` VARCHAR(320) NOT NULL DEFAULT '',
  `og_image`        VARCHAR(500) NOT NULL DEFAULT '',
  `is_featured`     TINYINT(1)   NOT NULL DEFAULT 0,
  `is_published`    TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`      INT          NOT NULL DEFAULT 0,
  `created_at`      DATETIME     NOT NULL,
  `updated_at`      DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_services_slug` (`slug`),
  KEY `ix_services_published` (`is_published`, `sort_order`),
  KEY `ix_services_featured` (`is_featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `service_features` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id`  INT UNSIGNED NOT NULL,
  `title`       VARCHAR(190) NOT NULL,
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `icon`        VARCHAR(60)  NOT NULL DEFAULT '',
  `sort_order`  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_sf_service` (`service_id`, `sort_order`),
  CONSTRAINT `fk_sf_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Industries
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `industries` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`            VARCHAR(190) NOT NULL,
  `name`            VARCHAR(190) NOT NULL,
  `tagline`         VARCHAR(255) NOT NULL DEFAULT '',
  `short_description` VARCHAR(500) NOT NULL DEFAULT '',
  `description`     LONGTEXT     NULL,
  `challenges`      TEXT         NULL,
  `solutions`       TEXT         NULL,
  `icon`            VARCHAR(60)  NOT NULL DEFAULT 'building',
  `image`           VARCHAR(500) NOT NULL DEFAULT '',
  `seo_title`       VARCHAR(190) NOT NULL DEFAULT '',
  `seo_description` VARCHAR(320) NOT NULL DEFAULT '',
  `og_image`        VARCHAR(500) NOT NULL DEFAULT '',
  `is_featured`     TINYINT(1)   NOT NULL DEFAULT 0,
  `is_published`    TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`      INT          NOT NULL DEFAULT 0,
  `created_at`      DATETIME     NOT NULL,
  `updated_at`      DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_industries_slug` (`slug`),
  KEY `ix_industries_published` (`is_published`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `industry_services` (
  `industry_id` INT UNSIGNED NOT NULL,
  `service_id`  INT UNSIGNED NOT NULL,
  `sort_order`  INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`industry_id`, `service_id`),
  KEY `ix_is_service` (`service_id`),
  CONSTRAINT `fk_is_industry` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_is_service`  FOREIGN KEY (`service_id`)  REFERENCES `services` (`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Portfolio
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `portfolio_categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(190) NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `is_published` TINYINT(1)  NOT NULL DEFAULT 1,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL,
  `updated_at`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pcat_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `portfolio_technologies` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`       VARCHAR(120) NOT NULL,
  `name`       VARCHAR(80)  NOT NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ptech_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `portfolio` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`            VARCHAR(190) NOT NULL,
  `title`           VARCHAR(190) NOT NULL,
  `category_id`     INT UNSIGNED NULL,
  `industry_id`     INT UNSIGNED NULL,
  `client_name`     VARCHAR(190) NOT NULL DEFAULT '',
  `short_description` VARCHAR(500) NOT NULL DEFAULT '',
  `overview`        LONGTEXT     NULL,
  `challenge`       LONGTEXT     NULL,
  `solution`        LONGTEXT     NULL,
  `results`         LONGTEXT     NULL,
  `thumbnail`       VARCHAR(500) NOT NULL DEFAULT '',
  `hero_image`      VARCHAR(500) NOT NULL DEFAULT '',
  `project_url`     VARCHAR(500) NOT NULL DEFAULT '',
  `android_url`     VARCHAR(500) NOT NULL DEFAULT '',
  `ios_url`         VARCHAR(500) NOT NULL DEFAULT '',
  `project_date`    DATE         NULL,
  `duration`        VARCHAR(60)  NOT NULL DEFAULT '',
  `accent`          VARCHAR(20)  NOT NULL DEFAULT 'cyan',
  `seo_title`       VARCHAR(190) NOT NULL DEFAULT '',
  `seo_description` VARCHAR(320) NOT NULL DEFAULT '',
  `og_image`        VARCHAR(500) NOT NULL DEFAULT '',
  `is_featured`     TINYINT(1)   NOT NULL DEFAULT 0,
  `is_published`    TINYINT(1)   NOT NULL DEFAULT 1,
  `view_count`      INT UNSIGNED NOT NULL DEFAULT 0,
  `sort_order`      INT          NOT NULL DEFAULT 0,
  `created_at`      DATETIME     NOT NULL,
  `updated_at`      DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_portfolio_slug` (`slug`),
  KEY `ix_portfolio_published` (`is_published`, `sort_order`),
  KEY `ix_portfolio_category` (`category_id`),
  KEY `ix_portfolio_industry` (`industry_id`),
  KEY `ix_portfolio_featured` (`is_featured`),
  CONSTRAINT `fk_portfolio_category` FOREIGN KEY (`category_id`) REFERENCES `portfolio_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_portfolio_industry` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `portfolio_images` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `portfolio_id` INT UNSIGNED NOT NULL,
  `path`         VARCHAR(500) NOT NULL,
  `alt_text`     VARCHAR(255) NOT NULL DEFAULT '',
  `caption`      VARCHAR(255) NOT NULL DEFAULT '',
  `sort_order`   INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_pimg_portfolio` (`portfolio_id`, `sort_order`),
  CONSTRAINT `fk_pimg_portfolio` FOREIGN KEY (`portfolio_id`) REFERENCES `portfolio` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `portfolio_technology_map` (
  `portfolio_id`  INT UNSIGNED NOT NULL,
  `technology_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`portfolio_id`, `technology_id`),
  KEY `ix_ptm_tech` (`technology_id`),
  CONSTRAINT `fk_ptm_portfolio` FOREIGN KEY (`portfolio_id`)  REFERENCES `portfolio` (`id`)               ON DELETE CASCADE,
  CONSTRAINT `fk_ptm_tech`      FOREIGN KEY (`technology_id`) REFERENCES `portfolio_technologies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `portfolio_services` (
  `portfolio_id` INT UNSIGNED NOT NULL,
  `service_id`   INT UNSIGNED NOT NULL,
  PRIMARY KEY (`portfolio_id`, `service_id`),
  KEY `ix_ps_service` (`service_id`),
  CONSTRAINT `fk_ps_portfolio` FOREIGN KEY (`portfolio_id`) REFERENCES `portfolio` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ps_service`   FOREIGN KEY (`service_id`)   REFERENCES `services` (`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Packages (prepaid system)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `packages` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`             VARCHAR(190) NOT NULL,
  `name`             VARCHAR(120) NOT NULL,
  `tagline`          VARCHAR(255) NOT NULL DEFAULT '',
  `short_description` VARCHAR(500) NOT NULL DEFAULT '',
  `description`      LONGTEXT     NULL,
  `best_for`         VARCHAR(255) NOT NULL DEFAULT '',
  `currency`         VARCHAR(6)   NOT NULL DEFAULT 'USD',
  `regular_price`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `prepaid_price`    DECIMAL(10,2) NULL,
  `billing_period`   VARCHAR(40)  NOT NULL DEFAULT 'one-time',
  `duration_months`  INT UNSIGNED NOT NULL DEFAULT 12,
  `badge`            VARCHAR(40)  NOT NULL DEFAULT '',
  `cta_label`        VARCHAR(60)  NOT NULL DEFAULT 'Get Started',
  `icon`             VARCHAR(60)  NOT NULL DEFAULT 'layers',
  `image`            VARCHAR(500) NOT NULL DEFAULT '',
  `accent`           VARCHAR(20)  NOT NULL DEFAULT 'cyan',
  `is_custom_quote`  TINYINT(1)   NOT NULL DEFAULT 0,
  `seo_title`        VARCHAR(190) NOT NULL DEFAULT '',
  `seo_description`  VARCHAR(320) NOT NULL DEFAULT '',
  `og_image`         VARCHAR(500) NOT NULL DEFAULT '',
  `is_featured`      TINYINT(1)   NOT NULL DEFAULT 0,
  `is_published`     TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`       INT          NOT NULL DEFAULT 0,
  `created_at`       DATETIME     NOT NULL,
  `updated_at`       DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_packages_slug` (`slug`),
  KEY `ix_packages_published` (`is_published`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `package_features` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `package_id`  INT UNSIGNED NOT NULL,
  `title`       VARCHAR(190) NOT NULL,
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `is_included` TINYINT(1)   NOT NULL DEFAULT 1,
  `is_highlight` TINYINT(1)  NOT NULL DEFAULT 0,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_pf_package` (`package_id`, `sort_order`),
  CONSTRAINT `fk_pf_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `package_addons` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(190) NOT NULL,
  `name`        VARCHAR(190) NOT NULL,
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `price`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency`    VARCHAR(6)   NOT NULL DEFAULT 'USD',
  `billing_period` VARCHAR(40) NOT NULL DEFAULT 'one-time',
  `icon`        VARCHAR(60)  NOT NULL DEFAULT '',
  `is_published` TINYINT(1)  NOT NULL DEFAULT 1,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL,
  `updated_at`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_addons_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `package_addon_map` (
  `package_id` INT UNSIGNED NOT NULL,
  `addon_id`   INT UNSIGNED NOT NULL,
  PRIMARY KEY (`package_id`, `addon_id`),
  KEY `ix_pam_addon` (`addon_id`),
  CONSTRAINT `fk_pam_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`)       ON DELETE CASCADE,
  CONSTRAINT `fk_pam_addon`   FOREIGN KEY (`addon_id`)   REFERENCES `package_addons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Customers & purchases
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(190) NOT NULL,
  `business_name` VARCHAR(190) NOT NULL DEFAULT '',
  `email`         VARCHAR(190) NOT NULL,
  `phone`         VARCHAR(40)  NOT NULL DEFAULT '',
  `country`       VARCHAR(80)  NOT NULL DEFAULT '',
  `city`          VARCHAR(120) NOT NULL DEFAULT '',
  `website`       VARCHAR(255) NOT NULL DEFAULT '',
  `industry_id`   INT UNSIGNED NULL,
  `notes`         TEXT         NULL,
  `status`        ENUM('lead','active','inactive') NOT NULL DEFAULT 'lead',
  `created_at`    DATETIME     NOT NULL,
  `updated_at`    DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customers_email` (`email`),
  KEY `ix_customers_status` (`status`),
  KEY `ix_customers_industry` (`industry_id`),
  CONSTRAINT `fk_customers_industry` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `package_purchases` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`        VARCHAR(30)  NOT NULL,
  `customer_id`      INT UNSIGNED NOT NULL,
  `package_id`       INT UNSIGNED NULL,
  `package_name`     VARCHAR(120) NOT NULL,
  `currency`         VARCHAR(6)   NOT NULL DEFAULT 'USD',
  `regular_price`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `prepaid_price`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `addons_total`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `duration_months`  INT UNSIGNED NOT NULL DEFAULT 12,
  `billing_period`   VARCHAR(40)  NOT NULL DEFAULT 'one-time',
  `payment_method`   VARCHAR(40)  NOT NULL DEFAULT 'manual',
  `payment_status`   ENUM('pending','paid','refunded','cancelled') NOT NULL DEFAULT 'pending',
  `package_status`   ENUM('pending','active','expiring','expired','cancelled') NOT NULL DEFAULT 'pending',
  `renewal_status`   ENUM('not_due','due','renewed','declined') NOT NULL DEFAULT 'not_due',
  `payment_reference` VARCHAR(120) NOT NULL DEFAULT '',
  `purchased_at`     DATETIME     NOT NULL,
  `starts_at`        DATE         NULL,
  `expires_at`       DATE         NULL,
  `business_details` TEXT         NULL,
  `requirements`     TEXT         NULL,
  `selected_features` TEXT        NULL,
  `preferred_contact` ENUM('whatsapp','email','phone') NOT NULL DEFAULT 'whatsapp',
  `admin_notes`      TEXT         NULL,
  `ip_address`       VARCHAR(45)  NOT NULL DEFAULT '',
  `created_at`       DATETIME     NOT NULL,
  `updated_at`       DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_purchase_reference` (`reference`),
  KEY `ix_purchase_customer` (`customer_id`),
  KEY `ix_purchase_package` (`package_id`),
  KEY `ix_purchase_status` (`package_status`, `expires_at`),
  KEY `ix_purchase_payment` (`payment_status`),
  CONSTRAINT `fk_purchase_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_purchase_package`  FOREIGN KEY (`package_id`)  REFERENCES `packages` (`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_addons` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_id` INT UNSIGNED NOT NULL,
  `addon_id`    INT UNSIGNED NULL,
  `name`        VARCHAR(190) NOT NULL,
  `price`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `ix_pa_purchase` (`purchase_id`),
  CONSTRAINT `fk_pa_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `package_purchases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pa_addon`    FOREIGN KEY (`addon_id`)    REFERENCES `package_addons` (`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Premade projects — ready-made builds a customer can buy as-is
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `project_categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(190) NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `icon`        VARCHAR(60)  NOT NULL DEFAULT 'grid',
  `is_published` TINYINT(1)  NOT NULL DEFAULT 1,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL,
  `updated_at`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `premade_projects` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`             VARCHAR(190) NOT NULL,
  `name`             VARCHAR(190) NOT NULL,
  `tagline`          VARCHAR(255) NOT NULL DEFAULT '',
  `short_description` VARCHAR(500) NOT NULL DEFAULT '',
  `description`      LONGTEXT     NULL,
  `whats_included`   LONGTEXT     NULL,
  `customisation_note` VARCHAR(500) NOT NULL DEFAULT '',
  `category_id`      INT UNSIGNED NULL,
  `industry_id`      INT UNSIGNED NULL,

  -- Live demo. demo_username / demo_password are shown publicly on the
  -- project page, so only ever throwaway credentials belong here.
  `demo_url`         VARCHAR(500) NOT NULL DEFAULT '',
  `demo_admin_url`   VARCHAR(500) NOT NULL DEFAULT '',
  `demo_username`    VARCHAR(120) NOT NULL DEFAULT '',
  `demo_password`    VARCHAR(120) NOT NULL DEFAULT '',
  `demo_note`        VARCHAR(255) NOT NULL DEFAULT '',

  `thumbnail`        VARCHAR(500) NOT NULL DEFAULT '',
  `hero_image`       VARCHAR(500) NOT NULL DEFAULT '',

  -- Mobile builds. apk_path is a file inside uploads/; apk_external_url points
  -- at one hosted elsewhere. Only one is used, and the download is served
  -- through a route so it always arrives as an attachment.
  `apk_path`         VARCHAR(500) NOT NULL DEFAULT '',
  `apk_external_url` VARCHAR(500) NOT NULL DEFAULT '',
  `apk_version`      VARCHAR(40)  NOT NULL DEFAULT '',
  `apk_size_bytes`   INT UNSIGNED NOT NULL DEFAULT 0,
  `apk_note`         VARCHAR(255) NOT NULL DEFAULT '',
  `android_url`      VARCHAR(500) NOT NULL DEFAULT '',
  `ios_url`          VARCHAR(500) NOT NULL DEFAULT '',

  -- No price is stored or shown. Every premade project is priced in
  -- conversation over WhatsApp or email, so there is no figure here to fall
  -- out of date or to advertise a saving that was never agreed.
  `licence`          VARCHAR(80)  NOT NULL DEFAULT '',
  `delivery_days`    INT UNSIGNED NOT NULL DEFAULT 0,
  `revisions`        VARCHAR(60)  NOT NULL DEFAULT '',
  `support_months`   INT UNSIGNED NOT NULL DEFAULT 0,
  `page_count`       INT UNSIGNED NOT NULL DEFAULT 0,

  `badge`            VARCHAR(40)  NOT NULL DEFAULT '',
  `cta_label`        VARCHAR(60)  NOT NULL DEFAULT 'Enquire about this',
  `accent`           VARCHAR(20)  NOT NULL DEFAULT 'cyan',
  `view_count`       INT UNSIGNED NOT NULL DEFAULT 0,

  `seo_title`        VARCHAR(190) NOT NULL DEFAULT '',
  `seo_description`  VARCHAR(320) NOT NULL DEFAULT '',
  `og_image`         VARCHAR(500) NOT NULL DEFAULT '',
  `is_featured`      TINYINT(1)   NOT NULL DEFAULT 0,
  `is_published`     TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`       INT          NOT NULL DEFAULT 0,
  `created_at`       DATETIME     NOT NULL,
  `updated_at`       DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_premade_projects_slug` (`slug`),
  KEY `ix_premade_projects_published` (`is_published`, `sort_order`),
  KEY `ix_premade_projects_category` (`category_id`),
  KEY `ix_premade_projects_industry` (`industry_id`),
  CONSTRAINT `fk_pp_category` FOREIGN KEY (`category_id`) REFERENCES `project_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pp_industry` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_features` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id`  INT UNSIGNED NOT NULL,
  `title`       VARCHAR(190) NOT NULL,
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `is_included` TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_prf_project` (`project_id`, `sort_order`),
  CONSTRAINT `fk_prf_project` FOREIGN KEY (`project_id`) REFERENCES `premade_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_images` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED NOT NULL,
  `path`       VARCHAR(500) NOT NULL,
  `alt_text`   VARCHAR(255) NOT NULL DEFAULT '',
  `caption`    VARCHAR(255) NOT NULL DEFAULT '',
  `sort_order` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_pri_project` (`project_id`, `sort_order`),
  CONSTRAINT `fk_pri_project` FOREIGN KEY (`project_id`) REFERENCES `premade_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_technology_map` (
  `project_id`    INT UNSIGNED NOT NULL,
  `technology_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`project_id`, `technology_id`),
  KEY `ix_ptm_tech` (`technology_id`),
  CONSTRAINT `fk_prtm_project` FOREIGN KEY (`project_id`)    REFERENCES `premade_projects` (`id`)       ON DELETE CASCADE,
  CONSTRAINT `fk_prtm_tech`    FOREIGN KEY (`technology_id`) REFERENCES `portfolio_technologies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- An enquiry about a premade project. The customer never sees or agrees a
-- price on the website; quoted_amount is filled in by an administrator after
-- the conversation, and stays NULL until then.
CREATE TABLE IF NOT EXISTS `project_orders` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`        VARCHAR(30)  NOT NULL,
  `customer_id`      INT UNSIGNED NOT NULL,
  `project_id`       INT UNSIGNED NULL,
  `project_name`     VARCHAR(190) NOT NULL,
  `preferred_contact` ENUM('whatsapp','email','phone') NOT NULL DEFAULT 'whatsapp',
  `currency`         VARCHAR(6)   NOT NULL DEFAULT 'USD',
  `quoted_amount`    DECIMAL(10,2) NULL,
  `payment_status`   ENUM('pending','paid','refunded','cancelled') NOT NULL DEFAULT 'pending',
  `order_status`     ENUM('new','discussing','quoted','in_setup','delivered','cancelled') NOT NULL DEFAULT 'new',
  `payment_reference` VARCHAR(120) NOT NULL DEFAULT '',
  `domain_name`      VARCHAR(190) NOT NULL DEFAULT '',
  `business_details` TEXT         NULL,
  `requirements`     TEXT         NULL,
  `admin_notes`      TEXT         NULL,
  `ordered_at`       DATETIME     NOT NULL,
  `delivered_at`     DATETIME     NULL,
  `ip_address`       VARCHAR(45)  NOT NULL DEFAULT '',
  `created_at`       DATETIME     NOT NULL,
  `updated_at`       DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_order_reference` (`reference`),
  KEY `ix_project_order_customer` (`customer_id`),
  KEY `ix_project_order_project` (`project_id`),
  KEY `ix_project_order_status` (`order_status`),
  KEY `ix_project_order_payment` (`payment_status`),
  CONSTRAINT `fk_po_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)         ON DELETE CASCADE,
  CONSTRAINT `fk_po_project`  FOREIGN KEY (`project_id`)  REFERENCES `premade_projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Blog
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blog_categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(190) NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `is_published` TINYINT(1)  NOT NULL DEFAULT 1,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL,
  `updated_at`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bcat_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `blog_tags` (
  `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(190) NOT NULL,
  `name` VARCHAR(80)  NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_btag_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`            VARCHAR(190) NOT NULL,
  `title`           VARCHAR(190) NOT NULL,
  `excerpt`         VARCHAR(500) NOT NULL DEFAULT '',
  `content`         LONGTEXT     NULL,
  `category_id`     INT UNSIGNED NULL,
  `author_id`       INT UNSIGNED NULL,
  `author_name`     VARCHAR(120) NOT NULL DEFAULT '',
  `featured_image`  VARCHAR(500) NOT NULL DEFAULT '',
  `reading_minutes` INT UNSIGNED NOT NULL DEFAULT 1,
  `status`          ENUM('draft','scheduled','published') NOT NULL DEFAULT 'draft',
  `published_at`    DATETIME     NULL,
  `seo_title`       VARCHAR(190) NOT NULL DEFAULT '',
  `seo_description` VARCHAR(320) NOT NULL DEFAULT '',
  `og_image`        VARCHAR(500) NOT NULL DEFAULT '',
  `is_featured`     TINYINT(1)   NOT NULL DEFAULT 0,
  `view_count`      INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`      DATETIME     NOT NULL,
  `updated_at`      DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_posts_slug` (`slug`),
  KEY `ix_posts_status` (`status`, `published_at`),
  KEY `ix_posts_category` (`category_id`),
  CONSTRAINT `fk_posts_category` FOREIGN KEY (`category_id`) REFERENCES `blog_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_posts_author`   FOREIGN KEY (`author_id`)   REFERENCES `admins` (`id`)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `blog_post_tags` (
  `post_id` INT UNSIGNED NOT NULL,
  `tag_id`  INT UNSIGNED NOT NULL,
  PRIMARY KEY (`post_id`, `tag_id`),
  KEY `ix_bpt_tag` (`tag_id`),
  CONSTRAINT `fk_bpt_post` FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bpt_tag`  FOREIGN KEY (`tag_id`)  REFERENCES `blog_tags` (`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Social proof
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `testimonials` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_name`  VARCHAR(190) NOT NULL,
  `company`      VARCHAR(190) NOT NULL DEFAULT '',
  `position`     VARCHAR(190) NOT NULL DEFAULT '',
  `image`        VARCHAR(500) NOT NULL DEFAULT '',
  `rating`       TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `quote`        TEXT         NOT NULL,
  `portfolio_id` INT UNSIGNED NULL,
  `is_featured`  TINYINT(1)   NOT NULL DEFAULT 0,
  `is_published` TINYINT(1)   NOT NULL DEFAULT 0,
  `sort_order`   INT          NOT NULL DEFAULT 0,
  `created_at`   DATETIME     NOT NULL,
  `updated_at`   DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_testimonials_published` (`is_published`, `sort_order`),
  CONSTRAINT `fk_testimonials_portfolio` FOREIGN KEY (`portfolio_id`) REFERENCES `portfolio` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `faqs` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question`     VARCHAR(500) NOT NULL,
  `answer`       TEXT         NOT NULL,
  `category`     VARCHAR(80)  NOT NULL DEFAULT 'General',
  `is_published` TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`   INT          NOT NULL DEFAULT 0,
  `created_at`   DATETIME     NOT NULL,
  `updated_at`   DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_faqs_published` (`is_published`, `category`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Leads
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(190) NOT NULL,
  `company`    VARCHAR(190) NOT NULL DEFAULT '',
  `email`      VARCHAR(190) NOT NULL,
  `phone`      VARCHAR(40)  NOT NULL DEFAULT '',
  `country`    VARCHAR(80)  NOT NULL DEFAULT '',
  `subject`    VARCHAR(190) NOT NULL DEFAULT '',
  `message`    TEXT         NOT NULL,
  `status`     ENUM('new','read','replied','archived','spam') NOT NULL DEFAULT 'new',
  `admin_notes` TEXT        NULL,
  `ip_address` VARCHAR(45)  NOT NULL DEFAULT '',
  `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME     NOT NULL,
  `updated_at` DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_contact_status` (`status`, `created_at`),
  KEY `ix_contact_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quote_requests` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`       VARCHAR(30)  NOT NULL,
  `source`          ENUM('quote','journey','package') NOT NULL DEFAULT 'quote',
  `name`            VARCHAR(190) NOT NULL,
  `business_name`   VARCHAR(190) NOT NULL DEFAULT '',
  `email`           VARCHAR(190) NOT NULL,
  `phone`           VARCHAR(40)  NOT NULL DEFAULT '',
  `country`         VARCHAR(80)  NOT NULL DEFAULT '',
  `website`         VARCHAR(255) NOT NULL DEFAULT '',
  `industry_id`     INT UNSIGNED NULL,
  `business_stage`  VARCHAR(80)  NOT NULL DEFAULT '',
  `services_needed` TEXT         NULL,
  `package_id`      INT UNSIGNED NULL,
  `budget_range`    VARCHAR(80)  NOT NULL DEFAULT '',
  `timeline`        VARCHAR(80)  NOT NULL DEFAULT '',
  `project_details` TEXT         NULL,
  `status`          ENUM('new','reviewing','quoted','won','lost','archived') NOT NULL DEFAULT 'new',
  `priority`        ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  `estimated_value` DECIMAL(10,2) NULL,
  `admin_notes`     TEXT         NULL,
  `ip_address`      VARCHAR(45)  NOT NULL DEFAULT '',
  `user_agent`      VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`      DATETIME     NOT NULL,
  `updated_at`      DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_quote_reference` (`reference`),
  KEY `ix_quote_status` (`status`, `created_at`),
  KEY `ix_quote_source` (`source`),
  KEY `ix_quote_email` (`email`),
  CONSTRAINT `fk_quote_industry` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_quote_package`  FOREIGN KEY (`package_id`)  REFERENCES `packages` (`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`          VARCHAR(190) NOT NULL,
  `name`           VARCHAR(190) NOT NULL DEFAULT '',
  `status`         ENUM('subscribed','unsubscribed') NOT NULL DEFAULT 'subscribed',
  `source`         VARCHAR(60)  NOT NULL DEFAULT 'footer',
  `confirm_token`  VARCHAR(64)  NOT NULL DEFAULT '',
  `ip_address`     VARCHAR(45)  NOT NULL DEFAULT '',
  `created_at`     DATETIME     NOT NULL,
  `updated_at`     DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subscriber_email` (`email`),
  KEY `ix_subscriber_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
