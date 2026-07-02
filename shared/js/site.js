/* 더와이랩 — 공통 사이트 스크립트 */
(function () {
  // ----- Header scroll state -----
  var nav = document.querySelector('.nav');
  if (nav) {
    var onScroll = function () {
      var s = window.scrollY > 24;
      nav.classList.toggle('scrolled', s);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  // ----- Full-screen menu overlay -----
  var trigger = document.getElementById('menuTrigger');
  var overlay = document.getElementById('menuOverlay');
  var closeBtn = document.getElementById('menuClose');
  var menuInited = false;
  function focusables() {
    return overlay ? overlay.querySelectorAll('a[href], button:not([disabled])') : [];
  }
  function setMenu(open) {
    document.body.classList.toggle('menu-open', open);
    if (trigger) trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (overlay) {
      // belt-and-suspenders: also set inline style so opacity transition is guaranteed
      overlay.style.opacity = open ? '1' : '0';
      overlay.style.pointerEvents = open ? 'auto' : 'none';
      overlay.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
    if (!menuInited) { menuInited = true; return; }
    if (open) {
      var f = focusables();
      if (f.length && f[0].focus) f[0].focus();
    } else {
      if (trigger && trigger.focus) trigger.focus();
    }
  }
  if (trigger && overlay) {
    setMenu(false);
    trigger.addEventListener('click', function () { setMenu(true); });
    if (closeBtn) closeBtn.addEventListener('click', function () { setMenu(false); });
    overlay.addEventListener('click', function (e) {
      if (e.target.closest && e.target.closest('a')) setMenu(false);
    });
    document.addEventListener('keydown', function (e) {
      if (!document.body.classList.contains('menu-open')) return;
      if (e.key === 'Escape') { setMenu(false); return; }
      if (e.key === 'Tab') {
        var f = focusables();
        if (!f.length) return;
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); if (last.focus) last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); if (first.focus) first.focus(); }
      }
    });
  }

  // ----- Hero video lazy load + mute toggle -----
  var video = document.getElementById('heroVideo');
  var muteBtn = document.getElementById('muteToggle');
  if (video) {
    // 모바일(작은 뷰포트)에선 영상 로드 자체를 건너뜀
    var skipVideo = window.matchMedia && window.matchMedia('(max-width: 640px)').matches;
    // prefers-reduced-motion이거나 절약 모드면 건너뜀
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) skipVideo = true;
    if (navigator.connection && navigator.connection.saveData) skipVideo = true;

    if (skipVideo) {
      // 영상 컨테이너에 정적 배경 폴백 (CSS의 .hero-video 다크 배경 유지)
      video.style.display = 'none';
      if (muteBtn) muteBtn.style.display = 'none';
    } else if ('IntersectionObserver' in window && video.dataset.src) {
      // hero가 보일 때만 video src 주입
      var vio = new IntersectionObserver(function (ents) {
        ents.forEach(function (en) {
          if (en.isIntersecting) {
            video.src = video.dataset.src;
            video.removeAttribute('data-src');
            vio.disconnect();
          }
        });
      }, { rootMargin: '200px' });
      vio.observe(video);
    } else if (video.dataset.src) {
      // IO 미지원: 즉시 로드
      video.src = video.dataset.src;
    }
  }
  if (video && muteBtn) {
    var label = muteBtn.querySelector('.video-mute-label');
    function setMuted(m) {
      video.muted = m;
      muteBtn.setAttribute('aria-pressed', m ? 'true' : 'false');
      muteBtn.setAttribute('aria-label', m ? '소리 켜기' : '소리 끄기');
      if (label) label.textContent = m ? '소리 켜기' : '소리 끄기';
    }
    muteBtn.addEventListener('click', function () {
      var nextMuted = !video.muted;
      setMuted(nextMuted);
      if (!nextMuted) video.play().catch(function () {});
    });
    video.addEventListener('canplay', function () {
      var p = video.play();
      if (p && typeof p.catch === 'function') p.catch(function () {});
    });
  }

  // ----- Hero banner carousel (자동 슬라이드 · 3개 · 5초) -----
  (function () {
    var track = document.getElementById('hbTrack');
    var dotsWrap = document.getElementById('hbDots');
    if (!track) return;

    var slides = track.querySelectorAll('.hb-slide');
    var dots = dotsWrap ? dotsWrap.querySelectorAll('.hb-dot') : [];
    var prevBtn = document.querySelector('.hb-prev');
    var nextBtn = document.querySelector('.hb-next');
    var total = slides.length;
    if (total <= 1) return;

    var index = 0;
    // 슬라이드별 머무는 시간(ms) — 텍스트 양에 맞춰 배분
    // 1 AX(간결) / 2 강사(보통) / 3 스쿨(보통) / 4 책(많음)
    var DURATIONS = [6000, 5500, 6500, 6500, 7000, 6500];
    var DEFAULT_DURATION = 6000;
    var timer = null;
    var autoplaying = false;
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function go(i) {
      index = (i + total) % total;
      for (var s = 0; s < slides.length; s++) {
        slides[s].classList.toggle('is-current', s === index);
      }
      for (var d = 0; d < dots.length; d++) {
        var on = d === index;
        dots[d].classList.toggle('is-active', on);
        dots[d].setAttribute('aria-selected', on ? 'true' : 'false');
      }
      // 현재 슬라이드에 등장 애니메이션 1회 부여 (이미 본 슬라이드는 유지)
      if (slides[index]) slides[index].classList.add('is-shown');
    }
    function next() { go(index + 1); }
    function prev() { go(index - 1); }

    function start() {
      if (reduceMotion) return;
      stop();
      autoplaying = true;
      var dur = DURATIONS[index] || DEFAULT_DURATION;
      timer = setTimeout(function () { next(); if (autoplaying) start(); }, dur);
    }
    function stop() {
      autoplaying = false;
      if (timer) { clearTimeout(timer); timer = null; }
    }

    if (nextBtn) nextBtn.addEventListener('click', function () { next(); start(); });
    if (prevBtn) prevBtn.addEventListener('click', function () { prev(); start(); });
    for (var k = 0; k < dots.length; k++) {
      (function (idx) {
        dots[idx].addEventListener('click', function () { go(idx); start(); });
      })(k);
    }

    // 마우스 올리면 일시정지, 벗어나면 재개
    var banner = track.closest('.hero-banner');
    if (banner) {
      banner.addEventListener('mouseenter', stop);
      banner.addEventListener('mouseleave', start);
    }

    // 탭이 백그라운드일 때 정지 (배터리/리소스 절약)
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) stop(); else start();
    });

    // 터치 스와이프 지원
    var startX = null;
    track.addEventListener('touchstart', function (e) {
      startX = e.touches[0].clientX; stop();
    }, { passive: true });
    track.addEventListener('touchend', function (e) {
      if (startX === null) return;
      var dx = e.changedTouches[0].clientX - startX;
      if (Math.abs(dx) > 40) { dx < 0 ? next() : prev(); }
      startX = null; start();
    }, { passive: true });

    go(0);
    start();
  })();

  // ----- Intersection-based reveal -----
  if ('IntersectionObserver' in window) {
    document.documentElement.setAttribute('data-reveal-ready', '');
    var clientCells = document.querySelectorAll('.clients-grid .client-cell');
    clientCells.forEach(function (cell, i) {
      cell.style.setProperty('--i', i);
    });
    var els = document.querySelectorAll('.reveal');
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.classList.add('visible');
          io.unobserve(en.target);
        }
      });
    }, { threshold: 0.05, rootMargin: '0px 0px -6% 0px' });
    els.forEach(function (el) {
      var rect = el.getBoundingClientRect();
      if (rect.top < window.innerHeight * 0.9) {
        el.classList.add('visible');
        return;
      }
      el.classList.add('pre-reveal');
      io.observe(el);
    });
  } else {
    // IO 미지원: .reveal 요소를 즉시 visible 처리
    document.querySelectorAll('.reveal').forEach(function (el) {
      el.classList.add('visible');
    });
  }

  // ----- Growth chart bar animation (CSS-driven) -----
  var chart = document.getElementById('growthChart');
  if (chart) {
    // Set CSS custom properties for height & stagger delay
    var bars = chart.querySelectorAll('.chart-bar');
    bars.forEach(function (bar, i) {
      var pct = bar.getAttribute('data-pct') || '30';
      bar.style.setProperty('--h', pct + '%');
      bar.style.setProperty('--delay', (i * 0.08) + 's');
    });

    var animate = function () {
      chart.classList.add('animated');
    };

    if ('IntersectionObserver' in window) {
      var rect = chart.getBoundingClientRect();
      if (rect.top < window.innerHeight * 0.9) {
        // already in view — animate immediately
        animate();
      } else {
        chart.classList.add('pre-animate');
        var co = new IntersectionObserver(function (ents) {
          ents.forEach(function (e) {
            if (e.isIntersecting) {
              animate();
              co.disconnect();
            }
          });
        }, { threshold: 0.3 });
        co.observe(chart);
      }
    } else {
      chart.classList.add('no-anim');
    }
  }

  // ----- v7: Scroll progress bar -----
  if (!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
    var progress = document.createElement('div');
    progress.className = 'scroll-progress';
    document.body.appendChild(progress);
    var ticking = false;
    function updateProgress() {
      var h = document.documentElement.scrollHeight - window.innerHeight;
      var pct = h > 0 ? (window.scrollY / h) * 100 : 0;
      progress.style.width = pct + '%';
      ticking = false;
    }
    window.addEventListener('scroll', function () {
      if (!ticking) { requestAnimationFrame(updateProgress); ticking = true; }
    }, { passive: true });
    updateProgress();

    // ----- v7: Film grain overlay (subtle, only on wide viewports) -----
    if (window.innerWidth > 640) {
      var grain = document.createElement('div');
      grain.className = 'grain-overlay';
      grain.setAttribute('aria-hidden', 'true');
      document.body.appendChild(grain);
    }
  }

  // ----- v7: Magnetic buttons (pointer-only, primary CTAs) -----
  if (window.matchMedia && window.matchMedia('(hover: hover)').matches && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var magnets = document.querySelectorAll('.btn-primary, .p-cta-btn, .menu-cta');
    magnets.forEach(function (btn) {
      var raf = null;
      btn.addEventListener('pointermove', function (e) {
        var r = btn.getBoundingClientRect();
        var x = e.clientX - (r.left + r.width / 2);
        var y = e.clientY - (r.top + r.height / 2);
        if (raf) cancelAnimationFrame(raf);
        raf = requestAnimationFrame(function () {
          btn.style.transform = 'translate(' + (x * 0.18) + 'px, ' + (y * 0.18) + 'px)';
        });
      });
      btn.addEventListener('pointerleave', function () {
        if (raf) cancelAnimationFrame(raf);
        btn.style.transform = '';
      });
    });
  }

  // ----- v7: Reference card stagger index -----
  document.querySelectorAll('.refs > article').forEach(function (el, i) {
    el.style.setProperty('--i', i);
  });

})();
