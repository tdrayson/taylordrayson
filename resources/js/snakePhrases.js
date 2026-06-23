/**
 * Post-game feedback lines for the 404 snake. Each is a function of
 * `{ score, best }` so it can weave the numbers in. Kept deliberately dry.
 */

export const missPhrases = [
    ({ best }) => `That won't trouble your best of ${best}. Beat it to claim a spot on the board.`,
    ({ score, best }) => `${score}? Your best of ${best} didn't even flinch.`,
    ({ best }) => `Your record of ${best} remains comfortably intact.`,
    ({ score, best }) => `A ${score}. Brave. Your best is still ${best}.`,
    ({ best }) => `Nope. ${best} is the number to beat.`,
    ({ score, best }) => `${score} dots and a dream. Your best of ${best} stands.`,
    ({ best }) => `The leaderboard remains blissfully unaware. Beat ${best} first.`,
    ({ score, best }) => `Your past self scored ${best} and is, frankly, still winning.`,
    ({ best }) => `Respectfully, no. Come back when you can beat ${best}.`,
    ({ score }) => `So close to mattering. ${score} is not it.`,
    ({ best }) => `Snake one, you nil. ${best} is still safe.`,
    ({ score, best }) => `The maths (${score} versus ${best}) is not on your side.`,
    ({ best }) => `Bold effort. ${best} remains the bar, and you walked under it.`,
];

export const bestPhrases = [
    ({ score }) => `New personal best: ${score}.`,
    ({ score }) => `${score}! Now we're talking.`,
    ({ score }) => `A shiny new best of ${score}.`,
    ({ score }) => `Look at you. ${score} is your best yet.`,
    ({ score }) => `${score} and climbing. New best.`,
    ({ score }) => `Personal best: ${score}. Respectable.`,
];

/**
 * Pick a random phrase from a list and render it with the given context.
 */
export function pickPhrase(list, context) {
    const phrase = list[Math.floor(Math.random() * list.length)];

    return phrase(context);
}
