-- 더와이랩 폼 백엔드 스키마 (utf8mb4)
-- inquiries: 교육문의(kind='contact') + AX 이벤트 신청(kind='event') 통합 테이블.
--   contact 전용 컬럼(affiliation..message)은 event 행에서 빈 값, event 전용 컬럼은 contact 행에서 NULL.
CREATE TABLE IF NOT EXISTS inquiries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kind VARCHAR(20) NOT NULL DEFAULT 'contact',
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  email VARCHAR(190) NOT NULL,
  affiliation VARCHAR(255) NOT NULL,
  topic VARCHAR(255) NOT NULL,
  purpose TEXT NOT NULL,
  audience VARCHAR(255) NOT NULL,
  location VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  privacy_agreed TINYINT(1) NOT NULL DEFAULT 0,
  -- AX 이벤트 신청 전용 (event)
  company VARCHAR(255) NULL,
  dept VARCHAR(255) NULL,
  job_title VARCHAR(120) NULL,
  postal_address VARCHAR(500) NULL,
  role_work VARCHAR(500) NULL,
  org_size VARCHAR(60) NULL,
  interests VARCHAR(500) NULL,
  story TEXT NULL,
  card_file VARCHAR(255) NULL,
  card_original VARCHAR(255) NULL,
  consult_wanted TINYINT(1) NOT NULL DEFAULT 0,
  marketing_agreed TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  ip VARCHAR(45) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 기존 서버 마이그레이션(1회) ─────────────────────────────
-- 이미 inquiries 테이블이 있는 운영 DB라면 위 CREATE는 무시되므로 아래 ALTER를 1회 실행하세요.
-- (관리자 페이지 접속 또는 이벤트 폼 접수 시 자동으로도 추가되지만, 명시적 실행 권장.)
--   ALTER TABLE inquiries
--     ADD COLUMN kind VARCHAR(20) NOT NULL DEFAULT 'contact',
--     ADD COLUMN company VARCHAR(255) NULL,
--     ADD COLUMN dept VARCHAR(255) NULL,
--     ADD COLUMN job_title VARCHAR(120) NULL,
--     ADD COLUMN postal_address VARCHAR(500) NULL,
--     ADD COLUMN role_work VARCHAR(500) NULL,
--     ADD COLUMN org_size VARCHAR(60) NULL,
--     ADD COLUMN interests VARCHAR(500) NULL,
--     ADD COLUMN story TEXT NULL,
--     ADD COLUMN card_file VARCHAR(255) NULL,
--     ADD COLUMN card_original VARCHAR(255) NULL,
--     ADD COLUMN consult_wanted TINYINT(1) NOT NULL DEFAULT 0,
--     ADD COLUMN marketing_agreed TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS member_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  referral VARCHAR(150) NULL,
  privacy_agreed TINYINT(1) NOT NULL DEFAULT 0,
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  gender VARCHAR(10) NULL,
  birth_year CHAR(4) NULL,
  region VARCHAR(120) NULL,
  career_years VARCHAR(60) NULL,
  prev_career VARCHAR(500) NULL,
  fields TEXT NULL,
  styles TEXT NULL,
  intro TEXT NULL,
  client_types VARCHAR(255) NULL,
  min_fee VARCHAR(100) NULL,
  profile_file VARCHAR(255) NULL,
  profile_original VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  ip VARCHAR(45) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
