'use strict';

const board = document.getElementById('board');
const gameStatus = document.getElementById('game-status');
const saveStatus = document.getElementById('save-status');
const newGameButton = document.getElementById('new-game');
const changeSettingsButton = document.getElementById('change-settings');
const retrySaveButton = document.getElementById('retry-save');
const beginGameButton = document.getElementById('begin-game');
const setupPanel = document.getElementById('setup-panel');
const gameArea = document.getElementById('game-area');
const gameNote = document.getElementById('game-note');
const loadError = document.getElementById('load-error');
const resultDialog = document.getElementById('result-dialog');
const resultTitle = document.getElementById('result-title');
const resultMessage = document.getElementById('result-message');
const resultSaveStatus = document.getElementById('result-save-status');
const resultRetryButton = document.getElementById('result-retry');
const resultNewGameButton = document.getElementById('result-new-game');
let cellButtons = [];
const markRadios = Array.from(document.querySelectorAll('input[name="player-mark"]'));
const boardModeRadios = Array.from(document.querySelectorAll('input[name="board-mode"]'));
const difficultyRadios = Array.from(document.querySelectorAll('input[name="difficulty"]'));
const difficultyLabels = {
    easy: 'easy',
    medium: 'medium',
    hard: 'hard',
};
const difficultyMultipliers = {
    easy: 1,
    medium: 2,
    hard: 3,
};

let portalSession = null;
let cells = [];
let winningLines = [];
let cellImages = [];
let boardDimension = 3;
let winLength = 3;
let gameOver = false;
let gameStarted = false;
let computerThinking = false;
let pendingScore = null;
let computerTimer = null;
let playerMark = 'X';
let computerMark = 'O';
let difficulty = 'medium';
const markImages = {
    X: [
        '../../assets/tictactoe_images/x1.png',
        '../../assets/tictactoe_images/x2.png',
        '../../assets/tictactoe_images/x3.png',
        '../../assets/tictactoe_images/x4.png',
    ],
    O: [
        '../../assets/tictactoe_images/6.png',
        '../../assets/tictactoe_images/o5.png',
        '../../assets/tictactoe_images/o7.png',
        '../../assets/tictactoe_images/o8.png',
    ],
};
let selectedMarkImages = {
    X: markImages.X[0],
    O: markImages.O[0],
};

function chooseMarkImage(mark) {
    return selectedMarkImages[mark];
}

function markLabel(mark) {
    return `as ${mark}`;
}

function createWinningLines(size, length) {
    const lines = [];
    const directions = [[0, 1], [1, 0], [1, 1], [1, -1]];

    for (let row = 0; row < size; row += 1) {
        for (let column = 0; column < size; column += 1) {
            directions.forEach(([rowStep, columnStep]) => {
                const endRow = row + (length - 1) * rowStep;
                const endColumn = column + (length - 1) * columnStep;
                if (endRow < 0 || endRow >= size || endColumn < 0 || endColumn >= size) {
                    return;
                }

                lines.push(Array.from({ length }, (_, step) =>
                    (row + step * rowStep) * size + column + step * columnStep
                ));
            });
        }
    }

    return lines;
}

function configureBoard(size, length) {
    boardDimension = size;
    winLength = length;
    cells = Array(size * size).fill('');
    cellImages = Array(size * size).fill('');
    winningLines = createWinningLines(size, length);
    board.style.setProperty('--board-size', String(size));
    board.setAttribute('aria-label', `Tic-tac-toe board, ${size} by ${size}; connect ${length}`);
    board.replaceChildren();

    cellButtons = cells.map((_, index) => {
        const button = document.createElement('button');
        button.className = 'cell';
        button.type = 'button';
        button.dataset.cell = String(index);
        button.setAttribute('aria-label', `Cell ${index + 1}`);

        const image = document.createElement('img');
        image.className = 'cell-avatar';
        image.alt = '';

        const mark = document.createElement('span');
        mark.className = 'cell-mark';
        mark.setAttribute('aria-hidden', 'true');

        button.append(image, mark);
        button.addEventListener('click', onCellClick);
        board.append(button);
        return button;
    });
}

function getOutcome(position) {
    for (const line of winningLines) {
        const mark = position[line[0]];
        if (mark && line.every((index) => position[index] === mark)) {
            return mark;
        }
    }

    const xCanStillWin = winningLines.some((line) =>
        line.every((index) => position[index] !== 'O')
    );
    const oCanStillWin = winningLines.some((line) =>
        line.every((index) => position[index] !== 'X')
    );

    return position.every(Boolean) || (!xCanStillWin && !oCanStillWin) ? 'draw' : null;
}

function renderBoard() {
    cellButtons.forEach((button, index) => {
        const mark = cells[index];
        const image = button.querySelector('.cell-avatar');
        const symbol = button.querySelector('.cell-mark');

        symbol.textContent = '';
        image.hidden = !mark;
        if (mark && cellImages[index]) {
            image.src = cellImages[index];
        } else {
            image.removeAttribute('src');
        }
        button.classList.toggle('mark-x', mark === 'X');
        button.classList.toggle('mark-o', mark === 'O');
        button.disabled = !portalSession || !gameStarted || gameOver || computerThinking || Boolean(mark);
        button.setAttribute('aria-label', `Cell ${index + 1}${mark ? `: ${mark}` : ''}`);
    });

    newGameButton.disabled = !gameStarted || pendingScore !== null;
    changeSettingsButton.disabled = pendingScore !== null;
}

function chooseComputerMove(position) {
    const availableMoves = position
        .map((mark, index) => mark ? -1 : index)
        .filter((index) => index >= 0);

    if (difficulty === 'easy') {
        return chooseRandomMove(availableMoves);
    }

    const mistakeChance = difficulty === 'medium' ? 0.3 : 0.08;
    if (Math.random() < mistakeChance) {
        return chooseRandomMove(availableMoves);
    }

    const winningMoves = findImmediateMoves(position, computerMark);
    if (winningMoves.length > 0) {
        return chooseRandomMove(winningMoves);
    }

    const blockingMoves = findImmediateMoves(position, playerMark);
    if (blockingMoves.length > 0) {
        return chooseRandomMove(blockingMoves);
    }

    if (difficulty === 'medium') {
        return chooseStrategicMove(position, availableMoves, 2.5);
    }

    if (boardDimension > 3) {
        return chooseStrategicMove(position, availableMoves);
    }

    let bestScore = -Infinity;
    let bestMoves = [];

    position.forEach((mark, index) => {
        if (mark) {
            return;
        }

        position[index] = computerMark;
        const score = minimax(position, false);
        position[index] = '';

        if (score > bestScore) {
            bestScore = score;
            bestMoves = [index];
        } else if (score === bestScore) {
            bestMoves.push(index);
        }
    });

    return bestMoves[Math.floor(Math.random() * bestMoves.length)];
}

function chooseStrategicMove(position, availableMoves, randomness = 0) {
    let bestScore = -Infinity;
    let bestMoves = [];

    availableMoves.forEach((index) => {
        position[index] = computerMark;
        const score = evaluatePosition(position) + Math.random() * randomness;
        position[index] = '';

        if (score > bestScore) {
            bestScore = score;
            bestMoves = [index];
        } else if (score === bestScore) {
            bestMoves.push(index);
        }
    });

    return bestMoves[Math.floor(Math.random() * bestMoves.length)];
}

function chooseRandomMove(availableMoves) {
    return availableMoves[Math.floor(Math.random() * availableMoves.length)];
}

function evaluatePosition(position) {
    return winningLines.reduce((score, line) => {
        const marks = line.map((index) => position[index]);
        const computerCount = marks.filter((mark) => mark === computerMark).length;
        const playerCount = marks.filter((mark) => mark === playerMark).length;

        if (playerCount === 0) {
            return score + (computerCount ** 2);
        }
        if (computerCount === 0) {
            return score - (playerCount ** 2) * 1.2;
        }
        return score;
    }, 0);
}

function findImmediateMoves(position, mark) {
    const moves = [];

    for (let index = 0; index < position.length; index += 1) {
        if (position[index] !== '') {
            continue;
        }

        position[index] = mark;
        const outcome = getOutcome(position);
        position[index] = '';

        if (outcome === mark) {
            moves.push(index);
        }
    }

    return moves;
}

function minimax(position, isComputerTurn) {
    const outcome = getOutcome(position);
    if (outcome === computerMark) {
        return 1;
    }
    if (outcome === playerMark) {
        return -1;
    }
    if (outcome === 'draw') {
        return 0;
    }

    const scores = [];
    position.forEach((mark, index) => {
        if (mark) {
            return;
        }

        position[index] = isComputerTurn ? computerMark : playerMark;
        scores.push(minimax(position, !isComputerTurn));
        position[index] = '';
    });

    return isComputerTurn ? Math.max(...scores) : Math.min(...scores);
}

function finishGame(outcome) {
    gameOver = true;
    const baseScore = outcome === playerMark ? 3 : outcome === 'draw' ? 1 : 0;
    const boardScore = Math.ceil(baseScore * winLength / 3);
    const score = boardScore * difficultyMultipliers[difficulty];
    const pointsText = `${score} ${score === 1 ? 'point' : 'points'}`;

    if (outcome === playerMark) {
        resultTitle.textContent = 'You win!';
        resultMessage.textContent = `Nice game! You won with ${playerMark} and earned ${pointsText}.`;
        gameStatus.textContent = `You won with ${playerMark}! Nice game.`;
    } else if (outcome === 'draw') {
        resultTitle.textContent = 'It’s a draw!';
        resultMessage.textContent = `Well defended! The game ended in a draw. You earned ${pointsText}.`;
        gameStatus.textContent = 'It’s a draw. Well defended!';
    } else {
        resultTitle.textContent = 'The computer wins';
        resultMessage.textContent = `The computer won with ${computerMark}. Try again! You earned ${pointsText}.`;
        gameStatus.textContent = `The computer won with ${computerMark}. Try again!`;
    }

    pendingScore = score;
    resultSaveStatus.textContent = 'Saving score…';
    resultRetryButton.hidden = true;
    resultNewGameButton.disabled = true;
    resultDialog.showModal();
    renderBoard();
    saveResult();
}

function scheduleComputerTurn() {
    computerThinking = true;
    gameStatus.textContent = `The computer (${computerMark}) is thinking…`;
    renderBoard();

    computerTimer = window.setTimeout(() => {
        computerTimer = null;
        const computerMove = chooseComputerMove(cells);
        cells[computerMove] = computerMark;
        cellImages[computerMove] = chooseMarkImage(computerMark);
        computerThinking = false;

        const outcome = getOutcome(cells);
        if (outcome) {
            finishGame(outcome);
            return;
        }

        gameStatus.textContent = `Your turn (${playerMark}) — choose a square.`;
        renderBoard();
    }, 350);
}

function onCellClick(event) {
    const index = Number(event.currentTarget.dataset.cell);
    if (!portalSession || !gameStarted || gameOver || computerThinking || cells[index]) {
        return;
    }

    cells[index] = playerMark;
    cellImages[index] = chooseMarkImage(playerMark);
    const outcome = getOutcome(cells);
    if (outcome) {
        finishGame(outcome);
        return;
    }

    scheduleComputerTurn();
}

function startNewGame() {
    if (pendingScore !== null) {
        return;
    }

    if (computerTimer !== null) {
        window.clearTimeout(computerTimer);
        computerTimer = null;
    }

    cells.fill('');
    cellImages.fill('');
    gameOver = false;
    gameStarted = true;
    computerThinking = false;
    saveStatus.textContent = '';
    retrySaveButton.hidden = true;
    setupPanel.hidden = true;
    gameArea.hidden = false;
    gameNote.textContent =
        `You play ${markLabel(playerMark)}; the computer plays ${markLabel(computerMark)} · ${boardDimension}×${boardDimension}, connect ${winLength} · ${difficultyLabels[difficulty]} difficulty (×${difficultyMultipliers[difficulty]} points).`;
    gameStatus.textContent = `Your turn (${playerMark}) — choose a square.`;
    renderBoard();

    if (computerMark === 'X') {
        scheduleComputerTurn();
    }
}

function changeSettings() {
    if (pendingScore !== null) {
        return;
    }

    if (computerTimer !== null) {
        window.clearTimeout(computerTimer);
        computerTimer = null;
    }

    cells.fill('');
    gameOver = false;
    gameStarted = false;
    computerThinking = false;
    saveStatus.textContent = '';
    setupPanel.hidden = false;
    gameArea.hidden = true;
    gameStatus.textContent = 'Choose your mark, styles, and difficulty.';
}

function updateMarkSelection() {
    markRadios.forEach((radio) => {
        radio.closest('.mark-option').classList.toggle('is-selected', radio.checked);
    });
}

function updateDifficultySelection() {
    difficultyRadios.forEach((radio) => {
        radio.closest('.difficulty-option').classList.toggle('is-selected', radio.checked);
    });
}

function updateBoardModeSelection() {
    boardModeRadios.forEach((radio) => {
        radio.closest('.board-mode-option').classList.toggle('is-selected', radio.checked);
    });
}

function renderLeaderboard(entries) {
    const list = document.getElementById('leaderboard');
    if (!list) {
        return;
    }

    list.replaceChildren();

    if (!entries.length) {
        const empty = document.createElement('li');
        empty.className = 'empty-state';
        empty.textContent = 'No scores yet.';
        list.append(empty);
        return;
    }

    entries.forEach((entry) => {
        const item = document.createElement('li');
        const name = document.createElement('span');
        const score = document.createElement('span');
        name.className = 'leader-name';
        name.textContent = entry.username;
        score.className = 'leader-score';
        score.textContent = `${entry.score} ${entry.score === 1 ? 'point' : 'points'}`;
        item.append(name, score);
        list.append(item);
    });
}

function updateAccountStats(data) {
    document.getElementById('games-played').textContent = String(data.games_played);
    document.getElementById('total-points').textContent =
        `${new Intl.NumberFormat('en-US').format(data.total_points)} points`;
    renderLeaderboard(data.leaderboard);
}

async function requestJson(url, options) {
    const response = await fetch(url, options);
    let data;
    try {
        data = await response.json();
    } catch (error) {
        throw new Error('The server returned an invalid response.');
    }

    if (!response.ok || !data.ok) {
        throw new Error(data.error || 'The request failed.');
    }

    return data;
}

async function loadPortalData() {
    const data = await requestJson('session.php');
    portalSession = data;
    document.getElementById('player-name').textContent = data.username;
    document.getElementById('account-name').textContent = data.username;
    updateAccountStats(data);
    beginGameButton.disabled = false;
    gameStatus.textContent = 'Choose your mark, styles, and difficulty.';
}

function beginGame() {
    const selectedMark = document.querySelector('input[name="player-mark"]:checked');
    const selectedBoardMode = document.querySelector('input[name="board-mode"]:checked');
    const selectedDifficulty = document.querySelector('input[name="difficulty"]:checked');
    const selectedXStyle = document.querySelector('input[name="x-style"]:checked');
    const selectedOStyle = document.querySelector('input[name="o-style"]:checked');

    if (!selectedMark || !selectedBoardMode || !selectedDifficulty || !selectedXStyle || !selectedOStyle) {
        gameStatus.textContent = 'Game settings are unavailable.';
        return;
    }

    const boardSettings = selectedBoardMode.value === 'large'
        ? { size: 4, winLength: 4 }
        : { size: 3, winLength: 3 };
    configureBoard(boardSettings.size, boardSettings.winLength);
    playerMark = selectedMark.value;
    computerMark = playerMark === 'X' ? 'O' : 'X';
    difficulty = selectedDifficulty.value;
    selectedMarkImages = {
        X: selectedXStyle.value,
        O: selectedOStyle.value,
    };
    startNewGame();
}

async function saveResult() {
    if (!portalSession || pendingScore === null) {
        return;
    }

    retrySaveButton.hidden = true;
    saveStatus.textContent = 'Saving score…';
    resultSaveStatus.textContent = 'Saving score…';
    resultRetryButton.hidden = true;

    let data;
    try {
        data = await requestJson('../../save_score.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': portalSession.csrf_token,
            },
            body: JSON.stringify({
                game_slug: portalSession.game_slug,
                score: pendingScore,
            }),
        });
    } catch (error) {
        saveStatus.textContent = error.message;
        resultSaveStatus.textContent = `Could not save score: ${error.message}`;
        resultRetryButton.hidden = false;
        retrySaveButton.hidden = false;
        return;
    }

    pendingScore = null;
    renderBoard();
    const savedPointsText = `${data.score} ${data.score === 1 ? 'point' : 'points'}`;
    saveStatus.textContent = `Score saved (${savedPointsText}).`;
    resultSaveStatus.textContent = `Score saved (${savedPointsText}).`;
    resultNewGameButton.disabled = false;

    try {
        const refreshedData = await requestJson('session.php');
        portalSession = refreshedData;
        updateAccountStats(refreshedData);
    } catch (error) {
        saveStatus.textContent = `Score saved, but stats could not be refreshed: ${error.message}`;
    }
}

function startNewGameFromDialog() {
    if (pendingScore !== null) {
        return;
    }

    resultDialog.close();
    startNewGame();
}

configureBoard(3, 3);
markRadios.forEach((radio) => radio.addEventListener('change', updateMarkSelection));
boardModeRadios.forEach((radio) => radio.addEventListener('change', updateBoardModeSelection));
difficultyRadios.forEach((radio) => radio.addEventListener('change', updateDifficultySelection));
beginGameButton.addEventListener('click', beginGame);
newGameButton.addEventListener('click', startNewGame);
changeSettingsButton.addEventListener('click', changeSettings);
retrySaveButton.addEventListener('click', saveResult);
resultRetryButton.addEventListener('click', saveResult);
resultNewGameButton.addEventListener('click', startNewGameFromDialog);
updateMarkSelection();
updateBoardModeSelection();
updateDifficultySelection();

loadPortalData().catch((error) => {
    gameStatus.textContent = 'The game could not be started.';
    loadError.hidden = false;
    loadError.textContent = `${error.message} `;

    if (error.message.includes('sign in to play')) {
        const loginLink = document.createElement('a');
        loginLink.href = '../../screens/login.php';
        loginLink.textContent = 'Sign in to the portal.';
        loadError.append(loginLink);
    }
});
