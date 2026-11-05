<?php
declare(strict_types=1);

session_start();

/*
 * Paws&Whiskers account page.
 * This file is ADDITIVE: it does not replace your existing index.html,
 * js/script.js, style.css, or php/database.php.
 */

$dbHost = 'localhost';
$dbName = 'pawsandwhiskers';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Unable to connect to the Paws&Whiskers database. Check the database settings in account.php.');
}

if (empty($_SESSION['pw_csrf'])) {
    $_SESSION['pw_csrf'] = bin2hex(random_bytes(32));
}

$mode = (($_GET['mode'] ?? 'login') === 'signup') ? 'signup' : 'login';
$error = '';
$success = '';
$nameValue = '';
$emailValue = '';

function pw_redirect(string $mode = 'login'): never {
    header('Location: account.php?mode=' . $mode);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedMode = ($_POST['action'] ?? 'login') === 'signup' ? 'signup' : 'login';
    $mode = $postedMode;

    if (!hash_equals($_SESSION['pw_csrf'], (string)($_POST['csrf'] ?? ''))) {
        $error = 'Your session expired. Please try again.';
    } else {
        $emailValue = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');

        if (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Your password must be at least 8 characters.';
        } elseif ($postedMode === 'signup') {
            $nameValue = trim((string)($_POST['name'] ?? ''));
            $confirm = (string)($_POST['confirm_password'] ?? '');

            if ($nameValue === '' || mb_strlen($nameValue) > 100) {
                $error = 'Please enter your name (up to 100 characters).';
            } elseif ($password !== $confirm) {
                $error = 'Your passwords do not match.';
            } else {
                $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $check->execute(['email' => $emailValue]);

                if ($check->fetch()) {
                    $error = 'An account with that email already exists. Please log in.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $insert = $pdo->prepare(
                        'INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :password_hash)'
                    );
                    $insert->execute([
                        'name' => $nameValue,
                        'email' => $emailValue,
                        'password_hash' => $hash,
                    ]);

                    session_regenerate_id(true);
                    $_SESSION['pw_user_id'] = (int)$pdo->lastInsertId();
                    $_SESSION['pw_user_name'] = $nameValue;
                    $_SESSION['pw_user_email'] = $emailValue;
                    $_SESSION['pw_csrf'] = bin2hex(random_bytes(32));
                    pw_redirect('account');
                }
            }
        } else {
            $stmt = $pdo->prepare(
                'SELECT id, name, email, password_hash FROM users WHERE email = :email LIMIT 1'
            );
            $stmt->execute(['email' => $emailValue]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'The email or password is incorrect.';
            } else {
                session_regenerate_id(true);
                $_SESSION['pw_user_id'] = (int)$user['id'];
                $_SESSION['pw_user_name'] = $user['name'];
                $_SESSION['pw_user_email'] = $user['email'];
                $_SESSION['pw_csrf'] = bin2hex(random_bytes(32));
                pw_redirect('account');
            }
        }
    }
}

if (isset($_GET['logout'])) {
    if (!hash_equals($_SESSION['pw_csrf'], (string)($_GET['token'] ?? ''))) {
        http_response_code(403);
        exit('Invalid logout request.');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: account.php?mode=login&loggedout=1');
    exit;
}

$isLoggedIn = !empty($_SESSION['pw_user_id']);
if (isset($_GET['loggedout'])) {
    $success = 'You have been logged out successfully.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $isLoggedIn ? 'My Account' : ($mode === 'signup' ? 'Create Account' : 'Log In') ?> — Paws&Whiskers</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="auth.css">
</head>
<body>
<nav class="glass auth-nav">
  <a href="index.html#home" class="logo">Paws&Whiskers</a>
  <div class="links auth-links">
    <a href="index.html#home">Home</a>
    <a href="index.html#shop">Shop</a>
    <a href="index.html#shop-dogs">Dogs</a>
    <a href="index.html#shop-cats">Cats</a>
    <a href="index.html#about">About Us</a>
    <a href="index.html#contact">Contact</a>
  </div>
  <a class="ib auth-nav-icon" href="account.php" aria-label="My account"><i class="ri-user-line"></i></a>
</nav>

<main class="auth-shell">
  <section class="auth-card glass">
    <div class="auth-welcome">
      <div class="auth-paw"><i class="ri-footprint-line"></i></div>
      <p class="auth-kicker">A LITTLE PLACE FOR YOUR BEST FRIEND</p>
      <h1>Good things<br>are better <em>together.</em></h1>
      <p class="auth-welcome-copy">Thoughtful essentials for the ones who make a house feel like home.</p>
      <div class="auth-notes">
        <span><i class="ri-heart-3-line"></i> Curated with care</span>
        <span><i class="ri-gift-line"></i> Lovely finds for pets</span>
      </div>
      <a class="auth-back-link" href="index.html#shop">Explore the collection <i class="ri-arrow-right-line"></i></a>
    </div>

    <div class="auth-panel">
      <?php if ($isLoggedIn): ?>
        <div class="auth-tabs"><span class="auth-tab active">MY ACCOUNT</span></div>
        <div class="auth-form-content">
          <p class="auth-kicker">WELCOME TO THE FAMILY</p>
          <h2>Hello, <?= htmlspecialchars((string)$_SESSION['pw_user_name'], ENT_QUOTES, 'UTF-8') ?>.</h2>
          <p class="auth-description">You’re signed in to your Paws&Whiskers account.</p>
          <div class="account-details">
            <span class="account-avatar"><i class="ri-user-smile-line"></i></span>
            <div><strong><?= htmlspecialchars((string)$_SESSION['pw_user_name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string)$_SESSION['pw_user_email'], ENT_QUOTES, 'UTF-8') ?></small></div>
          </div>
          <a class="auth-submit" href="account.php?logout=1&amp;token=<?= htmlspecialchars($_SESSION['pw_csrf'], ENT_QUOTES, 'UTF-8') ?>">Log out <i class="ri-arrow-right-line"></i></a>
          <a class="auth-back-link centered" href="index.html#home">Back to home</a>
        </div>
      <?php else: ?>
        <div class="auth-tabs">
          <a class="auth-tab <?= $mode === 'login' ? 'active' : '' ?>" href="account.php?mode=login">LOG IN</a>
          <a class="auth-tab <?= $mode === 'signup' ? 'active' : '' ?>" href="account.php?mode=signup">SIGN UP</a>
        </div>
        <div class="auth-form-content">
          <p class="auth-kicker"><?= $mode === 'signup' ? 'BECOME PART OF THE FAMILY' : 'WELCOME BACK' ?></p>
          <h2><?= $mode === 'signup' ? 'Create your account' : 'Lovely to see you again.' ?></h2>
          <p class="auth-description"><?= $mode === 'signup' ? 'A few little details and you’re all set.' : 'Log in to continue exploring the good stuff.' ?></p>

          <?php if ($error !== ''): ?><div class="auth-alert error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
          <?php if ($success !== ''): ?><div class="auth-alert success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

          <form method="post" action="account.php?mode=<?= $mode ?>" class="auth-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['pw_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="<?= $mode ?>">
            <?php if ($mode === 'signup'): ?>
              <label for="auth-name">Full name</label>
              <div class="auth-input"><i class="ri-user-line"></i><input id="auth-name" name="name" type="text" placeholder="Your name" maxlength="100" autocomplete="name" value="<?= htmlspecialchars($nameValue, ENT_QUOTES, 'UTF-8') ?>" required></div>
            <?php endif; ?>
            <label for="auth-email">Email address</label>
            <div class="auth-input"><i class="ri-mail-line"></i><input id="auth-email" name="email" type="email" placeholder="you@example.com" autocomplete="email" value="<?= htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8') ?>" required></div>
            <label for="auth-password">Password</label>
            <div class="auth-input"><i class="ri-lock-2-line"></i><input id="auth-password" name="password" type="password" placeholder="At least 8 characters" minlength="8" autocomplete="<?= $mode === 'signup' ? 'new-password' : 'current-password' ?>" required><button type="button" class="auth-eye" onclick="toggleAuthPassword('auth-password', this)" aria-label="Show password"><i class="ri-eye-line"></i></button></div>
            <?php if ($mode === 'signup'): ?>
              <label for="auth-confirm">Confirm password</label>
              <div class="auth-input"><i class="ri-lock-password-line"></i><input id="auth-confirm" name="confirm_password" type="password" placeholder="Enter your password again" minlength="8" autocomplete="new-password" required></div>
            <?php endif; ?>
            <button class="auth-submit" type="submit"><?= $mode === 'signup' ? 'Create account' : 'Log in' ?> <i class="ri-arrow-right-line"></i></button>
          </form>
          <p class="auth-switch-copy"><?= $mode === 'signup' ? 'Already have an account?' : 'New to Paws&Whiskers?' ?> <a href="account.php?mode=<?= $mode === 'signup' ? 'login' : 'signup' ?>"><?= $mode === 'signup' ? 'Log in' : 'Create an account' ?></a></p>
          <p class="auth-terms">By continuing, you agree to keep tails wagging and whiskers happy.</p>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>
<footer class="auth-footer"><div class="wrap"><p>© 2026 Paws&Whiskers. Made with care for dogs and cats.</p></div></footer>
<script>
function toggleAuthPassword(id, button) {
  const field = document.getElementById(id);
  const visible = field.type === 'password';
  field.type = visible ? 'text' : 'password';
  button.innerHTML = visible ? '<i class="ri-eye-off-line"></i>' : '<i class="ri-eye-line"></i>';
  button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
}
</script>
</body>
</html>
