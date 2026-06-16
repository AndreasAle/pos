<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::forBusiness(auth()->user()->business_id)
            ->with('outlet')
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(20);

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $outlets = Outlet::forBusiness(auth()->user()->business_id)
            ->where('is_active', true)
            ->get();

        return view('users.create', compact('outlets'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|string|min:8|confirmed',
            'role'      => ['required', Rule::in(['admin', 'cashier', 'kitchen', 'warehouse'])],
            'outlet_id' => 'nullable|exists:outlets,id',
            'phone'     => 'nullable|string|max:20',
        ]);

        if ($request->outlet_id) {
            abort_if(
                !Outlet::where('id', $request->outlet_id)
                    ->where('business_id', auth()->user()->business_id)
                    ->exists(),
                403
            );
        }

        User::create([
            'business_id' => auth()->user()->business_id,
            'outlet_id'   => $request->outlet_id,
            'name'        => $request->name,
            'email'       => $request->email,
            'password'    => $request->password,
            'role'        => $request->role,
            'phone'       => $request->phone,
            'is_active'   => true,
        ]);

        return redirect()->route('users.index')
            ->with('success', 'User berhasil ditambahkan.');
    }

    public function edit(User $user)
    {
        $this->authorizeBusiness($user);

        $outlets = Outlet::forBusiness(auth()->user()->business_id)
            ->where('is_active', true)
            ->get();

        return view('users.edit', compact('user', 'outlets'));
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeBusiness($user);

        $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password'  => 'nullable|string|min:8|confirmed',
            'role'      => ['required', Rule::in(['owner', 'admin', 'cashier', 'kitchen', 'warehouse'])],
            'outlet_id' => 'nullable|exists:outlets,id',
            'phone'     => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        $data = $request->only('name', 'email', 'role', 'outlet_id', 'phone', 'is_active');

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // Prevent owner from deactivating themselves
        if ($user->id === auth()->id()) {
            unset($data['is_active']);
        }

        $user->update($data);

        return redirect()->route('users.index')
            ->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        $this->authorizeBusiness($user);
        abort_if($user->id === auth()->id(), 403, 'Tidak dapat menghapus akun sendiri.');

        $user->delete();
        return redirect()->route('users.index')
            ->with('success', 'User berhasil dihapus.');
    }

    public function toggle(User $user)
    {
        $this->authorizeBusiness($user);
        abort_if($user->id === auth()->id(), 403);

        $user->update(['is_active' => !$user->is_active]);

        return back()->with('success', 'Status user berhasil diubah.');
    }

    public function profile()
    {
        return view('users.profile', ['user' => auth()->user()]);
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name'     => 'required|string|max:255',
            'phone'    => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $data = $request->only('name', 'phone');

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    private function authorizeBusiness(User $user): void
    {
        abort_if($user->business_id !== auth()->user()->business_id, 403);
    }
}
