<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = $pageTitle ?? 'チェックシート';
$isLoggedIn = !empty($_SESSION['is_logged_in']);
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
  <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
  <script defer src="js/app.js?v=<?= time() ?>"></script>

</head>
<body>

<?php if ($isLoggedIn): ?>
  <header class="app-header">
    <p class="app-title">チェックシート</p>
    <button type="button" class="menu-toggle" aria-label="メニュー" aria-expanded="false">☰</button>
    <nav class="fab-menu" aria-hidden="true">
      <a class="fab-item" href="logs.php">ログ確認</a>
      <a class="fab-item" href="question_builder.php">設問生成</a>
      <a class="fab-item" href="check.php">チェック</a>
    </nav>
  </header>
<?php endif; ?>