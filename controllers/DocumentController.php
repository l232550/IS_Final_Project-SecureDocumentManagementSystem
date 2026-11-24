<?php
class DocumentController extends BaseController
{
    /** Runs a document action; any denial becomes one generic 403 page. */
    private function guard(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (AccessDeniedException) {
            $this->denied('Access denied.');
        } catch (IntegrityException) {
            $this->render('errors/403', ['message' => 'Integrity check failed: this document was modified or damaged and was not released.'], 409);
            exit;
        }
    }

    public function index(): void
    {
        $user = $this->requireLogin();
        $this->render('documents/index', ['owned' => Container::documents()->listOwned($user), 'shared' => Container::documents()->listShared($user)]);
    }

    public function upload(): void
    {
        $user = $this->requireLogin();
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            try {
                $id = Container::documents()->upload($user, $_FILES['document'] ?? []);
                SessionService::flash('success', 'Document encrypted and stored.');
                $this->redirect('doc/show', ['id' => $id]);
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            }
        }
        $this->render('documents/upload', ['error' => $error]);
    }

    public function show(): void
    {
        $user = $this->requireLogin();
        $doc = $this->guard(fn() => Container::documents()->fetchAuthorized($user, $this->docId(), 'details'));
        $authz = Container::authz();
        $this->render('documents/show', [
            'doc' => $doc, 'role' => $authz->roleOn($user, $doc),
            'can' => ['view' => $authz->can($user, $doc, 'view'), 'download' => $authz->can($user, $doc, 'download'),
                      'edit' => $authz->can($user, $doc, 'edit'), 'delete' => $authz->can($user, $doc, 'delete'),
                      'share' => $authz->can($user, $doc, 'share')],
            'verifyResult' => $_SESSION['verify_result'] ?? null,
        ]);
        unset($_SESSION['verify_result']);
    }

    private function safeName(string $name): string
    {
        $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? 'document';
        return 'filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name);
    }

    private function send(string $mode): void
    {
        $user = $this->requireLogin();
        [$doc, $plain] = $this->guard(fn() => Container::documents()->open($user, $this->docId(), $mode));
        if ($mode === 'view' && $doc['file_type'] === 'docx') {
            SessionService::flash('info', 'DOCX files cannot be previewed in the browser. Use Download if you have the permission.');
            $this->redirect('doc/show', ['id' => (int)$doc['id']]);
        }
        $inline = $mode === 'view';
        header('Content-Type: ' . ($doc['file_type'] === 'txt' ? 'text/plain; charset=utf-8' : $doc['mime_type']));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; ' . $this->safeName($doc['original_filename']));
        header('Content-Length: ' . strlen($plain));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, private');
        echo $plain;
        exit;
    }

    public function view(): void     { $this->send('view'); }
    public function download(): void { $this->send('download'); }

    public function edit(): void
    {
        $user = $this->requireLogin();
        $doc = $this->guard(fn() => Container::documents()->fetchAuthorized($user, $this->docId(), 'edit'));
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            try {
                $this->guard(fn() => Container::documents()->update($user, (int)$doc['id'], $_FILES['document'] ?? []));
                SessionService::flash('success', 'Document updated and re-encrypted.');
                $this->redirect('doc/show', ['id' => (int)$doc['id']]);
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            }
        }
        $this->render('documents/edit', ['doc' => $doc, 'error' => $error]);
    }

    public function delete(): void
    {
        $user = $this->requireLogin();
        $this->requireCsrf();
        $this->guard(fn() => Container::documents()->delete($user, $this->docId()));
        SessionService::flash('success', 'Document deleted.');
        $this->redirect('documents');
    }

    public function verify(): void
    {
        $user = $this->requireLogin();
        $this->requireCsrf();
        $id = $this->docId();
        $_SESSION['verify_result'] = $this->guard(fn() => Container::documents()->verify($user, $id));
        $this->redirect('doc/show', ['id' => $id]);
    }
}
