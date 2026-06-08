/* =========================================================
   AX Discovery — Interaction Enhancements (Add-on)
   원본 main.js + 인터랙션 강화
   ========================================================= */

(function () {
    'use strict';

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const isCoarsePointer = window.matchMedia('(pointer: coarse)').matches;

    /* ==========================================
       1. Scroll Progress Bar
       ========================================== */
    (function initScrollProgress() {
        const bar = document.querySelector('.scroll-progress-bar');
        if (!bar) return;
        let ticking = false;
        function update() {
            const scrollTop = window.scrollY || document.documentElement.scrollTop;
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            const progress = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
            bar.style.width = progress + '%';
            ticking = false;
        }
        window.addEventListener('scroll', function () {
            if (!ticking) {
                requestAnimationFrame(update);
                ticking = true;
            }
        }, { passive: true });
        update();
    })();

    /* ==========================================
       2. Cursor Glow (hero/section follow)
       ========================================== */
    (function initCursorGlow() {
        if (isCoarsePointer || reduceMotion) return;
        const glow = document.querySelector('.cursor-glow');
        if (!glow) return;

        let mx = window.innerWidth / 2;
        let my = window.innerHeight / 2;
        let cx = mx;
        let cy = my;
        let rafId = null;

        function loop() {
            cx += (mx - cx) * 0.12;
            cy += (my - cy) * 0.12;
            glow.style.transform = 'translate(' + (cx - 240) + 'px, ' + (cy - 240) + 'px)';
            rafId = requestAnimationFrame(loop);
        }

        document.addEventListener('mousemove', function (e) {
            mx = e.clientX;
            my = e.clientY;
            glow.classList.add('is-active');
        });

        document.addEventListener('mouseleave', function () {
            glow.classList.remove('is-active');
        });

        loop();
    })();

    /* ==========================================
       3. Number Counter Animation
       ========================================== */
    (function initCounters() {
        const counters = document.querySelectorAll('.counter');
        if (!counters.length) return;

        function animateCounter(el) {
            const target = parseFloat(el.dataset.target);
            const decimals = parseInt(el.dataset.decimals || '0', 10);
            const suffix = el.dataset.suffix || '';
            const prefix = el.dataset.prefix || '';
            const duration = 1800;
            const startTime = performance.now();
            const wrap = el.closest('.counter-wrap');

            function easeOutExpo(t) {
                return t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
            }

            function formatNum(n) {
                if (decimals > 0) return n.toFixed(decimals);
                return Math.floor(n).toLocaleString('en-US');
            }

            function tick(now) {
                const elapsed = now - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const eased = easeOutExpo(progress);
                const current = target * eased;

                el.textContent = prefix + formatNum(current) + suffix;

                if (progress < 1) {
                    requestAnimationFrame(tick);
                } else {
                    el.textContent = prefix + (decimals > 0 ? target.toFixed(decimals) : target.toLocaleString('en-US')) + suffix;
                    if (wrap) wrap.classList.add('counted');
                }
            }
            requestAnimationFrame(tick);
        }

        if ('IntersectionObserver' in window) {
            const io = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        animateCounter(entry.target);
                        io.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });
            counters.forEach(function (el) { io.observe(el); });
        } else {
            counters.forEach(function (el) {
                const decimals = parseInt(el.dataset.decimals || '0', 10);
                const target = parseFloat(el.dataset.target);
                const suffix = el.dataset.suffix || '';
                const prefix = el.dataset.prefix || '';
                el.textContent = prefix + (decimals > 0 ? target.toFixed(decimals) : target.toLocaleString('en-US')) + suffix;
            });
        }
    })();

    /* ==========================================
       4. Hero Title Word-by-word Split
       ========================================== */
    (function initTitleSplit() {
        const titles = document.querySelectorAll('[data-split]');
        if (!titles.length) return;

        // 1) 모든 data-split 타이틀의 단어를 span으로 감싸기
        titles.forEach(function (title) {
            const html = title.innerHTML;
            const protectedHtml = html.replace(/<br\s*\/?>/gi, '###BR###');
            const parts = protectedHtml.split(/(<[^>]+>)/);
            let result = '';
            parts.forEach(function (part) {
                if (part.startsWith('<')) {
                    result += part;
                } else {
                    const words = part.split(/(\s+)/);
                    words.forEach(function (w) {
                        if (w.trim() === '') {
                            result += w;
                        } else {
                            result += '<span class="split-word">' + w + '</span>';
                        }
                    });
                }
            });
            result = result.replace(/###BR###/g, '<br>');
            title.innerHTML = result;
        });

        // 2) reduceMotion 사용자 또는 IO 미지원 환경 — 즉시 visible
        if (reduceMotion || !('IntersectionObserver' in window)) {
            document.querySelectorAll('.split-word').forEach(function (w) {
                w.classList.add('visible');
            });
            return;
        }

        // 3) Hero 등 첫 화면에 보이는 타이틀은 페이지 로드 직후 즉시 애니메이션 시작
        //    (스크롤 안 해도 보여야 하는 영역)
        titles.forEach(function (title) {
            const rect = title.getBoundingClientRect();
            const inViewport = rect.top < window.innerHeight && rect.bottom > 0;

            if (inViewport) {
                // 즉시 stagger 애니메이션 시작
                const words = title.querySelectorAll('.split-word');
                words.forEach(function (w, i) {
                    setTimeout(function () {
                        w.classList.add('visible');
                    }, 100 + i * 60);
                });
            } else {
                // 스크롤로 진입할 때 트리거
                const io = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            const words = entry.target.querySelectorAll('.split-word');
                            words.forEach(function (w, i) {
                                setTimeout(function () {
                                    w.classList.add('visible');
                                }, i * 60);
                            });
                            io.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.1 });
                io.observe(title);
            }
        });

        // 4) Safety net: 3초 후에도 visible이 안 된 split-word는 강제로 visible 처리
        //    (어떤 이유로든 애니메이션이 트리거 실패한 경우 텍스트가 보이지 않는 사고 방지)
        setTimeout(function () {
            document.querySelectorAll('.split-word:not(.visible)').forEach(function (w) {
                w.classList.add('visible');
            });
        }, 3000);
    })();

    /* ==========================================
       5. Card Tilt 3D (글로벌 모든 .tilt-card에 적용)
       ========================================== */
    (function initTilt() {
        if (isCoarsePointer || reduceMotion) return;
        const cards = document.querySelectorAll('.tilt-card');
        cards.forEach(function (card) {
            let rafId = null;

            function handleMove(e) {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const cx = rect.width / 2;
                const cy = rect.height / 2;
                const nx = (x - cx) / cx;
                const ny = (y - cy) / cy;
                const ry = nx * 8;
                const rx = -ny * 8;
                const mx = (x / rect.width) * 100;
                const my = (y / rect.height) * 100;

                if (rafId) cancelAnimationFrame(rafId);
                rafId = requestAnimationFrame(function () {
                    card.style.setProperty('--rx', rx + 'deg');
                    card.style.setProperty('--ry', ry + 'deg');
                    card.style.setProperty('--tz', '12px');
                    card.style.setProperty('--mx', mx + '%');
                    card.style.setProperty('--my', my + '%');
                });
            }

            function handleEnter() {
                card.classList.add('is-tilting');
            }
            function handleLeave() {
                card.classList.remove('is-tilting');
                card.style.setProperty('--rx', '0deg');
                card.style.setProperty('--ry', '0deg');
                card.style.setProperty('--tz', '0px');
            }

            card.addEventListener('mouseenter', handleEnter);
            card.addEventListener('mousemove', handleMove);
            card.addEventListener('mouseleave', handleLeave);
        });
    })();

    /* ==========================================
       6. Card Hover Glow (mouse position tracking for pain/solution/feature cards)
       ========================================== */
    (function initCardGlow() {
        if (isCoarsePointer) return;
        const cards = document.querySelectorAll('.pain-card, .solution-card, .feature-card');
        cards.forEach(function (card) {
            card.addEventListener('mousemove', function (e) {
                const rect = card.getBoundingClientRect();
                const x = ((e.clientX - rect.left) / rect.width) * 100;
                const y = ((e.clientY - rect.top) / rect.height) * 100;
                card.style.setProperty('--mx', x + '%');
                card.style.setProperty('--my', y + '%');
            });
        });
    })();

    /* ==========================================
       7. Magnetic Buttons
       ========================================== */
    (function initMagnetic() {
        if (isCoarsePointer || reduceMotion) return;
        const buttons = document.querySelectorAll('.btn-magnetic');
        buttons.forEach(function (btn) {
            let rafId = null;
            btn.addEventListener('mousemove', function (e) {
                const rect = btn.getBoundingClientRect();
                const x = e.clientX - rect.left - rect.width / 2;
                const y = e.clientY - rect.top - rect.height / 2;
                if (rafId) cancelAnimationFrame(rafId);
                rafId = requestAnimationFrame(function () {
                    btn.style.transform = 'translate(' + (x * 0.18) + 'px, ' + (y * 0.18) + 'px)';
                });
            });
            btn.addEventListener('mouseleave', function () {
                btn.style.transform = '';
            });
        });
    })();

    /* ==========================================
       8. Scroll-to-Top Button
       ========================================== */
    (function initToTop() {
        const btn = document.querySelector('.to-top');
        if (!btn) return;

        function update() {
            if (window.scrollY > 600) {
                btn.classList.add('is-visible');
            } else {
                btn.classList.remove('is-visible');
            }
        }
        window.addEventListener('scroll', update, { passive: true });
        update();

        btn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    })();

    /* ==========================================
       9. Original main.js features (preserved)
       ========================================== */

    /* Navigation scroll state */
    const nav = document.getElementById('nav');
    function handleNavScroll() {
        if (!nav) return;
        if (window.scrollY > 30) nav.classList.add('scrolled');
        else nav.classList.remove('scrolled');
    }
    handleNavScroll();
    window.addEventListener('scroll', handleNavScroll, { passive: true });

    /* FAQ Accordion */
    const faqItems = document.querySelectorAll('.faq-item');
    faqItems.forEach(function (item) {
        const button = item.querySelector('.faq-question');
        if (!button) return;
        button.addEventListener('click', function () {
            const isOpen = item.classList.contains('open');
            if (isOpen) {
                item.classList.remove('open');
                button.setAttribute('aria-expanded', 'false');
            } else {
                faqItems.forEach(function (other) {
                    other.classList.remove('open');
                    const otherBtn = other.querySelector('.faq-question');
                    if (otherBtn) otherBtn.setAttribute('aria-expanded', 'false');
                });
                item.classList.add('open');
                button.setAttribute('aria-expanded', 'true');
            }
        });
    });

    /* Scroll Reveal (enhanced + safety net) */
    const revealElements = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { root: null, rootMargin: '0px 0px -8% 0px', threshold: 0.05 });

        revealElements.forEach(function (el) {
            // 첫 화면 안에 이미 보이는 reveal 요소는 즉시 visible
            const rect = el.getBoundingClientRect();
            if (rect.top < window.innerHeight && rect.bottom > 0) {
                // 살짝 delay 줘서 stagger 느낌만 유지
                setTimeout(function () { el.classList.add('visible'); }, 50);
            } else {
                revealObserver.observe(el);
            }
        });

        // Safety net: 3초 후에도 visible 안 된 reveal은 강제 표시
        // (PDF 캡쳐, 스크린샷 도구, 검색엔진 크롤러 등 어떤 환경에서도 텍스트가 사라지지 않게)
        setTimeout(function () {
            document.querySelectorAll('.reveal:not(.visible)').forEach(function (el) {
                el.classList.add('visible');
            });
        }, 3000);
    } else {
        revealElements.forEach(function (el) { el.classList.add('visible'); });
    }

    /* Smooth Scroll */
    const anchorLinks = document.querySelectorAll('a[href^="#"]:not([href="#"])');
    anchorLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            const targetId = link.getAttribute('href');
            const target = document.querySelector(targetId);
            if (target) {
                e.preventDefault();
                const navHeight = nav ? nav.offsetHeight : 0;
                const targetPosition = target.getBoundingClientRect().top + window.scrollY - navHeight - 12;
                window.scrollTo({ top: targetPosition, behavior: 'smooth' });
                if (history.pushState) history.pushState(null, '', targetId);
            }
        });
    });

    /* Original badge tilt — replaced by global tilt-card, but keep for [data-tilt] backwards compat */
    const tiltEls = document.querySelectorAll('[data-tilt]');
    if (!isCoarsePointer && !reduceMotion) {
        tiltEls.forEach(function (el) {
            if (el.classList.contains('tilt-card')) return; // already handled
            const parent = el.parentElement;
            let rafId = null;
            function handleMove(e) {
                const rect = parent.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const cx = rect.width / 2;
                const cy = rect.height / 2;
                const nx = (x - cx) / cx;
                const ny = (y - cy) / cy;
                const ry = nx * 12;
                const rx = -ny * 12;
                const mx = (x / rect.width) * 100;
                const my = (y / rect.height) * 100;
                if (rafId) cancelAnimationFrame(rafId);
                rafId = requestAnimationFrame(function () {
                    el.style.setProperty('--rx', rx + 'deg');
                    el.style.setProperty('--ry', ry + 'deg');
                    el.style.setProperty('--tz', '20px');
                    el.style.setProperty('--mx', mx + '%');
                    el.style.setProperty('--my', my + '%');
                });
            }
            function handleEnter() { el.classList.add('is-tilting'); }
            function handleLeave() {
                el.classList.remove('is-tilting');
                el.style.setProperty('--rx', '0deg');
                el.style.setProperty('--ry', '0deg');
                el.style.setProperty('--tz', '0px');
            }
            parent.addEventListener('mouseenter', handleEnter);
            parent.addEventListener('mousemove', handleMove);
            parent.addEventListener('mouseleave', handleLeave);
        });
    }

})();
