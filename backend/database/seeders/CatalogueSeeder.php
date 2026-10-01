<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'ordinateurs-portables' => 'Ordinateurs portables',
            'ordinateurs-de-bureau' => 'Ordinateurs de bureau',
            'composants' => 'Composants',
            'accessoires' => 'Accessoires',
            'reseau' => 'Réseau',
        ];

        $ids = [];
        foreach ($categories as $slug => $name) {
            $ids[$slug] = Category::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_active' => true]
            )->id;
        }

        $products = [
            ['cat' => 'ordinateurs-portables', 'name' => 'HP ProBook 450 G8', 'reference' => 'HP-450G8', 'brand' => 'HP', 'price' => 275000, 'stock' => 5, 'processor' => 'Core i5', 'ram' => '16 Go', 'storage' => '512 Go SSD', 'screen' => '15,6 pouces', 'state' => 'reconditionné'],
            ['cat' => 'ordinateurs-portables', 'name' => 'Lenovo ThinkPad T480', 'reference' => 'LEN-T480', 'brand' => 'Lenovo', 'price' => 185000, 'stock' => 8, 'processor' => 'Core i5', 'ram' => '8 Go', 'storage' => '256 Go SSD', 'screen' => '14 pouces', 'state' => 'occasion'],
            ['cat' => 'ordinateurs-portables', 'name' => 'Apple MacBook Air M1', 'reference' => 'APL-MBA-M1', 'brand' => 'Apple', 'price' => 520000, 'stock' => 2, 'processor' => 'Apple M1', 'ram' => '8 Go', 'storage' => '256 Go SSD', 'screen' => '13,3 pouces', 'state' => 'reconditionné'],
            ['cat' => 'ordinateurs-portables', 'name' => 'ASUS TUF Gaming F15', 'reference' => 'ASUS-TUF-F15', 'brand' => 'ASUS', 'price' => 650000, 'stock' => 3, 'processor' => 'Core i7', 'ram' => '16 Go', 'storage' => '512 Go SSD', 'graphics_card' => 'RTX 3050', 'screen' => '15,6 pouces', 'state' => 'neuf'],
            ['cat' => 'ordinateurs-de-bureau', 'name' => 'Dell OptiPlex 7070', 'reference' => 'DELL-OPT7070', 'brand' => 'Dell', 'price' => 140000, 'stock' => 6, 'processor' => 'Core i5', 'ram' => '8 Go', 'storage' => '256 Go SSD', 'state' => 'occasion'],
            ['cat' => 'composants', 'name' => 'Kingston NV2 SSD 1 To', 'reference' => 'KNG-NV2-1TB', 'brand' => 'Kingston', 'price' => 65000, 'stock' => 20, 'storage' => '1 To NVMe', 'state' => 'neuf'],
            ['cat' => 'accessoires', 'name' => 'Logitech MX Keys', 'reference' => 'LOG-MXKEYS', 'brand' => 'Logitech', 'price' => 75000, 'stock' => 0, 'state' => 'neuf'],
            ['cat' => 'reseau', 'name' => 'TP-Link Archer AX55', 'reference' => 'TPL-AX55', 'brand' => 'TP-Link', 'price' => 70000, 'stock' => 12, 'state' => 'neuf'],
        ];

        foreach ($products as $data) {
            $data['category_id'] = $ids[$data['cat']];
            $data['slug'] = Str::slug($data['name']);
            $data['is_active'] = true;
            unset($data['cat']);

            Product::updateOrCreate(['reference' => $data['reference']], $data);
        }
    }
}