<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/games.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Only POST requests are allowed.']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please sign in to save your score.']);
    exit;
}

$submittedToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid or missing CSRF token. Refresh the page and try again.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$gameSlug = trim((string) ($data['game_slug'] ?? ''));
$score = filter_var($data['score'] ?? null, FILTER_VALIDATE_INT);

if ($gameSlug === '' || $score === false || $score < 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid game identifier or score.']);
    exit;
}

if (!isGameImplemented($gameSlug)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'This game is still in development and cannot save scores.']);
    exit;
}

$gameStmt = $pdo->prepare('SELECT id FROM games WHERE slug = :slug LIMIT 1');
$gameStmt->execute([':slug' => $gameSlug]);
$game = $gameStmt->fetch();

if (!$game) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'The game could not be found or is inactive.']);
    exit;
}

$insertStmt = $pdo->prepare(
    'INSERT INTO scores (user_id, game_id, score) VALUES (:user_id, :game_id, :score)'
);
$insertStmt->execute([
    ':user_id' => (int) $_SESSION['user_id'],
    ':game_id' => (int) $game['id'],
    ':score' => $score,
]);

echo json_encode([
    'ok' => true,
    'id' => (int) $pdo->lastInsertId(),
    'score' => $score,
]);