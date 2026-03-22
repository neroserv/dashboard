<?php

use App\Models\AiTokenBalance;
use App\Models\Brand;
use App\Models\BrandExtension;
use App\Models\User;
use App\Services\OpenAiService;

beforeEach(function () {
    Brand::query()->update(['is_default' => false]);
    $this->brand = Brand::create([
        'key' => 'ai-api-test',
        'name' => 'AI API Test',
        'domains' => null,
        'is_default' => true,
    ]);

    BrandExtension::query()->create([
        'brand_id' => $this->brand->id,
        'extension' => BrandExtension::EXTENSION_CHATGPT,
        'installed_at' => now(),
        'settings' => ['api_key' => 'test-key-from-brand-extension'],
    ]);

    $this->user = User::factory()->create(['brand_id' => $this->brand->id]);
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

test('generate-text returns 503 when chatgpt extension has no api key', function () {
    $row = BrandExtension::query()
        ->where('brand_id', $this->brand->id)
        ->where('extension', BrandExtension::EXTENSION_CHATGPT)
        ->first();
    expect($row)->not->toBeNull();
    $row->settings = [];
    $row->save();

    AiTokenBalance::create(['user_id' => $this->user->id, 'balance' => 1000]);

    $response = $this->postJson('/api/ai/generate-text', [
        'context' => 'Short text',
        'prompt_template' => 'expand',
    ]);

    $response->assertStatus(503);
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
