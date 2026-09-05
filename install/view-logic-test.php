<?php
/**
 * Decision-table test for install_choose_view().
 *
 * Run from the project root:  php install/view-logic-test.php
 *
 * The installer picks between screens that range from harmless ("you're
 * live") to irreversible ("wipe this database"), so the choice is worth
 * pinning down. It needs no database — the function is pure, which is why
 * it was extracted.
 */

// Pull just the function out of install/index.php, without executing the
// page around it.
$src = file_get_contents(__DIR__ . '/index.php');
$start = strpos($src, 'function install_choose_view(');
$end = strpos($src, "\n}\n", $start) + 3;
eval(substr($src, $start, $end - $start));

$fail = 0;
/**
 * @param array{config:bool,db:bool,err:bool,lock:bool,data:bool,newAdmin:bool,staff:bool,pending:bool} $in
 */
function check(string $name, array $in, string $expected): void
{
    global $fail;
    $got = install_choose_view(
        $in['config'], $in['db'], $in['err'], $in['lock'],
        $in['data'], $in['newAdmin'], $in['staff'], $in['pending']
    );
    if ($got === $expected) {
        echo "  ok    $name -> $got\n";
        return;
    }
    echo "  FAIL  $name -> got '$got', expected '$expected'\n";
    $fail++;
}

$base = ['config' => true, 'db' => true, 'err' => false, 'lock' => false,
         'data' => false, 'newAdmin' => false, 'staff' => false, 'pending' => false];

echo "A database with data always gets asked, never resolved automatically:\n";
// The reported case: a deploy dropped install.lock (it is gitignored) on a
// site that is really installed. This must ASK, not silently migrate.
check('lock missing, data present, signed out',
    array_merge($base, ['lock' => false, 'data' => true, 'staff' => false]), 'existing_data');
check('lock missing, data present, signed in',
    array_merge($base, ['lock' => false, 'data' => true, 'staff' => true]), 'existing_data');
check('lock missing, data present, updates pending',
    array_merge($base, ['lock' => false, 'data' => true, 'pending' => true]), 'existing_data');
// Even when 001 has never run, existing data wins: that database belongs to
// something, and offering "create an admin account" would talk past it.
check('lock missing, data present, 001 not yet applied',
    array_merge($base, ['lock' => false, 'data' => true, 'newAdmin' => true]), 'existing_data');

echo "\nGenuinely fresh installs are unaffected:\n";
check('empty database, 001 pending',
    array_merge($base, ['data' => false, 'newAdmin' => true]), 'admin_account');
check('no config yet',
    array_merge($base, ['config' => false, 'db' => false]), 'setup');

echo "\nAlready locked:\n";
check('locked + signed in',
    array_merge($base, ['lock' => true, 'staff' => true]), 'manage');
check('locked + signed in + data',
    array_merge($base, ['lock' => true, 'staff' => true, 'data' => true]), 'manage');
check('locked, signed out, updates pending',
    array_merge($base, ['lock' => true, 'pending' => true]), 'pending_locked');
check('locked, signed out, nothing pending',
    array_merge($base, ['lock' => true]), 'done');

echo "\nDatabase unreachable:\n";
check('db error, locked',
    array_merge($base, ['db' => false, 'err' => true, 'lock' => true]), 'db_error_locked');
check('db error, not locked',
    array_merge($base, ['db' => false, 'err' => true]), 'db_error_unlocked');

echo $fail ? "\n$fail FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);
