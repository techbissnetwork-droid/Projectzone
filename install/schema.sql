-- TECHBISS platform schema (MySQL 5.7+ / MariaDB 10.3+)

CREATE TABLE settings (
  skey        VARCHAR(64)  NOT NULL PRIMARY KEY,
  svalue      TEXT         NULL,
  updated_at  DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  phone         VARCHAR(40)  NULL,
  company       VARCHAR(160) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          VARCHAR(16)  NOT NULL DEFAULT 'client',
  status        VARCHAR(16)  NOT NULL DEFAULT 'active',
  must_change   TINYINT(1)   NOT NULL DEFAULT 0,
  last_login_at DATETIME     NULL,
  created_at    DATETIME     NOT NULL,
  UNIQUE KEY uq_users_email (email),
  KEY ix_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE login_attempts (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  identifier VARCHAR(190) NOT NULL,
  ip         VARCHAR(45)  NOT NULL,
  created_at DATETIME     NOT NULL,
  KEY ix_attempts (identifier, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE services (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug       VARCHAR(120) NOT NULL,
  title      VARCHAR(160) NOT NULL,
  summary    VARCHAR(400) NULL,
  body       TEXT         NULL,
  features   TEXT         NULL,
  tech       VARCHAR(400) NULL,
  icon       VARCHAR(40)  NOT NULL DEFAULT 'websites',
  sort_order INT          NOT NULL DEFAULT 0,
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL,
  UNIQUE KEY uq_services_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Completed work. visibility decides whether the public portfolio shows it.
CREATE TABLE portfolio (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(180) NOT NULL,
  slug         VARCHAR(180) NOT NULL,
  client_name  VARCHAR(160) NULL,
  category     VARCHAR(80)  NULL,
  summary      VARCHAR(500) NULL,
  body         TEXT         NULL,
  tech         VARCHAR(400) NULL,
  results      TEXT         NULL,
  live_url     VARCHAR(255) NULL,
  cover_image  VARCHAR(255) NULL,
  visibility   VARCHAR(16)  NOT NULL DEFAULT 'private',
  is_featured  TINYINT(1)   NOT NULL DEFAULT 0,
  completed_on DATE         NULL,
  sort_order   INT          NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL,
  UNIQUE KEY uq_portfolio_slug (slug),
  KEY ix_portfolio_vis (visibility)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Marketplace: premade projects people can buy.
CREATE TABLE products (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(180) NOT NULL,
  slug         VARCHAR(180) NOT NULL,
  category     VARCHAR(80)  NULL,
  summary      VARCHAR(500) NULL,
  body         TEXT         NULL,
  tech         VARCHAR(400) NULL,
  includes     TEXT         NULL,
  price        DECIMAL(12,2) NOT NULL DEFAULT 0,
  sale_price   DECIMAL(12,2) NULL,
  demo_url     VARCHAR(255) NULL,
  cover_image  VARCHAR(255) NULL,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  is_featured  TINYINT(1)   NOT NULL DEFAULT 0,
  sales_count  INT          NOT NULL DEFAULT 0,
  sort_order   INT          NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL,
  UNIQUE KEY uq_products_slug (slug),
  KEY ix_products_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE orders (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  reference      VARCHAR(32)  NOT NULL,
  user_id        INT UNSIGNED NULL,
  product_id     INT UNSIGNED NULL,
  buyer_name     VARCHAR(160) NOT NULL,
  buyer_email    VARCHAR(190) NOT NULL,
  buyer_phone    VARCHAR(40)  NULL,
  amount         DECIMAL(12,2) NOT NULL DEFAULT 0,
  currency       VARCHAR(8)   NOT NULL DEFAULT 'NPR',
  status         VARCHAR(16)  NOT NULL DEFAULT 'pending',
  payment_method VARCHAR(120) NULL,
  payment_method_id INT UNSIGNED NULL,
  payment_ref    VARCHAR(120) NULL,
  paid_at        DATETIME     NULL,
  access_token   VARCHAR(64)  NULL,
  notes          TEXT         NULL,
  created_at     DATETIME     NOT NULL,
  updated_at     DATETIME     NOT NULL,
  UNIQUE KEY uq_orders_ref (reference),
  KEY ix_orders_token (access_token),
  KEY ix_orders_user (user_id),
  KEY ix_orders_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A delivered site + everything that expires on it.
CREATE TABLE projects (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id           INT UNSIGNED NULL,
  portfolio_id      INT UNSIGNED NULL,
  name              VARCHAR(180) NOT NULL,
  site_url          VARCHAR(255) NULL,
  package           VARCHAR(120) NULL,
  status            VARCHAR(20)  NOT NULL DEFAULT 'planning',

  owner_name        VARCHAR(160) NULL,
  owner_email       VARCHAR(190) NULL,
  owner_phone       VARCHAR(40)  NULL,

  domain_name       VARCHAR(190) NULL,
  domain_registrar  VARCHAR(120) NULL,
  domain_expires_on DATE         NULL,

  hosting_provider  VARCHAR(120) NULL,
  hosting_plan      VARCHAR(120) NULL,
  hosting_expires_on DATE        NULL,

  ssl_issuer        VARCHAR(120) NULL,
  ssl_expires_on    DATE         NULL,

  email_provider    VARCHAR(120) NULL,
  email_accounts    INT          NOT NULL DEFAULT 0,

  started_on        DATE         NULL,
  launched_on       DATE         NULL,
  monthly_fee       DECIMAL(12,2) NULL,
  notes             TEXT         NULL,
  created_at        DATETIME     NOT NULL,
  updated_at        DATETIME     NOT NULL,
  KEY ix_projects_user (user_id),
  KEY ix_projects_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- What was actually done on a project, and when. Visible to the client.
CREATE TABLE maintenance_logs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  project_id  INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NULL,
  kind        VARCHAR(24)  NOT NULL DEFAULT 'maintenance',
  title       VARCHAR(200) NOT NULL,
  body        TEXT         NULL,
  performed_on DATE        NOT NULL,
  created_at  DATETIME     NOT NULL,
  KEY ix_mlogs_project (project_id, performed_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Support / maintenance / upgrade conversations.
CREATE TABLE tickets (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  reference    VARCHAR(32)  NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  project_id   INT UNSIGNED NULL,
  subject      VARCHAR(220) NOT NULL,
  category     VARCHAR(24)  NOT NULL DEFAULT 'support',
  priority     VARCHAR(16)  NOT NULL DEFAULT 'normal',
  status       VARCHAR(20)  NOT NULL DEFAULT 'open',
  last_reply_by VARCHAR(16) NOT NULL DEFAULT 'client',
  created_at   DATETIME     NOT NULL,
  updated_at   DATETIME     NOT NULL,
  UNIQUE KEY uq_tickets_ref (reference),
  KEY ix_tickets_user (user_id, status),
  KEY ix_tickets_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ticket_messages (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ticket_id  INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  body       TEXT         NOT NULL,
  is_staff   TINYINT(1)   NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL,
  KEY ix_tmsg_ticket (ticket_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE enquiries (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(160) NOT NULL,
  email      VARCHAR(190) NOT NULL,
  phone      VARCHAR(40)  NULL,
  service    VARCHAR(120) NULL,
  message    TEXT         NOT NULL,
  status     VARCHAR(16)  NOT NULL DEFAULT 'new',
  created_at DATETIME     NOT NULL,
  KEY ix_enq_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE activity_log (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,
  action     VARCHAR(80)  NOT NULL,
  entity     VARCHAR(40)  NULL,
  entity_id  INT UNSIGNED NULL,
  detail     VARCHAR(400) NULL,
  ip         VARCHAR(45)  NULL,
  created_at DATETIME     NOT NULL,
  KEY ix_activity_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══════════════════════════════════════════════════════════════
-- Customisation: pages, menus and repeatable content blocks
-- ══════════════════════════════════════════════════════════════

-- Arbitrary pages the admin creates (About, Privacy, Terms, anything).
CREATE TABLE pages (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug         VARCHAR(180) NOT NULL,
  title        VARCHAR(200) NOT NULL,
  subtitle     VARCHAR(400) NULL,
  eyebrow      VARCHAR(120) NULL,
  body         MEDIUMTEXT   NULL,
  meta_title   VARCHAR(200) NULL,
  meta_desc    VARCHAR(400) NULL,
  hero_style   VARCHAR(20)  NOT NULL DEFAULT 'standard',
  status       VARCHAR(16)  NOT NULL DEFAULT 'draft',
  show_cta     TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order   INT          NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL,
  updated_at   DATETIME     NOT NULL,
  UNIQUE KEY uq_pages_slug (slug),
  KEY ix_pages_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Header and footer menus, built by the admin.
CREATE TABLE nav_items (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  location   VARCHAR(24)  NOT NULL DEFAULT 'header',
  label      VARCHAR(120) NOT NULL,
  url        VARCHAR(255) NULL,
  page_id    INT UNSIGNED NULL,
  new_tab    TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order INT          NOT NULL DEFAULT 0,
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL,
  KEY ix_nav_loc (location, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every repeatable list on the site: hero stats, the offline-to-online
-- journey, the process timeline, trust pillars, the architecture diagram
-- and the business-transformation examples.
CREATE TABLE content_items (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind       VARCHAR(24)  NOT NULL,
  label      VARCHAR(200) NULL,
  title      VARCHAR(250) NULL,
  body       TEXT         NULL,
  extra      TEXT         NULL,
  meta1      VARCHAR(200) NULL,
  meta2      VARCHAR(200) NULL,
  icon       VARCHAR(40)  NULL,
  sort_order INT          NOT NULL DEFAULT 0,
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL,
  KEY ix_content_kind (kind, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══════════════════════════════════════════════════════════════
-- Marketplace payments
-- ══════════════════════════════════════════════════════════════

-- How buyers can pay. "manual" methods just show instructions and are
-- confirmed by hand; the rest hand off to a gateway and are verified.
CREATE TABLE payment_methods (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code           VARCHAR(40)  NOT NULL,
  name           VARCHAR(120) NOT NULL,
  provider       VARCHAR(24)  NOT NULL DEFAULT 'manual',
  summary        VARCHAR(300) NULL,
  instructions   TEXT         NULL,
  account_name   VARCHAR(160) NULL,
  account_number VARCHAR(160) NULL,
  config         TEXT         NULL,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  is_test        TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order     INT          NOT NULL DEFAULT 0,
  created_at     DATETIME     NOT NULL,
  UNIQUE KEY uq_pay_code (code),
  KEY ix_pay_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per attempt, so a failed or abandoned payment is never silently lost.
CREATE TABLE payment_attempts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id     INT UNSIGNED NOT NULL,
  method_id    INT UNSIGNED NULL,
  provider     VARCHAR(24)  NOT NULL,
  status       VARCHAR(16)  NOT NULL DEFAULT 'started',
  amount       DECIMAL(12,2) NOT NULL DEFAULT 0,
  currency     VARCHAR(8)   NOT NULL DEFAULT 'NPR',
  gateway_ref  VARCHAR(190) NULL,
  message      VARCHAR(400) NULL,
  payload      TEXT         NULL,
  created_at   DATETIME     NOT NULL,
  updated_at   DATETIME     NOT NULL,
  KEY ix_attempt_order (order_id, created_at),
  KEY ix_attempt_ref (gateway_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Single-use sign-in codes emailed to the account holder.
CREATE TABLE login_codes (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(190) NOT NULL,
  code_hash  VARCHAR(255) NOT NULL,
  audience   VARCHAR(16)  NOT NULL DEFAULT 'client',
  expires_at DATETIME     NOT NULL,
  attempts   INT          NOT NULL DEFAULT 0,
  used_at    DATETIME     NULL,
  ip         VARCHAR(45)  NULL,
  created_at DATETIME     NOT NULL,
  KEY ix_code_email (email, created_at),
  KEY ix_code_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
