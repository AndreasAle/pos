<?php

namespace App\Services\Intelligence;

use App\Models\Business;
use App\Models\BusinessInsight;
use App\Models\IntelligenceAgent;
use App\Models\IntelligenceAgentRun;
use App\Services\YokaToolRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

class AgentService
{
    public function __construct(private YokaToolRegistry $tools) {}

    public function ensureDefaults(Business $business): void
    {
        $timezone = $business->timezone ?: config('app.timezone');
        foreach ([
            ['morning_briefing', 'daily', '08:00'], ['sales_pace', 'daily', '14:00'],
            ['low_stock', 'hourly', null], ['end_of_day', 'daily', '22:00'],
        ] as [$key, $frequency, $time]) {
            IntelligenceAgent::firstOrCreate(
                ['business_id' => $business->id, 'outlet_id' => null, 'agent_key' => $key],
                ['frequency' => $frequency, 'schedule_time' => $time, 'timezone' => $timezone, 'channels' => ['in_app'], 'enabled' => false, 'suppression_minutes' => $frequency === 'hourly' ? 120 : 720]
            );
        }
    }

    public function due(IntelligenceAgent $agent, ?CarbonImmutable $now = null): bool
    {
        if (! $agent->enabled) {
            return false;
        }
        $now ??= CarbonImmutable::now($agent->timezone);
        if ($agent->frequency === 'hourly') {
            return $now->minute < 5;
        }

        return $agent->schedule_time === $now->format('H:i');
    }

    public function run(IntelligenceAgent $agent, CarbonImmutable $scheduledFor): IntelligenceAgentRun
    {
        $slot = $agent->frequency === 'hourly' ? $scheduledFor->format('YmdH') : $scheduledFor->format('Ymd');
        $runKey = $agent->id.':'.$agent->version.':'.$slot;
        if ($existing = IntelligenceAgentRun::where('run_key', $runKey)->first()) {
            return $existing;
        }
        try {
            $run = IntelligenceAgentRun::create(['agent_id' => $agent->id, 'run_key' => $runKey, 'status' => 'running', 'scheduled_for' => $scheduledFor, 'started_at' => now()]);
        } catch (QueryException) {
            return IntelligenceAgentRun::where('run_key', $runKey)->firstOrFail();
        }
        try {
            $owner = $agent->business->owner()->firstOrFail();
            [$tool, $args] = match ($agent->agent_key) {
                'morning_briefing' => ['sales_summary', ['period' => 'yesterday']],
                'sales_pace' => ['sales_summary', ['period' => 'today']],
                'low_stock' => ['stock_status', ['period' => 'today']],
                'end_of_day' => ['sales_summary', ['period' => 'today']],
            };
            if ($agent->outlet_id) {
                $args['outlet_ids'] = [$agent->outlet_id];
            }
            $result = $this->tools->execute($owner, $tool, $args);
            $run->update(['status' => 'succeeded', 'finished_at' => now(), 'evidence' => $result['evidence_refs'], 'output' => $result]);
            $agent->update(['last_run_at' => now()]);
            $this->publishInAppInsight($agent, $result, $scheduledFor);
        } catch (\Throwable $error) {
            $run->update(['status' => 'failed', 'finished_at' => now(), 'error_code' => class_basename($error)]);
        }

        return $run->fresh();
    }

    private function publishInAppInsight(IntelligenceAgent $agent, array $result, CarbonImmutable $scheduledFor): void
    {
        $data = $result['data'];
        if ($agent->agent_key === 'low_stock') {
            $count = collect($data['ingredients'])->where('low', true)->count();
            if ($count === 0) {
                return;
            }
            $title = "{$count} bahan perlu perhatian";
            $summary = 'Agen stok menemukan bahan pada atau di bawah batas minimum.';
            $severity = 'warning';
        } else {
            $transactions = (int) ($data['transactions'] ?? 0);
            if ($transactions === 0 && $agent->agent_key === 'sales_pace') {
                return;
            }
            $title = match ($agent->agent_key) {
                'morning_briefing' => 'Morning briefing siap', 'sales_pace' => 'Pembaruan laju penjualan', default => 'Ringkasan akhir hari'
            };
            $summary = "{$transactions} transaksi tercatat untuk {$result['period']['label']}.";
            $severity = 'info';
        }
        $recent = BusinessInsight::where('business_id', $agent->business_id)->where('type', 'agent_'.$agent->agent_key)->where('detected_at', '>=', now()->subMinutes($agent->suppression_minutes))->exists();
        if ($recent) {
            return;
        }
        BusinessInsight::create([
            'business_id' => $agent->business_id, 'outlet_id' => $agent->outlet_id,
            'fingerprint' => 'agent:'.$agent->id.':'.$scheduledFor->format($agent->frequency === 'hourly' ? 'YmdH' : 'Ymd'),
            'type' => 'agent_'.$agent->agent_key, 'severity' => $severity, 'title' => $title, 'summary' => $summary,
            'data' => $data, 'evidence' => ['agent_run' => true, 'refs' => $result['evidence_refs']], 'confidence' => 100,
            'sample_size' => (int) ($data['transactions'] ?? count($data['ingredients'] ?? [])), 'detected_at' => now(),
            'first_detected_at' => now(), 'last_detected_at' => now(), 'expires_at' => now()->addHours(26),
        ]);
    }
}
