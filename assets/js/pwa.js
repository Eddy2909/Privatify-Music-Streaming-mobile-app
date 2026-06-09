(() => {
  if (!('serviceWorker' in navigator)) return;

  window.addEventListener('load', () => {
    navigator.serviceWorker.register('service-worker.js', {scope: './'}).catch(() => {
      // PWA support is optional; the app must keep working if registration is blocked.
    });
  });
})();
