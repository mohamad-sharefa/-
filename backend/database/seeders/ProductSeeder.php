<?php

namespace Database\Seeders;

use App\Modules\Products\Models\Category;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Development catalogue: four categories with a few products each (no images).
     */
    public function run(): void
    {
        $catalogue = [
            'إلكترونيات' => [
                ['سماعات لاسلكية', 45.00, 25, 'سماعات بلوتوث بعزل للضوضاء وبطارية تدوم حتى 20 ساعة.'],
                ['شاحن سريع', 12.50, 40, 'شاحن بقدرة 20 واط بمنفذ USB-C.'],
                ['فأرة لاسلكية', 15.00, 0, 'فأرة لاسلكية صامتة بدقة قابلة للتعديل.'],
            ],
            'ملابس' => [
                ['قميص قطني', 18.00, 30, 'قميص من القطن الخالص بمقاسات متعددة.'],
                ['حذاء رياضي', 55.00, 12, 'حذاء خفيف مناسب للمشي والجري.'],
            ],
            'كتب' => [
                ['كتاب تعلّم البرمجة', 9.99, 50, 'مدخل مبسّط إلى أساسيات البرمجة للمبتدئين.'],
                ['رواية', 7.50, 3, 'رواية عربية معاصرة.'],
            ],
            'المنزل' => [
                ['كوب حراري', 8.00, 60, 'كوب يحافظ على حرارة المشروب لساعات.'],
            ],
        ];

        foreach ($catalogue as $categoryName => $products) {
            $category = Category::firstOrCreate(['name' => $categoryName]);

            foreach ($products as [$name, $price, $stock, $description]) {
                $category->products()->firstOrCreate(
                    ['name' => $name],
                    ['price' => $price, 'stock' => $stock, 'description' => $description],
                );
            }
        }
    }
}
