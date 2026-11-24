<h3 class="mb-4">Dashboard</h3>
<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Total documents (owned)</div><div class="stat"><?= count($owned) ?></div></div></div></div>
  <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Shared with me</div><div class="stat"><?= count($shared) ?></div></div></div></div>
  <div class="col-md-4"><div class="card"><div class="card-body d-flex flex-column justify-content-center h-100">
    <a class="btn btn-primary" href="<?= e(url('doc/upload')) ?>">Upload document</a></div></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-6"><div class="card"><div class="card-header bg-white fw-semibold">My documents</div>
    <ul class="list-group list-group-flush">
    <?php foreach (array_slice($owned, 0, 5) as $d): ?>
      <li class="list-group-item d-flex justify-content-between"><a href="<?= e(url('doc/show', ['id' => $d['id']])) ?>"><?= e($d['original_filename']) ?></a>
        <span class="text-muted small"><?= e(strtoupper($d['file_type'])) ?> &middot; <?= e(human_size((int)$d['file_size'])) ?></span></li>
    <?php endforeach; if (!$owned): ?><li class="list-group-item text-muted">No documents yet.</li><?php endif; ?>
    </ul></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-header bg-white fw-semibold">Shared with me</div>
    <ul class="list-group list-group-flush">
    <?php foreach (array_slice($shared, 0, 5) as $d): ?>
      <li class="list-group-item d-flex justify-content-between"><a href="<?= e(url('doc/show', ['id' => $d['id']])) ?>"><?= e($d['original_filename']) ?></a>
        <span class="text-muted small">from <?= e($d['owner_name']) ?></span></li>
    <?php endforeach; if (!$shared): ?><li class="list-group-item text-muted">Nothing shared with you.</li><?php endif; ?>
    </ul></div></div>
  <div class="col-12"><div class="card"><div class="card-header bg-white fw-semibold">Recent activities</div>
    <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Time</th><th>Activity</th><th>Document</th><th>Outcome</th></tr></thead><tbody>
    <?php foreach ($activities as $a): ?>
      <tr><td><?= e($a['created_at']) ?></td><td><?= e(str_replace('_', ' ', $a['action'])) ?></td><td><?= $a['document_id'] ? '#' . e($a['document_id']) : '-' ?></td>
        <td><span class="badge bg-<?= $a['outcome'] === 'success' ? 'success' : 'danger' ?>"><?= e($a['outcome']) ?></span></td></tr>
    <?php endforeach; if (!$activities): ?><tr><td colspan="4" class="text-muted">No activity yet.</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
</div>
<?php if ($isAdmin): ?><p class="mt-3 text-muted small">Administrator: you can manage users and audit logs, but you have no automatic access to other users' documents.</p><?php endif; ?>
