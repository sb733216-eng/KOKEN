/* ===================== AYARLAR (kolayca değiştirilebilir) ===================== */
// MySQL kataloğu ana kaynaktır; bağlantı kesilirse yedek kayıtlar satışa kapalı gösterilir.
const WHATSAPP_NO = "905395982499"; // Ülke koduyla, + ve boşluk olmadan. Örn: 905321234567
let FREE_SHIPPING_LIMIT = 2000;      // Veritabanındaki mağaza ayarlarından yüklenir.
let SHIPPING_FEE = 60;
let LOW_STOCK_THRESHOLD = 5;

// Kategori sekmesi adları
const CAT_NAMES = { kavrulmus: 'Kavrulmuş', cig: 'Çiğ', ezme: 'Ezme', paketleme: 'Paketleme' };

// Yedek ürünler. cat: kavrulmus | cig | ezme | paketleme
// Açıklama ve içindekiler ÖRNEK metindir; kendi ürün etiketinize göre değiştirin.
const FALLBACK_PRODUCTS = [
  { id: '1', cat: 'kavrulmus', name: 'Taze Kavrulmuş İç Fındık', weight: '500g', chip: '', price: 250, img: 'kavrulmus.jpg',
    desc: 'Karadeniz fındığının kırılıp kendi tesisimizde taze kavrulmuş iç hali. Doğal tadı ve çıtırlığıyla atıştırmalık, kahvaltı ve tatlı tarifleri için uygundur.',
    ing: 'Fındık içi.' },
  { id: '3', cat: 'kavrulmus', name: 'Çifte Kavrulmuş Fındık', weight: '1 kg', chip: '', price: 480, img: 'cifte-kavrulmus.jpg',
    desc: 'İki aşamalı kavurma ile daha derin aroma ve belirgin çıtırlık. Kahve yanında ya da pastacılıkta kullanıma uygundur.',
    ing: 'Fındık içi.' },
  { id: '2', cat: 'ezme', name: 'Şekersiz Doğal Fındık Ezmesi', weight: '320g', chip: '320g Cam Kavanoz', price: 190, img: 'ezme.jpg',
    desc: 'Şeker eklenmeden öğütülen doğal fındık ezmesi, 320 g cam kavanozda. Ekmek, kahvaltı ve tatlı tariflerinde kullanılabilir.',
    ing: 'Kavrulmuş fındık. Şeker eklenmemiştir.' },
  { id: '4', cat: 'cig', name: 'Çiğ İç Fındık', weight: '500g', chip: '', price: 230, img: 'cig.jpg',
    desc: 'Kavrulmamış, çiğ iç fındık. Kendi kavurma tercihinize, granola ve tatlı tariflerine uygundur.',
    ing: 'Çiğ fındık içi.' },
  { id: '5', cat: 'paketleme', name: 'Vakumlu Paket Lüks Karışık Fındık', weight: '1 kg', chip: '', price: 520, img: 'paket.jpg',
    desc: 'Taze kalması için vakumlu paketlenmiş lüks karışık fındık, 1 kg. Hediye ve toplu tüketim için uygundur.',
    ing: 'Fındık içi.' }
];


/* ============================================================================== */

// ---- WhatsApp bağlantılarını WHATSAPP_NO'dan günceller (evrensel api.whatsapp.com biçimi) ----
// wa.me veya api.whatsapp.com/send ile başlayan her bağlantı güncellenir; ?text= korunur, yeni sekmede güvenli açılır.
(function syncWhatsAppLinks() {
  if (!/^\d{10,15}$/.test(WHATSAPP_NO)) return;
  const pretty = WHATSAPP_NO.replace(/^90(\d{3})(\d{3})(\d{2})(\d{2})$/, '0$1 $2 $3 $4');
  document.querySelectorAll('a[href*="wa.me/"], a[href*="api.whatsapp.com/send"]').forEach((a) => {
    let text = '';
    try { text = new URL(a.getAttribute('href'), location.href).searchParams.get('text') || ''; } catch (e) { /* yok say */ }
    a.href = `https://api.whatsapp.com/send?phone=${WHATSAPP_NO}` + (text ? `&text=${encodeURIComponent(text)}` : '');
    a.target = '_blank';
    a.rel = 'noopener noreferrer';
    if (/^\+?[\d\s]{10,}$/.test(a.textContent.trim())) a.textContent = pretty === WHATSAPP_NO ? '+' + WHATSAPP_NO : pretty;
  });
})();

(() => {
  'use strict';

  // Alt sayfalarda (ürün vitrini yok) mağaza kodu çalışmasın
 
  
  const KEY = 'koken-findik-cart';
  const MAX_QTY = 99;
  const SEP = '--------------------------------';

  const $ = (s) => document.querySelector(s);
  const $$ = (s) => Array.from(document.querySelectorAll(s));
  const productPageData = $('#productPageData');
  const productBootstrap = productPageData ? JSON.parse(productPageData.textContent) : null;
  const isProductPage = Boolean(productPageData);
  if (!document.querySelector('base')) {
    const base = document.createElement('base');
    base.href = location.origin + location.pathname;
    document.head.prepend(base);
  }
  const originalTitle = productBootstrap?.home?.title || document.title;
  const originalMetadata = new Map(Array.from(document.head.querySelectorAll('meta[name="description"],meta[property^="og:"],meta[name^="twitter:"],link[rel="canonical"]')).map((element) => {
    const name = element.tagName === 'LINK' ? 'canonical' : element.getAttribute('name') || element.getAttribute('property');
    return [element, productBootstrap?.home?.metadata?.[name] ?? element.getAttribute(element.tagName === 'LINK' ? 'href' : 'content')];
  }));
  let detailReturnUrl = null;
  let fullCatalogLoaded = !isProductPage;
  let catalogRequest = null;
  const money = (n) => Math.round(n * 100) / 100;
  const fmt = (n) => money(n).toLocaleString('tr-TR', { maximumFractionDigits: 2 }) + ' TL';
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /* @pure-start */
  const slug = (s) => String(s || '').toLocaleLowerCase('tr')
    .replace(/ç/g, 'c').replace(/ğ/g, 'g').replace(/ı/g, 'i').replace(/ö/g, 'o').replace(/ş/g, 's').replace(/ü/g, 'u')
    .replace(/[^a-z0-9]/g, '');
  const safeImg = (s) => (/^(?![a-z][a-z0-9+.-]*:)(?!\/\/)[^\s:"'<>]+$/i.test(s) ? s : '');

  /* @pure-end */

  const label = (p) => (p.weight ? `${p.name} (${p.weight})` : p.name);

  
  // Fiyat gösterimi: indirim varsa eski fiyat üstü çizili, güncel fiyat vurgulu
  const priceInner = (p) => (p.disc ? `<del>${fmt(p.old)}</del><span>${fmt(p.price)}</span>` : fmt(p.price));
  const priceHTML = (p) => p.catalogUnavailable ? '<span class="price">Fiyat bilgisi alınamıyor</span>' : p.stockQty === 0 ? '<span class="price">Stokta yok</span>' : `<span class="price${p.disc ? ' price--sale' : ''}">${priceInner(p)}</span>`;

  // Görsel yüklenemezse bozuk simge yerine arka plan görünsün
  document.addEventListener('error', (e) => {
    if (e.target.tagName !== 'IMG') return;
    const t = e.target.closest('.thumb');
    if (t) { // bozuk küçük resmi kaldır; 2'den az kalırsa galeriyi gizle
      const box = t.parentElement;
      t.remove();
            if (box.children.length < 2) {
        box.hidden = true;
        const st = document.querySelector('.detail__stage');
        if (st) st.classList.remove('is-gallery');
      }
      return;
    }
    e.target.style.visibility = 'hidden';
  }, true);

  /* ---------- Durum ---------- */
  let PRODUCTS = [];
  let byId = new Map();
  let catalogUnavailable = false;
  let filter = 'all';
  let cart = load();

  function load() {
    try {
      const d = JSON.parse(localStorage.getItem(KEY) || '[]');
      if (!Array.isArray(d)) return [];
      const m = new Map();
      d.forEach((i) => {
        const id = String(i && i.id), q = Math.floor(Number(i && i.qty));
        if (id && q > 0) m.set(id, Math.min(MAX_QTY, (m.get(id) || 0) + q));
      });
      return Array.from(m, ([id, qty]) => ({ id, qty }));
    } catch (e) { return []; }
  }
  function save() {
    try {
      if (cart.length) localStorage.setItem(KEY, JSON.stringify(cart)); else localStorage.removeItem(KEY);
    } catch (e) { /* depolama kapalı */ }
  }

  // Sepet satırları her zaman güncel kataloğa göre hesaplanır (ad ve fiyat katalogdan gelir)
  const lines = () => cart.filter((i) => byId.has(i.id)).map((i) => ({ id: i.id, qty: i.qty, p: byId.get(i.id) }));
  const count = () => lines().reduce((s, l) => s + l.qty, 0);
  function totals() {
    const sub = money(lines().reduce((s, l) => s + l.p.price * l.qty, 0));
    const ship = sub === 0 || sub >= FREE_SHIPPING_LIMIT ? 0 : SHIPPING_FEE;
    return { sub, ship, total: money(sub + ship) };
  }

  /* ---------- MySQL admin kataloğu ---------- */
  async function loadStoreSettings() {
    try {
      const res = await fetch('api/store-settings.php', { cache: 'no-store' });
      if (!res.ok) throw new Error('Mağaza ayarları API HTTP ' + res.status);
      const settings = await res.json();
      const limit = Number(settings.freeShippingLimit);
      const fee = Number(settings.shippingFee);
      if (Number.isFinite(limit) && limit >= 0) FREE_SHIPPING_LIMIT = limit;
      if (Number.isFinite(fee) && fee >= 0) SHIPPING_FEE = fee;
      const threshold = Number(settings.lowStockThreshold);
      if (Number.isFinite(threshold) && threshold >= 0) LOW_STOCK_THRESHOLD = threshold;
      const headline = $('#freeShippingHeadline');
      if (headline) headline.textContent = `${fmt(FREE_SHIPPING_LIMIT)} ve Üzeri Siparişlerde Kargo Ücretsiz`;
      const faqLimit = $('#shippingFaqLimit');
      if (faqLimit) faqLimit.textContent = fmt(FREE_SHIPPING_LIMIT);
      const topShipping = $('#topShippingText');
      if (topShipping) topShipping.textContent = `${fmt(FREE_SHIPPING_LIMIT)} ve Üzeri Siparişlerde Kargo Ücretsiz! | Doğrudan Üreticiden Taze Karadeniz Fındığı`;
      const faqFee = $('#shippingFaqFee');
      if (faqFee) faqFee.textContent = fmt(SHIPPING_FEE);
    } catch (err) {
      console.warn('Mağaza ayarları yüklenemedi; varsayılan kargo değerleri kullanılıyor:', err);
    }
  }

  function normalizeProductRow(p) {
    return {
      id: String(p.id), url: String(p.url || ''), canonical: String(p.canonical_url || ''),
      cat: slug(p.category), catLabel: String(p.category || 'Genel'), name: String(p.title || ''),
      badges: Array.isArray(p.badges) ? p.badges.map(String) : [],
      weight: String(p.weight || ''), chip: String(p.chip || ''),
      price: Number(p.price) || 0, old: Number(p.old_price) || 0,
      disc: Number(p.old_price) > Number(p.price) ? Math.round((Number(p.old_price) - Number(p.price)) * 100 / Number(p.old_price)) : 0,
      img: safeImg(String(p.image || '')), img2: safeImg(String(p.image2 || '')), img3: safeImg(String(p.image3 || '')),
      stockQty: p.stockQty === null || p.stockQty === undefined ? null : Number(p.stockQty),
      newHarvest: Boolean(p.newHarvest), desc: String(p.description || ''), ing: String(p.ingredients || ''),
      seoTitle: String(p.seo_title || ''), seoDescription: String(p.seo_description || '')
    };
  }

  async function loadCatalog() {
    try {
      const localRes = await fetch('api/products.php', { cache: 'no-store' });
      if (!localRes.ok) throw new Error('Ürün API HTTP ' + localRes.status);
      const rows = await localRes.json();
      if (!Array.isArray(rows)) throw new Error('Ürün API geçersiz yanıt döndürdü');
      catalogUnavailable = false;
      if (!rows.length) return [];
      return rows.map(normalizeProductRow).filter((p) => p.name && p.price >= 0);
    } catch (err) {
      catalogUnavailable = true;
      console.error('MySQL kataloğu okunamadı; satış kontrolleri kapatıldı:', err);
      return FALLBACK_PRODUCTS.map((product) => ({ ...product, price: 0, old: 0, disc: 0, stockQty: 0, catalogUnavailable: true }));
    }
  }


  function productAddress(product) {
    if (!product.url) return '';
    try {
      const url = new URL(product.url, document.baseURI);
      return url.origin === location.origin ? url.pathname : '';
    } catch (error) { return ''; }
  }

  function showProductMetadata(product) {
    document.title = product.seoTitle || `${product.name} | Köken Fındık`;
    const description = product.seoDescription || product.desc || `${product.name} — Köken Fındık`;
    const values = {
      'meta[name="description"]': description,
      'meta[property="og:title"]': document.title,
      'meta[property="og:description"]': description,
      'meta[property="og:url"]': product.canonical,
      'meta[property="og:type"]': 'product',
      'meta[name="twitter:title"]': document.title,
      'meta[name="twitter:description"]': description
    };
    for (const [selector, value] of Object.entries(values)) {
      if (value) document.querySelector(selector)?.setAttribute('content', value);
    }
    if (product.canonical) document.querySelector('link[rel="canonical"]')?.setAttribute('href', product.canonical);
    if (product.img) {
      const image = new URL(product.img, document.baseURI).href;
      for (const selector of ['meta[property="og:image"]', 'meta[property="og:image:secure_url"]', 'meta[name="twitter:image"]']) {
        document.querySelector(selector)?.setAttribute('content', image);
      }
    }
  }

  function restorePageMetadata() {
    document.title = originalTitle;
    for (const [element, value] of originalMetadata) {
      const attribute = element.tagName === 'LINK' ? 'href' : 'content';
      if (value === null) element.removeAttribute(attribute);
      else element.setAttribute(attribute, value);
    }
  }

  // Direct product pages keep their server-rendered schema; home cards link to real product URLs.
  function updateSchema() {
    const abs = (url) => { try { return new URL(url, document.baseURI).href; } catch (error) { return undefined; } };
    const schema = (p) => ({
      '@context': 'https://schema.org', '@type': 'Product',
      ...(p.canonical ? { '@id': p.canonical + '#product', url: p.canonical } : {}),
      name: label(p), sku: p.id,
      description: p.desc || p.seoDescription || p.name + ' — Köken Fındık',
      image: [p.img, p.img2, p.img3].filter(Boolean).map(abs).filter(Boolean),
      brand: { '@type': 'Brand', name: 'Köken Fındık' }, category: p.catLabel || 'Fındık',
      offers: {
        '@type': 'Offer', priceCurrency: 'TRY', price: String(p.price),
        availability: p.stockQty === 0 ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
        itemCondition: 'https://schema.org/NewCondition',
        url: p.canonical || abs('index.html#urunler')
      }
    });
    const element = $('#ldProducts');
    const selected = $('#detail')?.classList.contains('on') ? byId.get(dId) : null;
    if (element) element.textContent = JSON.stringify(selected && !selected.catalogUnavailable
      ? schema(selected) : PRODUCTS.filter((p) => !p.catalogUnavailable).map(schema));
    const business = $('#ldBusiness');
    if (business && /^https?:$/.test(location.protocol)) business.textContent = business.textContent.split('https://www.ALAN-ADINIZ.com').join(location.origin);
  }

  function renderFilters() {
    if (!$('#filters')) return;
    const cats = new Map();
    PRODUCTS.forEach((p) => {
      if (p.cat && !cats.has(p.cat)) cats.set(p.cat, p.catLabel || CAT_NAMES[p.cat] || p.cat);
    });
    $('#filters').innerHTML =
      `<button type="button" data-f="all" aria-pressed="${filter === 'all'}">Tümü</button>` +
      Array.from(cats, ([k, l]) => `<button type="button" data-f="${esc(k)}" aria-pressed="${filter === k}">${esc(l)}</button>`).join('');
  }

  function setProducts(list, complete = true) {
    $('#grid')?.setAttribute('aria-busy', 'false');
    list.forEach((product) => { if (!Number.isFinite(product.stockQty)) product.stockQty = null; });
    PRODUCTS = list;
    byId = new Map(list.map((p) => [p.id, p]));
    const before = JSON.stringify(cart);
    if (complete) cart = cart.filter((i) => byId.has(i.id));
    cart.forEach((i) => { const p = byId.get(i.id); if (p && p.stockQty !== null) i.qty = Math.min(i.qty, p.stockQty); });
    cart = cart.filter((i) => i.qty > 0);
    if (JSON.stringify(cart) !== before) save();
    if (filter !== 'all' && !list.some((p) => p.cat === filter)) filter = 'all';
    renderFilters();
    renderGrid();
    render();
    updateSchema();

  }




  /* ---------- Vitrin ---------- */

    /* ---------- Kart üzeri durumsal adet butonu ---------- */
  const cartQty = (id) => { const i = cart.find((x) => x.id === id); return i ? i.qty : 0; };

  function addControlHTML(id) {
    const q = cartQty(id), e = esc(id);
    const p = byId.get(id);
    if (p && p.catalogUnavailable) return `<button type="button" class="btn btn--ghost" disabled>Mağaza geçici olarak kapalı</button>`;
    if (p && p.stockQty === 0 && q === 0) return `<button type="button" class="btn btn--ghost" disabled>Tükendi</button>`;
    return q > 0
      ? `<div class="qty-control" data-ctl="${e}"><button type="button" data-dec="${e}" aria-label="Adedi azalt">−</button><span aria-live="polite">${q}</span><button type="button" data-inc="${e}" aria-label="Adedi arttır">+</button></div>`
      : `<button type="button" class="btn btn--primary" data-add="${e}" data-ctl="${e}">Sepete Ekle</button>`;
  }

  // Sepet her değiştiğinde ana sayfadaki kart butonlarını anlık eşitler
  function syncCardControls() {
    $$('#grid [data-ctl]').forEach((el) => {
      const id = el.dataset.ctl, q = cartQty(id);
      const isQty = el.classList.contains('qty-control');
      if (q > 0 && isQty) { el.querySelector('span').textContent = q; return; }
      if ((q > 0) === isQty) return;
      const hadFocus = el.contains(document.activeElement);
      el.outerHTML = addControlHTML(id);
      if (hadFocus) {
        const n = $$('#grid [data-ctl]').find((x) => x.dataset.ctl === id);
        if (n) (n.querySelector('[data-inc]') || n).focus();
      }
    });
  }


  function cardHTML(p) {
    const id = esc(p.id);
    const address = productAddress(p);
    return `
    <article class="card">
      <${address ? `a href="${esc(address)}"` : 'div'} class="card__img" data-view="${id}">
        ${p.img ? `<img src="${esc(p.img)}" alt="${esc(label(p))} - Köken Fındık ürünü" width="400" height="300" loading="lazy">` : ''}
                <div class="card__tags">
          ${p.disc ? `<span class="disc">%${p.disc} İndirim</span>` : ''}
          ${p.stockQty === 0 ? '<span class="stock-tag stock-tag--out">Stokta yok</span>' : (p.stockQty !== null && p.stockQty <= LOW_STOCK_THRESHOLD ? `<span class="stock-tag stock-tag--low">Stok azalıyor · Son ${p.stockQty} adet</span>` : '')}
          ${p.newHarvest ? '<span class="tag tag--new">Yeni Mahsul</span>' : ''}
        </div>
      </${address ? 'a' : 'div'}>
      <div class="card__body">
        <span class="chip">${esc(p.chip || p.weight || 'Fındık')}</span>
        <h3>${address ? `<a class="product-detail-link" href="${esc(address)}" data-view="${id}">${esc(p.name)}</a>` : esc(p.name)}</h3>
        ${badgeHTML(p, 'badges--sm')}
        <div class="card__bottom">
                   ${priceHTML(p)}
          <div class="card__actions">
            ${address ? `<a class="btn btn--ghost" href="${esc(address)}" data-view="${id}">İncele</a>` : `<button type="button" class="btn btn--ghost" data-view="${id}">İncele</button>`}
                        ${addControlHTML(p.id)}
          </div>
        </div>
      </div>
    </article>`;
  }

  function renderGrid() {
    if (!$('#grid')) return;
    const query = ($('#productSearch')?.value || '').trim().toLocaleLowerCase('tr');
    const list = PRODUCTS.filter((p) => (filter === 'all' || p.cat === filter) && (!query || `${p.name} ${p.catLabel} ${p.desc} ${p.ing} ${p.id}`.toLocaleLowerCase('tr').includes(query)));
    const sort = $('#productSort')?.value || 'newest';
    if (sort === 'price-asc') list.sort((a, b) => a.price - b.price);
    else if (sort === 'price-desc') list.sort((a, b) => b.price - a.price);
    else if (sort === 'name') list.sort((a, b) => a.name.localeCompare(b.name, 'tr'));
    $('#grid').innerHTML = list.length ? list.map(cardHTML).join('') : `<p class="grid__msg">${query ? 'Aramanızla eşleşen ürün bulunamadı.' : 'Bu kategoride ürün bulunamadı.'}</p>`;
  }

  $('#filters')?.addEventListener('click', (e) => {
    const b = e.target.closest('button[data-f]');
    if (!b) return;
    filter = b.dataset.f;
    $$('#filters button').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
    renderGrid();
  });
  $('#productSearch')?.addEventListener('input', renderGrid);
  $('#productSort')?.addEventListener('change', renderGrid);

       $('#grid')?.addEventListener('click', (e) => {
    const add = e.target.closest('[data-add]');
    if (add) { addToCart(add.dataset.add, 1, true); showToast(add.dataset.add); return; }
    const inc = e.target.closest('[data-inc]');
    if (inc) { addToCart(inc.dataset.inc, 1, true); showToast(inc.dataset.inc); return; }
    const dec = e.target.closest('[data-dec]');
    if (dec) { changeCartQty(dec.dataset.dec, -1); return; }
    const v = e.target.closest('[data-view]');
    if (v) {
      if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
      if (v.matches('a[href]')) e.preventDefault();
      openDetail(v.dataset.view, v);
    }
  });

  function flash(btn) {
    const t = btn.textContent;
    btn.textContent = 'Eklendi ✓';
    btn.classList.add('added');
    setTimeout(() => { btn.textContent = t; btn.classList.remove('added'); }, 900);
  }

  /* ---------- Sepet ---------- */
   // quiet = true: sepet çekmecesini otomatik açmaz; çağıran bildirim gösterebilir.
  function addToCart(id, n, quiet) {
    if (!byId.has(id)) return;
    const product = byId.get(id);
    if (catalogUnavailable || product.catalogUnavailable) { showToastMessage('Ürün bilgileri şu anda alınamıyor. Lütfen biraz sonra tekrar deneyin.'); return; }
    const f = cart.find((i) => i.id === id);
    const desired = (f?.qty || 0) + n;
    if (product.stockQty !== null && desired > product.stockQty) {
      showToastMessage(product.stockQty > 0 ? `Stokta yalnızca ${product.stockQty} adet var.` : 'Bu ürün şu anda stokta yok.');
      return;
    }
    if (f) f.qty = Math.min(MAX_QTY, desired); else cart.push({ id, qty: Math.min(MAX_QTY, desired) });
    commit();
    const c = $('#openCart');
    c.classList.remove('bump'); void c.offsetWidth; c.classList.add('bump');
    if (!quiet) openDrawer();
  }

  // Adet 1 iken azaltılırsa ürün sepetten silinir (kart butonu "Sepete Ekle"ye döner)
  function changeCartQty(id, delta) {
    const it = cart.find((i) => i.id === id);
    if (!it) return;
    const product = byId.get(id);
    if (delta > 0 && product && product.stockQty !== null && it.qty >= product.stockQty) { showToastMessage(`Stokta yalnızca ${product.stockQty} adet var.`); return; }
    it.qty = Math.min(MAX_QTY, it.qty + delta);
    if (it.qty <= 0) cart = cart.filter((i) => i !== it);
    commit();
  }

  /* ---------- Sepet geri bildirimi (3 sn) ---------- */
  const toast = $('#addToast');
  let toastTimer = null;

  function showToastMessage(message) {
    $('#toastMsg').textContent = message;

    // Bildirim zaten açıksa giriş geçişini ve süre çubuğunu yeniden başlat.
    toast.style.transition = 'none';
    toast.classList.remove('on');
    void toast.querySelector('.toast__timer').offsetWidth;
    toast.style.removeProperty('transition');
    toast.classList.add('on');

    clearTimeout(toastTimer);
    toastTimer = setTimeout(hideToast, 3000);
  }

  function showToast(id) {
    const p = byId.get(id);
    if (!p) return;
    const q = cartQty(id);
    showToastMessage(q > 1 ? `${p.name}: sepette ${q} adet` : `${p.name} sepete eklendi`);
  }
  function hideToast() { toast.classList.remove('on'); clearTimeout(toastTimer); }

  $('#toastContinue').addEventListener('click', hideToast);
  $('#toastCheckout').addEventListener('click', () => { hideToast(); openDrawer(); });



    /* ---------- Sepet içi kargo tamamlayıcı öneri ---------- */
  let upsellId = null;

  // Sepette olmayan ürünlerden önce Ezme kategorisini, yoksa sıradaki ürünü önerir
  function pickUpsell() {
    const inCart = new Set(cart.map((i) => i.id));
    const rest = PRODUCTS.filter((p) => !inCart.has(p.id) && !p.catalogUnavailable && (p.stockQty === null || p.stockQty > cartQty(p.id)));
    return rest.find((p) => p.cat === 'ezme') || rest[0] || null;
  }

  $('#upsellAdd').addEventListener('click', () => { if (upsellId) addToCart(upsellId, 1, true); });

  function render() {
    const { sub, ship, total } = totals();
    const ls = lines();

    $('#cartItems').innerHTML = ls.length ? ls.map((l) => `
      <li class="cart-item">
        <div><strong>${esc(label(l.p))}</strong><small>${fmt(l.p.price)}</small></div>
        <div class="side">
          <div class="qty">
            <button type="button" data-act="dec" data-id="${esc(l.id)}" aria-label="Azalt">−</button>
            <span>${l.qty}</span>
            <button type="button" data-act="inc" data-id="${esc(l.id)}" aria-label="Arttır">+</button>
          </div>
          <strong>${fmt(l.p.price * l.qty)}</strong>
          <button type="button" class="del" data-act="del" data-id="${esc(l.id)}">Sil</button>
        </div>
      </li>`).join('') : '<li class="empty">Sepetiniz şu an boş.</li>';

    // Kargo çubuğu
    const pct = Math.min(100, Math.floor((sub / FREE_SHIPPING_LIMIT) * 100));
    $('#ship').classList.toggle('free', sub >= FREE_SHIPPING_LIMIT);
    $('#shipBar').style.width = pct + '%';
    $('#shipPct').textContent = '%' + pct;
    $('#shipText').textContent =
      sub >= FREE_SHIPPING_LIMIT ? 'Tebrikler, kargo ücretsiz!'
      : sub === 0 ? `${fmt(FREE_SHIPPING_LIMIT)} ve üzeri siparişlerde kargo ücretsiz`
      : `Ücretsiz kargoya ${fmt(FREE_SHIPPING_LIMIT - sub)} kaldı`;

    $('#subTotal').textContent = fmt(sub);
    const sc = $('#shipCost');
    sc.textContent = sub === 0 ? '—' : ship ? fmt(ship) : 'Ücretsiz';
    sc.classList.toggle('free', sub > 0 && !ship);
    $('#grandTotal').textContent = fmt(total);

    const badge = $('#cartCount');
    badge.textContent = count();
    badge.classList.toggle('on', count() > 0);

    
    // Mobil alt çubuk
    $('#mbarCount').textContent = count();
    $('#mbarTotal').textContent = fmt(total);
    $('#checkoutBtn').disabled = !ls.length;

    
       // Tepe kargo bandı
    const banner = $('#topShippingBanner');
    if (banner) {
      $('#topShippingText').textContent =
        sub === 0 ? `${fmt(FREE_SHIPPING_LIMIT)} ve Üzeri Siparişlerde Kargo Ücretsiz! | Doğrudan Üreticiden Taze Karadeniz Fındığı`
        : sub < FREE_SHIPPING_LIMIT ? `Ücretsiz kargoya ${fmt(FREE_SHIPPING_LIMIT - sub)} kaldı!`
        : '🎉 Tebrikler! Siparişinizde Kargo Ücretsiz!';
      $('#topShippingBar').style.width = Math.min(100, Math.floor((sub / FREE_SHIPPING_LIMIT) * 100)) + '%';
      banner.classList.toggle('free', sub >= FREE_SHIPPING_LIMIT);
    }

    // Kargo tamamlayıcı öneri: yalnızca 0 < sepet < limit iken görünür
    const up = sub > 0 && sub < FREE_SHIPPING_LIMIT ? pickUpsell() : null;
    upsellId = up ? up.id : null;
    $('#upsell').hidden = !up;
    if (up) {
      const remaining = money(FREE_SHIPPING_LIMIT - sub - up.price);
      $('#upsellText').textContent = remaining > 0
        ? `Bu ürünü eklerseniz ücretsiz kargoya ${fmt(remaining)} kalır.`
        : 'Bu ürünü ekleyin, kargonuz ücretsiz olsun.';
      $('#upsellName').textContent = label(up);
      $('#upsellPrice').textContent = fmt(up.price);
    }
    syncCardControls();
  }

  const commit = () => { save(); render(); };

  $('#cartItems').addEventListener('click', (e) => {
    const b = e.target.closest('button[data-act]');
    if (!b) return;
    const it = cart.find((i) => i.id === b.dataset.id);
    if (!it) return;
    if (b.dataset.act === 'del') cart = cart.filter((i) => i !== it);
    else {
      it.qty = Math.min(MAX_QTY, it.qty + (b.dataset.act === 'inc' ? 1 : -1));
      if (it.qty <= 0) cart = cart.filter((i) => i !== it);
    }
    commit();
  });

  /* ---------- Çekmece & modallar ---------- */
  const drawer = $('#drawer'), overlay = $('#overlay');
  const backgroundState = new Map();

  function currentDialog() {
    const openModals = $$('.modal.on');
    if (openModals.length) return openModals[openModals.length - 1].querySelector('[role="dialog"]') || openModals[openModals.length - 1];
    return drawer.classList.contains('on') ? drawer : null;
  }

  function syncOverlayState() {
    const openModals = $$('.modal.on');
    const topModal = openModals[openModals.length - 1] || null;
    const drawerOpen = drawer.classList.contains('on');
    const locked = Boolean(topModal || drawerOpen);
    const allowed = new Set();
    if (topModal) allowed.add(topModal);
    else if (drawerOpen) { allowed.add(drawer); allowed.add(overlay); }
    document.body.classList.toggle('overlay-open', locked);
    document.body.classList.toggle('no-scroll', locked);
    Array.from(document.body.children).forEach((element) => {
      const shouldInert = locked && !allowed.has(element);
      if (shouldInert) {
        if (!backgroundState.has(element)) backgroundState.set(element, { inert: element.inert, ariaHidden: element.getAttribute('aria-hidden') });
        element.inert = true;
        element.setAttribute('aria-hidden', 'true');
      } else if (backgroundState.has(element)) {
        const previous = backgroundState.get(element);
        element.inert = previous.inert;
        if (previous.ariaHidden === null) element.removeAttribute('aria-hidden');
        else element.setAttribute('aria-hidden', previous.ariaHidden);
        backgroundState.delete(element);
      }
    });
    const hideWhatsApp = locked;
    [$('#waIsland'), $('#mbarWhatsApp')].filter(Boolean).forEach((link) => {
      link.hidden = hideWhatsApp;
      link.inert = hideWhatsApp;
      if (hideWhatsApp) link.setAttribute('aria-hidden', 'true');
      else link.removeAttribute('aria-hidden');
    });
  }

  function focusableWithin(layer) {
    return Array.from(layer.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'))
      .filter((element) => !element.closest('[hidden],[inert]') && element.getClientRects().length > 0 && getComputedStyle(element).visibility !== 'hidden');
  }

  function focusLayer(layer) {
    if (!layer) return;
    const focusInside = () => {
      if (!layer.isConnected || layer.closest('[hidden]')) return;
      const target = focusableWithin(layer)[0] || layer;
      if (target === layer && !layer.hasAttribute('tabindex')) layer.setAttribute('tabindex', '-1');
      target.focus({ preventScroll: true });
    };
    focusInside();
    // Reassert after the modal's display/visibility transition and the opener's click default action.
    window.setTimeout(() => {
      if (!layer.contains(document.activeElement)) focusInside();
    }, 0);
  }

  function restoreFocus(opener, context) {
    if (opener instanceof HTMLElement && opener.isConnected && !opener.closest('[inert],[hidden]')) {
      opener.focus({ preventScroll: true });
      if (document.activeElement === opener) return;
    }
    const fallback = context === 'detail' ? ($('#productSearch') || $('#openCart')) : ($('#openCart') || $('#productSearch'));
    if (fallback) fallback.focus({ preventScroll: true });
  }

  async function ensureFullCatalog() {
    if (fullCatalogLoaded) return;
    if (catalogRequest) return catalogRequest;
    catalogRequest = (async () => {
      const list = await loadCatalog();
      if (!catalogUnavailable) {
        fullCatalogLoaded = true;
        setProducts(list);
      }
      syncDetailQty();
    })().finally(() => { catalogRequest = null; });
    return catalogRequest;
  }

  async function openDrawer() {
    if (isProductPage) await ensureFullCatalog();
    drawer._returnFocusElement = document.activeElement;
    drawer.classList.add('on');
    overlay.classList.add('on');
    drawer.setAttribute('aria-hidden', 'false');
    syncOverlayState();
    focusLayer(drawer);
  }

  hideToast();

  function closeDrawer() {
    if (!drawer.classList.contains('on')) return;
    const opener = drawer._returnFocusElement;
    drawer.classList.remove('on');
    overlay.classList.remove('on');
    drawer.setAttribute('aria-hidden', 'true');
    syncOverlayState();
    restoreFocus(opener, 'drawer');
  }

  function openModal(id, opener = document.activeElement) {
    const modal = $('#' + id);
    if (!modal) return;
    modal._returnFocusElement = opener instanceof HTMLElement ? opener : document.activeElement;
    modal.classList.add('on');
    modal.setAttribute('aria-hidden', 'false');
    syncOverlayState();
    focusLayer(currentDialog());
  }

  function closeModal(modal, fromHistory = false) {
    if (!modal || !modal.classList.contains('on')) return;
    if (modal.id === 'detail' && detailReturnUrl && !fromHistory) {
      history.back();
      return;
    }
    const opener = modal._returnFocusElement;
    const context = modal.id === 'detail' ? 'detail' : 'modal';
    modal.classList.remove('on');
    modal.setAttribute('aria-hidden', 'true');
    syncOverlayState();
    restoreFocus(opener, context);
    if (modal.id === 'detail') {
      detailReturnUrl = null;
      restorePageMetadata();
      updateSchema();
    }
  }

  $('#openCart').addEventListener('click', openDrawer);
  $('#mbarCart').addEventListener('click', openDrawer);
  $('#closeCart').addEventListener('click', closeDrawer);
  overlay.addEventListener('click', closeDrawer);
  document.addEventListener('click', (event) => {
    const closeButton = event.target.closest('[data-close]');
    if (closeButton) { event.preventDefault(); closeModal(closeButton.closest('.modal')); }
  });
  document.addEventListener('focusin', (event) => {
    const layer = currentDialog();
    if (layer && !layer.contains(event.target)) focusLayer(layer);
  });
  document.addEventListener('keydown', (event) => {
    const openModals = $$('.modal.on');
    const activeLayer = currentDialog();
    if (event.key === 'Tab' && activeLayer) {
      const focusable = focusableWithin(activeLayer);
      if (!focusable.length) { event.preventDefault(); focusLayer(activeLayer); return; }
      const first = focusable[0], last = focusable[focusable.length - 1];
      const index = focusable.indexOf(document.activeElement);
      if (event.shiftKey && index <= 0) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && (index === -1 || index === focusable.length - 1)) { event.preventDefault(); first.focus(); }
      return;
    }
    if (event.key !== 'Escape') return;
    if (openModals.length) closeModal(openModals[openModals.length - 1]);
    else if (drawer.classList.contains('on')) closeDrawer();
  });  window.addEventListener('storage', async (e) => { if (e.key === KEY) { cart = load(); if (isProductPage && cart.some((item) => !byId.has(item.id))) await ensureFullCatalog(); render(); } });


  /* ---------- Rozetler ve SSS ---------- */
  const FAQ_CATS = ['ezme']; // SSS'nin gösterileceği kategoriler. Tüm ürünlerde göstermek için: ['ezme', 'kavrulmus', 'cig', 'paketleme']
  const FAQ = [
    ['Ürünün üzerinde yağ tabakası birikmesi normal mi?', 'Evet. Kıvam artırıcı ve emülgatör bulunmadığından fındığın kendi doğal yağı üste toplanır. Tüketmeden önce karıştırmanız yeterlidir.'],
    ['İlave palm yağı, trans yağ veya koruyucu var mı?', 'Hayır. Yalnızca birinci kalite Karadeniz fındığı ve pancar şekeri içerir. İlave yağ veya katkı barındırmaz.'],
    ['Kavanoz açıldıktan sonra nasıl saklanmalıdır?', 'Buzdolabına koymayınız. 18-24°C oda sıcaklığında, doğrudan güneş almayan serin ve kuru yerde saklayınız. Raf ömrü 12 aydır.'],
    ['Siparişim ne zaman kargoya verilir?', "Haftalık taze üretim döngüsüyle hazırlanır; saat 14:00'e kadar verilen siparişler aynı gün kargoya teslim edilir."]
  ];

  const catBadges = (p) => Array.isArray(p.badges) ? p.badges : [];
  const badgeHTML = (p, cls) => {
    const b = catBadges(p);
    return b.length ? `<ul class="badges badges--earth ${cls || ''}">${b.map((x) => `<li>${esc(x)}</li>`).join('')}</ul>` : '';
  };

  function renderFaq() {
    $('#faqList').innerHTML = FAQ.map(([q, a], i) => `
      <div class="faq__item">
        <button type="button" class="faq__q" id="faqQ${i}" aria-expanded="false" aria-controls="faqA${i}">${esc(q)}<span class="faq__ic" aria-hidden="true"></span></button>
        <div class="faq__a" id="faqA${i}" role="region" aria-labelledby="faqQ${i}"><div><p>${esc(a)}</p></div></div>
      </div>`).join('');
  }

  $('#faqList').addEventListener('click', (e) => {
    const b = e.target.closest('.faq__q');
    if (!b) return;
    const item = b.parentElement;
    const open = !item.classList.contains('open');
    item.classList.toggle('open', open);
    b.setAttribute('aria-expanded', String(open));
  });

    // Ana sayfa SSS akordiyonu
  $('#homeFaq')?.addEventListener('click', (e) => {
    const b = e.target.closest('.faq__q');
    if (!b) return;
    const item = b.parentElement;
    const open = !item.classList.contains('open');
    item.classList.toggle('open', open);
    b.setAttribute('aria-expanded', String(open));
  });

  /* ---------- Ürün detay modalı ---------- */
  let dId = null, dQty = 1;

  function syncDetailQty() {
    const p = byId.get(dId);
    if (!p) return;
    $('#dQty').textContent = dQty;
    const unavailable = catalogUnavailable || p.catalogUnavailable;
    const outOfStock = p.stockQty === 0;
    $('#dAdd').textContent = unavailable ? 'Mağaza geçici olarak kapalı' : outOfStock ? 'Stokta yok' : `Sepete Ekle · ${fmt(p.price * dQty)}`;
    $('#dAdd').disabled = unavailable || outOfStock;
    $('#dPlus').disabled = unavailable || outOfStock;
    $('#dMinus').disabled = unavailable || outOfStock;
    $('#restockForm').hidden = unavailable || !outOfStock;
  }

  // Ana görsel + (varsa) küçük önizlemeler
  function setMainImage(src, alt) {
    const img = $('#dImg');
    img.style.visibility = '';
    if (src) { img.src = src; img.alt = alt; img.hidden = false; }
    else { img.removeAttribute('src'); img.hidden = true; }
  }

  function buildThumbs(imgs, name) {
    const box = $('#dThumbs');
    $('.detail__stage').classList.toggle('is-gallery', imgs.length > 1); // imleç ipucu yalnızca çoklu görselde
    if (imgs.length < 2) { box.innerHTML = ''; box.hidden = true; return; } // tek görsel: önizleme alanı yok
    box.innerHTML = imgs.map((src, i) =>
      `<button type="button" class="thumb${i === 0 ? ' on' : ''}" data-src="${esc(src)}" aria-label="Görsel ${i + 1}" aria-pressed="${i === 0}"><img src="${esc(src)}" alt="${esc(name)} - görsel ${i + 1}" loading="lazy"></button>`
    ).join('');
    box.hidden = false;
  }

  // Tek noktadan seçim: ana görsel + aktif küçük resim her zaman birlikte güncellenir
  function selectThumb(t) {
    $$('#dThumbs .thumb').forEach((x) => {
      x.classList.toggle('on', x === t);
      x.setAttribute('aria-pressed', String(x === t));
    });
    setMainImage(t.dataset.src, label(byId.get(dId)));
  }

  $('#dThumbs').addEventListener('click', (e) => {
    const b = e.target.closest('.thumb');
    if (b) selectThumb(b);
  });

  // Büyük görsele tıklayınca sıradaki görsele geç (sondan sonra başa döner)
  $('.detail__stage').addEventListener('click', () => {
    const ts = $$('#dThumbs .thumb');
    if (ts.length < 2) return;
    const i = ts.findIndex((x) => x.classList.contains('on'));
    selectThumb(ts[(i + 1) % ts.length]);
  });

  function openDetail(id, opener = document.activeElement, fromHistory = false) {
    const p = byId.get(id);
    if (!p) return;
    dId = id; dQty = 1;

    const img = $('#dImg');
    img.style.visibility = '';
    if (p.img) { img.src = p.img; img.alt = label(p); img.hidden = false; }
    else { img.removeAttribute('src'); img.hidden = true; }

    const chip = $('#dChip');
    chip.textContent = p.chip || p.weight;
    chip.hidden = !(p.chip || p.weight);

    $('#dName').textContent = p.name;
    const badges = $('#dCatBadges');
    badges.innerHTML = catBadges(p).map((text) => `<li>${esc(text)}</li>`).join('');
    badges.hidden = !badges.children.length;
    $('#restockForm').dataset.productId = String(p.id);
    $('#restockMessage').textContent = '';
    $('#restockEmail').value = '';
        const imgs = Array.from(new Set([p.img, p.img2, p.img3].filter(Boolean)));
    setMainImage(imgs[0], label(p));
    buildThumbs(imgs, label(p));

    const dd = $('#dDisc');
    dd.textContent = p.disc ? `%${p.disc} İndirim` : '';
    dd.hidden = !p.disc;

    //const chip = $('#dChip');
   // chip.textContent = p.chip || p.weight;
   // chip.hidden = !(p.chip || p.weight);

    $('#dName').textContent = p.name;
    if (p.catalogUnavailable || catalogUnavailable) $('#dPrice').textContent = 'Fiyat bilgisi alınamıyor';
    else $('#dPrice').innerHTML = priceInner(p);
    $('#dPrice').classList.toggle('price--sale', !!p.disc);

    const desc = $('#dDesc');
    desc.textContent = p.desc;
    desc.hidden = !p.desc;

    $('#dIng').textContent = p.ing;
    $('#dIngSec').hidden = !p.ing;

    $('.detail').scrollTop = 0;
    syncDetailQty();
    openModal('detail', opener);
    const address = productAddress(p);
    if (address) {
      if (!fromHistory) {
        detailReturnUrl = location.pathname + location.search + location.hash;
        history.pushState({ ...(history.state || {}), kokenProduct: p.id, kokenReturn: detailReturnUrl }, '', address);
      } else {
        detailReturnUrl = history.state?.kokenReturn || 'index.html#urunler';
      }
      showProductMetadata(p);
    }
    updateSchema();
  }

  $('#dMinus').addEventListener('click', () => { dQty = Math.max(1, dQty - 1); syncDetailQty(); });
  $('#dPlus').addEventListener('click', () => { const p = byId.get(dId); if (p && p.stockQty !== null && dQty + cartQty(dId) >= p.stockQty) { showToastMessage(`Stokta yalnızca ${p.stockQty} adet var.`); return; } dQty = Math.min(MAX_QTY, dQty + 1); syncDetailQty(); });
  $('#dAdd').addEventListener('click', () => {
    const id = dId, n = dQty;
    closeModal($('#detail'));
    const qtyBefore = cartQty(id);
    addToCart(id, n, true);
    if (cartQty(id) > qtyBefore) showToast(id);
  });
  $('#restockForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget, button = $('#restockSubmit'), message = $('#restockMessage');
    if (!$('#restockConsent').checked) { message.textContent = 'Talep için onay kutusunu işaretleyin.'; return; }
    const originalLabel = button.textContent;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.textContent = 'Kaydediliyor…';
    message.textContent = 'Talebiniz kaydediliyor…';
    try {
      const tokenResponse = await fetch('api/restock-notifications.php', { cache: 'no-store' });
      const tokenData = await tokenResponse.json();
      const response = await fetch('api/restock-notifications.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': tokenData.csrfToken }, body: JSON.stringify({ productId: Number(form.dataset.productId), email: $('#restockEmail').value, consent: $('#restockConsent').checked }) });
      const data = await response.json(); if (!response.ok) throw new Error(data.error || 'Talep kaydedilemedi.');
      message.textContent = data.message; form.reset();
    } catch (error) { message.textContent = error.message || 'Talep kaydedilemedi.'; }
    finally { button.disabled = false; button.removeAttribute('aria-busy'); button.textContent = originalLabel; }
  });

  /* ---------- Yasal metinler (footer linkleri) ---------- */
  const NOTE = '<p class="note">YAYIN ÖNCESİ TASLAK — <span class="legal-required">Sarı işaretli alanları tamamlayın</span> ve metni hukuk danışmanına kontrol ettirin.</p>';
  const LEGAL = {
    iade: { title: 'İptal ve İade', html: NOTE + `
      <p>Güncel taslak ve yayından önce doldurulması gereken iade adresi, kargo/ücret süreci ve ürün türlerine uygulanacak şartlar için <a href="iptal-ve-iade.html">İptal ve İade sayfasını açın</a>.</p>
      <h4>İletişim</h4>
      <p>Telefon/WhatsApp: 0531 338 59 55</p>
      <p><span class="legal-required">[EKLENECEK: izlenen e-posta veya ek başvuru kanalı]</span></p>
      <p><span class="legal-required">[HUKUKİ KONTROL: cayma hakkı istisnaları, iade kargo ve bedel iadesi şartları]</span></p>` },
    kvkk: { title: 'KVKK Aydınlatma Metni', html: NOTE + `
      <h4>Veri sorumlusu</h4>
      <p>Köken Fındık, [firma ünvanı], [adres]. İletişim: [e-posta].</p>
      <h4>İşlenen veriler ve amaç</h4>
      <p>Ad soyad, telefon, teslimat adresi (il, ilçe, açık adres), sipariş notu ve sipariş içeriği; siparişin alınması, hazırlanması, teslimatı ve sizinle iletişim kurulması amacıyla işlenir.</p>
      <h4>Hukuki sebep</h4>
      <p>Bir sözleşmenin kurulması veya ifasıyla doğrudan ilgili olması ve veri sorumlusunun hukuki yükümlülüklerini yerine getirmesi (KVKK m.5/2).</p>
      <h4>Aktarım</h4>
      <p>Verileriniz, teslimatın yapılabilmesi için kargo firmasına ve siparişin iletildiği WhatsApp hizmetine, yasal zorunluluk halinde yetkili kurumlara aktarılabilir.</p>
      <h4>Saklama süresi</h4>
      <p>Mevzuatta öngörülen süreler boyunca saklanır, süre sonunda silinir veya anonim hale getirilir.</p>
      <h4>Haklarınız</h4>
      <p>KVKK m.11 uyarınca verilerinizin işlenip işlenmediğini öğrenme, düzeltme, silme ve itiraz haklarına sahipsiniz. Başvurularınızı yukarıdaki iletişim adresine iletebilirsiniz.</p>` },
    teslimat: { title: 'Teslimat ve Kargo', html: NOTE + `
      <h4>Kargo ücreti</h4>
      <p>${fmt(FREE_SHIPPING_LIMIT)} ve üzeri siparişlerde kargo ücretsizdir. Bu tutarın altındaki siparişlere ${fmt(SHIPPING_FEE)} kargo ücreti eklenir.</p>
      <h4>Hazırlık ve teslimat süresi</h4>
      <p>Siparişin satıcı tarafından onaylanması, hazırlık süresi ve kullanılacak kargo firması: [YAYIN ÖNCESİ DOLDURUN]. Bölgeye göre tahmini teslimat süresi: [YAYIN ÖNCESİ DOLDURUN].</p>
      <h4>Teslim alırken</h4>
      <p>Paketi kargo görevlisinin yanında kontrol edin. Hasar varsa tutanak düzenletin (ayrıntılar için "İptal ve İade").</p>
      <h4>Adres bilgisi</h4>
      <p>Teslimat, siparişte belirttiğiniz adrese yapılır. Eksik veya hatalı adres ve telefon bilgisi teslimatı geciktirebilir.</p>` },
    hakkimizda: { title: 'Hakkımızda', html: NOTE + `
      <p>Köken Fındık; taze Karadeniz fındığını kırma, kavurma, ezme ve paketleme aşamalarından geçirerek doğrudan size ulaştıran bir üreticidir.</p>
      <p>[Markanın hikâyesi, kuruluş yılı ve üretim anlayışı buraya yazılabilir.]</p>` },
    tesis: { title: 'Üretim Tesisimiz', html: NOTE + `
      <p>Kırma, kavurma, ezme ve paketleme işlemleri kendi tesisimizde yapılır.</p>
      <p>[Tesis adresi, kapasite, hijyen ve kalite belgeleri buraya yazılabilir.]</p>` },
    takip: { title: 'Sipariş Takibi', html: NOTE + `
      <p>Siparişiniz kaydedildikten sonra kısa sipariş numarası ve siparişte kullandığınız telefonla <a href="siparis-takibi.html">Sipariş Takibi</a> sayfasından durumunu kontrol edebilirsiniz. Sorularınız için sipariş numaranızla WhatsApp üzerinden bize ulaşabilirsiniz.</p>
      <p>Kargoya verildiğinde takip numaranız tarafınıza iletilir.</p>` },
    satis: { title: 'Mesafeli Satış Sözleşmesi', html: NOTE + `
      <h4>Madde 1 – Taraflar</h4>
      <p>SATICI: Köken Fındık (marka). <span class="legal-required">[EKLENECEK: resmi tam unvan, vergi dairesi/no ve varsa MERSİS numarası]</span>. Bilinen adres ve telefon: Kökenli, Tokat Ordu Yolu No:34, 52000 Altınordu/Ordu · 0531 338 59 55.</p>
      <h4>Madde 2 – Sipariş</h4>
      <p>Sipariş sisteme kaydedilir. Satıcının sipariş kabul/ret bildiriminin hangi anda ve kanalla yapılacağı <span class="legal-required">[TAMAMLANACAK: gerçek işleyişi yazın]</span>.</p>
      <h4>Madde 3 – Bedel, ödeme ve teslimat</h4>
      <p><span class="legal-required">[TAMAMLANACAK: vergi dâhil fiyat gösterimi, sunulan ödeme yöntemleri, hazırlık/kargo koşulları]</span>.</p>
      <h4>Madde 4 – Cayma ve uyuşmazlık</h4>
      <p><span class="legal-required">[HUKUKİ KONTROL: cayma hakkı/istisnalar ve başvuru şartları]</span>. Tam metin için <a href="mesafeli-satis-sozlesmesi.html">Mesafeli Satış Sözleşmesi sayfasını açın</a>.</p>` },
    gizlilik: { title: 'Gizlilik ve Çerez Politikası', html: NOTE + `
      <p>Bu site, sepetinizin sayfa yenilense de korunması için tarayıcınızın yerel depolama alanını kullanır. Bu bilgi cihazınızda kalır, tarafımıza gönderilmez.</p>
      <p>Site, yazı tiplerini Google Fonts üzerinden yükler. Reklam veya takip amaçlı çerez kullanılmaz. [Analiz aracı eklerseniz bu metni güncelleyin.]</p>
      <p>Sipariş sırasında girdiğiniz bilgilerin işlenmesi için KVKK Aydınlatma Metni'ne bakınız.</p>` }
  };

  document.addEventListener('click', (e) => {
    const a = e.target.closest('[data-legal]');
    if (!a) return;
    e.preventDefault();
    const d = LEGAL[a.dataset.legal];
    if (!d) return;
    $('#legalTitle').textContent = d.title;
    $('#legalBody').innerHTML = d.html;
    $('#legalBody').scrollTop = 0;
    openModal('legal');
  });

  $('#checkoutBtn').addEventListener('click', () => {
    const items = lines();
    if (!items.length) return;
    save();
    window.location.assign('siparis-tamamla.html');
  });
  window.addEventListener('popstate', (event) => {
    const productId = event.state?.kokenProduct;
    if (productId && byId.has(productId)) openDetail(productId, document.activeElement, true);
    else closeModal($('#detail'), true);
  });
  render();
  if (isProductPage) {
    const product = normalizeProductRow(productBootstrap.product);
    dId = product.id;
    fullCatalogLoaded = true;
    setProducts(productBootstrap.catalog.map(normalizeProductRow));
    const homeUrl = productBootstrap.home.path + '#urunler';
    if (history.state?.kokenProduct !== product.id || !history.state?.kokenReturn) {
      history.replaceState({ kokenHome: true }, '', homeUrl);
      history.pushState({ kokenProduct: product.id, kokenReturn: homeUrl }, '', productAddress(product));
    }
    const section = $('#urunler');
    if (section) window.scrollTo({ top: Math.max(0, section.getBoundingClientRect().top + window.scrollY - ($('.site-top')?.offsetHeight || 0)), behavior: 'instant' });
    openDetail(product.id, null, true);
    loadStoreSettings().then(render);
  } else Promise.all([loadStoreSettings(), loadCatalog()]).then(([, products]) => {
    setProducts(products);
    const addId = new URLSearchParams(location.search).get('add_to_cart');
    if (addId && byId.has(addId)) {
      const qtyBefore = cartQty(addId);
      addToCart(addId, 1, true);
      if (cartQty(addId) > qtyBefore) showToast(addId);
      history.replaceState(null, '', location.pathname + '#urunler');
    } else {
      const viewId = new URLSearchParams(location.search).get('view_product');
      if (viewId && byId.has(viewId)) {
        history.replaceState(null, '', location.pathname + '#urunler');
        openDetail(viewId);
      }
    }
  });
})();


/* ===================== HERO SLIDER ===================== */
(() => {
  const hero = document.querySelector('.hero');
  const track = document.querySelector('#heroTrack');
  if (!hero || !track) return;

  const slides = Array.from(track.querySelectorAll('.hero__slide'));
  const dots = Array.from(hero.querySelectorAll('.hero-dots button'));
  const previous = hero.querySelector('.hero-prev');
  const next = hero.querySelector('.hero-next');
  if (slides.length < 2) return;

  let active = 0;
  let timer = null;
  let pointerStartX = null;

  function showSlide(index) {
    active = (index + slides.length) % slides.length;
    track.style.transform = `translateX(-${active * 100}%)`;
    slides.forEach((slide, i) => { slide.setAttribute('aria-hidden', String(i !== active)); slide.inert = i !== active; });
    dots.forEach((dot, i) => dot.setAttribute('aria-current', String(i === active)));
  }

  function stopAuto() {
    if (timer) window.clearInterval(timer);
    timer = null;
  }

  function startAuto() {
    stopAuto();
    if (document.visibilityState === 'visible') {
      timer = window.setInterval(() => showSlide(active + 1), 5000);
    }
  }

  function goTo(index) {
    showSlide(index);
    startAuto();
  }

  previous?.addEventListener('click', () => goTo(active - 1));
  next?.addEventListener('click', () => goTo(active + 1));
  dots.forEach((dot, index) => dot.addEventListener('click', () => goTo(index)));

  document.addEventListener('visibilitychange', startAuto);

  hero.addEventListener('pointerdown', (event) => {
    if (event.target.closest('.trust')) {
      pointerStartX = null;
      return;
    }
    pointerStartX = event.clientX;
  }, { passive: true });
  hero.addEventListener('pointerup', (event) => {
    if (pointerStartX === null) return;
    const distance = event.clientX - pointerStartX;
    pointerStartX = null;
    if (Math.abs(distance) > 45) goTo(active + (distance < 0 ? 1 : -1));
  }, { passive: true });
  hero.addEventListener('pointercancel', () => { pointerStartX = null; }, { passive: true });

  showSlide(0);
  startAuto();
})();
