<?php
session_start();
require_once __DIR__ . '/auth.php';
requireLogin();

$pageTitle = 'チェックシート | ホーム';
require_once __DIR__ . '/header.php';
?>
<main class="container">
  <section class="card">
    <h1>チェックシートWebアプリ</h1>
    <p>ログイン中: <?= htmlspecialchars((string)($_SESSION['auth_user_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
    <p>右上メニューから「ログ確認」「設問生成」「チェック」へ進めます。</p>
  </section>
</main>
<?php require_once __DIR__ . '/footer.php'; ?>
