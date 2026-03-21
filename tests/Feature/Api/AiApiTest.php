<?php

use App\Models\AiTokenBalance;
use App\Models\User;
use App\Services\OpenAiService;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('openai.api_key', 'test-key-for-ai-tests');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('balance returns zero for user with no tokens', function () {
    $response = $this->getJson('/api/ai/balance');

    $response->assertOk();
    $response->assertJson(['balance' => 0]);
});

test('balance returns correct amount for user with tokens', function () {
    AiTokenBalance::create([
        'user_id' => $this->user->id,
        'balance' => 1500,
    ]);

    $response = $this->getJson('/api/ai/balance');

    $response->assertOk();
    $response->assertJson(['balance' => 1500]);
});

test('ai endpoints require authentication', function () {
    auth()->logout();

    $this->getJson('/api/ai/balance')->assertUnauthorized();
    $this->postJson('/api/ai/generate-text', [
        'context' => 'Test',
        'prompt_template' => 'expand',
    ])->assertUnauthorized();
});

test('generate-text returns 402 when insufficient tokens', function () {
    AiTokenBalance::create(['user_id' => $this->user->id, 'balance' => 50]);

    $this->mock(OpenAiService::class);

    $response = $this->postJson('/api/ai/generate-text', [
        'context' => 'Short text',
        'prompt_template' => 'expand',
    ]);

    $response->assertStatus(402);
});

test('generate-text returns generated text when successful', function () {
    AiTokenBalance::create(['user_id' => $this->user->id, 'balance' => 1000]);

    $this->mock(OpenAiService::class, function ($mock) {
        $mock->shouldReceive('generateText')
            ->once()
            ->andReturn('Expanded professional text.');
        $mock->shouldReceive('estimateTokens')
            ->andReturn(100);
    });

    $response = $this->postJson('/api/ai/generate-text', [
        'context' => 'Short text',
        'prompt_template' => 'expand',
    ]);

    $response->assertOk();
    $response->assertJson(['text' => 'Expanded professional text.']);
});

test('generate-text validates required fields', function () {
    $response = $this->postJson('/api/ai/generate-text', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['context', 'prompt_template']);
});

test('generate-text validates prompt_template enum', function () {
    $response = $this->postJson('/api/ai/generate-text', [
        'context' => 'Text',
        'prompt_template' => 'invalid',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['prompt_template']);
});
