<?php
/** Front controller: the only PHP file exposed to the web. Everything else lives outside public/. */
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';

// ---- HTTP security headers ----
header_remove('X-Powered-By');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' https://cdn.jsdelivr.net; "
    . "img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store');
if (SessionService::isHttps()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// ---- generic error handling: log details, show nothing sensitive ----
$fail = function (Throwable $e): void {
    error_log(sprintf('[%s] %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><meta charset="utf-8"><title>Error</title><body style="font-family:sans-serif;margin:3rem">'
       . '<h1>Something went wrong</h1><p>The request could not be completed. Please try again later.</p></body>';
};
set_exception_handler($fail);

$expired = SessionService::start();
if ($expired) {
    SessionService::flash('warning', 'Your session expired after 30 minutes of inactivity. Please sign in again.');
}

// route => [Controller, method, allowed HTTP method]
$routes = [
    'login'          => ['AuthController', 'login', 'ANY'],
    'register'       => ['AuthController', 'register', 'ANY'],
    'logout'         => ['AuthController', 'logout', 'POST'],
    'forgot'         => ['AuthController', 'forgot', 'ANY'],
    'reset'          => ['AuthController', 'reset', 'ANY'],
    'dashboard'      => ['DashboardController', 'index', 'GET'],
    'documents'      => ['DocumentController', 'index', 'GET'],
    'doc/upload'     => ['DocumentController', 'upload', 'ANY'],
    'doc/show'       => ['DocumentController', 'show', 'GET'],
    'doc/view'       => ['DocumentController', 'view', 'GET'],
    'doc/download'   => ['DocumentController', 'download', 'GET'],
    'doc/edit'       => ['DocumentController', 'edit', 'ANY'],
    'doc/delete'     => ['DocumentController', 'delete', 'POST'],
    'doc/verify'     => ['DocumentController', 'verify', 'POST'],
    'share'          => ['ShareController', 'manage', 'ANY'],
    'share/revoke'   => ['ShareController', 'revoke', 'POST'],
    'admin/users'    => ['AdminController', 'users', 'GET'],
    'admin/user'     => ['AdminController', 'updateUser', 'POST'],
    'admin/audit'    => ['AdminController', 'audit', 'GET'],
    'admin/verify'   => ['AdminController', 'verifyAudit', 'POST'],
];

$route = (string)($_GET['r'] ?? 'dashboard');
if (!isset($routes[$route])) {
    http_response_code(404);
    (new AuthController())->notFound();
    exit;
}
[$class, $method, $verb] = $routes[$route];
if ($verb !== 'ANY' && $_SERVER['REQUEST_METHOD'] !== $verb) {
    http_response_code(405);
    header('Allow: ' . $verb);
    echo 'Method not allowed.';
    exit;
}
(new $class())->$method();
