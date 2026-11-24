<div class="card auth-card"><div class="card-body p-4">
  <h4 class="mb-3">Reset password</h4>
  <?php if (!$valid && !$error): ?>
    <div class="alert alert-danger">This reset link is invalid or has expired.</div>
    <a href="<?= e(url('forgot')) ?>">Request a new link</a>
  <?php else: ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($valid): ?>
    <form method="post" action="<?= e(url('reset')) ?>">
      <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="mb-3"><label class="form-label" for="password">New password</label>
        <input class="form-control" type="password" id="password" name="password" required minlength="10" maxlength="200"></div>
      <div class="mb-3"><label class="form-label" for="confirm">Confirm new password</label>
        <input class="form-control" type="password" id="confirm" name="confirm" required minlength="10" maxlength="200"></div>
      <button class="btn btn-primary w-100">Change password</button>
    </form>
    <?php else: ?><a href="<?= e(url('forgot')) ?>">Request a new link</a><?php endif; ?>
  <?php endif; ?>
</div></div>
