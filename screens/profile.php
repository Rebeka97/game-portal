<?php

declare(strict_types=1);

session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/avatars.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = (int) $_SESSION['user_id'];
$avatars = getAvailableAvatars();

$avatarSuccess = '';
$avatarErrors = [];
$passwordSuccess = '';
$passwordErrors = [];

$userStmt = $pdo->prepare(
    'SELECT id, username, email, password_hash, avatar, created_at FROM users WHERE id = :id LIMIT 1'
);
$userStmt->execute([':id' => $userId]);
$user = $userStmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$currentAvatar = (string) ($user['avatar'] ?? 'cat-ball');
if (!isset($avatars[$currentAvatar])) {
    $currentAvatar = 'cat-ball';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $formAction = (string) ($_POST['form_action'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        if ($formAction === 'change_password') {
            $passwordErrors[] = 'Invalid form submission (CSRF). Refresh the page and try again.';
        } else {
            $avatarErrors[] = 'Invalid form submission (CSRF). Refresh the page and try again.';
        }
    } elseif ($formAction === 'change_avatar') {
        $chosenAvatar = (string) ($_POST['avatar'] ?? '');

        if (!isset($avatars[$chosenAvatar])) {
            $avatarErrors[] = 'The selected avatar is invalid.';
        } else {
            $updateAvatarStmt = $pdo->prepare(
                'UPDATE users SET avatar = :avatar WHERE id = :id'
            );
            $updateAvatarStmt->execute([
                ':avatar' => $chosenAvatar,
                ':id' => $userId,
            ]);

            $currentAvatar = $chosenAvatar;
            $_SESSION['avatar'] = $chosenAvatar;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $avatarSuccess = 'Avatar updated successfully!';
        }
    } elseif ($formAction === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $passwordErrors[] = 'All password fields are required.';
        } elseif (!password_verify($currentPassword, (string) $user['password_hash'])) {
            $passwordErrors[] = 'The current password is incorrect.';
        } elseif (mb_strlen($newPassword) < 8) {
            $passwordErrors[] = 'The new password must be at least 8 characters long.';
        } elseif ($newPassword === $currentPassword) {
            $passwordErrors[] = 'The new password must be different from the current password.';
        } elseif ($newPassword !== $confirmPassword) {
            $passwordErrors[] = 'The new password and confirmation do not match.';
        }

        if (empty($passwordErrors)) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updatePassStmt = $pdo->prepare(
                'UPDATE users SET password_hash = :password_hash WHERE id = :id'
            );
            $updatePassStmt->execute([
                ':password_hash' => $newHash,
                ':id' => $userId,
            ]);

            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $user['password_hash'] = $newHash;
            $passwordSuccess = 'Your password has been changed successfully!';
        }
    }
}

$createdAtFormatted = 'Ismeretlen';
if (!empty($user['created_at'])) {
    try {
        $dt = new DateTimeImmutable((string) $user['created_at']);
        $createdAtFormatted = $dt->format('M j, Y H:i');
    } catch (Exception $e) {
        $createdAtFormatted = (string) $user['created_at'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile – Game Portal</title>
    <link rel="stylesheet" href="assets/portal.css">
</head>
<body>
    <div class="portal-wrap">
        <header class="portal-header">
            <div class="brand-block">
                <div>
                    <h1>My Profile</h1>
                    <p>Account details, avatar, and password settings</p>
                </div>
            </div>
            <div class="header-actions">
                <a class="btn-ghost" href="index.php">← Games &amp; Scores</a>
                <a class="btn-ghost" href="logout.php">Sign out</a>
            </div>
        </header>

        <div class="profile-grid">
            <section class="panel" aria-label="Account details and avatar">
                <div class="profile-summary">
                    <?= renderAvatarCircle($currentAvatar, 'avatar-lg') ?>
                    <div class="profile-meta">
                        <h2><?= htmlspecialchars((string) $user['username'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="profile-email"><?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="profile-date">Member since: <strong><?= htmlspecialchars($createdAtFormatted, ENT_QUOTES, 'UTF-8') ?></strong></p>
                    </div>
                </div>

                <hr class="soft-divider">

                <h3 class="sub-heading">Choose a profile avatar</h3>
                <p class="muted">Choose one of the available avatars:</p>

                <?php if ($avatarSuccess !== ''): ?>
                    <div class="alert-success"><?= htmlspecialchars($avatarSuccess, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <?php if (!empty($avatarErrors)): ?>
                    <div class="alert-error">
                        <ul>
                            <?php foreach ($avatarErrors as $err): ?>
                                <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="profile.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="form_action" value="change_avatar">

                    <div class="avatar-picker-grid">
                        <?php foreach ($avatars as $key => $item): ?>
                            <?php $isSelected = ($key === $currentAvatar); ?>
                            <label class="avatar-option <?= $isSelected ? 'is-selected' : '' ?>">
                                <input
                                    type="radio"
                                    name="avatar"
                                    value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                    aria-label="<?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $isSelected ? 'checked' : '' ?>
                                >
                                <span
                                    class="avatar-circle avatar-md"
                                    style="background: <?= htmlspecialchars($item['bg'], ENT_QUOTES, 'UTF-8') ?>;"
                                    aria-hidden="true"
                                ><img class="avatar-image" src="<?= htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8') ?>" alt=""></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Save avatar</button>
                    </div>
                </form>
            </section>

            <section class="panel" aria-label="Change password">
                <h2 class="panel-title-left">Change password</h2>
                <p class="muted">Choose a new password with at least 8 characters to protect your account.</p>

                <?php if ($passwordSuccess !== ''): ?>
                    <div class="alert-success"><?= htmlspecialchars($passwordSuccess, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <?php if (!empty($passwordErrors)): ?>
                    <div class="alert-error">
                        <ul>
                            <?php foreach ($passwordErrors as $err): ?>
                                <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="profile.php" class="profile-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="form_action" value="change_password">

                    <div class="form-field">
                        <label for="current_password">Current password</label>
                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            class="portal-input"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="new_password">New password</label>
                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            class="portal-input"
                            placeholder="At least 8 characters"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="confirm_password">Confirm new password</label>
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            class="portal-input"
                            placeholder="Enter your new password again"
                            required
                        >
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Update password</button>
                    </div>
                </form>
            </section>
        </div>
    </div>

    <script>
        (function () {
            const options = document.querySelectorAll('.avatar-option');
            options.forEach((opt) => {
                const radio = opt.querySelector('input[type="radio"]');
                if (!radio) return;
                radio.addEventListener('change', () => {
                    options.forEach((el) => el.classList.remove('is-selected'));
                    if (radio.checked) {
                        opt.classList.add('is-selected');
                    }
                });
            });
        })();
    </script>
</body>
</html>