(() => {
  'use strict';
  const content = window.AX_CONTENT;
  if (!content) return;
  document.querySelectorAll('[data-ax-link]').forEach(link => {
    const href = content.links?.[link.dataset.axLink];
    if (href) link.href = href;
  });
})();
