# Phase 2 — 접근성·반응형·성능 정비 Implementation Plan

> **For agentic workers:** subagent-driven-development으로 task별 실행. 외형(색/폰트/로고/레이아웃)은 유지하고 품질만 개선한다(CLAUDE.md). git 브랜치에서 작업, task별 커밋.

**Goal:** 외형을 유지하면서 접근성(A)·반응형 버그(B)·성능(C)을 개선한다. (그룹 D 유지보수 구조는 이번 범위 제외.)

**Tech Stack:** 정적 HTML/CSS/Vanilla JS, jsdom 테스트(Node v24). 셸 PowerShell이나 **전역 문자열 치환은 BOM 유입 방지를 위해 git-bash(sed/perl) 또는 Edit 도구 사용** (PowerShell `Set-Content -Encoding utf8`은 BOM을 붙이므로 금지).

**범위 파일 수:** viewport maximum-scale 23개 html, Pretendard 폰트 link 22개 html, 공통 `shared/css/site.css`·`shared/js/site.js`, 폼 `contact/`·`apply/`, 루트 `index.html`(캐러셀 이미지). `AX_Discovery/` 제외.

**검증 공통:** 작업 후 `node --check shared/js/*.js`, `cd test && node --test`(전부 PASS), BOM 0건 확인(`head -c3`), 깨진 내부 링크 0건, `py -m http.server`로 육안.

---

## Task 1: 접근성 — 메뉴 오버레이 포커스 관리 (공통 JS)

**Files:** Modify `shared/js/site.js` (setMenu 영역, line 18-37); Test `test/menu.test.mjs` (new)

문제: 닫힌 오버레이가 `aria-hidden` 없이 CSS로만 숨겨져 SR이 읽고, 키보드 포커스가 빠져나가며, 닫을 때 트리거로 포커스 복귀 없음.

- [ ] **Step 1: 실패 테스트 작성** `test/menu.test.mjs`
```javascript
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
```

- [ ] **Step 2: 실행 → 실패 확인** `cd test && node --test` (aria-hidden 미설정으로 실패)

- [ ] **Step 3: site.js setMenu 수정** — `setMenu` 함수에 aria-hidden 토글, 열 때 첫 링크 포커스, 닫을 때 트리거 포커스 복귀, Esc(기존 유지), 간단 포커스 트랩(Tab 순환) 추가. 기존 코드(18-37) 교체:
```javascript
  var trigger = document.getElementById('menuTrigger');
  var overlay = document.getElementById('menuOverlay');
  var closeBtn = document.getElementById('menuClose');
  function focusables() {
    return overlay ? overlay.querySelectorAll('a[href], button:not([disabled])') : [];
  }
  function setMenu(open) {
    document.body.classList.toggle('menu-open', open);
    if (trigger) trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (overlay) {
      overlay.style.opacity = open ? '1' : '0';
      overlay.style.pointerEvents = open ? 'auto' : 'none';
      overlay.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
    if (open) {
      var f = focusables();
      if (f.length && f[0].focus) f[0].focus();
    } else if (trigger && trigger.focus) {
      trigger.focus();
    }
  }
  if (trigger && overlay) {
    setMenu(false);
    trigger.addEventListener('click', function () { setMenu(true); });
    if (closeBtn) closeBtn.addEventListener('click', function () { setMenu(false); });
    overlay.addEventListener('click', function (e) {
      if (e.target.tagName === 'A') setMenu(false);
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
```
> 주의: 기존 `setMenu(false)` 최초 호출이 트리거에 포커스를 주면 안 됨 → 초기화 시엔 포커스 이동을 건너뛰도록, 위 코드는 닫힘 시 트리거 포커스를 주지만 최초 `setMenu(false)`에서도 트리거로 감. 초기 로드 포커스 점프 방지를 위해 `setMenu` 첫 호출은 별도 처리: 최초엔 `overlay.setAttribute('aria-hidden','true')`와 스타일만 적용하고 포커스 이동 생략. 구현 시 `var inited=false;` 플래그로 첫 호출 시 포커스 스킵.

- [ ] **Step 4: 실행 → 통과** `cd test && node --test` (menu 3 + forms 6 = 9 PASS), `node --check shared/js/site.js`

- [ ] **Step 5: 커밋** `feat(a11y): menu overlay aria-hidden + focus trap/restore`

---

## Task 2: 접근성 — reduced-motion 크로스페이드 + 폼 라벨/fieldset

**Files:** Modify `shared/css/site.css`(reduced-motion 블록), `contact/index.html`, `apply/index.html`, `shared/js/forms.js`, `test/forms.test.mjs`

- [ ] **Step 1: CSS reduced-motion 보완** — `@media (prefers-reduced-motion: reduce)` 블록(있는 곳)에 추가:
```css
@media (prefers-reduced-motion: reduce) {
  .hb-slide { transition: none !important; }
}
```
(기존 reduced-motion 블록 끝에 한 줄 추가. 없으면 파일 끝에 신규 블록.)

- [ ] **Step 2: 폼 라벨 명시 연결 + fieldset** (contact, apply)
  - 각 `<input>/<textarea>/<select>`에 고유 `id` 부여, 라벨에 `for` 연결. (래핑 라벨이라 기능은 되나 명시 연결로 강화.) id 규칙: `f-<name>` (예 `f-name`, `f-email`; apply는 `f-region` 등).
  - 라디오/체크박스 그룹은 `<fieldset class="form-field">` + `<legend>`로 감싸기(기존 `<div class="form-field">`→`<fieldset>`, `<label>`(질문) → `<legend>`). 대상: contact 없음(라디오/체크박스 그룹은 동의 1개 체크박스뿐 → fieldset 불필요), apply의 성별(radio), 개인정보 동의(radio), 강의분야(checkbox), 강의스타일(checkbox), 출강기관(checkbox).
  - `.form-field`와 동일 스타일이 `fieldset.form-field`에도 적용되도록 CSS 셀렉터 보정 필요: site.css의 `.form-field` 규칙은 클래스 기반이라 `<fieldset class="form-field">`에 그대로 적용됨. 단 fieldset 기본 `border/margin/padding` 리셋 추가:
```css
fieldset.form-field { border: 0; margin: 0 0 22px; padding: 0; min-width: 0; }
fieldset.form-field > legend { display: block; font-weight: 700; font-size: 15px; margin-bottom: 8px; padding: 0; color: var(--ink); }
```
  - 오류 안내: 각 `.err-msg`에 `id="<fname>-err"` 부여하고 입력에 `aria-describedby` 연결(선택적 강화). 최소한 invalid 시 `aria-invalid="true"` 토글을 forms.js에 추가.

- [ ] **Step 3: forms.js — aria-invalid 토글** `setInvalid`에 추가:
```javascript
  function setInvalid(el, bad) {
    var f = el.closest('.form-field') || el.closest('.form-consent');
    if (!f) return;
    f.classList.toggle('invalid', !!bad);
    if (el.setAttribute) el.setAttribute('aria-invalid', bad ? 'true' : 'false');
  }
```
(체크박스 그룹은 group에 `classList`만, 개별 input aria-invalid는 생략 가능.)

- [ ] **Step 4: 검증** `cd test && node --test`(9 PASS 유지), 정적서버로 /contact/ /apply/ 라벨 클릭 시 입력 포커스 이동 확인, fieldset 렌더 확인.

- [ ] **Step 5: 커밋** `feat(a11y): explicit form labels, fieldset/legend, aria-invalid, reduced-motion`

---

## Task 3: 반응형 버그픽스 (CSS only)

**Files:** Modify `shared/css/site.css`

- [ ] **Step 1: 100dvh** — line 172 `.menu-inner { min-height: 100vh; }` → `min-height: 100dvh;` (fallback 위해 100vh 줄을 남기고 다음 줄에 dvh 추가가 안전):
```css
  min-height: 100vh;
  min-height: 100dvh;
```

- [ ] **Step 2: safe-area** — `.nav`(line 70~)의 좌우 패딩에 안전영역 반영. 기존 `.nav-inner` 패딩 확인 후, 노치 가로 영역 보호:
```css
.nav { padding-left: env(safe-area-inset-left); padding-right: env(safe-area-inset-right); }
```
(기존 .nav에 padding이 없고 .nav-inner가 패딩을 가지면 .nav-inner에 `max()`로 적용. 구현 시 현재 규칙 확인 후 외형 안 깨지게 추가.) scroll-progress 바가 하단 고정이면 `bottom: env(safe-area-inset-bottom)` 검토(상단 고정이면 불필요).

- [ ] **Step 3: check-grid 모바일** — `.check-grid`(220px minmax) 아래 초소형 화면 보정 추가:
```css
@media (max-width: 480px) {
  .check-grid { grid-template-columns: 1fr 1fr; gap: 8px 12px; }
  .check-item { font-size: 13px; }
}
```

- [ ] **Step 4: 검증** 정적서버로 좁은 뷰포트(개발자도구) 시뮬레이션 육안: 메뉴 풀높이, apply 체크박스 2열, 가로스크롤 없음.

- [ ] **Step 5: 커밋** `fix(responsive): 100dvh menu, safe-area nav, mobile check-grid`

---

## Task 4: 성능 — 폰트 비차단 로드 (전역 head, 22개 html)

**Files:** Modify Pretendard 폰트 link을 가진 모든 html(22개). **BOM 금지 → git-bash sed/perl 또는 Edit 사용.**

- [ ] **Step 1: 백업** 대상 파일 `.bak` (gitignored). (git 추적 중이라 백업은 선택; git revert 가능하므로 생략 가능.)

- [ ] **Step 2: 치환** — 두 변형(`...css" /` 와 `...css"/`)을 비차단 패턴으로. 각 파일에서 아래 한 줄을:
```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css"/>
```
다음으로 교체:
```html
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin/>
<link rel="preload" as="style" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" onload="this.onload=null;this.rel='stylesheet'"/>
<noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css"/></noscript>
```
sed 예(공백 변형 모두 처리, BOM 미유입):
```bash
for f in $(grep -rl "pretendard.min.css" --include="*.html" . | grep -v AX_Discovery | grep -v "\.bak"); do
  perl -0pi -e 's{<link rel="stylesheet" href="https://cdn\.jsdelivr\.net/gh/orioncactus/pretendard\@v1\.3\.9/dist/web/static/pretendard\.min\.css" ?/>}{<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin/>\n<link rel="preload" as="style" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard\@v1.3.9/dist/web/static/pretendard.min.css" onload="this.onload=null;this.rel=\x27stylesheet\x27"/>\n<noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard\@v1.3.9/dist/web/static/pretendard.min.css"/></noscript>}g' "$f"
done
```

- [ ] **Step 3: 검증** — `grep -c 'rel="preload" as="style"'` = 22; BOM 0건(`head -c3` 점검 루프); 정적서버로 한 페이지 폰트 정상 적용 육안.

- [ ] **Step 4: 커밋** `perf(font): non-blocking Pretendard load (preload+swap, noscript fallback)`

---

## Task 5: 성능/접근성 — viewport 줌 허용 + 캐러셀 이미지 (전역 + index) ✅ 완료 `f34dad2`
> 23개 html `maximum-scale` 제거(+`initial-scale=1.0→1`), index.html 41개 `loading="lazy"`(첫 슬라이드 eager). PowerShell 따옴표 함정 → Node로 교정. BOM 0·CRLF 보존.

**Files:** Modify viewport을 가진 html(23개), `index.html`(캐러셀 이미지). **BOM 금지.**

- [ ] **Step 1: viewport maximum-scale 제거** — 줌 허용. 세 변형 처리:
  - `width=device-width, initial-scale=1, maximum-scale=5` → `width=device-width, initial-scale=1`
  - `width=device-width, initial-scale=1.0, maximum-scale=1` (career-test) → `width=device-width, initial-scale=1`
```bash
for f in $(grep -rl "maximum-scale" --include="*.html" . | grep -v AX_Discovery | grep -v "\.bak"); do
  perl -pi -e 's/, ?maximum-scale=\d+(\.\d+)?//g; s/initial-scale=1\.0/initial-scale=1/g' "$f"
done
```
(`viewport-fit=cover`만 있고 maximum-scale 없는 페이지는 미대상 — 유지.)

- [ ] **Step 2: 캐러셀/배너 이미지 lazy + 크기** — `index.html`의 hero 배너 슬라이드 `<img>`: 첫 슬라이드 외 `loading="lazy"`, 가능하면 `width`/`height` 또는 `style="aspect-ratio:..."` 부여로 CLS 방지. (이미지 실제 비율 확인 후. 비율 불명 시 `loading="lazy"`만이라도 적용, 첫 슬라이드는 eager 유지.) 다른 페이지 hero 배너 이미지도 동일 원칙(선택).
  - 클라이언트 로고 그리드 이미지엔 이미 다수가 below-fold → `loading="lazy"` 추가(없으면).

- [ ] **Step 3: 검증** — `grep -c maximum-scale`(.bak 제외, AX 제외) = 0; BOM 0건; 정적서버로 핀치줌 가능 확인(데스크톱은 Ctrl+휠), 캐러셀 정상 표시.

- [ ] **Step 4: 커밋** `perf(a11y): allow zoom (remove maximum-scale); lazy-load carousel/logo images`

---

## Task 6: 통합 검증 ✅ 완료
> `node --check` OK, `node --test` 9 PASS, 전체 html BOM 0, 내부링크 0, `maximum-scale` 0, `preload as=style` 22. 추가로 Cafe24 배포 보강(루트 `.htaccess`, `api/.user.ini`, uploads/.htaccess 2.4호환, README) + PHP 8.3 lint/스모크.

- [ ] `node --check shared/js/site.js shared/js/forms.js` 통과
- [ ] `cd test && node --test` 전부 PASS (menu 3 + forms 6+)
- [ ] BOM 점검: 모든 html `head -c3` ≠ efbbbf
- [ ] 깨진 내부 링크 0건(이전 audit 스크립트 재사용)
- [ ] 잔여 `maximum-scale` 0건(AX 제외), `preload as=style` 22건
- [ ] `py -m http.server 8765`로 루트·mission·members-edu·contact·apply 육안: 외형 동일, 메뉴 키보드 동작, 폼 라벨 클릭

---

## Self-Review
- **A 커버**: 줌(T5), 메뉴 포커스/aria(T1), 폼 라벨·fieldset·aria-invalid(T2), reduced-motion(T2). lang/alt 저우선은 범위서 보류(외형·기능 영향 적음, 필요시 후속).
- **B 커버**: 100dvh(T3), safe-area(T3), check-grid(T3).
- **C 커버**: 폰트 비차단(T4), 이미지 lazy/CLS(T5), site.js 조기 return 폴백 → **누락**. 아래 T1에 미포함.
- **수정**: site.js 조기 return(line 176, IntersectionObserver 미지원 시 scroll-progress/magnetic/reveal 스킵) 폴백을 Task 1 Step 3 범위에 포함하거나 별도 작게. → Task 1에 Step 3b로 추가: IO 미지원 시 `.reveal` 즉시 visible 처리하고 scroll-progress/magnetic 블록을 IO 가드 밖으로 이동. (구형 브라우저 한정 영향이라 우선순위 낮음, 시간 허용 시 적용.)
