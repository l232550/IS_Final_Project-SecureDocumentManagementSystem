<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Secure Document Management System</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="<?= e(url('dashboard')) ?>">&#128274; SecureDocs</a>
    <?php if ($currentUser): ?>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="Menu"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= e(url('dashboard')) ?>">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(url('documents')) ?>">Documents</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(url('doc/upload')) ?>">Upload</a></li>
        <?php if ($currentUser['role'] === 'admin'): ?>
        <li class="nav-item"><a class="nav-link" href="<?= e(url('admin/users')) ?>">Users</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(url('admin/audit')) ?>">Audit Logs</a></li>
        <?php endif; ?>
      </ul>
      <span class="navbar-text me-3"><?= e($currentUser['username']) ?> <span class="badge bg-secondary"><?= e($currentUser['role']) ?></span></span>
      <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button class="btn btn-outline-light btn-sm">Sign out</button></form>
    </div>
    <?php endif; ?>
  </div>
</nav>
<main class="container py-4">
<?php foreach ($flashes as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert"><?= e($f['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endforeach; ?>
