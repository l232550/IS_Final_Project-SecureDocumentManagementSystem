<div class="card auth-card"><div class="card-body p-4">
  <h4 class="mb-3">Create account</h4>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('register')) ?>">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label" for="username">Username</label>
      <input class="form-control" id="username" name="username" required pattern="[A-Za-z0-9_]{3,30}" maxlength="30" value="<?= e($_POST['username'] ?? '') ?>">
      <div class="form-text">3-30 letters, digits or underscores.</div></div>
    <div class="mb-3"><label class="form-label" for="email">Email</label>
      <input class="form-control" type="email" id="email" name="email" required maxlength="190" value="<?= e($_POST['email'] ?? '') ?>"></div>
    <div class="mb-3"><label class="form-label" for="password">Password</label>
      <input class="form-control" type="password" id="password" name="password" required minlength="10" maxlength="200">
      <div class="form-text">At least 10 characters with upper-case, lower-case and a digit.</div></div>
    <div class="mb-3"><label class="form-label" for="confirm">Confirm password</label>
      <input class="form-control" type="password" id="confirm" name="confirm" required minlength="10" maxlength="200"></div>
    <button class="btn btn-primary w-100">Register</button>
  </form>
  <p class="mt-3 small mb-0">Already registered? <a href="<?= e(url('login')) ?>">Sign in</a></p>
</div></div>
