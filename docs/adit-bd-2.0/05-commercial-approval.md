# 05 — Approval harga khusus

Keadaan keputusan (`CommercialPolicy::evaluate`):
| Keadaan | Kapan | Tindakan |
|---|---|---|
| STANDARD_OFFER | Budget cukup untuk paket resmi, atau tanya diskon tanpa angka | Adit menjelaskan paket resmi |
| APPROVED_PROMOTION | Diskon ≤ `approved_discount_pct` (sekarang 0%) | Hanya harga resmi |
| NEEDS_HUMAN_APPROVAL | Di bawah harga resmi dan data biaya ada; atau paket custom | Proposal + kartu |
| INSUFFICIENT_DATA | Data biaya belum ada, atau tidak ada paket aktif | Proposal + kartu, putuskan manual |
| NOT_COMMERCIALLY_VIABLE | Di bawah `floor_price` (bila diisi) | Tolak sopan, tanpa angka internal |
| REJECTED_BY_POLICY | Minta gratis permanen / aktivasi gratis | Tawarkan trial saja |

Alur:
1. `CommercialApproval::propose` membuat `platform_ai_commercial_proposals` versi N (terms + analisis + risiko + kebutuhan prospek + `terms_hash`) dan kartu `commercial_proposal` (risk high, requires_approval) di Tim AI.
2. Permintaan sama diulang → tidak ada proposal/kartu baru. Syarat berbeda → versi lama `superseded`, kartunya ditutup.
3. Reviewer menekan Setujui/Tolak di Tim AI → `AiTeamController::decide` → `CommercialApproval::decide`:
   - hanya super admin aktif,
   - hanya proposal `pending_approval` (replay ditolak),
   - `terms_hash` kartu harus sama dengan proposal (basi ditolak),
   - mencatat aktor, waktu, syarat persis, catatan, versi.
4. Proposal yang sudah diputuskan tidak bisa diubah (model melempar `LogicException`); perubahan = versi baru.
5. Geser kartu di kanban untuk `commercial_proposal` diblokir (422).
6. Setelah disetujui, Adit **tidak** mengirim penawaran otomatis. Pemilik menyampaikan sendiri. Analisis internal tidak pernah masuk prompt.

`changes_requested` ada di layanan, tetapi UI Tim AI saat ini hanya punya Setujui/Tolak.
