<?php

namespace App\Support;

/**
 * Professeur animé en 3D : réglages transmis au navigateur (config/avatar.php).
 *
 * Les fichiers statiques sont adressés avec une empreinte de leur contenu :
 * ils ne sont retéléchargés que s'ils changent (et non à chaque déploiement).
 */
class Avatar
{
    /** @var array<string, string> */
    private static array $versions = [];

    public static function enabled(): bool
    {
        return (bool) config('avatar.enabled') && is_file(public_path((string) config('avatar.model.file')));
    }

    /**
     * Réglages lus par public/js/teacher-view.js et teacher-3d.js.
     *
     * @return array<string, mixed>
     */
    public static function clientConfig(): array
    {
        $model = config('avatar.model');

        return [
            'module' => self::asset('js/teacher-3d.js'),
            'serviceWorker' => asset('teacher3d-sw.js'),
            'lib' => [
                'three' => self::asset('teacher3d/lib/three-teacher.min.js'),
                'headaudio' => self::asset('teacher3d/lib/headaudio.min.js'),
                'worklet' => self::asset('teacher3d/lib/headworklet.min.js'),
                'lipsync' => self::asset('teacher3d/lib/lipsync-en.bin'),
            ],
            'model' => [
                'url' => self::asset($model['file']),
                'size' => (int) @filesize(public_path($model['file'])),
                'profile' => $model['profile'],
                'camera' => $model['camera'],
                'desk_height' => $model['desk_height'],
                'resting_smile' => $model['resting_smile'],
                'voice_hz' => $model['voice_hz'],
            ],
        ];
    }

    /**
     * Noms des expressions que Gemini peut demander.
     *
     * @return list<string>
     */
    public static function expressions(): array
    {
        return array_keys(config('avatar.expressions', []));
    }

    /**
     * URL d'un fichier de public/ avec l'empreinte de son contenu.
     */
    public static function asset(string $path): string
    {
        if (! isset(self::$versions[$path])) {
            $file = public_path($path);
            self::$versions[$path] = is_file($file) ? substr(hash_file('xxh128', $file), 0, 12) : '0';
        }

        return asset($path).'?v='.self::$versions[$path];
    }
}
