<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/games.php';

$slug = trim((string) ($_GET['game'] ?? ''));
$gamePaths = [
    'xoxo' => '../games/tictactoe/index.html',
    'sudoku' => '../games/sudoku/index.html',
];

if ($slug === '') {
    http_response_code(400);
    exit('Missing game identifier. <a href="../index.php">Back to games</a>');
}

if (!isGameImplemented($slug) || !isset($gamePaths[$slug])) {
    http_response_code(503);
    exit('This game is still in development. <a href="../index.php">Back to games</a>');
}

$stmt = $pdo->prepare('SELECT id FROM games WHERE slug = :slug LIMIT 1');
$stmt->execute([':slug' => $slug]);

if (!$stmt->fetch()) {
    http_response_code(404);
    exit('The requested game could not be found. <a href="../index.php">Back to games</a>');
}

header('Location: ' . $gamePaths[$slug]);
exit;
