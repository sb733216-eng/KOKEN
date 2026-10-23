(() => {
  'use strict';

  const STORAGE_KEY = 'koken_cookie_consent_v1';
  // GA4 web stream Measurement ID (format: G-XXXXXXXXXX). Keep empty until configured.
  const GA_MEASUREMENT_ID = '';
  let memoryConsent = null;

  const readConsent = () => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch (error) { return memoryConsent; }
  };

  const loadAnalytics = () => {
    if (!GA_MEASUREMENT_ID || !/^G-[A-Z0-9]+$/i.test(GA_MEASUREMENT_ID)) return;
    if (document.querySelector('script[data-koken-ga]')) return;
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', GA_MEASUREMENT_ID);
    const script = document.createElement('script');
    script.async = true;
    script.dataset.kokenGa = 'true';
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(GA_MEASUREMENT_ID)}`;
    document.head.appendChild(script);
  };

  const bannerMarkup = `
    <section class="cookie-consent" id="cookieConsent" role="region" aria-labelledby="cookieTitle" hidden>
      <div class="cookie-consent__copy">
        <h2 id="cookieTitle">Çerez tercihleri</h2>
        <p>Sepet ve tercihlerin çalışması için gerekli yerel depolama kullanılır. İsteğe bağlı analiz araçları yalnızca onayınızla etkinleşir. <a href="cerez-politikasi.html">Çerez politikası</a></p>
        <div class="cookie-consent__settings" id="cookieSettings" hidden>
          <label><input type="checkbox" checked disabled> Gerekli depolama (her zaman açık)</label>
          <label><input type="checkbox" id="cookieAnalytics"> İsteğe bağlı analiz</label>
        </div>
      </div>
      <div class="cookie-consent__actions">
        <button class="btn btn--ghost" type="button" id="cookieReject">Yalnızca gerekli</button>
        <button class="btn btn--ghost" type="button" id="cookieCustomize" aria-expanded="false" aria-controls="cookieSettings">Tercihleri seç</button>
        <button class="btn btn--primary" type="button" id="cookieAccept">Tümünü kabul et</button>
        <button class="btn btn--primary" type="button" id="cookieSave" hidden>Tercihleri kaydet</button>
      </div>
    </section>`;

  document.body.insertAdjacentHTML('beforeend', bannerMarkup);
  const banner = document.getElementById('cookieConsent');
  const settings = document.getElementById('cookieSettings');
  const analytics = document.getElementById('cookieAnalytics');
  const customize = document.getElementById('cookieCustomize');
  const accept = document.getElementById('cookieAccept');
  const reject = document.getElementById('cookieReject');
  const saveButton = document.getElementById('cookieSave');

  const saveConsent = (analyticsAllowed) => {
    const consent = { necessary: true, analytics: Boolean(analyticsAllowed), savedAt: new Date().toISOString() };
    memoryConsent = consent;
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(consent)); } catch (error) { /* Current-page choice still applies. */ }
    banner.hidden = true;
    if (consent.analytics) loadAnalytics();
  };

  const openPreferences = () => {
    banner.hidden = false;
    settings.hidden = false;
    customize.hidden = true;
    accept.hidden = true;
    saveButton.hidden = false;
    reject.hidden = false;
    const consent = readConsent();
    analytics.checked = Boolean(consent && consent.analytics);
    customize.setAttribute('aria-expanded', 'true');
    analytics.focus();
  };

  customize.addEventListener('click', () => {
    settings.hidden = false;
    customize.hidden = true;
    accept.hidden = true;
    saveButton.hidden = false;
    customize.setAttribute('aria-expanded', 'true');
    analytics.focus();
  });
  accept.addEventListener('click', () => saveConsent(true));
  reject.addEventListener('click', () => saveConsent(false));
  saveButton.addEventListener('click', () => saveConsent(analytics.checked));

  const settingsTrigger = document.createElement('button');
  settingsTrigger.type = 'button';
  settingsTrigger.className = 'cookie-settings-trigger';
  settingsTrigger.textContent = 'Çerez tercihleri';
  settingsTrigger.addEventListener('click', openPreferences);
  const footerCopy = document.querySelector('.footer__copy');
  (footerCopy || document.body).appendChild(settingsTrigger);

  const existingConsent = readConsent();
  if (!existingConsent) banner.hidden = false;
  else if (existingConsent.analytics) loadAnalytics();
})();
