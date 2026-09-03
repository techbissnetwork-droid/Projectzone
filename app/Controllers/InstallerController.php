<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Installer;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

/**
 * The Advanced Installer wizard.
 *
 * State lives in the session until the final step, so every decision is
 * reversible and nothing is written to disk or the database until the operator
 * presses install.
 */
final class InstallerController extends Controller
{
    private const SESSION_KEY = 'installer';

    private function installer(): Installer
    {
        return new Installer($this->app);
    }

    /** @return array<string,mixed> */
    private function state(): array
    {
        $this->session->start();
        $state = $this->session->get(self::SESSION_KEY, []);
        return is_array($state) ? $state : [];
    }

    private function saveState(array $patch): array
    {
        $state = array_replace($this->state(), $patch);
        $this->session->put(self::SESSION_KEY, $state);
        return $state;
    }

    private function stepKeys(): array
    {
        return array_keys(Installer::STEPS);
    }

    private function stepIndex(string $step): int
    {
        $index = array_search($step, $this->stepKeys(), true);
        return $index === false ? 0 : (int) $index;
    }

    private function chrome(string $step): array
    {
        $keys = $this->stepKeys();
        $index = $this->stepIndex($step);
        return [
            'steps' => Installer::STEPS,
            'stepKeys' => $keys,
            'current' => $step,
            'index' => $index,
            'previous' => $index > 0 ? $keys[$index - 1] : null,
            'next' => $index < count($keys) - 1 ? $keys[$index + 1] : null,
        ];
    }

    public function index(Request $request): Response
    {
        return $this->redirect('/install/step/requirements');
    }

    public function step(Request $request, string $step): Response
    {
        if (!isset(Installer::STEPS[$step])) {
            throw HttpException::notFound('That installer step does not exist.');
        }

        $this->session->start();
        $installer = $this->installer();
        $state = $this->state();

        $this->seo->title('Advanced Installer — ' . Installer::STEPS[$step]['label'], true);
        $this->seo->description(Installer::STEPS[$step]['blurb']);
        $this->seo->noindex();

        $data = [
            'request' => $request,
            'state' => $state,
            'meta' => Installer::STEPS[$step],
        ] + $this->chrome($step);

        switch ($step) {
            case 'requirements':
                $data['checks'] = $installer->requirements();
                $data['satisfied'] = $installer->requirementsSatisfied();
                break;

            case 'environment':
                $data['environment'] = $installer->detectEnvironment($request->server);
                break;

            case 'database':
                $data['config'] = $state['database'] ?? [
                    'driver' => 'sqlite',
                    'database' => $this->app->path('storage/db/techbiss.sqlite'),
                    'host' => '127.0.0.1',
                    'port' => 3306,
                    'username' => '',
                    'password' => '',
                ];
                break;

            case 'detection':
                $data['scan'] = $installer->scanExisting($state['database'] ?? []);
                break;

            case 'migration':
                $data['scan'] = $state['scan'] ?? $installer->scanExisting($state['database'] ?? []);
                break;

            case 'configuration':
                $data['environment'] = $installer->detectEnvironment($request->server);
                $data['timezones'] = self::TIMEZONES;
                break;

            case 'install':
                $data['ready'] = $this->readiness($state);
                break;

            case 'deploy':
                $url = (string) ($state['url'] ?? $installer->detectEnvironment($request->server)['url']);
                $data['snippets'] = $installer->deploymentSnippets($url);
                $data['url'] = $url;
                $data['log'] = $state['log'] ?? [];
                break;
        }

        return $this->render('install.' . $step, $data)->cachePrivate();
    }

    public function save(Request $request, string $step): Response
    {
        if (!isset(Installer::STEPS[$step])) {
            throw HttpException::notFound('That installer step does not exist.');
        }
        $this->session->start();
        $chrome = $this->chrome($step);

        switch ($step) {
            case 'environment':
                $validator = Validator::make($request->body, [
                    'url' => 'required|url|max:255',
                    'detect_url' => 'max:4',
                ]);
                if ($validator->fails()) {
                    $this->withInput($request, $validator->errors());
                    return $this->redirect('/install/step/environment');
                }
                $this->saveState([
                    'url' => rtrim((string) $request->str('url'), '/'),
                    'detect_url' => $request->boolean('detect_url'),
                ]);
                break;

            case 'database':
                $config = $this->databaseConfigFrom($request);
                $test = $this->installer()->testConnection($config);
                if (!$test['ok']) {
                    $this->withInput($request, ['database' => $test['message']]);
                    $this->session->flash('error', $test['message']);
                    return $this->redirect('/install/step/database');
                }
                $this->saveState(['database' => $config, 'database_server' => $test['server'] ?? '']);
                break;

            case 'detection':
                $mode = $request->str('mode', 'clean');
                if (!in_array($mode, ['clean', 'upgrade', 'migrate'], true)) {
                    $mode = 'clean';
                }
                $this->saveState([
                    'mode' => $mode,
                    'scan' => $this->installer()->scanExisting($this->state()['database'] ?? []),
                ]);
                // Migration only makes sense when migrating.
                if ($mode !== 'migrate') {
                    return $this->redirect('/install/step/configuration');
                }
                break;

            case 'migration':
                $patch = [
                    'old_url' => rtrim((string) $request->str('old_url'), '/'),
                    'import_source' => $request->str('import_source', 'none'),
                ];
                $upload = $_FILES['import_file'] ?? null;
                if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $extension = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));
                    if (!in_array($extension, ['json', 'csv'], true)) {
                        $this->session->flash('error', 'Import files must be JSON or CSV.');
                        return $this->redirect('/install/step/migration');
                    }
                    if ((int) $upload['size'] > 20 * 1024 * 1024) {
                        $this->session->flash('error', 'Import files are limited to 20 MB.');
                        return $this->redirect('/install/step/migration');
                    }
                    $target = $this->app->path('storage/uploads/import-' . bin2hex(random_bytes(6)) . '.' . $extension);
                    if (@move_uploaded_file((string) $upload['tmp_name'], $target) || @rename((string) $upload['tmp_name'], $target)) {
                        $patch['import_file'] = $target;
                    }
                }
                $this->saveState($patch);
                break;

            case 'configuration':
                $validator = Validator::make($request->body, [
                    'site_name' => 'required|max:120',
                    'timezone' => 'required|max:60',
                    'admin_name' => 'required|max:160',
                    'admin_email' => 'required|email|max:190',
                    'admin_password' => 'required|min:10|max:255|confirmed',
                    'demo_data' => 'max:4',
                ], [
                    'admin_password' => 'Password',
                    'admin_email' => 'Email address',
                ]);
                if ($validator->fails()) {
                    $this->withInput($request, $validator->errors());
                    $this->session->flash('error', 'Please correct the highlighted fields.');
                    return $this->redirect('/install/step/configuration');
                }
                $this->saveState([
                    'site_name' => $request->str('site_name'),
                    'timezone' => $request->str('timezone'),
                    'admin_name' => $request->str('admin_name'),
                    'admin_email' => strtolower($request->str('admin_email')),
                    'admin_password' => (string) $request->input('admin_password'),
                    'demo_data' => $request->boolean('demo_data'),
                ]);
                break;
        }

        $next = $chrome['next'] ?? 'install';
        return $this->redirect('/install/step/' . $next);
    }

    /** Live connection test, rendered back into the page by app.js. */
    public function testDatabase(Request $request): Response
    {
        $config = $this->databaseConfigFrom($request);
        $result = $this->installer()->testConnection($config);
        $this->app->shareViewGlobals($this->view, $request);

        return Response::json([
            'ok' => $result['ok'],
            'html' => $this->view->renderRaw('install.partials.db-result', ['result' => $result, 'config' => $config]),
        ])->cachePrivate();
    }

    /** Re-run the existing-site scan on demand. */
    public function scan(Request $request): Response
    {
        $this->session->start();
        $config = $this->state()['database'] ?? [];
        $scan = $this->installer()->scanExisting($config);
        $this->saveState(['scan' => $scan]);
        $this->app->shareViewGlobals($this->view, $request);

        return Response::json([
            'ok' => true,
            'html' => $this->view->renderRaw('install.partials.scan-result', ['scan' => $scan]),
        ])->cachePrivate();
    }

    public function run(Request $request): Response
    {
        $this->session->start();
        $state = $this->state();
        $missing = $this->readiness($state);

        if ($missing['blocked']) {
            $this->session->flash('error', 'Complete the earlier steps before installing.');
            return $this->redirect('/install/step/' . $missing['step']);
        }

        $result = $this->installer()->install([
            'database' => $state['database'],
            'site_name' => (string) ($state['site_name'] ?? 'TECHBISS'),
            'url' => (string) ($state['url'] ?? ''),
            'detect_url' => (bool) ($state['detect_url'] ?? true),
            'timezone' => (string) ($state['timezone'] ?? 'UTC'),
            'admin_name' => (string) ($state['admin_name'] ?? ''),
            'admin_email' => (string) ($state['admin_email'] ?? ''),
            'admin_password' => (string) ($state['admin_password'] ?? ''),
            'mode' => (string) ($state['mode'] ?? 'clean'),
            'demo_data' => (bool) ($state['demo_data'] ?? true),
            'import_file' => (string) ($state['import_file'] ?? ''),
            'old_url' => (string) ($state['old_url'] ?? ''),
        ]);

        // Credentials never linger in the session after the install runs.
        $this->saveState([
            'log' => $result['log'],
            'installed' => $result['ok'],
            'admin_password' => null,
            'database' => array_replace($state['database'], ['password' => '']),
        ]);

        if (!$result['ok']) {
            $this->session->flash('error', 'Installation could not complete. The log below shows where it stopped.');
            return $this->redirect('/install/step/install');
        }

        return $this->redirect('/install/complete');
    }

    public function complete(Request $request): Response
    {
        $this->session->start();
        $state = $this->state();

        $this->seo->title('Installation complete — TECHBISS', true);
        $this->seo->description('Your TECHBISS platform is installed and locked.');
        $this->seo->noindex();

        $url = (string) ($state['url'] ?? $this->installer()->detectEnvironment($request->server)['url']);

        $response = $this->render('install.complete', [
            'request' => $request,
            'state' => $state,
            'log' => $state['log'] ?? [],
            'snippets' => $this->installer()->deploymentSnippets($url),
            'url' => $url,
            'adminEmail' => (string) ($state['admin_email'] ?? ''),
        ] + $this->chrome('deploy'))->cachePrivate();

        // The wizard's working state is no longer needed once it is shown.
        $this->session->forget(self::SESSION_KEY);

        return $response;
    }

    private function databaseConfigFrom(Request $request): array
    {
        $driver = $request->str('driver', 'sqlite');
        if (!in_array($driver, ['sqlite', 'mysql'], true)) {
            $driver = 'sqlite';
        }

        if ($driver === 'sqlite') {
            $path = $request->str('database', $this->app->path('storage/db/techbiss.sqlite'));
            return ['driver' => 'sqlite', 'database' => $path !== '' ? $path : $this->app->path('storage/db/techbiss.sqlite')];
        }

        return [
            'driver' => 'mysql',
            'host' => $request->str('host', '127.0.0.1'),
            'port' => $request->int('port', 3306),
            'database' => $request->str('database'),
            'username' => $request->str('username'),
            'password' => (string) $request->input('password', ''),
            'charset' => 'utf8mb4',
        ];
    }

    /** @return array{blocked:bool,step:string,items:list<array{label:string,done:bool,value:string}>} */
    private function readiness(array $state): array
    {
        $items = [
            ['label' => 'Requirements verified', 'done' => $this->installer()->requirementsSatisfied(), 'value' => 'PHP ' . PHP_VERSION, 'step' => 'requirements'],
            ['label' => 'Site URL confirmed', 'done' => !empty($state['url']), 'value' => (string) ($state['url'] ?? 'not set'), 'step' => 'environment'],
            ['label' => 'Database connected', 'done' => !empty($state['database']), 'value' => (string) ($state['database_server'] ?? 'not tested'), 'step' => 'database'],
            ['label' => 'Install mode chosen', 'done' => !empty($state['mode']), 'value' => ucfirst((string) ($state['mode'] ?? 'clean')), 'step' => 'detection'],
            ['label' => 'Owner account defined', 'done' => !empty($state['admin_email']) && !empty($state['admin_password']), 'value' => (string) ($state['admin_email'] ?? 'not set'), 'step' => 'configuration'],
        ];

        foreach ($items as $item) {
            if (!$item['done']) {
                return ['blocked' => true, 'step' => $item['step'], 'items' => $items];
            }
        }

        return ['blocked' => false, 'step' => 'install', 'items' => $items];
    }

    private const TIMEZONES = [
        'UTC', 'America/Los_Angeles', 'America/Denver', 'America/Chicago', 'America/New_York',
        'America/Sao_Paulo', 'Europe/London', 'Europe/Dublin', 'Europe/Paris', 'Europe/Berlin',
        'Europe/Madrid', 'Europe/Warsaw', 'Africa/Lagos', 'Africa/Johannesburg', 'Africa/Nairobi',
        'Asia/Dubai', 'Asia/Karachi', 'Asia/Kolkata', 'Asia/Singapore', 'Asia/Hong_Kong',
        'Asia/Shanghai', 'Asia/Tokyo', 'Australia/Sydney', 'Pacific/Auckland',
    ];
}
