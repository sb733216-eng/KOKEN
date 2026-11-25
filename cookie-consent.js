/* Çerez Onay Banner'ı, Turksem.com Çerez Politikası Oluşturucu.
   Bu dosyayı head içinde tüm istatistik ve pazarlama script'lerinden önce yükleyin.
   Rıza gerektiren script'leri type="text/plain" data-cookie-consent="analytics" ile erteleyin. */
(function () {
  var policyUrl = new URL("cerez-politikasi.html", document.currentScript.src).href;
  window.dataLayer = window.dataLayer || [];
  function gtag() { window.dataLayer.push(arguments); }
  // Google Consent Mode v2: karar gelene kadar dört sinyal de "denied".
  gtag("consent", "default", { ad_storage: "denied", analytics_storage: "denied", ad_user_data: "denied", ad_personalization: "denied", wait_for_update: 500 });
  try { localStorage.removeItem("cookiesAccepted"); } catch (e) {}

  function unlockScripts() {
    var blocked = document.querySelectorAll('script[type="text/plain"][data-cookie-consent]');
    for (var i = 0; i < blocked.length; i++) {
      var s = document.createElement("script");
      if (blocked[i].src) { s.src = blocked[i].src; } else { s.textContent = blocked[i].textContent; }
      blocked[i].parentNode.replaceChild(s, blocked[i]);
    }
    if (typeof window.cookieConsentGranted === "function") window.cookieConsentGranted();
  }

  function activate() {
    gtag("consent", "update", { ad_storage: "granted", analytics_storage: "granted", ad_user_data: "granted", ad_personalization: "granted" });
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", unlockScripts);
    } else {
      unlockScripts();
    }
  }

  // Kararı silip banner'ı yeniden göstererek ziyaretçinin tercihini değiştirmesini sağlar.
  window.cookieConsentReset = function () {
    try { localStorage.removeItem("cookieConsent"); } catch (e) {}
    location.reload();
  };

  var stored = null;
  try { stored = localStorage.getItem("cookieConsent"); } catch (e) {}
  if (stored === "accepted") activate();

  function mount() {
    var footer = document.querySelector(".footer__copy");
    if (footer) {
      var preferences = document.createElement("button");
      preferences.type = "button";
      preferences.className = "cookie-settings-trigger";
      preferences.textContent = "Çerez tercihlerini değiştir";
      preferences.addEventListener("click", window.cookieConsentReset);
      footer.appendChild(preferences);
    }

    if (stored === "accepted" || stored === "rejected") return;

    var banner = document.createElement("div");
    banner.id = "cookie-banner";
    banner.innerHTML =
      '<p>Sitemizde zorunlu çerezler her zaman etkindir; analitik ve pazarlama çerezleri yalnızca onayınızla kullanılır. ' +
      '<a href="' + policyUrl + '" target="_blank" rel="noopener">Çerez Politikası</a></p>' +
      '<button class="btn btn--primary" type="button" id="cookie-accept">Tümünü Kabul Et</button>' +
      '<button class="btn btn--ghost" type="button" id="cookie-reject">Reddet</button>';
    document.body.appendChild(banner);

    function decide(value) {
      try { localStorage.setItem("cookieConsent", value); } catch (e) {}
      banner.remove();
      if (value === "accepted") activate();
    }

    document.getElementById("cookie-accept").addEventListener("click", function () { decide("accepted"); });
    document.getElementById("cookie-reject").addEventListener("click", function () { decide("rejected"); });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", mount);
  } else {
    mount();
  }
})();
/* Çerez Onay Banner'ı Bitiş */
