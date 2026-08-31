<?php
declare(strict_types=1);

namespace Techbiss\Controllers\Admin;

use Techbiss\Core\ActivityLog;
use Techbiss\Core\App;
use Techbiss\Core\Auth;
use Techbiss\Core\Csrf;
use Techbiss\Core\Request;
use Techbiss\Core\Session;
use Techbiss\Core\Validator;
use Techbiss\Core\View;

final class AuthController
{
    private View $view;

    public function __construct()
    {
        $this->view = new View(App::root() . '/admin/views', App::root() . '/admin/views/auth-layout.php');
        $this->view->shareMany([
            'settings' => App::settings(),
            'flash'    => App::flashMessages(),
        ]);
    }

    public function showLogin(Request $request): void
    {
        if (Auth::check()) {
            redirect('/admin');
        }
        $this->view->render('login', ['title' => 'Sign in']);
    }

    public function login(Request $request): void
    {
        Csrf::verify($request);

        $v = Validator::make($request->all())
            ->email('email')
            ->required('password', 'Password', 1, 200);

        if ($v->fails()) {
            flash('error', $v->firstError());
            Session::flashInput(['email' => $request->str('email')]);
            redirect('/admin/login');
        }

        $result = Auth::attempt((string) $v->get('email'), (string) $request->post('password'), $request->ip());

        if (!$result['ok']) {
            flash('error', $result['message']);
            Session::flashInput(['email' => $v->get('email')]);
            redirect('/admin/login');
        }

        ActivityLog::record('login', 'admin', Auth::id(), 'Signed in');
        flash('success', 'Welcome back, ' . Auth::name() . '.');

        $intended = Session::get('intended_url');
        Session::forget('intended_url');
        redirect(is_string($intended) && str_starts_with($intended, '/admin') ? $intended : '/admin');
    }

    public function logout(Request $request): void
    {
        Csrf::verify($request);
        ActivityLog::record('logout', 'admin', Auth::id(), 'Signed out');
        Auth::logout();
        flash('success', 'You have been signed out.');
        redirect('/admin/login');
    }
}
