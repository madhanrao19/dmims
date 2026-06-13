if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker
      .register('/service-worker.js')
      .then((reg) => {
        // Registration successful
        console.log('ServiceWorker registered:', reg.scope);
      })
      .catch((err) => {
        console.warn('ServiceWorker registration failed:', err);
      });
  });
}
