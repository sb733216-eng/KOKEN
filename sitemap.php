<?php
declare(strict_types=1);
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=900');

$projectPath = realpath(__DIR__);
$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$relativePath = ($projectPath && $documentRoot && stripos($projectPath, $documentRoot) === 0) ? substr($projectPath, strlen($documentRoot)) : '';
$base = 'https://kokenfindik.com' . rtrim('/' . trim(str_replace(DIRECTORY_SEPARATOR, '/', $relativePath), '/'), '/');
$escapeXml = static fn(string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
$pages = ['/', '/hakkimizda.html', '/uretim-tesisimiz.html', '/siparis-takibi.html', '/teslimat-ve-kargo.html', '/iptal-ve-iade.html', '/mesafeli-satis-sozlesmesi.html', '/kvkk.html', '/cerez-politikasi.html'];
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($pages as $page) echo '  <url><loc>' . $escapeXml($base . $page) . '</loc></url>' . "\n";
echo '</urlset>';
