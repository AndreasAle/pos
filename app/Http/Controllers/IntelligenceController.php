<?php

namespace App\Http\Controllers;

use App\Models\AssistantConversation;
use App\Models\BusinessInsight;
use App\Models\IntelligenceActionProposal;
use App\Models\IntelligenceAgent;
use App\Services\InsightService;
use App\Services\Intelligence\ActionService;
use App\Services\Intelligence\AgentService;
use App\Services\Intelligence\AttachmentService;
use App\Services\YokaAssistantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class IntelligenceController extends Controller
{
    public function index(Request $request, InsightService $insights, AgentService $agents)
    {
        abort_unless(config('pos.features.intelligence'), 404);
        $user = $request->user();
        $conversations = AssistantConversation::where('business_id', $user->business_id)
            ->where('user_id', $user->id)->withCount('messages')->latest('updated_at')->limit(40)->get();
        $activeConversation = null;
        if ($request->filled('conversation')) {
            $activeConversation = AssistantConversation::where('business_id', $user->business_id)
                ->where('user_id', $user->id)->with('messages')->findOrFail($request->integer('conversation'));
        }
        $businessInsights = $insights->visible($user->business, 5);
        $intelligenceAgents = collect();
        if (config('pos.features.intelligence_agents')) {
            $agents->ensureDefaults($user->business);
            $intelligenceAgents = IntelligenceAgent::where('business_id', $user->business_id)->orderBy('agent_key')->get();
        }

        $reportPrompt = $this->reportPrompt($request);

        return view('intelligence.index', compact('conversations', 'activeConversation', 'businessInsights', 'intelligenceAgents', 'reportPrompt'));
    }

    public function ask(Request $request, YokaAssistantService $assistant)
    {
        abort_unless(config('pos.features.intelligence'), 404);
        $data = $request->validate([
            'question' => 'required|string|max:2000', 'conversation_id' => 'nullable|integer',
            'files' => 'nullable|array|max:'.AttachmentService::MAX_FILES, 'files.*' => 'file|max:5120',
        ]);

        return response()->json(['success' => true] + $assistant->ask($request->user(), $data['question'], $data['conversation_id'] ?? null, $request->file('files', [])));
    }

    /** Same as ask(), but streams progress and answer tokens as Server-Sent Events; the final "done" event carries the full payload. */
    public function askStream(Request $request, YokaAssistantService $assistant)
    {
        abort_unless(config('pos.features.intelligence'), 404);
        $data = $request->validate([
            'question' => 'required|string|max:2000', 'conversation_id' => 'nullable|integer',
            'files' => 'nullable|array|max:'.AttachmentService::MAX_FILES, 'files.*' => 'file|max:5120',
        ]);
        $user = $request->user();
        $files = $request->file('files', []);

        return response()->stream(function () use ($assistant, $user, $data, $files) {
            @ini_set('zlib.output_compression', '0');
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            $send = function (string $event, array $payload) {
                echo "event: {$event}\ndata: ".json_encode($payload, JSON_UNESCAPED_UNICODE)."\n\n";
                @ob_flush();
                flush();
            };
            try {
                $send('status', ['label' => 'Memahami pertanyaan…']);
                $result = $assistant->ask($user, $data['question'], $data['conversation_id'] ?? null, $files,
                    fn (string $t) => $send('token', ['t' => $t]), fn (string $l) => $send('status', ['label' => $l]));
                $send('done', ['success' => true] + $result);
            } catch (\Illuminate\Validation\ValidationException $e) {
                $send('fail', ['message' => collect($e->errors())->flatten()->first() ?? 'Permintaan tidak valid.']);
            } catch (\Throwable $e) {
                report($e);
                $send('fail', ['message' => 'Yoka gagal membaca data. Coba lagi sebentar.']);
            }
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache, no-transform', 'X-Accel-Buffering' => 'no', 'Connection' => 'keep-alive']);
    }

    public function history(Request $request)
    {
        $conversation = AssistantConversation::where('business_id', $request->user()->business_id)
            ->where('user_id', $request->user()->id)->with(['messages' => fn ($q) => $q->orderByDesc('id')->limit(30)])
            ->latest('updated_at')->first();

        return response()->json(['conversation_id' => $conversation?->id, 'messages' => $conversation?->messages->reverse()->values() ?? []]);
    }

    public function destroyConversation(Request $request, AssistantConversation $conversation)
    {
        abort_unless($conversation->business_id === $request->user()->business_id && $conversation->user_id === $request->user()->id, 403);
        $conversation->delete();

        return response()->json(['success' => true]);
    }

    public function refresh(Request $request, InsightService $insights)
    {
        abort_unless(config('pos.features.intelligence'), 404);
        $count = $insights->refresh($request->user()->business);

        return back()->with('success', "Yoka memperbarui {$count} insight bisnis.");
    }

    public function read(Request $request, BusinessInsight $insight)
    {
        abort_unless($insight->business_id === $request->user()->business_id, 403);
        $insight->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function dismiss(Request $request, BusinessInsight $insight)
    {
        abort_unless($insight->business_id === $request->user()->business_id, 403);
        $insight->update(['dismissed_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function acknowledge(Request $request, BusinessInsight $insight)
    {
        abort_unless($insight->business_id === $request->user()->business_id, 403);
        $insight->update(['acknowledged_at' => now(), 'read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function snooze(Request $request, BusinessInsight $insight)
    {
        abort_unless($insight->business_id === $request->user()->business_id, 403);
        $data = $request->validate(['hours' => 'nullable|integer|min:1|max:168']);
        $insight->update(['snoozed_until' => now()->addHours($data['hours'] ?? 24)]);

        return response()->json(['success' => true]);
    }

    public function approveAction(Request $request, IntelligenceActionProposal $proposal, ActionService $actions)
    {
        $data = $request->validate(['confirmation' => 'required|string|max:20']);
        $proposal = $actions->approve($request->user(), $proposal, $data['confirmation']);

        return response()->json(['success' => true, 'proposal' => $proposal]);
    }

    public function executeAction(Request $request, IntelligenceActionProposal $proposal, ActionService $actions)
    {
        $proposal = $actions->execute($request->user(), $proposal);

        return response()->json(['success' => true, 'proposal' => $proposal]);
    }

    public function toggleAgent(Request $request, IntelligenceAgent $agent)
    {
        abort_unless(config('pos.features.intelligence_agents') && $agent->business_id === $request->user()->business_id, 403);
        $agent->update(['enabled' => ! $agent->enabled]);

        return response()->json(['success' => true, 'enabled' => $agent->enabled]);
    }

    /**
     * A report handed over by Laporan Penjualan. The reference is re-authenticated against the
     * logged-in user and the question text is rebuilt on the server from allowlisted filters,
     * so nothing in the URL can steer the assistant at another tenant or outlet.
     */
    private function reportPrompt(Request $request): ?string
    {
        if (! $request->filled('report_ref')) {
            return null;
        }
        try {
            $ref = json_decode(Crypt::decryptString((string) $request->query('report_ref')), true, 8, JSON_THROW_ON_ERROR);
            $user = $request->user();
            if (($ref['business_id'] ?? null) !== (int) $user->business_id || ($ref['user_id'] ?? null) !== $user->id
                || ($ref['report_type'] ?? '') !== 'sales' || ($ref['exp'] ?? 0) < now()->timestamp) {
                return null;
            }
            $f = \App\Services\Reporting\SalesReportFilter::fromRequest($user, (array) ($ref['filters'] ?? []));
        } catch (\Throwable) {
            return null;
        }
        $names = $user->business->outlets()->whereIn('id', $f->outletIds)->pluck('name')->all();
        $scope = count($f->outletIds) === count($f->allowedOutletIds) ? 'semua outlet' : 'outlet '.implode(', ', $names);

        return 'Analisis laporan penjualan '.$f->start->locale('id')->isoFormat('D MMM Y').' sampai '.$f->end->subSecond()->locale('id')->isoFormat('D MMM Y').' untuk '.$scope.'. Apa temuan utama dan hal yang perlu saya perhatikan?';
    }
}
