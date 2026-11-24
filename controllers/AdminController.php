<?php
/** Administrators manage accounts and investigate the audit trail. They have no route to document contents. */
class AdminController extends BaseController
{
    public function users(): void
    {
        $this->requireAdmin();
        $this->render('admin/users', ['users' => Container::users()->all()]);
    }

    public function updateUser(): void
    {
        $admin = $this->requireAdmin();
        $this->requireCsrf();
        $id = (int)($_POST['user_id'] ?? 0);
        $target = Container::users()->findById($id);
        if ($target === null) {
            SessionService::flash('danger', 'User not found.');
            $this->redirect('admin/users');
        }
        if ($id === (int)$admin['id']) {
            SessionService::flash('danger', 'You cannot change your own role or status.');
            $this->redirect('admin/users');
        }
        $change = (string)($_POST['change'] ?? '');
        if ($change === 'role' && in_array($_POST['role'] ?? '', ['admin', 'user'], true)) {
            Container::users()->updateRole($id, $_POST['role']);
            Container::audit()->log('role_changed', 'success', (int)$admin['id'], null, ['target_user' => $id, 'role' => $_POST['role']]);
            SessionService::flash('success', 'Role updated.');
        } elseif ($change === 'status') {
            $active = ($_POST['active'] ?? '') === '1';
            Container::users()->setActive($id, $active);
            Container::audit()->log('user_status_changed', 'success', (int)$admin['id'], null, ['target_user' => $id, 'active' => $active]);
            SessionService::flash('success', $active ? 'Account enabled.' : 'Account disabled.');
        } else {
            SessionService::flash('danger', 'Invalid request.');
        }
        $this->redirect('admin/users');
    }

    public function audit(): void
    {
        $this->requireAdmin();
        $repo = Container::auditRepo();
        $action = (string)($_GET['action'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per = 50;
        $total = $repo->count($action ?: null, null);
        $this->render('admin/audit', [
            'rows' => $repo->page($per, ($page - 1) * $per, $action ?: null, null),
            'actions' => $repo->actions(), 'action' => $action, 'page' => $page, 'pages' => max(1, (int)ceil($total / $per)),
            'total' => $total, 'result' => $_SESSION['audit_result'] ?? null,
        ]);
        unset($_SESSION['audit_result']);
    }

    public function verifyAudit(): void
    {
        $admin = $this->requireAdmin();
        $this->requireCsrf();
        $result = Container::audit()->verify();
        Container::audit()->log($result['ok'] ? 'audit_verified' : 'audit_verification_failure', $result['ok'] ? 'success' : 'failure',
            (int)$admin['id'], null, ['checked' => $result['checked'], 'errors' => count($result['errors'])]);
        $_SESSION['audit_result'] = $result;
        $this->redirect('admin/audit');
    }
}
