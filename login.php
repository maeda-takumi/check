<?php
session_start();
require_once __DIR__ . '/db.php';

$errors = [];
$successMessage = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);

if (isset($_GET['logout'])) {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
    session_start();
    $_SESSION['success_message'] = 'ログアウトしました。';

    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginId = trim((string)($_POST['login_id'] ?? ''));
    $loginPassword = (string)($_POST['login_password'] ?? '');

    if ($loginId === '') {
        $errors[] = 'ログインIDを入力してください。';
    }

    if ($loginPassword === '') {
        $errors[] = 'ログインパスワードを入力してください。';
    }

    if ($errors === []) {
        try {
            $pdo = dbConnect();
            $stmt = $pdo->prepare('SELECT user_id, user_name, login_name, password_hash FROM users WHERE login_name = :login_name LIMIT 1');
            $stmt->execute([':login_name' => $loginId]);
            $user = $stmt->fetch();

            if ($user && password_verify($loginPassword, (string)$user['password_hash'])) {
                $_SESSION['is_logged_in'] = true;
                $_SESSION['auth_user_id'] = (int)$user['user_id'];
                $_SESSION['auth_id'] = (string)$user['login_name'];
                $_SESSION['auth_user_name'] = (string)$user['user_name'];

                header('Location: index.php');
                exit;
            }
            $errors[] = 'ログインIDまたはパスワードが正しくありません。';
        } catch (PDOException $e) {
            $errors[] = 'ログインに失敗しました。DB接続を確認してください。';
        }
    }
}

$pageTitle = 'ログイン';
require_once __DIR__ . '/header.php';
?>
<main class="container">
  <section class="card">
    <h1>ログイン</h1>

    <?php if ($successMessage): ?>
      <div class="notice notice-success"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
      <div class="notice notice-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endforeach; ?>

    <form method="post" action="login.php" novalidate>
      <div class="form-row">
        <label for="login_id">ログインID</label>
        <input id="login_id" type="text" name="login_id" minlength="4" required>
      </div>

      <div class="form-row">
        <label for="login_password">ログインパスワード</label>
        <input id="login_password" type="password" name="login_password" autocomplete="current-password" required>
      </div>

      <button type="submit">ログインする</button>
    </form>
    <p class="helper">ログイン情報が未登録の場合は、<a class="btn-link" href="login_save.php">こちら</a>から登録してください。</p>
  </section>
</main>
<?php require_once __DIR__ . '/footer.php'; ?>
