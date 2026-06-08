import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';
const src = readFileSync(new URL('../shared/js/site.js', import.meta.url), 'utf8');
const chrome = `
<header class="nav"><div class="nav-inner">
<button id="menuTrigger" class="menu-trigger" aria-expanded="false" aria-controls="menuOverlay">Menu</button></div></header>
<button id="menuClose">닫기</button>
<aside id="menuOverlay" class="menu-overlay"><div class="menu-inner"><a href="#a">링크1</a><a href="#b">링크2</a></div></aside>`;
function setup() {
  const dom = new JSDOM(`<!doctype html><body>${chrome}</body>`, { runScripts: 'outside-only', url: 'http://localhost/', pretendToBeVisual: true });
  dom.window.HTMLElement.prototype.focus = function(){ this.ownerDocument.activeElement0 = this; };
  dom.window.eval(src);
  dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));
  return dom.window;
}
test('초기 상태: 오버레이 aria-hidden=true', () => {
  const w = setup();
  assert.equal(w.document.getElementById('menuOverlay').getAttribute('aria-hidden'), 'true');
});
test('열면 aria-hidden=false, 트리거 aria-expanded=true', () => {
  const w = setup();
  w.document.getElementById('menuTrigger').dispatchEvent(new w.Event('click', { bubbles: true }));
  assert.equal(w.document.getElementById('menuOverlay').getAttribute('aria-hidden'), 'false');
  assert.equal(w.document.getElementById('menuTrigger').getAttribute('aria-expanded'), 'true');
});
test('닫으면 다시 aria-hidden=true', () => {
  const w = setup();
  const t = w.document.getElementById('menuTrigger');
  t.dispatchEvent(new w.Event('click', { bubbles: true }));
  w.document.getElementById('menuClose').dispatchEvent(new w.Event('click', { bubbles: true }));
  assert.equal(w.document.getElementById('menuOverlay').getAttribute('aria-hidden'), 'true');
});
