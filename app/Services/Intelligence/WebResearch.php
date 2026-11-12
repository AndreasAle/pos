<?php

namespace App\Services\Intelligence;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Live internet research for market/competitor/trend/benchmark questions about the user's business.
 * The query is the only thing that leaves the app: personal data (emails, phone numbers) is stripped
 * and no POS figures are attached. Returned text is untrusted data with its sources.
 */
class WebResearch
{
    public function search(string $query): array
    {
        $query = trim(preg_replace(['/[\w.+-]+@[\w-]+\.[\w.]+/u', '/\+?\d[\d\s().-]{7,}\d/u'], '', strip_tags($query)));
        if (mb_strlen($query) < 4) {
            return ['summary' => 'Kueri pencarian terlalu pendek.', 'sources' => []];
        }
        $http = Http::baseUrl(rtrim(config('services.yoka_ai.url'), '/'))->withToken(config('services.yoka_ai.key'))->timeout(60)
            ->post('/responses', [
                'model' => config('services.yoka_ai.model'),
                'tools' => [['type' => 'web_search']],
                'max_output_tokens' => 1100,
                'input' => 'Riset web singkat untuk pemilik usaha F&B/retail di Indonesia. Jawab dalam bahasa Indonesia, hanya fakta berangka yang ada sumbernya, tandai tahun datanya, tanpa opini. Topik: '.Str::limit($query, 220, ''),
            ])->throw();

        $text = '';
        $sources = [];
        foreach ((array) $http->json('output', []) as $item) {
            if (($item['type'] ?? '') !== 'message') {
                continue;
            }
            foreach ((array) ($item['content'] ?? []) as $part) {
                $text .= (string) ($part['text'] ?? '');
                foreach ((array) ($part['annotations'] ?? []) as $a) {
                    if (($a['type'] ?? '') === 'url_citation' && ! empty($a['url']) && str_starts_with($a['url'], 'http')) {
                        $sources[$a['url']] = ['title' => Str::limit((string) ($a['title'] ?? parse_url($a['url'], PHP_URL_HOST)), 90, ''), 'url' => $a['url'], 'host' => preg_replace('/^www\./', '', (string) parse_url($a['url'], PHP_URL_HOST))];
                    }
                }
            }
        }

        return ['summary' => Str::limit(trim($text), 5000, '…'), 'sources' => array_slice(array_values($sources), 0, 6)];
    }
}
