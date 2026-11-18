<?php

namespace Tests\Feature\Platform;

use App\Services\PlatformAgents\AgentBrain;
use App\Services\PlatformAgents\AgentIntent;
use App\Services\PlatformAgents\BusinessReasoning;
use App\Services\PlatformAgents\ConversationDecision as D;
use App\Services\PlatformAgents\ResponseGuard;
use App\Services\PlatformAiContext;
use Tests\TestCase;

/** Evaluasi deterministik Workforce 3.0 (keputusan, nada, istilah, bukti). Data adalah fixture. */
class PlatformAiWorkforceV3Test extends TestCase
{
    public function test_decision_actions_for_representative_messages(): void
    {
        $cases = [
            'Ramli, menurutmu gimana?' => D::ANSWER_DIRECTLY,
            'Luna, hitung simulasi harga langganan' => D::GENERATE_DELIVERABLE,
            'Yusuf, buat lima hook video' => D::GENERATE_DELIVERABLE,
            'Naikkan budget iklan sekarang' => D::REQUEST_APPROVAL,
            'Yusuf, lanjutkan strategi kemarin' => D::RETRIEVE_PREVIOUS_WORK,
            'Yusuf tadi bahas apa?' => D::RETRIEVE_PREVIOUS_WORK,
            'Bukan itu maksudku' => D::CORRECT_COURSE,
            'jadikan tugas untuk Yosef follow up lead' => D::CREATE_TASK_PROPOSAL,
            'Yusuf, tanya Luna apakah ini masuk budget' => D::CONSULT_ANOTHER_AGENT,
            'Ramli rangkum diskusi tadi' => D::SUMMARIZE_DISCUSSION,
            'Ramli, analisis kondisi trial minggu ini' => D::ANALYZE_WITH_TOOLS,
        ];
        foreach ($cases as $text => $expected) {
            $this->assertSame($expected, D::decide($text)->action, $text);
        }
    }

    public function test_classifier_never_grants_task_permission_for_direct_work(): void
    {
        foreach (['Luna, hitung harga paket Yoka', 'Ramli, kasih prioritas hari ini', 'Yosef, buat draft penawaran', 'Bukan itu maksudku'] as $text) {
            $this->assertFalse(AgentIntent::classify($text)['allows_task'], $text);
        }
        $this->assertTrue(AgentIntent::classify('Naikkan budget iklan sekarang')['allows_task']);
    }

    public function test_mood_detection_is_temporary_and_specific(): void
    {
        $this->assertSame('frustrated', PlatformAiContext::mood('kenapa masih double ya'));
        $this->assertSame('frustrated', PlatformAiContext::mood('JAWABANNYA PANJANG PANJANG SEMUA'));
        $this->assertSame('urgent', PlatformAiContext::mood('cepat, trial yang habis hari ini siapa'));
        $this->assertSame('celebratory', PlatformAiContext::mood('akhirnya closing toko pertama!'));
        $this->assertSame('exploratory', PlatformAiContext::mood('gimana kalau kita bikin paket tahunan'));
        $this->assertSame('frustrated', D::decide('kenapa masih double ya')->mood);
    }

    public function test_business_reasoning_injects_only_relevant_checks(): void
    {
        $ads = D::decide('Yono, naikin budget iklan dong');
        $this->assertContains(BusinessReasoning::ADS, $ads->domains);
        $this->assertStringContainsString('KESIAPAN IKLAN', implode(' ', BusinessReasoning::facts($ads->domains)));
        $this->assertSame([], D::decide('yusuf buat 5 hook video')->domains);
        $this->assertContains(BusinessReasoning::FUNNEL, D::decide('kenapa churn trial tinggi')->domains);
    }

    public function test_trial_is_not_called_churn(): void
    {
        $r = ResponseGuard::validate('Churn kita tinggi karena 5 trial tidak aktif. Fokus ke aktivasi minggu ini.', ['tools_used' => ['funnel']]);
        $this->assertContains('terminology_trial_as_churn', $r['violations']);
        $this->assertSame('Fokus ke aktivasi minggu ini.', $r['text']);

        $ok = ResponseGuard::validate('Ini bukan churn, trial-nya memang belum aktif.');
        $this->assertSame([], $ok['violations']);
        $this->assertSame([], ResponseGuard::validate('Churn pelanggan berbayar bulan ini nol.')['violations']);
    }

    public function test_no_unsupported_assertions(): void
    {
        $r = ResponseGuard::validate('Udah aku cek laporannya. Pendaftar naik.', []);
        $this->assertContains('claimed_check_without_tool', $r['violations']);
        $this->assertSame([], ResponseGuard::validate('Udah aku cek laporannya. Pendaftar naik.', ['tools_used' => ['daily_report']])['violations']);

        $r = ResponseGuard::validate('Sudah aku jalankan campaign-nya.', ['tools_used' => ['business_metrics']]);
        $this->assertContains('claimed_external_action', $r['violations']);

        $r = ResponseGuard::validate('Aku lagi makan siang bos. Datanya kosong.');
        $this->assertContains('fabricated_human_life', $r['violations']);
        $this->assertSame('Datanya kosong.', $r['text']);
    }

    public function test_low_value_agreement_is_suppressed_but_warnings_are_not(): void
    {
        $this->assertSame('acknowledgement_only', ResponseGuard::lowValue('Setuju Bos.'));
        $this->assertNull(ResponseGuard::lowValue('Jangan dulu, trial kita belum siap.'));
        $this->assertNull(ResponseGuard::lowValue('Setuju, tapi CAC Rp 85.000 terlalu mahal buat harga Rp 99.000.'));
    }

    public function test_every_agent_has_a_distinct_judgment_style(): void
    {
        $this->assertCount(7, AgentBrain::JUDGMENT);
        $this->assertCount(7, array_unique(AgentBrain::JUDGMENT));
        $this->assertStringContainsString('Jangan otomatis setuju', AgentBrain::JUDGMENT['chief']);
        $this->assertStringContainsString('HITUNG langsung', AgentBrain::JUDGMENT['finance']);
    }

    public function test_decision_fact_is_guidance_not_permission(): void
    {
        $fact = D::decide('Naikkan budget iklan sekarang')->asFact();
        $this->assertStringContainsString('tidak bisa mengeksekusinya', $fact);
    }
}
