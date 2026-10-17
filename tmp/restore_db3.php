<?php
require '/var/www/html/vendor/autoload.php';
$app = require_once '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;

// Categories - utiliser firstOrCreate pour eviter doublons
$cats = [
    ['name'=>'Ordinateurs portables','slug'=>'ordinateurs-portables','is_active'=>true],
    ['name'=>'Ordinateurs de bureau','slug'=>'ordinateurs-de-bureau','is_active'=>true],
    ['name'=>'Composants','slug'=>'composants','is_active'=>true],
    ['name'=>'Accessoires','slug'=>'accessoires','is_active'=>true],
    ['name'=>'Reseau','slug'=>'reseau','is_active'=>true],
];
$catIds = [];
foreach ($cats as $c) {
    $cat = Category::firstOrCreate(['slug'=>$c['slug']], $c);
    $catIds[] = $cat->id;
    echo "Category: " . $cat->name . " (id=" . $cat->id . ")\n";
}

// Produits
$products = [
    ['catSlug'=>'ordinateurs-portables','name'=>'HP ProBook 450 G8','reference'=>'HP-450G8','brand'=>'HP','price'=>275000,'stock'=>5,'processor'=>'Core i5','ram'=>'16 Go','storage'=>'512 Go SSD','screen'=>'15,6 pouces','state'=>'reconditionne'],
    ['catSlug'=>'ordinateurs-portables','name'=>'Lenovo ThinkPad T480','reference'=>'LEN-T480','brand'=>'Lenovo','price'=>185000,'stock'=>8,'processor'=>'Core i5','ram'=>'8 Go','storage'=>'256 Go SSD','screen'=>'14 pouces','state'=>'occasion'],
    ['catSlug'=>'ordinateurs-portables','name'=>'Apple MacBook Air M1','reference'=>'APL-MBA-M1','brand'=>'Apple','price'=>520000,'stock'=>2,'processor'=>'Apple M1','ram'=>'8 Go','storage'=>'256 Go SSD','screen'=>'13,3 pouces','state'=>'reconditionne'],
    ['catSlug'=>'ordinateurs-portables','name'=>'ASUS TUF Gaming F15','reference'=>'ASUS-TUF-F15','brand'=>'ASUS','price'=>650000,'stock'=>3,'processor'=>'Core i7','ram'=>'16 Go','storage'=>'512 Go SSD','graphics_card'=>'RTX 3050','screen'=>'15,6 pouces','state'=>'neuf'],
    ['catSlug'=>'ordinateurs-de-bureau','name'=>'Dell OptiPlex 7070','reference'=>'DELL-OPT7070','brand'=>'Dell','price'=>140000,'stock'=>6,'processor'=>'Core i5','ram'=>'8 Go','storage'=>'256 Go SSD','state'=>'occasion'],
    ['catSlug'=>'composants','name'=>'Kingston NV2 SSD 1 To','reference'=>'KNG-NV2-1TB','brand'=>'Kingston','price'=>65000,'stock'=>20,'storage'=>'1 To NVMe','state'=>'neuf'],
    ['catSlug'=>'accessoires','name'=>'Logitech MX Keys','reference'=>'LOG-MXKEYS','brand'=>'Logitech','price'=>75000,'stock'=>0,'state'=>'neuf'],
    ['catSlug'=>'reseau','name'=>'TP-Link Archer AX55','reference'=>'TPL-AX55','brand'=>'TP-Link','price'=>70000,'stock'=>12,'state'=>'neuf'],
];
foreach ($products as $p) {
    $catId = $catIds[array_search($p['catSlug'], array_column($cats, 'slug'))];
    unset($p['catSlug']);
    $data = array_merge(['category_id'=>$catId,'slug'=>Str::slug($p['name']),'is_active'=>true], $p);
    Product::firstOrCreate(['reference'=>$p['reference']], $data);
    echo "Product: " . $p['name'] . " (stock=" . $p['stock'] . ")\n";
}
echo "DONE\n";
