/* =========================================================
   AX Discovery — 사외교육 참가신청서 다운로드 / 인쇄
   - 워드(.doc) 다운로드: Word가 인식하는 HTML 기반 .doc
   - 인쇄/PDF 저장: 새 창에 렌더링 후 print()
   ========================================================= */
(function () {
    'use strict';

    // 신청서 본문 HTML (워드/인쇄 공용)
    function buildFormBody() {
        return [
            '<h1 class="doc-title">사외교육 참가신청서</h1>',

            '<h2 class="doc-h2">1. 수강자 정보</h2>',
            '<table class="doc-table">',
            '  <tr><th class="lbl">소속</th><td>&nbsp;</td><th class="lbl">직위</th><td>&nbsp;</td></tr>',
            '  <tr><th class="lbl">성명</th><td>&nbsp;</td><th class="lbl">담당업무</th><td>&nbsp;</td></tr>',
            '</table>',

            '<h2 class="doc-h2">2. 교육 상세 정보</h2>',
            '<table class="doc-table">',
            '  <tr><th class="lbl">교육과정명</th><td colspan="3">AX Discovery l AX 전략기획 전문가 2급 자격증 과정 (2기)</td></tr>',
            '  <tr><th class="lbl">교육기간 및 시간</th><td colspan="3">2026년 7월 18일(토) ~ 7월 19일(일) 10:00~18:00 (총 14시간)</td></tr>',
            '  <tr><th class="lbl">교육장소</th><td colspan="3">더와이랩 (서울 성동구 성수일로99, 1004호)</td></tr>',
            '  <tr><th class="lbl">주최기관</th><td colspan="3">주식회사 더와이랩</td></tr>',
            '  <tr><th class="lbl">자격증 정보</th><td colspan="3">AX 전략기획 전문가 2급 (과학기술정보통신부 정식 등록 민간자격: 2026-003156)</td></tr>',
            '  <tr><th class="lbl">교육목적</th><td colspan="3">단순 AI 도구 사용법 습득을 넘어, 조직의 페인포인트를 발굴하고 워크플로우를 해체하여 AI 에이전트로 조립하는 실전 역량(AX) 확보</td></tr>',
            '  <tr><th class="lbl">주요 커리큘럼</th><td colspan="3">' +
                '<strong>[DAY 1] 현업의 문제를 AX 과제로 구조화</strong><br>' +
                '- AX 인사이트 및 Quick Win 과제 선정<br>' +
                '- 리스크 진단, 보안 가이드라인 수립 및 AX 목표수립<br>' +
                '- 성과 정량화(ROI 목표 설정) 및 업무 해체/분해<br><br>' +
                '<strong>[DAY 2] 업무를 해체하고 AI 에이전트로 조립</strong><br>' +
                '- 데이터 전략 및 AI 전환 가능성 진단<br>' +
                '- AI 전환 Task 결정 및 TO-BE 워크플로우 재설계<br>' +
                '- AI 에이전트 프로토타입 만들기 및 해커톤 발표<br>' +
                '- <em>교육 종료 후 60분간 온라인 자격시험 진행</em>' +
                '</td></tr>',
            '  <tr><th class="lbl">교육비 (수강료)</th><td colspan="3"><strong>1,000,000원</strong> (2기 특별 할인가, VAT 포함)<br>' +
                '<span class="doc-small">※ 실습 강의 14시간, 실물 산출물 3종, 자격증 응시료 10만 원 포함</span></td></tr>',
            '  <tr><th class="lbl">신청사유 (예시)</th><td colspan="3">당사 업무 프로세스 중 발생하는 비효율적인 반복 업무를 분석하고, 데이터 기반의 우선순위 결정(IFV 매트릭스) 및 인간-AI 협업(HITL) 구조를 설계하여 부서의 실질적인 업무 생산성과 ROI를 향상시키고자 함.</td></tr>',
            '</table>',

            '<p class="doc-confirm">상기 사외교육의 참가 승인을 바랍니다.</p>',
            '<p class="doc-date">202&nbsp;&nbsp;년&nbsp;&nbsp;&nbsp;&nbsp;월&nbsp;&nbsp;&nbsp;&nbsp;일</p>',
            '<p class="doc-sign">신청인 : __________________ (인)</p>'
        ].join('\n');
    }

    // 워드/인쇄 공용 스타일
    function buildFormStyle() {
        return [
            'body { font-family: "맑은 고딕", "Malgun Gothic", sans-serif; color:#1a1a1a; line-height:1.6; padding:32px; }',
            '.doc-title { text-align:center; font-size:24px; font-weight:800; margin:0 0 28px; letter-spacing:2px; }',
            '.doc-h2 { font-size:15px; font-weight:700; margin:22px 0 8px; padding-left:8px; border-left:4px solid #0070f3; }',
            '.doc-table { width:100%; border-collapse:collapse; margin-bottom:8px; }',
            '.doc-table th, .doc-table td { border:1px solid #999; padding:8px 10px; font-size:12px; vertical-align:top; text-align:left; }',
            '.doc-table th.lbl { background:#f0f3f8; font-weight:700; width:130px; white-space:nowrap; }',
            '.doc-small { font-size:11px; color:#555; }',
            '.doc-confirm { text-align:center; margin:28px 0 8px; font-size:13px; }',
            '.doc-date { text-align:center; margin:18px 0 6px; font-size:13px; }',
            '.doc-sign { text-align:right; margin:6px 0 0; font-size:13px; }'
        ].join('\n');
    }

    // 워드(.doc) 다운로드
    function downloadWord() {
        var html =
            '<html xmlns:o="urn:schemas-microsoft-com:office:office" ' +
            'xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">' +
            '<head><meta charset="utf-8"><title>사외교육 참가신청서</title>' +
            '<style>' + buildFormStyle() + '</style></head>' +
            '<body>' + buildFormBody() + '</body></html>';

        var blob = new Blob(['\ufeff', html], { type: 'application/msword' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'AX_Discovery_2기_사외교육_참가신청서.doc';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    }

    // 인쇄 / PDF 저장
    function printForm() {
        var win = window.open('', '_blank', 'width=820,height=1000');
        if (!win) {
            alert('팝업이 차단되었습니다. 팝업을 허용한 뒤 다시 시도해 주세요.');
            return;
        }
        win.document.open();
        win.document.write(
            '<!DOCTYPE html><html lang="ko"><head><meta charset="utf-8">' +
            '<title>사외교육 참가신청서</title>' +
            '<style>' + buildFormStyle() +
            '@media print { body { padding:0; } @page { margin:18mm; } }' +
            '</style></head><body>' + buildFormBody() + '</body></html>'
        );
        win.document.close();
        win.focus();
        // 렌더링 후 인쇄 다이얼로그
        setTimeout(function () {
            win.print();
        }, 400);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var wordBtn = document.getElementById('appformWord');
        var printBtn = document.getElementById('appformPrint');
        if (wordBtn) wordBtn.addEventListener('click', downloadWord);
        if (printBtn) printBtn.addEventListener('click', printForm);
    });
})();
