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

  function normalizeEvents(events) {
    if (Array.isArray(events)) {
      return events.map(function(eventName) {
        return String(eventName || '').trim();
      }).filter(Boolean);
    }

    if (typeof events === 'string' && events.trim() !== '') {
      return [events.trim()];
    }

    return [];
  }

  function throttle(fn, delayMs) {
    var timerId = null;
    var pendingArgs = null;

    return function throttled() {
      pendingArgs = arguments;
      if (timerId !== null) {
        return;
      }

      timerId = window.setTimeout(function() {
        timerId = null;
        var args = pendingArgs;
        pendingArgs = null;
        fn.apply(null, args);
      }, delayMs);
    };
  }

  var socketBridge = (function() {
    var config = window.KODUS_SOCKET_CONFIG || {};
    var enabled = !!config.enabled;
    var socket = null;
    var watchers = [];
    var initPromise = null;
    var boundEvents = {};
    var joinedChannels = {};

    function loadScript(src) {
      return new Promise(function(resolve, reject) {
        if (!src) {
          reject(new Error('Missing socket client script URL.'));
          return;
        }

        var existing = document.querySelector('script[data-kodus-socket-client="1"]');
        if (existing) {
          existing.addEventListener('load', function() { resolve(); }, { once: true });
          existing.addEventListener('error', function() { reject(new Error('Failed to load socket client script.')); }, { once: true });
          if (window.io) {
            resolve();
          }
          return;
        }

        var script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.dataset.kodusSocketClient = '1';
        script.onload = function() { resolve(); };
        script.onerror = function() { reject(new Error('Failed to load socket client script.')); };
        document.head.appendChild(script);
      });
    }

    function buildSocketOptions() {
      var token = String(config.accessToken || '').trim();
      return {
        transports: ['websocket', 'polling'],
        auth: token ? { token: 'Bearer ' + token } : undefined,
        query: token ? { token: token, bearer_token: token } : undefined
      };
    }

    function dispatch(payload) {
      window.dispatchEvent(new CustomEvent('kodus:socket-message', {
        detail: payload
      }));

      watchers.slice().forEach(function(watcher) {
        var channelMatches = !watcher.channel || !payload.channel || watcher.channel === payload.channel;
        var eventMatches = watcher.events.length === 0 || watcher.events.indexOf(payload.event) !== -1;

        if (channelMatches && eventMatches && typeof watcher.onMessage === 'function') {
          watcher.onMessage(payload);
        }
      });
    }

    function bindNamedEvent(eventName) {
      if (!socket || !eventName || boundEvents[eventName]) {
        return;
      }

      boundEvents[eventName] = true;
      socket.on(eventName, function(data) {
        var payload = data && typeof data === 'object' ? data : {};
        dispatch({
          transport: 'socket',
          channel: String(payload.channel || ''),
          event: eventName,
          data: payload && payload.data !== undefined ? payload.data : payload
        });
      });
    }

    function bindGenericEvents() {
      if (!socket) {
        return;
      }

      ['broadcast', 'socket:broadcast', 'kodus.broadcast'].forEach(function(eventName) {
        if (boundEvents[eventName]) {
          return;
        }

        boundEvents[eventName] = true;
        socket.on(eventName, function(payload) {
          if (!payload || typeof payload !== 'object') {
            return;
          }

          dispatch({
            transport: 'socket',
            channel: String(payload.channel || ''),
            event: String(payload.event || eventName),
            data: payload.data !== undefined ? payload.data : payload
          });
        });
      });

      if (typeof socket.onAny === 'function' && !boundEvents.__onAny) {
        boundEvents.__onAny = true;
        socket.onAny(function(eventName, payload) {
          if (!payload || typeof payload !== 'object') {
            return;
          }

          if (payload.event || payload.channel) {
            dispatch({
              transport: 'socket',
              channel: String(payload.channel || ''),
              event: String(payload.event || eventName || ''),
              data: payload.data !== undefined ? payload.data : payload
            });
          }
        });
      }
    }

    function joinChannel(channel) {
      var joinEvent = String(config.joinEvent || '').trim();
      if (!socket || !channel || !joinEvent || joinedChannels[channel]) {
        return;
      }

      joinedChannels[channel] = true;
      try {
        socket.emit(joinEvent, { channel: channel });
        socket.emit(joinEvent, channel);
      } catch (error) {
        joinedChannels[channel] = false;
      }
    }

    function ensureConnected() {
      if (!enabled || !config.serverUrl || !config.accessToken) {
        return Promise.resolve(null);
      }

      if (socket) {
        return Promise.resolve(socket);
      }

      if (initPromise) {
        return initPromise;
      }

      initPromise = Promise.resolve()
        .then(function() {
          if (window.io) {
            return null;
          }

          return loadScript(config.clientScriptUrl);
        })
        .then(function() {
          if (!window.io) {
            throw new Error('Socket.io client is unavailable.');
          }

          socket = window.io(config.serverUrl, buildSocketOptions());
          boundEvents = {};
          joinedChannels = {};

          socket.on('connect', function() {
            watchers.forEach(function(watcher) {
              if (watcher.channel) {
                joinChannel(watcher.channel);
              }
              watcher.events.forEach(bindNamedEvent);
            });
            bindGenericEvents();
          });

          bindGenericEvents();
          watchers.forEach(function(watcher) {
            watcher.events.forEach(bindNamedEvent);
          });

          return socket;
        })
        .catch(function(error) {
          console.warn('KODUS socket bridge unavailable.', error);
          initPromise = null;
          return null;
        });

      return initPromise;
    }

    function watch(options) {
      var watcher = {
        channel: String((options && options.channel) || '').trim(),
        events: normalizeEvents(options && options.events),
        onMessage: options && options.onMessage
      };

      watchers.push(watcher);

      ensureConnected().then(function(activeSocket) {
        if (!activeSocket) {
          return;
        }

        if (watcher.channel) {
          joinChannel(watcher.channel);
        }

        watcher.events.forEach(bindNamedEvent);
      });

      return {
        stop: function() {
          watchers = watchers.filter(function(currentWatcher) {
            return currentWatcher !== watcher;
          });
        }
      };
    }

    return {
      watch: watch,
      isEnabled: function() {
        return enabled && !!config.serverUrl && !!config.accessToken;
      }
    };
  }());

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

  function watchSocket(options) {
    return socketBridge.watch(options || {});
  }

  function watchDataTable(options) {
    var throttledReload = throttle(function(payload) {
      if (options.beforeReload) {
        options.beforeReload(payload);
      }

      if (options.table && options.table.ajax && typeof options.table.ajax.reload === 'function') {
        options.table.ajax.reload(null, false);
      }

      if (options.onChange) {
        options.onChange(payload);
      }
    }, 300);

    var pollWatcher = watch({
      endpoint: options.endpoint,
      channels: options.channels,
      timeoutMs: options.timeoutMs,
      retryDelayMs: options.retryDelayMs,
      onChange: throttledReload,
      onReady: options.onReady,
      onError: options.onError
    });

    var socketWatcher = null;
    if (options.socket && options.socket.channel) {
      socketWatcher = watchSocket({
        channel: options.socket.channel,
        events: options.socket.events,
        onMessage: throttledReload
      });
    }

    return {
      stop: function() {
        pollWatcher.stop();
        if (socketWatcher) {
          socketWatcher.stop();
        }
      },
      isActive: function() {
        return pollWatcher.isActive();
      }
    };
  }

  window.KODUSLiveRefresh = {
    watch: watch,
    watchSocket: watchSocket,
    watchDataTable: watchDataTable,
    isSocketEnabled: socketBridge.isEnabled
  };
})(window);
