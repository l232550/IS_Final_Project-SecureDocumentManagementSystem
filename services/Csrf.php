<?php
/** Synchronizer-token CSRF protection. Every state-changing request is POST and must carry the session's token. */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function valid(?string $submitted): bool
    {
        return is_string($submitted) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $submitted);
    }
}
