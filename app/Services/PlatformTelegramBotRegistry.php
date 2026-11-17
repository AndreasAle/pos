<?php

namespace App\Services;

use App\Models\PlatformAiAgent;
use App\Models\PlatformAiTelegramBot;
use Illuminate\Support\Str;

class PlatformTelegramBotRegistry
{
    public function sync(): void
    {
        app(PlatformAiOperations::class)->ensureAgents();
        foreach (PlatformAiAgent::all() as $agent) {
            $credentials = (array) config("services.telegram_office.bots.{$agent->key}", []);
            $secret = (string) ($credentials['webhook_secret'] ?? '');
            $values = [
                'platform_ai_agent_id' => $agent->id,
                'public_key' => PlatformAiTelegramBot::where('credential_key', $agent->key)->value('public_key') ?: (string) Str::uuid(),
                'webhook_secret_hash' => hash('sha256', $secret),
                'status' => filled($credentials['token'] ?? null) && $secret !== '' ? 'active' : 'inactive',
            ];
            if (filled($credentials['username'] ?? null)) {
                $values['username'] = ltrim($credentials['username'], '@');
            }
            PlatformAiTelegramBot::updateOrCreate(['credential_key' => $agent->key], $values);
        }
    }

    public function token(PlatformAiTelegramBot $bot): ?string
    {
        $token = config("services.telegram_office.bots.{$bot->credential_key}.token");

        return filled($token) ? (string) $token : null;
    }
}
