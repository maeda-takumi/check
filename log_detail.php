<?php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
requireLogin();

$logId = (int)($_GET['id'] ?? 0);
if ($logId <= 0) {
    header('Location: logs.php');
    exit;
}

$header = null;
$items = [];
$error = null;

try {
    $pdo = dbConnect();
    $stmt = $pdo->prepare(
        'SELECT qgl.q_g_l_id, qgl.target_date, qg.group_name, u.user_name
         FROM question_group_logs qgl
         INNER JOIN question_groups qg ON qg.group_id = qgl.group_id
         INNER JOIN users u ON u.user_id = qgl.user_id
         WHERE qgl.q_g_l_id = :id'
    );
    $stmt->execute([':id' => $logId]);
    $header = $stmt->fetch();

    if (!$header) {
        throw new RuntimeException('not found');
    }

    $detailStmt = $pdo->prepare(
        'SELECT q.question, q.check_type, ql.value_bool, ql.value_text, ql.question_snapshot
         FROM question_logs ql
         INNER JOIN questions q ON q.q_id = ql.q_id
         WHERE ql.q_g_l_id = :id
         ORDER BY q.sort_order, q.q_id'
    );
    $detailStmt->execute([':id' => $logId]);
    $items = $detailStmt->fetchAll();
} catch (Throwable $e) {
    $error = 'ログ詳細の取得に失敗しました。';
}

$pageTitle = 'ログ詳細';
require_once __DIR__ . '/header.php';
?>
<main class="container">
  <section class="card">
    <h1>ログ詳細</h1>
    <p><a class="btn-link" href="logs.php">一覧へ戻る</a></p>

    <?php if ($error): ?>
      <div class="notice notice-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php elseif ($header): ?>
      <p>グループ: <?= htmlspecialchars((string)$header['group_name'], ENT_QUOTES, 'UTF-8'); ?></p>
      <p>チェック者: <?= htmlspecialchars((string)$header['user_name'], ENT_QUOTES, 'UTF-8'); ?></p>
      <p>日時: <?= htmlspecialchars((string)$header['target_date'], ENT_QUOTES, 'UTF-8'); ?></p>

      <ul class="list">
        <?php foreach ($items as $item): ?>
          <?php
            $questionText = (string)($item['question_snapshot'] ?: $item['question']);
            $answer = '未チェック';
            if ((int)$item['check_type'] === 1) {
                if ($item['value_bool'] !== null) {
                    $answer = ((int)$item['value_bool'] === 1) ? 'チェック済み' : '未チェック';
                }
            } else {
                $answer = trim((string)$item['value_text']) === '' ? '未チェック' : (string)$item['value_text'];
            }
          ?>
          <li>
            <strong><?= htmlspecialchars($questionText, ENT_QUOTES, 'UTF-8'); ?></strong><br>
            <span><?= htmlspecialchars($answer, ENT_QUOTES, 'UTF-8'); ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</main>
<?php require_once __DIR__ . '/footer.php'; ?>
