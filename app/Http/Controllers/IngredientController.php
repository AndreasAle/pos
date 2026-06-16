<?php
namespace App\Http\Controllers;
use App\Models\Ingredient;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    public function __construct(protected InventoryService $inventory) {}

    public function index()
    {
        $ingredients = Ingredient::forBusiness(auth()->user()->business_id)->paginate(30);
        return view('inventory.ingredients.index', compact('ingredients'));
    }
    public function create() { return view('inventory.ingredients.create'); }
    public function store(Request $request)
    {
        $request->validate(['name'=>'required','unit'=>'required','minimum_stock'=>'numeric|min:0']);
        Ingredient::create(['business_id'=>auth()->user()->business_id] + $request->only('name','sku','unit','minimum_stock','average_cost','is_active'));
        return redirect()->route('ingredients.index')->with('success','Bahan berhasil ditambahkan.');
    }
    public function show(Ingredient $ingredient)
    {
        abort_if($ingredient->business_id !== auth()->user()->business_id, 403);
        $movements = $ingredient->movements()->with('user')->latest()->paginate(20);
        return view('inventory.ingredients.show', compact('ingredient','movements'));
    }
    public function edit(Ingredient $ingredient)
    {
        abort_if($ingredient->business_id !== auth()->user()->business_id, 403);
        return view('inventory.ingredients.edit', compact('ingredient'));
    }
    public function update(Request $request, Ingredient $ingredient)
    {
        abort_if($ingredient->business_id !== auth()->user()->business_id, 403);
        $request->validate(['name'=>'required','unit'=>'required']);
        $ingredient->update($request->only('name','sku','unit','minimum_stock','average_cost','is_active'));
        return redirect()->route('ingredients.show',$ingredient)->with('success','Bahan diperbarui.');
    }
    public function destroy(Ingredient $ingredient)
    {
        abort_if($ingredient->business_id !== auth()->user()->business_id, 403);
        $ingredient->delete();
        return redirect()->route('ingredients.index')->with('success','Bahan dihapus.');
    }
    public function stockIn(Request $request, Ingredient $ingredient)
    {
        abort_if($ingredient->business_id !== auth()->user()->business_id, 403);
        $request->validate(['qty'=>'required|numeric|min:0.001','unit_cost'=>'nullable|numeric|min:0','notes'=>'nullable|string']);
        $this->inventory->addStock($ingredient, $request->qty, $request->unit_cost ?? 0, auth()->user(), $request->notes ?? 'Stok masuk');
        return back()->with('success','Stok berhasil ditambahkan.');
    }
    public function stockOut(Request $request, Ingredient $ingredient)
    {
        abort_if($ingredient->business_id !== auth()->user()->business_id, 403);
        $request->validate(['qty'=>'required|numeric|min:0.001','notes'=>'nullable|string']);
        $before = (float)$ingredient->current_stock;
        $after  = $before - $request->qty;
        $ingredient->update(['current_stock' => max(0, $after)]);
        StockMovement::create(['ingredient_id'=>$ingredient->id,'business_id'=>$ingredient->business_id,'user_id'=>auth()->id(),'type'=>'out','qty'=>-$request->qty,'stock_before'=>$before,'stock_after'=>max(0,$after),'unit_cost'=>$ingredient->average_cost,'notes'=>$request->notes ?? 'Stok keluar']);
        return back()->with('success','Stok berhasil dikurangi.');
    }
    public function adjustment(Request $request, Ingredient $ingredient)
    {
        abort_if($ingredient->business_id !== auth()->user()->business_id, 403);
        $request->validate(['new_stock'=>'required|numeric|min:0','notes'=>'nullable|string']);
        $this->inventory->adjustStock($ingredient, $request->new_stock, auth()->user(), $request->notes ?? '');
        return back()->with('success','Stok berhasil disesuaikan.');
    }
    public function movements()
    {
        $movements = StockMovement::where('business_id', auth()->user()->business_id)
            ->with(['ingredient','user'])->latest()->paginate(30);
        return view('inventory.movements', compact('movements'));
    }
}
