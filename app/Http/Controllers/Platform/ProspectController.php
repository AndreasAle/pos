<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformActivityLog;
use App\Models\PlatformAiAgent;
use App\Models\PlatformAiProspect;
use App\Services\PlatformAgents\ProspectDesk;
use Illuminate\Http\Request;

/** Spreadsheet Prospek Adit. Hanya super admin. Mengisi data tidak mengirim pesan apa pun. */
class ProspectController extends Controller
{
    private function guard(): void
    {
        abort_unless(auth('platform')->user()->isSuperAdmin(), 403);
    }

    private function row(PlatformAiProspect $p): array
    {
        return $p->only(['id', ...PlatformAiProspect::EDITABLE, 'status', 'contact_count']) + [
            'status_label' => PlatformAiProspect::STATUSES[$p->status] ?? $p->status,
            'issues' => $p->check_issues ?? [],
            'last_contacted' => $p->last_contacted_at?->timezone('Asia/Jakarta')->format('d/m H:i'),
            'last_reply' => $p->last_reply_at?->timezone('Asia/Jakarta')->format('d/m H:i'),
            'locked' => $p->contact_count > 0 || $p->status === 'dijadwalkan',
        ];
    }

    private function rules(): array
    {
        return [
            'business_name' => 'nullable|string|max:150', 'contact_name' => 'nullable|string|max:100', 'phone' => 'nullable|string|max:30',
            'business_type' => 'nullable|string|max:40', 'city' => 'nullable|string|max:80', 'outlet_count' => 'nullable|integer|min:1|max:999',
            'source' => 'nullable|string|max:120', 'notes' => 'nullable|string|max:500',
        ];
    }

    public function index(ProspectDesk $desk)
    {
        $this->guard();

        return view('platform.prospects', [
            'rows' => PlatformAiProspect::orderBy('id')->limit(1000)->get()->map(fn ($p) => $this->row($p))->values(),
            'stats' => $desk->stats(),
            'statuses' => PlatformAiProspect::STATUSES,
        ]);
    }

    public function store(Request $request, ProspectDesk $desk)
    {
        $this->guard();
        $data = array_map(fn ($v) => $v ?? '', $request->validate($this->rules()));
        $p = new PlatformAiProspect(array_filter($data, fn ($v) => $v !== '') + ['created_by' => auth('platform')->id(), 'status' => 'baru']);
        $p->outlet_count = ($data['outlet_count'] ?? '') === '' ? null : (int) $data['outlet_count'];
        $desk->check($p);

        return response()->json($this->row($p->fresh()), 201);
    }

    public function update(Request $request, PlatformAiProspect $prospect, ProspectDesk $desk)
    {
        $this->guard();
        $data = $request->validate($this->rules());
        if ($prospect->contact_count > 0 && array_key_exists('phone', $data) && \App\Services\PlatformWhatsApp::normalize($prospect->phone) !== \App\Services\PlatformWhatsApp::normalize((string) $data['phone'])) {
            return response()->json(['message' => 'Nomor prospek yang sudah dihubungi tidak bisa diganti. Buat baris baru.'], 422);
        }
        foreach ($data as $k => $v) {
            $prospect->{$k} = $k === 'outlet_count' ? ($v === null || $v === '' ? null : (int) $v) : (string) ($v ?? '');
        }
        $desk->check($prospect);

        return response()->json($this->row($prospect->fresh()));
    }

    /** Tempel langsung dari Google Sheets/Excel (kolom dipisah tab) atau CSV. */
    public function paste(Request $request, ProspectDesk $desk)
    {
        $this->guard();
        $text = (string) $request->validate(['text' => 'required|string|max:200000'])['text'];
        $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', trim($text)), fn ($l) => trim($l) !== ''));
        if (count($lines) > 500) {
            return response()->json(['message' => 'Maksimal 500 baris sekali tempel.'], 422);
        }
        $cols = ['business_name', 'contact_name', 'phone', 'business_type', 'city', 'outlet_count', 'source', 'notes'];
        $created = [];
        foreach ($lines as $i => $line) {
            $cells = str_contains($line, "\t") ? explode("\t", $line) : str_getcsv($line, substr_count($line, ';') > substr_count($line, ',') ? ';' : ',');
            $cells = array_map(fn ($c) => trim((string) $c), $cells);
            if ($i === 0 && preg_match('/nama|bisnis|nomor|phone|wa/i', implode(' ', $cells)) && ! preg_match('/\d{8,}/', implode('', $cells))) {
                continue; // baris judul
            }
            $p = new PlatformAiProspect(['created_by' => auth('platform')->id(), 'status' => 'baru']);
            foreach ($cols as $j => $c) {
                $v = mb_substr($cells[$j] ?? '', 0, $c === 'notes' ? 500 : 150);
                $p->{$c} = $c === 'outlet_count' ? (ctype_digit($v) && (int) $v > 0 ? min(999, (int) $v) : null) : $v;
            }
            if (implode('', array_slice($cells, 0, 3)) === '') {
                continue;
            }
            $created[] = $this->row($desk->check($p)->fresh());
        }
        PlatformActivityLog::record('prospects.pasted', 'Menempel '.count($created).' prospek ke Prospek Adit.');

        return response()->json(['rows' => $created]);
    }

    public function destroy(PlatformAiProspect $prospect)
    {
        $this->guard();
        if ($prospect->contact_count > 0 || $prospect->status === 'dijadwalkan') {
            return response()->json(['message' => 'Prospek yang sudah/akan dihubungi tidak dihapus agar riwayat tetap ada. Ubah statusnya ke Tidak minat.'], 422);
        }
        $prospect->delete();

        return response()->json(['ok' => true]);
    }

    public function status(Request $request, PlatformAiProspect $prospect)
    {
        $this->guard();
        $status = $request->validate(['status' => 'required|in:tertarik,tidak_minat,berhenti,dihubungi'])['status'];
        $prospect->update(['status' => $status] + ($status === 'berhenti' ? ['opted_out_at' => now()] : []));
        PlatformActivityLog::record('prospects.status', "Status prospek {$prospect->business_name} diubah ke {$status}.", $prospect);

        return response()->json($this->row($prospect->fresh()));
    }

    public function checkAll(ProspectDesk $desk)
    {
        $this->guard();
        $n = 0;
        foreach (PlatformAiProspect::whereIn('status', ['baru', 'perlu_dicek', 'siap'])->get() as $p) {
            $desk->check($p);
            $n++;
        }
        $desk->refreshConversions();

        return back()->with('success', "{$n} prospek dicek ulang oleh Adit.");
    }

    public function draftToday(ProspectDesk $desk)
    {
        $this->guard();
        $adit = PlatformAiAgent::where('key', 'cs')->firstOrFail();
        $made = $desk->draftToday($adit);
        PlatformActivityLog::record('prospects.drafted', 'Adit menyusun '.count($made).' draf pesan prospek.');

        return back()->with('success', $made
            ? count($made).' draf dibuat. Periksa dan tekan "Setujui & kirim" di Tim AI.'
            : 'Tidak ada draf baru: batas '.ProspectDesk::DAILY_LIMIT.' per hari sudah terpakai atau belum ada prospek yang siap.');
    }
}

