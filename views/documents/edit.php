<div class="card mx-auto" style="max-width:640px"><div class="card-body p-4">
  <h4 class="mb-1">Update document</h4>
  <p class="text-muted">Replacing <strong><?= e($doc['original_filename']) ?></strong> (<?= e(strtoupper($doc['file_type'])) ?>). The new file must be the same type.</p>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('doc/edit', ['id' => $doc['id']])) ?>" enctype="multipart/form-data" data-confirm="Replace the current content of this document?">
    <?= csrf_field() ?>
    <div class="mb-3"><input class="form-control" type="file" name="document" accept=".<?= e($doc['file_type']) ?>" required></div>
    <button class="btn btn-primary">Encrypt and replace</button>
    <a class="btn btn-link" href="<?= e(url('doc/show', ['id' => $doc['id']])) ?>">Cancel</a>
  </form>
</div></div>
