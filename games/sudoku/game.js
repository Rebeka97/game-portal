'use strict';

const board = document.getElementById('board');
const gameStatus = document.getElementById('game-status');
const saveStatus = document.getElementById('save-status');
const setupPanel = document.getElementById('setup-panel');
const gameArea = document.getElementById('game-area');
const beginGameButton = document.getElementById('begin-game');
const newGameButton = document.getElementById('new-game');
const changeSettingsButton = document.getElementById('change-settings');
const checkBoardButton = document.getElementById('check-board');
const retrySaveButton = document.getElementById('retry-save');
const loadError = document.getElementById('load-error');
const resultDialog = document.getElementById('result-dialog');
const resultMessage = document.getElementById('result-message');
const resultSaveStatus = document.getElementById('result-save-status');
const resultRetryButton = document.getElementById('result-retry');
const resultNewGameButton = document.getElementById('result-new-game');
const difficultyRadios = Array.from(document.querySelectorAll('input[name="difficulty"]'));
const themeRadios = Array.from(document.querySelectorAll('input[name="board-theme"]'));
const numberButtons = Array.from(document.querySelectorAll('[data-number]'));
const clearButton = document.querySelector('[data-clear]');
const difficultySettings = {
    easy: { label: 'Easy', blanks: 30, points: 20, bonusLimit: 180 },
    medium: { label: 'Medium', blanks: 38, points: 30, bonusLimit: 360 },
    hard: { label: 'Hard', blanks: 44, points: 45, bonusLimit: 600 },
};

let portalSession = null;
let puzzle = [];
let solution = [];
let entries = [];
let cellButtons = [];
let selectedCell = -1;
let currentDifficulty = 'medium';
let currentTheme = 'ocean';
let startedAt = 0;
let elapsedSeconds = 0;
let timerId = null;
let gameFinished = false;
let isGenerating = false;
let pendingScore = null;
let currentScore = 0;

function shuffle(values) {
    const shuffled = values.slice();
    for (let index = shuffled.length - 1; index > 0; index -= 1) {
        const swapIndex = Math.floor(Math.random() * (index + 1));
        [shuffled[index], shuffled[swapIndex]] = [shuffled[swapIndex], shuffled[index]];
    }
    return shuffled;
}

function createSolvedGrid() {
    const bands = shuffle([0, 1, 2]);
    const rows = bands.flatMap((band) =>
        shuffle([0, 1, 2]).map((row) => band * 3 + row)
    );
    const stacks = shuffle([0, 1, 2]);
    const columns = stacks.flatMap((stack) =>
        shuffle([0, 1, 2]).map((column) => stack * 3 + column)
    );
    const digits = shuffle([1, 2, 3, 4, 5, 6, 7, 8, 9]);
    const grid = [];

    for (let row = 0; row < 9; row += 1) {
        for (let column = 0; column < 9; column += 1) {
            const patternIndex = (rows[row] * 3 + Math.floor(rows[row] / 3) + columns[column]) % 9;
            grid.push(digits[patternIndex]);
        }
    }

    if (Math.random() < 0.5) {
        for (let row = 0; row < 9; row += 1) {
            for (let column = row + 1; column < 9; column += 1) {
                const first = row * 9 + column;
                const second = column * 9 + row;
                [grid[first], grid[second]] = [grid[second], grid[first]];
            }
        }
    }

    return grid;
}

function candidatesFor(grid, index) {
    const row = Math.floor(index / 9);
    const column = index % 9;
    const used = new Set();

    for (let offset = 0; offset < 9; offset += 1) {
        used.add(grid[row * 9 + offset]);
        used.add(grid[offset * 9 + column]);
    }

    const boxRow = Math.floor(row / 3) * 3;
    const boxColumn = Math.floor(column / 3) * 3;
    for (let boxOffset = 0; boxOffset < 9; boxOffset += 1) {
        const boxIndex = (boxRow + Math.floor(boxOffset / 3)) * 9
            + boxColumn + (boxOffset % 3);
        used.add(grid[boxIndex]);
    }

    return [1, 2, 3, 4, 5, 6, 7, 8, 9].filter((value) => !used.has(value));
}

function countSolutions(grid, limit = 2) {
    let solutionsFound = 0;

    function search() {
        let bestIndex = -1;
        let bestCandidates = null;

        for (let index = 0; index < 81; index += 1) {
            if (grid[index] !== 0) {
                continue;
            }

            const candidates = candidatesFor(grid, index);
            if (candidates.length === 0) {
                return;
            }
            if (bestCandidates === null || candidates.length < bestCandidates.length) {
                bestIndex = index;
                bestCandidates = candidates;
                if (candidates.length === 1) {
                    break;
                }
            }
        }

        if (bestIndex === -1) {
            solutionsFound += 1;
            return;
        }

        for (const candidate of bestCandidates) {
            grid[bestIndex] = candidate;
            search();
            grid[bestIndex] = 0;
            if (solutionsFound >= limit) {
                return;
            }
        }
    }

    search();
    return solutionsFound;
}

function generatePuzzle(difficulty) {
    const targetBlanks = difficultySettings[difficulty].blanks;
    let bestPuzzle = null;
    let bestSolution = null;
    let bestBlankCount = -1;

    for (let attempt = 0; attempt < 5; attempt += 1) {
        const solvedGrid = createSolvedGrid();
        const candidatePuzzle = solvedGrid.slice();
        let blankCount = 0;

        for (const index of shuffle(Array.from({ length: 81 }, (_, cellIndex) => cellIndex))) {
            const originalValue = candidatePuzzle[index];
            candidatePuzzle[index] = 0;

            if (countSolutions(candidatePuzzle) !== 1) {
                candidatePuzzle[index] = originalValue;
                continue;
            }

            blankCount += 1;
            if (blankCount >= targetBlanks) {
                break;
            }
        }

        if (blankCount > bestBlankCount) {
            bestBlankCount = blankCount;
            bestPuzzle = candidatePuzzle.slice();
            bestSolution = solvedGrid;
        }
        if (blankCount >= targetBlanks) {
            break;
        }
    }

    return { puzzle: bestPuzzle, solution: bestSolution };
}

function formatTime(seconds) {
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    return `${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
}

function updateTimer() {
    elapsedSeconds = Math.floor((Date.now() - startedAt) / 1000);
    document.getElementById('timer').textContent = formatTime(elapsedSeconds);
}

function buildBoard() {
    board.replaceChildren();
    cellButtons = Array.from({ length: 81 }, (_, index) => {
        const button = document.createElement('button');
        button.className = 'sudoku-cell';
        button.type = 'button';
        button.setAttribute('role', 'gridcell');
        button.dataset.index = String(index);
        button.addEventListener('click', () => selectCell(index));
        board.append(button);
        return button;
    });
}

function findConflicts() {
    const conflicts = new Set();

    function checkGroup(indices) {
        const seen = new Map();
        indices.forEach((index) => {
            const value = entries[index];
            if (value === 0) {
                return;
            }
            if (seen.has(value)) {
                conflicts.add(index);
                conflicts.add(seen.get(value));
            } else {
                seen.set(value, index);
            }
        });
    }

    for (let row = 0; row < 9; row += 1) {
        checkGroup(Array.from({ length: 9 }, (_, column) => row * 9 + column));
        checkGroup(Array.from({ length: 9 }, (_, column) => column * 9 + row));
    }

    for (let boxRow = 0; boxRow < 3; boxRow += 1) {
        for (let boxColumn = 0; boxColumn < 3; boxColumn += 1) {
            const box = [];
            for (let row = 0; row < 3; row += 1) {
                for (let column = 0; column < 3; column += 1) {
                    box.push((boxRow * 3 + row) * 9 + boxColumn * 3 + column);
                }
            }
            checkGroup(box);
        }
    }

    return conflicts;
}

function renderBoard() {
    const conflicts = findConflicts();
    const selectedValue = entries[selectedCell] || 0;
    const selectedRow = Math.floor(selectedCell / 9);
    const selectedColumn = selectedCell % 9;

    cellButtons.forEach((button, index) => {
        const row = Math.floor(index / 9);
        const column = index % 9;
        const value = entries[index];
        const related = selectedCell >= 0
            && (row === selectedRow
                || column === selectedColumn
                || (Math.floor(row / 3) === Math.floor(selectedRow / 3)
                    && Math.floor(column / 3) === Math.floor(selectedColumn / 3)));

        button.className = 'sudoku-cell';
        button.textContent = value === 0 ? '' : String(value);
        button.setAttribute('aria-label', `Row ${row + 1}, column ${column + 1}, ${value || 'empty'}${puzzle[index] ? ', given' : ''}`);
        button.disabled = gameFinished;

        if (puzzle[index] !== 0) {
            button.classList.add('is-given');
        }
        if (related) {
            button.classList.add('is-related');
        }
        if (selectedCell === index) {
            button.classList.add('is-selected');
        }
        if (currentDifficulty === 'easy' && selectedValue !== 0 && value === selectedValue) {
            button.classList.add('is-same-number');
        }
        if (conflicts.has(index)) {
            button.classList.add('is-error');
        }
    });
}

function selectCell(index) {
    if (gameFinished || entries.length !== 81) {
        return;
    }
    selectedCell = index;
    renderBoard();
}

function isSolved() {
    return entries.length === 81
        && entries.every((value, index) => value === solution[index]);
}

function updateProgressStatus() {
    if (isSolved()) {
        finishGame();
        return;
    }

    const conflicts = findConflicts();
    if (conflicts.size > 0) {
        gameStatus.textContent = 'Some numbers conflict in a row, column, or 3×3 box. Check the highlighted cells.';
    } else if (entries.every((value) => value !== 0)) {
        gameStatus.textContent = 'The grid is full, but some numbers are incorrect. Keep trying.';
    } else {
        gameStatus.textContent = 'No conflicts so far. Keep going!';
    }
}

function enterNumber(value) {
    if (gameFinished || selectedCell < 0 || puzzle[selectedCell] !== 0) {
        return;
    }
    entries[selectedCell] = value;
    renderBoard();
    updateProgressStatus();
}

function eraseSelectedCell() {
    if (gameFinished || selectedCell < 0 || puzzle[selectedCell] !== 0) {
        return;
    }
    entries[selectedCell] = 0;
    renderBoard();
    gameStatus.textContent = 'Cell cleared.';
}

function renderLeaderboard(entriesList) {
    const list = document.getElementById('leaderboard');
    list.replaceChildren();

    if (!entriesList.length) {
        const empty = document.createElement('li');
        empty.className = 'empty-state';
        empty.textContent = 'No scores yet.';
        list.append(empty);
        return;
    }

    entriesList.forEach((entry) => {
        const item = document.createElement('li');
        const row = document.createElement('div');
        const name = document.createElement('span');
        const score = document.createElement('span');
        row.className = 'leader-entry';
        name.textContent = entry.username;
        score.className = 'leader-score';
        score.textContent = `${entry.score} ${entry.score === 1 ? 'point' : 'points'}`;
        row.append(name, score);
        item.append(row);
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
    gameStatus.textContent = 'Choose a difficulty and board color to begin.';
}

function updateDifficultySelection() {
    difficultyRadios.forEach((radio) => {
        radio.closest('.difficulty-option').classList.toggle('is-selected', radio.checked);
    });
}

function updateThemeSelection() {
    themeRadios.forEach((radio) => {
        radio.closest('.theme-option').classList.toggle('is-selected', radio.checked);
    });
}

function clearTimer() {
    if (timerId !== null) {
        window.clearInterval(timerId);
        timerId = null;
    }
}

async function startPuzzle(difficulty = currentDifficulty, theme = currentTheme) {
    if (!portalSession || isGenerating || pendingScore !== null) {
        return;
    }

    isGenerating = true;
    clearTimer();
    gameFinished = false;
    currentDifficulty = difficulty;
    currentTheme = theme;
    saveStatus.textContent = '';
    retrySaveButton.hidden = true;
    gameStatus.textContent = 'Generating a unique Sudoku puzzle…';
    await new Promise((resolve) => window.setTimeout(resolve, 30));

    try {
        const generated = generatePuzzle(currentDifficulty);
        puzzle = generated.puzzle;
        solution = generated.solution;
        entries = puzzle.slice();
        selectedCell = entries.findIndex((value) => value === 0);
        buildBoard();
        board.dataset.theme = currentTheme;
        setupPanel.hidden = true;
        gameArea.hidden = false;
        const settings = difficultySettings[currentDifficulty];
        document.getElementById('difficulty-label').textContent = `${settings.label} difficulty`;
        document.getElementById('score-label').textContent =
            `${settings.points} points · +10 bonus within ${formatTime(settings.bonusLimit)}`;
        document.getElementById('game-note').textContent =
            `Complete the grid correctly to earn ${settings.points} points. Finish within ${formatTime(settings.bonusLimit)} for 10 bonus points. Use the number buttons or your keyboard.`;
        startedAt = Date.now();
        elapsedSeconds = 0;
        document.getElementById('timer').textContent = '00:00';
        timerId = window.setInterval(updateTimer, 1000);
        gameStatus.textContent = 'Select an empty cell and enter a number from 1 to 9.';
        renderBoard();
    } catch (error) {
        gameStatus.textContent = `Could not generate the puzzle: ${error.message}`;
        gameArea.hidden = true;
        setupPanel.hidden = false;
    } finally {
        isGenerating = false;
    }
}

function beginGame() {
    const selectedDifficulty = document.querySelector('input[name="difficulty"]:checked');
    const selectedTheme = document.querySelector('input[name="board-theme"]:checked');
    if (!selectedDifficulty || !selectedTheme) {
        gameStatus.textContent = 'Choose a difficulty and board color.';
        return;
    }
    startPuzzle(selectedDifficulty.value, selectedTheme.value);
}

function checkBoard() {
    if (gameFinished || entries.length !== 81) {
        return;
    }
    renderBoard();
    updateProgressStatus();
}

function finishGame() {
    if (gameFinished) {
        return;
    }
    gameFinished = true;
    clearTimer();
    updateTimer();

    const settings = difficultySettings[currentDifficulty];
    const earnedBonus = elapsedSeconds <= settings.bonusLimit;
    currentScore = settings.points + (earnedBonus ? 10 : 0);
    pendingScore = currentScore;
    resultMessage.textContent = earnedBonus
        ? `Solved in ${formatTime(elapsedSeconds)}! You earned ${settings.points} base points and a 10-point time bonus: ${currentScore} points total.`
        : `Solved in ${formatTime(elapsedSeconds)}. You earned ${currentScore} points. Finish within ${formatTime(settings.bonusLimit)} next time for a 10-point bonus.`;
    resultSaveStatus.textContent = 'Saving score…';
    resultRetryButton.hidden = true;
    resultNewGameButton.disabled = true;
    resultDialog.showModal();
    renderBoard();
    saveResult();
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
        retrySaveButton.hidden = false;
        resultRetryButton.hidden = false;
        return;
    }

    pendingScore = null;
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

function startNewPuzzleFromDialog() {
    if (pendingScore !== null) {
        return;
    }
    resultDialog.close();
    startPuzzle();
}

function changeSettings() {
    if (pendingScore !== null || isGenerating) {
        return;
    }
    clearTimer();
    gameFinished = false;
    saveStatus.textContent = '';
    setupPanel.hidden = false;
    gameArea.hidden = true;
    gameStatus.textContent = 'Choose a difficulty and board color to begin.';
}

function handleKeyDown(event) {
    if (gameArea.hidden || gameFinished || selectedCell < 0) {
        return;
    }

    if (/^[1-9]$/.test(event.key)) {
        event.preventDefault();
        enterNumber(Number(event.key));
        return;
    }
    if (event.key === 'Backspace' || event.key === 'Delete') {
        event.preventDefault();
        eraseSelectedCell();
        return;
    }

    const row = Math.floor(selectedCell / 9);
    const column = selectedCell % 9;
    const moves = {
        ArrowUp: row > 0 ? selectedCell - 9 : selectedCell,
        ArrowDown: row < 8 ? selectedCell + 9 : selectedCell,
        ArrowLeft: column > 0 ? selectedCell - 1 : selectedCell,
        ArrowRight: column < 8 ? selectedCell + 1 : selectedCell,
    };
    if (Object.hasOwn(moves, event.key)) {
        event.preventDefault();
        selectCell(moves[event.key]);
    }
}

buildBoard();
difficultyRadios.forEach((radio) => radio.addEventListener('change', updateDifficultySelection));
themeRadios.forEach((radio) => radio.addEventListener('change', updateThemeSelection));
numberButtons.forEach((button) => {
    button.addEventListener('click', () => enterNumber(Number(button.dataset.number)));
});
clearButton.addEventListener('click', eraseSelectedCell);
beginGameButton.addEventListener('click', beginGame);
newGameButton.addEventListener('click', () => startPuzzle());
changeSettingsButton.addEventListener('click', changeSettings);
checkBoardButton.addEventListener('click', checkBoard);
retrySaveButton.addEventListener('click', saveResult);
resultRetryButton.addEventListener('click', saveResult);
resultNewGameButton.addEventListener('click', startNewPuzzleFromDialog);
window.addEventListener('keydown', handleKeyDown);
updateDifficultySelection();
updateThemeSelection();

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
