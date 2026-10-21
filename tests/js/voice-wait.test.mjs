// Message vocal pas encore prêt : la page redemande son état et le joue dès qu'il est prêt.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ReadyWatch, silentWav } from '../../public/js/voice-wait.js';

function harness(answers) {
    let clock = 0;
    const timers = [];
    const events = [];
    const watch = new ReadyWatch({
        check: async () => {
            const answer = answers.shift();

            if (answer instanceof Error) {
                throw answer;
            }

            return answer;
        },
        pollMs: 3000,
        lateMs: 30000,
        giveUpMs: 60000,
        onReady: () => events.push(['ready', clock]),
        onLate: () => events.push(['late', clock]),
        onGiveUp: () => events.push(['give-up', clock]),
        schedule: (fn, ms) => timers.push({ fn, at: clock + ms }),
        now: () => clock,
    });

    const run = async () => {
        await new Promise((resolve) => setImmediate(resolve));

        while (timers.length) {
            const timer = timers.shift();
            clock = timer.at;
            timer.fn();
            await new Promise((resolve) => setImmediate(resolve));
        }
    };

    return { watch, events, run };
}

test('prêt tout de suite : une seule demande, « prêt » aussitôt', async () => {
    const { watch, events, run } = harness([true]);
    watch.start();
    await run();
    assert.deepEqual(events, [['ready', 0]]);
});

test('pas encore prêt : redemande toutes les 3 secondes, puis prêt sans nouveau toucher', async () => {
    const { watch, events, run } = harness([false, false, new Error('réseau'), true]);
    watch.start();
    watch.start();
    await run();
    assert.deepEqual(events, [['ready', 9000]]);
});

test('toujours pas prêt après 30 secondes : « Le message arrive… » une fois, puis abandon', async () => {
    const { watch, events, run } = harness(Array(100).fill(false));
    watch.start();
    await run();
    assert.deepEqual(events, [['late', 30000], ['give-up', 60000]]);
});

test('un court silence en WAV pour déverrouiller le lecteur', () => {
    const bytes = silentWav(10);
    assert.equal(bytes.length, 64);
    assert.equal(String.fromCharCode(...bytes.slice(0, 4)), 'RIFF');
    assert.equal(String.fromCharCode(...bytes.slice(8, 12)), 'WAVE');
    assert.ok(bytes.slice(44).every((byte) => byte === 0));
});

test('réglages par défaut : minuterie et horloge du navigateur utilisables comme méthodes', async () => {
    let ready = false;
    const watch = new ReadyWatch({ check: async () => false, pollMs: 5, onReady: () => { ready = true; } });
    watch.start();
    await new Promise((resolve) => setTimeout(resolve, 20));
    watch.check = async () => true;
    await new Promise((resolve) => setTimeout(resolve, 30));
    assert.equal(ready, true);
    // Comme dans le navigateur : setTimeout appelé avec un autre « this » serait refusé.
    assert.notEqual(watch.schedule, setTimeout);
    assert.notEqual(watch.now, Date.now);
});
