<?php

return [
    /*
     * Matriks persetujuan biaya. Admin peran "finance" hanya boleh menyetujui biaya sampai batas ini dan
     * tidak boleh menyetujui biaya yang ia ajukan sendiri. Di atas batas, wajib super admin.
     */
    'approval_limit_finance' => (float) env('YOKA_FINANCE_APPROVAL_LIMIT', 5000000),

    // Peringatan bila saldo kredit OpenAI di bawah angka ini (USD).
    'openai_low_balance_usd' => (float) env('YOKA_OPENAI_LOW_BALANCE_USD', 5),

    // Batas draf agen AI per hari.
    'agent_expense_drafts_per_day' => 5,
    'agent_budget_drafts_per_day' => 3,
];
