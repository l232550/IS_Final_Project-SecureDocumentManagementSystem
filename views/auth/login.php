<div class="card auth-card"><div class="card-body p-4">
  <h4 class="mb-3">Sign in</h4>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('login')) ?>" autocomplete="on">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label" for="identifier">Email or username</label>
      <input class="form-control" id="identifier" name="identifier" required maxlength="190" autofocus></div>
    <div class="mb-3"><label class="form-label" for="password">Password</label>
      <input class="form-control" type="password" id="password" name="password" required maxlength="200"></div>
    <button class="btn btn-primary w-100">Sign in</button>
  </form>
  <div class="d-flex justify-content-between mt-3 small">
    <a href="<?= e(url('register')) ?>">Create account</a><a href="<?= e(url('forgot')) ?>">Forgot password?</a></div>
</div></div>
