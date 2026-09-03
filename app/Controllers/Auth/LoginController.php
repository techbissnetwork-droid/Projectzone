<?php
declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class LoginController extends Controller
{
    private const COPY = [
        'admin' => [
            'eyebrow' => 'Restricted access',
            'title' => 'Admin Console',
            'lede' => 'Platform administration — users, catalogue, orders, deployments and configuration.',
            'icon' => 'shield',
            'accent' => 'blue',
            'points' => [
                ['chart', 'Platform overview', 'Revenue, deployments, pipeline and fleet health in one view.'],
                ['users', 'People and access', 'Provision staff and client accounts, and revoke them in one action.'],
                ['grid', 'Catalogue control', 'Publish, retire and price every marketplace product.'],
                ['settings', 'Configuration', 'SEO, caching, commerce and mail settings without a deploy.'],
            ],
            'demo' => 'admin@techbiss.com',
            'demo_password' => 'the password set during installation',
        ],
        'staff' => [
            'eyebrow' => 'Team access',
            'title' => 'Staff Workspace',
            'lede' => 'Your pipeline, your projects, your tickets and the tasks due this week.',
            'icon' => 'users',
            'accent' => 'teal',
            'points' => [
                ['trend', 'Pipeline', 'Every lead with its owner, value and next action.'],
                ['layers', 'Projects', 'Phase, health and milestone status across the portfolio.'],
                ['ticket', 'Support queue', 'Client tickets triaged by priority and age.'],
                ['check-circle', 'Your tasks', 'What is due, what is late, and what you closed.'],
            ],
            'demo' => 'engineer@techbiss.com',
            'demo_password' => 'StaffDemo!2026',
        ],
        'client' => [
            'eyebrow' => 'Client access',
            'title' => 'Client Portal',
            'lede' => 'Project progress, licences, downloads, deployments, invoices and support.',
            'icon' => 'briefcase',
            'accent' => 'violet',
            'points' => [
                ['layers', 'Project progress', 'Phase, milestones and health, updated as we work.'],
                ['tag', 'Licences', 'Every marketplace licence with its key and registered domains.'],
                ['rocket', 'Deployments', 'Launch a new installation and watch it progress.'],
                ['ticket', 'Support', 'Raise a ticket and see every reply in one thread.'],
            ],
            'demo' => 'client@northwind.example',
            'demo_password' => 'ClientDemo!2026',
        ],
    ];

    public function showAdmin(Request $request): Response
    {
        return $this->showPortal($request, 'admin');
    }

    public function showStaff(Request $request): Response
    {
        return $this->showPortal($request, 'staff');
    }

    public function showClient(Request $request): Response
    {
        return $this->showPortal($request, 'client');
    }

    public function authenticateAdmin(Request $request): Response
    {
        return $this->attempt($request, 'admin');
    }

    public function authenticateStaff(Request $request): Response
    {
        return $this->attempt($request, 'staff');
    }

    public function authenticateClient(Request $request): Response
    {
        return $this->attempt($request, 'client');
    }

    private function showPortal(Request $request, string $portal): Response
    {
        $this->session->start();

        // Already signed in — send them where they belong rather than showing
        // a form they do not need.
        if ($this->auth->check()) {
            $home = Auth::PORTALS[$this->auth->portal()]['home'] ?? '/';
            return $this->redirect($home);
        }

        $copy = self::COPY[$portal];
        $this->seo->title($copy['title'] . ' sign in');
        $this->seo->description('Sign in to the TECHBISS ' . $copy['title'] . '.');
        $this->seo->canonical('/' . $portal . '/login');
        $this->seo->noindex();

        return $this->render('auth.login', [
            'request' => $request,
            'portal' => $portal,
            'copy' => $copy,
            'others' => array_diff_key(self::COPY, [$portal => true]),
        ])->cachePrivate();
    }

    private function attempt(Request $request, string $portal): Response
    {
        $this->session->start();

        $validator = Validator::make($request->body, [
            'email' => 'required|email|max:190',
            'password' => 'required|max:255',
        ]);

        if ($validator->fails()) {
            $this->withInput($request, $validator->errors());
            $this->session->flash('error', 'Enter your email address and password.');
            return $this->redirect('/' . $portal . '/login');
        }

        [$ok, $message] = $this->auth->attempt(
            $portal,
            (string) $request->input('email'),
            (string) $request->input('password'),
            $request->ip()
        );

        if (!$ok) {
            $this->withInput($request, []);
            $this->session->flash('error', $message);
            return $this->redirect('/' . $portal . '/login');
        }

        $this->session->flash('status', $message);
        return $this->redirect(Auth::PORTALS[$portal]['home']);
    }

    public function logout(Request $request): Response
    {
        $this->session->start();
        $portal = (string) ($this->auth->portal() ?? 'client');
        $this->auth->logout($request->ip());
        $this->session->flash('status', 'You have been signed out.');
        return $this->redirect('/' . $portal . '/login');
    }

    public function forgot(Request $request): Response
    {
        $portal = $this->portalFromPath($request->path);
        $this->seo->title('Reset your password');
        $this->seo->description('Request a password reset link for your TECHBISS account.');
        $this->seo->noindex();
        $this->session->start();

        return $this->render('auth.forgot', [
            'request' => $request,
            'portal' => $portal,
            'copy' => self::COPY[$portal],
        ])->cachePrivate();
    }

    public function sendReset(Request $request): Response
    {
        $portal = $this->portalFromPath($request->path);
        $this->session->start();

        $validator = Validator::make($request->body, ['email' => 'required|email|max:190']);
        if ($validator->passes()) {
            $email = strtolower((string) $validator->validated()['email']);
            $user = $this->db->first('SELECT id, name, email FROM users WHERE email = ? LIMIT 1', [$email]);

            if ($user !== null) {
                $token = bin2hex(random_bytes(24));
                $this->session->put('reset_token_' . $user['id'], [
                    'token' => hash('sha256', $token),
                    'expires' => time() + 3600,
                ]);
                $this->app->make('mailer')->send(
                    $email,
                    'Reset your TECHBISS password',
                    '<p>Hello ' . e((string) $user['name']) . ',</p>'
                    . '<p>Use the link below within the next hour to choose a new password. '
                    . 'If you did not request this, no action is needed.</p>'
                    . '<p><a href="' . e(url('/' . $portal . '/login?token=' . $token)) . '">Reset your password</a></p>'
                );
            }
        }

        // Deliberately identical whether or not the address exists, so the form
        // cannot be used to enumerate accounts.
        $this->session->flash('status', 'If that address has an account, a reset link is on its way. Check your inbox.');
        return $this->redirect('/' . $portal . '/forgot-password');
    }

    private function portalFromPath(string $path): string
    {
        foreach (array_keys(self::COPY) as $portal) {
            if (str_starts_with($path, '/' . $portal)) {
                return $portal;
            }
        }
        return 'client';
    }
}
