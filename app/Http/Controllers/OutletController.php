<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use Illuminate\Http\Request;

class OutletController extends Controller
{
    public function index()
    {
        $outlets = Outlet::forBusiness(auth()->user()->business_id)
            ->withCount('orders')
            ->latest()
            ->paginate(20);

        return view('outlets.index', compact('outlets'));
    }

    public function create()
    {
        return view('outlets.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'code'    => 'nullable|string|max:20',
            'phone'   => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ]);

        Outlet::create([
            'business_id' => auth()->user()->business_id,
            'name'        => $request->name,
            'code'        => $request->code,
            'phone'       => $request->phone,
            'address'     => $request->address,
            'is_active'   => true,
        ]);

        return redirect()->route('outlets.index')
            ->with('success', 'Outlet berhasil ditambahkan.');
    }

    public function edit(Outlet $outlet)
    {
        $this->authorizeBusiness($outlet);
        return view('outlets.edit', compact('outlet'));
    }

    public function update(Request $request, Outlet $outlet)
    {
        $this->authorizeBusiness($outlet);

        $request->validate([
            'name'      => 'required|string|max:255',
            'code'      => 'nullable|string|max:20',
            'phone'     => 'nullable|string|max:20',
            'address'   => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $outlet->update($request->only('name', 'code', 'phone', 'address', 'is_active'));

        return redirect()->route('outlets.index')
            ->with('success', 'Outlet berhasil diperbarui.');
    }

    public function destroy(Outlet $outlet)
    {
        $this->authorizeBusiness($outlet);

        if ($outlet->orders()->exists()) {
            return back()->with('error', 'Outlet tidak dapat dihapus karena memiliki data transaksi.');
        }

        $outlet->delete();
        return redirect()->route('outlets.index')
            ->with('success', 'Outlet berhasil dihapus.');
    }

    private function authorizeBusiness(Outlet $outlet): void
    {
        abort_if($outlet->business_id !== auth()->user()->business_id, 403);
    }
}
