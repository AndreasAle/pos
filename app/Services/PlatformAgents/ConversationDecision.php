<?php

namespace App\Services\PlatformAgents;

use App\Services\PlatformAiContext;
use Illuminate\Support\Str;

/**
 * Keputusan percakapan bertipe (Workforce 3.0), dihitung deterministik sebelum model dipanggil.
 *
 * Klasifikasi ini HANYA mengarahkan bentuk jawaban. Otorisasi tetap di tempat lain:
 * create_task dibuka/ditutup oleh AgentIntent::TASK_ALLOWED di AgentBrain, eksekusi eksternal
 * tidak punya tool sama sekali, dan approval hanya lewat dashboard / "Setujui & kirim".
 */
final class ConversationDecision
{
    public const ANSWER_DIRECTLY = 'answer_directly';

    public const ANALYZE_WITH_TOOLS = 'analyze_with_tools';

    public const GENERATE_DELIVERABLE = 'generate_deliverable';

    public const RETRIEVE_PREVIOUS_WORK = 'retrieve_previous_work';

    public const CREATE_TASK_PROPOSAL = 'create_task_proposal';

    public const REQUEST_APPROVAL = 'request_approval';

    public const CONSULT_ANOTHER_AGENT = 'consult_another_agent';

    public const SUMMARIZE_DISCUSSION = 'summarize_discussion';

    public const ASK_CLARIFICATION = 'ask_clarification';

    public const CORRECT_COURSE = 'correct_course';

    public const NO_RESPONSE = 'no_response';

    private function __construct(
        public readonly string $action,
        public readonly string $intent,
        public readonly string $mood,
        public readonly bool $correction,
        public readonly bool $needsData,
        /** @var array<int,string> */
        public readonly array $domains,
    ) {}

    public static function decide(string $text, ?array $intent = null): self
    {
        $intent ??= AgentIntent::classify($text);
        $t = Str::lower(trim($text));
        $correction = (bool) preg_match('/\b(bukan itu|bukan gitu|maksudku|maksud aku|salah|keliru|ralat|koreksi)\b/u', $t);
        $recall = (bool) preg_match('/\b(tadi|kemarin|kemaren|barusan|minggu lalu)\b.{0,25}\b(bahas|ngomongin|diskusi|bilang|hasil|hitungan|strategi)\b|\b(bahas|ngomongin|diskusi)\b.{0,10}\b(apa|apaan)\b.{0,10}\b(tadi|kemarin)?|\blanjut(kan|in)?\b.{0,20}\b(kemarin|tadi|yang kemarin)\b/u', $t);
        $summary = (bool) preg_match('/\b(rangkum|ringkas|kesimpulan|simpulkan)\b.{0,20}\b(diskusi|obrolan|tadi|semua)\b/u', $t);
        $vague = mb_strlen($t) <= 3 && ! preg_match('/[a-z]{2}/u', $t);

        $action = match (true) {
            $vague => self::NO_RESPONSE,
            $correction => self::CORRECT_COURSE,
            $intent['intent'] === AgentIntent::EXTERNAL_ACTION => self::REQUEST_APPROVAL,
            in_array($intent['intent'], [AgentIntent::TASK_CREATION, AgentIntent::TASK_DELEGATION], true) => self::CREATE_TASK_PROPOSAL,
            $intent['reference'] || $recall => self::RETRIEVE_PREVIOUS_WORK,
            $summary => self::SUMMARIZE_DISCUSSION,
            $intent['consult'] !== null => self::CONSULT_ANOTHER_AGENT,
            in_array($intent['intent'], [AgentIntent::CALCULATION, AgentIntent::DRAFT, AgentIntent::BRAINSTORM], true) => self::GENERATE_DELIVERABLE,
            in_array($intent['intent'], [AgentIntent::ANALYSIS, AgentIntent::TASK_STATUS], true) => self::ANALYZE_WITH_TOOLS,
            default => self::ANSWER_DIRECTLY,
        };

        $domains = BusinessReasoning::domains($t);

        return new self(
            $action,
            $intent['intent'],
            PlatformAiContext::mood($text),
            $correction,
            $domains !== [] || in_array($action, [self::ANALYZE_WITH_TOOLS, self::REQUEST_APPROVAL], true) || $intent['intent'] === AgentIntent::CALCULATION,
            $domains,
        );
    }

    /** Arahan singkat untuk model: bentuk jawaban, bukan izin. */
    public function asFact(): string
    {
        $shape = match ($this->action) {
            self::CORRECT_COURSE => 'Bos sedang mengoreksi. Sebut singkat apa yang kamu salah pahami, lalu perbaiki. Kalau masih benar-benar ambigu, ajukan SATU pertanyaan fokus. Jangan defensif dan jangan minta maaf berulang.',
            self::REQUEST_APPROVAL => 'Ini aksi eksternal/sensitif. Kamu tidak bisa mengeksekusinya. Jelaskan syarat dan risikonya; kalau masuk akal, usulkan lewat create_task yang menunggu approval. Kalau bukti bisnisnya belum cukup, katakan terus terang dan sarankan langkah yang lebih aman.',
            self::CREATE_TASK_PROPOSAL => 'Bos eksplisit minta tugas. Buat usulan tugas yang isinya keluaran konkret, lalu katakan tugas itu menunggu approval.',
            self::RETRIEVE_PREVIOUS_WORK => 'Bos merujuk pekerjaan/obrolan sebelumnya. Jawab dari MEMORI KERJA dan riwayat yang tersimpan. Kalau tidak ada catatannya, bilang tidak ada, jangan mengarang.',
            self::SUMMARIZE_DISCUSSION => 'Rangkum dari pesan yang benar-benar ada di riwayat: kesimpulan, perbedaan pendapat, dan keputusan yang masih terbuka.',
            self::CONSULT_ANOTHER_AGENT => 'Pakai jawaban rekan yang benar-benar tersimpan, lalu beri pendapatmu sendiri. Boleh tidak setuju.',
            self::GENERATE_DELIVERABLE => 'Tulis barang jadinya sekarang (hitungan/draf/ide), bukan janji atau judul tugas.',
            self::ANALYZE_WITH_TOOLS => 'Ambil data lewat tool dulu, lalu beri kesimpulan dan satu rekomendasi.',
            default => 'Jawab langsung di kalimat pertama sesuai konteks aktif. Panjang sesuai kebutuhan; jawaban satu kalimat itu wajar.',
        };

        return 'KEPUTUSAN PERCAKAPAN: '.$this->action.'. '.$shape.' Nada Bos sekarang: '.$this->mood.'.';
    }
}
