(function () {
  const originalLog = console.log;
  const originalError = console.error;
  const originalWarn = console.warn;

  function send(level, args) {
    const msg = args.map(arg => {
      try {
        return typeof arg === 'object' ? JSON.stringify(arg) : String(arg);
      } catch {
        return '[object]';
      }
    }).join(' ');

    fetch(CCL_Ajax.ajax_url + '?action=ccl_log', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        level,
        message: msg,
        timestamp: new Date().toISOString(),
        url: window.location.href,
        referrer: document.referrer,
        userAgent: navigator.userAgent,
        userId: CCL_Ajax.user_id || null,
      }),
    }).catch(() => {});// On ignore les erreurs réseau
  }

  // Redéfinition console
  console.log = function (...args) {
    send('log', args);
    originalLog.apply(console, args);
  };

  console.warn = function (...args) {
    send('warn', args);
    originalWarn.apply(console, args);
  };
  
  console.error = function (...args) {
    send('error', args);
    originalError.apply(console, args);
  };

  // Erreurs JavaScript globales
  window.addEventListener('error', function (event) {
    send('error', [`[GlobalError] ${event.message} at ${event.filename}:${event.lineno}:${event.colno}`]);
  });

  // Promesses non attrapées
  window.addEventListener('unhandledrejection', function (event) {
    send('error', [`[UnhandledRejection] ${event.reason}`]);
  });

})();
/*
const originalFetch = window.fetch;

window.fetch = async function (...args) {
  try {
    const response = await originalFetch.apply(this, args);

    if (!response.ok) {
      send('error', [
        `[FetchFail] ${response.status} ${response.statusText} on ${args[0]}`
      ]);
    }

    return response;
  } catch (err) {
    send('error', [`[FetchError] ${err}`]);
    throw err;
  }
};
*/