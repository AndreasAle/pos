<?php

namespace App\Http\Controllers;

use App\Exceptions\PosTransactionException;
use App\Models\User;
use App\Services\PosOrderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The offline register's way back (docs/offline-mode/01-baseline-and-design.md).
 *
 * The register keeps cash sales it could not send in IndexedDB and replays
 * them here once it is online. Each sale is its own transaction, so one bad
 * sale never holds back the rest, and each carries the client_uuid minted when
 * it was rung up, so replaying a batch twice records every sale once.
 */
class PosSyncController extends Controller
{
    /** Sales per request; the register sends its queue in slices this size. */
    public const BATCH = 20;

    public function __construct(protected PosOrderService $posService) {}

    /**
     * Cheap "am I really online and still signed in" check. Also hands the
     * register a fresh CSRF token: the one in a page served from the offline
     * cache may be hours old.
     */
    public function ping(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'ok'          => true,
            'csrf'        => csrf_token(),
            'user_id'     => $user->id,
            'business_id' => $user->business_id,
            'shift_id'    => $user->activeShift()->value('id'),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => 'required|string|max:64',
            'orders'    => 'required|array|min:1|max:' . self::BATCH,
            'orders.*'  => 'required|array',
        ]);

        $deviceId = (string) $request->input('device_id');
        $results  = [];

        foreach ($request->input('orders') as $raw) {
            $results[] = $this->syncOne($request->user(), is_array($raw) ? $raw : [], $deviceId);
        }

        return response()->json(['success' => true, 'results' => $results]);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function syncOne(User $auth, array $raw, string $deviceId): array
    {
        $uuid = PosOrderService::cleanUuid($raw['client_uuid'] ?? null);
        $base = ['client_uuid' => $uuid ?? ($raw['client_uuid'] ?? null)];

        try {
            $v = Validator::make($raw, [
                'client_uuid'            => 'required|uuid',
                'offline_number'         => 'nullable|string|max:40',
                'user_id'                => 'required|integer',
                'shift_id'               => 'nullable|integer',
                'ordered_at'             => 'required|string|max:40',
                'order_type'             => 'nullable|in:dine_in,takeaway',
                'table_number'           => 'nullable|string|max:20',
                'items'                  => 'required|array|min:1|max:100',
                'items.*.product_id'     => 'required|integer',
                'items.*.qty'            => 'required|numeric|min:0.001|max:9999',
                'items.*.variant_id'     => 'nullable|integer',
                'items.*.notes'          => 'nullable|string|max:255',
                'items.*.addons'         => 'nullable|array',
                'items.*.addons.*.addon_id' => 'required|integer',
                'paid_amount'            => 'required|numeric|min:0',
                'change_amount'          => 'nullable|numeric|min:0',
                'grand_total'            => 'required|numeric|min:0',
                'catalog_version'        => 'nullable|string|max:64',
            ]);
            $v->validate();

            // Only someone from this business can have rung it up. A queue left
            // on a tablet by another business must not land here.
            $seller = User::where('business_id', $auth->business_id)->find((int) $raw['user_id']);
            if (!$seller) {
                return $base + ['status' => 'rejected', 'message' => 'Kasir pada transaksi ini bukan bagian dari bisnis ini.'];
            }

            // Cash only, nothing that needs the server to decide at the till:
            // no customer, promotion, points, split bill, or held order.
            $data = [
                'client_uuid'    => $uuid,
                'items'          => array_map(fn ($i) => [
                    'product_id' => (int) $i['product_id'],
                    'qty'        => $i['qty'],
                    'variant_id' => $i['variant_id'] ?? null,
                    'notes'      => $i['notes'] ?? null,
                    'addons'     => array_map(fn ($a) => ['addon_id' => (int) $a['addon_id']], $i['addons'] ?? []),
                ], $raw['items']),
                'order_type'     => $raw['order_type'] ?? 'dine_in',
                'table_number'   => $raw['table_number'] ?? null,
                'payment_method' => 'cash',
                'paid_amount'    => (float) $raw['paid_amount'],
            ];

            $order = $this->posService->createOrder($seller, $data, false, [
                'shift_id'        => $raw['shift_id'] ?? null,
                'ordered_at'      => $raw['ordered_at'],
                'change_amount'   => $raw['change_amount'] ?? null,
                'client_total'    => (float) $raw['grand_total'],
                'offline_number'  => $raw['offline_number'] ?? null,
                'device_id'       => $deviceId,
                'catalog_version' => $raw['catalog_version'] ?? null,
            ]);

            return $base + [
                'status'       => $order->wasRecentlyCreated ? 'synced' : 'duplicate',
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'grand_total'  => (float) $order->grand_total,
                'review'       => $order->offline_review,
            ];
        } catch (ValidationException $e) {
            return $base + ['status' => 'rejected', 'message' => collect($e->errors())->flatten()->first()];
        } catch (ModelNotFoundException) {
            return $base + ['status' => 'rejected', 'message' => 'Produk di transaksi ini sudah dihapus dari menu.'];
        } catch (PosTransactionException $e) {
            return $base + ['status' => 'rejected', 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            // Leave it in the queue: a server hiccup must not be mistaken for a
            // sale the server refused.
            Log::error('POS offline sync failed', ['client_uuid' => $uuid, 'error' => $e->getMessage()]);

            return $base + ['status' => 'retry', 'message' => 'Server sedang bermasalah. Akan dicoba lagi.'];
        }
    }
}
