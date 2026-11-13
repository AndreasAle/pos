<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformActivityLog;
use App\Models\PlatformAiAgent;
use App\Models\PlatformAiRecord;
use App\Models\PlatformAiTask;
use App\Services\PlatformAiOperations;
use Illuminate\Http\Request;

class AiTeamController extends Controller
{
    public function index(PlatformAiOperations $operations)
    {
        abort_unless(auth('platform')->user()->isSuperAdmin(), 403);
        $operations->ensureAgents();
        $agents = PlatformAiAgent::withCount(['tasks as open_tasks_count' => fn ($q) => $q->whereIn('status', ['proposed', 'approved'])])->orderBy('id')->get();
        $tasks = PlatformAiTask::with('agent')->latest()->limit(30)->get();
        $records = PlatformAiRecord::latest()->limit(30)->get();
        $counts = PlatformAiRecord::selectRaw('kind, count(*) total')->groupBy('kind')->pluck('total', 'kind');
        $connections = [
            ['Instagram & Meta Ads', filled(config('services.meta_marketing.access_token')) && filled(config('services.meta_marketing.ad_account_id'))],
            ['TikTok & TikTok Ads', filled(config('services.tiktok_marketing.access_token')) && filled(config('services.tiktok_marketing.advertiser_id'))],
            ['WhatsApp Business', filled(config('services.whatsapp_business.access_token')) && filled(config('services.whatsapp_business.phone_number_id'))],
            ['Yoka AI', filled(config('services.yoka_ai.key'))],
            ['Payment & subscription Yoka', true],
            ['Database tenant Yoka', true],
        ];

        return view('platform.ai-team', compact('agents', 'tasks', 'records', 'counts', 'connections'));
    }

    public function run(PlatformAiAgent $agent, PlatformAiOperations $operations)
    {
        abort_unless(auth('platform')->user()->isSuperAdmin(), 403);
        $task = $operations->run($agent, true);
        PlatformActivityLog::record('ai.agent.run', "Menjalankan agen {$agent->name}.", $task);

        return back()->with('success', "{$agent->name} selesai menganalisis data.");
    }

    public function updateAgent(Request $request, PlatformAiAgent $agent)
    {
        abort_unless(auth('platform')->user()->isSuperAdmin(), 403);
        $data = $request->validate(['status' => 'required|in:active,paused', 'daily_budget' => 'required|integer|min:0|max:100000000']);
        $agent->update($data);
        PlatformActivityLog::record('ai.agent.update', "Memperbarui pengaturan {$agent->name}.", $agent, metadata: $data);

        return back()->with('success', 'Pengaturan agen disimpan.');
    }

    public function storeRecord(Request $request)
    {
        abort_unless(auth('platform')->user()->isSuperAdmin(), 403);
        $data = $request->validate([
            'kind' => 'required|in:content,campaign,lead,ticket,finance', 'title' => 'required|string|max:180',
            'status' => 'required|string|max:40', 'source' => 'required|string|max:80',
            'amount' => 'nullable|numeric|min:0', 'score' => 'nullable|integer|min:0|max:100',
            'notes' => 'nullable|string|max:2000', 'url' => 'nullable|url|max:1000',
        ]);
        $record = PlatformAiRecord::create([
            'kind' => $data['kind'], 'title' => $data['title'], 'status' => $data['status'], 'source' => $data['source'],
            'amount' => $data['amount'] ?? null, 'score' => $data['score'] ?? null,
            'data' => array_filter(['notes' => $data['notes'] ?? null, 'url' => $data['url'] ?? null]),
            'occurred_at' => now(), 'created_by' => auth('platform')->id(),
        ]);
        PlatformActivityLog::record('ai.record.create', "Menambahkan {$record->kind}: {$record->title}.", $record);

        return back()->with('success', 'Data operasional ditambahkan.');
    }

    public function decide(Request $request, PlatformAiTask $task)
    {
        abort_unless(auth('platform')->user()->isSuperAdmin(), 403);
        $decision = $request->validate(['decision' => 'required|in:approve,reject'])['decision'];
        abort_unless($task->status === 'proposed', 422);
        $task->update($decision === 'approve' ? ['status' => 'approved', 'approved_by' => auth('platform')->id(), 'approved_at' => now()] : ['status' => 'rejected']);
        PlatformActivityLog::record('ai.task.'.$decision, ($decision === 'approve' ? 'Menyetujui' : 'Menolak')." tugas {$task->title}.", $task);

        return back()->with('success', $decision === 'approve' ? 'Tugas disetujui. Eksekusi eksternal tetap menunggu koneksi resmi.' : 'Tugas ditolak.');
    }
}
