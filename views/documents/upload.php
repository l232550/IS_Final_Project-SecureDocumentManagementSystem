<div class="card mx-auto" style="max-width:640px"><div class="card-body p-4">
  <h4 class="mb-3">Upload document</h4>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('doc/upload')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label" for="document">File (PDF, DOCX or TXT, max 10 MB)</label>
      <input class="form-control" type="file" id="document" name="document" accept=".pdf,.docx,.txt" required></div>
    <p class="text-muted small">The file is validated, then encrypted with AES-256-GCM before it is stored. Only ciphertext is written to disk.</p>
    <button class="btn btn-primary">Encrypt and upload</button>
    <a class="btn btn-link" href="<?= e(url('documents')) ?>">Cancel</a>
  </form>
</div></div>
