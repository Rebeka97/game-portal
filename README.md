# Game Portal

Welcome to the **Game Portal** repository! This project is a browser-based gaming portal built with PHP, MySQL, JavaScript, and CSS. Players can create an account, choose an avatar, play games, and track their scores.

**XOXO** (tic-tac-toe) and **Sudoku** are currently playable. Snake and Memory are listed as upcoming games.

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

### Sudoku
- Solve randomly generated **9×9** puzzles using the standard row, column, and 3×3 box rules.
- Choose Easy, Medium, or Hard difficulty before starting a puzzle.
- Select from five board color themes: Ocean blue, Mint, Violet, Rose, and Amber.
- Easy highlights matching numbers as a hint; Medium and Hard do not.
- Earn 20 points on Easy, 30 on Medium, or 45 on Hard. Earn an additional 10-point speed bonus for solving Easy within 3 minutes, Medium within 6 minutes, or Hard within 10 minutes.
- View your Sudoku stats and leaderboard. Solved-puzzle scores are saved to your account.

### XOXO Scores & Difficulty
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
│   ├── tictactoe/          # XOXO game interface, styles, logic, and session API
│   └── sudoku/             # Sudoku interface, styles, logic, and session API
├── screens/                # Login, registration, profile, and play pages
├── index.php               # Main portal dashboard
├── save_score.php          # Score-saving endpoint
└── login.php, register.php,
    profile.php, play.php   # Root-level page entry points
