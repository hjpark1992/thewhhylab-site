# CLAUDE.md — 웹사이트 리뉴얼 작업 가이드

> 사용법: 이 파일을 리뉴얼할 **프로젝트 폴더 루트에 `CLAUDE.md`로 복사**해 두면,
> Claude Code가 그 폴더에서 세션을 시작할 때 자동으로 읽습니다.

## 프로젝트 성격
- 정적 웹사이트(HTML / CSS / Vanilla JS) 리뉴얼이 기본. 빌드 도구·프레임워크 없음 가정.
- 한국어로 소통한다.

## 작업 방식 (반드시 이 순서)
1. **먼저 폴더의 모든 HTML/CSS/JS를 읽고 구조를 파악**한다. 추측하지 말 것.
2. 큰 변경 전에는 **개선 포인트를 3가지로 제안하고 승인을 받는다.** 바로 갈아엎지 않는다.
3. **시각 디자인 변경 여부를 명확히 구분**한다.
   - 별도 요청이 없으면 **색/폰트/로고/레이아웃 등 외형은 유지**하고 구조만 개선.
   - "디자인 올려줘"라고 하면 브랜드 정체성(주요 컬러 등)은 지키며 세련되게 다듬는다.
4. **수술적 변경**: 요청과 무관한 코드·포맷은 건드리지 않는다. 기존 스타일/패턴을 따른다.
5. 큰 변경 전 **원본을 `*.bak`로 백업**하고, 작업이 안정되면 정리 여부를 묻는다.

## 리뉴얼 시 챙길 기술 기준
- **반응형**: 고정폭 모바일 전용이 아니라 fluid. 모바일은 `100dvh`(주소창 잘림 방지),
  넓은 화면은 `grid`/`container query`로 단(column) 전개. 고정 푸터는 `env(safe-area-inset-bottom)`.
- **라우팅/상태**: 단계형 화면은 History API(해시) 라우팅으로 뒤로가기·새로고침·딥링크 지원,
  진행 상태는 `sessionStorage` 복원.
- **접근성**: 의미 있는 `role`/`aria-*`, 키보드 이동(`focus-visible`, roving tabindex),
  화면 전환은 `aria-live`로 안내.
- **렌더링**: 거대한 단일 `innerHTML` 문자열 대신 작은 헬퍼로 분리하고, 사용자 입력은 escape.
- **성능**: 장식용 폰트는 렌더 비차단 로드.

## 검증 (완료라고 말하기 전에)
- `node --check`로 JS 문법 확인.
- 가능하면 **jsdom 스모크 테스트**로 핵심 플로우를 검증한다.
  중요: DOM 존재뿐 아니라 **실제 가시성(opacity 등 클래스 적용)**까지 확인할 것.
- 변경 후 **로컬 정적 서버**로 띄워 육안 확인을 권한다.

## 환경 메모 (Windows)
- 셸은 PowerShell. `python`은 스토어 스텁이라 안 됨 → **`py -m http.server`** 사용.
- 정적 서버 예: `py -m http.server 8765 --bind 127.0.0.1` → http://127.0.0.1:8765/

---

# 작업 인수인계 (2026-06-05, 집에서 이어서 작업)

> 회사 → 집 이동 핸드오프. 작업 브랜치: **`feature/phase2-a11y-responsive-perf`**
> 전체 계획서: `docs/superpowers/plans/2026-06-05-phase2-a11y-responsive-perf.md` (T5의 상세 perl 명령 포함)

## 🏠 집에서 첫 메시지로 이렇게 말하면 됨 (복사해서 붙여넣기)

```
thewhhylab 폴더에서 작업 이어서 할게.
먼저 환경 점검부터 해줘: node·py·perl·git 설치 여부와 버전, .git 폴더가 따라왔는지(git log -3),
현재 브랜치가 feature/phase2-a11y-responsive-perf 인지, cd test && node --test 가 9 PASS 인지 확인하고
결과를 알려줘. 없거나 막히는 게 있으면 멈추고 물어봐.
그 다음 CLAUDE.md 하단 "작업 인수인계" 섹션을 읽고,
남은 작업 T5(maximum-scale 제거 + 이미지 lazy)와 T6(통합 검증)를 이어서 진행해줘.
전역 치환 후에는 BOM·줄바꿈(CRLF) 통일 점검까지 꼭 해줘.
```

> 환경 점검 통과 기준: `node`(테스트·검증), `py`(로컬 서버), `perl`(전역 치환; 보통 Git for Windows 포함), `git` 모두 있어야 함.
> 하나라도 없으면 그 단계에서 막힘 → Claude가 대안(예: node 없이 육안 검증) 안내하도록 "물어봐"를 넣어둠.

> 더 짧게: **"CLAUDE.md 인수인계 보고 T5부터 이어서 해줘"** 한 줄이면 충분.
> (이 CLAUDE.md는 세션 시작 시 자동으로 읽힘.)

## 집에서 시작할 때 먼저 할 것
1. **git 사용자 설정 확인** — 회사 PC에선 비어 있어 로컬로 설정했음(`thewhhylab` / `hjpark1992@naver.com`). 폴더째 복사하면 `.git/config`에 같이 따라오지만, 새로 clone했다면 다시 설정:
   `git config user.name "thewhhylab"; git config user.email "hjpark1992@naver.com"`
2. **브랜치 확인** `git branch --show-current` → `feature/phase2-a11y-responsive-perf` 인지.
3. **테스트 통과 확인** `cd test && node --test` → **9 PASS** 여야 함(menu 3 + forms 6).
4. 로컬 서버 `py -m http.server 8765 --bind 127.0.0.1` 로 `/contact/` `/apply/` 육안.

> ⚠️ **환경 메모(2026-06-05 집 PC):** 집 PC엔 `git`·`py`·`perl`·`php`가 **미설치** 상태였음 →
> winget으로 설치 완료: **Git for Windows 2.54**(git+perl 5.42 동시), **Python 3.12**(py 런처), **PHP 8.3**(api lint/스모크용).
> 설치 직후엔 부모 셸이 옛 PATH를 캐시하므로, 새 터미널을 켜거나 명령 앞에 레지스트리 PATH를 다시 읽어 사용.
> perl은 Git 번들이라 PATH에 `C:\Program Files\Git\usr\bin` 추가 필요.

## ✅ 완료 (커밋됨)
- **T1** 메뉴 접근성 — `9e43054` (aria-hidden 토글, 포커스 트랩/복귀, IO 폴백)
- **T2 + 폼 장식 + 문구** — `9dcaa1f`
  - 모든 입력 `label[for]/id` 명시 연결, 라디오·체크박스 그룹 `fieldset/legend`, `aria-invalid` 토글
  - "기타" 선택 시 입력칸 토글(`initEtc`), 강의분야 **박스(칩) 그리드**
  - 폼 페이지 **은은한 장식**: 배경 블롭/도트 + eyebrow 칩 + PC 사이드 캐릭터(`≤1180px` 숨김). CSS는 `site.css` 끝 `/* 폼 페이지 장식 ... */` 블록
  - 문구: "본 설문은"→"해당 페이지는", 강사모집 소요시간 문구 제거
- **T3** 반응형 — `2b46db0` (메뉴 `100dvh`, nav `safe-area` 패딩, 모바일 `check-grid` 2열 `@max-width:480px`)
- **T4** 폰트 비차단 로드 — `e3f014e` (Pretendard `preconnect+preload(swap)+noscript`, **22개 페이지**)
  - ⚠️ perl 전역치환이 추가 줄만 LF로 만들어 CRLF와 섞였던 것 → 바이너리 모드로 **CRLF 통일 완료**, BOM 0건 확인.
- **T5** 줌 허용 + 이미지 lazy — `f34dad2`
  - `maximum-scale` 23개 html 제거(`initial-scale=1.0→1` 정규화) → 핀치줌 허용.
  - `index.html` 캐러셀 슬라이드 2~5 + 클라이언트 로고 **41개 `loading="lazy"`**(첫 슬라이드 ax-cover는 eager).
  - ⚠️ **PowerShell 5.1 따옴표 함정**: 작은따옴표 안의 `"`를 native 인자로 넘길 때 제거됨 → perl이 `loading=lazy`(따옴표 없이) 삽입 → Node로 `loading="lazy"` 교정. **이후 전역치환은 Node 권장**(따옴표 안전·CRLF/BOM 보존).
  - git `core.autocrlf=true` 확인: 작업트리 CRLF / 커밋 blob LF(정상).
- **T6** 통합 검증 — 전부 PASS (`node --check` OK, `node --test` 9 PASS, 전체 html BOM 0, 내부링크 0, `maximum-scale` 0, `preload as=style` 22).
- **Cafe24 배포 보강** (T5/T6 후속, 같은 커밋 흐름)
  - 루트 `.htaccess` 신규: gzip(mod_deflate)+캐시(mod_expires)+보안 파일차단(config.php/.bak/.sql/.ini/dotfiles)+보안헤더. **전부 `<IfModule>` 가드**(모듈 없으면 무시→500 방지). HTTPS 강제는 SSL 적용 후 주석 해제.
  - `api/.user.ini` 신규: `upload_max_filesize=12M`/`post_max_size=13M`(10MB 업로드 대응; CGI/FastCGI 전용, mod_php면 패널에서 직접).
  - `api/uploads/.htaccess`: Apache 2.4(`Require all denied`)+2.2 폴백.
  - `api/README.md`: Cafe24 배포 가이드 전면 보강(PHP 7.1+ 선택, 업로드 한도, SSL, mail()/SPF, 업로드 제외목록).
  - PHP 8.3에서 api 5개 `php -l` 통과 + `php -S` 스모크(GET 405 / 허니팟 200 / 3초미만 400 / 정상값 DB도달) 확인.

## 결정 사항 / 보류 (다시 묻지 말 것)
- **파일 업로드 10MB 유지** — 20MB는 서버 PHP(`upload_max_filesize`/`post_max_size`)도 같이 고쳐야 해서 보류. `api/config.sample.php`·`api/lib/validate.php`는 10MB 그대로. (`api/.user.ini`가 10MB용 PHP 한도를 12M로 올려둠.)
- **배포(Cafe24 웹호스팅)** — `api/README.md` 참고. 폴더째 `/www/`에 업로드(단 `.git/`·`test/`·`docs/`·`node_modules/` 제외). 서버에서 `api/config.php` 생성 + `db/schema.sql` 임포트 필수. **PHP 버전 7.1+ 선택** 필수(기본 5.x면 폼 미동작). SSL 적용 후 루트 `.htaccess` HTTPS 블록 주석 해제.
- **도메인 (2026-06-06 확정)** — 메인 사이트 canonical은 **`https://www.thewhhylab.com`**(www 포함; robots/sitemap/canonical/OG/JSON-LD 전부 이 기준). 이전에 쓰던 `thewhhylab.ai.kr`은 **AX_Discovery 전용 서브서버**일 뿐이라 메인 SEO 기준에서 제외 → `.ai.kr`→`www.thewhhylab.com` 122곳 이전 완료(Node, AX_Discovery 링크 46곳만 negative-lookahead로 유지). mail_from도 `master@thewhhylab.com`. career-test CTA(`www.thewhhylab.com/career-test/`)는 그대로. SSL 두 도메인 모두 발급 완료.
- 장식 강도/캐릭터 위치는 "은은한 장식 + 사이드 캐릭터"로 확정.

## 진행 현황 (2026-06-05 집에서 이어서 완료)
- **T1~T6 전부 완료/커밋**, Cafe24 배포 보강까지 반영. Phase 2(접근성·반응형·성능) 작업 목표 달성.
- **SEO 보강** `0ecec2b`: index.html JSON-LD(Organization+WebSite), sitemap 19→24 URL(+lastmod), career-test 메타 완비(+제목 정정). 기존 페이지들은 이미 title/desc/canonical/OG/twitter 구비 상태였음.
- **홈페이지 결함 수정** `0113aae`(독립 서브에이전트 리뷰 기반): GROWTH 섹션 div 불균형(각주 그리드 갇힘) 복원, heroVideo 빈 poster 제거, 메뉴 링크 `closest('a')` 닫힘 보정. div 균형 163=163.
- **모바일 최적화 (2026-06-06)** — Playwright(Chromium)로 24페이지를 390px 모바일 뷰포트 실측 렌더링+스크린샷 검사. 진단: 가로 오버플로는 0건이나 **데스크톱용 고정값이 모바일에 그대로 적용돼 여백·빈공간 과다**.
  - `8114031`: 공유 CSS — `.section`(96→52/64px)·`.p-sec`(120→52/76px) 모바일 세로여백 축소(`.p-sec`는 모바일 오버라이드가 아예 없었음=서브페이지 늘어짐 주범), `.service-card`/`.bpo-card` 고정 `min-height:280px` 제거→내용 기반, `.p-card` 패딩 축소. site.css 끝 "모바일 정돈/최적화" 블록.
  - `44fa48b`: ai-hrd 등식 3카드(inline `grid 1fr auto 1fr auto 1fr`)가 모바일에서 cramped → `.eq3`/`.eq3-op` 클래스로 세로 스택(`!important`).
  - 커스텀 inline 레이아웃 페이지(mission/learnies/workshop-tools/AX_Discovery)는 자체 @media로 이미 모바일 양호 — 미변경. 오버플로 회귀 0건 확인.
- 남은 것은 **사용자 직접**: ① 로컬/실서버 육안 확인(특히 GROWTH 각주 위치, 모바일 정돈) ② Cafe24 배포(PHP버전·config.php·DB·SSL) ③ 브랜치 머지(`main`).
- 미적용(판단 보류): career-test 로컬판이 외부 `.com`판과 중복일 수 있음(현재 self-canonical+sitemap 포함 처리). 구형 브라우저용 그라데이션 텍스트 fallback color는 외형 영향이라 보류. 모바일은 핵심 공유 레이아웃 위주로 정돈했고, 개별 페이지의 미세 튜닝은 사용자 확인 후 추가 가능.

## 작업 기록 (2026-06-08)
- **`/admin` 단축 경로 추가** — 관리자 페이지가 `/api/admin.php`로만 들어가지던 것을 `.htaccess`에 `RewriteRule ^admin/?$ api/admin.php [L]` 추가(HTTPS 강제 블록 내, 조건부 HTTPS 규칙 뒤 → 루프 없음). 이제 `thewhhylab.com/admin`으로 접속 가능. admin.php 6번 줄 주석엔 `/admin` 의도가 적혀 있었으나 실제 규칙은 누락 상태였음. ⚠️ **Cafe24 실서버에 `.htaccess` 재업로드해야 실제 작동**(현재 서버엔 미반영).
- **git 저장소 재초기화** — 기존 `.git` object DB 광범위 손상(loose object 633개 중 `git fsck` corrupt 845줄, packfile·원격 없음, index도 `0x72650000` 손상) → 이력 복구 불가. 원인 추정: 회사→집 폴더 이동 시 바이너리 git 객체가 텍스트(CRLF) 변환으로 깨짐(ref 끝 `0d0a`). 작업 트리 파일은 전부 무손상.
  - 손상본은 **`.git_corrupt_backup/`** 로 rename 보관(삭제 안 함, `.gitignore`에 추가). 회사 PC 원본 `.git`이 멀쩡하면 추후 병합 가능(옮길 땐 **반드시 zip 압축** 후 전송 — 또 깨짐 방지).
  - 새 `git init -b main` + user(`thewhhylab`/`hjpark1992@naver.com`) 재설정 + 현재 파일 전체 첫 커밋(`e1f6257`, 228파일). `api/config.php` 등 비밀파일 추적 제외 확인.
- **검증**: `node --test` 9 PASS(menu 3 + forms 6), shared/js `node --check` OK, `.htaccess` BOM 0건. (PHP는 이 세션 PATH 미탑재 → admin.php는 PHP 코드 미변경이라 lint 생략.)
- **남은 것(사용자 직접)**: Cafe24에 `.htaccess` 재업로드해야 `/admin` 작동. git 이력은 새출발(과거 커밋 해시는 위 기록의 것으로만 참조 가능).

### 관리자 페이지 기능 추가 (2026-06-08, 같은 날 후속)
- **디스크 사용량 게이지 + 선택 삭제** `311fbc1` (api/admin.php)
  - 게이지: 문서루트(`dirname(__DIR__)`=/www) 재귀 합산 ÷ **10GB**(`$DISK_LIMIT` 상수). 첨부폴더(`api/uploads`) 용량 별도 표기. 70%↑주황/90%↑빨강. 전체 렌더 시에만 계산(AJAX·다운로드는 조기 exit). Cafe24 계정 전체(메일·DB 포함)와는 근사치.
  - 선택 삭제: 교육문의/강사지원 체크 → 삭제. **DB행 + 첨부파일(unlink) + admin_memos 동반 삭제**로 실제 용량 회수. PRG 리다이렉트, 강한 확인창, PDO prepared + tbl_of 화이트리스트 + safe_upload_path 검증.
- **로그인 보안 강화** `0557708` (api/admin.php, config.sample.php)
  - **아이디 + 비번**: `admin_user` 추가(기존 `admin_pass`와 함께 hash_equals). **⚠️ 서버 `api/config.php`에 `'admin_user' => '원하는아이디'` 추가 안 하면 로그인 차단됨**(현재 서버 config엔 admin_pass만 있음).
  - **시도 잠금**: IP별 5회 실패 → 10분 잠금(`sys_get_temp_dir()/twl_admin_login.json` 파일 기반). 남은 시도 안내, 잠금 중엔 정상 비번도 차단.
  - **세션 자동 로그아웃**: 비활성 기본 30분(`admin_session_timeout` 초 단위로 조정). 로그인 성공 시 `session_regenerate_id`.
- **검증 도구**: 집 PC에 **PHP 8.3.31 설치**(winget `PHP.PHP.8.3`; php.exe = `%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_*\php.exe`, 새 셸은 PATH에 `php` 별칭). `php -l` api 6파일 전부 통과 + `php -S` 격리환경(임시폴더 admin.php 사본+테스트 config) 스모크 테스트로 로그인/302/5회잠금/잠금중차단/세션타임아웃/타임아웃전유지 **전부 PASS**.
- **⚠️ 배포 시 필수(api/admin.php 올릴 때)**:
  1. 서버 `api/config.php`에 **`admin_user` 키 추가** (없으면 로그인 불가).
  2. (선택) `admin_session_timeout` 조정. 미설정 시 1800초(30분).
  3. 잠금 파일은 서버 임시폴더에 자동 생성됨(쓰기 권한 필요 — 보통 OK).
  4. 게이지가 0/비정상이면 `open_basedir`/권한 문제 → 확인.
- **로그인 화면 로고** `4935b9a` (api/admin.php): 플레이스홀더 'W' → 실제 로고(`/shared/img/logo-blue.png` 절대경로) 적용. h1 '관리자 로그인'. Edge 헤드리스 스크린샷으로 어두운 배경에서 육안 확인 완료.

---

## ▶ 이전 세션 시작점 (2026-06-08 — 관리자 페이지 배포건. 최신 시작점은 문서 맨 끝)

**상태:** 코드·문서 전부 커밋(작업트리 깨끗, HEAD `4935b9a`). 집 PC에 PHP 8.3 설치돼 있어 로컬 검증 가능(새 터미널에서 `php` 바로 됨).

**남은 일 = 사용자 직접 배포(아직 서버 미반영).** FTP로 Cafe24 `/www/`에:
1. `api/admin.php` → `/www/api/admin.php` 덮어쓰기 — **로고 + 용량게이지 + 선택삭제 + 로그인보안** 전부 이 한 파일에 들어있음.
2. `api/config.php` → `/www/api/config.php` 업로드 — `admin_user` 사용자가 채워둠(완성본). DB·비번 포함된 실파일이라 그대로 올리면 됨. (git 비추적이라 로컬에만 있음)
3. `.htaccess` → `/www/.htaccess` (이전에 올렸으면 생략, `/admin` 단축경로).
4. 배포 후 확인: `thewhhylab.com/admin` 로고 표시 + 아이디·비번 로그인 → 용량게이지 표시 → **테스트로 1건 삭제**해서 첨부·용량 줄어드는지.

**다음에 추가하기로 검토했던 관리자 기능(사용자가 고르면 진행):**
- 일괄 상태 변경(여러 건 한번에 '답변완료') ← 1순위 후보, 선택삭제와 짝
- 기간 필터 + 간단 통계(오늘/주/월 건수, 상태별 카운트)
- 컬럼 정렬 + 페이지네이션(현재 최근 300건만 표시)

**결정/보류:** 로그인 잠금파일은 `/tmp` 자동만료(10분)가 업계 표준이라 **위치 이동 안 하기로 결정**(IP기준이라 진짜 관리자 DoS 위험도 낮음). 잠기면 10분 대기.

---

## 작업 기록 (2026-09-08) — AX_Discovery 상세페이지 내재화 + 메뉴 개편

> 다른 직원이 별도 프로젝트로 만든 `AX_Discovery/`(웹+A4 PDF 겸용 1파일 구조)를
> 회사 사이트 하위페이지 결에 맞추고, 메인 사이트의 외부링크를 내부 페이지로 전환.
> **서버 배포까지 완료. 라이브 검증 통과.**

### 무엇을 했나
- **상·하단 교체** (`AX_Discovery/index.html`): 자체 헤더(로고+앵커nav+PDF버튼) → 회사 공통 `.nav` + 전체화면 `.menu-overlay`, 히어로에 `.p-crumb` 브레드크럼, 하단에 공통 `.site-foot` 추가. `shared/css/site.css`는 **`media="screen"`**, `shared/js/site.js` 연결. 새 요소 전부 `web-only` → **PDF 출력 경로 무손상**.
- **PDF 저장**: 촌스러운 검정 버튼 대신 브레드크럼 줄 오른쪽 끝의 조용한 텍스트 액션(`.pdf-save`). `scripts/main.js`가 존재하지 않는 `#print-button`을 찾던 기존 버그를 `[data-print-button]`으로 교정(원래 눌러도 동작 안 했음).
- **메뉴 개편** (25개 페이지 = 메인 24 + AX 자체): AI HRD 열이
  `AX 프로그램`(내부 `/AX_Discovery/`) → `AX 전략기획 전문가 2급 자격증 과정`(외부 `https://www.thewhhylab.ai.kr/AX_Discovery/` ↗) 순. 푸터 '프로그램' 열도 동일. 총 50곳.

### ⚠️ 가장 중요 — `shared/css/site.css`의 전역 자간 리셋 함정
`site.css` **2525행**에 `*, *::before, *::after { letter-spacing: 0 !important; }` 가 있다.
`!important` + 전체선택자라 **자체 자간 설계를 가진 하위 프로젝트를 통째로 망가뜨린다.**
AX는 자간이 A4 레이아웃에 직결돼 있어서, 이게 걸리자 h1이 `AI로 일하는 방식` / `을` 로 줄이 밀렸다.

- **처음엔 site.css를 고쳤다가 되돌렸다.** (`:not(.document, .document *)` 예외) → 공유 CSS를 반드시 업로드해야만 동작해서, 실제로 AX 폴더만 올라간 상태에서 라이브가 깨졌다.
- **최종 해법 = AX 자체 CSS 안에서 해결** (`styles/screen.css` 상단 보정 블록):
  1. `.document *, .document *::before/after { letter-spacing: inherit !important; }` — 전역 리셋이 `<span>` 등 자식을 **직접** 잡아 상속을 끊으므로 상속부터 복구
  2. AX 자체 선언 **35개**를 원래 값 그대로 `!important` 재선언
  - **`shared/css/site.css`는 손대지 않은 원본 그대로**(서버와 해시 일치 확인). 하위 프로젝트가 자기 안에서 자립하는 구조.

### 그 외 site.css ↔ AX CSS 충돌 (전부 `styles/screen.css` 보정 블록에서 처리)
- `h1,h2,h3 { text-wrap: balance }` → AX 제목의 의도된 `<br>` 줄바꿈이 깨짐 → `.document h1~h3 { text-wrap: wrap }`
- `.eyebrow` / `.section-heading` **클래스 동명 충돌** — site.css가 장식 의사요소(대시선·파란 바)와 여백을 덧씌워 '차별성' 섹션이 +144px 늘어남 → AX가 직접 선언 안 한 속성만 되돌림(`::before { content: none }` 포함)
- `body` 전역 타이포 — AX는 Pretendard **Variable**(CDN dynamic-subset)인데 site.css가 static `Pretendard`로 덮어써 폰트 폴백 위험 → body 폰트 스택 복원 + `.document { line-height: normal; font-feature-settings: normal }`

### ⚠️ 소스 서식 관례가 다르다 (붙여넣기 사고 주의)
- **메인 사이트 html**: CRLF + 한 줄 압축 서식(메뉴 오버레이가 통째로 한 줄)
- **AX_Discovery**: LF + 2칸 중첩 들여쓰기, 최장 줄 307자
- 회사 헤더/푸터를 그대로 복사해 넣었더니 **2,374자짜리 한 줄**이 생겨 에디터에서 "폭이 다르고 다 밀려 보인다"는 지적을 받았다. → AX 관례대로 재서식(한 요소 한 줄, 2칸 중첩, `<br />` 표기). **렌더링 무변화는 실측으로 확인.**
- 전역 치환/삽입 후엔 **BOM·CRLF 혼합 점검 필수**(site.css에 LF 3줄 섞였던 것 발견·수정).

### 검증 방법 (재사용 권장)
- **원본 대비 기하 대조가 핵심.** `index.html.bak`을 임시로 되돌려 렌더 → 현재본 렌더 → `.document *` 전 요소의 `x/width/height/줄수`를 문자열로 뽑아 1:1 비교. 히어로 외 차이 0이면 안전.
- 웹 9개 폭(390·768·1024·1280·1366·1512·1600·1920·2560) + `emulateMedia({media:'print'})` 인쇄 기하 + `page.pdf()` 페이지 수(11p)까지 비교.
- **Playwright 경로 주의**: `AX_Discovery/node_modules`는 pnpm 심볼릭 링크가 깨져 있어 `require('playwright')` 실패.
  `AX_Discovery/node_modules/.pnpm/playwright-core@1.62.1/node_modules/playwright-core` 를 **직접 경로로** require 할 것. 브라우저는 `~/AppData/Local/ms-playwright`에 이미 설치돼 있음.
- 로컬 서버: `py -m http.server 8766 --bind 127.0.0.1`

### 배포 (완료)
- **`_Upload/` 폴더 방식** — 서버 루트(`/www/`) 구조 그대로 필요한 파일만 담아 드래그앤드랍. `.gitignore`에 등록(파생 산출물).
  - ⚠️ `_Upload`는 **반드시 프로젝트 루트에** 둘 것. 처음에 `AX_Discovery/` 안에 만들었다가, 그 폴더째 올릴 때 사이트 사본이 딸려 올라가는 위험이 있어 옮겼다.
- 최종 37개 / 1.89MB = AX_Discovery 13개(참조되는 asset 8개만; fonts·qr·source는 미사용) + 메인 html 24개. **`shared/css/site.css`는 불필요**(서버와 동일).
- **덮어쓰기 안전성 확인법**: 서버에서 `curl`로 받아 `git show HEAD:파일`과 해시 대조 → 서버에만 있는 변경이 없음을 증명한 뒤 올렸다. (24개 중 23개 완전 일치, index.html은 이미 올린 내 수정본)
- **라이브 검증 통과**: 37개 파일 서버=로컬 바이트 일치, AX 페이지 h1 3줄·자간 `-4.1472px`, 25개 페이지 AX 링크 4개(내부2+외부2) 정상, 가로오버플로 0, JS오류·404 0건.

### 결정 사항 / 남은 것
- **ai.kr은 계속 살린다** — 메뉴에서 `AX 전략기획 전문가 2급 자격증 과정`으로 별도 노출. (해당 페이지 실제 title도 "AX Discovery | AX 전략기획 전문가 2급 자격증 과정")
- **미적용(사용자 판단 대기)**:
  1. 홈 CTA 2곳이 아직 구 주소(`thewhhylab.ai.kr`, www 없음) — [index.html:338] 4기 배너 "수강 신청하기"(신청 폼이 ai.kr에 있어 보류), [index.html:482] `03 · AX DISCOVERY` 타일(내부로 보내는 게 자연스러움)
  2. AX 브레드크럼이 `Home / AI HRD / AX DISCOVERY` — 메뉴는 "AX 프로그램"이라 불일치
  3. `references/index.html` 본문의 "AX Discovery" 문구(메뉴 아니라 유지)
  4. AX 페이지에 canonical/OG/sitemap 등재 없음(favicon만 추가함)
  5. AX 마지막 섹션 문의처(`thewhhylab@naver.com`)와 공통 푸터(`master@thewhhylab.com`) 불일치
- **`AX_Discovery/` 폴더 정리 필요**: 중첩 `.git`(91M), `node_modules`(25M), `tmp`(34M), `output`(4.4M) — 배포 대상 아님. 중첩 `.git`은 메인 저장소 커밋 시 문제됨.
- 서버 구버전 잔재(`HANDOVER.md` 등 25개) 삭제 여부 미확인 → `thewhhylab.com/AX_Discovery/HANDOVER.md` 로 확인 가능.

---

## ▶ 다음 세션 시작점 (2026-09-08 저장 — 여기서 이어서)

**상태:** AX_Discovery 작업 **배포까지 완료, 라이브 검증 통과**. 단 **아직 커밋 안 함**(작업트리에 25개 html + .gitignore 변경분이 그대로 있음).

**바로 할 수 있는 것:**
1. **커밋** — `git add` 후 커밋. 변경분: AX_Discovery/index.html·styles/screen.css·scripts/main.js, 메인 24개 html, .gitignore, CLAUDE.md. (`_Upload/`는 ignore됨)
2. **백업본 정리** — `AX_Discovery/index.html.bak`, `AX_Discovery/styles/screen.css.bak`, `shared/css/site.css.bak`, `CLAUDE.md.bak`. (`*.bak`은 ignore라 커밋엔 안 들어감)
3. 위 "미적용" 1~5번 중 사용자가 고르는 것.
4. `AX_Discovery/` 폴더 정리(중첩 `.git`·`node_modules`·`tmp`·`output`).
---

## 작업 기록 (2026-10-28) — AX 프로그램(ax-programs) 하위페이지 추가 + AI HRD 메뉴 개편

> 직원이 사이트 사본을 바탕으로 만든 `ax-programs/`(AX 전체 프로그램 안내 + AX_Alignment 상세) 전달본을 메인 사이트에 통합.
> 전달본 원본: `C:/Users/parkh/claude-projects/ax-programs` + `README_홈페이지_업로드_안내_20261028.md`(프로젝트 상위 폴더).

### 무엇을 했나
- **9/11 배포분 사후 커밋** `552d148` — 홈 히어로 슬라이드 6개 개편·exp-banner CSS 제거·site.js 체류시간. 실서버와 바이트 일치 확인 후 그대로 기록(작업트리만 있고 커밋이 빠져 있었음).
- **`ax-programs/` 프로젝트 루트에 추가**(7파일 125KB, LF 관례 유지). 직원분 파일이 AX_Discovery 선례(site.css `media=screen`, 자간 리셋 `!important` 방어, `text-wrap:initial`, Pretendard Variable)를 그대로 따르고 있어 공유 CSS/JS 수정 없이 붙음.
  - 수정한 것: 자기 명칭 "AX 전체 프로그램"→**"AX 프로그램"**(title·메뉴·브레드크럼·푸터), title 꼬리 "| THE WHHY LAB"→"· 더와이랩", **내부 링크 `target=_blank` 제거**(Alignment/Discovery/AX 프로그램 버튼, app.js의 Discovery 상세보기 — 외부 ai.kr은 유지), AX_Alignment 페이지의 구버전 메뉴·푸터 동기화(탐구 위인 테스트·Naver blog·러니즈 추가), `styles.css` `.ax-dot{animation:none}`(홈 배너용 site.css 깜빡임 상속 차단).
- **AI HRD 메뉴 개편 — 26개 html**(메인 24 + 홈 + AX_Discovery): 메뉴 오버레이는 `AX 프로그램(/ax-programs/) → AX Discovery(/AX_Discovery/)` **2줄**(1차엔 AX Alignment 포함 3줄이었다가 아래 결정대로 메뉴에서만 제거). 푸터 '프로그램' 열은 `AX 프로그램 → AX Alignment → AX Discovery` **3줄 유지**. 자격증 과정(ai.kr ↗)은 그 아래 그대로. Node 문자열 치환(`scratchpad/apply-menu.js` → `remove-alignment-menu.js`)으로 CRLF/BOM 보존, 파일별 기대 횟수 assert.
- **홈 히어로 슬라이드 1은 미변경** — 화면 문구가 이미 "AX DISCOVERY · 기업 교육 프로그램"이고 CTA가 /AX_Discovery/라 그대로 맞음(HTML 주석만 "AX 프로그램").

### 검증(전부 PASS)
- `node --check` 4파일, `node --test` 9 PASS, 메인 html BOM 0·CRLF 통일, ax-programs LF 통일, 내부 href 1,121개 깨짐 0.
- Playwright 27페이지 × 390/1280px: 콘솔오류 0·404 0·가로오버플로 0, AI HRD 메뉴·푸터 순서 27/27 일치, current 표시 정상. (career-test는 원래 공통 메뉴 없음.)
- ax-programs 단독: 390/768/1024/1280/1920 렌더 + 인쇄 PDF(전체 13p, Alignment 5p).
- 덮어쓰기 안전성: 서버 25개 html이 커밋본(HEAD)과 정규화 후 일치 → 서버만의 변경 없음.

### 배포 세트
- `_Upload/` **32파일 733KB** = 변경 html 25개 + `ax-programs/` 7개. `/www/`에 폴더 구조 그대로 드래그앤드랍. shared·AX_Discovery 자산은 불필요.
- 직원 README의 확인 주소 `thewhhylab.ai.kr/ax-programs/`는 **오기** — 메인은 `www.thewhhylab.com`.

### 결정 사항
- **AX Alignment는 GNB(메뉴)에서 제외, 푸터에는 유지** — 같은 날 직원과 통화로 확정. 직원 README의 "메뉴에 AX Alignment 추가" 안내는 오기였고, 실제 의도는 "내용이 짧아 메뉴를 늘리지 않고 AX 프로그램 페이지 안 버튼으로만 진입". 메뉴 순서: AX 프로그램 → AX Discovery → 자격증. Alignment 페이지 메뉴에선 상위 'AX 프로그램'을 current 표시. (사용자가 제안한 팝업/이탈률 아이디어는 직원이 보류.)
- 내부 링크는 같은 탭(사이트 관례). 되돌리려면 `target="_blank" rel="noopener"` 다시 붙이면 됨.
- canonical/OG/sitemap 등재는 AX_Discovery와 같이 보류.

---

## ▶ 다음 세션 시작점 (2026-10-28 저장 — 여기서 이어서)

**상태:** 코드·검증·`_Upload/` 세트 완료, 커밋됨. **실서버 미반영.**
1. 사용자가 `_Upload/` 32파일을 FTP로 `/www/`에 업로드.
2. 업로드 후 **www.thewhhylab.com 라이브 비교 검증**(사용자 요청): `/ax-programs/`·`/ax-programs/AX_Alignment/` 200, 32파일 서버=로컬 바이트 일치, 27페이지 메뉴 순서, 콘솔오류·404 0.
3. 이후 선택: ax-programs canonical/OG/sitemap, `_data/`(7월 참고 이미지, 미추적) 정리 여부.

