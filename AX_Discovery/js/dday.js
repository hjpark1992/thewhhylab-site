/* =========================================================
   AX Discovery — 4기 단계별 모집 카운트다운
   ---------------------------------------------------------
   ★ 기수/가격/마감일 변경 시 아래 CONFIG 만 수정하면
     헤더 타이머 · 가격 3단계 카드 · 신청 카드 · 사외교육
     참가신청서(appform.js)가 전부 자동으로 따라갑니다.
   ========================================================= */
(function () {
    'use strict';

    /* ---------- CONFIG ---------- */
    var COURSE = {
        cohort: '4기',
        // 교육 일정 (참가신청서·표기용)
        scheduleText: '2026년 10월 15일(목) ~ 10월 16일(금), 10:00~18:00 (총 14시간)',
        capacity: 12
    };

    // 모집 시작 시각 (타임라인 게이지 기준점)
    var CAMPAIGN_START = '2026-08-11T00:00:00+09:00';

    // 게이지 최소 진행률(%) — 모집 첫날에도 마커가 출발선에 붙어 있지 않도록
    var MIN_PROGRESS = 8;

    // 단계별 모집: 순서대로 앞 단계가 끝나면 다음 단계로 자동 전환
    var PHASES = [
        { key: 'super',  name: '슈퍼 얼리버드', price: 1200000, start: '2026-08-11T00:00:00+09:00', end: '2026-09-11T23:59:59+09:00', nextText: '9/12부터 140만원으로 인상' },
        { key: 'early',  name: '얼리버드',      price: 1400000, start: '2026-09-12T00:00:00+09:00', end: '2026-10-05T23:59:59+09:00', nextText: '10/6부터 160만원으로 인상' },
        { key: 'normal', name: '일반 수강료',   price: 1600000, start: '2026-10-06T00:00:00+09:00', end: '2026-10-13T23:59:59+09:00', nextText: '10/13 마감 후 접수 종료' }
    ];

    /* ---------- 유틸 ---------- */
    function won(n) { return n.toLocaleString('ko-KR') + '원'; }
    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    var DOW = ['일', '월', '화', '수', '목', '금', '토'];
    /* 사용자의 기기 시간대와 무관하게 항상 한국시간(KST) 기준으로 표기 */
    function fmtDate(iso) {
        var d = new Date(iso);
        var kst = new Date(d.getTime() + (d.getTimezoneOffset() * 60000) + (9 * 3600000));
        return kst.getFullYear() + '. ' + (kst.getMonth() + 1) + '. ' + kst.getDate() +
               ' (' + DOW[kst.getDay()] + ') ' + pad(kst.getHours()) + ':' + pad(kst.getMinutes());
    }

    function currentPhase(now) {
        for (var i = 0; i < PHASES.length; i++) {
            if (now < new Date(PHASES[i].end).getTime()) {
                return { index: i, phase: PHASES[i] };
            }
        }
        return null; // 전체 마감
    }

    /* 전역 노출: appform.js 등에서 참조 */
    function publish(now) {
        var cur = currentPhase(now);
        window.AX_COURSE = COURSE;
        window.AX_PHASE = cur ? {
            key: cur.phase.key,
            name: cur.phase.name,
            price: cur.phase.price,
            priceText: won(cur.phase.price),
            end: cur.phase.end,
            closed: false
        } : {
            key: 'closed',
            name: '모집 마감',
            price: PHASES[PHASES.length - 1].price,
            priceText: won(PHASES[PHASES.length - 1].price),
            end: PHASES[PHASES.length - 1].end,
            closed: true
        };
        return cur;
    }

    /* ---------- DOM 갱신 ---------- */
    var el = {};

    function cacheDom() {
        el.timer = document.getElementById('navTimer');
        el.label = document.getElementById('navTimerLabel');
        el.dday = document.getElementById('navTimerDday');
        el.clock = document.getElementById('navTimerClock');
        el.cols = document.querySelectorAll('#priceTimeline .ptl-col');
        el.fill = document.getElementById('ptlFill');
        el.train = document.getElementById('ptlTrain');
        el.trainLabel = document.getElementById('ptlTrainLabel');
        el.anchor = document.getElementById('priceAnchor');
        el.save = document.getElementById('priceSave');
        el.mcta = document.getElementById('mcta');
        el.mctaPrice = document.getElementById('mctaPrice');
        el.mctaMeta = document.getElementById('mctaMeta');
        el.navApply = document.getElementById('navApply');
        el.deadline = document.getElementById('registerDeadline');
        el.deadlineLabel = document.getElementById('registerDeadlineLabel');
        el.deadlineNote = document.getElementById('registerDeadlineNote');
        el.tag = document.getElementById('registerTag');
        el.price = document.getElementById('registerPrice');
        el.priceNote = document.getElementById('registerPriceNote');
        el.card = document.querySelector('#register .register-card');
    }

    var lastKey = null;

    function renderStatic(cur) {
        var key = cur ? cur.phase.key : 'closed';
        if (key === lastKey) return;
        lastKey = key;

        // 타임라인 컬럼 상태
        var activeIdx = cur ? cur.index : PHASES.length;
        Array.prototype.forEach.call(el.cols, function (node, i) {
            node.classList.remove('is-active', 'is-past', 'is-future');
            if (i < activeIdx) node.classList.add('is-past');
            else if (i === activeIdx) node.classList.add('is-active');
            else node.classList.add('is-future');
        });

        // 앵커링(정상가 대비 절감액) 배지
        var LIST = PHASES[PHASES.length - 1].price;
        if (el.anchor && el.save) {
            var save = cur ? LIST - cur.phase.price : 0;
            if (save > 0) {
                el.anchor.hidden = false;
                el.save.textContent = Math.round(save / 10000) + '만 원 SAVE';
            } else {
                el.anchor.hidden = true;
            }
        }


        if (cur) {
            if (el.tag) el.tag.textContent = COURSE.cohort + ' 모집중 · ' + cur.phase.name;
            if (el.mctaPrice) el.mctaPrice.textContent = won(cur.phase.price);

            // 신청 카드 '모집 마감' 행 = 현재 단계의 마감일
            if (el.deadlineLabel) el.deadlineLabel.textContent = cur.phase.name + ' 마감';
            if (el.deadline) el.deadline.textContent = fmtDate(cur.phase.end);
            if (el.deadlineNote) {
                var nx = PHASES[cur.index + 1];
                el.deadlineNote.textContent = nx
                    ? '이후 ' + nx.name + ' ' + won(nx.price) + ' 적용'
                    : '정원 12명 소진 시 조기 마감';
            }
            if (el.mctaMeta) el.mctaMeta.textContent = cur.phase.name;
            if (el.price) el.price.textContent = won(cur.phase.price);
            if (el.priceNote) el.priceNote.textContent = '현재 ' + cur.phase.name + ' 적용가 (VAT · 응시료 포함)';
        } else {
            // 전체 마감 처리
            if (el.tag) {
                el.tag.textContent = COURSE.cohort + ' 모집 마감';
                el.tag.classList.add('register-card-tag--closed');
            }
            if (el.price) el.price.textContent = won(PHASES[PHASES.length - 1].price);
            if (el.priceNote) el.priceNote.textContent = '모집이 종료되었습니다';
            if (el.card) el.card.classList.add('is-closed');

            // 모든 신청 버튼 제거 + 마감 안내로 대체
            var btns = document.querySelectorAll('[data-apply-btn]');
            Array.prototype.forEach.call(btns, function (b) { b.style.display = 'none'; });

            var pending = document.getElementById('registerPending');
            if (pending) { pending.hidden = true; }

            if (el.card && !el.card.querySelector('.register-closed-notice')) {
                var notice = document.createElement('div');
                notice.className = 'register-closed-notice';
                notice.textContent = '4기 모집이 마감되었습니다. 다음 기수 안내를 원하시면 문의해 주세요.';
                el.card.appendChild(notice);
            }

            if (el.fill) el.fill.style.width = '100%';
            if (el.mcta) { el.mcta.hidden = true; el.mcta.classList.add('is-dead'); }
            if (el.navApply) el.navApply.classList.add('is-hidden');
            if (el.deadlineLabel) el.deadlineLabel.textContent = '모집 마감';
            if (el.deadline) el.deadline.textContent = fmtDate(PHASES[PHASES.length - 1].end);
            if (el.deadlineNote) el.deadlineNote.textContent = '4기 모집이 종료되었습니다';
            if (el.train) el.train.style.display = 'none';

            if (el.timer) {
                el.timer.classList.add('is-ended');
                if (el.label) el.label.textContent = '4기 모집 마감';
                if (el.dday) el.dday.textContent = '';
                if (el.clock) el.clock.textContent = '';
            }
        }
    }

    function renderTick(cur, now) {
        if (!cur) return;

        // ---- 타임라인 게이지 (3구간 균등 폭, 구간 내 경과율로 채움) ----
        if (el.fill) {
            var seg = 100 / PHASES.length;
            var st = new Date(cur.phase.start || CAMPAIGN_START).getTime();
            var en = new Date(cur.phase.end).getTime();
            var within = en > st ? Math.min(1, Math.max(0, (now - st) / (en - st))) : 0;
            var pct = cur.index * seg + within * seg;
            // 첫 구간 초반에도 "이미 출발했다"는 인상을 주기 위한 최소 진행률
            if (pct < MIN_PROGRESS) pct = MIN_PROGRESS;
            el.fill.style.width = pct.toFixed(2) + '%';
            if (el.train) el.train.style.left = pct.toFixed(2) + '%';
        }

        if (!el.timer) return;
        var diff = new Date(cur.phase.end).getTime() - now;
        if (diff < 0) diff = 0;

        var totalSec = Math.floor(diff / 1000);
        var days = Math.floor(totalSec / 86400);
        var hours = Math.floor((totalSec % 86400) / 3600);
        var mins = Math.floor((totalSec % 3600) / 60);
        var secs = totalSec % 60;

        if (el.label) el.label.textContent = cur.phase.name + ' 마감';
        if (el.dday) el.dday.textContent = 'D-' + days;
        if (el.clock) {
            el.clock.innerHTML = '<b>' + pad(hours) + '</b><i>:</i><b>' + pad(mins) + '</b><i>:</i><b>' + pad(secs) + '</b>';
        }
    }

    function tick() {
        var now = Date.now();
        var cur = publish(now);
        renderStatic(cur);
        renderTick(cur, now);
    }

    // 스크립트 로드 시점에 즉시 1회 (appform이 참조할 수 있도록)
    publish(Date.now());

    document.addEventListener('DOMContentLoaded', function () {
        cacheDom();
        tick();
        setInterval(tick, 1000);
    });
})();
