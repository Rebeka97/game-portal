<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/games.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = (int) $_SESSION['user_id'];
$page = (string) ($_GET['page'] ?? 'lobby');

// Predefined circular avatars
$avatars = [
    'cat-ball' => [
        'label' => 'Cat with a ball',
        'image' => 'assets/avatars/cat1.jpg',
        'bg' => 'linear-gradient(135deg, #ff9d3b 0%, #e25500 100%)',
    ],
    'fox-gamer' => [
        'label' => 'Gamer fox',
        'image' => 'assets/avatars/cat2.jpeg',
        'bg' => 'linear-gradient(135deg, #ffb347 0%, #ff6b00 100%)',
    ],
    'panda-pro' => [
        'label' => 'Pro panda',
        'image' => 'assets/avatars/cat3.jpeg',
        'bg' => 'linear-gradient(135deg, #ffd194 0%, #d95f02 100%)',
    ],
    'dragon-spark' => [
        'label' => 'Spark dragon',
        'image' => 'assets/avatars/cat4.jpeg',
        'bg' => 'linear-gradient(135deg, #ff7b1c 0%, #b83b00 100%)',
    ],
    'robot-arcade' => [
        'label' => 'Arcade robot',
        'image' => 'assets/avatars/cat5.jpeg',
        'bg' => 'linear-gradient(135deg, #ffbe73 0%, #e25500 100%)',
    ],
    'tiger-champ' => [
        'label' => 'Tiger champion',
        'image' => 'assets/avatars/cat6.jpeg',
        'bg' => 'linear-gradient(135deg, #ff922b 0%, #c94a00 100%)',
    ],
];

// Load the user and fall back if the avatar column has not been added yet.
try {
    $userStmt = $pdo->prepare(
        'SELECT id, username, email, password_hash, avatar, created_at FROM users WHERE id = :id LIMIT 1'
    );
    $userStmt->execute([':id' => $userId]);
    $user = $userStmt->fetch();
} catch (PDOException $e) {
    $userStmt = $pdo->prepare(
        'SELECT id, username, email, password_hash, created_at FROM users WHERE id = :id LIMIT 1'
    );
    $userStmt->execute([':id' => $userId]);
    $user = $userStmt->fetch();
    if ($user) {
        $user['avatar'] = $_SESSION['avatar'] ?? 'cat-ball';
    }
}

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$username = (string) $user['username'];
$currentAvatar = (string) ($user['avatar'] ?? $_SESSION['avatar'] ?? 'cat-ball');
if (!isset($avatars[$currentAvatar])) {
    $currentAvatar = 'cat-ball';
}

$avatarSuccess = '';
$avatarErrors = [];
$passwordSuccess = '';
$passwordErrors = [];

// Process profile forms.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $page = 'profile';
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
            try {
                $updateAvatarStmt = $pdo->prepare('UPDATE users SET avatar = :avatar WHERE id = :id');
                $updateAvatarStmt->execute([
                    ':avatar' => $chosenAvatar,
                    ':id' => $userId,
                ]);
            } catch (PDOException $e) {
                $avatarErrors[] = 'Add the avatar column to the users table to save your avatar.';
            }

            if (empty($avatarErrors)) {
                $currentAvatar = $chosenAvatar;
                $_SESSION['avatar'] = $chosenAvatar;
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $avatarSuccess = 'Avatar updated successfully!';
            }
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
            $updatePassStmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
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

$selectedAvatarInfo = $avatars[$currentAvatar];

// Load games and scores on the dashboard.
$games = [];
$recentScores = [];
$gameTotals = [];
$leaderboards = [];
$totalPoints = 0;

if ($page !== 'profile') {
    $gamesStmt = $pdo->query('SELECT id, slug, title FROM games ORDER BY id ASC');
    $games = $gamesStmt->fetchAll();
    usort($games, static function (array $first, array $second): int {
        $firstAvailable = isGameImplemented((string) $first['slug']) ? 1 : 0;
        $secondAvailable = isGameImplemented((string) $second['slug']) ? 1 : 0;

        if ($firstAvailable !== $secondAvailable) {
            return $secondAvailable <=> $firstAvailable;
        }

        return (int) $first['id'] <=> (int) $second['id'];
    });

    $scoresStmt = $pdo->prepare(
        'SELECT s.score, s.played_at, g.slug AS game_slug, g.title AS game_title
         FROM scores s
         JOIN games g ON s.game_id = g.id
         WHERE s.user_id = :user_id
         ORDER BY s.played_at DESC
         LIMIT 10'
    );
    $scoresStmt->execute([':user_id' => $userId]);
    $recentScores = $scoresStmt->fetchAll();

    $gameTotalsStmt = $pdo->prepare(
        'SELECT g.slug, g.title, COUNT(s.id) AS games_played, COALESCE(SUM(s.score), 0) AS total_points
         FROM games g
         LEFT JOIN scores s ON s.game_id = g.id AND s.user_id = :user_id
         GROUP BY g.id, g.slug, g.title
         ORDER BY g.title'
    );
    $gameTotalsStmt->execute([':user_id' => $userId]);
    $gameTotals = $gameTotalsStmt->fetchAll();
    foreach ($gameTotals as $entry) {
        $totalPoints += (int) $entry['total_points'];
    }

    $overallLeaderboardStmt = $pdo->query(
        'SELECT u.username, COALESCE(SUM(s.score), 0) AS total_points
         FROM users u
         JOIN scores s ON s.user_id = u.id
         GROUP BY u.id, u.username
         ORDER BY total_points DESC, u.username ASC
         LIMIT 10'
    );
    $leaderboards[] = [
        'slug' => '',
        'title' => 'Overall points',
        'entries' => $overallLeaderboardStmt->fetchAll(),
    ];

    $gameLeaderboardStmt = $pdo->prepare(
        'SELECT u.username, SUM(s.score) AS total_points
         FROM scores s
         JOIN users u ON u.id = s.user_id
         WHERE s.game_id = :game_id
         GROUP BY u.id, u.username
         ORDER BY total_points DESC, u.username ASC
         LIMIT 10'
    );
    foreach ($games as $game) {
        $gameLeaderboardStmt->execute([':game_id' => (int) $game['id']]);
        $leaderboards[] = [
            'slug' => (string) $game['slug'],
            'title' => (string) $game['title'],
            'entries' => $gameLeaderboardStmt->fetchAll(),
        ];
    }
}

$gameTitles = [
    'snake' => 'Snake',
    'memory' => 'Memory',
    'xoxo' => 'XOXO',
    'sudoku' => 'Sudoku',
];

$gameImages = [
    'snake' => '',
    'memory' => '',
    'xoxo' => 'assets/tictactoe_images/xoxo_bg.jpeg',
    'sudoku' => 'assets/sudoku_images/sudoku_bg.jpeg',
];

$gameDescriptions = [
    'snake' => 'Collect food, grow your snake, and avoid the walls!',
    'memory' => 'Match all the pairs in as few moves as possible!',
    'xoxo' => 'Classic tic-tac-toe: get three in a row!',
    'sudoku' => 'Complete the 9×9 grid using logic, without mistakes!',
];

$createdAtFormatted = 'Unknown';
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
    <title><?= $page === 'profile' ? 'Profile – Game Portal' : 'Game Portal' ?></title>
    <link rel="stylesheet" href="assets/portal.css?v=<?= (int) filemtime(__DIR__ . '/assets/portal.css') ?>">
</head>
<body>
    <div class="portal-wrap">
        <header class="portal-header">
            <div class="brand-block">
                <span
                    class="avatar-circle avatar-sm"
                    style="background: <?= htmlspecialchars($selectedAvatarInfo['bg'], ENT_QUOTES, 'UTF-8') ?>;"
                    role="img"
                    aria-label="<?= htmlspecialchars($selectedAvatarInfo['label'], ENT_QUOTES, 'UTF-8') ?>"
                ><img class="avatar-image" src="<?= htmlspecialchars($selectedAvatarInfo['image'], ENT_QUOTES, 'UTF-8') ?>" alt=""></span>
                <div>
                    <h1><?= $page === 'profile' ? 'My Profile' : 'Game Portal' ?></h1>
                    <p>Signed in as <strong><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></strong></p>
                </div>
            </div>
            <div class="header-actions">
                <?php if ($page === 'profile'): ?>
                    <a class="btn-secondary" href="index.php">← Games &amp; Scores</a>
                <?php else: ?>
                    <a class="btn-secondary" href="index.php?page=profile">Profile</a>
                <?php endif; ?>
                <a class="btn-ghost" href="logout.php">Sign out</a>
            </div>
        </header>

        <?php if ($page === 'profile'): ?>
            <div class="profile-grid">
                <section class="panel" aria-label="Account details and avatar">
                    <div class="profile-summary">
                        <span
                            class="avatar-circle avatar-lg"
                            style="background: <?= htmlspecialchars($selectedAvatarInfo['bg'], ENT_QUOTES, 'UTF-8') ?>;"
                            role="img"
                            aria-label="<?= htmlspecialchars($selectedAvatarInfo['label'], ENT_QUOTES, 'UTF-8') ?>"
                        ><img class="avatar-image" src="<?= htmlspecialchars($selectedAvatarInfo['image'], ENT_QUOTES, 'UTF-8') ?>" alt=""></span>
                        <div class="profile-meta">
                            <h2><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></h2>
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

                    <form method="post" action="index.php?page=profile">
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

                    <form method="post" action="index.php?page=profile" class="profile-form">
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
        <?php else: ?>
            <section class="games-section" aria-label="Games">
                <h2 class="section-heading">Games</h2>

                <?php if (empty($games)): ?>
                    <div class="panel">
                        <p class="muted">There are no games in the database yet.</p>
                    </div>
                <?php else: ?>
                    <div class="carousel-wrapper">
                        <button
                            type="button"
                            class="carousel-arrow"
                            id="prev-game-btn"
                            aria-label="Previous games"
                        >◀</button>

                        <div class="carousel-viewport" id="carousel-viewport">
                            <div class="carousel-track" id="carousel-track">
                                <?php foreach ($games as $game): ?>
                                    <?php
                                    $slug = (string) $game['slug'];
                                    $title = $gameTitles[$slug] ?? (string) $game['title'];
                                    $isPlayable = isGameImplemented($slug);
                                    $imgSrc = $gameImages[$slug] ?? '';
                                    $desc = $isPlayable
                                        ? ($gameDescriptions[$slug] ?? 'Play a game and earn points.')
                                        : 'This game is still in development.';
                                    ?>
                                    <article class="game-slide <?= $isPlayable ? '' : 'is-unavailable' ?>">
                                        <div class="game-thumb-frame">
                                            <div class="game-thumb">
                                                <?php if ($imgSrc !== ''): ?>
                                                    <img
                                                        src="<?= htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8') ?>"
                                                        alt="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
                                                    >
                                                <?php else: ?>
                                                    <span class="game-thumb-placeholder">
                                                        <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="game-card-body">
                                            <div>
                                                <span class="game-meta-tag"><?= $isPlayable ? 'Available' : 'Coming soon' ?></span>
                                                <h3 class="game-card-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3>
                                                <p class="game-card-desc"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></p>
                                            </div>

                                            <?php if ($isPlayable): ?>
                                                <a
                                                    class="game-play-link"
                                                    href="screens/play.php?game=<?= urlencode($slug) ?>"
                                                >Play now ›</a>
                                            <?php else: ?>
                                                <span class="game-unavailable" aria-label="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> – coming soon">
                                                    Coming soon
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="carousel-arrow"
                            id="next-game-btn"
                            aria-label="Next games"
                        >▶</button>
                    </div>

                    <div class="carousel-dots" id="carousel-dots" aria-label="Carousel pages"></div>
                <?php endif; ?>
            </section>

            <div class="dashboard-panels">
                <section class="panel" aria-label="Recent scores">
                    <h2 class="section-heading">Recent scores</h2>
                    <p class="muted">Your most recent saved game results.</p>
                    <?php if (empty($recentScores)): ?>
                        <p class="muted">You have no saved scores yet.</p>
                    <?php else: ?>
                        <ol class="score-list">
                            <?php foreach ($recentScores as $entry): ?>
                                <?php $playedAt = new DateTimeImmutable((string) $entry['played_at']); ?>
                                <li>
                                    <time class="score-time" datetime="<?= htmlspecialchars($playedAt->format(DateTimeInterface::ATOM), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($playedAt->format('M j, Y H:i'), ENT_QUOTES, 'UTF-8') ?>
                                    </time>
                                    <span class="game-tag"><?= htmlspecialchars($gameTitles[(string) $entry['game_slug']] ?? (string) $entry['game_title'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <strong class="pts"><?= (int) $entry['score'] ?> <?= (int) $entry['score'] === 1 ? 'point' : 'points' ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </section>

                <div class="points-stack">
                    <section class="panel" aria-label="Points by game">
                        <h2 class="section-heading">Points by game</h2>
                        <?php if (empty($gameTotals)): ?>
                            <p class="muted">No games are available to summarize.</p>
                        <?php else: ?>
                            <div class="points-table-wrap">
                                <table class="points-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Game</th>
                                            <th scope="col">Games played</th>
                                            <th scope="col">Total points</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($gameTotals as $entry): ?>
                                            <?php $gameSlug = (string) $entry['slug']; ?>
                                            <tr>
                                                <th scope="row"><?= htmlspecialchars($gameTitles[$gameSlug] ?? (string) $entry['title'], ENT_QUOTES, 'UTF-8') ?></th>
                                                <td><?= (int) $entry['games_played'] ?></td>
                                                <td><strong><?= (int) $entry['total_points'] ?> <?= (int) $entry['total_points'] === 1 ? 'point' : 'points' ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="panel total-points-card" aria-label="Total points across all games">
                        <h2 class="section-heading">TOTAL POINTS</h2>
                        <strong class="big-score"><?= number_format($totalPoints) ?> <?= $totalPoints === 1 ? 'point' : 'points' ?></strong>
                        <p class="muted">Combined score from all games.</p>
                    </section>
                </div>
            </div>

            <section class="panel public-leaderboard" aria-labelledby="public-leaderboard-title">
                <div class="public-leaderboard-heading">
                    <div>
                        <h2 class="section-heading" id="public-leaderboard-title">Player leaderboards</h2>
                        <p class="muted">Compare total points across the portal or browse scores by game.</p>
                    </div>
                </div>

                <nav class="leaderboard-tabs" role="tablist" aria-label="Leaderboard categories">
                    <?php foreach ($leaderboards as $boardIndex => $leaderboard): ?>
                        <?php
                        $leaderboardSlug = (string) $leaderboard['slug'];
                        $leaderboardTitle = $leaderboardSlug === ''
                            ? 'Overall points'
                            : ($gameTitles[$leaderboardSlug] ?? (string) $leaderboard['title']);
                        ?>
                        <button
                            type="button"
                            class="leaderboard-tab"
                            id="leaderboard-tab-<?= $boardIndex ?>"
                            role="tab"
                            aria-controls="leaderboard-page-<?= $boardIndex ?>"
                            aria-selected="<?= $boardIndex === 0 ? 'true' : 'false' ?>"
                            tabindex="<?= $boardIndex === 0 ? '0' : '-1' ?>"
                        ><?= htmlspecialchars($leaderboardTitle, ENT_QUOTES, 'UTF-8') ?></button>
                    <?php endforeach; ?>
                </nav>

                <?php foreach ($leaderboards as $boardIndex => $leaderboard): ?>
                    <?php
                    $leaderboardSlug = (string) $leaderboard['slug'];
                    $leaderboardTitle = $leaderboardSlug === ''
                        ? 'Overall points'
                        : ($gameTitles[$leaderboardSlug] ?? (string) $leaderboard['title']);
                    $medals = ['🥇', '🥈', '🥉'];
                    $medalLabels = ['Gold medal', 'Silver medal', 'Bronze medal'];
                    ?>
                    <section
                        class="leaderboard-page"
                        id="leaderboard-page-<?= $boardIndex ?>"
                        role="tabpanel"
                        aria-labelledby="leaderboard-tab-<?= $boardIndex ?>"
                        tabindex="0"
                        aria-label="<?= htmlspecialchars($leaderboardTitle, ENT_QUOTES, 'UTF-8') ?>"
                        <?= $boardIndex === 0 ? '' : 'hidden' ?>
                    >
                        <h3 class="leaderboard-page-title"><?= htmlspecialchars($leaderboardTitle, ENT_QUOTES, 'UTF-8') ?></h3>
                        <?php if (empty($leaderboard['entries'])): ?>
                            <p class="muted">No scores have been recorded for this list yet.</p>
                        <?php else: ?>
                            <ol class="player-ranking">
                                <?php foreach ($leaderboard['entries'] as $rank => $entry): ?>
                                    <li class="player-ranking-item">
                                        <span class="player-rank"><?= $rank + 1 ?>.</span>
                                        <span class="player-ranking-name">
                                            <?php if (isset($medals[$rank])): ?>
                                                <span class="player-medal" role="img" aria-label="<?= $medalLabels[$rank] ?>"><?= $medals[$rank] ?></span>
                                            <?php endif; ?>
                                            <span class="player-name-text"><?= htmlspecialchars((string) $entry['username'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </span>
                                        <strong class="player-ranking-score">
                                            <?= number_format((int) $entry['total_points']) ?>
                                            <?= (int) $entry['total_points'] === 1 ? 'point' : 'points' ?>
                                        </strong>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </section>

            <script>
                (function () {
                    const track = document.getElementById('carousel-track');
                    const viewport = document.getElementById('carousel-viewport');
                    const prevBtn = document.getElementById('prev-game-btn');
                    const nextBtn = document.getElementById('next-game-btn');
                    const dotsContainer = document.getElementById('carousel-dots');

                    if (!track || !viewport || !prevBtn || !nextBtn) return;

                    const originalSlides = Array.from(track.children);
                    const total = originalSlides.length;
                    if (total === 0) return;

                    let cloneCount = Math.min(3, total);
                    let currentIndex = cloneCount;
                    let isAnimating = false;
                    const transitionRule = 'transform 0.42s cubic-bezier(0.22, 1, 0.36, 1)';

                    function getVisibleCount() {
                        if (window.innerWidth <= 640) return 1;
                        if (window.innerWidth <= 960) return 2;
                        return 3;
                    }

                    function rebuildClones(keepLogicalIndex) {
                        track.style.transition = 'none';
                        track.innerHTML = '';

                        cloneCount = Math.min(getVisibleCount(), total);

                        for (let i = total - cloneCount; i < total; i++) {
                            const clone = originalSlides[i].cloneNode(true);
                            clone.dataset.clone = 'true';
                            track.appendChild(clone);
                        }

                        originalSlides.forEach((slide) => {
                            track.appendChild(slide);
                        });

                        for (let i = 0; i < cloneCount; i++) {
                            const clone = originalSlides[i].cloneNode(true);
                            clone.dataset.clone = 'true';
                            track.appendChild(clone);
                        }

                        currentIndex = cloneCount + (((keepLogicalIndex % total) + total) % total);
                        applyTransform(false);
                        updateDots();
                    }

                    function applyTransform(animate) {
                        const allSlides = Array.from(track.children);
                        const target = allSlides[currentIndex];
                        const base = allSlides[0];
                        const offset = target && base ? target.offsetLeft - base.offsetLeft : 0;

                        track.style.transition = animate ? transitionRule : 'none';
                        track.style.transform = `translateX(-${offset}px)`;
                    }

                    function getLogicalIndex() {
                        return (((currentIndex - cloneCount) % total) + total) % total;
                    }

                    function buildDots() {
                        if (!dotsContainer) return;
                        dotsContainer.innerHTML = '';
                        if (total <= 1) return;

                        for (let i = 0; i < total; i++) {
                            const dot = document.createElement('button');
                            dot.type = 'button';
                            dot.className = 'carousel-dot';
                            dot.setAttribute('aria-label', `Game ${i + 1}`);
                            dot.addEventListener('click', () => {
                                if (isAnimating) return;
                                const currentLogical = getLogicalIndex();
                                const delta = i - currentLogical;
                                if (delta === 0) return;
                                currentIndex += delta;
                                isAnimating = true;
                                applyTransform(true);
                                updateDots();
                            });
                            dotsContainer.appendChild(dot);
                        }
                        updateDots();
                    }

                    function updateDots() {
                        if (!dotsContainer) return;
                        const logical = getLogicalIndex();
                        Array.from(dotsContainer.children).forEach((dot, idx) => {
                            dot.classList.toggle('active', idx === logical);
                        });
                    }

                    function step(delta) {
                        if (isAnimating || total <= 1) return;
                        isAnimating = true;
                        currentIndex += delta;
                        applyTransform(true);
                        updateDots();
                    }

                    track.addEventListener('transitionend', (e) => {
                        if (e.propertyName !== 'transform') return;

                        if (currentIndex >= total + cloneCount) {
                            currentIndex -= total;
                            applyTransform(false);
                        } else if (currentIndex < cloneCount) {
                            currentIndex += total;
                            applyTransform(false);
                        }
                        isAnimating = false;
                    });

                    prevBtn.addEventListener('click', () => step(-1));
                    nextBtn.addEventListener('click', () => step(1));

                    let resizeTimer = null;
                    window.addEventListener('resize', () => {
                        clearTimeout(resizeTimer);
                        resizeTimer = setTimeout(() => {
                            const logical = getLogicalIndex();
                            isAnimating = false;
                            rebuildClones(logical);
                        }, 120);
                    });

                    rebuildClones(0);
                    buildDots();
                })();

                (function () {
                    const tabs = Array.from(document.querySelectorAll('.leaderboard-tab'));
                    const pages = Array.from(document.querySelectorAll('.leaderboard-page'));
                    if (!tabs.length || tabs.length !== pages.length) return;

                    let currentPage = 0;
                    const activatePage = (index, moveFocus = false) => {
                        currentPage = index;
                        tabs.forEach((tab, tabIndex) => {
                            const isSelected = tabIndex === currentPage;
                            tab.setAttribute('aria-selected', String(isSelected));
                            tab.tabIndex = isSelected ? 0 : -1;
                        });
                        pages.forEach((page, index) => {
                            page.hidden = index !== currentPage;
                        });
                        if (moveFocus) {
                            tabs[currentPage].focus();
                        }
                    };

                    tabs.forEach((tab, index) => {
                        tab.addEventListener('click', () => activatePage(index));
                        tab.addEventListener('keydown', (event) => {
                            let nextIndex = index;
                            if (event.key === 'ArrowRight') {
                                nextIndex = (index + 1) % tabs.length;
                            } else if (event.key === 'ArrowLeft') {
                                nextIndex = (index - 1 + tabs.length) % tabs.length;
                            } else if (event.key === 'Home') {
                                nextIndex = 0;
                            } else if (event.key === 'End') {
                                nextIndex = tabs.length - 1;
                            } else {
                                return;
                            }
                            event.preventDefault();
                            activatePage(nextIndex, true);
                        });
                    });
                    activatePage(0);
                })();
            </script>
        <?php endif; ?>
    </div>
</body>
</html>