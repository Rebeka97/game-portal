<?php

declare(strict_types=1);

function isGameImplemented(string $slug): bool
{
    return in_array($slug, ['xoxo', 'sudoku'], true);
}
