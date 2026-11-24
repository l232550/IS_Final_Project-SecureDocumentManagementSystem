<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0"><?= e($doc['original_filename']) ?></h3>
  <span class="badge bg-info text-dark fs-6">Your role: <?= e($role) ?></span></div>
<?php if ($verifyResult): ?>
  <div class="alert alert-<?= $verifyResult['ok'] ? 'success' : 'danger' ?>"><?= e($verifyResult['message']) ?></div>
<?php endif; ?>
<div class="row g-3">
  <div class="col-lg-7"><div class="card"><div class="card-header bg-white fw-semibold">Details</div>
    <table class="table mb-0"><tbody>
      <tr><th>Owner</th><td><?= e($doc['owner_name']) ?></td></tr>
      <tr><th>Type</th><td><?= e(strtoupper($doc['file_type'])) ?> (<?= e($doc['mime_type']) ?>)</td></tr>
      <tr><th>Size</th><td><?= e(human_size((int)$doc['file_size'])) ?></td></tr>
      <tr><th>Uploaded</th><td><?= e($doc['created_at']) ?></td></tr>
      <tr><th>Last updated</th><td><?= e($doc['updated_at']) ?></td></tr>
      <tr><th>Encryption</th><td><?= e($doc['enc_algorithm']) ?>, KEK version <?= e($doc['key_version']) ?></td></tr>
      <tr><th>SHA-256 fingerprint</th><td class="mono"><?= e($doc['sha256_fingerprint']) ?></td></tr>
    </tbody></table></div></div>
  <div class="col-lg-5"><div class="card"><div class="card-header bg-white fw-semibold">Actions</div><div class="card-body d-grid gap-2">
    <?php if ($can['view'] && $doc['file_type'] !== 'docx'): ?><a class="btn btn-primary" target="_blank" rel="noopener" href="<?= e(url('doc/view', ['id' => $doc['id']])) ?>">View</a><?php endif; ?>
    <?php if ($can['download']): ?><a class="btn btn-outline-primary" href="<?= e(url('doc/download', ['id' => $doc['id']])) ?>">Download</a><?php endif; ?>
    <?php if ($can['edit']): ?><a class="btn btn-outline-secondary" href="<?= e(url('doc/edit', ['id' => $doc['id']])) ?>">Update</a><?php endif; ?>
    <?php if ($can['view']): ?>
      <form method="post" action="<?= e(url('doc/verify')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($doc['id']) ?>">
        <button class="btn btn-outline-success w-100">Verify integrity</button></form>
    <?php endif; ?>
    <?php if ($can['share']): ?><a class="btn btn-outline-dark" href="<?= e(url('share', ['id' => $doc['id']])) ?>">Share / manage access</a><?php endif; ?>
    <?php if ($can['delete']): ?>
      <form method="post" action="<?= e(url('doc/delete')) ?>" data-confirm="Permanently delete this document? This cannot be undone.">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($doc['id']) ?>"><button class="btn btn-outline-danger w-100">Delete</button></form>
    <?php endif; ?>
    <?php if (!$can['download'] && $role !== 'owner'): ?><div class="text-muted small">Downloading is not part of your permission.</div><?php endif; ?>
  </div></div></div>
</div>
