<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Turning a device into an outlet's register (docs/login-flow-audit.md).
 *
 * The pattern other POS apps use: someone with an account signs in once on the
 * device and picks the outlet; from then on staff open the register with their
 * PIN. Nobody types an outlet code, and a device can only ever be bound to an
 * outlet of the business whose account set it up.
 *
 * Everything lives under /kasir so the Yoka tablet app, which keeps back-office
 * pages out of the register, can show it.
 */
class DeviceSetupController extends Controller
{
    /** Roles allowed to bind a device and to use the register. */
    private const ROLES = ['owner', 'admin', 'cashier'];

    /** Step 1: which outlet is this device for? */
    public function show(Request $request): View|RedirectResponse
    {
        $user    = $request->user();
        $outlets = $this->outletsFor($user);

        abort_unless(in_array($user->role, self::ROLES, true), 403, 'Akun ini tidak dipakai untuk kasir.');

        if ($outlets->isEmpty()) {
            abort(403, 'Belum ada outlet aktif untuk akun ini.');
        }

        if ($outlets->count() === 1) {
            return $this->bind($request, $outlets->first());
        }

        $current = PinLoginController::pairedOutlet($request);

        return view('auth.device-outlet', compact('outlets', 'current'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['outlet_id' => 'required|integer']);

        $outlet = $this->outletsFor($request->user())->firstWhere('id', (int) $request->outlet_id);

        // Not one of this account's outlets: never bind across businesses.
        abort_unless($outlet, 403);

        return $this->bind($request, $outlet);
    }

    /** Step 2: owners and admins without a PIN create one now. */
    public function pinForm(Request $request): View|RedirectResponse
    {
        if ($request->user()->pin) {
            return $this->next($request);
        }

        return view('auth.device-pin');
    }

    public function pinStore(Request $request): RedirectResponse
    {
        $request->validate([
            'pin' => 'required|digits_between:4,6|confirmed',
        ], [
            'pin.digits_between' => 'PIN harus 4 sampai 6 angka.',
            'pin.confirmed'      => 'Konfirmasi PIN tidak sama.',
        ]);

        $user = $request->user();
        $this->assertPinIsFree($user->business_id, $request->input('pin'), $user->id);

        $user->forceFill(['pin' => Hash::make($request->input('pin'))])->save();

        return $this->next($request);
    }

    /** Step 3 (new businesses): the two things a register cannot run without. */
    public function start(Request $request): View
    {
        $this->ownerOrAdmin($request);
        $business = $request->user()->business;

        $products = Product::where('business_id', $business->id)->latest()->limit(8)->get(['id', 'name', 'price']);
        $cashiers = User::where('business_id', $business->id)
            ->where('role', 'cashier')->where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'pin']);

        return view('auth.device-start', compact('products', 'cashiers'));
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $this->ownerOrAdmin($request);
        $data = $request->validate([
            'name'  => 'required|string|max:120',
            'price' => 'required|numeric|min:0|max:100000000',
        ]);

        Product::create([
            'business_id' => $request->user()->business_id,
            'name'        => trim($data['name']),
            'price'       => $data['price'],
            'cost_price'  => 0,
            'is_active'   => true,
        ]);

        return redirect()->route('device.start')->with('success', 'Produk ditambahkan.');
    }

    public function storeCashier(Request $request): RedirectResponse
    {
        $this->ownerOrAdmin($request);
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'pin'  => 'required|digits_between:4,6',
        ], [
            'pin.digits_between' => 'PIN harus 4 sampai 6 angka.',
        ]);

        $owner = $request->user();
        $this->assertPinIsFree($owner->business_id, $data['pin']);

        // A PIN-only cashier: no inbox to verify, no password anyone knows. The
        // address is unique and unroutable; the owner can replace it later in
        // the back office if this cashier needs a web login.
        $cashier = User::create([
            'business_id' => $owner->business_id,
            'outlet_id'   => PinLoginController::pairedOutlet($request)?->id ?? $owner->outlet_id,
            'name'        => trim($data['name']),
            'email'       => 'kasir-' . Str::lower(Str::random(12)) . '@kasir.yokapos.invalid',
            'password'    => Str::random(40),
            'role'        => 'cashier',
            'pin'         => Hash::make($data['pin']),
            'is_active'   => true,
        ]);
        $cashier->forceFill(['email_verified_at' => now()])->save();

        return redirect()->route('device.start')->with('success', 'Kasir ' . $cashier->name . ' bisa masuk dengan PIN-nya.');
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private function bind(Request $request, Outlet $outlet): RedirectResponse
    {
        return $this->next($request)->withCookie(PinLoginController::pairingCookie($outlet));
    }

    /** Where to go once the device is bound: PIN first, then setup or the till. */
    private function next(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user->pin && in_array($user->role, ['owner', 'admin'], true)) {
            return redirect()->route('device.pin');
        }

        if (in_array($user->role, ['owner', 'admin'], true)
            && !Product::where('business_id', $user->business_id)->exists()) {
            return redirect()->route('device.start');
        }

        return redirect()->route('pos.index');
    }

    /** @return Collection<int, Outlet> */
    private function outletsFor(User $user): Collection
    {
        $query = Outlet::where('business_id', $user->business_id)->where('is_active', true)->orderBy('name');

        // Cashiers tied to one outlet can only bind a device to that outlet.
        if ($user->role === 'cashier' && $user->outlet_id) {
            $query->whereKey($user->outlet_id);
        }

        return $query->get();
    }

    /**
     * PIN sign-in matches the PIN against everyone at the outlet, so two staff
     * of one business must never share a PIN.
     */
    private function assertPinIsFree(int $businessId, string $pin, ?int $except = null): void
    {
        $taken = User::where('business_id', $businessId)
            ->whereNotNull('pin')
            ->when($except, fn ($q) => $q->whereKeyNot($except))
            ->pluck('pin')
            ->contains(fn ($hash) => Hash::check($pin, $hash));

        if ($taken) {
            throw ValidationException::withMessages(['pin' => 'PIN ini sudah dipakai staf lain. Pilih PIN lain.']);
        }
    }

    private function ownerOrAdmin(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['owner', 'admin'], true), 403);
    }
}
