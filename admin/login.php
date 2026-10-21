<?php
require_once __DIR__ . '/auth.php';
if (!empty($_SESSION['admin_id'])) { header('Location: ' . app_url('admin/index.php')); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => trim((string)($_POST['username'] ?? ''))]);
    $admin = $stmt->fetch();
    if ($admin && password_verify((string)($_POST['password'] ?? ''), $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['admin_name'] = $admin['username'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: ' . app_url('admin/index.php')); exit;
    }
    $error = 'Kullanıcı adı veya şifre hatalı.';
}
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Yönetici Girişi</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="<?= e(app_url('assets/admin.css')) ?>" rel="stylesheet"></head><body class="login-page"><main class="login-card"><div class="brand-mark">K</div><p class="eyebrow">KOKEN YÖNETİM</p><h1>Tekrar hoş geldiniz</h1><p class="text-secondary mb-4">Devam etmek için hesabınıza giriş yapın.</p>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="mb-3"><label class="form-label" for="username">Kullanıcı adı</label><input class="form-control" id="username" name="username" autocomplete="username" required></div><div class="mb-4"><label class="form-label" for="password">Şifre</label><input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required></div><button class="btn btn-primary w-100" type="submit">Giriş yap</button></form></main></body></html>
