<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Pre-launch readiness checks, surfaced in the admin console.
 *
 * These replace the credential hints that used to sit on the sign-in pages:
 * an operator still needs to know that seeded accounts exist, but that belongs
 * behind authentication, not on a public page.
 */
final class SecurityAudit
{
    /** Passwords the seeder assigns to example accounts. */
    private const SEEDED_PASSWORDS = ['StaffDemo!2026', 'ClientDemo!2026'];

    /** Addresses the seeder used before the tracking row was introduced. */
    private const LEGACY_SEEDED_EMAILS = [
        'delivery@techbiss.com', 'security@techbiss.com', 'engineer@techbiss.com',
        'design@techbiss.com', 'support@techbiss.com',
        'client@northwind.example', 'priya@arclight.example', 'marcus@vantage.example',
    ];

    public function __construct(
        private Database $db,
        private Config $config,
        private Cache $cache,
        private bool $installerLocked,
    ) {
    }

    /**
     * @return list<array{level:string,title:string,detail:string,action:?string,path:?string}>
     */
    public function findings(): array
    {
        // password_verify is deliberately slow, so the account scan is cached.
        // Fifteen minutes is short enough that a changed password clears the
        // warning while an operator is still looking at the console.
        return $this->cache->remember('security.audit', 900, function (): array {
            $findings = [];

            // One finding for the whole set: eight identical rows would bury
            // the other checks rather than communicate urgency.
            $exposed = $this->accountsUsingSeededPasswords();
            if ($exposed !== []) {
                $names = array_map(static fn (array $a): string => $a['email'], $exposed);
                $findings[] = [
                    'level' => 'high',
                    'title' => count($exposed) === 1
                        ? 'One example account still uses its seeded password'
                        : count($exposed) . ' example accounts still use their seeded passwords',
                    'detail' => 'Created with the example content and never changed, so anyone who has read the '
                        . 'documentation can sign in as them: ' . implode(', ', $names)
                        . '. Suspend or delete them, or set new passwords, before you launch.',
                    'action' => 'Manage users',
                    'path' => '/admin/users',
                ];
            }

            if (!$this->installerLocked) {
                $findings[] = [
                    'level' => 'high',
                    'title' => 'The installer is reachable',
                    'detail' => 'storage/install.unlock is present, so /install can be opened by anyone who finds it. '
                        . 'Delete that file once you have finished re-running the installer.',
                    'action' => null,
                    'path' => null,
                ];
            }

            if ((bool) $this->config->get('app.debug', false)) {
                $findings[] = [
                    'level' => 'high',
                    'title' => 'Debug mode is on',
                    'detail' => 'Uncaught errors will render a stack trace to visitors. Set app.debug to false in storage/installed.php.',
                    'action' => null,
                    'path' => null,
                ];
            }

            $url = (string) $this->config->get('app.url', '');
            if ($url !== '' && !str_starts_with($url, 'https://') && !str_contains($url, 'localhost') && !str_contains($url, '127.0.0.1')) {
                $findings[] = [
                    'level' => 'medium',
                    'title' => 'The canonical URL is not HTTPS',
                    'detail' => 'Session cookies are only marked secure over HTTPS. Install a certificate and update the canonical URL.',
                    'action' => null,
                    'path' => null,
                ];
            }

            $indexable = (string) $this->db->value(
                "SELECT setting_value FROM settings WHERE setting_key = 'indexable'",
                [],
                '1'
            );
            if ($indexable !== '1') {
                $findings[] = [
                    'level' => 'medium',
                    'title' => 'Search engines are blocked',
                    'detail' => 'robots.txt currently disallows everything. Turn indexing on in settings when you are ready to launch.',
                    'action' => 'Open settings',
                    'path' => '/admin/settings',
                ];
            }

            return $findings;
        });
    }

    /** @return list<array{name:string,email:string}> */
    private function accountsUsingSeededPasswords(): array
    {
        $tracked = $this->db->value(
            "SELECT setting_value FROM settings WHERE setting_key = 'seeded_accounts'",
            [],
            null
        );

        $emails = is_string($tracked) ? json_decode($tracked, true) : null;

        // Installs created before this check existed have no tracking row, so
        // fall back to the addresses the seeder has always used. Anything not
        // present simply returns no rows.
        if (!is_array($emails) || $emails === []) {
            $emails = self::LEGACY_SEEDED_EMAILS;
        }

        $placeholders = implode(',', array_fill(0, count($emails), '?'));
        $users = $this->db->select(
            "SELECT name, email, password_hash FROM users WHERE email IN ({$placeholders})",
            array_values($emails)
        );

        $exposed = [];
        foreach ($users as $user) {
            foreach (self::SEEDED_PASSWORDS as $password) {
                if (password_verify($password, (string) $user['password_hash'])) {
                    $exposed[] = ['name' => (string) $user['name'], 'email' => (string) $user['email']];
                    break;
                }
            }
        }

        return $exposed;
    }

    public function forget(): void
    {
        $this->cache->forget('security.audit');
    }
}
