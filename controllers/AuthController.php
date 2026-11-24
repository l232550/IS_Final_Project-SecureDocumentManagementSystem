<?php
class AuthController extends BaseController
{
    public function login(): void
    {
        if ($this->currentUser() !== null) { $this->redirect('dashboard'); }
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            try {
                Container::auth()->login((string)($_POST['identifier'] ?? ''), (string)($_POST['password'] ?? ''));
                $this->redirect('dashboard');
            } catch (AuthException $e) {
                $error = $e->getMessage();
            }
        }
        $this->render('auth/login', ['error' => $error]);
    }

    public function register(): void
    {
        if ($this->currentUser() !== null) { $this->redirect('dashboard'); }
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            try {
                Container::auth()->register((string)($_POST['username'] ?? ''), (string)($_POST['email'] ?? ''),
                    (string)($_POST['password'] ?? ''), (string)($_POST['confirm'] ?? ''));
                SessionService::flash('success', 'Account created. You can now sign in.');
                $this->redirect('login');
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            }
        }
        $this->render('auth/register', ['error' => $error]);
    }

    public function logout(): void
    {
        $this->requireCsrf();
        Container::auth()->logout($this->currentUser());
        SessionService::start();
        SessionService::flash('success', 'You have been signed out.');
        $this->redirect('login');
    }

    public function forgot(): void
    {
        $done = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            Container::auth()->requestReset((string)($_POST['email'] ?? ''));
            $done = true;     // same message whether or not the account exists
        }
        $this->render('auth/forgot', ['done' => $done]);
    }

    public function reset(): void
    {
        $token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            try {
                Container::auth()->resetPassword($token, (string)($_POST['password'] ?? ''), (string)($_POST['confirm'] ?? ''));
                SessionService::flash('success', 'Password changed. Please sign in with your new password.');
                $this->redirect('login');
            } catch (ValidationException | AuthException $e) {
                $error = $e->getMessage();
            }
        }
        $valid = Container::auth()->tokenIsValid($token);
        $this->render('auth/reset', ['token' => $token, 'valid' => $valid, 'error' => $error]);
    }
}
