<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h3 class="mb-0">Audit logs <span class="text-muted fs-6">(<?= e($total) ?> records)</span></h3>
  <form method="post" action="<?= e(url('admin/verify')) ?>"><?= csrf_field() ?><button class="btn btn-success">Verify audit integrity</button></form></div>
<?php if ($result): ?>
  <div class="alert alert-<?= $result['ok'] ? 'success' : 'danger' ?>">
    <?php if ($result['ok']): ?>Audit chain verified: <?= e($result['checked']) ?> records, no tampering detected.
    <?php else: ?><strong>TAMPERING DETECTED</strong> in <?= e($result['checked']) ?> records checked:<ul class="mb-0"><?php foreach (array_slice($result['errors'], 0, 20) as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul><?php endif; ?></div>
<?php endif; ?>
<form class="row g-2 mb-3" method="get" action="index.php"><input type="hidden" name="r" value="admin/audit">
  <div class="col-auto"><select class="form-select" name="action"><option value="">All activities</option>
    <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>" <?= $a === $action ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div></form>
<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
<thead><tr><th>#</th><th>Time</th><th>User</th><th>Activity</th><th>Doc</th><th>Outcome</th><th>IP</th><th>Details</th><th>HMAC</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td><?= e($r['id']) ?></td><td><?= e($r['created_at']) ?></td><td><?= e($r['user_id'] ?? '-') ?></td><td><?= e($r['action']) ?></td><td><?= e($r['document_id'] ?? '-') ?></td>
<td><span class="badge bg-<?= $r['outcome'] === 'success' ? 'success' : ($r['outcome'] === 'denied' ? 'danger' : 'warning text-dark') ?>"><?= e($r['outcome']) ?></span></td>
<td><?= e($r['ip_address']) ?></td><td class="small"><?= e($r['details']) ?></td><td class="mono"><?= e(substr($r['record_hmac'], 0, 12)) ?>&hellip;</td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="9" class="text-muted">No records.</td></tr><?php endif; ?>
</tbody></table></div></div>
<nav class="mt-3"><ul class="pagination">
<?php for ($i = 1; $i <= $pages; $i++): if ($i > 1 && $i < $page - 3 && $i < $pages) { continue; } ?>
  <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(url('admin/audit', ['page' => $i, 'action' => $action])) ?>"><?= $i ?></a></li>
<?php endfor; ?></ul></nav>
