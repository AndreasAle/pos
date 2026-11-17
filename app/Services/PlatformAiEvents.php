<?php

namespace App\Services;

use App\Models\BusinessSubscription;
use App\Models\PlatformAiAgent;
use App\Models\PlatformAiEvent;
use App\Models\PlatformAiObservation;
use App\Models\PlatformAiTask;

class PlatformAiEvents
{
    public function detect(): int
    {
        app(PlatformAiOperations::class)->ensureAgents();
        $metrics = [
            'trial_ending_3d' => ['value' => BusinessSubscription::where('status', 'trial')->whereBetween('ends_at', [today(), today()->addDays(3)])->count(), 'agent' => 'cs', 'threshold' => 1, 'severity' => 'high'],
            'active_missing_paid_at' => ['value' => BusinessSubscription::where('status', 'active')->whereNull('paid_at')->count(), 'agent' => 'accounting', 'threshold' => 1, 'severity' => 'medium'],
            'pending_approvals' => ['value' => PlatformAiTask::where('status', 'proposed')->count(), 'agent' => 'chief', 'threshold' => 1, 'severity' => 'medium'],
        ];
        $created = 0;
        foreach ($metrics as $metric => $rule) {
            $previous = PlatformAiObservation::where('metric', $metric)->latest('observed_at')->first();
            $fingerprint = hash('sha256', $metric.'|'.$rule['value']);
            PlatformAiObservation::firstOrCreate(['fingerprint' => $fingerprint], [
                'metric' => $metric, 'value' => ['count' => $rule['value']], 'observed_at' => now(),
            ]);
            $old = (int) data_get($previous?->value, 'count', 0);
            if ($rule['value'] < $rule['threshold'] || $rule['value'] === $old) {
                continue;
            }
            $eventFingerprint = hash('sha256', $metric.'|'.$old.'>'.$rule['value']);
            $agent = PlatformAiAgent::where('key', $rule['agent'])->where('status', 'active')->first();
            if (! $agent) {
                continue;
            }
            $event = PlatformAiEvent::firstOrCreate(['fingerprint' => $eventFingerprint], [
                'platform_ai_agent_id' => $agent->id, 'type' => $metric, 'severity' => $rule['severity'],
                'evidence' => ['previous' => $old, 'current' => $rule['value']], 'status' => 'open',
            ]);
            if (! $event->wasRecentlyCreated) {
                continue;
            }
            $agent->messages()->create([
                'role' => 'assistant',
                'content' => "Bos, ada perubahan yang perlu diperhatikan: {$metric} berubah dari {$old} menjadi {$rule['value']}. Aku mencatatnya sebagai event {$rule['severity']} berdasarkan data terbaru.",
                'meta' => ['mode' => 'internal', 'proactive' => true, 'event_id' => $event->id, 'event_type' => $metric],
            ]);
            $event->update(['notified_at' => now()]);
            $created++;
        }

        return $created;
    }
}
