// Site LOCAL de DÉMONSTRATION des vidéos de présentation et du catalogue des écrans (make.mjs, catalogue.mjs) :
// cette copie du dépôt, base SQLite neuve, comptes fictifs « Élève », faux fournisseur des séances (fake-gemini.mjs).
// Jamais le site en ligne, aucune clé, aucun e-mail, aucun paiement envoyé.

import { spawn, spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { join } from 'node:path';
import { ROOT } from '../video/common.mjs';

export const SITE_PORT = 8123;
export const FAKE_PORT = 8124;
export const SITE = `http://127.0.0.1:${SITE_PORT}`;

/** Réglages du site de démonstration : base locale, faux fournisseur, aucune clé, aucun e-mail. */
export function demoEnv(database) {
    return {
        ...process.env,
        APP_ENV: 'local',
        APP_DEBUG: 'false',
        APP_KEY: process.env.DEMO_APP_KEY || `base64:${randomBytes(32).toString('base64')}`,
        APP_URL: SITE,
        APP_TIMEZONE: 'Africa/Lome',
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: database,
        SESSION_DRIVER: 'database',
        CACHE_STORE: 'database',
        QUEUE_CONNECTION: 'sync',
        PHP_CLI_SERVER_WORKERS: '6',
        FILESYSTEM_DISK: 'local',
        PRIVATE_FILES_DISK: 'local',
        MAIL_MAILER: 'log',
        LOG_CHANNEL: 'stderr',
        LOG_LEVEL: 'error',
        GEMINI_API_KEY: 'demonstration',
        GEMINI_BASE_URL: `http://127.0.0.1:${FAKE_PORT}`,
        GEMINI_WEBSOCKET_URL: `ws://127.0.0.1:${FAKE_PORT}`,
        OPENAI_API_KEY: '',
        LIVE_PROVIDERS: 'gemini',
        TEXT_PROVIDERS: 'gemini',
        TTS_PROVIDERS: 'gemini',
        VIDEO_UPLOAD_TOKEN: '',
        ADMIN_ALERT_EMAIL: '',
        VAPID_PUBLIC_KEY: '',
        VAPID_PRIVATE_KEY: '',
    };
}

/** Base neuve et comptes de démonstration (avant chaque vidéo : chaque vidéo part du même état). */
export function resetDemo(env, seeder = 'PresentationDemoSeeder') {
    const artisan = (...command) => {
        const result = spawnSync('php', ['artisan', ...command, '--no-interaction'], { cwd: ROOT, env, encoding: 'utf8' });

        if (result.status !== 0) {
            throw new Error(`php artisan ${command[0]} a échoué : ${(result.stderr || result.stdout).slice(-400)}`);
        }
    };

    artisan('migrate:fresh', '--force');
    artisan('db:seed', `--class=${seeder}`, '--force');
}

export async function startDemoSite(env) {
    // Serveur intégré de PHP (plusieurs processus), lancé depuis public/ comme « php artisan serve ».
    const router = join(ROOT, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');
    const server = spawn('php', ['-S', `127.0.0.1:${SITE_PORT}`, router], { cwd: join(ROOT, 'public'), env, stdio: ['ignore', 'ignore', 'ignore'] });

    for (let i = 0; i < 60; i++) {
        try {
            if ((await fetch(`${SITE}/up`)).ok) {
                return server;
            }
        } catch {}

        await new Promise((ok) => setTimeout(ok, 500));
    }

    server.kill();
    throw new Error('Le site de démonstration ne répond pas.');
}
