<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Snake played on a contribution-heatmap grid. The dark snake leaves accent
 * "contributions" behind: each pellet (accent) it eats stays lit in the same
 * colour, so the board fills in like a busy week. One point per square.
 *
 * Pure game component. It owns play state and emits `start`, `score` (live)
 * and `gameover` so a parent can drive the leaderboard.
 */
const COLUMNS = 20;
const ROWS = 12;
const BASE_SPEED = 200;
const MIN_SPEED = 80;
const SPEED_STEP = 4;

const emit = defineEmits(['start', 'score', 'gameover']);

const DIRECTIONS = {
    up: { x: 0, y: -1 },
    down: { x: 0, y: 1 },
    left: { x: -1, y: 0 },
    right: { x: 1, y: 0 },
};

const KEY_MAP = {
    ArrowUp: 'up', ArrowDown: 'down', ArrowLeft: 'left', ArrowRight: 'right',
    w: 'up', s: 'down', a: 'left', d: 'right',
    W: 'up', S: 'down', A: 'left', D: 'right',
};

const snake = ref([]);
const filled = ref({});
const pellet = ref(null);
const score = ref(0);
const state = ref('idle');

let direction = DIRECTIONS.right;
let nextDirection = DIRECTIONS.right;
let timer = null;

// Brief lockout after a game ends so a stray key press or swipe doesn't fire
// straight into a new game before the player has registered the loss.
const RESTART_COOLDOWN = 800;
const startReady = ref(true);
let cooldownTimer = null;

const cellKey = (x, y) => `${x},${y}`;

const snakeBody = computed(() => new Set(snake.value.slice(1).map((cell) => cellKey(cell.x, cell.y))));
const headKey = computed(() => (snake.value[0] ? cellKey(snake.value[0].x, snake.value[0].y) : null));

const rows = Array.from({ length: ROWS }, (_, y) => y);
const cols = Array.from({ length: COLUMNS }, (_, x) => x);

function cellBackground(x, y) {
    const key = cellKey(x, y);

    if (headKey.value === key) {
        return 'var(--color-ink)';
    }

    if (snakeBody.value.has(key)) {
        return 'var(--color-ink-2)';
    }

    if (filled.value[key]) {
        return 'var(--color-heat-2)';
    }

    return 'var(--color-surface)';
}

function isPellet(x, y) {
    return pellet.value !== null && pellet.value.x === x && pellet.value.y === y;
}

function spawnPellet(currentSnake) {
    const occupied = new Set(currentSnake.map((cell) => cellKey(cell.x, cell.y)));
    const candidates = [];

    for (let y = 0; y < ROWS; y++) {
        for (let x = 0; x < COLUMNS; x++) {
            const key = cellKey(x, y);

            if (!occupied.has(key) && filled.value[key] === undefined) {
                candidates.push({ x, y });
            }
        }
    }

    if (candidates.length === 0) {
        return null;
    }

    return candidates[Math.floor(Math.random() * candidates.length)];
}

function scheduleTick() {
    const speed = Math.max(MIN_SPEED, BASE_SPEED - score.value * SPEED_STEP);
    timer = window.setTimeout(tick, speed);
}

function tick() {
    if (state.value !== 'running') {
        return;
    }

    direction = nextDirection;

    const head = snake.value[0];
    const next = { x: head.x + direction.x, y: head.y + direction.y };

    const ate = pellet.value && next.x === pellet.value.x && next.y === pellet.value.y;
    const bodyToCheck = ate ? snake.value : snake.value.slice(0, -1);
    const hitsSelf = bodyToCheck.some((cell) => cell.x === next.x && cell.y === next.y);
    const hitsWall = next.x < 0 || next.x >= COLUMNS || next.y < 0 || next.y >= ROWS;

    if (hitsWall || hitsSelf) {
        endGame('over');

        return;
    }

    const grown = [next, ...snake.value];

    if (ate) {
        filled.value = { ...filled.value, [cellKey(pellet.value.x, pellet.value.y)]: true };
        score.value += 1;
        emit('score', score.value);

        const nextPellet = spawnPellet(grown);

        if (nextPellet === null) {
            snake.value = grown;
            endGame('won');

            return;
        }

        pellet.value = nextPellet;
    } else {
        grown.pop();
    }

    snake.value = grown;
    scheduleTick();
}

function startGame() {
    if (!startReady.value) {
        return;
    }

    const midRow = Math.floor(ROWS / 2);
    snake.value = [
        { x: 5, y: midRow },
        { x: 4, y: midRow },
        { x: 3, y: midRow },
    ];
    direction = DIRECTIONS.right;
    nextDirection = DIRECTIONS.right;
    filled.value = {};
    score.value = 0;
    pellet.value = spawnPellet(snake.value);
    state.value = 'running';
    emit('start');
    emit('score', 0);
    scheduleTick();
}

function endGame(result) {
    if (timer) {
        window.clearTimeout(timer);
        timer = null;
    }

    state.value = result;
    startReady.value = false;
    cooldownTimer = window.setTimeout(() => {
        startReady.value = true;
    }, RESTART_COOLDOWN);

    emit('gameover', { score: score.value, result });
}

function turn(name) {
    const candidate = DIRECTIONS[name];
    const isReverse = candidate.x === -direction.x && candidate.y === -direction.y;

    if (!isReverse) {
        nextDirection = candidate;
    }
}

function handleKeydown(event) {
    const target = event.target;

    if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable)) {
        return;
    }

    if (event.key === ' ' || event.key === 'Enter') {
        if (state.value !== 'running') {
            event.preventDefault();
            startGame();
        }

        return;
    }

    const name = KEY_MAP[event.key];

    if (!name) {
        return;
    }

    event.preventDefault();

    if (state.value !== 'running') {
        startGame();
    }

    turn(name);
}

const touchStart = ref(null);

function handleTouchStart(event) {
    const touch = event.changedTouches[0];
    touchStart.value = { x: touch.clientX, y: touch.clientY };
}

/* While a game is running, swipes anywhere on the page steer the snake, and we
   block the default scroll so the gesture isn't fighting the page. */
function handleTouchMove(event) {
    if (state.value === 'running' && event.cancelable) {
        event.preventDefault();
    }
}

function handleTouchEnd(event) {
    if (state.value !== 'running' || !touchStart.value) {
        touchStart.value = null;

        return;
    }

    const touch = event.changedTouches[0];
    const deltaX = touch.clientX - touchStart.value.x;
    const deltaY = touch.clientY - touchStart.value.y;
    touchStart.value = null;

    if (Math.abs(deltaX) < 16 && Math.abs(deltaY) < 16) {
        return;
    }

    if (Math.abs(deltaX) > Math.abs(deltaY)) {
        turn(deltaX > 0 ? 'right' : 'left');
    } else {
        turn(deltaY > 0 ? 'down' : 'up');
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('touchstart', handleTouchStart, { passive: true });
    window.addEventListener('touchmove', handleTouchMove, { passive: false });
    window.addEventListener('touchend', handleTouchEnd, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('touchstart', handleTouchStart);
    window.removeEventListener('touchmove', handleTouchMove);
    window.removeEventListener('touchend', handleTouchEnd);

    if (timer) {
        window.clearTimeout(timer);
    }

    if (cooldownTimer) {
        window.clearTimeout(cooldownTimer);
    }
});
</script>

<template>
    <div class="snake-board">
        <div class="snake-grid" :style="{ '--cols': COLUMNS }">
            <template v-for="y in rows" :key="`row-${y}`">
                <span
                    v-for="x in cols"
                    :key="`${x}-${y}`"
                    class="rounded"
                    :class="{ pellet: isPellet(x, y) }"
                    :style="{ background: cellBackground(x, y) }"
                />
            </template>
        </div>

        <button
            v-if="state !== 'running'"
            type="button"
            class="snake-overlay"
            @click="startGame"
        >
            <span v-if="state === 'idle'" class="snake-prompt">
                <span class="font-display text-section">Fill in your day</span>
                <span class="text-meta text-ink-3">Arrow keys, WASD, or tap to play</span>
            </span>
            <span v-else-if="state === 'over'" class="snake-prompt">
                <span class="font-display text-section">Game over</span>
                <span class="text-meta text-ink-3">You logged {{ score }} {{ score === 1 ? 'day' : 'days' }} · tap to retry</span>
            </span>
            <span v-else class="snake-prompt">
                <span class="font-display text-section">Full house! 🎉</span>
                <span class="text-meta text-ink-3">You filled the entire log · tap to play again</span>
            </span>
        </button>
    </div>
</template>

<style scoped>
.snake-board {
    position: relative;
}

/* Square cells in a fixed column grid, echoing the Year contribution heatmap. */
.snake-grid {
    display: grid;
    grid-template-columns: repeat(var(--cols), 1fr);
    gap: 3px;
}

/* On small screens, break the board out to the full viewport width and tighten
   the gap so each square is a little bigger and easier to read mid-game. */
@media (max-width: 639px) {
    .snake-board {
        width: 100vw;
        max-width: none;
        margin-left: calc(50% - 50vw);
    }

    .snake-grid {
        gap: 2px;
        padding-inline: 8px;
    }
}

.snake-grid > span {
    aspect-ratio: 1;
    transition: background-color 0.12s linear;
}

.snake-grid > span.pellet {
    background: var(--color-accent) !important;
    animation: pellet-pulse 0.9s ease-in-out infinite;
}

@keyframes pellet-pulse {
    0%, 100% {
        transform: scale(0.7);
        opacity: 0.85;
    }

    50% {
        transform: scale(1);
        opacity: 1;
    }
}

/* Low-opacity scrim so the board stays visible behind the prompt. */
.snake-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-md);
    background: rgba(255, 255, 255, 0.42);
    backdrop-filter: blur(1px);
    text-align: center;
}

.snake-prompt {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 10px 24px;
    border-radius: var(--radius-md);
    background: rgba(255, 255, 255, 0.82);
}

@media (prefers-reduced-motion: reduce) {
    .snake-grid > span.pellet {
        animation: none;
    }
}
</style>
