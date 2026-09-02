<?php
/**
 * Table definitions, emitted for whichever PDO driver is configured.
 * Kept in one place so the installer and any future migration agree.
 */

function schema_pk(): string
{
    return db_driver() === 'sqlite'
        ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
        : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
}

function schema_tables(): array
{
    $pk = schema_pk();

    return [
        'users' => "
            id {$pk},
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'admin',
            created_at DATETIME NULL,
            last_login_at DATETIME NULL
        ",

        /* Every editable string on the public site, keyed and grouped by page. */
        'content' => "
            id {$pk},
            ckey VARCHAR(120) NOT NULL,
            cvalue TEXT NULL,
            clabel VARCHAR(190) NOT NULL,
            cgroup VARCHAR(60) NOT NULL DEFAULT 'global',
            ctype VARCHAR(20) NOT NULL DEFAULT 'text',
            sort INT NOT NULL DEFAULT 0
        ",

        'services' => "
            id {$pk},
            sort INT NOT NULL DEFAULT 0,
            code VARCHAR(10) NULL,
            anchor VARCHAR(60) NULL,
            title VARCHAR(190) NOT NULL,
            summary TEXT NULL,
            kicker VARCHAR(190) NULL,
            heading VARCHAR(255) NULL,
            body TEXT NULL,
            bullets TEXT NULL,
            panel_title VARCHAR(120) NULL,
            panel TEXT NULL,
            size VARCHAR(1) NOT NULL DEFAULT 'c',
            is_active INT NOT NULL DEFAULT 1
        ",

        'industries' => "
            id {$pk},
            sort INT NOT NULL DEFAULT 0,
            code VARCHAR(10) NULL,
            anchor VARCHAR(60) NULL,
            name VARCHAR(120) NOT NULL,
            blurb VARCHAR(255) NULL,
            kicker VARCHAR(190) NULL,
            heading VARCHAR(255) NULL,
            summary TEXT NULL,
            bullets TEXT NULL,
            gradient INT NOT NULL DEFAULT 1,
            is_active INT NOT NULL DEFAULT 1
        ",

        'tiers' => "
            id {$pk},
            sort INT NOT NULL DEFAULT 0,
            grp VARCHAR(10) NOT NULL DEFAULT 'build',
            name VARCHAR(120) NOT NULL,
            price VARCHAR(60) NULL,
            period VARCHAR(120) NULL,
            description TEXT NULL,
            features TEXT NULL,
            tag VARCHAR(60) NULL,
            cta_label VARCHAR(120) NULL,
            is_featured INT NOT NULL DEFAULT 0,
            is_active INT NOT NULL DEFAULT 1
        ",

        'addons' => "
            id {$pk},
            sort INT NOT NULL DEFAULT 0,
            code VARCHAR(10) NULL,
            title VARCHAR(190) NOT NULL,
            description TEXT NULL,
            size VARCHAR(1) NOT NULL DEFAULT 'c',
            is_active INT NOT NULL DEFAULT 1
        ",

        'compare_rows' => "
            id {$pk},
            sort INT NOT NULL DEFAULT 0,
            label VARCHAR(190) NOT NULL,
            col1 VARCHAR(120) NULL,
            col2 VARCHAR(120) NULL,
            col3 VARCHAR(120) NULL,
            is_active INT NOT NULL DEFAULT 1
        ",

        'faqs' => "
            id {$pk},
            sort INT NOT NULL DEFAULT 0,
            page VARCHAR(40) NOT NULL DEFAULT 'services',
            question VARCHAR(255) NOT NULL,
            answer TEXT NULL,
            is_active INT NOT NULL DEFAULT 1
        ",

        'testimonials' => "
            id {$pk},
            sort INT NOT NULL DEFAULT 0,
            quote TEXT NOT NULL,
            name VARCHAR(120) NOT NULL,
            role VARCHAR(190) NULL,
            avatar VARCHAR(4) NULL,
            is_active INT NOT NULL DEFAULT 1
        ",

        'stats' => "
            id {$pk},
            sort INT NOT NULL DEFAULT 0,
            value VARCHAR(30) NOT NULL,
            suffix VARCHAR(20) NULL,
            label VARCHAR(255) NULL,
            is_active INT NOT NULL DEFAULT 1
        ",

        'steps' => "
            id {$pk},
            sort INT NOT NULL DEFAULT 0,
            code VARCHAR(10) NULL,
            title VARCHAR(120) NOT NULL,
            body TEXT NULL,
            is_active INT NOT NULL DEFAULT 1
        ",

        'enquiries' => "
            id {$pk},
            name VARCHAR(190) NOT NULL,
            email VARCHAR(190) NOT NULL,
            company VARCHAR(190) NULL,
            phone VARCHAR(60) NULL,
            service VARCHAR(190) NULL,
            budget VARCHAR(120) NULL,
            message TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'new',
            notes TEXT NULL,
            ip VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            mailed INT NOT NULL DEFAULT 0,
            created_at DATETIME NULL
        ",

        'rules' => "
            id {$pk},
            sort INT NOT NULL DEFAULT 0,
            code VARCHAR(10) NULL,
            title VARCHAR(190) NOT NULL,
            body TEXT NULL,
            size VARCHAR(1) NOT NULL DEFAULT 'c',
            is_active INT NOT NULL DEFAULT 1
        ",

        'team' => "
            id {$pk},
            sort INT NOT NULL DEFAULT 0,
            initial VARCHAR(4) NULL,
            name VARCHAR(120) NOT NULL,
            role VARCHAR(120) NULL,
            body TEXT NULL,
            is_active INT NOT NULL DEFAULT 1
        ",

        /* Failed logins and form posts, for throttling. */
        'throttle' => "
            id {$pk},
            bucket VARCHAR(60) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            created_at DATETIME NULL
        ",
    ];
}

function schema_create_all(): void
{
    $suffix = db_driver() === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    foreach (schema_tables() as $name => $cols) {
        db()->exec("CREATE TABLE IF NOT EXISTS {$name} ({$cols}){$suffix}");
    }
    // Unique/lookup indexes, created separately so both drivers accept them.
    $idx = [
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email ON users (email)',
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_content_key ON content (ckey)',
        'CREATE INDEX IF NOT EXISTS idx_enquiries_created ON enquiries (created_at)',
        'CREATE INDEX IF NOT EXISTS idx_throttle_lookup ON throttle (bucket, ip)',
    ];
    foreach ($idx as $sql) {
        try {
            db()->exec($sql);
        } catch (PDOException $e) {
            // MySQL below 8.0.29 has no IF NOT EXISTS for indexes; a duplicate
            // index is the only error we expect here and it is harmless.
        }
    }
}

function schema_installed(): bool
{
    try {
        scalar('SELECT COUNT(*) FROM users');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}
