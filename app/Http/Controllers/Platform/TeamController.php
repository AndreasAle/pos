<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformActivityLog;
use App\Models\PlatformAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Kelola akun tim Yoka (khusus super admin). Semua perubahan tercatat. */
class TeamController extends Controller
{
    private const ROLES = ['super_admin', 'support', 'finance'];

    public function index()
    {
        $this->authorizeSuper();

        return view('platform.team', ['admins' => PlatformAdmin::orderBy('name')->get(), 'me' => auth('platform')->user(), 'tempPassword' => session('temp_password'), 'tempEmail' => session('temp_email')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSuper();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190', Rule::unique('platform_admins', 'email')],
            'role' => ['required', Rule::in(self::ROLES)],
        ]);
        $password = Str::password(20, true, true, false);
        $admin = PlatformAdmin::create(['name' => $data['name'], 'email' => strtolower($data['email']), 'role' => $data['role'], 'password' => $password, 'is_active' => true]);
        PlatformActivityLog::record('team.admin_created', 'Menambah akun tim Yoka '.$admin->email.' ('.$admin->role.').', $admin);

        return back()->with('success', 'Akun dibuat. Berikan password sementara ini secara aman; tampil sekali saja.')->with('temp_password', $password)->with('temp_email', $admin->email);
    }

    public function toggle(PlatformAdmin $admin): RedirectResponse
    {
        $this->authorizeSuper();
        if ($admin->is(auth('platform')->user())) {
            return back()->withErrors(['team' => 'Kamu tidak bisa menonaktifkan akunmu sendiri.']);
        }
        if ($admin->is_active && $this->isLastSuper($admin)) {
            return back()->withErrors(['team' => 'Tidak bisa menonaktifkan super admin aktif terakhir.']);
        }
        $admin->update(['is_active' => ! $admin->is_active]);
        PlatformActivityLog::record('team.admin_toggled', ($admin->is_active ? 'Mengaktifkan' : 'Menonaktifkan').' akun tim '.$admin->email.'.', $admin);

        return back()->with('success', 'Status akun diperbarui.');
    }

    public function role(Request $request, PlatformAdmin $admin): RedirectResponse
    {
        $this->authorizeSuper();
        $data = $request->validate(['role' => ['required', Rule::in(self::ROLES)]]);
        if ($admin->is(auth('platform')->user())) {
            return back()->withErrors(['team' => 'Kamu tidak bisa mengubah perananmu sendiri.']);
        }
        if ($admin->role === 'super_admin' && $data['role'] !== 'super_admin' && $this->isLastSuper($admin)) {
            return back()->withErrors(['team' => 'Tidak bisa menurunkan super admin aktif terakhir.']);
        }
        $old = $admin->role;
        $admin->update(['role' => $data['role']]);
        PlatformActivityLog::record('team.role_changed', "Mengubah peran {$admin->email} dari {$old} menjadi {$admin->role}.", $admin, null, ['old' => $old, 'new' => $admin->role]);

        return back()->with('success', 'Peran diperbarui.');
    }

    private function authorizeSuper(): void
    {
        abort_unless(auth('platform')->user()->isSuperAdmin(), 403);
    }

    private function isLastSuper(PlatformAdmin $admin): bool
    {
        return $admin->role === 'super_admin' && PlatformAdmin::where('role', 'super_admin')->where('is_active', true)->where('id', '!=', $admin->id)->doesntExist();
    }
}
