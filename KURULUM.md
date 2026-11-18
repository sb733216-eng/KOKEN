# Köken Fındık — Ana site içinde ürün penceresi ve SEO

Bu sürüm yerel XAMPP sitesine uygulanmıştır. ZIP, önceki ürün sayfası geliştirmelerini de içeren güncel canlıya aktarım paketidir.

## Görünüm ve ürün adresleri

- Ana sayfadaki İncele, sayfayı yeniden yüklemeden mevcut ürün penceresini açar. Ana site arka planda kalır.
- Her ürünün `/urun/ezmeli-cikolata-dolgu.html` biçiminde kendine ait adresi vardır.
- Bu adres doğrudan açıldığında veya yenilendiğinde de aynı ana site ve ürün penceresi görüntülenir. Bağımsız bir ürün ekranı gösterilmez.
- Çarpı, Escape veya arka plana tıklama pencereyi kapatır; müşteri ana sitedeki ürün listesinde kalır. Tarayıcının geri/ileri tuşları da desteklenir.
- Her ürün için ayrı fiziksel HTML dosyası oluşturulmaz. `product.php`, `index.html` ana sayfa şablonunu ve `views/product-modal.php` ürün penceresi şablonunu veritabanındaki ürünle doldurur.
- Önceki uzantısız ürün adresleri `.html` adresine 301 yönlenir. Ürün adını düzenlemek mevcut ürün adresini değiştirmez.

## SEO ve panel

- Mevcut Ürün Ekle/Düzenle ekranı kullanılır. Aktif yeni ürün, ana sayfaya ve site haritasına otomatik eklenir.
- Adres ürün adından otomatik üretilir. Türkçe karakterler sadeleştirilir; aynı ad varsa numaralı ek kullanılır.
- SEO başlığı ürün adresinin `title` alanını, SEO açıklaması `meta description` alanını doldurur. Alanlar boşsa ürün adı ve açıklamasından tamamlanır.
- Ürün adı, açıklaması, fiyatı, görselleri, canonical, paylaşım etiketleri ve Product yapılandırılmış verisi ilk HTML yanıtında sunulur. Bu içerik JavaScript çalışmadan da görülebilir.
- Pencere açıkken ürünün, kapalıyken ana sayfanın başlık ve SEO bilgileri kullanılır.
- `sitemap.xml` aktif ürünlerin `.html` adreslerini veritabanından listeler. Bulunamayan veya pasif ürünler 404 döndürür.

## Canlıya aktarım

1. ZIP içindeki dosyaları mevcut sitenin köküne, aynı klasör yapısını koruyarak yükleyin. `admin`, `api`, `config` ve `views` klasörlerini mevcut klasörlerle birleştirin; `.htaccess` dosyasını da aktarın.
2. Veritabanını yeniden içe aktarmanız veya yeni SQL çalıştırmanız gerekmez. Mevcut ürün, slug, SEO ve adres geçmişi alanları kullanılır.
3. https://kokenfindik.com/urun/ezmeli-cikolata-dolgu.html adresini açın. Ana site arka planda, ürün penceresi açık görünmelidir. Kapatma ve yenilemeyi kontrol edin.
4. https://kokenfindik.com/sitemap.xml adresini kontrol edin. Tarayıcıda Ctrl + F5 ile güncel dosyaları yükleyin.
5. Search Console'da `sitemap.xml` dosyasını gönderin ve ürün URL'lerini URL Denetleme aracında kontrol edin.

Paket veritabanı bağlantı veya e-posta ayarlarını içermez; mevcut bağlantı dosyaları kullanılır. Yerel `/koken/` kurulumu ve canlı alan adı kökü aynı adres kurallarıyla desteklenir.

## Doğrulama

Yerelde ayrı, başsız Chrome kullanıldı. Mevcut 5 ürünün doğrudan adresleri, İncele, yeniden yükleme, kapatma, arka plana tıklama, Escape, geri/ileri, Ctrl+tıklama, mobil görünüm ve sepet/ödeme ekranına geçiş kontrol edildi.

Mevcut admin formundan geçici ürünler eklenerek Türkçe karakter, ad çakışması, SEO alanları, boş alanların tamamlanması, düzenleme sonrası sabit adres, eski adres yönlendirmesi, stok ve pasif ürün davranışı doğrulandı. Geçici ürünler ve ilişkili test kayıtları temizlendi; özgün katalog aynı kaldı.

Bu paket canlı sunucuda otomatik yayımlama yapmaz.
