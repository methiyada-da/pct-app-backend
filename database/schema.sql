SET NAMES utf8mb4;

CREATE TABLE course_group (
  cg_id INT NOT NULL AUTO_INCREMENT,
  cg_name VARCHAR(100) NOT NULL,
  cg_desc TEXT NULL,
  PRIMARY KEY (cg_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE faculty (
  fac_id INT NOT NULL,
  fac_name VARCHAR(100) NOT NULL,
  PRIMARY KEY (fac_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE major (
  mj_id INT NOT NULL,
  mj_name VARCHAR(100) NOT NULL,
  mj_abbr VARCHAR(10) NOT NULL,
  mj_desc TEXT NOT NULL,
  fac_id INT NOT NULL,
  PRIMARY KEY (mj_id),
  KEY idx_major_faculty (fac_id),
  CONSTRAINT fk_major_faculty FOREIGN KEY (fac_id) REFERENCES faculty (fac_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE course (
  crs_id VARCHAR(13) NOT NULL,
  crs_name VARCHAR(150) NOT NULL,
  crs_desc TEXT NULL,
  cg_id INT NULL,
  PRIMARY KEY (crs_id),
  KEY idx_course_group (cg_id),
  CONSTRAINT fk_course_group FOREIGN KEY (cg_id) REFERENCES course_group (cg_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE member (
  mb_id VARCHAR(13) NOT NULL,
  mb_full_name VARCHAR(100) NOT NULL,
  mb_email VARCHAR(50) NOT NULL,
  mb_pwd VARCHAR(255) NOT NULL,
  mj_id INT NOT NULL,
  mb_img VARCHAR(255) NOT NULL,
  PRIMARY KEY (mb_id),
  UNIQUE KEY uq_member_email (mb_email),
  KEY idx_member_major (mj_id),
  CONSTRAINT fk_member_major FOREIGN KEY (mj_id) REFERENCES major (mj_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE tutor (
  tut_id VARCHAR(13) NOT NULL,
  tut_desc TEXT NOT NULL,
  tut_skill TEXT NOT NULL,
  tut_gpax DECIMAL(3,2) NOT NULL,
  tut_has_exp TINYINT(1) NOT NULL,
  tut_exp_desc TEXT NOT NULL,
  tut_rating DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  tut_status TINYINT(1) NOT NULL,
  PRIMARY KEY (tut_id),
  CONSTRAINT fk_tutor_member FOREIGN KEY (tut_id) REFERENCES member (mb_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE tutor_course (
  tutc_id VARCHAR(13) NOT NULL,
  tutc_name VARCHAR(150) NOT NULL,
  tutc_desc TEXT NULL,
  tutc_status TINYINT(1) DEFAULT 0,
  crs_id VARCHAR(13) NULL,
  tut_id VARCHAR(13) NULL,
  tutc_price DECIMAL(8,2) DEFAULT 0.00,
  PRIMARY KEY (tutc_id),
  KEY idx_tutor_course_course (crs_id),
  KEY idx_tutor_course_tutor (tut_id),
  CONSTRAINT fk_tutor_course_course FOREIGN KEY (crs_id) REFERENCES course (crs_id),
  CONSTRAINT fk_tutor_course_tutor FOREIGN KEY (tut_id) REFERENCES tutor (tut_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE tutor_schedule (
  sch_id INT NOT NULL AUTO_INCREMENT,
  tutc_id VARCHAR(13) NOT NULL,
  sch_day TINYINT NOT NULL,
  sch_start TIME NOT NULL,
  sch_end TIME NOT NULL,
  PRIMARY KEY (sch_id),
  KEY idx_schedule_course (tutc_id),
  CONSTRAINT fk_schedule_course FOREIGN KEY (tutc_id) REFERENCES tutor_course (tutc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE conversation (
  conv_id INT NOT NULL AUTO_INCREMENT,
  user_a VARCHAR(13) NOT NULL,
  user_b VARCHAR(13) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (conv_id),
  UNIQUE KEY uq_conversation_pair (user_a, user_b)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE messages (
  msg_id INT NOT NULL AUTO_INCREMENT,
  conv_id INT NOT NULL,
  sender_id VARCHAR(13) NOT NULL,
  msg_text TEXT NOT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (msg_id),
  KEY idx_messages_conversation (conv_id),
  CONSTRAINT fk_messages_conversation FOREIGN KEY (conv_id) REFERENCES conversation (conv_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
