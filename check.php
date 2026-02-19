<?php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
requireLogin();

$errors = [];

try {
    $pdo = dbConnect();
    $groups = $pdo->query('SELECT group_id, group_name FROM question_groups WHERE is_active = 1 ORDER BY sort_order, group_id')->fetchAll();

    $selectedGroupId = (int)($_POST['group_id'] ?? $_GET['group_id'] ?? ($groups[0]['group_id'] ?? 0));

    $questionStmt = $pdo->prepare('SELECT q_id, question, check_type, required FROM questions WHERE group_id = :group_id AND is_active = 1 ORDER BY sort_order, q_id');
    $questionStmt->execute([':group_id' => $selectedGroupId]);
    $questions = $questionStmt->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'confirm') {
        $answers = [];

        foreach ($questions as $question) {
            $qid = (int)$question['q_id'];
            if ((int)$question['check_type'] === 1) {
                $value = isset($_POST['answers'][$qid]) ? 1 : 0;
                if ((int)$question['required'] === 1 && $value !== 1) {
                    $errors[] = '必須項目が未チェックです: ' . (string)$question['question'];
                }
                $answers[$qid] = ['type' => 1, 'value_bool' => $value, 'value_text' => null];
            } else {
                $text = trim((string)($_POST['answers'][$qid] ?? ''));
                if ((int)$question['required'] === 1 && $text === '') {
                    $errors[] = '必須項目が未入力です: ' . (string)$question['question'];
                }
                $answers[$qid] = ['type' => 2, 'value_bool' => null, 'value_text' => $text];
            }
        }

        if ($errors === []) {
            $_SESSION['check_draft'] = [
                'group_id' => $selectedGroupId,
                'target_date' => date('Y-m-d H:i:s'),
                'answers' => $answers,
            ];
            header('Location: check_confirm.php');
            exit;
        }
    }
} catch (Throwable $e) {
    $groups = [];
    $questions = [];
    $selectedGroupId = 0;
    $errors[] = 'チェック画面の表示に失敗しました。';
}

$pageTitle = 'チェック画面';
require_once __DIR__ . '/header.php';
?>
<main class="container">
  <section class="card">
    <h1>チェック画面</h1>

    <?php foreach ($errors as $error): ?>
      <div class="notice notice-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endforeach; ?>

    <form method="get">
      <div class="form-row">
        <label for="group_id">グループ</label>
        <select id="group_id" name="group_id" onchange="this.form.submit()">
          <?php foreach ($groups as $group): ?>
            <option value="<?= (int)$group['group_id']; ?>" <?= (int)$group['group_id'] === $selectedGroupId ? 'selected' : ''; ?>>
              <?= htmlspecialchars((string)$group['group_name'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>

    <form method="post">
      <input type="hidden" name="action" value="confirm">
      <input type="hidden" name="group_id" value="<?= $selectedGroupId; ?>">
      <?php foreach ($questions as $question): ?>
        <div class="form-row">
          <label>
            <?= htmlspecialchars((string)$question['question'], ENT_QUOTES, 'UTF-8'); ?>
            <?= (int)$question['required'] === 1 ? ' *' : ''; ?>
          </label>
          <?php if ((int)$question['check_type'] === 1): ?>
            <input type="checkbox" name="answers[<?= (int)$question['q_id']; ?>]" value="1">
          <?php else: ?>
            <input type="text" name="answers[<?= (int)$question['q_id']; ?>]">
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <button type="submit">確認画面へ</button>
    </form>
  </section>
</main>
<?php require_once __DIR__ . '/footer.php'; ?>
