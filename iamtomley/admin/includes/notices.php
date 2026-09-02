<?php
/**
 * Things about this site that want attention.
 *
 * These are conditions, not messages: each one is worked out fresh on every
 * page, so it appears when it becomes true and disappears the moment it is
 * dealt with. Nothing is stored and nothing has to be dismissed — a warning
 * that can be clicked away is a warning that gets clicked away.
 *
 * A confirmation of something you just did is not one of these; that is a
 * flash, and it stays on the page where you did it.
 */
declare(strict_types=1);

/**
 * @return array<int,array{level:string,title:string,text:string,href:string,cta:string}>
 *         Most serious first.
 */
function admin_notices(): array
{
    $out = [];

    // Anyone who finds the login page can walk straight in.
    if (using_default_password()) {
        $out[] = [
            'level' => 'danger',
            'title' => 'Default password still in use',
            'text'  => 'Anyone who finds your admin page can sign in as admin / admin.',
            'href'  => url('/admin/account.php'),
            'cta'   => 'Change it',
        ];
    }

    // The wizard can rebuild the database. It has no business staying online.
    if (is_dir(APP_ROOT . '/install')) {
        $out[] = [
            'level' => 'danger',
            'title' => 'Installer is still on the server',
            'text'  => 'Delete the install folder — it can reset your site if someone reaches it.',
            'href'  => url('/admin/seo.php'),
            'cta'   => 'How',
        ];
    }

    // Live, but invisible.
    if (setting('maintenance_mode') === '1') {
        $out[] = [
            'level' => 'warn',
            'title' => 'Maintenance mode is on',
            'text'  => 'Visitors see the holding page instead of your site.',
            'href'  => url('/admin/settings.php'),
            'cta'   => 'Turn off',
        ];
    }

    // Without one, every address the site answers on claims to be the original,
    // which is what Search Console reports as duplicates.
    if (trim(setting('seo_canonical')) === '') {
        $out[] = [
            'level' => 'warn',
            'title' => 'No canonical address set',
            'text'  => 'Google counts www and non-www as separate sites until you name one.',
            'href'  => url('/admin/seo.php'),
            'cta'   => 'Fix',
        ];
    }

    // A 404 here tells a crawler to back off.
    if (!is_file(APP_ROOT . '/robots.txt') && !is_file(APP_ROOT . '/robots.php')) {
        $out[] = [
            'level' => 'warn',
            'title' => 'No robots.txt',
            'text'  => 'Crawlers ask for this first and a missing one slows them down.',
            'href'  => url('/admin/seo.php'),
            'cta'   => 'Write it',
        ];
    }

    return $out;
}
