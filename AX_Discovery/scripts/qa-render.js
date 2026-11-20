import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const root = resolve(import.meta.dirname, '..');
const url = pathToFileURL(resolve(root, 'index.html')).href;
const qaDir = resolve(root, 'tmp', 'qa');
await mkdir(qaDir, { recursive: true });

const browser = await chromium.launch({ headless: true });
const report = { desktop: {}, mobile: {}, links: [], sections: { desktop: [], mobile: [] } };

async function auditSections(page, mode) {
  const sections = page.locator('.pdf-page');
  const count = await sections.count();
  const audit = [];
  for (let index = 0; index < count; index += 1) {
    const section = sections.nth(index);
    const id = (await section.getAttribute('id')) || (index === 0 ? 'top' : `page-${index + 1}`);
    await section.screenshot({ path: resolve(qaDir, `${mode}-${String(index + 1).padStart(2, '0')}-${id}.png`) });
    audit.push(await section.evaluate((element) => {
      const bounds = element.getBoundingClientRect();
      const visibleNodes = [...element.querySelectorAll('*')].filter((node) => {
        const style = getComputedStyle(node);
        const rect = node.getBoundingClientRect();
        return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
      });
      const overflow = visibleNodes.filter((node) => {
        const rect = node.getBoundingClientRect();
        return rect.left < bounds.left - 1 || rect.right > bounds.right + 1 || rect.top < bounds.top - 1 || rect.bottom > bounds.bottom + 1;
      }).map((node) => ({ tag: node.tagName, className: node.className, text: node.textContent.trim().slice(0, 50) }));
      const bodyTextNodes = visibleNodes.filter((node) => node.matches('p, li, dd, dt'));
      const minBodyFontPx = bodyTextNodes.length
        ? Math.min(...bodyTextNodes.map((node) => Number.parseFloat(getComputedStyle(node).fontSize)))
        : null;
      return { id: element.id || null, height: bounds.height, overflow: overflow.slice(0, 10), minBodyFontPx };
    }));
  }
  return audit;
}
try {
  const desktop = await browser.newPage({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 });
  await desktop.goto(url, { waitUntil: 'networkidle' });
  await desktop.evaluate(async () => document.fonts?.ready);
  await desktop.screenshot({ path: resolve(qaDir, 'web-desktop.png'), fullPage: true });
  report.desktop = await desktop.evaluate(() => ({
    viewport: [innerWidth, innerHeight],
    pageCount: document.querySelectorAll('.pdf-page').length,
    documentWidth: document.documentElement.scrollWidth,
    hasHorizontalOverflow: document.documentElement.scrollWidth > innerWidth
  }));
  report.sections.desktop = await auditSections(desktop, 'desktop');
  report.links = await desktop.locator('a[href]').evaluateAll((links) => links.map((a) => a.getAttribute('href')));

  const mobile = await browser.newPage({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1 });
  await mobile.goto(url, { waitUntil: 'networkidle' });
  await mobile.evaluate(async () => document.fonts?.ready);
  await mobile.screenshot({ path: resolve(qaDir, 'web-mobile.png'), fullPage: true });
  report.mobile = await mobile.evaluate(() => ({
    viewport: [innerWidth, innerHeight],
    pageCount: document.querySelectorAll('.pdf-page').length,
    documentWidth: document.documentElement.scrollWidth,
    hasHorizontalOverflow: document.documentElement.scrollWidth > innerWidth
  }));
  report.sections.mobile = await auditSections(mobile, 'mobile');
} finally {
  await browser.close();
}

await writeFile(resolve(qaDir, 'web-audit.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
