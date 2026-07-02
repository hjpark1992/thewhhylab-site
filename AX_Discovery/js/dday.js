/* =========================================================
   AX Discovery — 모집 마감 D-day 카운트다운 (2기·3기 동시 노출)
   2기 마감: 2026년 7월 11일(토) 00:00 (KST)
   3기 마감: 2026년 8월 1일(토) 00:00 (KST)
   각 기수의 남은 일수를 함께 표시. 마감된 기수는 "마감"으로 표시.
   ========================================================= */
(function () {
    'use strict';

    var DEADLINES = [
        { time: new Date('2026-07-11T00:00:00+09:00').getTime() }, // 2기
        { time: new Date('2026-08-01T00:00:00+09:00').getTime() }  // 3기
    ];

    var timer = document.getElementById('navTimer');
    if (!timer) return;

    var ddayEls = timer.querySelectorAll('.nav-timer-dday');
    var timerId = null;

    function update() {
        var now = Date.now();
        var allEnded = true;

        ddayEls.forEach(function (el) {
            var idx = parseInt(el.getAttribute('data-dday'), 10);
            var diff = DEADLINES[idx].time - now;
            var unit = el.closest('.nav-timer-unit');

            if (diff <= 0) {
                el.textContent = '마감';
                if (unit) unit.classList.add('is-ended');
            } else {
                allEnded = false;
                var days = Math.floor(diff / 86400000);
                el.textContent = days > 0 ? ('D-' + days) : 'D-DAY';
                if (unit) unit.classList.remove('is-ended');
            }
        });

        if (allEnded && timerId) clearInterval(timerId);
    }

    update();
    timerId = setInterval(update, 1000 * 30); // 일 단위라 30초마다 갱신이면 충분
})();
