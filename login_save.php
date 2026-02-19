<?php
session_start();
require_once __DIR__ . '/db.php';

$errors = [];
$successMessage = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginId = trim((string)($_POST['login_id'] ?? ''));
    $loginPassword = (string)($_POST['login_password'] ?? '');

    if ($loginId === '') {
        $errors[] = 'ログインIDを入力してください。';
    } elseif (mb_strlen($loginId) < 4) {
        $errors[] = 'ログインIDは4文字以上で入力してください。';
    }

    if ($loginPassword === '') {
        $errors[] = 'ログインパスワードを入力してください。';
    } elseif (mb_strlen($loginPassword) < 8) {
        $errors[] = 'ログインパスワードは8文字以上で入力してください。';
    }

    if ($errors === []) {
        try {
            $pdo = dbConnect();
            $stmt = $pdo->prepare(
                'INSERT INTO users (user_name, login_name, password_hash)
                 VALUES (:user_name, :login_name, :password_hash)
                 ON DUPLICATE KEY UPDATE
                   user_name = VALUES(user_name),
                   password_hash = VALUES(password_hash),
                   updated_at = CURRENT_TIMESTAMP'
            );
            $stmt->execute([
                ':user_name' => $loginId,
                ':login_name' => $loginId,
                ':password_hash' => password_hash($loginPassword, PASSWORD_DEFAULT),
            ]);

            $_SESSION['is_logged_in'] = false;
            $_SESSION['success_message'] = 'ログイン情報をDBへ保存しました。ログインしてください。';

            header('Location: login.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'DB保存に失敗しました。接続設定を確認してください。';
        }
    }
}

$pageTitle = 'ログイン情報登録';
require_once __DIR__ . '/header.php';
?>
<main class="container">
  <section class="card">
    <h1>ログイン情報登録</h1>

    <?php if ($successMessage): ?>
      <div class="notice notice-success"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
      <div class="notice notice-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endforeach; ?>

    <form method="post" action="login_save.php" novalidate>
      <div class="form-row">
        <label for="login_id">ログインID</label>
        <input id="login_id" type="text" name="login_id" minlength="4" required>
      </div>

      <div class="form-row">
        <label for="login_password">ログインパスワード</label>
        <input id="login_password" type="password" name="login_password" minlength="8" required>
      </div>

      <button type="submit">保存する</button>
    </form>
    <p class="helper">登録後は <a class="btn-link" href="login.php">ログイン画面</a> からログインしてください。</p>
  </section>
</main>
<?php require_once __DIR__ . '/footer.php'; ?>
