<?php
session_start();

if (empty($_SESSION['is_logged_in'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'チェックシート | ホーム';
require_once __DIR__ . '/header.php';
?>
<main class="container">
  <section class="card">
    <h1>チェックシートWebアプリ</h1>
    <p>ログイン済みです。ここからチェックシート機能を拡張していけます。</p>
    <p><a class="btn-link" href="login.php?logout=1">ログアウト</a></p>
  </section>
</main>
<?php require_once __DIR__ . '/footer.php'; ?>
