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
            'operation' => $operation, 'model' => $model,
            'status' => $error ? 'failed' : 'completed',
            'input_tokens' => $input, 'output_tokens' => $output,
            'tool_calls' => count((array) data_get($response, 'choices.0.message.tool_calls', [])),
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            'estimated_cost_usd' => (($input * $inputRate) + ($output * $outputRate)) / 1_000_000,
            'error' => $error ? class_basename($error).': '.$this->redact($error->getMessage()) : null,
            'metadata' => ['cost_is_estimate' => true, 'provider_usage_available' => $input + $output > 0],
        ]);
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
