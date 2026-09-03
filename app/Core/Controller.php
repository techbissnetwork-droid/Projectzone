<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected View $view;
    protected Seo $seo;
    protected Database $db;
    protected Session $session;
    protected Auth $auth;
    protected Config $config;

    public function __construct(protected Application $app)
    {
        $this->view = $app->make('view');
        $this->seo = $app->make('seo');
        $this->db = $app->make('db');
        $this->session = $app->make('session');
        $this->auth = $app->make('auth');
        $this->config = $app->config();
    }

    protected function render(string $view, array $data = [], int $status = 200): Response
    {
        $request = $data['request'] ?? Request::capture();
        $this->app->shareViewGlobals($this->view, $request);
        return Response::html($this->view->render($view, $data), $status);
    }

    protected function redirect(string $path, int $status = 302): Response
    {
        return Response::redirect(url($path), $status);
    }

    protected function back(Request $request, string $fallback = '/'): Response
    {
        $referer = (string) ($request->server['HTTP_REFERER'] ?? '');
        $base = (string) $this->config->get('app.url', '');
        if ($referer !== '' && $base !== '' && str_starts_with($referer, $base)) {
            return Response::redirect($referer);
        }
        return $this->redirect($fallback);
    }

    protected function withInput(Request $request, array $errors): void
    {
        $this->session->start();
        $this->session->flash('errors', $errors);
        $this->session->flash('old', array_diff_key($request->body, array_flip(['password', 'password_confirmation', '_token'])));
    }

    protected function requireCsrf(Request $request): void
    {
        /** @var Csrf $csrf */
        $csrf = $this->app->make('csrf');
        $this->session->start();
        if (!$csrf->verify($request)) {
            throw new HttpException(419, 'Your session expired. Please refresh and try again.');
        }
    }

    protected function cache(): Cache
    {
        return $this->app->make('cache');
    }
}
