<?php
$pageTitle = 'Fiyat Editörü';
$current = 'price-editor';
require __DIR__ . '/header.php';
?>
<iframe
  title="Fiyat Editörü"
  src="<?= e(app_url('admin/editor-content.php')) ?>"
  style="display:block;width:100%;height:82vh;border:0"
></iframe>
<?php require __DIR__ . '/footer.php'; ?>
