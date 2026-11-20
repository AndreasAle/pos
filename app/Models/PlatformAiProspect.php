<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Calon pelanggan yang diisi tim Yoka di spreadsheet Prospek Adit. */
class PlatformAiProspect extends Model
{
    public const STATUSES = [
        'baru' => 'Baru', 'perlu_dicek' => 'Perlu dicek', 'siap' => 'Siap dihubungi', 'dijadwalkan' => 'Draf menunggu persetujuan',
        'dihubungi' => 'Sudah dihubungi', 'membalas' => 'Membalas', 'tertarik' => 'Tertarik', 'trial' => 'Sudah daftar trial',
        'bayar' => 'Sudah bayar', 'tidak_minat' => 'Tidak minat', 'berhenti' => 'Minta berhenti dihubungi',
    ];

    /** Kolom yang boleh diisi dari spreadsheet. Status, hasil cek, dan riwayat kontak hanya diubah sistem. */
    public const EDITABLE = ['business_name', 'contact_name', 'phone', 'business_type', 'city', 'outlet_count', 'source', 'notes'];

    protected $fillable = ['business_name', 'contact_name', 'phone', 'phone_normalized', 'business_type', 'city', 'outlet_count', 'source', 'notes',
        'status', 'check_issues', 'checked_at', 'contact_count', 'last_contacted_at', 'last_reply_at', 'matched_business_id', 'last_task_id', 'opted_out_at', 'created_by'];

    protected function casts(): array
    {
        return ['check_issues' => 'array', 'checked_at' => 'datetime', 'last_contacted_at' => 'datetime', 'last_reply_at' => 'datetime', 'opted_out_at' => 'datetime'];
    }
}
