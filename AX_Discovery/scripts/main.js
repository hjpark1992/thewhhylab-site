(() => {
  const printButton = document.querySelector('[data-print-button]');
  if (printButton) {
    printButton.addEventListener('click', async () => {
      if (document.fonts?.ready) await document.fonts.ready;
      window.print();
    });
  }
})();
