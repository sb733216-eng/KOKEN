# Koken Admin Paneli

PHP 8+ ve MySQL ile çalışan, Bootstrap 5 kullanan responsive yönetim paneli ve ürün/kategori yönetimi. Gereken PHP eklentileri: PDO MySQL ve Fileinfo.

## Kurulum

1. PHP 8+ ve MySQL/MariaDB üzerinde PDO MySQL ve Fileinfo eklentilerini etkinleştirin.
2. `schema.sql` dosyasını MySQL'e aktarın.
3. `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` ortam değişkenlerini ayarlayın. Varsayılan veritabanı adı `koken_admin` değeridir.
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

Ana mağaza (`index.html`) önce `api/products.php` ile aktif ürünleri MySQL'den yükler. Veritabanı bağlantısı kesilirse örnek ürünler yalnızca vitrin açıklaması olarak görünür; fiyat ve satın alma kontrolleri kapatılır. Veritabanında ürün yoksa Google Sheets kataloğu denenebilir. Admin panelinde eklediğiniz aktif ürünler mağaza vitrininin kaynağı olur. Kategoriler için Genel, Kavrulmuş, Çiğ, Ezme ve Paketleme kayıtlarını `schema.sql` ekler.

Oturum açma `password_verify()` ile hash doğrular; tüm durum değiştiren işlemler CSRF token gerektirir. Ürün silme ve görsel değiştirme eski dosyayı temizler. Yükleme sırasında dosya uzantısı istemciden alınmaz; MIME türü ve görsel içeriği doğrulanıp rastgele dosya adı kullanılır.

Ek ürün görselleri ürün formundan dosya olarak yüklenir; veritabanındaki `image2` ve `image3` alanları dosya adını, dosyanın kendisi `uploads/products/` klasörünü kullanır. Harici görsel bağlantısı gerektirmez. `database/migrations/2026-10-21-store-settings.sql` dosyası ücretsiz kargo eşiği ve kargo ücreti ayar tablosunu oluşturur. Panelde **Mağaza ayarları** sayfasından değiştirilen değerler sepet, bilgilendirme bandı ve sipariş özetinde kullanılır.

Sipariş, stok, galeri ve dışa aktarma özelliklerini mevcut veritabanına eklemek için phpMyAdmin'de `database/migrations/2026-10-21-orders-stock-gallery.sql` dosyasını bir kez içe aktarın. Mevcut ürünlerde `stock_qty` boş kalır (stok takibi kapalı); ürün düzenleme sayfasından adet girildiğinde stok takibi ve mağaza sepetindeki adet kontrolü etkinleşir. Sipariş sunucuda fiyatı doğrulanarak kaydedilir ve yönetim panelindeki **Siparişler** bölümünde durumlandırılır; iptal edilen siparişlerin takip edilen stoğu geri eklenir. WhatsApp mesajı sipariş numarasını da içerir.

Ürünler sayfasında CSV dışa aktarma bulunur. **Yedekleme** sayfası veritabanı tablolarını ve yüklenen ürün görsellerini tek ZIP olarak indirir; ZIP için XAMPP PHP ayarlarında `extension=zip` etkin olmalıdır.

Ek sipariş/stok geliştirmeleri için `database/migrations/2026-10-22-orders-stock-seo.sql` dosyasını phpMyAdmin'de `koken_admin` veritabanı seçiliyken bir kez çalıştırın. Bu geçiş kargo firması ve takip no alanlarını, stok hareket defterini, stok gelince haber ver taleplerini ve ürün SEO alanlarını ekler. XAMPP mağazasında stok gelince haber ver formu e-posta toplar ve talepleri **Stok & talepler** ekranında listeler; e-posta gönderimi özellikle etkin değildir. Sipariş panelinden kargo firması/takip numarası kaydedilir ve müşteriye gönderilecek bilgilendirme mesajı WhatsApp'ta taslak olarak açılır. Müşteri takip sayfası kargo verisini gösterir.

Dashboard günlük/aylık sipariş ve iptal dışı ciro grafikleri sunar. Sipariş listesi durum, tarih, müşteri, telefon ve kodla filtrelenebilir. Ürün formundaki SEO alanları JSON-LD ürün yapılandırılmış verisine aktarılır. Stok hareketleri sipariş, iptal/yeniden açma ve panelden yapılan stok düzenlemelerinde kaydedilir; yedekleme arşivi yeni tabloları da içerir.

Günlük sipariş kodları 100'den başlar, 999'dan sonra dört ve gerekirse beş haneye çıkar. Kod alanının 5 haneye genişletilmesi için `database/migrations/2026-10-21-short-order-codes.sql` dosyasını mevcut veritabanında bir kez çalıştırın. Müşteri koduyla birlikte siparişte kullandığı telefonu girer. Aynı telefonla aynı gün oluşturulan her sipariş ayrı bir kod alır.
