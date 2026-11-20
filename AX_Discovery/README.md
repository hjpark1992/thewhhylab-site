# AX Discovery — 웹사이트

AX 전략기획 전문가 2급 자격증 과정 랜딩페이지 (정적 HTML)

## 파일 구조

```
ax-discovery/
├── index.html              # 메인 페이지
├── css/
│   └── style.css           # 전체 스타일시트
├── js/
│   └── main.js             # 네비, FAQ, 스크롤 효과
├── img/
│   ├── instructor.jpg      # 김홍태 강사 사진
│   ├── digital-badge.png   # AX Discovery 디지털 배지
│   └── README.md           # 이미지 교체 가이드
└── README.md               # 이 파일
```

## FileZilla 업로드 방법

1. FileZilla로 호스팅 서버에 접속
2. 기존 `index.html` 및 관련 파일들이 있다면 **백업**
3. 이 폴더 안의 모든 내용(`index.html`, `css/`, `js/`, `img/`)을 **웹 루트 디렉토리**(보통 `public_html/`, `www/`, `htdocs/` 등)에 그대로 업로드
4. 브라우저에서 도메인 접속 후 정상 표시 확인

## 신청 링크 (event-us)

- **1기**: https://event-us.kr/thewhhylab/event/126208
- **2기**: https://event-us.kr/thewhhylab/event/126212

링크 변경이 필요하면 `index.html`에서 위 URL 두 군데를 찾아 교체.

## 콘텐츠 수정

모든 텍스트는 `index.html` 안에 직접 들어있어 일반 텍스트 에디터(메모장, VS Code, 노트패드++)로 바로 수정 가능합니다.

### 자주 수정할 수 있는 부분

| 위치 | 수정 항목 |
|------|-----------|
| Hero 섹션 | 메인 카피, 모집 일정 |
| Registration 섹션 | 가격, 모집기간, 교육일정, 장소, event-us 링크 |
| FAQ 섹션 | 질문/답변 추가·수정·삭제 |
| Footer | 회사 정보, 이메일, 주소 |

## 기술 스택

- 순수 HTML5 / CSS3 / Vanilla JavaScript (외부 빌드 도구 불필요)
- 외부 의존성:
  - Pretendard Variable 폰트 (CDN jsdelivr)
  - JetBrains Mono 폰트 (Google Fonts)
- 인터넷 연결 없이도 동작하나, 폰트만 fallback 처리됨

## 반응형 지원

- 데스크톱: 1200px 컨테이너 기준
- 태블릿: 900px 이하에서 2-column → 1-column 전환
- 모바일: 768px 이하 최적화, 480px 이하 추가 보정
- 커리큘럼 표는 모바일에서 자동으로 카드 뷰로 전환

## 브라우저 호환성

Chrome 110+, Firefox 110+, Safari 16+, Edge 110+
(2026년 기준 모든 주요 브라우저에서 정상 동작)

---

© 2026 더와이랩 (THE WHHY LAB) · 과학기술정보통신부 정식 등록 민간자격 2026-003156
