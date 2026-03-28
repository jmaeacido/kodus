<?php
$kodusLoaderBaseUrl = $base_url ?? '';
$kodusLoaderLogo = $kodusLoaderBaseUrl . 'kodus/dist/img/kodus.png';
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

    function createLoader() {
      if (loaderNode || !document.body) {
        return loaderNode;
      }

      loaderNode = document.createElement('div');
      loaderNode.className = 'kodus-page-loader is-active';
      loaderNode.setAttribute('aria-live', 'polite');
      loaderNode.setAttribute('aria-busy', 'true');
      loaderNode.innerHTML =
        '<div class="kodus-page-loader__card">' +
          '<div class="kodus-page-loader__logo-wrap">' +
            '<div class="kodus-page-loader__ring"></div>' +
            '<img class="kodus-page-loader__logo" src="<?php echo htmlspecialchars($kodusLoaderLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="KODUS logo">' +
          '</div>' +
          '<div class="kodus-page-loader__eyebrow"><i class="fas fa-bolt"></i><span>KODUS</span></div>' +
          '<h2 class="kodus-page-loader__title">Preparing your page</h2>' +
          '<p class="kodus-page-loader__text"></p>' +
        '</div>';

      document.body.appendChild(loaderNode);
      syncTheme();
      setText(activeText);
      return loaderNode;
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

      hidden = false;
      syncTheme();
      document.documentElement.classList.add('kodus-page-loading');
      createLoader();

      if (loaderNode) {
        loaderNode.classList.add('is-active');
      }
    }

    function hide() {
      if (hidden) {
        return;
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
          showForModal(modalLoaderText(modalTrigger), Number(modalTrigger.dataset.loaderDuration || 5000));
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
      setText: setText,
      syncTheme: syncTheme,
      showForExport: showForExport,
      showForModal: showForModal,
      hideModalLoader: hideModalLoader
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
        window.Swal.fire = function () {
          hideModalLoader();
          return originalFire.apply(window.Swal, arguments);
        };
        swalHookInstalled = true;
      }
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () {
        createLoader();
        bindThemeObserver();
        hookModalLibraries();
      }, { once: true });
    } else {
      createLoader();
      bindThemeObserver();
      hookModalLibraries();
    }

    bindNavigationHooks();
    window.addEventListener('load', hide, { once: true });
    window.addEventListener('focus', function () {
      if (exportHideTimer) {
        window.clearTimeout(exportHideTimer);
        exportHideTimer = null;
      }
      hide();
    });
    window.setInterval(hookModalLibraries, 1200);
    window.setTimeout(hide, fallbackHideMs);
  })();
</script>
