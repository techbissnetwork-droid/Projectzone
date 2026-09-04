<?php
/**
 * Distinguishes two kinds of customer-raised ticket: a request for a
 * brand-new project (no project exists yet) versus a task raised
 * against an existing project. project_id links the latter to the
 * specific project it's about.
 */
return function (PDO $pdo, array $context): void {
    $cols = $pdo->query("SHOW COLUMNS FROM tickets")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('type', $cols, true)) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN type ENUM('new_project','project_task') NOT NULL DEFAULT 'project_task' AFTER title");
    }
    if (!in_array('project_id', $cols, true)) {
        $pdo->exec('ALTER TABLE tickets ADD COLUMN project_id INT UNSIGNED NULL AFTER business_id');
        $pdo->exec('ALTER TABLE tickets ADD FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL');
    }
    if (!in_array('description', $cols, true)) {
        $pdo->exec('ALTER TABLE tickets ADD COLUMN description TEXT NULL AFTER title');
    }
};
