<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Jobs\RunPlatformAiDiscussion;
use App\Models\PlatformActivityLog;
use App\Models\PlatformAiAgent;
use App\Models\PlatformAiDiscussion;
use App\Models\PlatformAiMemory;
use App\Models\PlatformAiMessage;
use App\Models\PlatformAiOwnerContext;
use App\Models\PlatformAiRecord;
use App\Models\PlatformAiTask;
use App\Services\PlatformAiAttachmentService;
use App\Services\PlatformAiContext;
use App\Services\PlatformAiOperations;
use App\Services\PlatformWhatsApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        $profiles = PlatformAiOperations::PROFILES;
        $snapshots = $agents->mapWithKeys(fn ($agent) => [$agent->id => $operations->snapshot($agent)]);
        $conversations = $agents->mapWithKeys(fn ($agent) => [
            $agent->id => $agent->messages()->with(['admin:id,name', 'attachments:id,platform_ai_message_id,original_name,kind,size'])->latest()->limit(40)->get()->reverse()->values(),
        ]);
        $unreadCounts = PlatformAiMessage::where('role', 'assistant')->whereNull('read_at')->get()
            ->filter(fn ($message) => (bool) data_get($message->meta, 'proactive'))
            ->countBy('platform_ai_agent_id');
        $ownerContexts = PlatformAiOwnerContext::query()->orderBy('label')->get();
        $memories = PlatformAiMemory::with('agent:id,name')->where('status', 'active')
            ->orderByDesc('confirmed')->orderByDesc('importance')->latest()->limit(30)->get();

        return view('platform.ai-team', compact('agents', 'tasks', 'records', 'counts', 'connections', 'profiles', 'snapshots', 'conversations', 'unreadCounts', 'ownerContexts', 'memories'));
    }

    public function chat(Request $request, PlatformAiAgent $agent, PlatformAiOperations $operations, PlatformAiAttachmentService $attachments)
    {
        abort_unless(auth('platform')->user()->isSuperAdmin(), 403);
        $data = $request->validate([
            'message' => 'nullable|required_without:files|string|max:4000',
            'files' => 'nullable|required_without:message|array|max:'.PlatformAiAttachmentService::MAX_FILES,
            'files.*' => 'file|max:5120',
        ]);
        $question = trim($data['message'] ?? '') ?: 'Tolong baca file ini dan beri tahu aku temuan pentingnya.';
        $approved = $operations->approveFromChat($agent, $question, auth('platform')->id());
        $facts = $approved ? ['Pemilik baru saja menyetujui tugas: '.implode('; ', $approved).'. Sistem sudah menjalankannya di latar belakang dan hasilnya akan masuk ke chat agen penerima begitu selesai (biasanya beberapa menit). Hasilnya BELUM ada, jangan mengarang isinya.'] : [];
        $savedFiles = $attachments->store(auth('platform')->user(), $agent, $request->file('files', []));
        $admin = auth('platform')->user();
        $result = $operations->reply($agent, $question, $savedFiles, $facts, $admin);
        $userMessage = $agent->messages()->create([
            'platform_admin_id' => auth('platform')->id(), 'role' => 'user', 'content' => $question,
            'meta' => ['attachments' => collect($savedFiles)->pluck('original_name')->all()],
        ]);
        collect($savedFiles)->each->update(['platform_ai_message_id' => $userMessage->id]);
        $context = app(PlatformAiContext::class);
        $context->rememberExplicitOwnerStatement($agent, $admin, $userMessage, $question);
        $assistantMessage = $agent->messages()->create([
            'role' => 'assistant', 'content' => $result['content'],
            'meta' => ['mode' => $result['mode'], 'snapshot' => $result['snapshot'], 'suggestions' => $result['suggestions'], 'proposed_tasks' => $result['created_tasks'] ?? []],
            'read_at' => now(),
        ]);
        $context->reviseConversationState($agent, $admin, $question, $result['created_tasks'] ?? [], $assistantMessage->content);
        PlatformActivityLog::record('ai.agent.chat', "Berdiskusi dengan {$agent->name}.", $assistantMessage, metadata: ['mode' => $result['mode']]);

        return response()->json([
            'user' => ['id' => $userMessage->id, 'role' => 'user', 'content' => $userMessage->content, 'attachments' => collect($savedFiles)->map(fn ($file) => ['name' => $file->original_name, 'kind' => $file->kind, 'size' => $file->size])->values(), 'created_at' => $userMessage->created_at->toIso8601String()],
            'assistant' => ['id' => $assistantMessage->id, 'role' => 'assistant', 'content' => $assistantMessage->content, 'mode' => $result['mode'], 'created_at' => $assistantMessage->created_at->toIso8601String()],
            'suggestions' => $result['suggestions'],
        ]);
    }

    public function markRead(PlatformAiAgent $agent)
    {
        abort_unless(auth('platform')->user()->isSuperAdmin(), 403);
        $agent->messages()->where('role', 'assistant')->whereNull('read_at')->update(['read_at' => now()]);

        return response()->noContent();
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

    public function storeOwnerContext(Request $request)
    {
        abort_unless(auth('platform')->user()->isSuperAdmin(), 403);
        $data = $request->validate([
            'key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
            'label' => 'required|string|max:120',
            'value' => 'required|string|max:4000',
        ]);
        $context = PlatformAiOwnerContext::updateOrCreate(['key' => $data['key']], $data + [
            'confirmed' => true,
            'updated_by' => auth('platform')->id(),
        ]);
        PlatformActivityLog::record('ai.context.update', "Memperbarui konteks pemilik: {$context->label}.", $context);

        return back()->with('success', 'Konteks pemilik disimpan dan berlaku untuk percakapan berikutnya.');
    }

    public function updateMemory(Request $request, PlatformAiMemory $memory)
    {
        abort_unless(auth('platform')->user()->isSuperAdmin(), 403);
        $data = $request->validate([
            'statement' => 'required|string|max:1000',
            'importance' => 'required|integer|min:1|max:100',
        ]);
        $replacement = DB::transaction(function () use ($memory, $data) {
            $content = (array) $memory->content;
            $content['statement'] = $data['statement'];
            $replacement = PlatformAiMemory::create([
                'scope' => $memory->scope, 'platform_admin_id' => $memory->platform_admin_id,
                'platform_ai_agent_id' => $memory->platform_ai_agent_id, 'category' => $memory->category,
                'memory_key' => $memory->memory_key, 'content' => $content,
                'source_message_id' => $memory->source_message_id, 'source_type' => 'owner_correction',
                'confidence' => 1, 'importance' => $data['importance'], 'confirmed' => true,
                'last_confirmed_at' => now(), 'version' => $memory->version + 1,
            ]);
            $memory->update(['status' => 'superseded', 'superseded_by' => $replacement->id]);

            return $replacement;
        });
        PlatformActivityLog::record('ai.memory.update', "Mengoreksi memori AI #{$memory->id} menjadi versi {$replacement->version}.", $replacement);

        return back()->with('success', 'Memori dikoreksi dan dikonfirmasi.');
    }

    public function deactivateMemory(PlatformAiMemory $memory)
    {
        abort_unless(auth('platform')->user()->isSuperAdmin(), 403);
        $memory->update(['status' => 'inactive']);
        PlatformActivityLog::record('ai.memory.deactivate', "Menonaktifkan memori AI #{$memory->id}.", $memory);

        return back()->with('success', 'Memori dinonaktifkan.');
    }

    public function storeDiscussion(Request $request)
    {
        abort_unless(auth('platform')->user()->isSuperAdmin(), 403);
        $data = $request->validate([
            'objective' => 'required|string|max:500',
            'agent_ids' => 'required|array|min:2|max:4',
            'agent_ids.*' => 'integer|distinct|exists:platform_ai_agents,id',
            'chair_agent_id' => 'nullable|integer|exists:platform_ai_agents,id',
        ]);
        $discussion = PlatformAiDiscussion::create([
            'created_by' => auth('platform')->id(), 'chair_agent_id' => $data['chair_agent_id'] ?? null,
            'objective' => $data['objective'], 'status' => 'pending',
            'limits' => ['max_agents' => 4, 'max_rounds' => 1, 'agent_ids' => $data['agent_ids']],
        ]);
        RunPlatformAiDiscussion::dispatchAfterResponse($discussion->id, auth('platform')->id(), $data['agent_ids']);
        PlatformActivityLog::record('ai.discussion.create', "Memulai diskusi tim: {$discussion->objective}.", $discussion);

        return back()->with('success', 'Diskusi tim dimulai. Kontribusi setiap agen akan dicatat.');
    }

    public function decide(Request $request, PlatformAiTask $task, PlatformAiOperations $operations)
    {
        abort_unless(auth('platform')->user()->isSuperAdmin(), 403);
        $data = $request->validate(['decision' => 'required|in:approve,reject', 'message' => 'nullable|string|max:600']);
        $decision = $data['decision'];
        abort_unless($task->status === 'proposed', 422);

        if ($decision === 'reject') {
            $task->update(['status' => 'rejected']);
            PlatformActivityLog::record('ai.task.reject', "Menolak tugas {$task->title}.", $task);

            return back()->with('success', 'Tugas ditolak.');
        }

        $task->update(['status' => 'approved', 'approved_by' => auth('platform')->id(), 'approved_at' => now(), 'idempotency_key' => (string) Str::uuid()]);
        PlatformActivityLog::record('ai.task.approve', "Menyetujui tugas {$task->title}.", $task);

        if ($task->type !== 'whatsapp_draft') {
            if ($operations->canExecute($task->fresh())) {
                $operations->dispatchExecution($task->fresh());

                return back()->with('success', "Disetujui. {$task->agent->name} mulai mengerjakan; hasilnya masuk ke chat-nya.");
            }

            return back()->with('success', 'Tugas disetujui. Eksekusi eksternal tetap menunggu koneksi resmi.');
        }

        // Manusia boleh menyunting pesan sebelum kirim; yang terkirim adalah teks yang disetujui.
        $message = trim($data['message'] ?? '') ?: $task->summary;
        $result = app(PlatformWhatsApp::class)->send((string) data_get($task->payload, 'phone'), $message);
        if ($result['ok']) {
            $task->update(['summary' => $message, 'status' => 'completed', 'executed_at' => now(), 'result' => ['sent' => true], 'error' => null]);
            PlatformActivityLog::record('ai.whatsapp.sent', "WhatsApp terkirim: {$task->title}.", $task, metadata: ['business_id' => data_get($task->payload, 'business_id')]);

            return back()->with('success', 'Disetujui dan WhatsApp terkirim.');
        }
        $task->update(['summary' => $message, 'error' => $result['error']]);

        return back()->with('success', 'Disetujui, tetapi belum terkirim: '.$result['error']);
    }

    public function storeTask(Request $request)
    {
        abort_unless(auth('platform')->user()->isSuperAdmin(), 403);
        $data = $request->validate([
            'platform_ai_agent_id' => 'required|exists:platform_ai_agents,id',
            'title' => 'required|string|max:180',
            'summary' => 'required|string|max:2000',
            'risk' => 'required|in:low,medium,high',
            'scheduled_for' => 'nullable|date',
        ]);
        $task = PlatformAiTask::create($data + [
            'type' => 'manual', 'status' => 'proposed', 'requires_approval' => true,
            'payload' => ['created_manually' => true],
        ]);
        PlatformActivityLog::record('ai.task.create', "Membuat tugas {$task->title}.", $task);

        return back()->with('success', 'Tugas baru masuk To Do List.');
    }

    public function moveTask(Request $request, PlatformAiTask $task, PlatformAiOperations $operations)
    {
        abort_unless(auth('platform')->user()->isSuperAdmin(), 403);
        $status = $request->validate(['status' => 'required|in:proposed,approved,completed,rejected'])['status'];
        // Pesan keluar hanya boleh lewat tombol Setujui & kirim, supaya tidak ada jalan pintas tanpa pengiriman tercatat.
        abort_if($task->type === 'whatsapp_draft' && in_array($status, ['approved', 'completed'], true), 422, 'Gunakan Setujui & kirim.');
        $changes = ['status' => $status];
        if ($status === 'approved') {
            $changes += ['approved_by' => auth('platform')->id(), 'approved_at' => now(), 'idempotency_key' => (string) Str::uuid()];
        }
        if ($status === 'completed') {
            $changes['executed_at'] = now();
        }
        $task->update($changes);
        PlatformActivityLog::record('ai.task.move', "Memindahkan {$task->title} ke {$status}.", $task, metadata: ['status' => $status]);
        if ($status === 'approved' && $operations->canExecute($task->fresh())) {
            $operations->dispatchExecution($task->fresh());

            return back()->with('success', "Tugas dipindahkan. {$task->agent->name} mulai mengerjakan; hasilnya masuk ke chat-nya.");
        }

        return back()->with('success', 'Tugas berhasil dipindahkan.');
    }
}
