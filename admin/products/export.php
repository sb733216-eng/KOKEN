<?php
require_once __DIR__ . '/../auth.php'; require_admin();
$rows = $pdo->query('SELECT p.id,p.title,p.slug,p.badges,p.weight,p.chip,c.name AS category,p.price,p.old_price,p.description,p.ingredients,p.image,p.image2,p.image3,p.is_active,p.stock_qty,p.is_new_harvest,p.created_at FROM products p LEFT JOIN categories c ON c.id=p.category_id ORDER BY p.id')->fetchAll();
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="koken-urunler-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store');
$out = fopen('php://output', 'wb');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['ID','Ürün adı','Ürün URL slug','Doğrulanmış özellikler','Gramaj','Etiket','Kategori','Fiyat','Eski fiyat','Açıklama','İçindekiler','Ana görsel','Ek görsel 2','Ek görsel 3','Aktif','Stok','Yeni Mahsul','Oluşturulma']);
foreach ($rows as $row) {
    $values = [$row['id'],$row['title'],$row['slug'],$row['badges'],$row['weight'],$row['chip'],$row['category'],$row['price'],$row['old_price'],$row['description'],$row['ingredients'],$row['image'],$row['image2'],$row['image3'],$row['is_active'] ? 'Evet' : 'Hayır',$row['stock_qty'],$row['is_new_harvest'] ? 'Evet' : 'Hayır',$row['created_at']];
    $values = array_map(static function ($value) {
        if (!is_string($value)) return $value;
        return preg_match('/^\s*[=+@\-\t\r]/u', $value) ? "'" . $value : $value;
    }, $values);
    fputcsv($out, $values);
}
fclose($out);
