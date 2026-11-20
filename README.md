# thewhhylab-site

www.thewhhylab.com 공식 홈페이지 파일. main 에 병합되면 GitHub Actions가 cafe24 FTP로 자동 배포한다.

## 들어 있지 않은 것

| 경로 | 이유 |
|---|---|
| `axd/` 웹캔버스 | 별도 저장소(AX-Discovery)에서 관리자가 직접 배포 |
| `PathOn/`, `sil/` | 앱. 이 저장소 범위 밖 |
| `api/config.php` | DB·메일·관리자 비밀값. 서버에만 있다. 구조는 `api/config.sample.php` |
| `api/uploads/` | 문의 폼 첨부 파일(개인정보). 서버에만 있다 |

배포 워크플로도 위 경로는 건드리지 않는다(`.github/workflows/deploy.yml` exclude).

## 수정 흐름

1. 브랜치를 만들어 고친다. 서식·줄바꿈을 바꾸지 않는다(`.gitattributes`로 보존).
2. PR을 올린다. 무엇을 왜 바꿨는지, 확인한 화면을 적는다.
3. 관리자가 보고 병합한다. 병합 후 1~2분 안에 서버에 반영된다. Actions 탭에서 결과 확인.
4. 반영 확인: `curl -sI https://www.thewhhylab.com/<경로> | grep -i last-modified`
5. 되돌리기: GitHub에서 해당 PR "Revert" → 병합.

## 규칙

- 스택은 정적 HTML + 바닐라 JS + CSS. 프레임워크·빌드 도구를 넣지 않는다.
- 새 폴더는 짧은 영문 소문자. 폴더명이 URL이 된다.
- 한국어 조판: `word-break: keep-all`, 한글 자간 없음, 라틴 대문자 라벨 없음, 이모지 없음.
- 문의 폼(`api/`)의 PHP를 고치면 `php -l` 문법 검사 후 PR. DB 스키마 변경은 자동 배포 대상이 아니다. 관리자와 상의.
- 비밀값을 어떤 파일에도 적지 않는다.

## 처음 설정 (관리자)

1. GitHub → Settings → Secrets and variables → Actions → `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD` 추가
2. `deploy.yml`의 `server-dir`가 cafe24 웹루트 폴더명과 같은지 확인(FileZilla 로그인 직후 오른쪽 창)
3. Actions 탭 → deploy-to-cafe24 → Run workflow. `dry-run: true` 상태라 계획만 출력된다
4. 계획이 맞으면 `dry-run: true` 줄을 지우고 커밋 → 실제 배포
