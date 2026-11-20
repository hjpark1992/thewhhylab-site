# 더와이랩 AX 홍보 상세페이지

하나의 `index.html`을 웹 상세페이지와 A4 PDF 홍보자료에 함께 사용하는 프로젝트입니다. 각 `.pdf-page`는 웹에서 일반적인 풀폭 상세페이지 섹션으로 이어지고, 인쇄 시 A4 가로 한 장(297mm × 210mm)으로 분리됩니다.

디자인과 HITL 작업 기준은 [`docs/AX_DISCOVERY_DESIGN_GUIDE.md`](docs/AX_DISCOVERY_DESIGN_GUIDE.md)를 먼저 확인합니다.

## 시작하기

```bash
npm install
npx playwright install chromium
npm start
```

터미널에 표시되는 로컬 주소를 브라우저에서 엽니다. 단순 확인은 `index.html`을 직접 열어도 가능합니다.

## PDF 생성

```bash
npm run export:pdf
```

결과물: `output/pdf/thewhylab-ax-introduction.pdf`

브라우저 상단의 **PDF로 저장** 버튼으로도 인쇄 대화상자를 열 수 있습니다. 배경 그래픽 인쇄 옵션을 켜고 여백은 없음으로 설정하세요.

웹 데스크톱·모바일 화면과 섹션별 넘침을 다시 검사하려면 다음 명령을 사용합니다.

```bash
npm run qa:web
```

검수 이미지와 결과는 `tmp/qa/`에 생성됩니다.

## 콘텐츠 수정 위치

- 문구: `index.html`의 `editable-content` 클래스와 각 `.pdf-page` 섹션
- 색상·글꼴: `styles/common.css` 맨 위 `:root`의 브랜드 토큰
- 화면 레이아웃: `styles/screen.css`
- PDF 인쇄 규칙: `styles/print.css`
- 이미지: `assets/images/`, 폰트: `assets/fonts/`
- 연락처 링크: `index.html` 마지막 페이지의 `mailto:`, `tel:`, 웹사이트 URL

이미지는 의미에 맞는 `alt` 텍스트를 함께 입력하세요. 외부 웹폰트를 사용할 경우 인쇄 전에 로딩이 완료되도록 유지해야 합니다.

## 페이지 관리 원칙

- 모든 PDF 페이지는 독립된 `<section class="pdf-page">`입니다.
- 웹 전용 요소는 `.web-only`, 인쇄 전용 요소는 `.print-only`를 사용합니다.
- 페이지 중간 분할을 막을 요소는 `.avoid-break`를 사용합니다.
- 페이지 높이를 넘는 콘텐츠를 숨기지 않습니다. 문구 길이 또는 구성 요소 크기를 조정해 A4 안전 영역 안에 배치합니다.
- 페이지를 추가할 때 페이지 번호와 출력 후 실제 PDF 페이지 수를 함께 확인하세요.

## 현재 확정 정보와 추가 확인 항목

- 문의 이메일: `thewhhylab@naver.com`
- 전화번호: `02-468-3555`
- 웹사이트: `https://www.thewhhylab.com/`
- QR 코드는 사용하지 않습니다.
- 7~9페이지의 REDUCE·REALLOCATE·REINVENT 세부 커리큘럼과 10페이지 교육효과 수치·후기는 사용자가 제공한 자료를 기준으로 반영했습니다.
- 향후 확정이 필요한 항목은 고객사 실명 공개 범위, 표본 수·조사 시점·조사 방식, 세부 과정 운영 조건입니다. 확인되지 않은 정보는 임의로 추가하지 않습니다.
