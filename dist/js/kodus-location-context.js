(function () {
  const config = window.KODUS_LOCATION_CONTEXT;
  if (!config || !config.endpoint || typeof fetch !== 'function') {
    return;
  }

  const sessionState = config.session || {};
  const csrfToken = String(config.csrfToken || window.KODUS_CSRF_TOKEN || '');
  const maxAgeSeconds = Number(config.maxAgeSeconds || 1800);
  const nowSeconds = Math.floor(Date.now() / 1000);
  const capturedAtIso = String(sessionState.captured_at_iso || '');
  const capturedAtMillis = capturedAtIso ? Date.parse(capturedAtIso) : NaN;
  const hasFreshCoordinates =
    Number.isFinite(capturedAtMillis) &&
    capturedAtMillis > 0 &&
    nowSeconds - Math.floor(capturedAtMillis / 1000) < maxAgeSeconds &&
    typeof sessionState.latitude === 'number' &&
    typeof sessionState.longitude === 'number';

  const timezone =
    typeof Intl !== 'undefined' &&
    Intl.DateTimeFormat &&
    Intl.DateTimeFormat().resolvedOptions
      ? Intl.DateTimeFormat().resolvedOptions().timeZone || ''
      : '';

  const timezoneChanged = Boolean(timezone) && timezone !== (sessionState.timezone || '');
  if (!timezoneChanged && hasFreshCoordinates) {
    return;
  }

  let submitted = false;

  function submitContext(payload) {
    if (submitted) {
      return;
    }
    submitted = true;

    fetch(config.endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': csrfToken
      },
      body: JSON.stringify(payload)
    })
      .then(function (response) {
        return response.json().catch(function () {
          return null;
        });
      })
      .then(function (result) {
        if (!result || !result.success || !result.changed || !config.reloadOnChange) {
          return;
        }

        window.location.reload();
      })
      .catch(function () {
      });
  }

  const basePayload = {};
  if (timezone) {
    basePayload.timezone = timezone;
  }

  if (hasFreshCoordinates || !navigator.geolocation) {
    submitContext(basePayload);
    return;
  }

  navigator.geolocation.getCurrentPosition(
    function (position) {
      submitContext({
        timezone: timezone,
        latitude: position.coords.latitude,
        longitude: position.coords.longitude
      });
    },
    function () {
      submitContext(basePayload);
    },
    {
      enableHighAccuracy: false,
      timeout: 8000,
      maximumAge: maxAgeSeconds * 1000
    }
  );
}());
