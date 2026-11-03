<?php

use App\Modules\Products\Inventory\InventoryService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Requirement 2: stock moves out of this database into the inventory service.
 * Existing quantities are copied there first (so the inventory service must be running),
 * then the column is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        $inventory = app(InventoryService::class);

        DB::table('products')->select(['id', 'stock'])->orderBy('id')->each(function ($product) use ($inventory) {
            $inventory->setStock($product->id, (int) $product->stock);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('stock');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('stock')->default(0)->after('price');
        });

        $inventory = app(InventoryService::class);
        $ids = DB::table('products')->pluck('id')->all();
        foreach (array_chunk($ids, 200) as $chunk) {
            foreach ($inventory->stockOf($chunk) as $id => $quantity) {
                DB::table('products')->where('id', $id)->update(['stock' => $quantity]);
            }
        }
    }
};
