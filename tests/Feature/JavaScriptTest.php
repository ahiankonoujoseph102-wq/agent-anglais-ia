<?php

namespace Tests\Feature;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Tests des modules JavaScript sans navigateur (tests/js/*.test.mjs), lancés avec « node --test ».
 * Node est présent sur les machines de la CI GitHub ; ailleurs, le test est sauté.
 */
class JavaScriptTest extends TestCase
{
    public function test_javascript_modules_pass_their_tests(): void
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node n\'est pas installé : tests JavaScript sautés.');
        }

        $files = glob(base_path('tests/js/*.test.mjs'));
        $this->assertNotEmpty($files);

        $process = new Process([$node, '--test', ...$files], base_path());
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
    }
}
