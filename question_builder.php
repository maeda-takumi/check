<?php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
requireLogin();

$errors = [];
$messages = [];

$mode = (string)($_GET['mode'] ?? '');
$selectedGroupId = (int)($_GET['group_id'] ?? 0);

try {
    $pdo = dbConnect();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'create_group') {
            $groupName = trim((string)($_POST['group_name'] ?? ''));
            if ($groupName === '') {
                $errors[] = 'グループ名を入力してください。';
            } else {
                $stmt = $pdo->prepare('INSERT INTO question_groups (group_name, sort_order, is_active) VALUES (:name, 0, 1)');
                $stmt->execute([':name' => $groupName]);
                $newGroupId = (int)$pdo->lastInsertId();
                header('Location: question_builder.php?mode=edit&group_id=' . $newGroupId);
                exit;
            }
        }

        if ($action === 'toggle_group') {
            $groupId = (int)($_POST['group_id'] ?? 0);
            $isActive = (int)($_POST['is_active'] ?? 0);
            $stmt = $pdo->prepare('UPDATE question_groups SET is_active = :active WHERE group_id = :id');
            $stmt->execute([':active' => $isActive === 1 ? 0 : 1, ':id' => $groupId]);
            $messages[] = 'グループ状態を更新しました。';
        }


        if ($action === 'create_question' || $action === 'create_questions') {
            $groupId = (int)($_POST['group_id'] ?? 0);
            $submittedQuestions = $_POST['question'] ?? [];
            $submittedCheckTypes = $_POST['check_type'] ?? [];
            $submittedRequired = $_POST['required'] ?? [];

            if (!is_array($submittedQuestions)) {
                $submittedQuestions = [$submittedQuestions];
            }
            if (!is_array($submittedCheckTypes)) {
                $submittedCheckTypes = [$submittedCheckTypes];
            }

            $createRows = [];
            foreach ($submittedQuestions as $index => $questionText) {
                $questionText = trim((string)$questionText);
                if ($questionText === '') {
                    continue;
                }

                $checkType = isset($submittedCheckTypes[$index]) ? (int)$submittedCheckTypes[$index] : 1;
                $required = isset($submittedRequired[$index]) && (int)$submittedRequired[$index] === 1 ? 1 : 0;

                $createRows[] = [
                    'question' => $questionText,
                    'check_type' => in_array($checkType, [1, 2], true) ? $checkType : 1,
                    'required' => $required,
                ];
            }

            if ($groupId <= 0 || empty($createRows)) {
                $errors[] = '設問追加にはグループと設問文が必要です。';
            } else {
                $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM questions WHERE group_id = :group_id');
                $sortStmt->execute([':group_id' => $groupId]);
                $nextSort = (int)$sortStmt->fetch()['next_sort'];

                $insert = $pdo->prepare('INSERT INTO questions (group_id, question, check_type, required, sort_order, is_active) VALUES (:group_id, :question, :check_type, :required, :sort_order, 1)');

                foreach ($createRows as $row) {
                    $insert->execute([
                        ':group_id' => $groupId,
                        ':question' => $row['question'],
                        ':check_type' => $row['check_type'],
                        ':required' => $row['required'],
                        ':sort_order' => $nextSort,
                    ]);
                    $nextSort++;
                }
                
                $selectedGroupId = $groupId;
                $mode = 'edit';
            }
        }

        if ($action === 'update_question') {
            $questionId = (int)($_POST['question_id'] ?? 0);
            $groupId = (int)($_POST['group_id'] ?? 0);
            $question = trim((string)($_POST['question'] ?? ''));
            $checkType = (int)($_POST['check_type'] ?? 1);
            $required = (int)($_POST['required'] ?? 0) === 1 ? 1 : 0;

            if ($questionId <= 0 || $groupId <= 0 || $question === '') {
                $errors[] = '設問更新に必要な情報が不足しています。';
            } else {
                $stmt = $pdo->prepare('UPDATE questions SET question = :question, check_type = :check_type, required = :required WHERE q_id = :id AND group_id = :group_id');
                $stmt->execute([
                    ':question' => $question,
                    ':check_type' => in_array($checkType, [1, 2], true) ? $checkType : 1,
                    ':required' => $required,
                    ':id' => $questionId,
                    ':group_id' => $groupId,
                ]);
                $messages[] = '設問を更新しました。';
                $selectedGroupId = $groupId;
                $mode = 'edit';
            }
        }

        if ($action === 'update_questions_bulk') {
            $groupId = (int)($_POST['group_id'] ?? 0);
            $questionIds = $_POST['question_id'] ?? [];
            $questions = $_POST['question'] ?? [];
            $checkTypes = $_POST['check_type'] ?? [];
            $requiredFlags = $_POST['required'] ?? [];

            if (!is_array($questionIds) || !is_array($questions) || !is_array($checkTypes)) {
                $errors[] = '設問更新データの形式が不正です。';
            } elseif ($groupId <= 0) {
                $errors[] = '設問更新に必要なグループ情報が不足しています。';
            } else {
                $update = $pdo->prepare('UPDATE questions SET question = :question, check_type = :check_type, required = :required WHERE q_id = :id AND group_id = :group_id');

                foreach ($questionIds as $index => $questionIdRaw) {
                    $questionId = (int)$questionIdRaw;
                    $questionText = trim((string)($questions[$index] ?? ''));
                    $checkType = (int)($checkTypes[$index] ?? 1);
                    $required = isset($requiredFlags[$index]) && (int)$requiredFlags[$index] === 1 ? 1 : 0;

                    if ($questionId <= 0 || $questionText === '') {
                        continue;
                    }

                    $update->execute([
                        ':question' => $questionText,
                        ':check_type' => in_array($checkType, [1, 2], true) ? $checkType : 1,
                        ':required' => $required,
                        ':id' => $questionId,
                        ':group_id' => $groupId,
                    ]);
                }

                $messages[] = '設問をまとめて更新しました。';
                $selectedGroupId = $groupId;
                $mode = 'edit';
            }
        }
        if ($action === 'move_question') {
            $questionId = (int)($_POST['question_id'] ?? 0);
            $direction = (string)($_POST['direction'] ?? 'up');

            $currentStmt = $pdo->prepare('SELECT q_id, group_id, sort_order FROM questions WHERE q_id = :id');
            $currentStmt->execute([':id' => $questionId]);
            $current = $currentStmt->fetch();

            if ($current) {
                if ($direction === 'up') {
                    $targetStmt = $pdo->prepare('SELECT q_id, sort_order FROM questions WHERE group_id = :group_id AND sort_order < :sort ORDER BY sort_order DESC LIMIT 1');
                } else {
                    $targetStmt = $pdo->prepare('SELECT q_id, sort_order FROM questions WHERE group_id = :group_id AND sort_order > :sort ORDER BY sort_order ASC LIMIT 1');
                }

                $targetStmt->execute([':group_id' => $current['group_id'], ':sort' => $current['sort_order']]);
                $target = $targetStmt->fetch();

                if ($target) {
                    $pdo->beginTransaction();
                    $update = $pdo->prepare('UPDATE questions SET sort_order = :sort WHERE q_id = :id');
                    $update->execute([':sort' => $target['sort_order'], ':id' => $current['q_id']]);
                    $update->execute([':sort' => $current['sort_order'], ':id' => $target['q_id']]);
                    $pdo->commit();
                    $messages[] = '設問順を更新しました。';
                }
                $selectedGroupId = (int)$current['group_id'];
                $mode = 'edit';
            }
        }

        if ($action === 'toggle_question') {
            $questionId = (int)($_POST['question_id'] ?? 0);
            $groupId = (int)($_POST['group_id'] ?? 0);
            $isActive = (int)($_POST['is_active'] ?? 0);
            $stmt = $pdo->prepare('UPDATE questions SET is_active = :active WHERE q_id = :id');
            $stmt->execute([':active' => $isActive === 1 ? 0 : 1, ':id' => $questionId]);
            $messages[] = '設問状態を更新しました。';
            $selectedGroupId = $groupId;
            $mode = 'edit';
        }
    }

    $groups = $pdo->query('SELECT group_id, group_name, is_active FROM question_groups ORDER BY sort_order, group_id')->fetchAll();

    $questionsByGroup = [];
    $allQuestions = $pdo->query('SELECT q_id, group_id, question, check_type, required, sort_order, is_active FROM questions ORDER BY group_id, sort_order, q_id')->fetchAll();
    foreach ($allQuestions as $q) {
        $questionsByGroup[(int)$q['group_id']][] = $q;
    }
} catch (Throwable $e) {
    $errors[] = '設問管理処理に失敗しました。';
    $groups = [];
    $questionsByGroup = [];
}
$selectedGroup = null;
if ($selectedGroupId > 0) {
    foreach ($groups as $group) {
        if ((int)$group['group_id'] === $selectedGroupId) {
            $selectedGroup = $group;
            break;
        }
    }
}

$pageTitle = '設問生成';
require_once __DIR__ . '/header.php';
?>
<main class="container">
  <section class="card">
    <h1>設問生成</h1>

    <?php foreach ($messages as $message): ?>
      <div class="notice notice-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endforeach; ?>

    <?php foreach ($errors as $error): ?>
      <div class="notice notice-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endforeach; ?>

    <?php if ($mode !== 'edit' && $mode !== 'create'): ?>
      <h2>操作を選択</h2>
      <form method="get">
        <div class="form-row">
          <label for="group_id">グループ</label>
          <select id="group_id" name="group_id" required>
            <option value="">選択してください</option>
            <?php foreach ($groups as $group): ?>
              <option value="<?= (int)$group['group_id']; ?>"><?= htmlspecialchars((string)$group['group_name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <input type="hidden" name="mode" value="edit">
        <button type="submit">編集</button>
      </form>

      <form method="get">
        <input type="hidden" name="mode" value="create">
        <button type="submit">新規作成</button>
      </form>
    <?php elseif ($mode === 'create'): ?>
      <h2>グループ新規作成</h2>
      <form method="post">
        <input type="hidden" name="action" value="create_group">
        <div class="form-row">
          <label for="group_name">グループ名</label>
          <input id="group_name" type="text" name="group_name" required>
        </div>
        <button type="submit">作成して編集へ</button>
      </form>
      <p><a href="question_builder.php">戻る</a></p>
    <?php else: ?>
      <?php if ($selectedGroup === null): ?>
        <div class="notice notice-error">選択したグループが見つかりません。</div>
        <p><a href="question_builder.php">戻る</a></p>
      <?php else: ?>
        <h2>グループ編集: <?= htmlspecialchars((string)$selectedGroup['group_name'], ENT_QUOTES, 'UTF-8'); ?></h2>

        <form method="post" class="inline-form">
          <input type="hidden" name="action" value="toggle_group">
          <input type="hidden" name="group_id" value="<?= (int)$selectedGroup['group_id']; ?>">
          <input type="hidden" name="is_active" value="<?= (int)$selectedGroup['is_active']; ?>">
          <button type="submit"><?= (int)$selectedGroup['is_active'] === 1 ? 'グループ無効化' : 'グループ有効化'; ?></button>
        </form>

        <form method="post" id="question-add-form" class="question-add-form">
          <input type="hidden" name="action" value="create_questions">
          <input type="hidden" name="group_id" value="<?= (int)$selectedGroup['group_id']; ?>">
          <div class="question-add-header">
            <h3>設問追加</h3>
            <button type="button" id="add-question-row">＋ 設問を追加</button>
          </div>
          <div id="question-row-list" class="question-row-list"></div>
          <template id="question-row-template">
            <div class="question-row">
              <input type="text" name="question[]" placeholder="設問" required>
              <select name="check_type[]" aria-label="タイプ">
                <option value="1">チェックボックス</option>
                <option value="2">テキスト</option>
              </select>
              <label class="required-check"><input type="checkbox" name="required[{index}]" value="1"> 必須</label>
            </div>
          </template>
          <div class="form-row">
            <button type="submit">設問を保存</button>
          </div>
        </form>

        <?php if (!empty($questionsByGroup[(int)$selectedGroup['group_id']])): ?>
          <form method="post" class="question-edit-form">
            <input type="hidden" name="group_id" value="<?= (int)$selectedGroup['group_id']; ?>">

            <div class="question-edit-list">
              <?php foreach ($questionsByGroup[(int)$selectedGroup['group_id']] as $index => $question): ?>
                <div class="question-edit-row <?= (int)$question['is_active'] === 0 ? 'is-inactive' : ''; ?>">
                  <input type="hidden" name="question_id[]" value="<?= (int)$question['q_id']; ?>">
                  <div class="question-edit-main">
                    <input type="text" name="question[]" value="<?= htmlspecialchars((string)$question['question'], ENT_QUOTES, 'UTF-8'); ?>" required aria-label="設問文">
                    <select name="check_type[]" aria-label="タイプ">
                      <option value="1" <?= (int)$question['check_type'] === 1 ? 'selected' : ''; ?>>チェックボックス</option>
                      <option value="2" <?= (int)$question['check_type'] === 2 ? 'selected' : ''; ?>>テキスト</option>
                    </select>
                    <label class="required-check"><input type="checkbox" name="required[<?= (int)$index; ?>]" value="1" <?= (int)$question['required'] === 1 ? 'checked' : ''; ?>> 必須</label>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="form-row">
              <button type="submit" name="action" value="update_questions_bulk">変更をまとめて保存</button>
            </div>
          </form>

          <div class="question-order-list">
            <?php foreach ($questionsByGroup[(int)$selectedGroup['group_id']] as $question): ?>
              <div class="question-order-row">
                <span class="question-order-label"><?= htmlspecialchars((string)$question['question'], ENT_QUOTES, 'UTF-8'); ?></span>
                <div class="question-row-actions">
                  <form method="post" class="inline-form">
                    <input type="hidden" name="action" value="move_question">
                    <input type="hidden" name="question_id" value="<?= (int)$question['q_id']; ?>">
                    <input type="hidden" name="direction" value="up">
                    <button type="submit" class="secondary-button">↑</button>
                  </form>
                  <form method="post" class="inline-form">
                    <input type="hidden" name="action" value="move_question">
                    <input type="hidden" name="question_id" value="<?= (int)$question['q_id']; ?>">
                    <input type="hidden" name="direction" value="down">
                    <button type="submit" class="secondary-button">↓</button>
                  </form>
                  <form method="post" class="inline-form">
                    <input type="hidden" name="action" value="toggle_question">
                    <input type="hidden" name="question_id" value="<?= (int)$question['q_id']; ?>">
                    <input type="hidden" name="group_id" value="<?= (int)$selectedGroup['group_id']; ?>">
                    <input type="hidden" name="is_active" value="<?= (int)$question['is_active']; ?>">
                    <button type="submit" class="secondary-button"><?= (int)$question['is_active'] === 1 ? '無効化' : '有効化'; ?></button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <p><a href="question_builder.php">グループ選択へ戻る</a></p>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const list = document.getElementById('question-row-list');
  const button = document.getElementById('add-question-row');
  const template = document.getElementById('question-row-template');

  if (!list || !button || !template) {
    return;
  }

  let rowIndex = 0;

  const appendRow = function () {
    const html = template.innerHTML.replace('{index}', String(rowIndex));
    rowIndex += 1;
    list.insertAdjacentHTML('beforeend', html);
  };

  button.addEventListener('click', appendRow);
  appendRow();
});
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
