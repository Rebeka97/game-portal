<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/avatars.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Only GET requests are allowed.']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please sign in to play.']);
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = (int) $_SESSION['user_id'];
$userStmt = $pdo->prepare('SELECT username FROM users WHERE id = :id LIMIT 1');
$userStmt->execute([':id' => $userId]);
$user = $userStmt->fetch();

if (!$user) {
    unset($_SESSION['user_id'], $_SESSION['username']);
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Your session is invalid. Please sign in again.']);
    exit;
}

$gameStmt = $pdo->prepare('SELECT id, slug FROM games WHERE slug = :slug LIMIT 1');
$gameStmt->execute([':slug' => 'xoxo']);
$game = $gameStmt->fetch();

if (!$game) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'The XOXO game could not be found or is inactive.']);
    exit;
}

$statsStmt = $pdo->prepare(
    'SELECT COUNT(*) AS games_played, COALESCE(SUM(score), 0) AS total_points
     FROM scores
     WHERE user_id = :user_id AND game_id = :game_id'
);
$statsStmt->execute([
    ':user_id' => $userId,
    ':game_id' => (int) $game['id'],
]);
$stats = $statsStmt->fetch();

$leaderboardStmt = $pdo->prepare(
    'SELECT s.score, u.username
     FROM scores s
     JOIN users u ON u.id = s.user_id
     WHERE s.game_id = :game_id
     ORDER BY s.score DESC, s.played_at DESC
     LIMIT 5'
);
$leaderboardStmt->execute([':game_id' => (int) $game['id']]);
$leaderboard = [];
foreach ($leaderboardStmt->fetchAll() as $entry) {
    $leaderboard[] = [
        'username' => (string) $entry['username'],
        'score' => (int) $entry['score'],
    ];
}

$avatarChoices = [];
foreach (getAvailableAvatars() as $key => $avatar) {
    $avatarChoices[] = [
        'key' => (string) $key,
        'label' => (string) $avatar['label'],
        'image' => '../../' . $avatar['image'],
    ];
}

echo json_encode([
    'ok' => true,
    'username' => (string) $user['username'],
    'game_slug' => (string) $game['slug'],
    'csrf_token' => $_SESSION['csrf_token'],
    'games_played' => (int) $stats['games_played'],
    'total_points' => (int) $stats['total_points'],
    'leaderboard' => $leaderboard,
    'avatar_choices' => $avatarChoices,
], JSON_UNESCAPED_UNICODE);
