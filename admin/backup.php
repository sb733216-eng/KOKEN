<?php
require_once __DIR__ . '/auth.php'; require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!class_exists(ZipArchive::class)) {
        http_response_code(500);
        exit('Yedek oluşturmak için C:\\xampp\\php\\php.ini dosyasında extension=zip etkinleştirilmelidir.');
    }
    $temporary = tempnam(sys_get_temp_dir(), 'koken-backup-');
    if ($temporary === false) { http_response_code(500); exit('Geçici yedek dosyası oluşturulamadı.'); }
    $zip = new ZipArchive();
    if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { @unlink($temporary); http_response_code(500); exit('ZIP yedeği oluşturulamadı.'); }
    $tables = ['categories','products','product_slug_redirects','admin_users','store_settings','order_code_sequences','orders','order_items','stock_movements','restock_notifications'];
    $dump = "-- Koken Admin database backup\nSET FOREIGN_KEY_CHECKS=0;\n";
    foreach ($tables as $table) {
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        if (!$create) continue;
        $dump .= "\nDROP TABLE IF EXISTS `$table`;\n" . $create[1] . ";\n";
        $result = $pdo->query("SELECT * FROM `$table`");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_map(static fn(string $column) => '`' . str_replace('`', '``', $column) . '`', array_keys($row));
            $values = array_map(fn($value) => $value === null ? 'NULL' : $pdo->quote((string)$value), array_values($row));
            $dump .= 'INSERT INTO `' . $table . '` (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n";
        }
    }
    $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $zip->addFromString('database.sql', $dump);
    $imageRoot = realpath(__DIR__ . '/../uploads/products');
    if ($imageRoot) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($imageRoot, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) if ($file->isFile() && $file->getFilename() !== '.htaccess') {
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($imageRoot) + 1));
            $zip->addFile($file->getPathname(), 'uploads/products/' . $relative);
        }
    }
    $zip->addFromString('README.txt', "Koken Admin yedeği\nVeritabanını geri yüklemek için database.sql dosyasını phpMyAdmin'de içe aktarın. Görseller uploads/products/ altındadır.\n");
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="koken-yedek-' . date('Y-m-d-His') . '.zip"');
    header('Content-Length: ' . filesize($temporary));
    header('Cache-Control: no-store');
    readfile($temporary);
    @unlink($temporary);
    exit;
}
$pageTitle = 'Yedekleme'; $current = 'backup'; require __DIR__ . '/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">BAKIM</p><h1>Yedekleme</h1><p class="text-secondary">Veritabanını ve yüklenen ürün görsellerini tek ZIP dosyasında indirin.</p></div></div>
<div class="content-card form-card"><div class="card-heading"><div><h2>Mağaza yedeği</h2><p>ZIP dosyası ürünleri, kategorileri, siparişleri, ayarları, yönetici hesaplarını ve uploads/products görsellerini içerir. Yedek dosyası sunucuda saklanmaz.</p></div></div><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn btn-primary" type="submit">Yedeği indir</button></form></div>
<?php require __DIR__ . '/footer.php'; ?>
