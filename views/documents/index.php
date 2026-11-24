<div class="d-flex justify-content-between align-items-center mb-3"><h3 class="mb-0">Documents</h3>
  <a class="btn btn-primary" href="<?= e(url('doc/upload')) ?>">Upload</a></div>
<div class="card mb-4"><div class="card-header bg-white fw-semibold">My documents</div><div class="table-responsive">
<table class="table table-hover mb-0 align-middle"><thead><tr><th>Name</th><th>Type</th><th>Size</th><th>Updated</th><th></th></tr></thead><tbody>
<?php foreach ($owned as $d): ?>
<tr><td><?= e($d['original_filename']) ?></td><td><?= e(strtoupper($d['file_type'])) ?></td><td><?= e(human_size((int)$d['file_size'])) ?></td><td><?= e($d['updated_at']) ?></td>
<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('doc/show', ['id' => $d['id']])) ?>">Details</a>
<a class="btn btn-sm btn-outline-secondary" href="<?= e(url('share', ['id' => $d['id']])) ?>">Share</a></td></tr>
<?php endforeach; if (!$owned): ?><tr><td colspan="5" class="text-muted">You have not uploaded anything yet.</td></tr><?php endif; ?>
</tbody></table></div></div>
<div class="card"><div class="card-header bg-white fw-semibold">Shared with me</div><div class="table-responsive">
<table class="table table-hover mb-0 align-middle"><thead><tr><th>Name</th><th>Owner</th><th>Permission</th><th>Expires</th><th></th></tr></thead><tbody>
<?php foreach ($shared as $d): ?>
<tr><td><?= e($d['original_filename']) ?></td><td><?= e($d['owner_name']) ?></td>
<td><?= (int)$d['can_edit'] ? 'Editor' : 'Viewer' ?><?= (int)$d['can_download'] ? ' + download' : '' ?></td><td><?= e($d['expires_at'] ?? 'never') ?></td>
<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('doc/show', ['id' => $d['id']])) ?>">Details</a></td></tr>
<?php endforeach; if (!$shared): ?><tr><td colspan="5" class="text-muted">Nothing has been shared with you.</td></tr><?php endif; ?>
</tbody></table></div></div>
