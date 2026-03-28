(function(window) {
  'use strict';

  function wait(ms) {
    return new Promise(function(resolve) {
      window.setTimeout(resolve, ms);
    });
  }

  function normalizeChannels(channels) {
    if (!Array.isArray(channels)) {
      return [];
    }

    return channels
      .map(function(channel) {
        return String(channel || '').trim();
      })
      .filter(Boolean);
  }

  function watch(options) {
    var endpoint = String((options && options.endpoint) || window.KODUS_LIVE_REFRESH_URL || '').trim();
    var channels = normalizeChannels(options && options.channels);
    var timeoutSeconds = Math.max(5, Math.min(25, Math.floor(Number((options && options.timeoutMs) || 25000) / 1000) || 20));
    var retryDelayMs = Math.max(1000, Number((options && options.retryDelayMs) || 3000));
    var token = '';
    var stopped = false;
    var activeController = null;

    if (!endpoint || channels.length === 0) {
      return {
        stop: function() {},
        isActive: function() { return false; }
      };
    }

    async function loop(delayMs) {
      if (stopped) {
        return;
      }

      if (delayMs > 0) {
        await wait(delayMs);
      }

      if (stopped) {
        return;
      }

      activeController = window.AbortController ? new AbortController() : null;

      var params = new URLSearchParams();
      params.set('channels', channels.join(','));
      params.set('timeout', String(timeoutSeconds));
      if (token) {
        params.set('token', token);
      }

      try {
        var response = await window.fetch(endpoint + '?' + params.toString(), {
          method: 'GET',
          credentials: 'same-origin',
          cache: 'no-store',
          headers: { 'Accept': 'application/json' },
          signal: activeController ? activeController.signal : undefined
        });

        if (!response.ok) {
          throw new Error('Live refresh request failed with status ' + response.status);
        }

        var payload = await response.json();
        if (stopped) {
          return;
        }

        var hadToken = token !== '';
        if (typeof payload.token === 'string' && payload.token) {
          token = payload.token;
        }

        if (hadToken && payload.changed && typeof options.onChange === 'function') {
          options.onChange(payload);
        } else if (!hadToken && typeof options.onReady === 'function') {
          options.onReady(payload);
        }

        loop(0);
      } catch (error) {
        if (stopped || (error && error.name === 'AbortError')) {
          return;
        }

        if (typeof options.onError === 'function') {
          options.onError(error);
        }

        loop(retryDelayMs);
      }
    }

    loop(0);

    return {
      stop: function() {
        stopped = true;
        if (activeController) {
          activeController.abort();
        }
      },
      isActive: function() {
        return !stopped;
      }
    };
  }

  function watchDataTable(options) {
    return watch({
      endpoint: options.endpoint,
      channels: options.channels,
      timeoutMs: options.timeoutMs,
      retryDelayMs: options.retryDelayMs,
      onChange: function(payload) {
        if (options.beforeReload) {
          options.beforeReload(payload);
        }

        if (options.table && options.table.ajax && typeof options.table.ajax.reload === 'function') {
          options.table.ajax.reload(null, false);
        }

        if (options.onChange) {
          options.onChange(payload);
        }
      },
      onReady: options.onReady,
      onError: options.onError
    });
  }

  window.KODUSLiveRefresh = {
    watch: watch,
    watchDataTable: watchDataTable
  };
})(window);
