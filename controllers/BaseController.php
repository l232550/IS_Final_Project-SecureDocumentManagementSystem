<?php
abstract class BaseController
{
    protected function render(string $view, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        $currentUser = $this->currentUser();
        $flashes = SessionService::takeFlash();
        extract($data, EXTR_SKIP);
        require Config::root() . '/views/layout/header.php';
        require Config::root() . "/views/$view.php";
        require Config::root() . '/views/layout/footer.php';
    }

    protected function redirect(string $route, array $params = []): never
    {
        header('Location: ' . url($route, $params));
        exit;
    }

    protected function currentUser(): ?array
    {
        static $cache = false;
        if ($cache === false) {
            $cache = null;
            if (!empty($_SESSION['user_id'])) {
                $u = Container::users()->findById((int)$_SESSION['user_id']);   // fresh from DB: deactivation takes effect immediately
                if ($u !== null && (int)$u['is_active'] === 1) {
                    $cache = $u;
                } else {
                    SessionService::destroy();
                    SessionService::start();
                }
            }
        }
        return $cache;
    }

    protected function requireLogin(): array
    {
        $u = $this->currentUser();
        if ($u === null) {
            $this->redirect('login');
        }
        return $u;
    }

    protected function requireAdmin(): array
    {
        $u = $this->requireLogin();
        if (!Container::authz()->isAdmin($u)) {
            Container::audit()->log('failed_authorization', 'denied', (int)$u['id'], null, ['area' => 'admin']);
            $this->denied();
        }
        return $u;
    }

    /** Checks the CSRF token of a POST request. */
    protected function requireCsrf(): void
    {
        if (!Csrf::valid($_POST['csrf_token'] ?? null)) {
            Container::audit()->log('csrf_failure', 'denied', $this->currentUser()['id'] ?? null, null, ['route' => $_GET['r'] ?? '']);
            $this->render('errors/403', ['message' => 'Your form expired or was invalid. Please go back and try again.'], 403);
            exit;
        }
    }

    protected function denied(string $message = 'Access denied.'): never
    {
        $this->render('errors/403', ['message' => $message], 403);
        exit;
    }

    protected function docId(): int
    {
        return (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    }

    public function notFound(): void
    {
        $this->render('errors/404', [], 404);
    }
}
