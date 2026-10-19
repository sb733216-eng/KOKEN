/* ===================== AYARLAR (kolayca değiştirilebilir) ===================== */
// Google E-Tablolar: Dosya > Paylaş > Web'de yayınla > Sayfa seçin, biçim "CSV" > Yayınla > linki buraya yapıştırın.
// Boş bırakılırsa veya veri çekilemezse aşağıdaki yedek (FALLBACK_PRODUCTS) ürünler gösterilir.
const GOOGLE_SHEET_CSV_URL = "https://docs.google.com/spreadsheets/d/e/2PACX-1vQ27fgneO23qLFmXw-XCes8of-EJCWA4Ut-71crc_hwzfBnOFUw-Fe6t3-ejSJskTjrobP5kDo_5meN/pub?output=csv";
const WHATSAPP_NO = "905313385955"; // Ülke koduyla, + ve boşluk olmadan. Örn: 905321234567
const FREE_SHIPPING_LIMIT = 750;     // Bu tutar ve üzerinde kargo ücretsiz (TL)
const SHIPPING_FEE = 60;             // Limit altındaki siparişlerde kargo ücreti (TL)

// Havale/EFT bilgileri (ödeme adımında gösterilir) - buradan değiştirin
const IBAN_INFO = {
  bank: 'Banka Adı',
  holder: 'Köken Fındık',
  iban: 'TR00 0000 0000 0000 0000 0000 00'
};

// Kategori sekmesi adı: Sheet'teki "Kategori" yazısı kullanılır; yedek ürünler için bu adlar kullanılır
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

  // Alt sayfalarda (ürün vitrini yok) mağaza kodu çalışmasın
 
  
  const KEY = 'koken-findik-cart';
  const MAX_QTY = 99;
  const SEP = '--------------------------------';

  const $ = (s) => document.querySelector(s);
  const $$ = (s) => Array.from(document.querySelectorAll(s));
  const money = (n) => Math.round(n * 100) / 100;
  const fmt = (n) => money(n).toLocaleString('tr-TR', { maximumFractionDigits: 2 }) + ' TL';
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /* @pure-start */
  const slug = (s) => String(s || '').toLocaleLowerCase('tr')
    .replace(/ç/g, 'c').replace(/ğ/g, 'g').replace(/ı/g, 'i').replace(/ö/g, 'o').replace(/ş/g, 's').replace(/ü/g, 'u')
    .replace(/[^a-z0-9]/g, '');
  const safeImg = (s) => (/^(https?:\/\/\S+|[^\s:"'<>]+)$/i.test(s) ? s : '');

  // Sheet sütun başlığı -> dahili alan (Türkçe/İngilizce, büyük-küçük harf ve boşluk fark etmez)
   const ALIAS = {
    id: ['id', 'kod', 'urunid'], cat: ['kategori', 'category', 'cat'],
    name: ['ad', 'urun', 'urunadi', 'isim', 'name'], weight: ['gramaj', 'agirlik', 'weight'],
    chip: ['rozet', 'chip'],
    old: ['etiket', 'eskifiyat', 'indirimsizfiyat', 'listefiyati'], // Etiket = indirimsiz (eski) fiyat
    price: ['fiyat', 'price', 'tutar'],
    img: ['resim', 'gorsel', 'foto', 'image', 'img'],
    img2: ['resim2', 'gorsel2', 'foto2', 'image2', 'img2'],
    img3: ['resim3', 'gorsel3', 'foto3', 'image3', 'img3'],
    desc: ['aciklama', 'description', 'detay'],
    ing: ['icindekiler', 'icerik', 'ingredients'],
    active: ['aktif', 'yayinda', 'active']
  };

  function parseCSV(text) {
    const rows = [];
    let row = [], cur = '', q = false;
    text = String(text).replace(/^\uFEFF/, '');
    for (let i = 0; i < text.length; i++) {
      const c = text[i];
      if (q) {
        if (c === '"') { if (text[i + 1] === '"') { cur += '"'; i++; } else q = false; }
        else cur += c;
      } else if (c === '"') q = true;
      else if (c === ',') { row.push(cur); cur = ''; }
      else if (c === '\n' || c === '\r') {
        if (c === '\r' && text[i + 1] === '\n') i++;
        row.push(cur); cur = ''; rows.push(row); row = [];
      } else cur += c;
    }
    if (cur !== '' || row.length) { row.push(cur); rows.push(row); }
    return rows.filter((r) => r.some((c) => c.trim() !== ''));
  }

  function parsePrice(v) {
    let s = String(v || '').replace(/[^\d.,]/g, '');
    if (!s) return NaN;
    if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.');
    else if (/^\d{1,3}(\.\d{3})+$/.test(s)) s = s.replace(/\./g, '');
    return Number(s);
  }

  function sheetToProducts(text) {
    const rows = parseCSV(text);
    if (rows.length < 2) return [];
    const cols = {};
    rows[0].forEach((h, i) => {
      const k = slug(h);
      for (const f in ALIAS) if (ALIAS[f].includes(k) && !(f in cols)) cols[f] = i;
    });
    if (!('name' in cols) || !('price' in cols)) return [];
    const seen = new Set(), out = [];
    rows.slice(1).forEach((r, n) => {
      const g = (f) => (f in cols ? String(r[cols[f]] || '').trim() : '');
      if (['hayir', '0', 'false', 'pasif', 'no'].includes(slug(g('active')))) return;
      const name = g('name'), price = parsePrice(g('price'));
      if (!name || !(price > 0)) return;
      const id = g('id') || slug(name) || String(n + 1);
      if (seen.has(id)) return;
      seen.add(id);
      const old = parseOldPrice(g('old'));
      const disc = old > price ? Math.round(((old - price) / old) * 100) : 0;
      out.push({ id, cat: slug(g('cat')), catLabel: g('cat'), name, weight: g('weight'), chip: g('chip'), price,
        old: disc >= 1 ? old : 0, disc: disc >= 1 ? disc : 0,
        img: safeImg(g('img')), img2: safeImg(g('img2')), img3: safeImg(g('img3')),
        desc: g('desc'), ing: g('ing') });
    });
    return out;
  }


    // Etiket (eski fiyat) yalnızca sayı ise geçerlidir (350, 350 TL, 1.250,50); "320g Cam Kavanoz" gibi metinler yok sayılır
  function parseOldPrice(raw) {
    return /^\s*\d[\d.,]*\s*(?:tl|₺)?\s*$/i.test(String(raw || '')) ? parsePrice(raw) : NaN;
  }
 
  /* @pure-end */

  const label = (p) => (p.weight ? `${p.name} (${p.weight})` : p.name);

  
  // Fiyat gösterimi: indirim varsa eski fiyat üstü çizili, güncel fiyat vurgulu
  const priceInner = (p) => (p.disc ? `<del>${fmt(p.old)}</del><span>${fmt(p.price)}</span>` : fmt(p.price));
  const priceHTML = (p) => `<span class="price${p.disc ? ' price--sale' : ''}">${priceInner(p)}</span>`;

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

  /* ---------- Google Sheets ---------- */
  async function loadCatalog() {
    const url = String(GOOGLE_SHEET_CSV_URL || '').trim();
    if (!/^https?:\/\//i.test(url)) return FALLBACK_PRODUCTS;
    const ctl = new AbortController();
    const timer = setTimeout(() => ctl.abort(), 8000);
    try {
      const res = await fetch(url, { signal: ctl.signal, cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const list = sheetToProducts(await res.text());
      if (!list.length) throw new Error('Tabloda geçerli ürün bulunamadı (ad ve fiyat sütunları gerekli)');
      return list;
    } catch (err) {
      console.warn('Google Sheets verisi alınamadı, yedek ürünler kullanılıyor:', err);
      return FALLBACK_PRODUCTS;
    } finally { clearTimeout(timer); }
  }

  // Kategori sekmeleri: Sheet'teki benzersiz "Kategori" değerlerinden (trim edilmiş) dinamik oluşur
  function renderFilters() {
    const cats = new Map();
    PRODUCTS.forEach((p) => {
      if (p.cat && !cats.has(p.cat)) cats.set(p.cat, p.catLabel || CAT_NAMES[p.cat] || p.cat);
    });
    $('#filters').innerHTML =
      `<button type="button" data-f="all" aria-pressed="${filter === 'all'}">Tümü</button>` +
      Array.from(cats, ([k, l]) => `<button type="button" data-f="${esc(k)}" aria-pressed="${filter === k}">${esc(l)}</button>`).join('');
  }

  function setProducts(list) {
    PRODUCTS = list;
    byId = new Map(list.map((p) => [p.id, p]));
    const before = cart.length;
    cart = cart.filter((i) => byId.has(i.id)); // artık satışta olmayan ürünleri sepetten düş
    if (cart.length !== before) save();
    if (filter !== 'all' && !list.some((p) => p.cat === filter)) filter = 'all';
    renderFilters();
    renderGrid();
    render();
  }




  /* ---------- Vitrin ---------- */

    /* ---------- Kart üzeri durumsal adet butonu ---------- */
  const cartQty = (id) => { const i = cart.find((x) => x.id === id); return i ? i.qty : 0; };

  function addControlHTML(id) {
    const q = cartQty(id), e = esc(id);
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
    return `
    <article class="card">
      <div class="card__img" data-view="${id}">
        ${p.img ? `<img src="${esc(p.img)}" alt="${esc(label(p))}" width="400" height="300" loading="lazy">` : ''}
                <div class="card__tags">
          ${p.disc ? `<span class="disc">%${p.disc} İndirim</span>` : ''}
          <span class="tag">Yeni Mahsul</span>
        </div>
      </div>
      <div class="card__body">
        <span class="chip">${esc(p.chip || p.weight || 'Fındık')}</span>
               <h3 data-view="${id}">${esc(p.name)}</h3>
        ${badgeHTML(p, 'badges--sm')}
        <div class="rating" aria-label="5 üzerinden 4,9 puan"><span class="stars" aria-hidden="true">★★★★★</span><b>4.9</b></div>
        <div class="card__bottom">
                   ${priceHTML(p)}
          <div class="card__actions">
            <button type="button" class="btn btn--ghost" data-view="${id}">İncele</button>
                        ${addControlHTML(p.id)}
          </div>
        </div>
      </div>
    </article>`;
  }

  function renderGrid() {
    const list = filter === 'all' ? PRODUCTS : PRODUCTS.filter((p) => p.cat === filter);
    $('#grid').innerHTML = list.length ? list.map(cardHTML).join('') : '<p class="grid__msg">Bu kategoride ürün bulunamadı.</p>';
  }

  $('#filters').addEventListener('click', (e) => {
    const b = e.target.closest('button[data-f]');
    if (!b) return;
    filter = b.dataset.f;
    $$('#filters button').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
    renderGrid();
  });

       $('#grid').addEventListener('click', (e) => {
    const add = e.target.closest('[data-add]');
    if (add) { addToCart(add.dataset.add, 1, true); showToast(add.dataset.add); return; }
    const inc = e.target.closest('[data-inc]');
    if (inc) { addToCart(inc.dataset.inc, 1, true); showToast(inc.dataset.inc); return; }
    const dec = e.target.closest('[data-dec]');
    if (dec) { changeCartQty(dec.dataset.dec, -1); return; }
    const v = e.target.closest('[data-view]');
    if (v) openDetail(v.dataset.view);
  });

  function flash(btn) {
    const t = btn.textContent;
    btn.textContent = 'Eklendi ✓';
    btn.classList.add('added');
    setTimeout(() => { btn.textContent = t; btn.classList.remove('added'); }, 900);
  }

  /* ---------- Sepet ---------- */
   // quiet = true: kart üzerindeki butonlar sepet çekmecesini açmaz (detay modalı açar)
  function addToCart(id, n, quiet) {
    if (!byId.has(id)) return;
    const f = cart.find((i) => i.id === id);
    if (f) f.qty = Math.min(MAX_QTY, f.qty + n); else cart.push({ id, qty: Math.min(MAX_QTY, n) });
    commit();
    const c = $('#openCart');
    c.classList.remove('bump'); void c.offsetWidth; c.classList.add('bump');
    if (!quiet) openDrawer();
  }


  // Adet 1 iken azaltılırsa ürün sepetten silinir (kart butonu "Sepete Ekle"ye döner)
  function changeCartQty(id, delta) {
    const it = cart.find((i) => i.id === id);
    if (!it) return;
    it.qty = Math.min(MAX_QTY, it.qty + delta);
    if (it.qty <= 0) cart = cart.filter((i) => i !== it);
    commit();
  }

  /* ---------- Sepet geri bildirimi (3 sn) ---------- */
  const toast = $('#addToast');
  let toastTimer = null;

  function showToast(id) {
    const p = byId.get(id);
    if (!p) return;
    const q = cartQty(id);
    $('#toastMsg').textContent = q > 1 ? `${p.name}: sepette ${q} adet` : `${p.name} sepete eklendi`;
    const tm = toast.querySelector('.toast__timer');
    tm.style.animation = 'none'; void tm.offsetWidth; tm.style.animation = ''; // süre çubuğunu baştan başlat
    toast.classList.add('on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(hideToast, 3000);
  }
  function hideToast() { toast.classList.remove('on'); clearTimeout(toastTimer); }

  $('#toastContinue').addEventListener('click', hideToast);
  $('#toastCheckout').addEventListener('click', () => { hideToast(); openDrawer(); });



    /* ---------- Sepet içi kargo tamamlayıcı öneri ---------- */
  let upsellId = null;

  // Sepette olmayan ürünlerden önce Ezme kategorisini, yoksa sıradaki ürünü önerir
  function pickUpsell() {
    const inCart = new Set(cart.map((i) => i.id));
    const rest = PRODUCTS.filter((p) => !inCart.has(p.id));
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
      if (up) $('#upsellText').innerHTML = `<span class="upsell__lead">Ücretsiz kargo hakkı için sepete</span> <strong class="upsell__name">${esc(label(up))}</strong> <span class="upsell__price">${fmt(up.price)}</span> <span class="upsell__lead">eklemek ister misiniz?</span>`;
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
  const lock = () => document.body.classList.toggle('no-scroll', !!document.querySelector('.drawer.on, .modal.on'));

  function openDrawer() {
    drawer.classList.add('on'); overlay.classList.add('on');
    drawer.setAttribute('aria-hidden', 'false'); lock();
    $('#closeCart').focus({ preventScroll: true });
  }

  hideToast();

  function closeDrawer() {
    drawer.classList.remove('on'); overlay.classList.remove('on');
    drawer.setAttribute('aria-hidden', 'true'); lock();
  }
  function openModal(id) {
    const m = $('#' + id);
    m.classList.add('on'); m.setAttribute('aria-hidden', 'false'); lock();
    const f = m.querySelector('input,select,.btn');
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


  /* ---------- Rozetler ve SSS ---------- */
  const EZME_BADGES = ['%100 Karadeniz Fındığı', 'Katkısız & İlave Yağsız', 'Pancar Şekeri ile Üretilmiştir'];
  const FAQ_CATS = ['ezme']; // SSS'nin gösterileceği kategoriler. Tüm ürünlerde göstermek için: ['ezme', 'kavrulmus', 'cig', 'paketleme']
  const FAQ = [
    ['Ürünün üzerinde yağ tabakası birikmesi normal mi?', 'Evet. Kıvam artırıcı ve emülgatör bulunmadığından fındığın kendi doğal yağı üste toplanır. Tüketmeden önce karıştırmanız yeterlidir.'],
    ['İlave palm yağı, trans yağ veya koruyucu var mı?', 'Hayır. Yalnızca birinci kalite Karadeniz fındığı ve pancar şekeri içerir. İlave yağ veya katkı barındırmaz.'],
    ['Kavanoz açıldıktan sonra nasıl saklanmalıdır?', 'Buzdolabına koymayınız. 18-24°C oda sıcaklığında, doğrudan güneş almayan serin ve kuru yerde saklayınız. Raf ömrü 12 aydır.'],
    ['Siparişim ne zaman kargoya verilir?', "Haftalık taze üretim döngüsüyle hazırlanır; saat 14:00'e kadar verilen siparişler aynı gün kargoya teslim edilir."]
  ];

  const catBadges = (p) => (p.cat === 'ezme' ? EZME_BADGES : []);
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


  /* ---------- Ürün detay modalı ---------- */
  let dId = null, dQty = 1;

  function syncDetailQty() {
    const p = byId.get(dId);
    if (!p) return;
    $('#dQty').textContent = dQty;
    $('#dAdd').textContent = `Sepete Ekle · ${fmt(p.price * dQty)}`;
  }

  // Ana görsel + (varsa) küçük önizlemeler
  function setMainImage(src, alt) {
    const img = $('#dImg');
    img.style.visibility = '';
    if (src) { img.src = src; img.alt = alt; img.hidden = false; }
    else { img.removeAttribute('src'); img.hidden = true; }
  }

  function buildThumbs(imgs) {
    const box = $('#dThumbs');
    $('.detail__stage').classList.toggle('is-gallery', imgs.length > 1); // imleç ipucu yalnızca çoklu görselde
    if (imgs.length < 2) { box.innerHTML = ''; box.hidden = true; return; } // tek görsel: önizleme alanı yok
    box.innerHTML = imgs.map((src, i) =>
      `<button type="button" class="thumb${i === 0 ? ' on' : ''}" data-src="${esc(src)}" aria-label="Görsel ${i + 1}" aria-pressed="${i === 0}"><img src="${esc(src)}" alt="" loading="lazy"></button>`
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

  function openDetail(id) {
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
        const imgs = Array.from(new Set([p.img, p.img2, p.img3].filter(Boolean)));
    setMainImage(imgs[0], label(p));
    buildThumbs(imgs);

    const dd = $('#dDisc');
    dd.textContent = p.disc ? `%${p.disc} İndirim` : '';
    dd.hidden = !p.disc;

    //const chip = $('#dChip');
   // chip.textContent = p.chip || p.weight;
   // chip.hidden = !(p.chip || p.weight);

    $('#dName').textContent = p.name;
    $('#dPrice').innerHTML = priceInner(p);
    $('#dPrice').classList.toggle('price--sale', !!p.disc);

    const desc = $('#dDesc');
    desc.textContent = p.desc;
    desc.hidden = !p.desc;

    $('#dIng').textContent = p.ing;
    $('#dIngSec').hidden = !p.ing;

    $('.detail').scrollTop = 0;
    syncDetailQty();
    openModal('detail');
  }

  $('#dMinus').addEventListener('click', () => { dQty = Math.max(1, dQty - 1); syncDetailQty(); });
  $('#dPlus').addEventListener('click', () => { dQty = Math.min(MAX_QTY, dQty + 1); syncDetailQty(); });
  $('#dAdd').addEventListener('click', () => {
    const id = dId, n = dQty;
    closeModal($('#detail'));
    addToCart(id, n);
  });

  /* ---------- Yasal metinler (footer linkleri) ---------- */
  const NOTE = '<p class="note">Bu metin örnektir. Yayına almadan önce [köşeli] alanları doldurun ve bir hukuk danışmanına kontrol ettirin.</p>';
  const LEGAL = {
    iade: { title: 'İptal ve İade', html: NOTE + `
      <h4>1. Cayma hakkı</h4>
      <p>Alıcı, ürünü teslim aldığı tarihten itibaren 14 gün içinde, gerekçe göstermeden cayma hakkını kullanabilir. Talep WhatsApp hattımız veya e-posta ile iletilir.</p>
      <h4>2. Gıda güvenliği şartı</h4>
      <ul>
        <li>İade; yalnızca <strong>ambalajı açılmamış</strong>, mührü ve koruyucu bandı bozulmamış, kullanılmamış ürünlerde kabul edilir.</li>
        <li>Ambalajı açılmış gıda ürünleri sağlık ve hijyen nedeniyle iade edilemez (Mesafeli Sözleşmeler Yönetmeliği istisnası).</li>
      </ul>
      <h4>3. Hasarlı veya ayıplı teslimat</h4>
      <ul>
        <li>Paketi kargo görevlisinin yanında kontrol edin. Ezik, yırtık veya açılmış paketi teslim almayın ve <strong>kargo hasar tespit tutanağı</strong> düzenletin.</li>
        <li>Tutanağın bir örneğini ve ürün fotoğraflarını teslimattan itibaren [2] gün içinde bize iletin; ürün yenilenir veya bedeli iade edilir.</li>
      </ul>
      <h4>4. İade süreci</h4>
      <p>Onaylanan iadelerde ürün bedeli, ürünün tarafımıza ulaşmasından itibaren 14 gün içinde ödemenin yapıldığı yönteme iade edilir. Cayma hakkında iade kargo bedeli alıcıya, hasarlı veya ayıplı üründe satıcıya aittir.</p>
      <h4>5. Sipariş iptali</h4>
      <p>Kargoya verilmeden önce iptal için WhatsApp hattımızdan bize ulaşabilirsiniz. Kargoya verilmiş siparişlerde yukarıdaki iade koşulları uygulanır.</p>` },
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
      <p>Siparişler WhatsApp üzerinden onaylandıktan sonra [1-2] iş günü içinde hazırlanıp [kargo firması] ile gönderilir. Teslimat süresi bölgeye göre genellikle [1-3] iş günüdür.</p>
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
      <p>Siparişleriniz WhatsApp üzerinden alındığı için durumunu aynı hat üzerinden öğrenebilirsiniz. Adınız ve telefon numaranızla bize yazmanız yeterlidir.</p>
      <p>Kargoya verildiğinde takip numaranız tarafınıza iletilir.</p>` },
    satis: { title: 'Mesafeli Satış Sözleşmesi', html: NOTE + `
      <h4>Madde 1 – Taraflar</h4>
      <p>SATICI: Köken Fındık, [firma ünvanı, adres]. ALICI: Sipariş mesajında adı, telefonu ve teslimat adresi yer alan kişi.</p>
      <h4>Madde 2 – Konu</h4>
      <p>ALICI'nın WhatsApp üzerinden sipariş ettiği ürünlerin satışı ve teslimidir. Ürünlerin türü, adedi, fiyatı ve kargo ücreti sipariş özetinde yer alır.</p>
      <h4>Madde 3 – Cayma hakkı</h4>
      <p>Cayma hakkı ve gıda ürünlerine ilişkin istisnalar "İptal ve İade" metninde belirtilmiştir.</p>
      <h4>Madde 4 – Uyuşmazlık</h4>
      <p>Uyuşmazlıklarda ALICI'nın yerleşim yerindeki Tüketici Hakem Heyetleri ve Tüketici Mahkemeleri yetkilidir.</p>` },
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

  /* ---------- Teslimat formu ---------- */
  const form = $('#orderForm'), errEl = $('#formErr');

  $('#checkoutBtn').addEventListener('click', () => {
    const ls = lines();
    if (!ls.length) return;
    const { ship, total } = totals();
    $('#coList').innerHTML = ls.map((l) => `<li><span>${esc(label(l.p))} × ${l.qty}</span><span>${fmt(l.p.price * l.qty)}</span></li>`).join('');
    const cs = $('#coShip');
    cs.textContent = ship ? fmt(ship) : 'Ücretsiz';
    cs.classList.toggle('free', !ship);
    $('#coTotal').textContent = fmt(total);
      showStep(1);
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

   /* ---------- 2 adımlı sipariş: 1) teslimat bilgileri  2) ödeme seçimi ---------- */
  const PAY_LABELS = { iban: 'Havale/EFT', kapida: 'Kapıda Ödeme' };
  let step = 1;
  let copyTimer = null;

  $('#ibanBank').textContent = IBAN_INFO.bank;
  $('#ibanHolder').textContent = IBAN_INFO.holder;
  $('#ibanNo').textContent = IBAN_INFO.iban;

  function showStep(n) {
    step = n;
    $('#stepInfo').hidden = n !== 1;
    $('#stepPay').hidden = n !== 2;
    $('#coTitle').textContent = n === 1 ? 'Teslimat bilgileri (1/2)' : 'Ödeme seçimi (2/2)';
    errEl.textContent = '';
    $('#payErr').textContent = '';
    const body = $('#checkout .modal__body');
    if (body) body.scrollTop = 0;
  }

  function readValues() {
    const v = {};
    ['name', 'phone', 'city', 'district', 'address', 'note'].forEach((k) => {
      v[k] = form.elements[k].value.replace(/\s+/g, ' ').trim();
    });
    return v;
  }

  // 1. adım doğrulaması
  function readInfo() {
    errEl.textContent = '';
    const v = readValues();
    let bad = null;
    ['name', 'phone', 'city', 'district', 'address'].forEach((k) => {
      const el = form.elements[k];
      const ok = v[k] && el.checkValidity();
      el.classList.toggle('bad', !ok);
      if (!ok && !bad) bad = el;
    });
    if (!bad && !(has(v.city) && IL_ILCE[v.city].split(',').includes(v.district))) { bad = citySel; citySel.classList.add('bad'); }
    if (bad) {
      errEl.textContent = 'Lütfen zorunlu alanları eksiksiz ve doğru doldurun (telefon: 05xx xxx xx xx).';
      bad.focus();
      return null;
    }
    return v;
  }

  function goToPay() {
    if (!readInfo()) return;
    showStep(2);
    const first = $('#stepPay input[name="pay"]');
    if (first) first.focus({ preventScroll: true });
  }

  $('#toPayBtn').addEventListener('click', goToPay);
  $('#backBtn').addEventListener('click', () => { showStep(1); form.elements.name.focus({ preventScroll: true }); });

  $$('input[name="pay"]').forEach((r) => r.addEventListener('change', () => {
    $('#ibanBox').hidden = form.elements.pay.value !== 'iban';
    $('#payErr').textContent = '';
  }));

  // IBAN'ı boşluksuz kopyalar (banka uygulamaları için en güvenlisi)
  $('#ibanCopy').addEventListener('click', async () => {
    const btn = $('#ibanCopy');
    const text = IBAN_INFO.iban.replace(/\s+/g, '');
    let ok = false;
    try { await navigator.clipboard.writeText(text); ok = true; }
    catch (err) { // clipboard API yoksa/izin yoksa eski yöntem
      const ta = document.createElement('textarea');
      ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
      document.body.appendChild(ta); ta.select();
      try { ok = document.execCommand('copy'); } catch (e2) { ok = false; }
      ta.remove();
    }
    btn.textContent = ok ? 'Kopyalandı ✓' : 'Kopyalanamadı, IBAN\'ı elle seçin';
    btn.classList.toggle('copied', ok);
    clearTimeout(copyTimer);
    copyTimer = setTimeout(() => { btn.textContent = 'IBAN\'ı Kopyala'; btn.classList.remove('copied'); }, 2000);
  });

  /* ---------- WhatsApp mesajı ve yönlendirme ---------- */
  function buildMessage(v) {
    const { sub, ship, total } = totals();
    const items = lines().map((l) => `• ${l.qty}x ${label(l.p)} - ${fmt(l.p.price * l.qty)}`);
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
      `*Not:* ${v.note || '-'}`,
      `*Ödeme Tercihi:* ${v.pay}`
    ].join('\n');
  }

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!lines().length) return;
    if (step === 1) { goToPay(); return; } // 1. adımda Enter: ödeme adımına geç

    const pay = form.elements.pay.value;
    if (!pay) { $('#payErr').textContent = 'Lütfen bir ödeme yöntemi seçin.'; return; }

    if (!/^\d{10,15}$/.test(WHATSAPP_NO)) {
      $('#payErr').textContent = 'Sipariş hattı henüz tanımlı değil. script.js içindeki WHATSAPP_NO değerini girin.';
      return;
    }

    const v = readValues();
    v.pay = PAY_LABELS[pay];
    const url = `https://api.whatsapp.com/send?phone=${WHATSAPP_NO}&text=${encodeURIComponent(buildMessage(v))}`;

    // Kullanıcı tıklamasıyla aynı anda açılır; engellenirse aynı sekmede devam eder
    const w = window.open(url, '_blank');
    if (w) w.opener = null; else window.location.href = url;

    cart = [];
    commit();
    form.reset();
    fillDistricts('');
    $('#ibanBox').hidden = true;
    showStep(1);
    $$('.form .bad').forEach((x) => x.classList.remove('bad'));
    $('#waFallback').href = url;
    closeModal($('#checkout'));
    openModal('sent');
  });
  form.addEventListener('input', (e) => e.target.classList.remove('bad'));


  

  
  $('#grid').innerHTML = '<p class="grid__msg">Ürünler yükleniyor…</p>';
  render();
  loadCatalog().then(setProducts);
})();
