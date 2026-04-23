<?php
$kodusLoaderLogo = $app_root . 'dist/img/kodus.png';
$kodusLoaderTheme = 'dark';

if (isset($themePreference) && is_string($themePreference) && $themePreference !== '') {
    $kodusLoaderTheme = strtolower($themePreference) === 'light' ? 'light' : 'dark';
} elseif (isset($isDarkTheme)) {
    $kodusLoaderTheme = $isDarkTheme ? 'dark' : 'light';
} else {
    if (!function_exists('theme_current_preference')) {
        $themeHelpersPath = __DIR__ . '/theme_helpers.php';
        if (is_file($themeHelpersPath)) {
            require_once $themeHelpersPath;
        }
    }

    if (function_exists('theme_current_preference')) {
        $kodusLoaderTheme = theme_current_preference() === 'light' ? 'light' : 'dark';
    }
}
?>
<script>
  document.documentElement.setAttribute('data-kodus-loader-theme', '<?php echo htmlspecialchars($kodusLoaderTheme, ENT_QUOTES, 'UTF-8'); ?>');
</script>
<style>
  html.kodus-page-loading {
    overflow: hidden;
  }

  html.kodus-page-loading .preloader {
    opacity: 0 !important;
    visibility: hidden !important;
    pointer-events: none !important;
  }

  .kodus-page-loader {
    position: fixed;
    inset: 0;
    z-index: 200000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    background:
      radial-gradient(circle at top left, rgba(13, 110, 253, 0.18), transparent 34%),
      radial-gradient(circle at bottom right, rgba(32, 201, 151, 0.14), transparent 28%),
      rgba(7, 12, 20, 0.88);
    backdrop-filter: blur(10px);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.28s ease, visibility 0.28s ease;
  }

  html.kodus-page-loading .kodus-page-loader,
  .kodus-page-loader.is-active {
    opacity: 1;
    visibility: visible;
  }

  .kodus-page-loader__card {
    width: min(100%, 340px);
    padding: 1.4rem 1.35rem;
    border-radius: 24px;
    border: 1px solid rgba(148, 163, 184, 0.2);
    background: rgba(15, 23, 42, 0.84);
    box-shadow: 0 30px 70px rgba(0, 0, 0, 0.3);
    text-align: center;
    color: #e8eef5;
  }

  .kodus-page-loader__logo-wrap {
    position: relative;
    width: 94px;
    height: 94px;
    margin: 0 auto 1rem;
    display: grid;
    place-items: center;
  }

  .kodus-page-loader__ring,
  .kodus-page-loader__ring::before,
  .kodus-page-loader__ring::after {
    position: absolute;
    inset: 0;
    border-radius: 50%;
  }

  .kodus-page-loader__ring {
    border: 3px solid rgba(125, 196, 255, 0.18);
    border-top-color: #7dc4ff;
    animation: kodus-loader-spin 1.15s linear infinite;
  }

  .kodus-page-loader__ring::before,
  .kodus-page-loader__ring::after {
    content: "";
    inset: 10px;
    border: 2px solid transparent;
  }

  .kodus-page-loader__ring::before {
    border-right-color: rgba(32, 201, 151, 0.7);
    animation: kodus-loader-spin 1.8s linear infinite reverse;
  }

  .kodus-page-loader__ring::after {
    inset: 20px;
    border-left-color: rgba(255, 255, 255, 0.55);
    animation: kodus-loader-spin 1.35s linear infinite;
  }

  .kodus-page-loader__logo {
    width: 54px;
    height: 54px;
    object-fit: contain;
    filter: drop-shadow(0 10px 20px rgba(13, 110, 253, 0.18));
  }

  .kodus-page-loader__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    margin-bottom: 0.55rem;
    padding: 0.4rem 0.8rem;
    border-radius: 999px;
    background: rgba(125, 196, 255, 0.12);
    color: #9dd7ff;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .kodus-page-loader__title {
    margin: 0;
    font-size: 1.08rem;
    font-weight: 700;
    color: #f8fbff;
  }

  .kodus-page-loader__text {
    margin: 0.45rem 0 0;
    color: rgba(232, 238, 245, 0.78);
    line-height: 1.5;
    font-size: 0.92rem;
  }

  html[data-kodus-loader-theme="light"] .kodus-page-loader {
    background:
      radial-gradient(circle at top left, rgba(13, 110, 253, 0.16), transparent 34%),
      radial-gradient(circle at bottom right, rgba(32, 201, 151, 0.12), transparent 28%),
      rgba(245, 248, 252, 0.88);
  }

  html[data-kodus-loader-theme="light"] .kodus-page-loader__card {
    background: rgba(255, 255, 255, 0.88);
    border-color: rgba(13, 110, 253, 0.12);
    box-shadow: 0 28px 64px rgba(15, 23, 42, 0.12);
    color: #1f2d3d;
  }

  html[data-kodus-loader-theme="light"] .kodus-page-loader__eyebrow {
    background: rgba(13, 110, 253, 0.08);
    color: #0d6efd;
  }

  html[data-kodus-loader-theme="light"] .kodus-page-loader__title {
    color: #1f2d3d;
  }

  html[data-kodus-loader-theme="light"] .kodus-page-loader__text {
    color: rgba(31, 45, 61, 0.72);
  }

  @keyframes kodus-loader-spin {
    to {
      transform: rotate(360deg);
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .kodus-page-loader,
    .kodus-page-loader__ring,
    .kodus-page-loader__ring::before,
    .kodus-page-loader__ring::after {
      transition: none;
      animation: none;
    }
  }

  :root {
    --kodus-swal-text: #e8eef5;
    --kodus-swal-muted: #aebfd1;
    --kodus-swal-panel: #1a2432;
    --kodus-swal-panel-strong: linear-gradient(180deg, rgba(32, 44, 61, 0.98), rgba(24, 34, 48, 0.98));
    --kodus-swal-border: #314154;
    --kodus-swal-hero-start: #2b579a;
    --kodus-swal-hero-end: #101828;
    --kodus-swal-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
    --kodus-swal-badge-bg: #113d48;
    --kodus-swal-badge-text: #9de8f2;
    --kodus-swal-link: #7dc4ff;
    --kodus-swal-confirm-start: #2563eb;
    --kodus-swal-confirm-end: #0b57d0;
    --kodus-swal-confirm-shadow: 0 14px 28px rgba(37, 99, 235, 0.28);
    --kodus-swal-cancel-bg: rgba(255, 255, 255, 0.06);
    --kodus-swal-input-bg: rgba(9, 16, 28, 0.42);
    --kodus-swal-validation-bg: rgba(220, 53, 69, 0.14);
    --kodus-swal-validation-text: #ffd2da;
    --kodus-swal-toast-bg: rgba(12, 20, 33, 0.94);
  }

  body[data-theme="light"],
  html[data-kodus-loader-theme="light"] {
    --kodus-swal-text: #1f2d3d;
    --kodus-swal-muted: #5f7488;
    --kodus-swal-panel: #ffffff;
    --kodus-swal-panel-strong: linear-gradient(180deg, #f8fbff, #ffffff);
    --kodus-swal-border: #d7e4f4;
    --kodus-swal-hero-start: #eef6ff;
    --kodus-swal-hero-end: #ffffff;
    --kodus-swal-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
    --kodus-swal-badge-bg: #e2efff;
    --kodus-swal-badge-text: #0d6efd;
    --kodus-swal-link: #0d6efd;
    --kodus-swal-confirm-start: #0d6efd;
    --kodus-swal-confirm-end: #0b57d0;
    --kodus-swal-confirm-shadow: 0 14px 26px rgba(13, 110, 253, 0.2);
    --kodus-swal-cancel-bg: rgba(13, 110, 253, 0.06);
    --kodus-swal-input-bg: rgba(245, 249, 255, 0.96);
    --kodus-swal-validation-bg: rgba(220, 53, 69, 0.1);
    --kodus-swal-validation-text: #a61d2a;
    --kodus-swal-toast-bg: rgba(255, 255, 255, 0.97);
  }

  .swal2-container.kodus-swal-container {
    padding: 1rem;
    backdrop-filter: blur(6px);
  }

  .swal2-popup.kodus-swal-popup {
    width: min(32rem, 92vw);
    padding: 1.35rem 1.35rem 1.15rem;
    border-radius: 24px;
    border: 1px solid var(--kodus-swal-border);
    background: linear-gradient(180deg, var(--kodus-swal-panel), rgba(24, 34, 48, 0.98));
    color: var(--kodus-swal-text);
    box-shadow: var(--kodus-swal-shadow);
    text-align: left;
  }

  html[data-kodus-loader-theme="light"] .swal2-popup.kodus-swal-popup,
  body[data-theme="light"] .swal2-popup.kodus-swal-popup {
    background: linear-gradient(180deg, var(--kodus-swal-panel), #f8fbff);
  }

  .swal2-popup.kodus-swal-popup .swal2-title {
    color: var(--kodus-swal-text);
    font-size: 1.28rem;
    font-weight: 700;
    line-height: 1.3;
  }

  .swal2-popup.kodus-swal-popup .swal2-html-container,
  .swal2-popup.kodus-swal-popup .swal2-footer {
    color: var(--kodus-swal-muted);
  }

  .swal2-popup.kodus-swal-popup .swal2-html-container {
    margin: 0.85rem 0 0;
    font-size: 0.96rem;
    line-height: 1.6;
  }

  .swal2-popup.kodus-swal-popup .swal2-html-container a,
  .swal2-popup.kodus-swal-popup .swal2-footer a {
    color: var(--kodus-swal-link);
  }

  .swal2-popup.kodus-swal-popup .swal2-icon {
    margin: 0.15rem auto 0;
  }

  .swal2-popup.kodus-swal-popup .swal2-actions {
    gap: 0.75rem;
    justify-content: flex-end;
    margin: 1.15rem 0 0;
    padding: 0.2rem 0 0;
  }

  .swal2-popup.kodus-swal-popup .swal2-styled {
    margin: 0;
    min-width: 8.75rem;
    padding: 0.8rem 1.2rem;
    border: 1px solid transparent;
    border-radius: 14px;
    font-size: 0.95rem;
    font-weight: 700;
    line-height: 1.2;
    transition: transform 0.16s ease, box-shadow 0.16s ease, filter 0.16s ease, border-color 0.16s ease;
  }

  .swal2-popup.kodus-swal-popup .swal2-styled:focus {
    box-shadow: 0 0 0 0.2rem rgba(125, 196, 255, 0.2);
  }

  .swal2-popup.kodus-swal-popup .swal2-styled:hover {
    transform: translateY(-1px);
  }

  .swal2-popup.kodus-swal-popup .swal2-confirm {
    background: linear-gradient(135deg, var(--kodus-swal-confirm-start), var(--kodus-swal-confirm-end)) !important;
    color: #fff !important;
    box-shadow: var(--kodus-swal-confirm-shadow);
  }

  .swal2-popup.kodus-swal-popup .swal2-confirm:hover,
  .swal2-popup.kodus-swal-popup .swal2-confirm:focus {
    filter: brightness(1.03);
  }

  .swal2-popup.kodus-swal-popup .swal2-cancel {
    background: var(--kodus-swal-cancel-bg) !important;
    border-color: var(--kodus-swal-border) !important;
    color: var(--kodus-swal-text) !important;
    box-shadow: none !important;
  }

  .swal2-popup.kodus-swal-popup .swal2-deny {
    background: linear-gradient(135deg, #f59e0b, #d97706) !important;
    color: #fff !important;
    box-shadow: 0 12px 24px rgba(217, 119, 6, 0.24);
  }

  .swal2-popup.kodus-swal-popup .swal2-input,
  .swal2-popup.kodus-swal-popup .swal2-select,
  .swal2-popup.kodus-swal-popup .swal2-textarea,
  .swal2-popup.kodus-swal-popup .swal2-file {
    margin-top: 1rem;
    border: 1px solid var(--kodus-swal-border);
    border-radius: 14px;
    background: var(--kodus-swal-input-bg);
    color: var(--kodus-swal-text);
    box-shadow: none;
  }

  .swal2-popup.kodus-swal-popup .swal2-input:focus,
  .swal2-popup.kodus-swal-popup .swal2-select:focus,
  .swal2-popup.kodus-swal-popup .swal2-textarea:focus {
    border-color: var(--kodus-swal-confirm-start);
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.14);
  }

  .swal2-popup.kodus-swal-popup .swal2-validation-message {
    margin-top: 0.9rem;
    border-radius: 14px;
    background: var(--kodus-swal-validation-bg);
    color: var(--kodus-swal-validation-text);
  }

  .swal2-popup.kodus-swal-popup .swal2-footer {
    margin-top: 1rem;
    padding-top: 0.85rem;
    border-top: 1px solid var(--kodus-swal-border);
  }

  .swal2-popup.kodus-swal-popup .swal2-timer-progress-bar {
    height: 0.3rem;
    background: linear-gradient(90deg, var(--kodus-swal-confirm-start), #20c997);
  }

  .swal2-popup.kodus-swal-toast {
    width: min(25rem, 92vw);
    padding: 0.95rem 1rem;
    border-radius: 18px;
    border: 1px solid var(--kodus-swal-border);
    background: var(--kodus-swal-toast-bg);
    color: var(--kodus-swal-text);
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
  }

  .swal2-popup.kodus-swal-toast .swal2-title {
    color: var(--kodus-swal-text);
    font-size: 0.98rem;
    font-weight: 700;
    text-align: left;
  }

  .swal2-popup.kodus-swal-toast .swal2-html-container {
    margin: 0.35rem 0 0;
    color: var(--kodus-swal-muted);
    font-size: 0.88rem;
    text-align: left;
  }

  .swal2-popup.kodus-swal-hero-ready .swal2-title,
  .swal2-popup.kodus-swal-hero-ready .swal2-icon {
    display: none !important;
  }

  .swal2-popup.kodus-swal-hero-ready .swal2-html-container {
    margin-top: 0.8rem;
  }

  .kodus-swal-hero {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 4.8rem 1rem 1.05rem;
    margin-bottom: 0.9rem;
    border-radius: 18px;
    border: 1px solid var(--kodus-swal-border);
    background: linear-gradient(135deg, var(--kodus-swal-hero-start), var(--kodus-swal-hero-end));
  }

  .kodus-swal-hero::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0));
    pointer-events: none;
  }

  .kodus-swal-hero-copy,
  .kodus-swal-hero-badge {
    position: relative;
    z-index: 1;
  }

  .kodus-swal-hero-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    margin-bottom: 0.35rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--kodus-swal-muted);
  }

  .kodus-swal-hero-title {
    margin: 0;
    font-size: 1.18rem;
    font-weight: 700;
    line-height: 1.3;
    color: var(--kodus-swal-text);
  }

  .kodus-swal-hero-subtitle {
    margin: 0.3rem 0 0;
    color: var(--kodus-swal-muted);
    line-height: 1.45;
    font-size: 0.9rem;
  }

  .kodus-swal-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    min-height: 2.2rem;
    padding: 0.45rem 0.8rem;
    border-radius: 999px;
    border: 1px solid var(--kodus-swal-border);
    background: var(--kodus-swal-badge-bg);
    color: var(--kodus-swal-badge-text);
    font-size: 0.8rem;
    font-weight: 700;
    white-space: nowrap;
  }

  .swal2-popup.kodus-swal-has-top-actions {
    position: relative;
  }

  .swal2-popup .kodus-swal-top-actions {
    position: absolute;
    top: 1rem;
    right: 1rem;
    z-index: 3;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
  }

  .swal2-popup .kodus-swal-top-action-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.4rem;
    height: 2.4rem;
    padding: 0 0.8rem;
    border: 1px solid var(--kodus-swal-border);
    border-radius: 999px;
    background: var(--kodus-swal-panel-strong);
    color: var(--kodus-swal-text);
    box-shadow: none;
  }

  .swal2-popup .kodus-swal-top-action-button:hover,
  .swal2-popup .kodus-swal-top-action-button:focus {
    color: var(--kodus-swal-text);
    opacity: 1;
  }

  .swal2-popup .kodus-swal-top-action-button i {
    pointer-events: none;
  }

  .swal2-popup .swal2-close {
    color: var(--kodus-swal-text);
    opacity: 0.78;
    text-shadow: none;
    transition: opacity 0.15s ease-in-out, color 0.15s ease-in-out;
  }

  .swal2-popup .swal2-close:hover,
  .swal2-popup .swal2-close:focus {
    color: var(--kodus-swal-text);
    opacity: 1;
  }

  .swal2-popup.kodus-swal-has-top-actions .swal2-close {
    position: static;
    inset: auto;
    width: 2.4rem;
    height: 2.4rem;
    margin: 0;
    border-radius: 999px;
    font-size: 1.5rem;
  }

  @media (max-width: 576px) {
    .swal2-popup.kodus-swal-popup {
      padding: 1.1rem 1rem 1rem;
    }

    .swal2-popup.kodus-swal-popup .swal2-actions {
      flex-direction: column-reverse;
      align-items: stretch;
    }

    .swal2-popup.kodus-swal-popup .swal2-styled {
      width: 100%;
      min-width: 0;
    }

    .kodus-swal-hero {
      padding-right: 1.05rem;
      flex-direction: column;
    }
  }
</style>
<script>
  (function () {
    if (window.__kodusPageLoaderInitialized) {
      return;
    }

    window.__kodusPageLoaderInitialized = true;

    var loaderStart = Date.now();
    var minVisibleMs = 250;
    var fallbackHideMs = 12000;
    var loaderNode = null;
    var activeText = 'Loading your workspace...';
    var hidden = false;
    var themeObserver = null;
    var exportHideTimer = null;
    var modalHideTimer = null;
    var swalHookInstalled = false;
    var fallbackHideTimer = null;
    var swalHookRetryTimer = null;
    var holdCount = window.__kodusPageLoaderHold ? 1 : 0;

    document.documentElement.classList.add('kodus-page-loading');

    function detectTheme() {
      var body = document.body;
      if (!body) {
        return document.documentElement.getAttribute('data-kodus-loader-theme') || 'dark';
      }

      var dataTheme = (body.getAttribute('data-theme') || '').toLowerCase();
      if (dataTheme === 'light' || dataTheme === 'dark') {
        return dataTheme;
      }

      return body.classList.contains('dark-mode') ? 'dark' : 'light';
    }

    function syncTheme() {
      document.documentElement.setAttribute('data-kodus-loader-theme', detectTheme());
    }

    function buildLoaderMarkup() {
      return (
        '<div class="kodus-page-loader__card">' +
          '<div class="kodus-page-loader__logo-wrap">' +
            '<div class="kodus-page-loader__ring"></div>' +
            '<img class="kodus-page-loader__logo" src="<?php echo htmlspecialchars($kodusLoaderLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="KODUS logo">' +
          '</div>' +
          '<div class="kodus-page-loader__eyebrow"><i class="fas fa-bolt"></i><span>KODUS</span></div>' +
          '<h2 class="kodus-page-loader__title">Preparing your page</h2>' +
          '<p class="kodus-page-loader__text"></p>' +
        '</div>'
      );
    }

    function createLoader() {
      if (loaderNode || !document.body) {
        return loaderNode;
      }

      loaderNode = document.createElement('div');
      loaderNode.className = hidden ? 'kodus-page-loader' : 'kodus-page-loader is-active';
      loaderNode.setAttribute('aria-live', 'polite');
      loaderNode.setAttribute('aria-busy', hidden ? 'false' : 'true');
      loaderNode.innerHTML = buildLoaderMarkup();

      document.body.appendChild(loaderNode);
      syncTheme();
      setText(activeText);

      if (hidden) {
        document.documentElement.classList.remove('kodus-page-loading');
      }

      return loaderNode;
    }

    function scheduleFallbackHide(timeoutMs) {
      if (fallbackHideTimer) {
        window.clearTimeout(fallbackHideTimer);
      }

      fallbackHideTimer = window.setTimeout(function () {
        holdCount = 0;
        window.__kodusPageLoaderHold = false;
        hide();
        fallbackHideTimer = null;
      }, timeoutMs || fallbackHideMs);
    }

    function setText(text) {
      activeText = text || activeText;
      if (!loaderNode) {
        return;
      }

      var textNode = loaderNode.querySelector('.kodus-page-loader__text');
      if (textNode) {
        textNode.textContent = activeText;
      }
    }

    function show(text) {
      if (text) {
        setText(text);
      }

      loaderStart = Date.now();
      hidden = false;
      syncTheme();
      document.documentElement.classList.add('kodus-page-loading');
      createLoader();

      if (loaderNode) {
        loaderNode.classList.add('is-active');
        loaderNode.setAttribute('aria-busy', 'true');
      }

      scheduleFallbackHide();
    }

    function hide() {
      if (holdCount > 0) {
        return;
      }

      if (hidden) {
        return;
      }

      if (fallbackHideTimer) {
        window.clearTimeout(fallbackHideTimer);
        fallbackHideTimer = null;
      }

      hidden = true;
      var delay = Math.max(0, minVisibleMs - (Date.now() - loaderStart));

      window.setTimeout(function () {
        document.documentElement.classList.remove('kodus-page-loading');
        if (loaderNode) {
          loaderNode.classList.remove('is-active');
          loaderNode.setAttribute('aria-busy', 'false');
        }
      }, delay);
    }

    function showForExport(text, duration) {
      if (exportHideTimer) {
        window.clearTimeout(exportHideTimer);
      }

      show(text || 'Preparing your export...');

      exportHideTimer = window.setTimeout(function () {
        hide();
        exportHideTimer = null;
      }, duration || 2600);
    }

    function showForModal(text, duration) {
      if (modalHideTimer) {
        window.clearTimeout(modalHideTimer);
      }

      show(text || 'Opening dialog...');

      modalHideTimer = window.setTimeout(function () {
        hide();
        modalHideTimer = null;
      }, duration || 5000);
    }

    function hideModalLoader() {
      if (modalHideTimer) {
        window.clearTimeout(modalHideTimer);
        modalHideTimer = null;
      }
      hide();
    }

    function hold(text) {
      window.__kodusPageLoaderHold = true;
      holdCount += 1;
      show(text || activeText);
    }

    function release() {
      if (holdCount > 0) {
        holdCount -= 1;
      }

      window.__kodusPageLoaderHold = holdCount > 0;

      if (holdCount === 0) {
        hide();
      }
    }

    function createStandaloneLoader(options) {
      var standaloneNode = null;
      var standaloneText = (options && options.text) || 'Working on your request...';
      var standaloneHidden = true;
      var standaloneStart = 0;
      var standaloneMinVisibleMs = Math.max(0, Number(options && options.minVisibleMs) || minVisibleMs);

      function ensureStandaloneNode() {
        if (standaloneNode || !document.body) {
          return standaloneNode;
        }

        standaloneNode = document.createElement('div');
        standaloneNode.className = 'kodus-page-loader';
        standaloneNode.setAttribute('aria-live', 'polite');
        standaloneNode.setAttribute('aria-busy', 'false');
        standaloneNode.innerHTML = buildLoaderMarkup();
        document.body.appendChild(standaloneNode);
        syncTheme();
        setStandaloneText(standaloneText);
        return standaloneNode;
      }

      function setStandaloneText(text) {
        standaloneText = text || standaloneText;

        if (!standaloneNode) {
          return;
        }

        var textNode = standaloneNode.querySelector('.kodus-page-loader__text');
        if (textNode) {
          textNode.textContent = standaloneText;
        }
      }

      function showStandalone(text) {
        if (text) {
          setStandaloneText(text);
        }

        standaloneStart = Date.now();
        standaloneHidden = false;
        syncTheme();
        ensureStandaloneNode();

        if (!standaloneNode) {
          return;
        }

        standaloneNode.classList.add('is-active');
        standaloneNode.setAttribute('aria-busy', 'true');
      }

      function hideStandalone() {
        if (standaloneHidden) {
          return;
        }

        standaloneHidden = true;

        var delay = Math.max(0, standaloneMinVisibleMs - (Date.now() - standaloneStart));
        window.setTimeout(function () {
          if (!standaloneHidden || !standaloneNode) {
            return;
          }

          standaloneNode.classList.remove('is-active');
          standaloneNode.setAttribute('aria-busy', 'false');
        }, delay);
      }

      return {
        show: showStandalone,
        hide: hideStandalone,
        setText: setStandaloneText
      };
    }

    function isExportTrigger(element) {
      if (!element) {
        return false;
      }

      if (element.dataset.kodusExportTrigger === 'true') {
        return true;
      }

      var className = element.className || '';
      if (typeof className === 'string' && /(buttons-copy|buttons-csv|buttons-excel|buttons-pdf|buttons-print|buttons-json|buttons-html5)/.test(className)) {
        return true;
      }

      var exportClasses = [
        'export-btn',
        'btn-export',
        'export-excel',
        'export-csv',
        'export-pdf',
        'export-print'
      ];

      for (var i = 0; i < exportClasses.length; i += 1) {
        if (element.classList && element.classList.contains(exportClasses[i])) {
          return true;
        }
      }

      var text = ((element.getAttribute('aria-label') || '') + ' ' + (element.textContent || '')).toLowerCase();
      return /(^|\s)(export|download csv|download excel|download pdf|print)(\s|$)/.test(text);
    }

    function exportLoaderText(element) {
      var text = ((element.getAttribute('aria-label') || '') + ' ' + (element.textContent || '')).toLowerCase();

      if (text.indexOf('excel') !== -1) {
        return 'Preparing your Excel export...';
      }
      if (text.indexOf('csv') !== -1) {
        return 'Preparing your CSV export...';
      }
      if (text.indexOf('pdf') !== -1) {
        return 'Preparing your PDF export...';
      }
      if (text.indexOf('print') !== -1) {
        return 'Preparing your print view...';
      }
      if (text.indexOf('copy') !== -1) {
        return 'Preparing your export...';
      }

      return 'Preparing your export...';
    }

    function isModalTrigger(element) {
      if (!element) {
        return false;
      }

      if (element.closest && element.closest('.reply-menu-trigger, .reply-menu-dropdown, .reply-edit-trigger, .reply-delete-trigger')) {
        return false;
      }

      if (element.dataset.kodusModalTrigger === 'true') {
        return true;
      }

      var id = (element.id || '').toLowerCase();
      if (id === 'track-documents') {
        return true;
      }

      var targetAttr = (element.getAttribute('data-toggle') || element.getAttribute('data-bs-toggle') || '').toLowerCase();
      if (targetAttr === 'modal') {
        return true;
      }

      if (element.hasAttribute('data-target') || element.hasAttribute('data-bs-target')) {
        return true;
      }

      var ariaHasPopup = (element.getAttribute('aria-haspopup') || '').toLowerCase();
      if (ariaHasPopup === 'dialog') {
        return true;
      }

      var className = element.className || '';
      if (typeof className === 'string' && /(edit-btn|forward-btn|modal-trigger|open-modal|view-btn|details-btn|preview-btn|reply-btn)/.test(className)) {
        return true;
      }

      var text = ((element.getAttribute('title') || '') + ' ' + (element.getAttribute('aria-label') || '') + ' ' + (element.textContent || '')).toLowerCase();
      if (text.indexOf('reply actions') !== -1) {
        return false;
      }
      return /(open|view details|edit|forward|track document|track incoming|track outgoing|preview|reply)/.test(text);
    }

    function modalLoaderText(element) {
      var text = ((element.getAttribute('title') || '') + ' ' + (element.getAttribute('aria-label') || '') + ' ' + (element.textContent || '')).toLowerCase();

      if (text.indexOf('forward') !== -1) {
        return 'Opening the forwarding dialog...';
      }
      if (text.indexOf('edit') !== -1) {
        return 'Opening the editor...';
      }
      if (text.indexOf('track') !== -1) {
        return 'Preparing the tracking form...';
      }
      if (text.indexOf('view') !== -1 || text.indexOf('details') !== -1 || text.indexOf('preview') !== -1) {
        return 'Loading details...';
      }
      if (text.indexOf('reply') !== -1) {
        return 'Opening the reply dialog...';
      }

      return 'Opening dialog...';
    }

    function shouldShowModalLoader(element) {
      if (!element) {
        return false;
      }

      if (element.dataset.noLoader === 'true') {
        return false;
      }

      return element.dataset.modalLoader === 'true' || element.dataset.kodusModalLoader === 'true';
    }

    function shouldHandleLink(link, event) {
      if (!link || event.defaultPrevented) {
        return false;
      }

      if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return false;
      }

      var href = link.getAttribute('href') || '';
      if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0 || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) {
        return false;
      }

      if (link.hasAttribute('download') || link.getAttribute('target') === '_blank' || link.dataset.noLoader === 'true') {
        return false;
      }

      try {
        var targetUrl = new URL(link.href, window.location.href);
        return targetUrl.origin === window.location.origin && targetUrl.href !== window.location.href;
      } catch (error) {
        return false;
      }
    }

    function bindNavigationHooks() {
      document.addEventListener('click', function (event) {
        var exportTrigger = event.target.closest('button, a, .dt-button');
        if (isExportTrigger(exportTrigger)) {
          showForExport(exportLoaderText(exportTrigger), Number(exportTrigger.dataset.loaderDuration || 2600));
          return;
        }

        var modalTrigger = event.target.closest('button, a, [role="button"]');
        if (isModalTrigger(modalTrigger)) {
          if (shouldShowModalLoader(modalTrigger)) {
            showForModal(modalLoaderText(modalTrigger), Number(modalTrigger.dataset.loaderDuration || 5000));
          }
          return;
        }

        var link = event.target.closest('a[href]');
        if (!shouldHandleLink(link, event)) {
          return;
        }

        show(link.dataset.loaderText || 'Opening the next page...');
      }, true);

      document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || form.dataset.noLoader === 'true' || form.getAttribute('target') === '_blank') {
          return;
        }

        show(form.dataset.loaderText || 'Loading the next page...');
      }, true);
    }

    window.KodusPageLoader = {
      show: show,
      hide: hide,
      hold: hold,
      release: release,
      setText: setText,
      syncTheme: syncTheme,
      showForExport: showForExport,
      showForModal: showForModal,
      hideModalLoader: hideModalLoader,
      createStandaloneLoader: createStandaloneLoader
    };

    function bindThemeObserver() {
      if (!document.body || themeObserver) {
        return;
      }

      themeObserver = new MutationObserver(syncTheme);
      themeObserver.observe(document.body, {
        attributes: true,
        attributeFilter: ['class', 'data-theme']
      });
      syncTheme();
    }

    function hookModalLibraries() {
      if (!document.__kodusModalLoaderBound) {
        document.addEventListener('shown.bs.modal', hideModalLoader);
        document.addEventListener('shown.bs.tab', hideModalLoader);
        document.__kodusModalLoaderBound = true;
      }

      if (!swalHookInstalled && window.Swal && typeof window.Swal.fire === 'function') {
        var originalFire = window.Swal.fire.bind(window.Swal);
        function normalizeSwalArguments(argsLike) {
          var args = Array.prototype.slice.call(argsLike);

          if (args.length === 0) {
            return [{}];
          }

          if (typeof args[0] === 'object' && args[0] !== null && !Array.isArray(args[0])) {
            return [Object.assign({}, args[0])];
          }

          return [{
            title: args[0],
            html: args.length > 1 ? args[1] : undefined,
            icon: args.length > 2 ? args[2] : undefined
          }];
        }

        function isEditActionConfig(config) {
          return typeof config.confirmButtonText === 'string' && config.confirmButtonText.indexOf('fa-pen') !== -1;
        }

        function isDismissText(value) {
          var text = String(value || '').toLowerCase().trim();
          return text === 'cancel' || text === 'close' || text === 'dismiss';
        }

        function isIconOnlyCloseCancel(config) {
          return typeof config.cancelButtonText === 'string' && config.cancelButtonText.indexOf('fa-times') !== -1;
        }

        function syncSwalActionsVisibility() {
          var actions = window.Swal.getActions && window.Swal.getActions();
          if (!actions) {
            return;
          }

          var visibleButtons = Array.prototype.filter.call(actions.children, function (child) {
            return child instanceof HTMLElement && child.style.display !== 'none';
          });

          actions.style.display = visibleButtons.length > 0 ? '' : 'none';
        }

        function escapeHtml(value) {
          return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
        }

        function getSwalIconMeta(icon) {
          switch (String(icon || '').toLowerCase()) {
            case 'success':
              return { label: 'Success', iconClass: 'fas fa-check-circle' };
            case 'error':
              return { label: 'Alert', iconClass: 'fas fa-exclamation-circle' };
            case 'warning':
              return { label: 'Attention', iconClass: 'fas fa-exclamation-triangle' };
            case 'info':
              return { label: 'Information', iconClass: 'fas fa-info-circle' };
            case 'question':
              return { label: 'Question', iconClass: 'fas fa-question-circle' };
            default:
              return { label: 'Dialog', iconClass: 'fas fa-window-maximize' };
          }
        }

        function getSwalSubtitle(icon) {
          switch (String(icon || '').toLowerCase()) {
            case 'success':
              return 'Everything looks good. You can continue with confidence.';
            case 'error':
              return 'Something needs attention before you continue.';
            case 'warning':
              return 'Please review the details carefully before proceeding.';
            case 'info':
              return 'Here is the information you need before you continue.';
            case 'question':
              return 'Choose the option that best matches what you want to do.';
            default:
              return 'Review the details below and continue when you are ready.';
          }
        }

        function resolveSwalPageLabel() {
          var rawTitle = document.title || 'KODUS';
          var parts = rawTitle.split('|').map(function (part) { return part.trim(); }).filter(Boolean);
          return parts.length > 1 ? parts[1] : parts[0];
        }

        function shouldDecorateSwalHeader(config, popup) {
          if (!popup || config.toast) {
            return false;
          }

          if (config.kodusHeroHeader === false) {
            return false;
          }

          if (popup.classList.contains('kodus-swal-hero-ready') || popup.querySelector('.kodus-swal-hero')) {
            return false;
          }

          if (
            popup.classList.contains('verify-swal-popup') ||
            popup.querySelector('.kodus-detail-modal, .activity-edit-shell, .kodus-edit-shell, .verify-swal-popup')
          ) {
            return false;
          }

          var titleEl = window.Swal.getTitle && window.Swal.getTitle();
          var titleText = titleEl ? String(titleEl.textContent || '').trim() : String(config.title || '').trim();
          return titleText !== '';
        }

        function enhanceSwalHeader(config, popup) {
          if (!shouldDecorateSwalHeader(config, popup)) {
            return;
          }

          var titleEl = window.Swal.getTitle && window.Swal.getTitle();
          var iconEl = popup.querySelector('.swal2-icon');
          var titleText = titleEl ? String(titleEl.textContent || '').trim() : String(config.title || '').trim();
          var pageLabel = resolveSwalPageLabel();
          var iconMeta = getSwalIconMeta(config.icon);
          var pageText = pageLabel && pageLabel !== titleText ? pageLabel : 'KODUS Workspace';
          var hero = document.createElement('div');

          hero.className = 'kodus-swal-hero';
          hero.innerHTML =
            '<div class="kodus-swal-hero-copy">' +
              '<span class="kodus-swal-hero-eyebrow"><i class="' + escapeHtml(iconMeta.iconClass) + '"></i>' + escapeHtml(pageText) + '</span>' +
              '<h3 class="kodus-swal-hero-title">' + escapeHtml(titleText) + '</h3>' +
              '<p class="kodus-swal-hero-subtitle">' + escapeHtml(getSwalSubtitle(config.icon)) + '</p>' +
            '</div>' +
            '<div class="kodus-swal-hero-badge"><i class="' + escapeHtml(iconMeta.iconClass) + '"></i>' + escapeHtml(iconMeta.label) + '</div>';

          popup.insertBefore(hero, popup.firstChild);
          popup.classList.add('kodus-swal-hero-ready');

          if (titleEl) {
            titleEl.setAttribute('aria-hidden', 'true');
          }

          if (iconEl) {
            iconEl.setAttribute('aria-hidden', 'true');
          }
        }

        function enhanceSwalChrome(config, popup) {
          if (!popup || config.toast) {
            return;
          }

          var closeButton = window.Swal.getCloseButton && window.Swal.getCloseButton();
          var confirmButton = window.Swal.getConfirmButton && window.Swal.getConfirmButton();
          var cancelButton = window.Swal.getCancelButton && window.Swal.getCancelButton();
          var shouldMoveConfirm = isEditActionConfig(config) && !!confirmButton;
          var shouldHideDuplicateCancel = !!cancelButton && isIconOnlyCloseCancel(config);
          var shouldMoveCancel = !!cancelButton && !shouldHideDuplicateCancel && isDismissText(config.cancelButtonText);
          var shouldUseCloseButton = !!closeButton && !shouldMoveCancel;
          var shouldDecorateActions = shouldUseCloseButton || shouldMoveConfirm || shouldMoveCancel;

          if (!shouldDecorateActions) {
            return;
          }

          popup.classList.add('kodus-swal-has-top-actions');

          var topActions = popup.querySelector('.kodus-swal-top-actions');
          if (!topActions) {
            topActions = document.createElement('div');
            topActions.className = 'kodus-swal-top-actions';
            popup.insertBefore(topActions, popup.firstChild);
          }

          if (closeButton) {
            closeButton.style.display = shouldUseCloseButton ? '' : 'none';
          }

          if (cancelButton) {
            cancelButton.style.display = shouldHideDuplicateCancel ? 'none' : '';
          }

          if (shouldMoveConfirm) {
            confirmButton.classList.add('kodus-swal-top-action-button');
            topActions.appendChild(confirmButton);
          }

          if (shouldUseCloseButton) {
            closeButton.classList.add('kodus-swal-top-action-button');
            topActions.appendChild(closeButton);
          }

          if (shouldMoveCancel) {
            cancelButton.classList.add('kodus-swal-top-action-button');
            cancelButton.innerHTML = '<i class="fas fa-times"></i>';
            cancelButton.setAttribute('aria-label', 'Cancel');
            cancelButton.setAttribute('title', 'Cancel');
            topActions.appendChild(cancelButton);
          }

          syncSwalActionsVisibility();
        }

        window.Swal.fire = function () {
          var normalizedArgs = normalizeSwalArguments(arguments);
          var config = normalizedArgs[0] || {};
          var kodusHeroHeader = config.kodusHeroHeader;
          var kodusKeepPageLoader = config.kodusKeepPageLoader;
          var originalDidOpen = config.didOpen;

          delete config.kodusHeroHeader;
          delete config.kodusKeepPageLoader;

          if (kodusKeepPageLoader !== true) {
            hideModalLoader();
          }

          if (typeof config.showCloseButton === 'undefined' && !config.toast) {
            config.showCloseButton = true;
          }

          if (typeof config.closeButtonAriaLabel === 'undefined') {
            config.closeButtonAriaLabel = 'Close';
          }

          config.didOpen = function (popup) {
            popup.classList.add(config.toast ? 'kodus-swal-toast' : 'kodus-swal-popup');
            if (config.icon) {
              popup.classList.add('kodus-swal-icon-' + String(config.icon).toLowerCase());
            }

            var container = popup && popup.closest ? popup.closest('.swal2-container') : null;
            if (container) {
              container.classList.add('kodus-swal-container');
            }

            if (kodusHeroHeader === false) {
              config.kodusHeroHeader = false;
            }

            enhanceSwalHeader(config, popup);
            enhanceSwalChrome(config, popup);

            if (typeof originalDidOpen === 'function') {
              originalDidOpen(popup);
            }

            syncSwalActionsVisibility();
          };

          return originalFire.apply(window.Swal, normalizedArgs);
        };
        swalHookInstalled = true;

        if (swalHookRetryTimer) {
          window.clearInterval(swalHookRetryTimer);
          swalHookRetryTimer = null;
        }
      }
    }

    function ensureSwalHook() {
      hookModalLibraries();

      if (!swalHookInstalled && !swalHookRetryTimer) {
        swalHookRetryTimer = window.setInterval(function () {
          hookModalLibraries();

          if (swalHookInstalled && swalHookRetryTimer) {
            window.clearInterval(swalHookRetryTimer);
            swalHookRetryTimer = null;
          }
        }, 100);
      }
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () {
        createLoader();
        bindThemeObserver();
        ensureSwalHook();
      }, { once: true });
    } else {
      createLoader();
      bindThemeObserver();
      ensureSwalHook();
    }

    bindNavigationHooks();
    ensureSwalHook();
    window.addEventListener('load', hide, { once: true });
    window.addEventListener('pageshow', hide);
    window.addEventListener('focus', function () {
      if (exportHideTimer) {
        window.clearTimeout(exportHideTimer);
        exportHideTimer = null;
      }
      hide();
    });
    window.setInterval(hookModalLibraries, 1200);
    scheduleFallbackHide();
  })();
</script>
