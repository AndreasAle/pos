<?php

return [
    /*
     * Matriks persetujuan biaya. Admin peran "finance" hanya boleh menyetujui biaya sampai batas ini dan
     * tidak boleh menyetujui biaya yang ia ajukan sendiri. Di atas batas, wajib super admin.
     */
    'approval_limit_finance' => (float) env('YOKA_FINANCE_APPROVAL_LIMIT', 5000000),

    // Batas draf agen AI per hari.
    'agent_expense_drafts_per_day' => 5,
    'agent_budget_drafts_per_day' => 3,
];
