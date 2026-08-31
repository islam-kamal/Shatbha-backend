<?php

namespace Database\Seeders;

use App\Models\Party;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\Project;
use App\Models\VendorAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EcosystemSeeder extends Seeder
{
    public function run(): void
    {
        if (VendorAccount::query()->where('email', 'contractor@market.test')->exists()) {
            return;
        }

        $contractor = VendorAccount::query()->create([
            'type' => 'contractor',
            'name' => 'مقاول النخبة',
            'email' => 'contractor@market.test',
            'password' => Hash::make('password'),
            'phone' => '01010000001',
            'bio' => 'تشطيبات ومحارة',
            'service_area' => 'القاهرة',
            'rating_avg' => 4.5,
            'reviews_count' => 2,
        ]);

        $supplier = VendorAccount::query()->create([
            'type' => 'supplier',
            'name' => 'مورد السيراميك',
            'email' => 'supplier@market.test',
            'password' => Hash::make('password'),
            'phone' => '01010000002',
            'bio' => 'مواد تشطيب',
            'service_area' => 'الجيزة',
            'rating_avg' => 4.0,
            'reviews_count' => 1,
        ]);

        $tiles = ProductCategory::query()->firstOrCreate(['name' => 'سيراميك']);
        $paint = ProductCategory::query()->firstOrCreate(['name' => 'دهانات']);

        $p1 = Product::query()->create([
            'vendor_account_id' => $supplier->id,
            'category_id' => $tiles->id,
            'sku' => 'CER-60',
            'name' => 'سيراميك 60×60',
            'unit' => 'م²',
        ]);
        ProductPrice::query()->create([
            'product_id' => $p1->id,
            'price' => 250,
            'effective_from' => now()->toDateString(),
        ]);

        $p2 = Product::query()->create([
            'vendor_account_id' => $supplier->id,
            'category_id' => $paint->id,
            'sku' => 'PNT-WHT',
            'name' => 'دهان أبيض',
            'unit' => 'جالون',
        ]);
        ProductPrice::query()->create([
            'product_id' => $p2->id,
            'price' => 450,
            'effective_from' => now()->toDateString(),
        ]);

        $customer = Party::query()->where('type', 'customer')->where('name', 'خالد')->first();
        if ($customer) {
            Project::query()->create([
                'company_id' => $customer->company_id,
                'customer_id' => $customer->id,
                'title' => 'فيلا خالد - التجمع',
                'site_address' => 'التجمع الخامس',
                'status' => 'planning',
                'start_date' => '2026-09-01',
                'budget_planned' => 500000,
            ]);
        }
    }
}
