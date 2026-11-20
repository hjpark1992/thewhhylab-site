import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const root = resolve(import.meta.dirname, '..');
const htmlPath = resolve(root, 'index.html');
const outputPath = resolve(root, 'output', 'pdf', 'thewhylab-ax-introduction.pdf');

await mkdir(dirname(outputPath), { recursive: true });

const browser = await chromium.launch({ headless: true });
try {
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 });
  await page.goto(pathToFileURL(htmlPath).href, { waitUntil: 'networkidle' });
  await page.emulateMedia({ media: 'print' });
  await page.evaluate(async () => document.fonts?.ready);

  const audit = await page.evaluate(() => {
    const pages = [...document.querySelectorAll('.pdf-page')];
    return pages.map((item, index) => {
      const pageRect = item.getBoundingClientRect();
      const visibleNodes = [...item.querySelectorAll('*')].filter((node) => {
        const style = getComputedStyle(node);
        const rect = node.getBoundingClientRect();
        return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
      });
      const outOfBounds = visibleNodes.filter((node) => {
        const rect = node.getBoundingClientRect();
        return rect.left < pageRect.left - 1 || rect.right > pageRect.right + 1 || rect.top < pageRect.top - 1 || rect.bottom > pageRect.bottom + 1;
      }).map((node) => `${node.tagName}.${node.className}`);
      const smallText = visibleNodes.filter((node) => {
        if (node.closest('.page-footer')) return false;
        const hasDirectText = [...node.childNodes].some((child) => child.nodeType === Node.TEXT_NODE && child.textContent.trim());
        if (!hasDirectText) return false;
        const fontPt = Number.parseFloat(getComputedStyle(node).fontSize) * 0.75;
        return fontPt < 9.9;
      }).map((node) => ({
        element: `${node.tagName}.${node.className}`,
        text: node.textContent.trim().slice(0, 40),
        fontPt: Number((Number.parseFloat(getComputedStyle(node).fontSize) * 0.75).toFixed(2))
      }));

      return {
        page: index + 1,
        width: pageRect.width,
        height: pageRect.height,
        overflowX: item.scrollWidth - item.clientWidth,
        overflowY: item.scrollHeight - item.clientHeight,
        outOfBounds,
        smallText
      };
    });
  });

  const invalid = audit.filter((item) => item.overflowX > 1 || item.overflowY > 1 || item.outOfBounds.length || item.smallText.length);
  if (invalid.length) throw new Error(`A4 overflow detected: ${JSON.stringify(invalid)}`);

  await page.pdf({
    path: outputPath,
    format: 'A4',
    printBackground: true,
    preferCSSPageSize: true
  });

  console.log(`PDF exported: ${outputPath}`);
  console.log(`Page modules checked: ${audit.length}`);
} finally {
  await browser.close();
}
