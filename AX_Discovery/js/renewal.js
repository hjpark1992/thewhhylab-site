/* =========================================================
   AX Discovery — 리뉴얼(4기) 인터랙션
   1) 대표 후기 슬라이더
   2) 커리큘럼 아코디언
   3) 신청 링크 준비중 안내 토글
   ========================================================= */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------- 1) 대표 후기 슬라이더 ---------- */
    (function initReviewSlider() {
        var root = document.getElementById('rslider');
        var track = document.getElementById('rsliderTrack');
        var dotsWrap = document.getElementById('rsliderDots');
        if (!root || !track) return;

        var slides = track.querySelectorAll('.rslide');
        if (!slides.length) return;

        var idx = 0;
        var timer = null;

        // 점 생성
        var dots = [];
        Array.prototype.forEach.call(slides, function (_, i) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'rslider-dot';
            b.setAttribute('aria-label', (i + 1) + '번째 후기');
            b.addEventListener('click', function () { go(i, true); });
            if (dotsWrap) dotsWrap.appendChild(b);
            dots.push(b);
        });

        function go(n, manual) {
            idx = (n + slides.length) % slides.length;
            track.style.transform = 'translateX(' + (-idx * 100) + '%)';
            dots.forEach(function (d, i) { d.classList.toggle('is-on', i === idx); });
            Array.prototype.forEach.call(slides, function (s, i) {
                s.setAttribute('aria-hidden', i === idx ? 'false' : 'true');
            });
            if (manual) restart();
        }

        function next() { go(idx + 1); }
        function prev() { go(idx - 1); }

        function restart() {
            if (timer) clearInterval(timer);
            if (!reduceMotion) timer = setInterval(next, 5000);
        }

        root.querySelectorAll('[data-rslide]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.getAttribute('data-rslide') === 'next') { go(idx + 1, true); }
                else { go(idx - 1, true); }
            });
        });

        // 터치 스와이프
        var startX = null;
        root.addEventListener('touchstart', function (e) {
            startX = e.touches[0].clientX;
        }, { passive: true });
        root.addEventListener('touchend', function (e) {
            if (startX === null) return;
            var dx = e.changedTouches[0].clientX - startX;
            if (Math.abs(dx) > 45) { dx < 0 ? go(idx + 1, true) : go(idx - 1, true); }
            startX = null;
        }, { passive: true });

        // 마우스 올리면 정지
        root.addEventListener('mouseenter', function () { if (timer) clearInterval(timer); });
        root.addEventListener('mouseleave', restart);

        go(0);
        restart();
    })();

    /* ---------- 2) 커리큘럼 아코디언 ---------- */
    (function initCurriculumAccordion() {
        var items = document.querySelectorAll('.cur-item');
        if (!items.length) return;

        Array.prototype.forEach.call(items, function (item) {
            var btn = item.querySelector('.cur-q');
            if (!btn) return;
            btn.addEventListener('click', function () {
                var isOpen = item.classList.contains('open');
                if (isOpen) {
                    item.classList.remove('open');
                    btn.setAttribute('aria-expanded', 'false');
                } else {
                    Array.prototype.forEach.call(items, function (other) {
                        other.classList.remove('open');
                        var ob = other.querySelector('.cur-q');
                        if (ob) ob.setAttribute('aria-expanded', 'false');
                    });
                    item.classList.add('open');
                    btn.setAttribute('aria-expanded', 'true');
                }
            });
        });
    })();

    /* ---------- 3) 모바일 후기 더보기 ---------- */
    (function initReviewMore() {
        var grid = document.querySelector('.rev-grid');
        var btn = document.getElementById('revMore');
        if (!grid || !btn) return;

        var VISIBLE = 4;
        var cards = grid.querySelectorAll('.rev-card');
        if (cards.length <= VISIBLE) return;

        var mq = window.matchMedia('(max-width: 680px)');

        function apply() {
            if (mq.matches && !grid.classList.contains('is-expanded')) {
                grid.classList.add('is-collapsed');
                btn.hidden = false;
                var c = btn.querySelector('.rev-more-count');
                if (c) c.textContent = '(' + (cards.length - VISIBLE) + ')';
            } else {
                grid.classList.remove('is-collapsed');
                btn.hidden = !mq.matches || grid.classList.contains('is-expanded') ? true : false;
                if (mq.matches && grid.classList.contains('is-expanded')) btn.hidden = true;
            }
        }

        btn.addEventListener('click', function () {
            grid.classList.add('is-expanded');
            grid.classList.remove('is-collapsed');
            btn.hidden = true;
        });

        if (mq.addEventListener) mq.addEventListener('change', apply);
        else if (mq.addListener) mq.addListener(apply);
        apply();
    })();

    /* ---------- 4) 부서별 후기 토글 ---------- */
    (function initDeptToggle() {
        var items = document.querySelectorAll('.dept-item');
        if (!items.length) return;
        Array.prototype.forEach.call(items, function (item) {
            var btn = item.querySelector('.dept-q');
            if (!btn) return;
            btn.addEventListener('click', function () {
                var open = item.classList.toggle('open');
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        });
    })();

    /* ---------- 5) 모바일 고정 CTA ---------- */
    (function initMobileCta() {
        var bar = document.getElementById('mcta');
        var register = document.getElementById('register');
        var hero = document.querySelector('.hero');
        if (!bar || !register || !hero) return;

        var mq = window.matchMedia('(max-width: 860px)');
        var ticking = false;

        function update() {
            ticking = false;
            if (!mq.matches || bar.classList.contains('is-dead')) {
                bar.hidden = true;
                bar.classList.remove('is-on');
                document.body.classList.remove('has-mcta');
                return;
            }
            var vh = window.innerHeight;
            var h = hero.getBoundingClientRect();
            var r = register.getBoundingClientRect();
            var heroVisible = h.bottom > 80;              // 히어로가 아직 보이면 숨김
            var registerVisible = r.top < vh && r.bottom > 0;  // 신청 영역이 보이면 숨김
            var show = !heroVisible && !registerVisible;

            bar.hidden = !show;
            bar.classList.toggle('is-on', show);
            document.body.classList.toggle('has-mcta', show);
        }

        function onScroll() {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(update);
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll, { passive: true });
        if (mq.addEventListener) mq.addEventListener('change', update);
        update();
    })();

})();
