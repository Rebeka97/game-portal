# Game Portal

Welcome to the **Game Portal** repository! This project is a browser-based gaming portal built with PHP, MySQL, JavaScript, and CSS. Players can create an account, choose an avatar, play games, and track their scores.

**XOXO** (tic-tac-toe) is currently playable. Snake, Memory, and Sudoku are listed as upcoming games.

## Key Features

### Player Accounts & Profiles
- Register, sign in, and sign out with a player account.
- Update your profile avatar and password.
- View recent scores, games played, and points earned.

### Game Portal Dashboard
- Browse games from a visual game carousel.
- View scores by game and a combined **Total Points** score.
- See recent results and game availability.

### XOXO — Tic-Tac-Toe
- Play against the computer on either a classic **3×3** board (connect 3) or a larger **4×4** board (connect 4).
- Choose to play as X or O and select from multiple image styles for each mark.
- Pick from Easy, Medium, and Hard difficulties. The AI varies its moves while using stronger tactics at higher difficulties.
- Detect wins, draws, and positions where neither player can still win.
- View a result dialog after each game and save scores to your account.
- Check your XOXO statistics and the leaderboard.

### Scores & Difficulty
- Difficulty multipliers: Easy ×1, Medium ×2, and Hard ×3.
- The larger board awards proportionally higher base scores.
- Saved scores contribute to per-game and total points.

### Security
- Passwords are stored as hashes.
- Database queries use PDO prepared statements.
- Forms and score-saving requests use CSRF tokens.
- Player sessions are used for account and game data.

## Tech Stack

- **Backend:** PHP
- **Database:** MySQL or MariaDB, accessed through PDO
- **Frontend:** HTML, CSS, and vanilla JavaScript
- **Local development:** XAMPP, Apache, and MySQL/MariaDB

No Composer or npm build step is currently required.

## Project Structure

```text
game-portal/
├── assets/                 # Portal styles, avatars, and game images
├── config/                 # Database, game, and avatar configuration
├── games/
│   └── tictactoe/          # XOXO game interface, styles, logic, and session API
├── screens/                # Login, registration, profile, and play pages
├── index.php               # Main portal dashboard
├── save_score.php          # Score-saving endpoint
└── login.php, register.php,
    profile.php, play.php   # Root-level page entry points
```

## Getting Started

### Prerequisites

- PHP with the **PDO MySQL** extension enabled
- MySQL or MariaDB
- Apache or another PHP-capable web server
- XAMPP is a convenient option for local development

### 1. Clone the repository

```bash
git clone https://github.com/Rebeka97/game-portal.git
```

Place the project in your web server's document root. For XAMPP, this is commonly:

```text
C:\xampp\htdocs\game-portal
```

### 2. Create the database

The application expects `users`, `games`, and `scores` tables. The following schema is a starting point for a fresh local database:

```sql
CREATE DATABASE IF NOT EXISTS `game-portal`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `game-portal`;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(32) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    avatar VARCHAR(40) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS games (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(32) NOT NULL,
    title VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_games_slug (slug)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS scores (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    game_id INT UNSIGNED NOT NULL,
    score INT UNSIGNED NOT NULL DEFAULT 0,
    played_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_scores_user_game (user_id, game_id),
    KEY idx_scores_game_score (game_id, score, played_at),
    CONSTRAINT fk_scores_user
        FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT fk_scores_game
        FOREIGN KEY (game_id) REFERENCES games (id)
) ENGINE=InnoDB;

INSERT INTO games (slug, title) VALUES
    ('snake', 'Snake'),
    ('memory', 'Memory'),
    ('xoxo', 'XOXO'),
    ('sudoku', 'Sudoku');
```

The `xoxo` game record is required for the playable tic-tac-toe game and score saving. The other games are currently listed as coming soon.

### 3. Configure the database connection

Open `config/db.php` and set the database host, port, name, username, and password for your environment. The default settings are intended for a local XAMPP setup. Do not use a blank database password in production.

### 4. Run the application

Start Apache and MySQL from XAMPP, then open:

```text
http://localhost/game-portal/
```

Register an account to access the portal and play XOXO.

## Notes

- The application requires an active database connection to sign in, play XOXO, and save scores.
- Snake, Memory, and Sudoku are currently placeholders and are not playable yet.
- For production deployment, use secure database credentials and configure HTTPS.
