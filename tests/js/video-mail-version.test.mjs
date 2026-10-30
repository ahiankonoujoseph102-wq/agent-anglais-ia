// Version « e-mail » des vidéos (tools/video/mail-version.mjs) : débit calculé pour tenir sous la limite d'une pièce
// jointe, deux passes, son mono léger.
import test from 'node:test';
import assert from 'node:assert/strict';
import { mailArgs, mailVideoKbps } from '../../tools/video/mail-version.mjs';

const mail = { max_bytes: Math.floor(3.8 * 1024 * 1024), width: 540, height: 960, audio_kbps: 48 };

test('le débit tient sous la limite pour une vidéo de promotion et pour le Club', () => {
    for (const seconds of [130, 210]) {
        const kbps = mailVideoKbps(mail.max_bytes, seconds, mail.audio_kbps);
        const bytes = ((kbps + mail.audio_kbps) * 1000 * seconds) / 8;
        assert.ok(kbps >= 60, `débit trop bas pour ${seconds} secondes`);
        assert.ok(bytes <= mail.max_bytes * 0.95, `trop lourd pour ${seconds} secondes`);
    }

    assert.ok(mailVideoKbps(mail.max_bytes, 130, 48, 0.7) < mailVideoKbps(mail.max_bytes, 130, 48));
});

test('deux passes : la première sans son ni fichier, la seconde avec un son mono léger', () => {
    const first = mailArgs(mail, 'in.mp4', 'out.mp4', 150, 1, 'log');
    const second = mailArgs(mail, 'in.mp4', 'out.mp4', 150, 2, 'log');

    assert.ok(first.includes('-an') && first.includes('/dev/null'));
    assert.deepEqual(second.slice(-9), ['-c:a', 'aac', '-ac', '1', '-b:a', '48k', '-movflags', '+faststart', 'out.mp4']);
    assert.ok(second.includes('scale=540:960:flags=lanczos'));
    assert.ok(second.includes('150k'));
});
