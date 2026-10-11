<?php

namespace Tests\Unit;

use App\Support\Gemini\TextClient;
use PHPUnit\Framework\TestCase;

class TextClientTest extends TestCase
{
    public function test_output_text_is_the_last_model_output(): void
    {
        $steps = [
            ['type' => 'user_input', 'content' => [['type' => 'text', 'text' => 'question']]],
            ['type' => 'model_output', 'content' => [['type' => 'thought', 'text' => '…'], ['type' => 'text', 'text' => 'Bonjour '], ['type' => 'text', 'text' => 'Ama.']]],
        ];

        $this->assertSame('Bonjour Ama.', TextClient::outputText($steps));
        $this->assertSame('', TextClient::outputText([]));
    }
}
