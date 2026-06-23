<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Link, useHttp } from '@inertiajs/vue3';
import HeatmapSnake from './HeatmapSnake.vue';
import Leaderboard from './Leaderboard.vue';
import { bestPhrases, missPhrases, pickPhrase } from '../snakePhrases';

const props = defineProps({
    leaderboard: { type: Array, default: () => [] },
});

const STORE_KEY = 'snake-404';

const board = ref([...props.leaderboard]);
const playerName = ref('');
const playerId = ref('');
const myFingerprint = ref('');
const bestScore = ref(0);
const currentScore = ref(0);

const nonce = ref(null);
const lastResult = ref(null);
const lastScore = ref(0);
const lastWasBest = ref(false);
const submitted = ref(false);
const rank = ref(null);
const feedback = ref('');

const nameLocked = ref(false);
const prompted = ref(false);
const formOpen = ref(false);
const nameInput = ref(null);

const renameOpen = ref(false);
const renameName = ref('');
const renameInput = ref(null);

const tokenHttp = useHttp({});
const scoreHttp = useHttp({ name: '', score: 0, nonce: '', player_id: '' });
const renameHttp = useHttp({ name: '', player_id: '' });

// A finished game worth recording: a new personal best backed by a live token.
const eligible = computed(() => lastResult.value !== null && lastWasBest.value && lastScore.value > 0 && !!nonce.value && !submitted.value);
// First-timers physically enter a name; returning players save silently.
const showForm = computed(() => eligible.value && !nameLocked.value && formOpen.value);
const showAddTrigger = computed(() => eligible.value && !nameLocked.value && !formOpen.value);
const showSaving = computed(() => eligible.value && nameLocked.value && scoreHttp.processing);
const showRetry = computed(() => eligible.value && nameLocked.value && !scoreHttp.processing && !!errorMessage.value);
const showBeatHint = computed(() => lastResult.value !== null && lastScore.value > 0 && !lastWasBest.value && !submitted.value);
const showBest = computed(() => bestScore.value > 0);
const canSubmit = computed(() => playerName.value.trim().length >= 1 && !scoreHttp.processing);
const canRename = computed(() => renameName.value.trim().length >= 1 && !renameHttp.processing);
const errorMessage = computed(() => Object.values(scoreHttp.errors)[0] ?? '');
const renameError = computed(() => Object.values(renameHttp.errors)[0] ?? '');

// Focus the relevant input whenever a form opens.
watch(showForm, (visible) => {
    if (visible) {
        nextTick(() => nameInput.value?.focus());
    }
});
watch(renameOpen, (visible) => {
    if (visible) {
        nextTick(() => renameInput.value?.focus());
    }
});

function onStart() {
    lastResult.value = null;
    submitted.value = false;
    rank.value = null;
    currentScore.value = 0;
    formOpen.value = false;
    renameOpen.value = false;

    tokenHttp.post('/snake/token', {
        onSuccess: (data) => {
            nonce.value = data.nonce;
        },
        onError: () => {
            nonce.value = null;
        },
    });
}

function onScore(value) {
    currentScore.value = value;
}

function onGameOver({ score, result }) {
    lastResult.value = result;
    lastScore.value = score;
    currentScore.value = score;
    lastWasBest.value = score > bestScore.value;

    if (lastWasBest.value) {
        bestScore.value = score;
        save();
        feedback.value = pickPhrase(bestPhrases, { score, best: score });
    } else if (score > 0) {
        feedback.value = pickPhrase(missPhrases, { score, best: bestScore.value });
    } else {
        feedback.value = '';
    }

    if (!lastWasBest.value || score <= 0 || !nonce.value) {
        return;
    }

    if (nameLocked.value) {
        submitScore(); // Returning player: save the new best automatically.
    } else if (!prompted.value) {
        formOpen.value = true; // First time only: ask for a name.
    }
}

function submitScore() {
    if (!canSubmit.value) {
        return;
    }

    const name = playerName.value.trim();
    scoreHttp.name = name;
    scoreHttp.score = lastScore.value;
    scoreHttp.nonce = nonce.value;
    scoreHttp.player_id = playerId.value;

    scoreHttp.post('/snake/score', {
        onSuccess: (data) => {
            applyBoard(data);
            playerName.value = name;
            submitted.value = true;
            nonce.value = null;
            nameLocked.value = true;
            prompted.value = true;
            formOpen.value = false;
            save();
        },
    });
}

function cancelPrompt() {
    prompted.value = true;
    formOpen.value = false;
    save();
}

function openPrompt() {
    formOpen.value = true;
}

function openRename() {
    renameName.value = playerName.value;
    renameOpen.value = true;
}

function renamePlayer() {
    if (!canRename.value) {
        return;
    }

    const name = renameName.value.trim();
    renameHttp.name = name;
    renameHttp.player_id = playerId.value;

    renameHttp.post('/snake/rename', {
        onSuccess: (data) => {
            applyBoard(data);
            playerName.value = name;
            renameOpen.value = false;
            save();
        },
    });
}

function applyBoard(data) {
    board.value = data.leaderboard;
    rank.value = data.rank;
    myFingerprint.value = data.fp;

    if (data.best !== null && data.best > bestScore.value) {
        bestScore.value = data.best;
    }

    save();
}

/* All persisted state lives under one key as a JSON blob. */
function save() {
    try {
        window.localStorage.setItem(STORE_KEY, JSON.stringify({
            playerId: playerId.value,
            name: playerName.value,
            best: bestScore.value,
            fingerprint: myFingerprint.value,
            prompted: prompted.value,
        }));
    } catch {
        // Ignore storage failures (private mode, disabled storage).
    }
}

function load() {
    try {
        const raw = window.localStorage.getItem(STORE_KEY);

        if (raw) {
            return JSON.parse(raw);
        }
    } catch {
        // Ignore parse/storage failures and fall through to fresh state.
    }

    return null;
}

/* A valid v4 UUID, usable outside secure contexts (Herd serves .test over http,
   where crypto.randomUUID is unavailable but getRandomValues still works). */
function uuidV4() {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }

    const bytes = new Uint8Array(16);

    if (window.crypto?.getRandomValues) {
        window.crypto.getRandomValues(bytes);
    } else {
        for (let i = 0; i < 16; i++) {
            bytes[i] = Math.floor(Math.random() * 256);
        }
    }

    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;

    const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0'));

    return `${hex.slice(0, 4).join('')}-${hex.slice(4, 6).join('')}-${hex.slice(6, 8).join('')}-${hex.slice(8, 10).join('')}-${hex.slice(10, 16).join('')}`;
}

onMounted(() => {
    const data = load() ?? {};

    playerId.value = data.playerId || uuidV4();
    playerName.value = (data.name ?? '').trim();
    nameLocked.value = playerName.value !== '';
    myFingerprint.value = data.fingerprint ?? '';
    prompted.value = !!data.prompted;

    const storedBest = Number(data.best);

    if (Number.isFinite(storedBest) && storedBest > 0) {
        bestScore.value = storedBest;
    }

    save();
});
</script>

<template>
    <div>
        <HeatmapSnake @start="onStart" @score="onScore" @gameover="onGameOver" />

        <div class="mt-9 grid gap-9 md:grid-cols-2">
            <div>
                <div class="flex items-start gap-9">
                    <div>
                        <p class="text-label uppercase text-ink-3">Score</p>
                        <p class="tnum mt-1 font-display text-stat-lg text-accent">{{ currentScore }}</p>
                    </div>
                    <div v-if="showBest">
                        <p class="text-label uppercase text-ink-3">Your best</p>
                        <p class="tnum mt-1 font-display text-stat-lg text-ink-2">{{ bestScore }}</p>
                    </div>
                </div>

                <!-- Identity line: who you're playing as, with an opt-in rename. -->
                <div v-if="nameLocked" class="mt-4">
                    <p v-if="!renameOpen" class="text-meta text-ink-3">
                        Playing as <strong class="text-ink">{{ playerName }}</strong>.
                        <button type="button" class="ml-1 underline decoration-line underline-offset-4 hover:text-ink" @click="openRename">Rename</button>
                    </p>
                    <form v-else class="flex w-full flex-col gap-2 sm:max-w-sm sm:flex-row" @submit.prevent="renamePlayer">
                        <input
                            ref="renameInput"
                            v-model="renameName"
                            type="text"
                            maxlength="20"
                            class="w-full min-w-0 rounded-md bg-surface px-3 py-2 text-base text-ink outline-none ring-accent/40 focus:ring-2 sm:flex-1"
                        />
                        <div class="flex gap-2">
                            <button type="submit" :disabled="!canRename" class="rounded-md bg-accent px-4 py-2 text-base font-semibold text-white hover:bg-accent-active disabled:opacity-50">Save</button>
                            <button type="button" class="rounded-md bg-surface px-4 py-2 text-base text-ink-3 hover:text-ink" @click="renameOpen = false">Cancel</button>
                        </div>
                    </form>
                    <p v-if="renameError" class="mt-2 text-meta text-accent">{{ renameError }}</p>
                </div>

                <!-- First time only: physically enter a name, or skip (we won't nag again). -->
                <div v-if="showForm" class="mt-6 w-full sm:max-w-sm">
                    <form @submit.prevent="submitScore">
                        <label class="block text-section font-display text-ink" for="snake-name">Who's the legend behind that score?</label>
                        <p class="mt-1 text-meta text-ink-3">Add your name once and every future best saves itself.</p>
                        <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                            <input
                                id="snake-name"
                                ref="nameInput"
                                v-model="playerName"
                                type="text"
                                maxlength="20"
                                placeholder="Your name"
                                class="w-full min-w-0 rounded-md bg-surface px-3 py-3 text-base text-ink outline-none ring-accent/40 focus:ring-2 sm:flex-1"
                            />
                            <button
                                type="submit"
                                :disabled="!canSubmit"
                                class="w-full rounded-md bg-accent px-4 py-3 text-base font-semibold text-white transition-colors hover:bg-accent-active disabled:opacity-50 sm:w-auto"
                            >
                                Add
                            </button>
                        </div>
                        <button type="button" class="mt-2 text-meta text-ink-3 underline decoration-line underline-offset-4 hover:text-ink" @click="cancelPrompt">
                            No thanks
                        </button>
                    </form>
                    <p v-if="errorMessage" class="mt-2 text-meta text-accent">{{ errorMessage }}</p>
                </div>

                <p v-else-if="showSaving" class="mt-6 text-body text-ink-3">Saving your new best...</p>

                <p v-else-if="submitted" class="mt-6 text-body text-ink-2">
                    Saved as <strong class="text-ink">{{ playerName }}</strong>.<span v-if="rank"> You're <strong class="text-accent">#{{ rank }}</strong> on the board.</span>
                </p>

                <div v-else-if="showRetry" class="mt-6">
                    <p class="text-meta text-accent">{{ errorMessage }}</p>
                    <button type="button" :disabled="scoreHttp.processing" class="mt-2 rounded-md bg-accent px-4 py-2 text-base font-semibold text-white hover:bg-accent-active disabled:opacity-50" @click="submitScore">
                        Try again
                    </button>
                </div>

                <div v-else-if="showAddTrigger" class="mt-6">
                    <p class="text-body text-ink-2">{{ feedback }}</p>
                    <button type="button" class="mt-2 text-meta font-medium text-ink underline decoration-line underline-offset-4 hover:text-ink-3" @click="openPrompt">
                        Add your name to the board
                    </button>
                </div>

                <p v-else-if="showBeatHint" class="mt-6 text-body text-ink-3">{{ feedback }}</p>
            </div>

            <div>
                <Leaderboard :entries="board" :highlight-fp="myFingerprint" />
                <Link href="/leaderboard" class="mt-4 inline-block text-meta font-medium text-ink underline decoration-line underline-offset-4 transition-colors hover:text-ink-3">
                    See the full leaderboard
                </Link>
            </div>
        </div>
    </div>
</template>
