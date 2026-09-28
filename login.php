<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE username = :u');
    $stmt->execute(['u' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = [
            'username' => $user['username'],
            'role'     => $user['role'],
            'label'    => $user['label'],
        ];
        header('Location: index.php');
        exit;
    }
    $error = 'Incorrect username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Sign in — FleetDeck</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-screen">
  <div class="login-box">
    <div class="brand-mark"><span class="dot"></span>FleetDeck</div>
    <p class="sub">Sign in to continue</p>
    <?php if ($error): ?><div class="login-error show"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" action="login.php">
      <div class="field">
        <label>Username</label>
        <input name="username" type="text" placeholder="admin" autofocus>
      </div>
      <div class="field" style="margin-bottom:6px;">
        <label>Password</label>
        <input name="password" type="password" placeholder="••••••••">
      </div>
      <button class="btn primary" type="submit" style="width:100%;justify-content:center;margin-top:10px;">Sign in</button>
    </form>
    <div class="login-hint">
      First time here? Run <code>setup.php</code> first to create the database and the default <code>admin / admin123</code> login.
    </div>
  </div>
</div>
</body>
</html>
