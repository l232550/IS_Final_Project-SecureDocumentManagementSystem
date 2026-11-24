<h3 class="mb-3">User management</h3>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
<thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($users as $u): $self = (int)$u['id'] === (int)$currentUser['id']; ?>
<tr><td><?= e($u['id']) ?></td><td><?= e($u['username']) ?></td><td><?= e($u['email']) ?></td>
<td><span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : 'secondary' ?>"><?= e($u['role']) ?></span></td>
<td><span class="badge bg-<?= (int)$u['is_active'] ? 'success' : 'dark' ?>"><?= (int)$u['is_active'] ? 'active' : 'disabled' ?></span></td>
<td><?= e($u['last_login_at'] ?? '-') ?></td>
<td><?php if ($self): ?><span class="text-muted small">(you)</span><?php else: ?>
  <form class="d-inline" method="post" action="<?= e(url('admin/user')) ?>" data-confirm="Change the role of <?= e($u['username']) ?>?">
    <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= e($u['id']) ?>"><input type="hidden" name="change" value="role">
    <input type="hidden" name="role" value="<?= $u['role'] === 'admin' ? 'user' : 'admin' ?>">
    <button class="btn btn-sm btn-outline-secondary"><?= $u['role'] === 'admin' ? 'Make user' : 'Make admin' ?></button></form>
  <form class="d-inline" method="post" action="<?= e(url('admin/user')) ?>" data-confirm="<?= (int)$u['is_active'] ? 'Disable' : 'Enable' ?> this account?">
    <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= e($u['id']) ?>"><input type="hidden" name="change" value="status">
    <input type="hidden" name="active" value="<?= (int)$u['is_active'] ? '0' : '1' ?>">
    <button class="btn btn-sm btn-outline-<?= (int)$u['is_active'] ? 'danger' : 'success' ?>"><?= (int)$u['is_active'] ? 'Disable' : 'Enable' ?></button></form>
<?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div></div>
