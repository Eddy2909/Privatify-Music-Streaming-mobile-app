(() => {
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('service-worker.js', {scope: './'}).then(
        (reg) => console.info('[Privatefy PWA] Service Worker aktiv:', reg.scope),
        (err) => console.warn('[Privatefy PWA] Service Worker Registrierung fehlgeschlagen:', err)
      );
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
    console.info('[Privatefy PWA] Nativer Install-Dialog verfügbar.');
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

    // Fallback only when the browser offers no native prompt (e.g. already
    // installed, or install suppressed). A website cannot force an install.
    console.info('[Privatefy PWA] Kein nativer Prompt - Anleitung statt Dialog.');
    const dialog = document.getElementById('pwaInstallDialog');
    if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
  });
})();
