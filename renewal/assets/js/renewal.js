/* THE WHHY LAB — Renewal 2026
   헤더 스크롤 상태 · 전체 메뉴(포커스 트랩/ESC) · 스크롤 리빌 · 숫자 카운트업 · 레일 스크롤 · 서브 네비 활성화
   JS가 없어도 모든 콘텐츠는 보인다(리빌은 html.js 일 때만 숨김). */
(function () {
  'use strict';
  var root = document.documentElement;
  root.classList.add('js');
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- header shadow ---------- */
  var hdr = document.querySelector('.hdr');
  function onScroll() { if (hdr) hdr.classList.toggle('is-scrolled', window.scrollY > 8); }
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  /* ---------- full menu ---------- */
  var menu = document.getElementById('menu');
  var openBtn = document.getElementById('menuOpen');
  var closeBtn = document.getElementById('menuClose');
  var lastFocus = null;
  var FOCUSABLE = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';

  function setMenu(open) {
    if (!menu) return;
    menu.classList.toggle('is-open', open);
    menu.setAttribute('aria-hidden', String(!open));
    document.body.classList.toggle('menu-open', open);
    if (openBtn) openBtn.setAttribute('aria-expanded', String(open));
    if (open) {
      lastFocus = document.activeElement;
      var first = menu.querySelector(FOCUSABLE);
      if (first) first.focus();
    } else if (lastFocus && lastFocus.focus) {
      lastFocus.focus();
    }
  }
  if (openBtn) openBtn.addEventListener('click', function () { setMenu(!menu.classList.contains('is-open')); });
  if (closeBtn) closeBtn.addEventListener('click', function () { setMenu(false); });
  document.addEventListener('keydown', function (e) {
    if (!menu || !menu.classList.contains('is-open')) return;
    if (e.key === 'Escape') { e.preventDefault(); setMenu(false); return; }
    if (e.key !== 'Tab') return;
    var items = Array.prototype.slice.call(menu.querySelectorAll(FOCUSABLE)).filter(function (el) { return el.offsetParent !== null; });
    if (!items.length) return;
    var first = items[0], last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });
  if (menu) menu.addEventListener('click', function (e) {
    var a = e.target.closest('a');
    if (a && a.getAttribute('href') && a.getAttribute('href').charAt(0) === '#') setMenu(false);
  });

  /* ---------- reveal on scroll ---------- */
  var rv = document.querySelectorAll('.rv, .rv-stagger');
  if (reduce || !('IntersectionObserver' in window)) {
    rv.forEach(function (el) { el.classList.add('in'); });
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    rv.forEach(function (el) { io.observe(el); });
  }

  /* ---------- count up ---------- */
  var nums = document.querySelectorAll('[data-count]');
  function fmt(n) { return n.toLocaleString('ko-KR'); }
  function animate(el) {
    var target = parseFloat(el.getAttribute('data-count'));
    var dur = 1100, start = null;
    function step(ts) {
      if (start === null) start = ts;
      var p = Math.min((ts - start) / dur, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = fmt(Math.round(target * eased));
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  if (reduce || !('IntersectionObserver' in window)) {
    nums.forEach(function (el) { el.textContent = fmt(parseFloat(el.getAttribute('data-count'))); });
  } else {
    var io2 = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { animate(en.target); io2.unobserve(en.target); }
      });
    }, { threshold: 0.4 });
    nums.forEach(function (el) { el.textContent = '0'; io2.observe(el); });
  }

  /* ---------- reference rail controls ---------- */
  document.querySelectorAll('[data-rail]').forEach(function (wrap) {
    var rail = wrap.querySelector('.rail');
    if (!rail) return;
    wrap.querySelectorAll('[data-dir]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var card = rail.firstElementChild;
        var w = card ? card.getBoundingClientRect().width + 16 : 320;
        rail.scrollBy({ left: btn.getAttribute('data-dir') === 'next' ? w : -w, behavior: reduce ? 'auto' : 'smooth' });
      });
    });
  });

  /* ---------- sub nav active state ---------- */
  var subnav = document.querySelector('.subnav');
  if (subnav && 'IntersectionObserver' in window) {
    var links = Array.prototype.slice.call(subnav.querySelectorAll('a[href^="#"]'));
    var targets = links.map(function (a) { return document.querySelector(a.getAttribute('href')); }).filter(Boolean);
    var io3 = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        links.forEach(function (a) { a.classList.toggle('is-active', a.getAttribute('href') === '#' + en.target.id); });
      });
    }, { rootMargin: '-40% 0px -55% 0px' });
    targets.forEach(function (t) { io3.observe(t); });
  }

  /* ---------- year ---------- */
  var y = document.getElementById('year');
  if (y) y.textContent = String(new Date().getFullYear());
})();
