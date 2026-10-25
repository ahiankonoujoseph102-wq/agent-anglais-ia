// Appels de l'outil des vidéos de présentation à Talkami (tools/presentation/talkami-api.mjs) : nouveaux essais
// après une réponse 5xx ou une coupure, raison gardée, aucun essai inutile après un refus définitif.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { call, retryable } from '../../tools/presentation/talkami-api.mjs';

const reply = (status, body = {}) => ({ ok: status >= 200 && status < 300, status, url: 'https://talkami.example/api/presentations/11/prete', text: async () => JSON.stringify(body) });
const noWait = { sleep: async () => {}, log: () => {} };

test('un 503 passager est réessayé, puis la vidéo est bien déclarée prête', async () => {
    const answers = [reply(503, { message: 'Service indisponible' }), reply(200, { ready: true })];
    const waits = [];
    const response = await call('u', {}, { ...noWait, sleep: async (ms) => waits.push(ms), fetch: async () => answers.shift() });
    assert.equal(response.status, 200);
    assert.deepEqual(waits, [5000]);
});

test('une coupure du réseau est réessayée', async () => {
    let calls = 0;
    const response = await call('u', {}, { ...noWait, fetch: async () => (++calls === 1 ? Promise.reject(new Error('socket hang up')) : reply(200)) });
    assert.equal(response.status, 200);
    assert.equal(calls, 2);
});

test('« déjà prête » (409) au deuxième essai compte comme une réussite, jamais au premier', async () => {
    const answers = [reply(502), reply(409, { message: "Cette vidéo n'est pas en fabrication." })];
    assert.equal((await call('u', {}, { ...noWait, acceptOnRetry: [409], fetch: async () => answers.shift() })).status, 409);
    await assert.rejects(call('u', {}, { ...noWait, acceptOnRetry: [409], fetch: async () => reply(409) }), /409/);
});

test('un refus définitif n\'est pas réessayé ; après trois essais, la raison de Talkami est donnée', async () => {
    let calls = 0;
    await assert.rejects(call('u', {}, { ...noWait, fetch: async () => { calls++; return reply(401, { message: 'Jeton refusé.' }); } }), /401 \(Jeton refusé\.\) pour \/api\/presentations\/11\/prete/);
    assert.equal(calls, 1);

    calls = 0;
    await assert.rejects(call('u', {}, { ...noWait, fetch: async () => { calls++; return reply(503, { message: 'Jeton des vidéos non réglé (VIDEO_UPLOAD_TOKEN).' }); } }), /503 \(Jeton des vidéos non réglé/);
    assert.equal(calls, 4);
    assert.ok(retryable(500) && retryable(429) && retryable(0) && !retryable(404) && !retryable(422));
});
