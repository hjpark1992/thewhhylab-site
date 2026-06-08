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
