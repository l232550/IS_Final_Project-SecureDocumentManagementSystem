<h3 class="mb-1">Share: <?= e($doc['original_filename']) ?></h3>
<p class="text-muted">Access is checked on the server for every request. Revoking removes access immediately, but copies a user already downloaded cannot be recalled.</p>
<div class="row g-3">
  <div class="col-lg-5"><div class="card"><div class="card-header bg-white fw-semibold">Share with a user</div><div class="card-body">
    <?php if (!$recipients): ?><p class="text-muted mb-0">There are no other registered users yet.</p><?php else: ?>
    <form method="post" action="<?= e(url('share', ['id' => $doc['id']])) ?>">
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label" for="recipient_id">Recipient</label>
        <select class="form-select" id="recipient_id" name="recipient_id" required><option value="">Select a user...</option>
        <?php foreach ($recipients as $r): ?><option value="<?= e($r['id']) ?>"><?= e($r['username']) ?></option><?php endforeach; ?></select></div>
      <div class="mb-3"><label class="form-label" for="level">Permission</label>
        <select class="form-select" id="level" name="level"><option value="viewer">Viewer (view only)</option><option value="editor">Editor (view + update)</option></select></div>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="can_download" name="can_download" value="1">
        <label class="form-check-label" for="can_download">Allow download</label></div>
      <div class="mb-3"><label class="form-label" for="expires_at">Expires (optional)</label>
        <input class="form-control" type="datetime-local" id="expires_at" name="expires_at" data-future="1"></div>
      <button class="btn btn-primary w-100">Grant / update access</button>
    </form><?php endif; ?>
  </div></div></div>
  <div class="col-lg-7"><div class="card"><div class="card-header bg-white fw-semibold">Users with access</div><div class="table-responsive">
    <table class="table mb-0 align-middle"><thead><tr><th>User</th><th>Permission</th><th>Expires</th><th></th></tr></thead><tbody>
    <?php foreach ($shares as $s): ?>
      <tr><td><?= e($s['username']) ?></td><td><?= (int)$s['can_edit'] ? 'Editor' : 'Viewer' ?><?= (int)$s['can_download'] ? ' + download' : '' ?></td>
        <td><?= e($s['expires_at'] ?? 'never') ?><?= ($s['expires_at'] && strtotime($s['expires_at']) <= time()) ? ' <span class="badge bg-warning text-dark">expired</span>' : '' ?></td>
        <td class="text-end"><form method="post" action="<?= e(url('share/revoke')) ?>" data-confirm="Revoke access for <?= e($s['username']) ?>?">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($doc['id']) ?>"><input type="hidden" name="user_id" value="<?= e($s['user_id']) ?>">
          <button class="btn btn-sm btn-outline-danger">Revoke</button></form></td></tr>
    <?php endforeach; if (!$shares): ?><tr><td colspan="4" class="text-muted">Not shared with anyone.</td></tr><?php endif; ?>
    </tbody></table></div></div>
    <a class="btn btn-link" href="<?= e(url('doc/show', ['id' => $doc['id']])) ?>">&larr; Back to document</a></div>
</div>
