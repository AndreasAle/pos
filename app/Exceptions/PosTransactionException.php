<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A sale was rejected for a business reason the cashier can act on —
 * not enough ingredient stock, not enough loyalty points, and so on.
 *
 * Thrown from inside the order transaction so nothing is persisted, and
 * rendered as the {success:false, message} shape the POS screen already
 * knows how to display.
 */
class PosTransactionException extends Exception
{
    public function render(Request $request): Response
    {
        // The POS screen posts with Content-Type: application/json but does not
        // always send an Accept header, so isJson() is checked alongside expectsJson().
        if ($request->expectsJson() || $request->isJson()) {
            return response()->json([
                'success' => false,
                'message' => $this->getMessage(),
            ], 422);
        }

        return back()->withInput()->withErrors(['pos' => $this->getMessage()]);
    }
}
