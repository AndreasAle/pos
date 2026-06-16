<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::forBusiness(auth()->user()->business_id)
            ->when($request->search, fn($q) =>
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('phone', 'like', '%' . $request->search . '%')
            )
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ]);

        Customer::create([
            'business_id' => auth()->user()->business_id,
            'name'        => $request->name,
            'phone'       => $request->phone,
            'email'       => $request->email,
            'is_active'   => true,
        ]);

        return redirect()->route('customers.index')
            ->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function show(Customer $customer)
    {
        abort_if($customer->business_id !== auth()->user()->business_id, 403);
        $customer->load('orders', 'points');
        return view('customers.show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        abort_if($customer->business_id !== auth()->user()->business_id, 403);
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        abort_if($customer->business_id !== auth()->user()->business_id, 403);

        $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ]);

        $customer->update($request->only('name', 'phone', 'email', 'is_active'));

        return redirect()->route('customers.index')
            ->with('success', 'Pelanggan berhasil diperbarui.');
    }

    public function destroy(Customer $customer)
    {
        abort_if($customer->business_id !== auth()->user()->business_id, 403);
        $customer->delete();
        return redirect()->route('customers.index')
            ->with('success', 'Pelanggan berhasil dihapus.');
    }
}
