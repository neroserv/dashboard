<?php

namespace App\Services;

use OpenAI;
use OpenAI\Contracts\ClientContract;

class OpenAiService
{
    private const MODEL = 'gpt-4o-mini';

    private const SEO_SYSTEM_PROMPT = 'Du bist ein SEO-Experte. Erstelle auf Basis des Seiteninhalts alle SEO-Felder für Suchmaschinen und Social Media. Antworte nur mit JSON. Felder: meta_title (max. 70 Zeichen), meta_description (max. 160 Zeichen), og_title, og_description (max. 200 Zeichen), og_image (vollständige URL oder leer), twitter_card ("summary_large_image" oder "summary"), twitter_title, twitter_description (max. 200 Zeichen), twitter_image (vollständige URL oder leer). Wenn kein Bild vorhanden: og_image und twitter_image leer lassen. Beispiel: {"meta_title":"...","meta_description":"...","og_title":"...","og_description":"...","og_image":"","twitter_card":"summary_large_image","twitter_title":"...","twitter_description":"...","twitter_image":""}.';

    private const PROMPT_TEMPLATES = [
        'expand' => 'Erweitere den folgenden Text sinnvoll, ohne den Kern zu verändern. Bleibe sachlich und professionell.',
        'shorten' => 'Kürze den folgenden Text deutlich, behalte aber die wichtigsten Informationen bei.',
        'professional' => 'Formuliere den folgenden Text professioneller und sachlicher.',
        'ad_copy' => 'Formuliere den folgenden Text als werbewirksamen, ansprechenden Werbetext.',
    ];

    private function clientForApiKey(string $apiKey): ClientContract
    {
        $organization = config('openai.organization');
        $project = config('openai.project');
        $baseUri = config('openai.base_uri');
        $factory = OpenAI::factory()
            ->withApiKey($apiKey)
            ->withHttpClient(new \GuzzleHttp\Client(['timeout' => (int) config('openai.request_timeout', 30)]));

        if (is_string($organization) && $organization !== '') {
            $factory->withOrganization($organization);
        }
        if (is_string($project) && $project !== '') {
            $factory->withProject($project);
        }
        if (is_string($baseUri) && $baseUri !== '') {
            $factory->withBaseUri($baseUri);
        }

        return $factory->make();
    }

    /**
     * @return array{
     *     meta_title: string,
     *     meta_description: string,
     *     og_title: string,
     *     og_description: string,
     *     og_image: string,
     *     twitter_card: string,
     *     twitter_title: string,
     *     twitter_description: string,
     *     twitter_image: string
     * }
     */
    public function generateSeoSuggestions(string $apiKey, string $pageContent, string $pageTitle): array
    {
        $client = $this->clientForApiKey($apiKey);
        $userPrompt = "Seitentitel: {$pageTitle}\n\nSeiteninhalt:\n".mb_substr(strip_tags($pageContent), 0, 4000);

        $response = $client->chat()->create([
            'model' => self::MODEL,
            'messages' => [
                ['role' => 'system', 'content' => self::SEO_SYSTEM_PROMPT],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.5,
        ]);

        $content = trim($response->choices[0]->message->content ?? '{}');
        if (preg_match('/^```(?:json)?\s*\n?(.*?)\n?```/s', $content, $m)) {
            $content = trim($m[1]);
        }
        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            $decoded = [];
        }

        $metaTitle = mb_substr((string) ($decoded['meta_title'] ?? ''), 0, 70);
        $metaDesc = mb_substr((string) ($decoded['meta_description'] ?? ''), 0, 160);
        $ogTitle = (string) ($decoded['og_title'] ?? $metaTitle);
        $ogDesc = mb_substr((string) ($decoded['og_description'] ?? $metaDesc), 0, 200);
        $ogImage = (string) ($decoded['og_image'] ?? '');
        $twitterCard = in_array($decoded['twitter_card'] ?? '', ['summary_large_image', 'summary', 'player'], true)
            ? (string) $decoded['twitter_card']
            : 'summary_large_image';
        $twitterTitle = (string) ($decoded['twitter_title'] ?? $ogTitle);
        $twitterDesc = mb_substr((string) ($decoded['twitter_description'] ?? $ogDesc), 0, 200);
        $twitterImage = (string) ($decoded['twitter_image'] ?? $ogImage);

        return [
            'meta_title' => $metaTitle,
            'meta_description' => $metaDesc,
            'og_title' => $ogTitle,
            'og_description' => $ogDesc,
            'og_image' => $ogImage,
            'twitter_card' => $twitterCard,
            'twitter_title' => $twitterTitle,
            'twitter_description' => $twitterDesc,
            'twitter_image' => $twitterImage,
        ];
    }

    public function generateText(
        string $apiKey,
        string $context,
        string $promptTemplate,
        ?string $pageName = null,
        ?string $blockType = null,
        ?string $additionalPrompt = null
    ): string {
        $client = $this->clientForApiKey($apiKey);
        $systemPrompt = self::PROMPT_TEMPLATES[$promptTemplate] ?? self::PROMPT_TEMPLATES['expand'];

        $contextParts = [];
        if ($pageName) {
            $contextParts[] = "Seite: {$pageName}";
        }
        if ($blockType) {
            $contextParts[] = "Blocktyp: {$blockType}";
        }
        if (count($contextParts) > 0) {
            $systemPrompt .= ' Kontext: '.implode(', ', $contextParts);
        }

        $userContent = $context;
        if ($additionalPrompt) {
            $userContent .= "\n\nZusatzanweisung: {$additionalPrompt}";
        }

        $response = $client->chat()->create([
            'model' => self::MODEL,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userContent],
            ],
            'temperature' => 0.6,
        ]);

        return trim($response->choices[0]->message->content ?? '');
    }

    /**
     * Rough token estimate: ~4 chars per token for German/English.
     */
    public function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }
}
