<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Populates a fresh installation. Everything written here is real, queryable
 * data — the portals, marketplace and dashboards all read from these rows, so
 * a new install is immediately explorable rather than a set of empty states.
 */
final class Seeder
{
    private array $log = [];

    public function __construct(private Database $db, private string $basePath)
    {
    }

    public function log(): array
    {
        return $this->log;
    }

    private function note(string $message): void
    {
        $this->log[] = 'ok|' . $message;
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    private function daysAgo(int $days): string
    {
        return gmdate('c', time() - $days * 86400);
    }

    private function daysAhead(int $days): string
    {
        return gmdate('c', time() + $days * 86400);
    }

    /**
     * @param array{name:string,email:string,password:string} $admin
     */
    public function run(array $admin, bool $withDemoData = true): void
    {
        $this->seedUsers($admin, $withDemoData);
        $this->seedCatalogue();
        $this->seedSettings();

        if ($withDemoData) {
            $this->seedCommerce();
            $this->seedOperations();
        }
    }

    private function seedUsers(array $admin, bool $withDemoData): void
    {
        $now = gmdate('c');
        $this->db->insert('users', [
            'uuid' => bin2hex(random_bytes(8)),
            'name' => $admin['name'],
            'email' => strtolower($admin['email']),
            'password_hash' => password_hash($admin['password'], PASSWORD_DEFAULT),
            'role' => 'owner',
            'status' => 'active',
            'company' => 'TECHBISS',
            'job_title' => 'Platform Owner',
            'avatar_color' => 'blue',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->note('Created owner account ' . $admin['email']);

        if (!$withDemoData) {
            return;
        }

        // Demo accounts. Passwords are documented in the installer's final step
        // and in README; the admin console flags them until they are changed.
        $people = [
            ['Rashid Al-Amin', 'delivery@techbiss.com', 'manager', 'TECHBISS', 'Chief Delivery Officer', 'amber'],
            ['Sofia Ricci', 'security@techbiss.com', 'manager', 'TECHBISS', 'CISO', 'violet'],
            ['Tomas Berger', 'engineer@techbiss.com', 'engineer', 'TECHBISS', 'Principal Engineer', 'teal'],
            ['Amara Diallo', 'design@techbiss.com', 'engineer', 'TECHBISS', 'Senior Product Designer', 'violet'],
            ['Jonas Meyer', 'support@techbiss.com', 'support', 'TECHBISS', 'Support Lead', 'blue'],
            ['Adaeze Okonkwo', 'client@northwind.example', 'client', 'Northwind Bank', 'Chief Technology Officer', 'blue'],
            ['Priya Raman', 'priya@arclight.example', 'client', 'Arclight Retail', 'Head of Ecommerce', 'amber'],
            ['Marcus Feld', 'marcus@vantage.example', 'client', 'Vantage Logistics', 'VP Digital', 'teal'],
        ];

        foreach ($people as $i => [$name, $email, $role, $company, $title, $colour]) {
            $this->db->insert('users', [
                'uuid' => bin2hex(random_bytes(8)),
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($role === 'client' ? 'ClientDemo!2026' : 'StaffDemo!2026', PASSWORD_DEFAULT),
                'role' => $role,
                'status' => 'active',
                'company' => $company,
                'job_title' => $title,
                'avatar_color' => $colour,
                'last_login_at' => $this->daysAgo($i % 5),
                'created_at' => $this->daysAgo(220 - $i * 12),
                'updated_at' => $now,
            ]);
        }
        $this->note('Created ' . count($people) . ' demo staff and client accounts');
    }

    private function seedCatalogue(): void
    {
        $now = gmdate('c');

        $products = require $this->basePath . '/database/seeds/products.php';
        foreach ($products as $p) {
            $this->db->insert('products', [
                'slug' => $p['slug'],
                'name' => $p['name'],
                'tagline' => $p['tagline'],
                'description' => $p['description'],
                'category' => $p['category'],
                'product_type' => $p['product_type'],
                'price' => $p['price'],
                'compare_price' => $p['compare_price'],
                'currency' => 'USD',
                'extended_price' => $p['extended_price'],
                'enterprise_price' => $p['enterprise_price'],
                'rating' => $p['rating'],
                'reviews_count' => $p['reviews_count'],
                'sales_count' => $p['sales_count'],
                'layout' => $p['layout'],
                'version' => $p['version'],
                'tags' => $this->json($p['tags']),
                'features' => $this->json($p['features']),
                'specs' => $this->json($p['specs']),
                'includes' => $this->json($p['includes']),
                'pages' => $this->json($p['pages']),
                'lighthouse' => $p['lighthouse'],
                'demo_url' => $p['demo_url'],
                'status' => 'published',
                'featured' => $p['featured'],
                'released_at' => $p['released_at'],
                'updated_at' => $p['updated_at'],
                'created_at' => $now,
            ]);
        }
        $this->note('Imported ' . count($products) . ' marketplace products');

        $cases = require $this->basePath . '/database/seeds/case_studies.php';
        foreach ($cases as $c) {
            $this->db->insert('case_studies', [
                'slug' => $c['slug'],
                'client' => $c['client'],
                'title' => $c['title'],
                'summary' => $c['summary'],
                'body' => null,
                'industry' => $c['industry'],
                'service' => $c['service'],
                'region' => $c['region'],
                'duration' => $c['duration'],
                'year' => $c['year'],
                'accent' => $c['accent'],
                'layout' => $c['layout'],
                'metrics' => $this->json($c['metrics']),
                'challenge' => $c['challenge'],
                'approach' => $this->json($c['approach']),
                'outcome' => $c['outcome'],
                'stack' => $this->json($c['stack']),
                'quote' => $c['quote'],
                'quote_by' => $c['quote_by'],
                'quote_role' => $c['quote_role'],
                'featured' => $c['featured'],
                'published_at' => $c['year'] . '-06-01',
                'created_at' => $now,
            ]);
        }
        $this->note('Imported ' . count($cases) . ' case studies');

        $resources = require $this->basePath . '/database/seeds/resources.php';
        foreach ($resources as $r) {
            $this->db->insert('resources', [
                'slug' => $r['slug'],
                'title' => $r['title'],
                'excerpt' => $r['excerpt'],
                'body' => $r['body'],
                'type' => $r['type'],
                'topic' => $r['topic'],
                'author' => $r['author'],
                'author_role' => $r['author_role'],
                'read_minutes' => $r['read_minutes'],
                'accent' => $r['accent'],
                'featured' => $r['featured'],
                'published_at' => $r['published_at'],
                'created_at' => $now,
            ]);
        }
        $this->note('Imported ' . count($resources) . ' resources');
    }

    private function seedSettings(): void
    {
        $settings = [
            ['site_name', 'TECHBISS', 'general'],
            ['site_tagline', 'Digital transformation, engineered.', 'general'],
            ['contact_email', 'hello@techbiss.com', 'general'],
            ['sales_email', 'sales@techbiss.com', 'general'],
            ['support_email', 'support@techbiss.com', 'general'],
            ['default_currency', 'USD', 'commerce'],
            ['tax_rate', '0', 'commerce'],
            ['marketplace_enabled', '1', 'commerce'],
            ['page_cache_ttl', '600', 'performance'],
            ['lazy_images', '1', 'performance'],
            ['amp_enabled', '1', 'seo'],
            ['indexable', '1', 'seo'],
        ];
        foreach ($settings as [$key, $value, $group]) {
            $this->db->insert('settings', [
                'setting_key' => $key,
                'setting_value' => $value,
                'setting_group' => $group,
                'updated_at' => gmdate('c'),
            ]);
        }
        $this->note('Wrote ' . count($settings) . ' platform settings');
    }

    private function seedCommerce(): void
    {
        $clients = $this->db->select("SELECT id, name, email, company FROM users WHERE role = 'client' ORDER BY id");
        $products = $this->db->select('SELECT id, name, slug, price, extended_price FROM products ORDER BY id LIMIT 8');
        if ($clients === [] || $products === []) {
            return;
        }

        $orders = 0;
        $deployments = 0;

        foreach ($clients as $ci => $client) {
            for ($n = 0; $n < 2; $n++) {
                $product = $products[($ci * 2 + $n) % count($products)];
                $tier = $n === 0 ? 'standard' : 'extended';
                $unit = (float) ($tier === 'extended' ? ($product['extended_price'] ?? $product['price']) : $product['price']);
                $tax = round($unit * 0.0, 2);
                $reference = 'TB-' . strtoupper(substr(hash('xxh128', $client['email'] . $product['slug'] . $n), 0, 8));
                $placedAt = $this->daysAgo(180 - ($ci * 24 + $n * 11));

                $orderId = $this->db->insert('orders', [
                    'reference' => $reference,
                    'user_id' => (int) $client['id'],
                    'customer_name' => $client['name'],
                    'customer_email' => $client['email'],
                    'company' => $client['company'],
                    'country' => ['United Kingdom', 'United States', 'Germany'][$ci % 3],
                    'subtotal' => $unit,
                    'tax' => $tax,
                    'total' => $unit + $tax,
                    'currency' => 'USD',
                    'status' => 'paid',
                    'payment_method' => $n === 0 ? 'card' : 'invoice',
                    'created_at' => $placedAt,
                ]);

                $this->db->insert('order_items', [
                    'order_id' => $orderId,
                    'product_id' => (int) $product['id'],
                    'product_name' => $product['name'],
                    'license_tier' => $tier,
                    'unit_price' => $unit,
                    'quantity' => 1,
                    'created_at' => $placedAt,
                ]);

                $licenseId = $this->db->insert('licenses', [
                    'license_key' => self::licenseKey($reference . $product['slug']),
                    'order_id' => $orderId,
                    'product_id' => (int) $product['id'],
                    'user_id' => (int) $client['id'],
                    'tier' => $tier,
                    'seats' => $tier === 'extended' ? 5 : 1,
                    'domains' => $this->json([strtolower(str_replace(' ', '', (string) $client['company'])) . '.example']),
                    'status' => 'active',
                    'support_until' => $this->daysAhead(365 - $ci * 30),
                    'created_at' => $placedAt,
                ]);
                $orders++;

                if ($n === 0) {
                    $states = [['live', 100], ['running', 62], ['pending', 0]];
                    [$status, $progress] = $states[$ci % 3];
                    $this->db->insert('deployments', [
                        'token' => bin2hex(random_bytes(16)),
                        'license_id' => $licenseId,
                        'product_id' => (int) $product['id'],
                        'user_id' => (int) $client['id'],
                        'site_name' => $client['company'] . ' — ' . $product['name'],
                        'target_url' => 'https://' . strtolower(str_replace(' ', '', (string) $client['company'])) . '.example',
                        'environment' => $ci === 0 ? 'production' : 'staging',
                        'install_mode' => $ci === 1 ? 'migrate' : 'clean',
                        'source_platform' => $ci === 1 ? 'wordpress' : null,
                        'database_driver' => 'mysql',
                        'status' => $status,
                        'progress' => $progress,
                        'log' => null,
                        'created_at' => $this->daysAgo(120 - $ci * 20),
                        'completed_at' => $status === 'live' ? $this->daysAgo(118 - $ci * 20) : null,
                    ]);
                    $deployments++;
                }
            }
        }
        $this->note("Created {$orders} orders with licences and {$deployments} deployments");
    }

    private function seedOperations(): void
    {
        $clients = $this->db->select("SELECT id, name, company FROM users WHERE role = 'client' ORDER BY id");
        $staff = $this->db->select("SELECT id, name FROM users WHERE role IN ('manager','engineer','support') ORDER BY id");
        if ($clients === [] || $staff === []) {
            return;
        }

        $blueprints = [
            ['NW-01', 'Settlement platform migration', 'build', 'green', 68, 1850000, 1170000, 'Event-sourced ledger replacing the legacy settlement core, domain by domain.'],
            ['AR-02', 'Storefront replatform', 'harden', 'amber', 84, 640000, 553000, 'Composable storefront rebuild against a one-second LCP budget.'],
            ['VG-03', 'Fleet visibility platform', 'operate', 'green', 96, 920000, 884000, 'Telemetry ingestion, routing and customer tracking on one event stream.'],
        ];

        $milestoneSets = [
            [['Discovery and architecture', 'complete'], ['Shadow-mode parity', 'complete'], ['Domestic transfers cutover', 'complete'], ['Direct debits cutover', 'active'], ['Cross-border cutover', 'pending'], ['Handover and training', 'pending']],
            [['Performance budget agreed', 'complete'], ['Catalogue templates', 'complete'], ['Checkout rebuild', 'complete'], ['Load and soak testing', 'active'], ['Progressive rollout', 'pending']],
            [['Ingestion pipeline', 'complete'], ['Routing engine', 'complete'], ['Customer tracking', 'complete'], ['Operations console', 'complete'], ['SLO reporting live', 'complete']],
        ];

        foreach ($blueprints as $i => [$code, $name, $phase, $health, $progress, $budget, $spent, $summary]) {
            $client = $clients[$i % count($clients)];
            $projectId = $this->db->insert('projects', [
                'code' => $code,
                'name' => $name,
                'client_id' => (int) $client['id'],
                'lead_id' => (int) $staff[0]['id'],
                'summary' => $summary,
                'phase' => $phase,
                'status' => 'active',
                'health' => $health,
                'progress' => $progress,
                'budget' => $budget,
                'spent' => $spent,
                'started_at' => $this->daysAgo(300 - $i * 40),
                'due_at' => $this->daysAhead(120 - $i * 35),
                'created_at' => $this->daysAgo(310 - $i * 40),
            ]);

            foreach ($milestoneSets[$i] as $position => [$title, $status]) {
                $this->db->insert('project_milestones', [
                    'project_id' => $projectId,
                    'title' => $title,
                    'detail' => null,
                    'status' => $status,
                    'due_at' => $this->daysAhead(($position - 2) * 21),
                    'position' => $position,
                ]);
            }

            $this->db->insert('invoices', [
                'number' => 'INV-2026-' . str_pad((string) ($i + 101), 4, '0', STR_PAD_LEFT),
                'user_id' => (int) $client['id'],
                'project_id' => $projectId,
                'description' => $name . ' — increment ' . ($i + 4),
                'amount' => round($budget / 8, 2),
                'currency' => 'USD',
                'status' => $i === 1 ? 'due' : 'paid',
                'issued_at' => $this->daysAgo(28 - $i * 7),
                'due_at' => $this->daysAhead(2 + $i * 7),
                'paid_at' => $i === 1 ? null : $this->daysAgo(10),
            ]);
        }
        $this->note('Created ' . count($blueprints) . ' projects with milestones and invoices');

        $tasks = [
            ['Reconcile month-end shadow run divergence', 'Northwind settlement — final parity check', 'high', 'open', 1],
            ['Review load test results against 1s LCP budget', 'Arclight storefront — harden phase', 'high', 'open', 3],
            ['Draft rollback runbook for cross-border cutover', 'Northwind settlement — launch prep', 'normal', 'open', 6],
            ['Accessibility audit remediation pass', 'Clinic portal release candidate', 'normal', 'open', 4],
            ['Update SLO dashboard for Vantage handover', 'Vantage fleet — operate phase', 'low', 'done', -2],
            ['Threat model review for new payment adapter', 'Meridian Commerce 4.2', 'high', 'open', 2],
            ['Publish Q3 fleet reliability report', 'All clients — monthly reporting', 'normal', 'open', 9],
            ['Pair with Northwind engineers on on-call rota', 'Capability transfer', 'normal', 'done', -5],
        ];
        foreach ($tasks as $i => [$title, $detail, $priority, $status, $dueIn]) {
            $this->db->insert('tasks', [
                'project_id' => null,
                'assignee_id' => (int) $staff[$i % count($staff)]['id'],
                'title' => $title,
                'detail' => $detail,
                'priority' => $priority,
                'status' => $status,
                'due_at' => $dueIn >= 0 ? $this->daysAhead($dueIn) : $this->daysAgo(abs($dueIn)),
                'created_at' => $this->daysAgo(14 - $i),
            ]);
        }
        $this->note('Created ' . count($tasks) . ' staff tasks');

        $tickets = [
            ['Advanced Installer stops at database step on shared hosting', 'The installer reports a connection failure on our shared host, but the same credentials work in phpMyAdmin. The host uses a non-standard socket path.', 'installation', 'high', 'open'],
            ['Extending the licence to a second staging domain', 'We need to register staging.arclight.example against our existing extended licence. What is the process?', 'licensing', 'normal', 'open'],
            ['Catalogue page LCP regressed after our template change', 'Since we added a hero video the catalogue LCP moved from 0.9s to 2.1s. Looking for guidance on the right pattern.', 'performance', 'normal', 'answered'],
            ['Migrating content from our old WordPress install', 'We have around 400 posts and want the URL structure preserved. Does the migration step handle redirects?', 'migration', 'normal', 'resolved'],
        ];
        foreach ($tickets as $i => [$subject, $body, $category, $priority, $status]) {
            $client = $clients[$i % count($clients)];
            $ticketId = $this->db->insert('tickets', [
                'reference' => 'SUP-' . str_pad((string) (4120 + $i), 5, '0', STR_PAD_LEFT),
                'user_id' => (int) $client['id'],
                'assignee_id' => (int) $staff[count($staff) - 1]['id'],
                'subject' => $subject,
                'body' => $body,
                'category' => $category,
                'priority' => $priority,
                'status' => $status,
                'created_at' => $this->daysAgo(9 - $i * 2),
                'updated_at' => $this->daysAgo(max(0, 7 - $i * 2)),
            ]);

            if (in_array($status, ['answered', 'resolved'], true)) {
                $this->db->insert('ticket_replies', [
                    'ticket_id' => $ticketId,
                    'user_id' => (int) $staff[count($staff) - 1]['id'],
                    'author_name' => $staff[count($staff) - 1]['name'],
                    'body' => $status === 'resolved'
                        ? 'The migration step preserves permalink structure and writes 301s for every mapped URL. I have attached the mapping report from your import — every one of the 412 URLs resolved to a single destination.'
                        : 'A hero video will almost always become the LCP element. Use a poster image sized for the viewport and load the video after first paint; that returns you to roughly 1.0s. I have sent the exact markup pattern.',
                    'created_at' => $this->daysAgo(max(0, 6 - $i * 2)),
                ]);
            }
        }
        $this->note('Created ' . count($tickets) . ' support tickets');

        $leads = [
            ['Helena Voss', 'h.voss@brightline.example', 'Brightline Group', 'new-project', '500k-plus', 'Q1 2027', 'new', 'Replacing our order management platform across four European markets. Need architecture and delivery.', 420000],
            ['Kwame Mensah', 'kwame@atlaspay.example', 'AtlasPay', 'new-project', '150k-500k', 'Immediate', 'qualified', 'Payments platform needs PCI DSS 4.0 remediation and a performance rebuild before our audit in March.', 310000],
            ['Yuki Tanaka', 'yuki@sanko.example', 'Sanko Logistics', 'marketplace', '50k-150k', 'This quarter', 'contacted', 'Interested in Harbor Logistics Tracker with an enterprise licence and a customisation engagement.', 78000],
            ['Elena Costa', 'e.costa@medira.example', 'Medira Health', 'migration', 'under-50k', 'Next month', 'new', 'We bought Clinic Health Portal and need help migrating 900 pages from our current CMS.', 34000],
            ['David Okoro', 'd.okoro@civicnorth.example', 'Civic North', 'new-project', '150k-500k', 'Q2 2027', 'proposal', 'Citizen services programme, three services in scope, must pass service standard assessment.', 265000],
            ['Anna Lindqvist', 'anna@nordvind.example', 'Nordvind Energy', 'partnership', 'not-sure', 'Exploring', 'new', 'Exploring a delivery partnership for Nordic market engagements.', null],
        ];
        foreach ($leads as $i => [$name, $email, $company, $topic, $budget, $timeline, $status, $message, $value]) {
            $this->db->insert('leads', [
                'reference' => 'LD-' . strtoupper(substr(hash('xxh128', $email), 0, 8)),
                'name' => $name,
                'email' => $email,
                'company' => $company,
                'phone' => null,
                'topic' => $topic,
                'budget' => $budget,
                'timeline' => $timeline,
                'message' => $message,
                'source' => ['contact-form', 'contact-form', 'marketplace', 'contact-form', 'referral', 'contact-form'][$i],
                'status' => $status,
                'owner_id' => (int) $staff[$i % count($staff)]['id'],
                'value' => $value,
                'created_at' => $this->daysAgo(21 - $i * 3),
                'updated_at' => $this->daysAgo(max(0, 14 - $i * 3)),
            ]);
        }
        $this->note('Created ' . count($leads) . ' pipeline leads');

        $owner = $this->db->first("SELECT id FROM users WHERE role = 'owner' ORDER BY id LIMIT 1");
        $activity = [
            ['install.complete', 'Platform installed and configured'],
            ['product.publish', 'Published Atlas Corporate Platform v3.2.1'],
            ['order.paid', 'Order TB-9F2A1C marked paid'],
            ['deployment.live', 'Deployment for Northwind Bank went live'],
            ['user.invite', 'Invited Jonas Meyer to the staff workspace'],
        ];
        foreach ($activity as $i => [$action, $description]) {
            $this->db->insert('activity_log', [
                'user_id' => $owner ? (int) $owner['id'] : null,
                'action' => $action,
                'description' => $description,
                'ip_address' => '127.0.0.1',
                'created_at' => $this->daysAgo($i),
            ]);
        }
    }

    public static function licenseKey(string $seed): string
    {
        $hash = strtoupper(substr(hash('sha256', $seed . random_bytes(8)), 0, 20));
        return 'TBX-' . implode('-', str_split($hash, 5));
    }
}
