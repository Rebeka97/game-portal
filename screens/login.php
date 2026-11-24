<?php

declare(strict_types=1);

session_start();
require_once dirname(__DIR__) . '/config/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$loginInput = '';
$rememberMe = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $errors[] = 'Invalid form submission (CSRF). Refresh the page and try again.';
    }

    $loginInput = trim((string) ($_POST['login'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $rememberMe = isset($_POST['remember_me']);

    if ($loginInput === '' || $password === '') {
        $errors[] = 'Enter your username (or email address) and password.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'SELECT id, username, password_hash FROM users WHERE username = :username OR email = :email LIMIT 1'
        );
        $stmt->execute([
            ':username' => $loginInput,
            ':email' => $loginInput,
        ]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            $errors[] = 'Incorrect username, email address, or password.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['username'] = (string) $user['username'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            if ($rememberMe) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    session_id(),
                    [
                        'expires' => time() + 60 * 60 * 24 * 14,
                        'path' => $params['path'] ?: '/',
                        'domain' => $params['domain'],
                        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]
                );
            }

            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In – Game Portal</title>
    <link rel="stylesheet" href="assets/auth.css">
</head>
<body>
    <div class="auth-shell">
        <div class="mascot-panel">
            <img
                src="assets/mascot_cat.jpg"
                alt="Black cat mascot playing with a ball"
                class="mascot-img"
            >
            <span class="mascot-badge">🐾 Games available after signing in</span>
        </div>

        <div class="auth-card">
            <div class="brand-mark">🎮</div>
            <h1 class="auth-title">Welcome back!</h1>
            <p class="auth-subtitle">Sign in to access your scores and leaderboard</p>

            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="login.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <div class="field-group">
                    <label class="field-label" for="login">Username or email address</label>
                    <div class="input-wrap">
                        <span class="input-icon">✉️</span>
                        <input
                            class="auth-input no-toggle"
                            type="text"
                            id="login"
                            name="login"
                            placeholder="e.g. player01 or you@example.com"
                            value="<?= htmlspecialchars($loginInput, ENT_QUOTES, 'UTF-8') ?>"
                            required
                        >
                    </div>
                </div>

                <div class="field-group">
                    <label class="field-label" for="password">Password</label>
                    <div class="input-wrap">
                        <span class="input-icon">🔒</span>
                        <input
                            class="auth-input"
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            required
                        >
                        <button
                            type="button"
                            class="toggle-pass-btn"
                            data-target="password"
                            aria-label="Show or hide password"
                        >👁️</button>
                    </div>
                </div>

                <div class="auth-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember_me" <?= $rememberMe ? 'checked' : '' ?>>
                        <span>Remember me</span>
                    </label>
                    <a class="muted-link" href="register.php">Create an account</a>
                </div>

                <button type="submit" class="submit-btn">Sign in →</button>
            </form>

            <div class="divider">Quick look</div>
            <div class="quick-badges">
                <div class="quick-pill">🏆 Top 10</div>
                <div class="quick-pill">⚡ Instant score saving</div>
                <div class="quick-pill">🕹️ Mini-games</div>
            </div>

            <p class="switch-text">
                Don’t have an account? <a href="register.php">Sign up</a>
            </p>
        </div>
    </div>

    <script>
        document.querySelectorAll('.toggle-pass-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const input = document.getElementById(btn.dataset.target);
                if (!input) return;
                input.type = input.type === 'password' ? 'text' : 'password';
            });
        });
    </script>
</body>
</html>