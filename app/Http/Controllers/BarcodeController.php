<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Picqer\Barcode\BarcodeGeneratorPNG;

class BarcodeController extends Controller
{
    /**
     * Show barcode SVG for a single product (used as <img src="">).
     */
    public function show(Product $product)
    {
        abort_if($product->business_id !== auth()->user()->business_id, 403);

        $sku = $product->sku ?: str_pad($product->id, 8, '0', STR_PAD_LEFT);

        $generator = new BarcodeGeneratorSVG();
        $svg = $generator->getBarcode($sku, BarcodeGeneratorSVG::TYPE_CODE_128, 3, 80);

        return response($svg, 200)->header('Content-Type', 'image/svg+xml');
    }

    /**
     * Print labels page for one or many products.
     * GET /products/{product}/labels?qty=3
     * GET /barcodes/print?ids=1,2,3
     */
    public function printLabels(Request $request)
    {
        $user = auth()->user();

        if ($request->has('product_id')) {
            $product = Product::forBusiness($user->business_id)->findOrFail($request->product_id);
            $products = collect([$product]);
            $qty      = max(1, (int) $request->qty ?? 1);
        } else {
            $ids      = array_filter(explode(',', $request->ids ?? ''));
            $products = Product::forBusiness($user->business_id)->whereIn('id', $ids)->get();
            $qty      = 1;
        }

        $generator = new BarcodeGeneratorSVG();
        $labels = [];

        foreach ($products as $product) {
            $sku = $product->sku ?: str_pad($product->id, 8, '0', STR_PAD_LEFT);
            $svg = $generator->getBarcode($sku, BarcodeGeneratorSVG::TYPE_CODE_128, 2, 60);

            $count = $request->has('product_id') ? $qty : max(1, (int) ($request->{'qty_' . $product->id} ?? 1));

            for ($i = 0; $i < $count; $i++) {
                $labels[] = [
                    'product' => $product,
                    'sku'     => $sku,
                    'svg'     => $svg,
                ];
            }
        }

        return view('products.print-labels', compact('labels'));
    }

    /**
     * Mass print page: select products from list.
     */
    public function massSelect(Request $request)
    {
        $user     = auth()->user();
        $products = Product::forBusiness($user->business_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('products.barcode-select', compact('products'));
    }
}
