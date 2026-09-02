<?php
/**
 * The database schema. Written once by the installer.
 *
 * The same statements run on MySQL and SQLite, so the types stay to the
 * common ground: INTEGER / TEXT / VARCHAR / DECIMAL / DATE / DATETIME.
 */

function schema_statements(): array
{
    $mysql = db_driver() === 'mysql';
    $id    = $mysql
        ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
        : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $fk    = $mysql ? 'INT UNSIGNED' : 'INTEGER';
    $tail  = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    $t = [];

    /* --- people ------------------------------------------------------- */
    $t[] = "CREATE TABLE IF NOT EXISTS users (
        id {$id},
        name VARCHAR(160) NOT NULL,
        email VARCHAR(190) NOT NULL,
        password_hash VARCHAR(255) NOT NULL DEFAULT '',
        role VARCHAR(20) NOT NULL DEFAULT 'client',
        phone VARCHAR(60) NOT NULL DEFAULT '',
        company VARCHAR(160) NOT NULL DEFAULT '',
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        must_change_password INTEGER NOT NULL DEFAULT 0,
        last_login_at DATETIME NULL,
        created_at DATETIME NOT NULL
    ){$tail}";
    $t[] = "CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email ON users (email)";

    /* --- editable site content ---------------------------------------- */
    $t[] = "CREATE TABLE IF NOT EXISTS settings (
        id {$id},
        group_name VARCHAR(60) NOT NULL DEFAULT 'global',
        setting_key VARCHAR(120) NOT NULL,
        label VARCHAR(190) NOT NULL DEFAULT '',
        value TEXT NULL,
        field_type VARCHAR(20) NOT NULL DEFAULT 'text',
        sort INTEGER NOT NULL DEFAULT 0
    ){$tail}";
    $t[] = "CREATE UNIQUE INDEX IF NOT EXISTS idx_settings_key ON settings (setting_key)";

    $t[] = "CREATE TABLE IF NOT EXISTS services (
        id {$id},
        slug VARCHAR(160) NOT NULL,
        title VARCHAR(190) NOT NULL,
        subtitle VARCHAR(190) NOT NULL DEFAULT '',
        icon VARCHAR(20) NOT NULL DEFAULT '',
        body TEXT NULL,
        bullets TEXT NULL,
        sort INTEGER NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1
    ){$tail}";

    $t[] = "CREATE TABLE IF NOT EXISTS industries (
        id {$id},
        slug VARCHAR(160) NOT NULL,
        title VARCHAR(190) NOT NULL,
        icon VARCHAR(20) NOT NULL DEFAULT '',
        body TEXT NULL,
        bullets TEXT NULL,
        sort INTEGER NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1
    ){$tail}";

    $t[] = "CREATE TABLE IF NOT EXISTS packages (
        id {$id},
        name VARCHAR(120) NOT NULL,
        kind VARCHAR(20) NOT NULL DEFAULT 'build',
        price VARCHAR(40) NOT NULL DEFAULT '',
        period VARCHAR(40) NOT NULL DEFAULT 'One-off',
        blurb VARCHAR(255) NOT NULL DEFAULT '',
        features TEXT NULL,
        is_featured INTEGER NOT NULL DEFAULT 0,
        sort INTEGER NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1
    ){$tail}";

    $t[] = "CREATE TABLE IF NOT EXISTS addons (
        id {$id},
        name VARCHAR(160) NOT NULL,
        price VARCHAR(40) NOT NULL DEFAULT '',
        blurb VARCHAR(255) NOT NULL DEFAULT '',
        sort INTEGER NOT NULL DEFAULT 0
    ){$tail}";

    $t[] = "CREATE TABLE IF NOT EXISTS faqs (
        id {$id},
        page VARCHAR(40) NOT NULL DEFAULT 'services',
        question VARCHAR(255) NOT NULL,
        answer TEXT NULL,
        sort INTEGER NOT NULL DEFAULT 0
    ){$tail}";

    $t[] = "CREATE TABLE IF NOT EXISTS testimonials (
        id {$id},
        quote TEXT NOT NULL,
        author VARCHAR(120) NOT NULL,
        role VARCHAR(160) NOT NULL DEFAULT '',
        sort INTEGER NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1
    ){$tail}";

    /* --- portfolio: completed work ------------------------------------- */
    $t[] = "CREATE TABLE IF NOT EXISTS portfolio (
        id {$id},
        slug VARCHAR(190) NOT NULL,
        title VARCHAR(190) NOT NULL,
        client_name VARCHAR(190) NOT NULL DEFAULT '',
        sector VARCHAR(120) NOT NULL DEFAULT '',
        summary VARCHAR(400) NOT NULL DEFAULT '',
        body TEXT NULL,
        services_used TEXT NULL,
        tech TEXT NULL,
        live_url VARCHAR(255) NOT NULL DEFAULT '',
        cover_image VARCHAR(255) NOT NULL DEFAULT '',
        completed_on DATE NULL,
        visibility VARCHAR(20) NOT NULL DEFAULT 'public',
        is_featured INTEGER NOT NULL DEFAULT 0,
        sort INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL
    ){$tail}";

    /* --- marketplace: premade projects for sale ------------------------ */
    $t[] = "CREATE TABLE IF NOT EXISTS products (
        id {$id},
        slug VARCHAR(190) NOT NULL,
        title VARCHAR(190) NOT NULL,
        category VARCHAR(120) NOT NULL DEFAULT '',
        summary VARCHAR(400) NOT NULL DEFAULT '',
        body TEXT NULL,
        features TEXT NULL,
        tech TEXT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        sale_price DECIMAL(10,2) NULL,
        demo_url VARCHAR(255) NOT NULL DEFAULT '',
        cover_image VARCHAR(255) NOT NULL DEFAULT '',
        pages INTEGER NOT NULL DEFAULT 0,
        includes_setup INTEGER NOT NULL DEFAULT 1,
        is_active INTEGER NOT NULL DEFAULT 1,
        is_featured INTEGER NOT NULL DEFAULT 0,
        sort INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL
    ){$tail}";

    $t[] = "CREATE TABLE IF NOT EXISTS orders (
        id {$id},
        reference VARCHAR(40) NOT NULL,
        product_id {$fk} NULL,
        user_id {$fk} NULL,
        buyer_name VARCHAR(160) NOT NULL,
        buyer_email VARCHAR(190) NOT NULL,
        buyer_phone VARCHAR(60) NOT NULL DEFAULT '',
        buyer_company VARCHAR(160) NOT NULL DEFAULT '',
        amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        wants_setup INTEGER NOT NULL DEFAULT 0,
        notes TEXT NULL,
        admin_notes TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'new',
        created_at DATETIME NOT NULL
    ){$tail}";

    /* --- client projects ----------------------------------------------- */
    $t[] = "CREATE TABLE IF NOT EXISTS projects (
        id {$id},
        user_id {$fk} NULL,
        portfolio_id {$fk} NULL,
        name VARCHAR(190) NOT NULL,
        reference VARCHAR(40) NOT NULL DEFAULT '',
        status VARCHAR(20) NOT NULL DEFAULT 'building',
        owner_name VARCHAR(160) NOT NULL DEFAULT '',
        owner_email VARCHAR(190) NOT NULL DEFAULT '',
        owner_phone VARCHAR(60) NOT NULL DEFAULT '',
        company VARCHAR(190) NOT NULL DEFAULT '',
        domain VARCHAR(190) NOT NULL DEFAULT '',
        domain_registrar VARCHAR(160) NOT NULL DEFAULT '',
        domain_expires_on DATE NULL,
        hosting_provider VARCHAR(160) NOT NULL DEFAULT '',
        hosting_plan VARCHAR(160) NOT NULL DEFAULT '',
        hosting_expires_on DATE NULL,
        server_ip VARCHAR(60) NOT NULL DEFAULT '',
        ssl_provider VARCHAR(160) NOT NULL DEFAULT '',
        ssl_expires_on DATE NULL,
        email_provider VARCHAR(160) NOT NULL DEFAULT '',
        email_accounts INTEGER NOT NULL DEFAULT 0,
        email_expires_on DATE NULL,
        care_plan VARCHAR(120) NOT NULL DEFAULT '',
        care_renews_on DATE NULL,
        launched_on DATE NULL,
        notes TEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL
    ){$tail}";

    /* --- support and maintenance --------------------------------------- */
    $t[] = "CREATE TABLE IF NOT EXISTS tickets (
        id {$id},
        reference VARCHAR(40) NOT NULL,
        project_id {$fk} NULL,
        user_id {$fk} NULL,
        subject VARCHAR(255) NOT NULL,
        category VARCHAR(30) NOT NULL DEFAULT 'support',
        priority VARCHAR(20) NOT NULL DEFAULT 'normal',
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL
    ){$tail}";

    $t[] = "CREATE TABLE IF NOT EXISTS ticket_messages (
        id {$id},
        ticket_id {$fk} NOT NULL,
        author_id {$fk} NULL,
        author_name VARCHAR(160) NOT NULL DEFAULT '',
        author_type VARCHAR(20) NOT NULL DEFAULT 'client',
        body TEXT NOT NULL,
        is_internal INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL
    ){$tail}";

    $t[] = "CREATE TABLE IF NOT EXISTS maintenance_logs (
        id {$id},
        project_id {$fk} NOT NULL,
        title VARCHAR(255) NOT NULL,
        kind VARCHAR(30) NOT NULL DEFAULT 'update',
        body TEXT NULL,
        performed_on DATE NULL,
        performed_by VARCHAR(160) NOT NULL DEFAULT '',
        visible_to_client INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL
    ){$tail}";

    /* --- enquiries from the public site --------------------------------- */
    $t[] = "CREATE TABLE IF NOT EXISTS enquiries (
        id {$id},
        name VARCHAR(160) NOT NULL,
        email VARCHAR(190) NOT NULL,
        phone VARCHAR(60) NOT NULL DEFAULT '',
        company VARCHAR(160) NOT NULL DEFAULT '',
        service VARCHAR(120) NOT NULL DEFAULT '',
        budget VARCHAR(60) NOT NULL DEFAULT '',
        message TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'new',
        admin_notes TEXT NULL,
        mail_sent INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL
    ){$tail}";

    /* --- audit trail ----------------------------------------------------- */
    $t[] = "CREATE TABLE IF NOT EXISTS activity_log (
        id {$id},
        user_id {$fk} NULL,
        actor VARCHAR(160) NOT NULL DEFAULT '',
        action VARCHAR(190) NOT NULL,
        entity VARCHAR(60) NOT NULL DEFAULT '',
        entity_id INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL
    ){$tail}";

    /* --- login throttling ------------------------------------------------ */
    $t[] = "CREATE TABLE IF NOT EXISTS login_attempts (
        id {$id},
        identifier VARCHAR(190) NOT NULL,
        attempted_at DATETIME NOT NULL
    ){$tail}";

    return $t;
}

function schema_create(): void
{
    foreach (schema_statements() as $sql) {
        db()->exec($sql);
    }
}
