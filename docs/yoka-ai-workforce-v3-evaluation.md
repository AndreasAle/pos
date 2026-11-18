# Yoka AI Workforce 3.0 — Evaluasi

Jalankan di MySQL terisolasi (jangan di DB server):

```
DB_PORT=3307 php artisan test tests/Feature/Platform
php artisan yoka:ai-quality --days=7
```

Hasil 2026-11-19: 120 test lolos (495 asersi), termasuk `PlatformAiWorkforceV3Test` (9 test baru).

## Pemetaan skenario spec ke test

| # | Skenario | Test |
|---|---|---|
| 1 | Jawaban pendek natural | `V3Test::test_decision_actions…` (ANSWER_DIRECTLY) |
| 2 | Hitungan langsung tanpa tugas | `V3Test` + `ActionPolicyTest` |
| 3 | Menolak keputusan lemah (budget iklan) | REQUEST_APPROVAL + fakta kesiapan iklan |
| 4, 9, 19 | Memori / recall / lintas hari | RETRIEVE_PREVIOUS_WORK + `WorkingMemory` (`ActionPolicyTest`) |
| 5 | Koreksi | CORRECT_COURSE |
| 6, 20 | Tanpa kerja palsu / klaim tanpa dasar | `test_no_unsupported_assertions` |
| 7 | Perbedaan pendapat nyata | `consult` (`ActionPolicyTest`), JUDGMENT berbeda |
| 8 | "Setuju Bos" ditahan | `test_low_value_agreement…` |
| 10 | Hasil tugas tepat sekali | `ActionPolicyTest` (taskOutcome) |
| 11 | Metrik terbaru > memori lama | aturan prompt + fakta tool; **belum ada test otomatis** |
| 12 | Trial vs churn | `test_trial_is_not_called_churn` |
| 13 | Frustrasi | `test_mood_detection…` |
| 14, 15 | Routing, anti-loop | `PlatformTelegramOfficeTest` |
| 16, 17 | Keamanan, approval | `OfficeTest::unauthorized…`, `test_classifier_never_grants…` |
| 18 | Variasi bahasa | **rubrik manual** (lihat panduan live) |

## Batas evaluasi
Test ini deterministik: mereka membuktikan state, izin, dan guard, tapi tidak membuktikan kualitas bahasa model. Kualitas subjektif dinilai manual dengan rubrik: relevan, menambah info, angka bertumpu data, tanpa filler, beda tiap agen.
