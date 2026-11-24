<div class="card auth-card"><div class="card-body p-4">
  <h4 class="mb-3">Forgot password</h4>
  <?php if ($done): ?>
    <div class="alert alert-success">If an account with that email exists, a single-use reset link (valid for 30 minutes) has been issued.</div>
    <a href="<?= e(url('login')) ?>">Back to sign in</a>
  <?php else: ?>
  <form method="post" action="<?= e(url('forgot')) ?>">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label" for="email">Email</label>
      <input class="form-control" type="email" id="email" name="email" required maxlength="190" autofocus></div>
    <button class="btn btn-primary w-100">Request reset link</button>
  </form>
  <?php endif; ?>
</div></div>
