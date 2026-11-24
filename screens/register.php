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
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $errors[] = 'Invalid form submission (CSRF). Refresh the page and try again.';
    }

    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if ($username === '' || mb_strlen($username) < 3 || mb_strlen($username) > 50) {
        $errors[] = 'Username must be between 3 and 50 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
        $errors[] = 'Username may contain only letters, numbers, periods, hyphens, and underscores.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
        $errors[] = 'Enter a valid email address.';
    }

    if (mb_strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($password !== $passwordConfirm) {
        $errors[] = 'The passwords do not match.';
    }

    if (empty($errors)) {
        $checkStmt = $pdo->prepare(
            'SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1'
        );
        $checkStmt->execute([
            ':username' => $username,
            ':email' => $email,
        ]);

        if ($checkStmt->fetch()) {
            $errors[] = 'That username or email address is already in use.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $insertStmt = $pdo->prepare(
                'INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)'
            );
            $insertStmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':password_hash' => $passwordHash,
            ]);

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $pdo->lastInsertId();
            $_SESSION['username'] = $username;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

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
    <title>Sign Up – Game Portal</title>
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
            <span class="mascot-badge">🐾 Join the game portal</span>
        </div>

        <div class="auth-card">
            <div class="brand-mark">✨</div>
            <h1 class="auth-title">Create an account</h1>
            <p class="auth-subtitle">Sign up to save your scores</p>

            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="register.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <div class="field-group">
                    <label class="field-label" for="username">Username</label>
                    <div class="input-wrap">
                        <span class="input-icon">🕹️</span>
                        <input
                            class="auth-input no-toggle"
                            type="text"
                            id="username"
                            name="username"
                            placeholder="3–50 characters"
                            value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                            required
                        >
                    </div>
                </div>

                <div class="field-group">
                    <label class="field-label" for="email">Email address</label>
                    <div class="input-wrap">
                        <span class="input-icon">✉️</span>
                        <input
                            class="auth-input no-toggle"
                            type="email"
                            id="email"
                            name="email"
                            placeholder="you@example.com"
                            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
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
                            placeholder="At least 8 characters"
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

                <div class="field-group">
                    <label class="field-label" for="password_confirm">Confirm password</label>
                    <div class="input-wrap">
                        <span class="input-icon">🔑</span>
                        <input
                            class="auth-input"
                            type="password"
                            id="password_confirm"
                            name="password_confirm"
                            placeholder="Enter your password again"
                            required
                        >
                        <button
                            type="button"
                            class="toggle-pass-btn"
                            data-target="password_confirm"
                            aria-label="Show or hide password"
                        >👁️</button>
                    </div>
                </div>

                <button type="submit" class="submit-btn">Sign up →</button>
            </form>

            <p class="switch-text">
                Already have an account? <a href="login.php">Sign in</a>
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