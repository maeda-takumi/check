<?php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
requireLogin();

$rows = [];
$error = null;
$successMessage = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);

try {
    $pdo = dbConnect();
    $stmt = $pdo->query(
        'SELECT qgl.q_g_l_id, qgl.target_date, qgl.log_date, qgl.status, qg.group_name, u.user_name
         FROM question_group_logs qgl
         INNER JOIN question_groups qg ON qg.group_id = qgl.group_id
         INNER JOIN users u ON u.user_id = qgl.user_id
         ORDER BY qgl.target_date DESC, qgl.log_date DESC'
    );
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'ログの取得に失敗しました。';
}

$pageTitle = 'ログ確認';
require_once __DIR__ . '/header.php';
?>
<main class="container">
  <section class="card">
    <h1>ログ確認</h1>
    <?php if ($successMessage): ?>
      <div class="notice notice-success"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="notice notice-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($rows === []): ?>
      <p>まだログはありません。</p>
    <?php else: ?>
      <ul class="list">
        <?php foreach ($rows as $row): ?>
          <li>
            <a class="list-link" href="log_detail.php?id=<?= (int)$row['q_g_l_id']; ?>">
              <strong><?= htmlspecialchars((string)$row['group_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
              <span>チェック者: <?= htmlspecialchars((string)$row['user_name'], ENT_QUOTES, 'UTF-8'); ?></span><br>
              <span>日時: <?= htmlspecialchars((string)$row['target_date'], ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</main>
<?php require_once __DIR__ . '/footer.php'; ?>
