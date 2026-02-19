<?php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
requireLogin();

$draft = $_SESSION['check_draft'] ?? null;
if (!$draft) {
    header('Location: check.php');
    exit;
}

$errors = [];

try {
    $pdo = dbConnect();
    $groupStmt = $pdo->prepare('SELECT group_name FROM question_groups WHERE group_id = :id');
    $groupStmt->execute([':id' => (int)$draft['group_id']]);
    $group = $groupStmt->fetch();

    $qIds = array_map('intval', array_keys($draft['answers']));
    $in = implode(',', array_fill(0, count($qIds), '?'));
    $qStmt = $pdo->prepare("SELECT q_id, question, check_type, required FROM questions WHERE q_id IN ($in)");
    $qStmt->execute($qIds);
    $questionRows = $qStmt->fetchAll();

    $questionMap = [];
    foreach ($questionRows as $row) {
        $questionMap[(int)$row['q_id']] = $row;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'save') {
        $pdo->beginTransaction();

        $insertHeader = $pdo->prepare('INSERT INTO question_group_logs (group_id, user_id, target_date, status) VALUES (:group_id, :user_id, :target_date, 1)');
        $insertHeader->execute([
            ':group_id' => (int)$draft['group_id'],
            ':user_id' => (int)($_SESSION['auth_user_id'] ?? 0),
            ':target_date' => date('Y-m-d'),
        ]);
        $runId = (int)$pdo->lastInsertId();

        $insertDetail = $pdo->prepare('INSERT INTO question_logs (q_g_l_id, q_id, value_bool, value_text, question_snapshot) VALUES (:run_id, :q_id, :value_bool, :value_text, :snapshot)');

        foreach ($draft['answers'] as $qId => $answer) {
            $qId = (int)$qId;
            $q = $questionMap[$qId] ?? null;
            if (!$q) {
                continue;
            }
            $insertDetail->execute([
                ':run_id' => $runId,
                ':q_id' => $qId,
                ':value_bool' => $answer['value_bool'],
                ':value_text' => $answer['value_text'],
                ':snapshot' => (string)$q['question'],
            ]);
        }

        $pdo->commit();
        unset($_SESSION['check_draft']);
        $_SESSION['success_message'] = 'チェック内容を保存しました。';
        header('Location: logs.php');
        exit;
    }
} catch (Throwable $e) {
    $errors[] = '確認画面の表示に失敗しました。';
    $group = ['group_name' => ''];
    $questionMap = [];
}

$pageTitle = 'チェック確認';
require_once __DIR__ . '/header.php';
?>
<main class="container">
  <section class="card">
    <h1>チェック確認</h1>
    <?php foreach ($errors as $error): ?>
      <div class="notice notice-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endforeach; ?>

    <p>グループ: <?= htmlspecialchars((string)($group['group_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
    <ul class="list">
      <?php foreach ($draft['answers'] as $qId => $answer): ?>
        <?php
          $row = $questionMap[(int)$qId] ?? null;
          if (!$row) {
              continue;
          }
          $value = ((int)$row['check_type'] === 1)
              ? ((int)$answer['value_bool'] === 1 ? 'チェック済み' : '未チェック')
              : (trim((string)$answer['value_text']) === '' ? '未チェック' : (string)$answer['value_text']);
        ?>
        <li>
          <strong><?= htmlspecialchars((string)$row['question'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
          <span><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?></span>
        </li>
      <?php endforeach; ?>
    </ul>

    <form method="post" class="inline-form">
      <input type="hidden" name="action" value="save">
      <button type="submit">保存する</button>
    </form>
    <a class="btn-link" href="check.php?group_id=<?= (int)$draft['group_id']; ?>">入力へ戻る</a>
  </section>
</main>
<?php require_once __DIR__ . '/footer.php'; ?>
