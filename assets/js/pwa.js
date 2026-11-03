(() => {
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('service-worker.js', {scope: './'}).catch(() => {
        // PWA support is optional; the app must keep working if registration is blocked.
      });
    });
  }

  let deferredPrompt = null;
  const buttons = () => document.querySelectorAll('[data-pwa-install]');
  const setVisible = (visible) => buttons().forEach((el) => { el.hidden = !visible; });
  const isStandalone = () =>
    window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

  // Always offer the control, even when the browser does not deliver a native
  // install prompt (then the click opens the manual instructions instead).
  if (!isStandalone()) setVisible(true);

  // Chrome/Edge (Android + Desktop) fire this once the app is installable.
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPrompt = event;
    setVisible(true);
  });

  window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    setVisible(false);
  });

  document.addEventListener('click', async (event) => {
    const trigger = event.target.closest('[data-pwa-install]');
    if (!trigger) return;
    event.preventDefault();

    if (deferredPrompt) {
      deferredPrompt.prompt();
      const choice = await deferredPrompt.userChoice.catch(() => null);
      if (choice && choice.outcome === 'accepted') setVisible(false);
      deferredPrompt = null;
      return;
    }

    // Fallback: manual instructions (iOS Safari, or a browser without the prompt).
    const dialog = document.getElementById('pwaInstallDialog');
    if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
  });
})();
