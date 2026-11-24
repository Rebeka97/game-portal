<?php

declare(strict_types=1);

function getAvailableAvatars(): array
{
    return [
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
}

function renderAvatarCircle(string $avatarKey, string $sizeClass = 'avatar-md'): string
{
    $avatars = getAvailableAvatars();
    $selected = $avatars[$avatarKey] ?? $avatars['cat-ball'];
    $safeLabel = htmlspecialchars($selected['label'], ENT_QUOTES, 'UTF-8');
    $safeImage = htmlspecialchars($selected['image'], ENT_QUOTES, 'UTF-8');
    $safeBg = htmlspecialchars($selected['bg'], ENT_QUOTES, 'UTF-8');

    return sprintf(
        '<span class="avatar-circle %s" style="background: %s;" role="img" aria-label="%s"><img class="avatar-image" src="%s" alt=""></span>',
        htmlspecialchars($sizeClass, ENT_QUOTES, 'UTF-8'),
        $safeBg,
        $safeLabel,
        $safeImage
    );
}