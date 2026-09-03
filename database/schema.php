<?php
declare(strict_types=1);

/**
 * Platform schema, expressed once and translated per driver.
 *
 * Keeping DDL in one place lets the Advanced Installer create an identical
 * database on SQLite (zero-config default) or MySQL/MariaDB (production)
 * without a migration framework.
 */

return static function (string $driver): array {
    $isSqlite = $driver === 'sqlite';

    $id = $isSqlite
        ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
        : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $text = $isSqlite ? 'TEXT' : 'LONGTEXT';
    $json = $isSqlite ? 'TEXT' : 'JSON';
    $money = $isSqlite ? 'REAL' : 'DECIMAL(12,2)';
    $bool = $isSqlite ? 'INTEGER' : 'TINYINT(1)';
    $ts = $isSqlite ? 'TEXT' : 'VARCHAR(40)';
    $suffix = $isSqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $tables = [];

    $tables['users'] = "CREATE TABLE IF NOT EXISTS users (
        id {$id},
        uuid VARCHAR(40) NOT NULL,
        name VARCHAR(160) NOT NULL,
        email VARCHAR(190) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(24) NOT NULL DEFAULT 'client',
        status VARCHAR(24) NOT NULL DEFAULT 'active',
        company VARCHAR(160) NULL,
        job_title VARCHAR(160) NULL,
        phone VARCHAR(40) NULL,
        avatar_color VARCHAR(16) NULL,
        last_login_at {$ts} NULL,
        created_at {$ts} NOT NULL,
        updated_at {$ts} NULL
    ){$suffix}";

    $tables['products'] = "CREATE TABLE IF NOT EXISTS products (
        id {$id},
        slug VARCHAR(140) NOT NULL,
        name VARCHAR(190) NOT NULL,
        tagline VARCHAR(255) NOT NULL,
        description {$text} NOT NULL,
        category VARCHAR(60) NOT NULL,
        product_type VARCHAR(40) NOT NULL DEFAULT 'website',
        price {$money} NOT NULL DEFAULT 0,
        compare_price {$money} NULL,
        currency VARCHAR(8) NOT NULL DEFAULT 'USD',
        extended_price {$money} NULL,
        enterprise_price {$money} NULL,
        rating {$money} NOT NULL DEFAULT 0,
        reviews_count INTEGER NOT NULL DEFAULT 0,
        sales_count INTEGER NOT NULL DEFAULT 0,
        layout VARCHAR(24) NOT NULL DEFAULT 'auto',
        version VARCHAR(24) NOT NULL DEFAULT '1.0.0',
        tags {$json} NULL,
        features {$json} NULL,
        specs {$json} NULL,
        includes {$json} NULL,
        pages {$json} NULL,
        lighthouse INTEGER NOT NULL DEFAULT 98,
        demo_url VARCHAR(255) NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'published',
        featured {$bool} NOT NULL DEFAULT 0,
        released_at {$ts} NULL,
        updated_at {$ts} NULL,
        created_at {$ts} NOT NULL
    ){$suffix}";

    $tables['orders'] = "CREATE TABLE IF NOT EXISTS orders (
        id {$id},
        reference VARCHAR(40) NOT NULL,
        user_id INTEGER NULL,
        customer_name VARCHAR(160) NOT NULL,
        customer_email VARCHAR(190) NOT NULL,
        company VARCHAR(160) NULL,
        country VARCHAR(80) NULL,
        subtotal {$money} NOT NULL DEFAULT 0,
        tax {$money} NOT NULL DEFAULT 0,
        total {$money} NOT NULL DEFAULT 0,
        currency VARCHAR(8) NOT NULL DEFAULT 'USD',
        status VARCHAR(24) NOT NULL DEFAULT 'paid',
        payment_method VARCHAR(40) NOT NULL DEFAULT 'invoice',
        notes {$text} NULL,
        created_at {$ts} NOT NULL
    ){$suffix}";

    $tables['order_items'] = "CREATE TABLE IF NOT EXISTS order_items (
        id {$id},
        order_id INTEGER NOT NULL,
        product_id INTEGER NOT NULL,
        product_name VARCHAR(190) NOT NULL,
        license_tier VARCHAR(24) NOT NULL DEFAULT 'standard',
        unit_price {$money} NOT NULL DEFAULT 0,
        quantity INTEGER NOT NULL DEFAULT 1,
        created_at {$ts} NOT NULL
    ){$suffix}";

    $tables['licenses'] = "CREATE TABLE IF NOT EXISTS licenses (
        id {$id},
        license_key VARCHAR(64) NOT NULL,
        order_id INTEGER NULL,
        product_id INTEGER NOT NULL,
        user_id INTEGER NULL,
        tier VARCHAR(24) NOT NULL DEFAULT 'standard',
        seats INTEGER NOT NULL DEFAULT 1,
        domains {$json} NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'active',
        support_until {$ts} NULL,
        created_at {$ts} NOT NULL
    ){$suffix}";

    $tables['deployments'] = "CREATE TABLE IF NOT EXISTS deployments (
        id {$id},
        token VARCHAR(64) NOT NULL,
        license_id INTEGER NULL,
        product_id INTEGER NULL,
        user_id INTEGER NULL,
        site_name VARCHAR(190) NOT NULL,
        target_url VARCHAR(255) NOT NULL,
        environment VARCHAR(24) NOT NULL DEFAULT 'production',
        install_mode VARCHAR(24) NOT NULL DEFAULT 'clean',
        source_platform VARCHAR(40) NULL,
        database_driver VARCHAR(24) NOT NULL DEFAULT 'mysql',
        status VARCHAR(24) NOT NULL DEFAULT 'pending',
        progress INTEGER NOT NULL DEFAULT 0,
        log {$text} NULL,
        created_at {$ts} NOT NULL,
        completed_at {$ts} NULL
    ){$suffix}";

    $tables['leads'] = "CREATE TABLE IF NOT EXISTS leads (
        id {$id},
        reference VARCHAR(40) NOT NULL,
        name VARCHAR(160) NOT NULL,
        email VARCHAR(190) NOT NULL,
        company VARCHAR(160) NULL,
        phone VARCHAR(40) NULL,
        topic VARCHAR(60) NOT NULL DEFAULT 'new-project',
        budget VARCHAR(40) NULL,
        timeline VARCHAR(60) NULL,
        message {$text} NOT NULL,
        source VARCHAR(80) NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'new',
        owner_id INTEGER NULL,
        value {$money} NULL,
        created_at {$ts} NOT NULL,
        updated_at {$ts} NULL
    ){$suffix}";

    $tables['projects'] = "CREATE TABLE IF NOT EXISTS projects (
        id {$id},
        code VARCHAR(24) NOT NULL,
        name VARCHAR(190) NOT NULL,
        client_id INTEGER NOT NULL,
        lead_id INTEGER NULL,
        summary {$text} NULL,
        phase VARCHAR(40) NOT NULL DEFAULT 'align',
        status VARCHAR(24) NOT NULL DEFAULT 'active',
        health VARCHAR(24) NOT NULL DEFAULT 'green',
        progress INTEGER NOT NULL DEFAULT 0,
        budget {$money} NULL,
        spent {$money} NULL,
        started_at {$ts} NULL,
        due_at {$ts} NULL,
        created_at {$ts} NOT NULL
    ){$suffix}";

    $tables['project_milestones'] = "CREATE TABLE IF NOT EXISTS project_milestones (
        id {$id},
        project_id INTEGER NOT NULL,
        title VARCHAR(190) NOT NULL,
        detail VARCHAR(255) NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'pending',
        due_at {$ts} NULL,
        position INTEGER NOT NULL DEFAULT 0
    ){$suffix}";

    $tables['tasks'] = "CREATE TABLE IF NOT EXISTS tasks (
        id {$id},
        project_id INTEGER NULL,
        assignee_id INTEGER NULL,
        title VARCHAR(190) NOT NULL,
        detail VARCHAR(255) NULL,
        priority VARCHAR(24) NOT NULL DEFAULT 'normal',
        status VARCHAR(24) NOT NULL DEFAULT 'open',
        due_at {$ts} NULL,
        created_at {$ts} NOT NULL
    ){$suffix}";

    $tables['tickets'] = "CREATE TABLE IF NOT EXISTS tickets (
        id {$id},
        reference VARCHAR(40) NOT NULL,
        user_id INTEGER NULL,
        assignee_id INTEGER NULL,
        subject VARCHAR(190) NOT NULL,
        body {$text} NOT NULL,
        category VARCHAR(40) NOT NULL DEFAULT 'general',
        priority VARCHAR(24) NOT NULL DEFAULT 'normal',
        status VARCHAR(24) NOT NULL DEFAULT 'open',
        created_at {$ts} NOT NULL,
        updated_at {$ts} NULL
    ){$suffix}";

    $tables['ticket_replies'] = "CREATE TABLE IF NOT EXISTS ticket_replies (
        id {$id},
        ticket_id INTEGER NOT NULL,
        user_id INTEGER NULL,
        author_name VARCHAR(160) NOT NULL,
        body {$text} NOT NULL,
        created_at {$ts} NOT NULL
    ){$suffix}";

    $tables['invoices'] = "CREATE TABLE IF NOT EXISTS invoices (
        id {$id},
        number VARCHAR(40) NOT NULL,
        user_id INTEGER NOT NULL,
        project_id INTEGER NULL,
        description VARCHAR(255) NOT NULL,
        amount {$money} NOT NULL DEFAULT 0,
        currency VARCHAR(8) NOT NULL DEFAULT 'USD',
        status VARCHAR(24) NOT NULL DEFAULT 'due',
        issued_at {$ts} NOT NULL,
        due_at {$ts} NULL,
        paid_at {$ts} NULL
    ){$suffix}";

    $tables['case_studies'] = "CREATE TABLE IF NOT EXISTS case_studies (
        id {$id},
        slug VARCHAR(140) NOT NULL,
        client VARCHAR(160) NOT NULL,
        title VARCHAR(190) NOT NULL,
        summary {$text} NOT NULL,
        body {$text} NULL,
        industry VARCHAR(60) NOT NULL,
        service VARCHAR(60) NOT NULL,
        region VARCHAR(60) NULL,
        duration VARCHAR(40) NULL,
        year VARCHAR(8) NULL,
        accent VARCHAR(16) NOT NULL DEFAULT 'blue',
        layout VARCHAR(24) NOT NULL DEFAULT 'auto',
        metrics {$json} NULL,
        challenge {$text} NULL,
        approach {$json} NULL,
        outcome {$text} NULL,
        stack {$json} NULL,
        quote {$text} NULL,
        quote_by VARCHAR(160) NULL,
        quote_role VARCHAR(160) NULL,
        featured {$bool} NOT NULL DEFAULT 0,
        published_at {$ts} NULL,
        created_at {$ts} NOT NULL
    ){$suffix}";

    $tables['resources'] = "CREATE TABLE IF NOT EXISTS resources (
        id {$id},
        slug VARCHAR(140) NOT NULL,
        title VARCHAR(190) NOT NULL,
        excerpt {$text} NOT NULL,
        body {$text} NULL,
        type VARCHAR(40) NOT NULL DEFAULT 'article',
        topic VARCHAR(60) NOT NULL,
        author VARCHAR(160) NULL,
        author_role VARCHAR(160) NULL,
        read_minutes INTEGER NOT NULL DEFAULT 6,
        accent VARCHAR(16) NOT NULL DEFAULT 'blue',
        featured {$bool} NOT NULL DEFAULT 0,
        published_at {$ts} NULL,
        created_at {$ts} NOT NULL
    ){$suffix}";

    $tables['settings'] = "CREATE TABLE IF NOT EXISTS settings (
        id {$id},
        setting_key VARCHAR(120) NOT NULL,
        setting_value {$text} NULL,
        setting_group VARCHAR(60) NOT NULL DEFAULT 'general',
        updated_at {$ts} NULL
    ){$suffix}";

    $tables['subscribers'] = "CREATE TABLE IF NOT EXISTS subscribers (
        id {$id},
        email VARCHAR(190) NOT NULL,
        source VARCHAR(80) NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'subscribed',
        created_at {$ts} NOT NULL
    ){$suffix}";

    $tables['login_attempts'] = "CREATE TABLE IF NOT EXISTS login_attempts (
        id {$id},
        email VARCHAR(190) NOT NULL,
        ip_address VARCHAR(64) NOT NULL,
        successful {$bool} NOT NULL DEFAULT 0,
        created_at {$ts} NOT NULL
    ){$suffix}";

    $tables['activity_log'] = "CREATE TABLE IF NOT EXISTS activity_log (
        id {$id},
        user_id INTEGER NULL,
        action VARCHAR(80) NOT NULL,
        description VARCHAR(255) NULL,
        ip_address VARCHAR(64) NULL,
        created_at {$ts} NOT NULL
    ){$suffix}";

    $tables['migrations'] = "CREATE TABLE IF NOT EXISTS migrations (
        id {$id},
        name VARCHAR(190) NOT NULL,
        batch INTEGER NOT NULL DEFAULT 1,
        ran_at {$ts} NOT NULL
    ){$suffix}";

    // Indexes are declared separately so both drivers accept them.
    $indexes = [
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email ON users (email)',
        'CREATE INDEX IF NOT EXISTS idx_users_role ON users (role)',
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_products_slug ON products (slug)',
        'CREATE INDEX IF NOT EXISTS idx_products_cat ON products (category, status)',
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_orders_ref ON orders (reference)',
        'CREATE INDEX IF NOT EXISTS idx_orders_user ON orders (user_id)',
        'CREATE INDEX IF NOT EXISTS idx_items_order ON order_items (order_id)',
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_licenses_key ON licenses (license_key)',
        'CREATE INDEX IF NOT EXISTS idx_licenses_user ON licenses (user_id)',
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_deploy_token ON deployments (token)',
        'CREATE INDEX IF NOT EXISTS idx_deploy_user ON deployments (user_id)',
        'CREATE INDEX IF NOT EXISTS idx_leads_status ON leads (status)',
        'CREATE INDEX IF NOT EXISTS idx_projects_client ON projects (client_id)',
        'CREATE INDEX IF NOT EXISTS idx_milestones_project ON project_milestones (project_id)',
        'CREATE INDEX IF NOT EXISTS idx_tasks_assignee ON tasks (assignee_id, status)',
        'CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets (status)',
        'CREATE INDEX IF NOT EXISTS idx_replies_ticket ON ticket_replies (ticket_id)',
        'CREATE INDEX IF NOT EXISTS idx_invoices_user ON invoices (user_id)',
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_cases_slug ON case_studies (slug)',
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_resources_slug ON resources (slug)',
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_settings_key ON settings (setting_key)',
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_subs_email ON subscribers (email)',
        'CREATE INDEX IF NOT EXISTS idx_attempts ON login_attempts (email, created_at)',
        'CREATE INDEX IF NOT EXISTS idx_activity_user ON activity_log (user_id, created_at)',
    ];

    return ['tables' => $tables, 'indexes' => $indexes];
};
