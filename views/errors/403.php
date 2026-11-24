<div class="card auth-card"><div class="card-body p-4 text-center">
  <h2 class="text-danger">Access denied</h2><p><?= e($message ?? 'You do not have permission to do that.') ?></p>
  <a class="btn btn-primary" href="<?= e(url('dashboard')) ?>">Back to dashboard</a></div></div>
