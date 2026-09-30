<?php

namespace Tests\Feature\Reports;

use App\Exports\SalesItemSheet;
use App\Exports\SalesOrderSheet;
use App\Exports\SalesProductSheet;
use App\Exports\SalesReportExport;
use App\Models\Product;
use App\Models\User;
use App\Services\PosOrderService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * The sales report has to say what was sold and how many, not only the total.
 * An owner reconciling the day wants "12 Espresso, 5 Matcha", and wants the
 * same in the Excel file they hand to their bookkeeper.
 */
class SalesReportItemsTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private Product $espresso;
    private Product $matcha;

    protected function setUp(): void
    {
        parent::setUp();
        // The sheet classes live in SalesReportExport.php; load it so a test
        // run on its own can find them.
        class_exists(SalesReportExport::class);
        $this->setUpPos();

        $this->espresso = $this->product(price: 25000);
        $this->espresso->update(['name' => 'Espresso']);
        $this->matcha = $this->product(price: 25000);
        $this->matcha->update(['name' => 'Matcha']);

        $pos = app(PosOrderService::class);
        $pos->createOrder($this->cashier, $this->payload([
            ['product_id' => $this->espresso->id, 'qty' => 2],
            ['product_id' => $this->matcha->id, 'qty' => 1],
        ]));
        $pos->createOrder($this->cashier, $this->payload([
            ['product_id' => $this->espresso->id, 'qty' => 3],
        ]));
    }

    private function filters(): array
    {
        return ['date_from' => today()->toDateString(), 'date_to' => today()->toDateString(), 'outlet_id' => null];
    }

    public function test_products_sold_add_up_quantities_across_orders(): void
    {
        $sold = app(ReportService::class)->salesReport($this->business, [])['productsSold']
            ->keyBy('product_name');

        $this->assertEquals(5, $sold['Espresso']->total_qty);
        $this->assertEquals(125000, $sold['Espresso']->total_revenue);
        $this->assertEquals(1, $sold['Matcha']->total_qty);
    }

    public function test_the_report_page_lists_products_and_each_orders_items(): void
    {
        $owner = User::factory()->create([
            'business_id' => $this->business->id,
            'outlet_id'   => $this->outlet->id,
            'role'        => 'owner',
            'is_active'   => true,
        ]);

        $this->actingAs($owner)
            ->get(route('reports.sales'))
            ->assertOk()
            ->assertSee('Produk Terjual')
            ->assertSee('Detail Transaksi')
            ->assertSee('Espresso')
            ->assertSee('Matcha')
            ->assertSee('3×');
    }

    public function test_the_excel_export_has_product_and_item_sheets(): void
    {
        $sheets = (new SalesReportExport($this->business, $this->filters()))->sheets();

        $this->assertArrayHasKey('Produk Terjual', $sheets);
        $this->assertArrayHasKey('Detail Item', $sheets);
    }

    public function test_the_product_sheet_totals_quantity(): void
    {
        $rows = (new SalesProductSheet($this->business, $this->filters()))->collection();

        $this->assertSame(['Espresso', '', 5.0, 125000.0], $rows->first());
        $this->assertSame(['TOTAL', '', 6.0, 150000.0], $rows->last());
    }

    public function test_the_item_sheet_has_one_row_per_item_sold(): void
    {
        $rows = (new SalesItemSheet($this->business, $this->filters()))->collection();

        $this->assertCount(3, $rows);
        $this->assertSame(['Espresso', 2.0], [$rows[0][3], $rows[0][5]]);
    }

    public function test_the_order_sheet_names_the_items_in_each_order(): void
    {
        $rows = (new SalesOrderSheet($this->business, $this->filters()))->collection();

        // Both orders share a timestamp, so their order in the sheet is not fixed.
        $this->assertEqualsCanonicalizing(
            ["2x Espresso @25.000 = 50.000\n1x Matcha @25.000 = 25.000", '3x Espresso @25.000 = 75.000'],
            $rows->pluck(4)->all()
        );
    }

    public function test_each_order_row_explains_its_total(): void
    {
        $rows = (new SalesOrderSheet($this->business, $this->filters()))->collection();
        $row  = $rows->firstWhere(4, '3x Espresso @25.000 = 75.000');

        // subtotal, discount, promo, tax, total, method, paid, change
        $this->assertSame(75000.0, $row[5]);
        $this->assertSame(75000.0, $row[9]);
        $this->assertSame('CASH', strtoupper($row[10]));
        $this->assertCount(13, $row);
    }

    public function test_the_page_shows_line_prices_and_payment(): void
    {
        $owner = User::factory()->create([
            'business_id' => $this->business->id,
            'outlet_id'   => $this->outlet->id,
            'role'        => 'owner',
            'is_active'   => true,
        ]);

        $this->actingAs($owner)
            ->get(route('reports.sales'))
            ->assertOk()
            ->assertSee('@ 25.000', false)
            ->assertSee('Bayar Rp', false)
            ->assertSee('Kasir: ' . $this->cashier->name, false);
    }

    public function test_the_pdf_lists_every_order_with_its_items(): void
    {
        $owner = User::factory()->create([
            'business_id' => $this->business->id,
            'outlet_id'   => $this->outlet->id,
            'role'        => 'owner',
            'is_active'   => true,
        ]);

        $html = view('reports.pdf.sales', app(\App\Services\ReportService::class)->salesReport($this->business, []) + [
            'business'  => $this->business,
            'allOrders' => app(\App\Services\ReportService::class)->detailedOrders($this->business, $this->filters())->get(),
        ])->render();

        $this->assertStringContainsString('Detail Transaksi', $html);
        $this->assertStringContainsString('3x Espresso', $html);

        $this->actingAs($owner)->get(route('reports.sales.pdf'))->assertOk();
    }
}
