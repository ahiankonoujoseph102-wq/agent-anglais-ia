// Page « Alertes » (public/js/admin-alerts.js) : conversion de la clé publique VAPID. Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { keyToBytes } from '../../public/js/admin-alerts.js';

test('la clé publique VAPID (base64 pour adresse web, sans remplissage) devient des octets', () => {
    // « Man? » en base64 classique : TWFuPw== ; en base64 pour adresse web, sans « = ».
    assert.deepEqual([...keyToBytes('TWFuPw')], [77, 97, 110, 63]);
    // Les caractères « - » et « _ » remplacent « + » et « / ».
    assert.deepEqual([...keyToBytes('-_-_')], [251, 255, 191]);
});
