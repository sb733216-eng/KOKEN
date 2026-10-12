/* ===================== AYARLAR (kolayca değiştirilebilir) ===================== */
const WHATSAPP_NO = "905313385955"; // Ülke koduyla, + ve boşluk olmadan. Örn: 905321234567
const FREE_SHIPPING_LIMIT = 750;    // Bu tutar ve üzerinde kargo ücretsiz (TL)
const SHIPPING_FEE = 60;            // Limit altındaki siparişlerde kargo ücreti (TL)

// Ürünleri ve fiyatları buradan değiştirebilirsiniz.
// cat: kavrulmus | cig | ezme | paketleme
const PRODUCTS = [
  { id: '1', cat: 'kavrulmus', name: 'Taze Kavrulmuş İç Fındık',          weight: '500g', price: 250, img: 'kavrulmus.jpg' },
  { id: '3', cat: 'kavrulmus', name: 'Çifte Kavrulmuş Fındık',            weight: '1 kg', price: 480, img: 'cifte-kavrulmus.jpg' },
  { id: '2', cat: 'ezme',      name: 'Şekersiz Doğal Fındık Ezmesi',      weight: '320g', chip: '320g Cam Kavanoz', price: 190, img: 'ezme.jpg' },
  { id: '4', cat: 'cig',       name: 'Çiğ İç Fındık',                     weight: '500g', price: 230, img: 'cig.jpg' },
  { id: '5', cat: 'paketleme', name: 'Vakumlu Paket Lüks Karışık Fındık', weight: '1 kg', price: 520, img: 'paket.jpg' }
];
// 81 il ve tüm ilçeleri (il adı -> virgülle ayrılmış ilçeler)
const IL_ILCE = {
  'Adana': 'Aladağ,Ceyhan,Çukurova,Feke,İmamoğlu,Karaisalı,Karataş,Kozan,Pozantı,Saimbeyli,Sarıçam,Seyhan,Tufanbeyli,Yumurtalık,Yüreğir',
  'Adıyaman': 'Besni,Çelikhan,Gerger,Gölbaşı,Kahta,Merkez,Samsat,Sincik,Tut',
  'Afyonkarahisar': 'Başmakçı,Bayat,Bolvadin,Çay,Çobanlar,Dazkırı,Dinar,Emirdağ,Evciler,Hocalar,İhsaniye,İscehisar,Kızılören,Merkez,Sandıklı,Sinanpaşa,Sultandağı,Şuhut',
  'Ağrı': 'Diyadin,Doğubayazıt,Eleşkirt,Hamur,Merkez,Patnos,Taşlıçay,Tutak',
  'Aksaray': 'Ağaçören,Eskil,Gülağaç,Güzelyurt,Merkez,Ortaköy,Sarıyahşi,Sultanhanı',
  'Amasya': 'Göynücek,Gümüşhacıköy,Hamamözü,Merkez,Merzifon,Suluova,Taşova',
  'Ankara': 'Akyurt,Altındağ,Ayaş,Bala,Beypazarı,Çamlıdere,Çankaya,Çubuk,Elmadağ,Etimesgut,Evren,Gölbaşı,Güdül,Haymana,Kahramankazan,Kalecik,Keçiören,Kızılcahamam,Mamak,Nallıhan,Polatlı,Pursaklar,Sincan,Şereflikoçhisar,Yenimahalle',
  'Antalya': 'Akseki,Aksu,Alanya,Demre,Döşemealtı,Elmalı,Finike,Gazipaşa,Gündoğmuş,İbradı,Kaş,Kemer,Kepez,Konyaaltı,Korkuteli,Kumluca,Manavgat,Muratpaşa,Serik',
  'Ardahan': 'Çıldır,Damal,Göle,Hanak,Merkez,Posof',
  'Artvin': 'Ardanuç,Arhavi,Borçka,Hopa,Kemalpaşa,Merkez,Murgul,Şavşat,Yusufeli',
  'Aydın': 'Bozdoğan,Buharkent,Çine,Didim,Efeler,Germencik,İncirliova,Karacasu,Karpuzlu,Koçarlı,Köşk,Kuşadası,Kuyucak,Nazilli,Söke,Sultanhisar,Yenipazar',
  'Balıkesir': 'Altıeylül,Ayvalık,Balya,Bandırma,Bigadiç,Burhaniye,Dursunbey,Edremit,Erdek,Gömeç,Gönen,Havran,İvrindi,Karesi,Kepsut,Manyas,Marmara,Savaştepe,Sındırgı,Susurluk',
  'Bartın': 'Amasra,Kurucaşile,Merkez,Ulus',
  'Batman': 'Beşiri,Gercüş,Hasankeyf,Kozluk,Merkez,Sason',
  'Bayburt': 'Aydıntepe,Demirözü,Merkez',
  'Bilecik': 'Bozüyük,Gölpazarı,İnhisar,Merkez,Osmaneli,Pazaryeri,Söğüt,Yenipazar',
  'Bingöl': 'Adaklı,Genç,Karlıova,Kiğı,Merkez,Solhan,Yayladere,Yedisu',
  'Bitlis': 'Adilcevaz,Ahlat,Güroymak,Hizan,Merkez,Mutki,Tatvan',
  'Bolu': 'Dörtdivan,Gerede,Göynük,Kıbrıscık,Mengen,Merkez,Mudurnu,Seben,Yeniçağa',
  'Burdur': 'Ağlasun,Altınyayla,Bucak,Çavdır,Çeltikçi,Gölhisar,Karamanlı,Kemer,Merkez,Tefenni,Yeşilova',
  'Bursa': 'Büyükorhan,Gemlik,Gürsu,Harmancık,İnegöl,İznik,Karacabey,Keles,Kestel,Mudanya,Mustafakemalpaşa,Nilüfer,Orhaneli,Orhangazi,Osmangazi,Yenişehir,Yıldırım',
  'Çanakkale': 'Ayvacık,Bayramiç,Biga,Bozcaada,Çan,Eceabat,Ezine,Gelibolu,Gökçeada,Lapseki,Merkez,Yenice',
  'Çankırı': 'Atkaracalar,Bayramören,Çerkeş,Eldivan,Ilgaz,Kızılırmak,Korgun,Kurşunlu,Merkez,Orta,Şabanözü,Yapraklı',
  'Çorum': 'Alaca,Bayat,Boğazkale,Dodurga,İskilip,Kargı,Laçin,Mecitözü,Merkez,Oğuzlar,Ortaköy,Osmancık,Sungurlu,Uğurludağ',
  'Denizli': 'Acıpayam,Babadağ,Baklan,Bekilli,Beyağaç,Bozkurt,Buldan,Çal,Çameli,Çardak,Çivril,Güney,Honaz,Kale,Merkezefendi,Pamukkale,Sarayköy,Serinhisar,Tavas',
  'Diyarbakır': 'Bağlar,Bismil,Çermik,Çınar,Çüngüş,Dicle,Eğil,Ergani,Hani,Hazro,Kayapınar,Kocaköy,Kulp,Lice,Silvan,Sur,Yenişehir',
  'Düzce': 'Akçakoca,Cumayeri,Çilimli,Gölyaka,Gümüşova,Kaynaşlı,Merkez,Yığılca',
  'Edirne': 'Enez,Havsa,İpsala,Keşan,Lalapaşa,Meriç,Merkez,Süloğlu,Uzunköprü',
  'Elazığ': 'Ağın,Alacakaya,Arıcak,Baskil,Karakoçan,Keban,Kovancılar,Maden,Merkez,Palu,Sivrice',
  'Erzincan': 'Çayırlı,İliç,Kemah,Kemaliye,Merkez,Otlukbeli,Refahiye,Tercan,Üzümlü',
  'Erzurum': 'Aşkale,Aziziye,Çat,Hınıs,Horasan,İspir,Karaçoban,Karayazı,Köprüköy,Narman,Oltu,Olur,Palandöken,Pasinler,Pazaryolu,Şenkaya,Tekman,Tortum,Uzundere,Yakutiye',
  'Eskişehir': 'Alpu,Beylikova,Çifteler,Günyüzü,Han,İnönü,Mahmudiye,Mihalgazi,Mihalıççık,Odunpazarı,Sarıcakaya,Seyitgazi,Sivrihisar,Tepebaşı',
  'Gaziantep': 'Araban,İslahiye,Karkamış,Nizip,Nurdağı,Oğuzeli,Şahinbey,Şehitkamil,Yavuzeli',
  'Giresun': 'Alucra,Bulancak,Çamoluk,Çanakçı,Dereli,Doğankent,Espiye,Eynesil,Görele,Güce,Keşap,Merkez,Piraziz,Şebinkarahisar,Tirebolu,Yağlıdere',
  'Gümüşhane': 'Kelkit,Köse,Kürtün,Merkez,Şiran,Torul',
  'Hakkâri': 'Çukurca,Derecik,Merkez,Şemdinli,Yüksekova',
  'Hatay': 'Altınözü,Antakya,Arsuz,Belen,Defne,Dörtyol,Erzin,Hassa,İskenderun,Kırıkhan,Kumlu,Payas,Reyhanlı,Samandağ,Yayladağı',
  'Iğdır': 'Aralık,Karakoyunlu,Merkez,Tuzluca',
  'Isparta': 'Aksu,Atabey,Eğirdir,Gelendost,Gönen,Keçiborlu,Merkez,Senirkent,Sütçüler,Şarkikaraağaç,Uluborlu,Yalvaç,Yenişarbademli',
  'İstanbul': 'Adalar,Arnavutköy,Ataşehir,Avcılar,Bağcılar,Bahçelievler,Bakırköy,Başakşehir,Bayrampaşa,Beşiktaş,Beykoz,Beylikdüzü,Beyoğlu,Büyükçekmece,Çatalca,Çekmeköy,Esenler,Esenyurt,Eyüpsultan,Fatih,Gaziosmanpaşa,Güngören,Kadıköy,Kağıthane,Kartal,Küçükçekmece,Maltepe,Pendik,Sancaktepe,Sarıyer,Silivri,Sultanbeyli,Sultangazi,Şile,Şişli,Tuzla,Ümraniye,Üsküdar,Zeytinburnu',
  'İzmir': 'Aliağa,Balçova,Bayındır,Bayraklı,Bergama,Beydağ,Bornova,Buca,Çeşme,Çiğli,Dikili,Foça,Gaziemir,Güzelbahçe,Karabağlar,Karaburun,Karşıyaka,Kemalpaşa,Kınık,Kiraz,Konak,Menderes,Menemen,Narlıdere,Ödemiş,Seferihisar,Selçuk,Tire,Torbalı,Urla',
  'Kahramanmaraş': 'Afşin,Andırın,Çağlayancerit,Dulkadiroğlu,Ekinözü,Elbistan,Göksun,Nurhak,Onikişubat,Pazarcık,Türkoğlu',
  'Karabük': 'Eflani,Eskipazar,Merkez,Ovacık,Safranbolu,Yenice',
  'Karaman': 'Ayrancı,Başyayla,Ermenek,Kazımkarabekir,Merkez,Sarıveliler',
  'Kars': 'Akyaka,Arpaçay,Digor,Kağızman,Merkez,Sarıkamış,Selim,Susuz',
  'Kastamonu': 'Abana,Ağlı,Araç,Azdavay,Bozkurt,Cide,Çatalzeytin,Daday,Devrekani,Doğanyurt,Hanönü,İhsangazi,İnebolu,Küre,Merkez,Pınarbaşı,Seydiler,Şenpazar,Taşköprü,Tosya',
  'Kayseri': 'Akkışla,Bünyan,Develi,Felahiye,Hacılar,İncesu,Kocasinan,Melikgazi,Özvatan,Pınarbaşı,Sarıoğlan,Sarız,Talas,Tomarza,Yahyalı,Yeşilhisar',
  'Kırıkkale': 'Bahşılı,Balışeyh,Çelebi,Delice,Karakeçili,Keskin,Merkez,Sulakyurt,Yahşihan',
  'Kırklareli': 'Babaeski,Demirköy,Kofçaz,Lüleburgaz,Merkez,Pehlivanköy,Pınarhisar,Vize',
  'Kırşehir': 'Akçakent,Akpınar,Boztepe,Çiçekdağı,Kaman,Merkez,Mucur',
  'Kilis': 'Elbeyli,Merkez,Musabeyli,Polateli',
  'Kocaeli': 'Başiskele,Çayırova,Darıca,Derince,Dilovası,Gebze,Gölcük,İzmit,Kandıra,Karamürsel,Kartepe,Körfez',
  'Konya': 'Ahırlı,Akören,Akşehir,Altınekin,Beyşehir,Bozkır,Cihanbeyli,Çeltik,Çumra,Derbent,Derebucak,Doğanhisar,Emirgazi,Ereğli,Güneysınır,Hadim,Halkapınar,Hüyük,Ilgın,Kadınhanı,Karapınar,Karatay,Kulu,Meram,Sarayönü,Selçuklu,Seydişehir,Taşkent,Tuzlukçu,Yalıhüyük,Yunak',
  'Kütahya': 'Altıntaş,Aslanapa,Çavdarhisar,Domaniç,Dumlupınar,Emet,Gediz,Hisarcık,Merkez,Pazarlar,Simav,Şaphane,Tavşanlı',
  'Malatya': 'Akçadağ,Arapgir,Arguvan,Battalgazi,Darende,Doğanşehir,Doğanyol,Hekimhan,Kale,Kuluncak,Pütürge,Yazıhan,Yeşilyurt',
  'Manisa': 'Ahmetli,Akhisar,Alaşehir,Demirci,Gölmarmara,Gördes,Kırkağaç,Köprübaşı,Kula,Salihli,Sarıgöl,Saruhanlı,Selendi,Soma,Şehzadeler,Turgutlu,Yunusemre',
  'Mardin': 'Artuklu,Dargeçit,Derik,Kızıltepe,Mazıdağı,Midyat,Nusaybin,Ömerli,Savur,Yeşilli',
  'Mersin': 'Akdeniz,Anamur,Aydıncık,Bozyazı,Çamlıyayla,Erdemli,Gülnar,Mezitli,Mut,Silifke,Tarsus,Toroslar,Yenişehir',
  'Muğla': 'Bodrum,Dalaman,Datça,Fethiye,Kavaklıdere,Köyceğiz,Marmaris,Menteşe,Milas,Ortaca,Seydikemer,Ula,Yatağan',
  'Muş': 'Bulanık,Hasköy,Korkut,Malazgirt,Merkez,Varto',
  'Nevşehir': 'Acıgöl,Avanos,Derinkuyu,Gülşehir,Hacıbektaş,Kozaklı,Merkez,Ürgüp',
  'Niğde': 'Altunhisar,Bor,Çamardı,Çiftlik,Merkez,Ulukışla',
  'Ordu': 'Akkuş,Altınordu,Aybastı,Çamaş,Çatalpınar,Çaybaşı,Fatsa,Gölköy,Gülyalı,Gürgentepe,İkizce,Kabadüz,Kabataş,Korgan,Kumru,Mesudiye,Perşembe,Ulubey,Ünye',
  'Osmaniye': 'Bahçe,Düziçi,Hasanbeyli,Kadirli,Merkez,Sumbas,Toprakkale',
  'Rize': 'Ardeşen,Çamlıhemşin,Çayeli,Derepazarı,Fındıklı,Güneysu,Hemşin,İkizdere,İyidere,Kalkandere,Merkez,Pazar',
  'Sakarya': 'Adapazarı,Akyazı,Arifiye,Erenler,Ferizli,Geyve,Hendek,Karapürçek,Karasu,Kaynarca,Kocaali,Pamukova,Sapanca,Serdivan,Söğütlü,Taraklı',
  'Samsun': '19 Mayıs,Alaçam,Asarcık,Atakum,Ayvacık,Bafra,Canik,Çarşamba,Havza,İlkadım,Kavak,Ladik,Salıpazarı,Tekkeköy,Terme,Vezirköprü,Yakakent',
  'Siirt': 'Baykan,Eruh,Kurtalan,Merkez,Pervari,Şirvan,Tillo',
  'Sinop': 'Ayancık,Boyabat,Dikmen,Durağan,Erfelek,Gerze,Merkez,Saraydüzü,Türkeli',
  'Sivas': 'Akıncılar,Altınyayla,Divriği,Doğanşar,Gemerek,Gölova,Gürün,Hafik,İmranlı,Kangal,Koyulhisar,Merkez,Suşehri,Şarkışla,Ulaş,Yıldızeli,Zara',
  'Şanlıurfa': 'Akçakale,Birecik,Bozova,Ceylanpınar,Eyyübiye,Halfeti,Haliliye,Harran,Hilvan,Karaköprü,Siverek,Suruç,Viranşehir',
  'Şırnak': 'Beytüşşebap,Cizre,Güçlükonak,İdil,Merkez,Silopi,Uludere',
  'Tekirdağ': 'Çerkezköy,Çorlu,Ergene,Hayrabolu,Kapaklı,Malkara,Marmaraereğlisi,Muratlı,Saray,Süleymanpaşa,Şarköy',
  'Tokat': 'Almus,Artova,Başçiftlik,Erbaa,Merkez,Niksar,Pazar,Reşadiye,Sulusaray,Turhal,Yeşilyurt,Zile',
  'Trabzon': 'Akçaabat,Araklı,Arsin,Beşikdüzü,Çarşıbaşı,Çaykara,Dernekpazarı,Düzköy,Hayrat,Köprübaşı,Maçka,Of,Ortahisar,Sürmene,Şalpazarı,Tonya,Vakfıkebir,Yomra',
  'Tunceli': 'Çemişgezek,Hozat,Mazgirt,Merkez,Nazımiye,Ovacık,Pertek,Pülümür',
  'Uşak': 'Banaz,Eşme,Karahallı,Merkez,Sivaslı,Ulubey',
  'Van': 'Bahçesaray,Başkale,Çaldıran,Çatak,Edremit,Erciş,Gevaş,Gürpınar,İpekyolu,Muradiye,Özalp,Saray,Tuşba',
  'Yalova': 'Altınova,Armutlu,Çiftlikköy,Çınarcık,Merkez,Termal',
  'Yozgat': 'Akdağmadeni,Aydıncık,Boğazlıyan,Çandır,Çayıralan,Çekerek,Kadışehri,Merkez,Saraykent,Sarıkaya,Sorgun,Şefaatli,Yenifakılı,Yerköy',
  'Zonguldak': 'Alaplı,Çaycuma,Devrek,Ereğli,Gökçebey,Kilimli,Kozlu,Merkez'
};

/* ============================================================================== */

(() => {
  'use strict';

  const KEY = 'koken-findik-cart';
  const MAX_QTY = 99;
  const SEP = '--------------------------------';

  const $ = (s) => document.querySelector(s);
  const $$ = (s) => Array.from(document.querySelectorAll(s));
  const fmt = (n) => n.toLocaleString('tr-TR') + ' TL';
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  document.addEventListener('error', (e) => { if (e.target.tagName === 'IMG') e.target.style.visibility = 'hidden'; }, true);

  const byId = new Map(PRODUCTS.map((p) => [p.id, p]));
  const label = (p) => `${p.name} (${p.weight})`;

  /* ---------- Sepet (LocalStorage: yalnızca id + adet; ad ve fiyat kataloğdan gelir) ---------- */
  let cart = load();

  function load() {
    try {
      const d = JSON.parse(localStorage.getItem(KEY) || '[]');
      if (!Array.isArray(d)) return [];
      const m = new Map();
      d.forEach((i) => {
        const id = String(i && i.id), q = Math.floor(Number(i && i.qty));
        if (byId.has(id) && q > 0) m.set(id, Math.min(MAX_QTY, (m.get(id) || 0) + q));
      });
      return Array.from(m, ([id, qty]) => ({ id, qty }));
    } catch (e) { return []; }
  }
  function save() {
    try {
      if (cart.length) localStorage.setItem(KEY, JSON.stringify(cart)); else localStorage.removeItem(KEY);
    } catch (e) { /* depolama kapalı */ }
  }

  function totals() {
    const sub = cart.reduce((s, i) => s + byId.get(i.id).price * i.qty, 0);
    const ship = sub === 0 || sub >= FREE_SHIPPING_LIMIT ? 0 : SHIPPING_FEE;
    return { sub, ship, total: sub + ship };
  }
  const count = () => cart.reduce((s, i) => s + i.qty, 0);

  /* ---------- Ürün vitrini ---------- */
  $('#grid').innerHTML = PRODUCTS.map((p) => `
    <article class="card" data-cat="${esc(p.cat)}">
      <div class="card__img">
        <img src="${esc(p.img)}" alt="${esc(label(p))}" width="400" height="300" loading="lazy">
        <span class="tag">Yeni Mahsul</span>
      </div>
      <div class="card__body">
        <span class="chip">${esc(p.chip || p.weight)}</span>
        <h3>${esc(p.name)}</h3>
        <div class="rating" aria-label="5 üzerinden 4,9 puan"><span class="stars" aria-hidden="true">★★★★★</span><b>4.9</b></div>
        <div class="card__bottom">
          <span class="price">${fmt(p.price)}</span>
          <button type="button" class="btn btn--primary" data-add="${esc(p.id)}">Sepete Ekle</button>
        </div>
      </div>
    </article>`).join('');

  $('#filters').addEventListener('click', (e) => {
    const b = e.target.closest('button[data-f]');
    if (!b) return;
    $$('#filters button').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
    $$('#grid .card').forEach((c) => { c.hidden = b.dataset.f !== 'all' && c.dataset.cat !== b.dataset.f; });
  });

  /* ---------- Çizim ---------- */
  function render() {
    const { sub, ship, total } = totals();

    $('#cartItems').innerHTML = cart.length ? cart.map((i) => {
      const p = byId.get(i.id);
      return `<li class="cart-item">
        <div><strong>${esc(label(p))}</strong><small>${fmt(p.price)}</small></div>
        <div class="side">
          <div class="qty">
            <button type="button" data-act="dec" data-id="${esc(i.id)}" aria-label="Azalt">−</button>
            <span>${i.qty}</span>
            <button type="button" data-act="inc" data-id="${esc(i.id)}" aria-label="Arttır">+</button>
          </div>
          <strong>${fmt(p.price * i.qty)}</strong>
          <button type="button" class="del" data-act="del" data-id="${esc(i.id)}">Sil</button>
        </div>
      </li>`;
    }).join('') : '<li class="empty">Sepetiniz şu an boş.</li>';

    // Kargo çubuğu
    const pct = Math.min(100, Math.floor((sub / FREE_SHIPPING_LIMIT) * 100));
    $('#ship').classList.toggle('free', sub >= FREE_SHIPPING_LIMIT);
    $('#shipBar').style.width = pct + '%';
    $('#shipPct').textContent = '%' + pct;
    $('#shipText').textContent =
      sub >= FREE_SHIPPING_LIMIT ? 'Tebrikler, kargo ücretsiz!'
      : sub === 0 ? `${fmt(FREE_SHIPPING_LIMIT)} ve üzeri siparişlerde kargo ücretsiz`
      : `Ücretsiz kargoya ${fmt(FREE_SHIPPING_LIMIT - sub)} kaldı`;

    // Toplamlar
    $('#subTotal').textContent = fmt(sub);
    const sc = $('#shipCost');
    sc.textContent = sub === 0 ? '—' : ship ? fmt(ship) : 'Ücretsiz';
    sc.classList.toggle('free', sub > 0 && !ship);
    $('#grandTotal').textContent = fmt(total);

    const badge = $('#cartCount');
    badge.textContent = count();
    badge.classList.toggle('on', count() > 0);
    $('#checkoutBtn').disabled = !cart.length;
  }

  const commit = () => { save(); render(); };

  /* ---------- Sepet işlemleri ---------- */
  $('#grid').addEventListener('click', (e) => {
    const b = e.target.closest('button[data-add]');
    if (!b) return;
    const f = cart.find((i) => i.id === b.dataset.add);
    if (f) f.qty = Math.min(MAX_QTY, f.qty + 1); else cart.push({ id: b.dataset.add, qty: 1 });
    commit();

    const t = b.textContent;
    b.textContent = 'Eklendi ✓';
    b.classList.add('added');
    setTimeout(() => { b.textContent = t; b.classList.remove('added'); }, 900);
    const c = $('#openCart');
    c.classList.remove('bump'); void c.offsetWidth; c.classList.add('bump');
    openDrawer();
  });

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
  const lock = () => document.body.classList.toggle('no-scroll', !!document.querySelector('.drawer.on, .modal.on'));

  function openDrawer() {
    drawer.classList.add('on'); overlay.classList.add('on');
    drawer.setAttribute('aria-hidden', 'false'); lock();
    $('#closeCart').focus({ preventScroll: true });
  }
  function closeDrawer() {
    drawer.classList.remove('on'); overlay.classList.remove('on');
    drawer.setAttribute('aria-hidden', 'true'); lock();
  }
  function openModal(id) {
    const m = $('#' + id);
    m.classList.add('on'); m.setAttribute('aria-hidden', 'false'); lock();
    const f = m.querySelector('input,.btn');
    if (f) f.focus({ preventScroll: true });
  }
  function closeModal(m) { m.classList.remove('on'); m.setAttribute('aria-hidden', 'true'); lock(); }

  $('#openCart').addEventListener('click', openDrawer);
  $('#closeCart').addEventListener('click', closeDrawer);
  overlay.addEventListener('click', closeDrawer);
  document.addEventListener('click', (e) => {
    const c = e.target.closest('[data-close]');
    if (c) closeModal(c.closest('.modal'));
  });
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    const open = $$('.modal.on');
    if (open.length) closeModal(open[open.length - 1]); else closeDrawer();
  });

  window.addEventListener('storage', (e) => { if (e.key === KEY) { cart = load(); render(); } });

  /* ---------- Teslimat formu ---------- */
  const form = $('#orderForm'), errEl = $('#formErr');

  $('#checkoutBtn').addEventListener('click', () => {
    if (!cart.length) return;
    const { ship, total } = totals();
    $('#coList').innerHTML = cart.map((i) => {
      const p = byId.get(i.id);
      return `<li><span>${esc(label(p))} × ${i.qty}</span><span>${fmt(p.price * i.qty)}</span></li>`;
    }).join('');
    const cs = $('#coShip');
    cs.textContent = ship ? fmt(ship) : 'Ücretsiz';
    cs.classList.toggle('free', !ship);
    $('#coTotal').textContent = fmt(total);
    errEl.textContent = '';
    closeDrawer();
    openModal('checkout');
  });

  /* ---------- İl / İlçe seçimi ---------- */
  const citySel = form.elements.city, distSel = form.elements.district;
  const trSort = (a, b) => a.localeCompare(b, 'tr');
  const has = (c) => Object.prototype.hasOwnProperty.call(IL_ILCE, c);
  const opt = (v, t) => { const o = document.createElement('option'); o.value = v; o.textContent = t || v; return o; };

  function fillDistricts(city) {
    distSel.replaceChildren(opt('', city ? 'İlçe seçin' : 'Önce il seçin'));
    distSel.disabled = !city;
    if (city && has(city)) IL_ILCE[city].split(',').sort(trSort).forEach((d) => distSel.append(opt(d)));
  }
  citySel.replaceChildren(opt('', 'İl seçin'), ...Object.keys(IL_ILCE).sort(trSort).map((c) => opt(c)));
  citySel.addEventListener('change', () => fillDistricts(citySel.value));
  fillDistricts('');

  /* ---------- WhatsApp mesajı ve yönlendirme ---------- */
  function buildMessage(v) {
    const { sub, ship, total } = totals();
    const items = cart.map((i) => {
      const p = byId.get(i.id);
      return `• ${i.qty}x ${label(p)} - ${fmt(p.price * i.qty)}`;
    });
    return [
      '*KÖKEN FINDIK - YENİ SİPARİŞ* 🌰',
      SEP,
      '*Sipariş Kalemleri:*',
      ...items,
      SEP,
      `*Ara Toplam:* ${fmt(sub)}`,
      `*Kargo:* ${ship ? fmt(ship) : 'Ücretsiz'}`,
      `*Genel Toplam:* ${fmt(total)}`,
      SEP,
      '*Teslimat Bilgileri:*',
      `*Alıcı:* ${v.name}`,
      `*Telefon:* ${v.phone}`,
      `*Adres:* ${v.address} - ${v.district} / ${v.city}`,
      `*Not:* ${v.note || '-'}`
    ].join('\n');
  }

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    errEl.textContent = '';
    if (!cart.length) return;

    // Boşluk/yeni satırları sadeleştirip değerleri al
    const v = {};
    ['name', 'phone', 'city', 'district', 'address', 'note'].forEach((k) => {
      v[k] = form.elements[k].value.replace(/\s+/g, ' ').trim();
    });

    // Zorunlu alan doğrulaması
    const required = ['name', 'phone', 'city', 'district', 'address'];
    let bad = null;
    required.forEach((k) => {
      const el = form.elements[k];
      const ok = v[k] && el.checkValidity();
      el.classList.toggle('bad', !ok);
      if (!ok && !bad) bad = el;
    });
    if (!bad && !(has(v.city) && IL_ILCE[v.city].split(',').includes(v.district))) { bad = citySel; citySel.classList.add('bad'); }
    if (bad) {
      errEl.textContent = 'Lütfen zorunlu alanları eksiksiz ve doğru doldurun (telefon: 05xx xxx xx xx).';
      bad.focus();
      return;
    }

    if (!/^\d{10,15}$/.test(WHATSAPP_NO)) {
      errEl.textContent = 'Sipariş hattı henüz tanımlı değil. script.js içindeki WHATSAPP_NO değerini girin.';
      return;
    }

    const url = `https://api.whatsapp.com/send?phone=${WHATSAPP_NO}&text=${encodeURIComponent(buildMessage(v))}`;

    // Kullanıcı tıklamasıyla aynı anda açılır; engellenirse aynı sekmede devam eder
    const w = window.open(url, '_blank');
    if (w) w.opener = null; else window.location.href = url;

    // Sepeti temizle ve bilgilendir
    cart = [];
    commit();
    form.reset();
    fillDistricts('');
    $$('.form .bad').forEach((x) => x.classList.remove('bad'));
    $('#waFallback').href = url;
    closeModal($('#checkout'));
    openModal('sent');
  });

  form.addEventListener('input', (e) => e.target.classList.remove('bad'));

  render();
})();
