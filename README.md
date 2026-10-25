# Koken Admin Paneli

PHP 8+ ve MySQL ile çalışan, Bootstrap 5 kullanan responsive yönetim paneli ve ürün/kategori yönetimi. Gereken PHP eklentileri: PDO MySQL ve Fileinfo.

## Kurulum

1. PHP 8+ ve MySQL/MariaDB üzerinde PDO MySQL ve Fileinfo eklentilerini etkinleştirin.
2. `schema.sql` dosyasını MySQL'e aktarın.
3. Yerel XAMPP'de veritabanı varsayılanları `127.0.0.1`, `koken_admin`, `root` ve boş paroladır. Üretimde `APP_ENV=production`, `DB_HOST`, `DB_NAME`, `DB_USER` ve `DB_PASS` değişkenlerini hostingin PHP ortam ayarlarında tanımlayın; ayrı ve yalnızca bu veritabanına gereken yetkileri olan bir kullanıcı kullanın. Değerleri Git'e veya herkese açık dosyalara koymayın. Üretim ortamında değişkenlerden biri eksikse bağlantı bilerek reddedilir.
4. Bu klasörü web köküne yerleştirin. XAMPP'de `C:\\xampp\\htdocs\\koken-admin` konumunda panel `http://localhost/koken-admin/admin/login.php`, mağaza `http://localhost/koken-admin/` adresinde açılır. Uygulama kurulu alt klasörünü otomatik algılar.
5. İlk yöneticiyi bir kez oluşturun. PHP CLI üzerinden proje kökünde çalıştırın:

```bash
php -r '$pdo=new PDO("mysql:host=127.0.0.1;dbname=koken_admin;charset=utf8mb4","root",""); $s=$pdo->prepare("INSERT INTO admin_users(username,password_hash) VALUES(?,?)"); $s->execute(["admin", password_hash("BURAYA_GUCLU_BIR_SIFRE", PASSWORD_DEFAULT)]);'
```

Ardından `https://alan-adiniz/admin/login.php` adresinden giriş yapın. Üretim ortamında HTTPS kullanın. CSS ve Bootstrap CDN'den yüklenir.

## Yapı

- `config/db.php`: PDO bağlantısı ve ortam değişkenleri
- `schema.sql`: ürün, kategori ve yönetici tabloları
- `admin/`: oturum, dashboard, kategori ve ürün CRUD sayfaları
- `uploads/products/`: yüklenen ürün görselleri (JPG, PNG, WEBP, GIF; 5 MB sınırı)
- `assets/admin.css`: responsive panel stilleri

Ana mağaza (`index.html`) aktif ürünleri yalnızca `api/products.php` üzerinden MySQL'den yükler. Veritabanı bağlantısı kesilirse örnek ürünler yalnızca vitrin açıklaması olarak görünür; fiyat ve satın alma kontrolleri kapatılır. Veritabanında aktif ürün yoksa katalog boş gösterilir. Admin panelinde eklediğiniz aktif ürünler mağaza vitrininin kaynağı olur. Kategoriler için Genel, Kavrulmuş, Çiğ, Ezme ve Paketleme kayıtlarını `schema.sql` ekler.

Havale/EFT seçeneği şu anda test amaçlı örnek bilgilerle açıktır; gerçek ödeme almayın. Canlıya geçmeden önce checkout.js içindeki IBAN_INFO değerlerini gerçek banka, hesap sahibi ve doğrulanmış IBAN ile değiştirin; isSample değerini false yapın.

SEO ve mağaza arayüzü: kök dizinde `404.html`, `robots.txt`, `sitemap.xml` ve `favicon.svg` bulunur. Apache/XAMPP için `.htaccess` 404 sayfasını `/koken-admin/404.html` yoluna bağlar; site alan adı köküne taşınırsa bu satırı `/404.html` olarak değiştirin. Sayfalar için title, description, canonical ve Open Graph etiketleri tanımlıdır. Çerez tercihleri isteğe bağlı analiz iznini saklar; Google Analytics yalnızca `cookie-consent.js` içindeki `GA_MEASUREMENT_ID` (GA4 `G-...` kimliği) girildiğinde ve kullanıcı izin verdiğinde yüklenir. Analitik kimliği henüz yapılandırılmadı.

Ürün detay SEO sayfaları için `database/migrations/2026-10-24-product-slugs-and-order-idempotency.sql` dosyasını `koken_admin` veritabanı seçiliyken phpMyAdmin'de **bir kez** çalıştırın. Eski ürünlere `urun-ID` adresi atanır; yeni ürünlerin slug'ı admin formunda boş bırakılırsa ürün adından üretilir. Apache'de `mod_rewrite` etkin olmalıdır. Ardından ürün adresleri `/urun/urun-slug` biçiminde çalışır ve `sitemap.xml` aktif ürünleri veritabanından dinamik listeler. Admin panelinden slug değiştiğinde önceki URL, yeni ürün adresine yönlendirilir.

Bu migration, sipariş tekrar denemelerinin aynı siparişi yeniden kullanması için idempotency anahtarını da ekler. Migration uygulanmadan sipariş API'sinin yeni sürümünü kullanmayın. `APP_ENV` ayarı verilmezse yalnızca `localhost`/loopback HTTP isteği yerel sayılır; diğer istekler üretim kabul edilir. CLI işlemlerinde ortamı açıkça verin (XAMPP geliştirmede `APP_ENV=local`). Üretim bağlantısı için tüm DB değerleri açıkça tanımlanmalı, kullanıcı `root` olmamalı ve parola boş olmamalıdır.

Oturum açma `password_verify()` ile hash doğrular; tüm durum değiştiren işlemler CSRF token gerektirir. Ürün silme ve görsel değiştirme eski dosyayı temizler. Yükleme sırasında dosya uzantısı istemciden alınmaz; MIME türü ve görsel içeriği doğrulanıp rastgele dosya adı kullanılır.

Ek ürün görselleri ürün formundan dosya olarak yüklenir; veritabanındaki `image2` ve `image3` alanları dosya adını, dosyanın kendisi `uploads/products/` klasörünü kullanır. Harici görsel bağlantısı gerektirmez. `database/migrations/2026-10-21-store-settings.sql` dosyası ücretsiz kargo eşiği ve kargo ücreti ayar tablosunu oluşturur. Panelde **Mağaza ayarları** sayfasından değiştirilen değerler sepet, bilgilendirme bandı ve sipariş özetinde kullanılır.

Sipariş, stok, galeri ve dışa aktarma özelliklerini mevcut veritabanına eklemek için phpMyAdmin'de `database/migrations/2026-10-21-orders-stock-gallery.sql` dosyasını bir kez içe aktarın. Mevcut ürünlerde `stock_qty` boş kalır (stok takibi kapalı); ürün düzenleme sayfasından adet girildiğinde stok takibi ve mağaza sepetindeki adet kontrolü etkinleşir. Sipariş sunucuda fiyatı doğrulanarak kaydedilir ve yönetim panelindeki **Siparişler** bölümünde durumlandırılır; iptal edilen siparişlerin takip edilen stoğu geri eklenir. WhatsApp mesajı sipariş numarasını da içerir.

Ürünler sayfasında CSV dışa aktarma bulunur. **Yedekleme** sayfası veritabanı tablolarını ve yüklenen ürün görsellerini tek ZIP olarak indirir; ZIP için XAMPP PHP ayarlarında `extension=zip` etkin olmalıdır.

Ek sipariş/stok geliştirmeleri için `database/migrations/2026-10-22-orders-stock-seo.sql` dosyasını phpMyAdmin'de `koken_admin` veritabanı seçiliyken bir kez çalıştırın. Bu geçiş kargo firması ve takip no alanlarını, stok hareket defterini, stok gelince haber ver taleplerini ve ürün SEO alanlarını ekler. XAMPP mağazasında stok gelince haber ver formu e-posta toplar ve talepleri **Stok & talepler** ekranında listeler; e-posta gönderimi özellikle etkin değildir. Sipariş panelinden kargo firması/takip numarası kaydedilir ve müşteriye gönderilecek bilgilendirme mesajı WhatsApp'ta taslak olarak açılır. Müşteri takip sayfası kargo verisini gösterir.

Dashboard günlük/aylık sipariş ve iptal dışı ciro grafikleri sunar. Sipariş listesi durum, tarih, müşteri, telefon ve kodla filtrelenebilir. Ürün formundaki SEO alanları JSON-LD ürün yapılandırılmış verisine aktarılır. Stok hareketleri sipariş, iptal/yeniden açma ve panelden yapılan stok düzenlemelerinde kaydedilir; yedekleme arşivi yeni tabloları da içerir.

Güncel ürün görseli, sipariş numarası ve mahsul etiketi değişiklikleri için `database/migrations/2026-10-23-local-images-order-codes-new-harvest.sql` dosyasını `koken_admin` veritabanı seçiliyken bir kez çalıştırın. Bu geçiş ürünlerin “Yeni Mahsul” alanını ve global sipariş numarası sayacını ekler; daha önce veritabanına kaydedilmiş GitHub/Google görsel bağlantılarını `uploads/products/` içindeki yerel dosyalara dönüştürür. Ürün görselleri bundan sonra ürün formundan sunucuya yüklenir; mağaza tarafında harici katalog görseli okunmaz. Yeni sipariş numaraları 100’den başlar, sayı 999’u geçince 4 ve sonrasında 5 haneye büyür. `tracking_code` veritabanında ayrı, rastgele gizli anahtar olarak kalır; yeni müşteriye gösterilmez. Sipariş takibinde kısa sipariş numarası ve telefon gerekir. Eski siparişlerin daha önce paylaşılmış takip kodları geçiş dönemi için kabul edilir.

Sipariş hız sınırlaması için `database/migrations/2026-10-23-security-rate-limits.sql` dosyasını da `koken_admin` veritabanı seçiliyken bir kez çalıştırın. Eski kısa sayısal kodları çeviren `2026-10-23-rotate-order-tracking-codes.php` aracı yalnızca tarihsel geçiş için tutulmuştur; yeni sipariş akışında kullanılmaz.

API hız sınırları MySQL'de saklandığı için yeni oturum açmak bunları sıfırlamaz. Sipariş oluşturma IP başına 10/10 dk, takip IP başına 20/10 dk ve telefon başına 8/10 dk, stok bildirimi IP başına 10/saat ve e-posta başına 3/saat ile sınırlandırılır. Admin girişinde başarısız denemeler kullanıcı adına göre 5/15 dk, IP'ye göre 20/15 dk sınırındadır.

Yedekleme ekranından indirilen ZIP, sipariş ve müşteri adres/telefon bilgileriyle yönetici parola hash'lerini içerir. Dosyayı web kökü dışında, erişimi kısıtlı ve şifreli ayrı bir konumda saklayın. Geri yüklemeyi önce üretim dışı bir kopyada deneyin; veritabanı `database.sql` içe aktarımıyla, görseller ise `uploads/products/` diziniyle geri yüklenir.
Sipariş e-postası, kargo durumu ve ödeme durumu için `database/migrations/2026-10-26-order-payments-email-outbox.sql` dosyasını phpMyAdmin'de **koken_admin veritabanı seçiliyken bir kez** çalıştırın. Migration mevcut siparişlerde ödeme yöntemini değiştirmez ve ödeme durumunu `Belirtilmemiş` olarak bırakır. Yeni havale/EFT siparişleri `Ödeme bekleniyor`, kapıda ödeme siparişleri `Kapıda tahsil edilecek` durumuyla oluşturulur. Admin > Siparişler bölümünde ödeme durumu sipariş/kargo durumundan bağımsız güncellenebilir; değişiklikler geçmiş tablosuna yazılır. Migration'ı çalıştırmadan yeni API/admin PHP dosyalarını kullanmayın.

E-posta için PHP `mail()` kullanılmaz. PHPMailer Composer bağımlılığıdır; kurulumda `composer install` çalıştırın (XAMPP PHP'de zip eklentisi yoksa `composer install --prefer-source` kullanılabilir). PHP `openssl` ve `pdo_mysql` eklentileri etkin olmalıdır. Gmail hesabında 2 Adımlı Doğrulama'yı açıp bir Uygulama Şifresi oluşturun. Normal Gmail parolanızı kullanmayın. `config/mail.example.php` dosyasını örnek olarak kullanıp gerçek bilgileri `C:\xampp\private\koken-mail.php` konumuna yazın; bu dizin `htdocs` dışındadır ve dosya Git'e eklenmemelidir. Alternatif olarak hostingin sunucu ortam değişkenlerinde `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` tanımlanabilir. Gmail için host `smtp.gmail.com`, port `587`, şifreleme `starttls` olmalıdır. TLS sertifika doğrulaması açık kalır. Gönderici adresi Gmail hesabı veya hesabınızda doğrulanmış alias olmalıdır.

Yeni sipariş veritabanı transaction'ı başarıyla commit edildikten sonra e-posta kuyruğa alınır ve gönderim denenir. SMTP yapılandırması eksik/hatalı olsa bile sipariş ve stok kaydı geri alınmaz. Müşteri onay ekranında WhatsApp bildirimi gerektiğini görür; kısa kodlu mesajı kullanıcı WhatsApp'ta kendisi gönderir. E-posta, satıcı onayının yerine geçmez. Admin ekranı başarısız bildirimleri gösterir ve aynı kuyruk kaydını yeniden dener; `sent` durumu yalnızca SMTP sunucusunun mesajı kabul ettiğini gösterir, gelen kutusuna teslim garantisi değildir. Gerçek sipariş veya müşteriye test e-postası oluşturmayın; yapılandırmayı göndermeden önce yalnızca admin ekranındaki durumdan doğrulayın.

Sepette “Siparişi Tamamla” seçildiğinde teslimat, zorunlu e-posta, ödeme seçimi ve sipariş kaydı `siparis-tamamla.html` üzerindeki tek checkout akışında yürür. Sipariş kaydı başarılı olduğunda müşteri `siparis-tamamlandi.html` onay ekranına yönlendirilir. Bu ekranda kısa sipariş numarası gösterilir ve takip bağlantısına numara otomatik taşınır.
