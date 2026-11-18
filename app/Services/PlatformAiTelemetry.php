<?php

namespace App\Services;

use App\Models\PlatformAiAgent;
use App\Models\PlatformAiRun;

class PlatformAiTelemetry
{
    public function record(PlatformAiAgent $agent, string $operation, string $model, float $started, array $response = [], ?\Throwable $error = null, array $links = []): PlatformAiRun
    {
        $input = (int) data_get($response, 'usage.prompt_tokens', 0);
        $output = (int) data_get($response, 'usage.completion_tokens', 0);
        $inputRate = (float) config('services.yoka_ai.input_cost_per_million', 0);
        $outputRate = (float) config('services.yoka_ai.output_cost_per_million', 0);

        return PlatformAiRun::create([
            'platform_ai_agent_id' => $agent->id,
            'platform_ai_task_id' => $links['task_id'] ?? null,
            'platform_ai_discussion_id' => $links['discussion_id'] ?? null,
            'operation' => $operation,
            'channel' => str_starts_with($operation, 'telegram') ? 'telegram_group' : 'platform_dashboard',
            'interaction_id' => $links['interaction_id'] ?? null,
            'model' => $model,
            'status' => $error ? 'failed' : 'completed',
            'input_tokens' => $input, 'output_tokens' => $output,
            'tool_calls' => count((array) data_get($response, 'choices.0.message.tool_calls', [])),
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            'estimated_cost_usd' => (($input * $inputRate) + ($output * $outputRate)) / 1_000_000,
            'error' => $error ? class_basename($error).': '.$this->redact($error->getMessage()) : null,
            'metadata' => ['cost_is_estimate' => true, 'provider_usage_available' => $input + $output > 0],
        ]);
    }

    /** Metrik kualitas percakapan yang dicatat sebagai PlatformAiRun berbiaya nol. */
    public const QUALITY_METRICS = [
        'direct_answer', 'direct_answer_failed', 'task_created_explicit', 'task_blocked_by_policy',
        'approval_notice_deduplicated', 'unsupported_claim_removed', 'filler_removed',
        'low_value_suppressed', 'reference_resolved', 'reference_unresolved', 'deliverable_missing',
        'task_result_delivered', 'task_failure_reported', 'consultation', 'useful_interaction', 'duplicate_reply_blocked',
        'terminology_corrected', 'correction_handled', 'business_check_applied',
    ];

    /**
     * Catat satu kejadian kualitas. Sengaja TIDAK menyimpan isi percakapan: hanya nama metrik,
     * agen, dan angka/flag pendek di metadata.
     */
    public function quality(string $metric, ?PlatformAiAgent $agent = null, array $meta = []): PlatformAiRun
    {
        $safe = collect($meta)->filter(fn ($v) => is_bool($v) || is_int($v) || is_float($v) || (is_string($v) && mb_strlen($v) <= 60))->all();

        return PlatformAiRun::create([
            'platform_ai_agent_id' => $agent?->id,
            'operation' => mb_substr('quality.'.$metric, 0, 40),
            'channel' => 'telegram_group',
            'model' => null,
            'status' => 'completed',
            'estimated_cost_usd' => 0,
            'metadata' => array_merge(['quality' => true], $safe),
        ]);
    }

    /** Ringkasan metrik kualitas + biaya per interaksi berguna untuk N hari terakhir. */
    public function qualitySummary(int $days = 7): array
    {
        $since = now()->subDays($days);
        $counts = PlatformAiRun::where('operation', 'like', 'quality.%')->where('created_at', '>=', $since)
            ->selectRaw('operation, count(*) total')->groupBy('operation')->pluck('total', 'operation')
            ->mapWithKeys(fn ($total, $op) => [substr($op, 8) => (int) $total])->all();
        $cost = (float) PlatformAiRun::where('channel', 'telegram_group')->where('operation', 'not like', 'quality.%')
            ->where('created_at', '>=', $since)->sum('estimated_cost_usd');
        $useful = (int) ($counts['useful_interaction'] ?? 0);

        return [
            'days' => $days,
            'metrics' => array_merge(array_fill_keys(self::QUALITY_METRICS, 0), $counts),
            'telegram_cost_usd' => round($cost, 4),
            'cost_per_useful_interaction_usd' => $useful > 0 ? round($cost / $useful, 4) : null,
        ];
    }

    public function dailyBudgetReached(): bool
    {
        $limit = (float) config('services.yoka_ai.daily_cost_budget_usd', 0);

        return $limit > 0 && (float) PlatformAiRun::whereDate('created_at', today())->sum('estimated_cost_usd') >= $limit;
    }

    private function redact(string $message): string
    {
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._~+\/-]+/iu', 'Bearer [REDACTED]', $message);
        $message = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/iu', '[REDACTED_EMAIL]', $message);
        $message = preg_replace('/\b(api[_-]?key|token|password|secret)\s*[=:]\s*[^\s,;]+/iu', '$1=[REDACTED]', $message);

        return mb_substr((string) $message, 0, 500);
    }
}
