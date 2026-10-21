<?php
require_once __DIR__ . '/auth.php';
require_admin();
$pageTitle = $pageTitle ?? 'Yönetim Paneli';
$current = $current ?? '';
$notice = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> · Koken</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= e(app_url('assets/admin.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="admin-shell">
  <aside class="sidebar">
    <a class="side-brand" href="<?= e(app_url('admin/index.php')) ?>"><span class="brand-mark">K</span><span>KOKEN<span class="brand-sub">YÖNETİM PANELİ</span></span></a>
    <div class="nav-label">MENÜ</div>
    <nav class="side-nav">
      <a class="<?= $current === 'dashboard' ? 'active' : '' ?>" href="<?= e(app_url('admin/index.php')) ?>"><span>▦</span> Dashboard</a>
      <a class="<?= $current === 'products' ? 'active' : '' ?>" href="<?= e(app_url('admin/products/index.php')) ?>"><span>◇</span> Ürünler</a>
      <a class="<?= $current === 'orders' ? 'active' : '' ?>" href="<?= e(app_url('admin/orders/index.php')) ?>"><span>▣</span> Siparişler</a>
      <a class="<?= $current === 'categories' ? 'active' : '' ?>" href="<?= e(app_url('admin/categories/index.php')) ?>"><span>▤</span> Kategoriler</a>
      <a class="<?= $current === 'settings' ? 'active' : '' ?>" href="<?= e(app_url('admin/settings.php')) ?>"><span>⚙</span> Mağaza ayarları</a>
      <a class="<?= $current === 'backup' ? 'active' : '' ?>" href="<?= e(app_url('admin/backup.php')) ?>"><span>⇩</span> Yedekleme</a>
    </nav>
    <div class="sidebar-bottom">
      <div class="admin-user"><span class="avatar"><?= e(strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1))) ?></span><span><?= e($_SESSION['admin_name'] ?? 'Yönetici') ?><small>Yönetici</small></span></div>
      <form method="post" action="<?= e(app_url('admin/logout.php')) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="logout-link" type="submit">↪ Çıkış yap</button></form>
    </div>
  </aside>
  <main class="main-panel">
    <header class="topbar"><button class="menu-toggle" aria-label="Menüyü aç" onclick="document.body.classList.toggle('nav-open')">☰</button><div><span class="breadcrumb-label">Yönetim</span><span class="breadcrumb-sep">/</span><strong><?= e($pageTitle) ?></strong></div><span class="top-date"><?= date('d.m.Y') ?></span></header>
    <section class="page-content">
      <?php if ($notice): ?><div class="alert alert-<?= e($notice['type']) ?> alert-dismissible fade show" role="alert"><?= e($notice['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
