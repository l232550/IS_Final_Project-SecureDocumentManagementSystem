<?php
/**
 * Business logic for documents. Every public method that touches a document first calls fetchAuthorized(),
 * so the server (not the UI) enforces access. Plaintext lives only in memory: no temporary decrypted files exist.
 */
class DocumentService
{
    public function __construct(
        private DocumentRepository $docs,
        private AuthorizationService $authz,
        private KeyManager $keys,
        private StorageService $storage,
        private AuditService $audit
    ) {}

    /** Loads the document and checks the action. Missing and forbidden documents give the same error (no ID enumeration). */
    public function fetchAuthorized(array $user, int $docId, string $action): array
    {
        $doc = $this->docs->find($docId);
        if ($doc === null) {
            $this->audit->log('unauthorized_access', 'denied', (int)$user['id'], $docId, ['action' => $action, 'reason' => 'no_such_document']);
            throw new AccessDeniedException('Access denied.');
        }
        if (!$this->authz->can($user, $doc, $action)) {
            $related = $this->authz->roleOn($user, $doc) !== 'none';
            $this->audit->log($related ? 'failed_authorization' : 'unauthorized_access', 'denied', (int)$user['id'], $docId,
                ['action' => $action, 'role' => $this->authz->roleOn($user, $doc)]);
            throw new AccessDeniedException('Access denied.');
        }
        return $doc;
    }

    /** Encrypt (envelope) and persist. Returns [row-fields for DB]. */
    private function protect(string $content, string $storageId, int $ownerId, array $meta): array
    {
        $dek = CryptoService::newKey();                                        // 1. fresh random DEK per document
        $enc = CryptoService::encrypt($content, $dek, "doc|$storageId");        // 2. AES-256-GCM, fresh 96-bit nonce
        $wrap = $this->keys->wrapDek($dek, $storageId);                         // 3. wrap DEK with active KEK (own nonce)
        $this->storage->write($storageId, $enc['ciphertext']);                  // 4. only ciphertext reaches disk
        if (function_exists('sodium_memzero')) { sodium_memzero($dek); }
        return $meta + $wrap + [
            'owner_id' => $ownerId, 'storage_id' => $storageId, 'doc_nonce' => $enc['nonce'], 'auth_tag' => $enc['tag'],
            'sha256_fingerprint' => CryptoService::fingerprint($content),
        ];
    }

    public function upload(array $user, array $file, bool $requireHttpUpload = true): int
    {
        @ini_set('memory_limit', '256M');
        try {
            $v = UploadValidator::validate($file, $requireHttpUpload);
        } catch (ValidationException $e) {
            $this->audit->log('document_upload', 'failure', (int)$user['id'], null, ['reason' => 'validation']);
            throw $e;
        }
        $storageId = StorageService::newId();
        $row = $this->protect($v['content'], $storageId, (int)$user['id'], [
            'original_filename' => $v['original_filename'], 'file_type' => $v['file_type'],
            'mime_type' => $v['mime_type'], 'file_size' => $v['file_size'],
        ]);
        try {
            $id = $this->docs->create($row);
        } catch (Throwable $e) {
            $this->storage->delete($storageId);                                 // no orphan files
            throw $e;
        }
        $this->audit->log('document_upload', 'success', (int)$user['id'], $id, ['type' => $v['file_type'], 'size' => $v['file_size']]);
        return $id;
    }

    public function update(array $user, int $docId, array $file, bool $requireHttpUpload = true): void
    {
        @ini_set('memory_limit', '256M');
        $doc = $this->fetchAuthorized($user, $docId, 'edit');
        try {
            $v = UploadValidator::validate($file, $requireHttpUpload);
            if ($v['file_type'] !== $doc['file_type']) {
                throw new ValidationException('The new file must be of the same type (' . strtoupper($doc['file_type']) . ').');
            }
        } catch (ValidationException $e) {
            $this->audit->log('document_update', 'failure', (int)$user['id'], $docId, ['reason' => 'validation']);
            throw $e;
        }
        $newStorageId = StorageService::newId();
        $row = $this->protect($v['content'], $newStorageId, (int)$doc['owner_id'], [
            'original_filename' => $v['original_filename'], 'file_type' => $v['file_type'],
            'mime_type' => $v['mime_type'], 'file_size' => $v['file_size'],
        ]);
        try {
            $this->docs->replaceContent($docId, $row);
        } catch (Throwable $e) {
            $this->storage->delete($newStorageId);
            throw $e;
        }
        $this->storage->delete($doc['storage_id']);                              // remove the old encrypted file
        $this->audit->log('document_update', 'success', (int)$user['id'], $docId, ['size' => $v['file_size']]);
    }

    /** Decrypts in memory and verifies GCM tag + SHA-256 fingerprint. @throws IntegrityException */
    public function readPlaintext(array $doc): string
    {
        $encrypted = $this->storage->read($doc['storage_id']);
        $dek = $this->keys->unwrapDek($doc);
        $plain = CryptoService::decrypt($encrypted, $dek, $doc['doc_nonce'], $doc['auth_tag'], 'doc|' . $doc['storage_id']);
        if (function_exists('sodium_memzero')) { sodium_memzero($dek); }
        if (!hash_equals($doc['sha256_fingerprint'], CryptoService::fingerprint($plain))) {
            throw new IntegrityException('SHA-256 fingerprint does not match the stored value.');
        }
        return $plain;
    }

    /** @return array{0:array,1:string} [document row, plaintext] */
    public function open(array $user, int $docId, string $mode): array
    {
        $action = $mode === 'download' ? 'download' : 'view';
        $doc = $this->fetchAuthorized($user, $docId, $action);
        try {
            $plain = $this->readPlaintext($doc);
        } catch (IntegrityException $e) {
            $this->audit->log('integrity_failure', 'failure', (int)$user['id'], $docId, ['mode' => $mode]);
            throw $e;
        }
        $this->audit->log($action === 'download' ? 'document_download' : 'document_view', 'success', (int)$user['id'], $docId);
        return [$doc, $plain];
    }

    public function delete(array $user, int $docId): void
    {
        $doc = $this->fetchAuthorized($user, $docId, 'delete');
        $this->docs->delete($docId);                                            // permissions removed by FK cascade
        $this->storage->delete($doc['storage_id']);
        $this->audit->log('document_delete', 'success', (int)$user['id'], $docId, ['type' => $doc['file_type']]);
    }

    /** @return array{ok:bool,gcm:bool,sha256:bool,message:string} */
    public function verify(array $user, int $docId): array
    {
        $doc = $this->fetchAuthorized($user, $docId, 'verify');
        try {
            $this->readPlaintext($doc);
            $r = ['ok' => true, 'gcm' => true, 'sha256' => true, 'message' => 'Integrity verified: AES-GCM tag and SHA-256 fingerprint both match.'];
        } catch (IntegrityException $e) {
            $gcm = !str_contains($e->getMessage(), 'fingerprint');
            $r = ['ok' => false, 'gcm' => $gcm, 'sha256' => false,
                'message' => 'INTEGRITY FAILURE: the stored document was modified, is missing, or the key does not match.'];
            $this->audit->log('integrity_failure', 'failure', (int)$user['id'], $docId, ['mode' => 'verify']);
        }
        $this->audit->log('document_verify', $r['ok'] ? 'success' : 'failure', (int)$user['id'], $docId);
        return $r;
    }

    public function listOwned(array $user): array  { return $this->docs->listOwned((int)$user['id']); }
    public function listShared(array $user): array { return $this->docs->listSharedWith((int)$user['id']); }
}
