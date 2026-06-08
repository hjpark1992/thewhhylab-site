-- 더와이랩 폼 백엔드 스키마 (utf8mb4)
CREATE TABLE IF NOT EXISTS inquiries (
  id INT AUTO_INCREMENT PRIMARY KEY,
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
  created_at DATETIME NOT NULL,
  ip VARCHAR(45) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
