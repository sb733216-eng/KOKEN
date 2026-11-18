<?php
if (!isset($product, $images, $price, $discount, $unavailable)) { http_response_code(404); exit; }
?>
<div class="modal on" id="detail" aria-hidden="false">
  <div class="modal__bg" data-close></div>
  <div class="modal__box modal__box--xl" role="dialog" aria-modal="true" aria-labelledby="dName">
    <a class="x x--float" data-close href="<?= store_escape(store_path() . '#urunler') ?>" aria-label="Kapat">&times;</a>
    <div class="detail">
      <div class="detail__media">
        <div class="detail__stage<?= count($images) > 1 ? ' is-gallery' : '' ?>">
          <img id="dImg"<?= $images ? ' src="' . store_escape($images[0]) . '"' : ' hidden' ?> alt="<?= store_escape($product['title']) ?>" width="640" height="640" fetchpriority="high" decoding="async">
          <span class="disc" id="dDisc"<?= $discount ? '' : ' hidden' ?>><?= $discount ? '%' . $discount . ' İndirim' : '' ?></span>
          <span class="chip" id="dChip"<?= ($product['chip'] || $product['weight']) ? '' : ' hidden' ?>><?= store_escape($product['chip'] ?: $product['weight']) ?></span>
        </div>
        <div class="thumbs" id="dThumbs"<?= count($images) > 1 ? '' : ' hidden' ?>>
          <?php foreach ($images as $index => $image): ?>
          <button type="button" class="thumb<?= $index === 0 ? ' on' : '' ?>" data-src="<?= store_escape($image) ?>" aria-label="Görsel <?= $index + 1 ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"><img src="<?= store_escape($image) ?>" alt="<?= store_escape($product['title']) ?> - görsel <?= $index + 1 ?>" width="64" height="64" loading="lazy"></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="detail__info">
        <h1 id="dName"><?= store_escape($product['title']) ?></h1>
        <p class="detail__price<?= $discount ? ' price--sale' : '' ?>" id="dPrice"><?php if ($discount): ?><del><?= store_escape($price($product['old_price'])) ?></del><span><?= store_escape($price($product['price'])) ?></span><?php else: ?><?= store_escape($price($product['price'])) ?><?php endif; ?></p>
        <ul class="badges badges--earth" id="dCatBadges" aria-label="Ürün özellikleri"<?= $product['badges'] ? '' : ' hidden' ?>><?php foreach ($product['badges'] as $badge): ?><li><?= store_escape($badge) ?></li><?php endforeach; ?></ul>
        <p class="detail__desc" id="dDesc"<?= $product['description'] ? '' : ' hidden' ?>><?= store_escape($product['description']) ?></p>
        <section class="detail__sec" id="dIngSec"<?= $product['ingredients'] ? '' : ' hidden' ?>><h4>İçindekiler</h4><p id="dIng"><?= store_escape($product['ingredients']) ?></p></section>
        <section class="detail__sec" id="dFaq" hidden><h4>Sık Sorulan Sorular</h4><div class="faq" id="faqList"></div></section>
        <div class="detail__buy">
          <div class="qty qty--lg"><button type="button" id="dMinus" aria-label="Adedi azalt"<?= $unavailable ? ' disabled' : '' ?>>−</button><span id="dQty" aria-live="polite">1</span><button type="button" id="dPlus" aria-label="Adedi arttır"<?= $unavailable ? ' disabled' : '' ?>>+</button></div>
          <button type="button" class="btn btn--primary btn--lg" id="dAdd"<?= $unavailable ? ' disabled' : '' ?>><?= $unavailable ? 'Stokta yok' : 'Sepete Ekle · ' . store_escape($price($product['price'])) ?></button>
        </div>
        <form id="restockForm" class="detail__sec" data-product-id="<?= store_escape($product['id']) ?>"<?= $unavailable ? '' : ' hidden' ?>>
          <h4>Stok gelince haber ver</h4><label class="form__label" for="restockEmail">E-posta adresi</label><input class="form__input" id="restockEmail" name="email" type="email" maxlength="190" autocomplete="email" required>
          <label class="restock-consent"><input id="restockConsent" type="checkbox" required> Stok bildirimi talebim için e-posta adresimin saklanmasını kabul ediyorum.</label>
          <button class="btn btn--primary" type="submit" id="restockSubmit">Talep oluştur</button><p id="restockMessage" role="status" aria-live="polite"></p>
        </form>
        <p class="micro">📦 Özel korumalı ambalaj. Kırılma garantili gönderim; hasarlı ürünler sorgusuz yenilenir.</p>
      </div>
    </div>
  </div>
</div>
