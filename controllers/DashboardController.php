<?php
class DashboardController extends BaseController
{
    public function index(): void
    {
        $user = $this->requireLogin();
        $docs = Container::documents();
        $owned = $docs->listOwned($user);
        $shared = $docs->listShared($user);
        $this->render('dashboard', [
            'owned' => $owned, 'shared' => $shared,
            'activities' => Container::auditRepo()->recentForUser((int)$user['id']),
            'isAdmin' => Container::authz()->isAdmin($user),
        ]);
    }
}
