<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Le modèle 3D configuré respecte les conventions attendues par
 * public/js/teacher-3d.js et reste léger pour les forfaits mobiles.
 */
class AvatarModelTest extends TestCase
{
    /**
     * @return array<string, mixed> partie JSON du fichier GLB
     */
    private function gltf(): array
    {
        $bytes = file_get_contents(public_path(config('avatar.model.file')));
        $this->assertSame('glTF', substr($bytes, 0, 4));
        $length = unpack('V', substr($bytes, 12, 4))[1];
        $this->assertSame('JSON', substr($bytes, 16, 4));

        return json_decode(substr($bytes, 20, $length), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_model_weighs_less_than_5_mb(): void
    {
        $this->assertLessThan(5 * 1024 * 1024, filesize(public_path(config('avatar.model.file'))));
    }

    public function test_model_has_a_mixamo_skeleton_and_talking_head_shapes(): void
    {
        $gltf = $this->gltf();
        $nodes = array_column($gltf['nodes'], 'name');

        foreach (['Hips', 'Spine', 'Spine1', 'Spine2', 'Neck', 'Head', 'LeftEye', 'RightEye', 'LeftArm', 'LeftForeArm', 'LeftHand', 'RightArm', 'RightForeArm', 'RightHand'] as $bone) {
            $this->assertContains($bone, $nodes, "Os manquant : {$bone}");
        }

        $shapes = [];

        foreach ($gltf['meshes'] as $mesh) {
            $shapes = [...$shapes, ...($mesh['extras']['targetNames'] ?? [])];
        }

        $visemes = ['sil', 'PP', 'FF', 'TH', 'DD', 'kk', 'CH', 'SS', 'nn', 'RR', 'aa', 'E', 'I', 'O', 'U'];

        foreach ([...array_map(fn ($v) => "viseme_{$v}", $visemes), 'mouthSmileLeft', 'mouthSmileRight', 'eyeBlinkLeft', 'eyeBlinkRight', 'browInnerUp', 'jawOpen'] as $shape) {
            $this->assertContains($shape, $shapes, "Forme manquante : {$shape}");
        }
    }

    public function test_libraries_and_licenses_are_served_as_static_files(): void
    {
        foreach (['three-teacher.min.js', 'headaudio.min.js', 'headworklet.min.js', 'lipsync-en.bin', 'LICENSES.txt'] as $file) {
            $this->assertFileExists(public_path("teacher3d/lib/{$file}"));
        }

        $this->assertFileExists(public_path('teacher3d-sw.js'));
        $this->assertFileExists(public_path('js/teacher-3d.js'));
        $this->assertFileExists(public_path('js/teacher-view.js'));
    }
}
