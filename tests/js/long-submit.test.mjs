// Envois longs de « Mes cours » : messages clairs pour chaque limite (fichier trop lourd, délai, réseau, panne).
import test from 'node:test';
import assert from 'node:assert/strict';
import { MESSAGES, messageFor, firstError, send } from '../../public/js/long-submit.js';

const form = { action: '/mes-cours' };
const reply = (status, data) => async () => ({ ok: status >= 200 && status < 300, status, json: async () => data });

test('chaque limite a son message clair', () => {
    assert.equal(messageFor(413), MESSAGES.tooLarge);
    assert.match(MESSAGES.tooLarge, /trop lourd pour être envoyé : envoyez seulement les pages utiles/);
    assert.equal(messageFor(504), MESSAGES.timeout);
    assert.equal(messageFor(0, true), MESSAGES.timeout);
    assert.equal(messageFor(0), MESSAGES.network);
    assert.equal(messageFor(502), MESSAGES.server);
    assert.equal(messageFor(419), MESSAGES.expired);
    assert.equal(messageFor(429), MESSAGES.tooMany);
    assert.equal(messageFor(422), null);
});

test('réussite : l\'adresse où aller', async () => {
    assert.deepEqual(await send(form, { body: 'formulaire', fetchImpl: reply(200, { redirect: '/mes-cours/4#partie' }) }), { redirect: '/mes-cours/4#partie' });
});

test('erreur de validation : le premier message du serveur', async () => {
    const result = await send(form, { body: 'formulaire', fetchImpl: reply(422, { message: 'x', errors: { pdf: ['Ce PDF a 60 pages : 50 pages au plus.'] } }) });
    assert.equal(result.error, 'Ce PDF a 60 pages : 50 pages au plus.');
    assert.equal(firstError({ errors: {} }), null);
});

test('fichier refusé par le serveur ou le proxy (413), sans JSON', async () => {
    const result = await send(form, { body: 'formulaire', fetchImpl: async () => ({ ok: false, status: 413, json: async () => { throw new Error('HTML'); } }) });
    assert.equal(result.error, MESSAGES.tooLarge);
});

test('attente trop longue : abandon avec le message du délai, jamais une attente sans fin', async () => {
    const never = (url, { signal }) => new Promise((resolve, reject) => signal.addEventListener('abort', () => reject(new Error('abort'))));
    const result = await send(form, { body: 'formulaire', timeoutMs: 20, fetchImpl: never });
    assert.equal(result.error, MESSAGES.timeout);
});

test('réseau coupé', async () => {
    const result = await send(form, { body: 'formulaire', fetchImpl: async () => { throw new TypeError('Failed to fetch'); } });
    assert.equal(result.error, MESSAGES.network);
});
