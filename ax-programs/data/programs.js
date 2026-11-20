// 더와이랩 제작 명세서의 콘텐츠 마스터. 웹과 인쇄가 동일한 데이터를 사용합니다.
// 문구 수정은 이 파일에서 진행합니다. 프로그램명·영역·분류 코드는 유지합니다.
window.AX_CONTENT = {
  areas: [
    { id: 'customer', name: '고객가치 전환', shortName: '고객가치', group: 'external', description: '고객 이해와 경험 혁신', programs: [
      { id: '1-1', name: 'AI 기반 고객경험(CX) 혁신', introduction: '고객 접점을 진단하고, AI로 새로운 개선 기회를 찾습니다.', audience: '고객 경험을 개선하고 싶지만 어디서부터 바꿔야 할지 막막한 조직', topics: ['고객경험(CX) 진단', '고객 접점·Pain Point 분석', '우수사례 역공학·개선 아이디어'] },
      { id: '1-2', name: '생성형 AI를 활용한 고객여정 재설계', introduction: '고객 여정의 불편과 이탈 지점을 찾아 AI로 접점을 재설계합니다.', audience: '고객 여정은 알고 있지만 우선 개선할 접점을 정하기 어려운 조직', topics: ['고객여정지도', 'Pain Point·이탈지점 분석', '접점 개선 시나리오'] },
      { id: '1-3', name: 'AI 기반 고객 니즈 분석 및 인사이트 발굴', introduction: 'VOC와 고객 데이터를 AI로 분석해 반복 신호와 숨은 니즈를 찾습니다.', audience: 'VOC와 고객 데이터는 쌓이지만 인사이트 도출이 어려운 조직', topics: ['VOC 분석 Task', '고객 데이터 패턴', '분석 Skill·인사이트 도출'] },
      { id: '1-4', name: '고객가치 중심 AI 서비스 기획', introduction: '고객 문제와 가치제안을 연결해 AI 서비스 아이디어를 구체화합니다.', audience: 'AI 서비스 아이디어는 있지만 고객가치가 명확하지 않은 조직', topics: ['고객가치·문제 정의', 'AI 서비스 아이디어', '고객 검증'] },
      { id: '1-5', name: 'AI를 활용한 고객문제 발굴 및 해결', introduction: '고객의 실제 문제를 찾아 해결 가설까지 빠르게 구체화합니다.', audience: '해결 아이디어는 있지만 핵심 고객문제 정의가 어려운 조직', topics: ['고객문제 정의', '인터뷰·니즈 탐색', '해결 가설'] }
    ] },
    { id: 'market', name: '시장/경쟁 전환', shortName: '시장·경쟁', group: 'external', description: '시장 분석과 경쟁전략', programs: [
      { id: '2-1', name: 'AI 시대의 경쟁전략 수립', introduction: '시장 변화와 경쟁 구도를 읽고 대응 전략을 설계합니다.', audience: '시장 변화는 빠르지만 대응 전략 수립이 어려운 조직', topics: ['경쟁구도 분석', '전략 시나리오', '차별화 전략'] },
      { id: '2-2', name: 'AI 기반 시장·경쟁사 분석 실무', introduction: '시장·경쟁사 조사를 AI로 빠르게 수행하고 비교 기준을 구조화합니다.', audience: '시장 자료는 많지만 인사이트 도출이 어려운 조직', topics: ['시장조사 Task', '경쟁사 분석 기준', '분석 Skill'] },
      { id: '2-3', name: '생성형 AI를 활용한 전략적 의사결정', introduction: '여러 대안을 비교하고 판단 기준을 명확히 해 의사결정 품질을 높입니다.', audience: '검토 자료는 많지만 전략적 판단 기준이 불명확한 조직', topics: ['우수 전략보고서 역공학', '대안평가', '판단 기준'] },
      { id: '2-4', name: '산업별 AX 트렌드 분석 및 대응전략', introduction: '산업별 AX 변화를 읽고 우리 조직의 대응 방향을 찾습니다.', audience: 'AX 트렌드는 접하지만 우리 산업의 변화 방향을 읽기 어려운 조직', topics: ['산업 변화', 'AX 트렌드', '대응 전략'] },
      { id: '2-5', name: 'AI 기반 미래 사업기회 탐색', introduction: '시장 변화에서 새로운 성장 기회와 신시장 후보를 발굴합니다.', audience: '기존 사업의 성장 한계는 보이지만 다음 사업기회를 찾지 못한 조직', topics: ['기회 신호 탐색', '성장 시나리오', '신시장 후보'] }
    ] },
    { id: 'business', name: '비즈니스모델 전환', shortName: '비즈니스모델', group: 'external', description: '신상품과 수익모델 혁신', programs: [
      { id: '3-1', name: 'AI 기반 비즈니스모델 혁신', introduction: '현재 비즈니스모델을 진단하고 AI로 가치와 수익구조를 재설계합니다.', audience: 'AI를 기존 사업에 어떻게 연결해야 할지 막막한 조직', topics: ['비즈니스모델 진단', '가치구조 재설계', '수익구조 혁신'] },
      { id: '3-2', name: '생성형 AI를 활용한 신상품·서비스 기획', introduction: '우수 기획안을 역공학해 신상품·서비스 기획 방식을 구조화합니다.', audience: '신상품 기획이 담당자의 경험과 감각에 의존하는 조직', topics: ['우수 기획안 역공학', '신상품 구상', '서비스 설계'] },
      { id: '3-3', name: 'AI 신상품 아이디어 발굴 및 사업화', introduction: 'AI로 아이디어를 넓히고 사업성 있는 후보를 빠르게 좁힙니다.', audience: '신상품 아이디어는 많지만 사업화로 연결되지 않는 조직', topics: ['아이디어 발굴', '사업성 검토', '검증 우선순위'] },
      { id: '3-4', name: 'AI 기반 신규 수익모델 설계', introduction: '새로운 수익원과 과금방식을 탐색해 사업의 경제성을 검토합니다.', audience: '서비스 아이디어는 있지만 수익모델 설계가 어려운 조직', topics: ['수익원 발굴', '과금방식', '경제성 검토'] },
      { id: '3-5', name: 'AI 에이전트를 활용한 비즈니스모델 개발', introduction: 'AI Agent를 고객가치와 수익모델로 연결하는 사업을 설계합니다.', audience: 'AI Agent 아이디어는 있지만 사업모델로 구체화하지 못한 조직', topics: ['Agent 기반 서비스', '가치제안', '비즈니스모델 설계'] }
    ] },
    { id: 'workflow', name: '워크플로우/프로세스 전환', shortName: '워크플로우·프로세스', group: 'internal', description: 'Task·Skill 기반 업무 재설계', programs: [
      { id: '4-1', name: 'AX Discovery: 업무혁신 과제 발굴', introduction: '업무를 Task 단위로 분해해 AX 우선 과제를 찾습니다.', audience: 'AI를 도입하고 싶지만 우선 적용할 업무를 찾지 못한 조직', topics: ['Role·Task 분해', 'Pain Point 진단', 'AX 과제 선정'], detail: 'discovery' },
      { id: '4-2', name: 'AI 기반 업무 프로세스 재설계', introduction: 'Task·Skill 기준으로 사람과 AI의 역할을 다시 설계합니다.', audience: 'AI를 활용하지만 업무 방식은 달라지지 않은 조직', topics: ['AS-IS/TO-BE', 'Task·Skill 설계', 'Human·AI 역할 배분'] },
      { id: '4-3', name: '생성형 AI를 활용한 실무 자동화', introduction: '반복 업무를 표준화하고 AI로 자동화할 수 있는 흐름을 설계합니다.', audience: '반복 업무가 많지만 무엇부터 자동화할지 모르는 조직', topics: ['반복 Task', 'SOP(표준 운영 절차) 표준화', '자동화 시나리오'] },
      { id: '4-4', name: '나만의 AI 에이전트 개발 실습', introduction: '업무 노하우를 Skill로 구조화해 실제 AI Agent로 구현·검증합니다.', audience: 'AI Agent를 도입하고 싶지만 적용할 업무와 기준을 찾지 못한 조직', topics: ['SOP(표준 운영 절차)·SKILL.md', 'Agent 설계', '정상·경계·실패 테스트'] },
      { id: '4-5', name: 'AI 에이전트 기반 업무 자동화 시스템 구축', introduction: '여러 Skill과 시스템을 연결해 실제 업무가 흐르는 Agent 시스템을 설계합니다.', audience: '개별 AI 도구는 활용하지만 업무 전체를 자동화 흐름으로 연결하지 못한 조직', topics: ['Skill 연결', '업무 Workflow', '운영·개선'] }
    ] },
    { id: 'culture', name: 'HR/조직문화 전환', shortName: 'HR·조직문화', group: 'internal', description: '사람·AI 역할과 협업 혁신', programs: [
      { id: '5-1', name: 'AI Native 조직 전환 전략', introduction: '조직의 업무를 Task·Skill 관점으로 다시 보고 AI Native 전환 방향을 설계합니다.', audience: 'AI 활용은 늘었지만 조직 차원의 전환 방향이 보이지 않는 조직', topics: ['조직진단', 'Task·Skill 전환', 'AI Native 운영'] },
      { id: '5-2', name: 'AI 시대의 리더십과 변화관리', introduction: 'AX 변화 속에서 리더가 해야 할 판단과 변화관리 방식을 정리합니다.', audience: 'AI 전환을 추진하지만 구성원의 변화 참여를 이끌기 어려운 조직', topics: ['변화관리', '리더 역할', '이해관계자 커뮤니케이션'] },
      { id: '5-3', name: 'AI와 협업하는 조직문화 만들기', introduction: '사람과 AI가 함께 일하기 위한 협업 원칙과 조직 규칙을 설계합니다.', audience: 'AI 활용 방식이 제각각이라 협업 기준과 원칙이 필요한 조직', topics: ['협업 규칙', 'AI 활용 원칙', '조직문화'] },
      { id: '5-4', name: 'AI 시대의 직무·역할 재설계', introduction: 'JOB을 Task로 분해하고 사람과 AI의 역할을 재배분합니다.', audience: 'AI 도입 이후 직무와 역할을 어떻게 바꿔야 할지 막막한 조직', topics: ['Job→Task 분해', 'Skill 정의', 'Human·AI 역할분담'] },
      { id: '5-5', name: '사내 AX 혁신리더 및 코치 양성', introduction: '조직 안에서 AX 과제를 발굴하고 확산시키는 내부 코치 역량을 만듭니다.', audience: 'AX를 조직 내부에서 지속적으로 확산할 추진 인력이 부족한 조직', topics: ['Best Case 역공학 코칭', 'Skill 내재화', '퍼실리테이션'] }
    ] },
    { id: 'data', name: '기술·데이터 기반 전환', shortName: '기술·데이터', group: 'internal', description: '조직지능과 AI 실행 기반', programs: [
      { id: '6-1', name: 'AI 도입을 위한 데이터 활용 전략', introduction: 'AI 활용에 필요한 데이터 상태를 진단하고 우선 정비 과제를 찾습니다.', audience: '데이터는 있지만 AI가 활용할 수 있는 상태인지 판단하기 어려운 조직', topics: ['AI Ready Data', '데이터 현황 진단', '활용 우선과제'] },
      { id: '6-2', name: '생성형 AI 기반 데이터 분석 및 시각화', introduction: '데이터를 AI로 분석하고 의사결정에 필요한 형태로 빠르게 시각화합니다.', audience: '데이터는 많지만 분석과 보고에 시간이 많이 드는 조직', topics: ['데이터 분석', '리포트 자동화', '시각화'] },
      { id: '6-3', name: 'AI 에이전트 구축을 위한 사내 지식·데이터 연결', introduction: '개인의 노하우와 사내 지식을 AI가 활용할 실행 지식으로 구조화합니다.', audience: '우수 직원의 노하우가 개인과 문서에만 머물러 있는 조직', topics: ['SOP(표준 운영 절차)·SKILL.md', '지식베이스', '검색·접근권한'] },
      { id: '6-4', name: 'AI 도입 리스크 관리 및 거버넌스', introduction: 'AI 활용의 보안·평가·운영 기준을 정해 안전한 사용 체계를 만듭니다.', audience: 'AI 활용을 확대하고 싶지만 보안과 책임 기준이 불명확한 조직', topics: ['정보보안', 'AI 평가', '운영정책·거버넌스'] },
      { id: '6-5', name: 'AI 업무 시스템 구축 및 운영 실무', introduction: 'AI Skill과 시스템을 연결하고 안정적으로 운영·검증하는 체계를 설계합니다.', audience: 'AI PoC는 만들었지만 실제 시스템 운영으로 연결하지 못한 조직', topics: ['Skill 실행환경', '시스템 연동', '검증·모니터링'] }
    ] }
  ],
  special: [
    { id: 'C2', name: '공공기관 AX 업무혁신 실무', introduction: '공공기관 업무의 AX 적용 기회를 탐색합니다.', audience: '생성형 AI 교육은 했지만 행정업무 변화로 연결되지 않은 조직', topics: ['행정업무 진단', '업무 재설계', '과제 발굴'], relation: '워크플로우/프로세스 전환 중심', area: 'workflow' },
    { id: 'C3', name: 'AX 전략기획 전문가 양성 과정', introduction: '내 업무의 본질을 재정의 하고, 사람과 AI의 역할을 재배치 하여 새로운 비즈니스 가치를 만드는 방법론을 기반으로 한 과정으로, AX 전략기획 전문가 2급 자격증 과정 취득이 가능 합니다.', audience: 'AX 추진 요구는 커지지만 실행 순서와 우선과제가 불명확한 조직', topics: ['전환 진단', '전략 수립', '실행 로드맵'], relation: '6개 전환 영역 통합', area: 'all', detailUrl: 'https://www.thewhhylab.ai.kr/AX_Discovery/', detailLabel: 'AX 전략기획 전문가 과정' },
    { id: 'C4', name: '사내 AX 코치 양성 과정', introduction: 'AX 과제 발굴과 조직 확산을 지원할 코칭 역량을 개발합니다.', audience: 'AX를 현업에 확산·정착시킬 내부 추진 인력이 부족한 조직', topics: ['AX Discovery', '코칭 도구', '퍼실리테이션'], relation: 'HR/조직문화 전환 중심', area: 'culture' },
    { id: 'C5', name: '소상공인을 위한 AI 에이전트 제작 워크숍', introduction: '소상공인 업무 문제를 발굴하고 AI Agent 적용을 실습합니다.', audience: '인력은 부족하고 반복 업무는 많지만 AI 적용 지점을 찾지 못한 조직', topics: ['운영 과제 선정', '에이전트 제작', '검증'], relation: '워크플로우 중심 · 비즈니스모델 연계', area: 'workflow' }
  ],
  links: { alignment: 'AX_Alignment/index.html', discovery: '/AX_Discovery/index.html' },
  contact: { email: 'master@thewhhylab.com', phone: '02-468-3555', telephone: '+8224683555', inquiryUrl: '../contact/' }
};
