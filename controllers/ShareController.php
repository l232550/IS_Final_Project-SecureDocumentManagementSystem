<?php
class ShareController extends BaseController
{
    public function manage(): void
    {
        $user = $this->requireLogin();
        $id = $this->docId();
        try {
            $doc = Container::documents()->fetchAuthorized($user, $id, 'share');
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->requireCsrf();
                try {
                    $new = Container::sharing()->share($user, $id, (int)($_POST['recipient_id'] ?? 0), (string)($_POST['level'] ?? ''),
                        isset($_POST['can_download']), (string)($_POST['expires_at'] ?? ''));
                    SessionService::flash('success', $new ? 'Document shared.' : 'Permissions updated.');
                } catch (ValidationException $e) {
                    SessionService::flash('danger', $e->getMessage());
                }
                $this->redirect('share', ['id' => $id]);
            }
            $this->render('documents/share', [
                'doc' => $doc, 'shares' => Container::sharing()->listFor($user, $id),
                'recipients' => Container::users()->recipients((int)$user['id']),
            ]);
        } catch (AccessDeniedException) {
            $this->denied();
        }
    }

    public function revoke(): void
    {
        $user = $this->requireLogin();
        $this->requireCsrf();
        $id = $this->docId();
        try {
            Container::sharing()->revoke($user, $id, (int)($_POST['user_id'] ?? 0));
            SessionService::flash('success', 'Access revoked. The user is denied from now on.');
        } catch (ValidationException $e) {
            SessionService::flash('danger', $e->getMessage());
        } catch (AccessDeniedException) {
            $this->denied();
        }
        $this->redirect('share', ['id' => $id]);
    }
}
