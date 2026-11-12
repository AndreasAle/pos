<?php

namespace App\Services\Intelligence;

use Illuminate\Support\Str;

/**
 * The model may embed visual cards in its Markdown as fenced blocks:
 *
 *   ```yoka
 *   {"type":"chart","kind":"bar","title":"...","items":[{"label":"A","value":10}]}
 *   ```
 *
 * They are pulled out here, validated against a strict whitelist (types, field lengths, counts) and
 * replaced with [[visual:N]] markers so the browser renders trusted components instead of raw HTML.
 */
class VisualBlocks
{
    /** @return array{0:string,1:array} text with markers, validated visuals */
    public function extract(string $text): array
    {
        $visuals = [];
        $text = preg_replace_callback('/```yoka\s*(\{.*?\})\s*```/su', function ($m) use (&$visuals) {
            $block = $this->validate(json_decode($m[1], true));
            if (! $block || count($visuals) >= 6) {
                return '';
            }
            $visuals[] = $block;

            return "\n\n[[visual:".(count($visuals) - 1)."]]\n\n";
        }, $text);
        // A fence that never closed (cut-off answer) must not leak raw JSON.
        $text = preg_replace('/```yoka.*$/su', '', $text);

        return [trim(preg_replace('/\n{3,}/', "\n\n", $text)), $visuals];
    }

    private function validate($b): ?array
    {
        if (! is_array($b)) {
            return null;
        }
        $s = fn ($v, int $n = 120) => Str::limit(trim(strip_tags((string) $v)), $n, '');
        $num = fn ($v) => is_numeric($v) ? (float) $v : null;
        $type = $b['type'] ?? '';
        $base = ['type' => $type, 'title' => $s($b['title'] ?? '', 90), 'subtitle' => $s($b['subtitle'] ?? '', 160), 'note' => $s($b['note'] ?? '', 180)];

        switch ($type) {
            case 'chart':
                $kind = in_array($b['kind'] ?? 'bar', ['bar', 'line', 'hbar'], true) ? $b['kind'] : 'bar';
                $pts = [];
                foreach (array_slice((array) ($b['items'] ?? []), 0, 24) as $i) {
                    if (is_array($i) && ($v = $num($i['value'] ?? null)) !== null) {
                        $pts[] = ['label' => $s($i['label'] ?? '', 28), 'value' => $v, 'formatted' => $s($i['formatted'] ?? '', 24)];
                    }
                }

                return count($pts) >= 2 ? $base + ['kind' => $kind, 'unit' => $s($b['unit'] ?? '', 12), 'headline' => $s($b['headline'] ?? '', 40), 'items' => $pts] : null;
            case 'kpi':
                $m = [];
                foreach (array_slice((array) ($b['metrics'] ?? []), 0, 6) as $i) {
                    if (is_array($i) && isset($i['label'], $i['value'])) {
                        $m[] = ['label' => $s($i['label'], 40), 'value' => $s($i['value'], 30), 'note' => $s($i['note'] ?? '', 60)];
                    }
                }

                return $m ? $base + ['metrics' => $m] : null;
            case 'steps':
                $st = [];
                foreach (array_slice((array) ($b['steps'] ?? []), 0, 7) as $i) {
                    if (is_array($i) && ! empty($i['title'])) {
                        $st[] = ['title' => $s($i['title'], 70), 'text' => $s($i['text'] ?? '', 160), 'highlight' => (bool) ($i['highlight'] ?? false)];
                    }
                }

                return count($st) >= 2 ? $base + ['steps' => $st] : null;
            case 'roadmap':
                $it = [];
                foreach (array_slice((array) ($b['items'] ?? []), 0, 8) as $i) {
                    if (is_array($i) && ! empty($i['title'])) {
                        $it[] = ['when' => $s($i['when'] ?? '', 18), 'title' => $s($i['title'], 70), 'detail' => $s($i['detail'] ?? '', 80), 'text' => $s($i['text'] ?? '', 140)];
                    }
                }

                return count($it) >= 2 ? $base + ['items' => $it] : null;
            case 'callout':
                return ! empty($b['text']) ? $base + ['tone' => in_array($b['tone'] ?? 'info', ['info', 'warn', 'success'], true) ? $b['tone'] : 'info', 'text' => $s($b['text'], 400)] : null;
        }

        return null;
    }
}
