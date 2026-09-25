-- LDNA database schema (MySQL 8 / MariaDB 10.6+)
-- Employees are NOT stored here: they come from the employee portal (backend/lib/portal.php).
-- Who may take a training is decided by: assessment level (from salary grade), position, area.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW IF EXISTS v_assessment_gaps, v_profile_level;
DROP TABLE IF EXISTS core_competency_rating, training_session_rating, training_session, training_position, training_area, training_competency, trainings,
  competency_levels, competencies, positions, offices, divisions, assessment_levels,
  -- tables from the retired competency-map version
  employee_assignment, profile_training, assessment_rating, assessments, profile_competency, profiles, training_office;
SET FOREIGN_KEY_CHECKS = 1;

-- Level legend: the salary grades each assessment level covers (editable in the app)
CREATE TABLE assessment_levels (
  level   TINYINT UNSIGNED PRIMARY KEY,                -- 1..4
  label   VARCHAR(40) NOT NULL,                        -- Basic, Intermediate, Advanced, Expert
  min_sg  TINYINT UNSIGNED NOT NULL,
  max_sg  TINYINT UNSIGNED NOT NULL,
  CONSTRAINT chk_level_range CHECK (min_sg <= max_sg AND level BETWEEN 1 AND 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE divisions (
  id    SMALLINT UNSIGNED PRIMARY KEY,
  code  VARCHAR(20)  NOT NULL UNIQUE,                  -- HOPSS, MS, NS, FS, AHPS, OCH
  name  VARCHAR(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE offices (                                 -- an area inside a division
  id          INT UNSIGNED PRIMARY KEY,
  division_id SMALLINT UNSIGNED NULL,
  area        VARCHAR(160) NOT NULL,
  name        VARCHAR(200) NOT NULL,
  portal_code VARCHAR(60)  NULL,
  source      VARCHAR(20)  NOT NULL DEFAULT 'added',   -- 'official-2026' (R1MC list), 'map-2023', 'added'
  UNIQUE KEY uq_office (division_id, area),
  CONSTRAINT fk_office_division FOREIGN KEY (division_id) REFERENCES divisions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE positions (
  id           INT UNSIGNED PRIMARY KEY,
  title        VARCHAR(160) NOT NULL UNIQUE,
  salary_grade TINYINT UNSIGNED NULL,                  -- decides the assessment level
  current      BOOLEAN NOT NULL DEFAULT TRUE,          -- in the current R1MC plantilla list
  portal_code  VARCHAR(60) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Competency dictionary
CREATE TABLE competencies (
  id          INT UNSIGNED PRIMARY KEY,
  name        VARCHAR(160) NOT NULL UNIQUE,
  category    ENUM('core','organizational','leadership','technical') NOT NULL,
  definition  TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE competency_levels (
  competency_id INT UNSIGNED NOT NULL,
  level         TINYINT UNSIGNED NOT NULL,
  description   TEXT NOT NULL,
  indicators    JSON NULL,
  verification  JSON NULL,
  PRIMARY KEY (competency_id, level),
  CONSTRAINT fk_level_comp FOREIGN KEY (competency_id) REFERENCES competencies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Position profile: the competencies a plantilla position has IN A SPECIFIC AREA (set by CETAR).
-- The same position can have different competencies in different areas.
CREATE TABLE position_profile (
  id          INT UNSIGNED PRIMARY KEY,
  position_id INT UNSIGNED NOT NULL,
  office_id   INT UNSIGNED NOT NULL,                   -- an official area
  source      VARCHAR(20) NOT NULL DEFAULT 'cetar',    -- 'cetar' or 'map-2023' (copied from the DOH map)
  UNIQUE KEY uq_profile (position_id, office_id),
  CONSTRAINT fk_pp_position FOREIGN KEY (position_id) REFERENCES positions(id),
  CONSTRAINT fk_pp_office   FOREIGN KEY (office_id)   REFERENCES offices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PIVOT: competency levels of a position profile
CREATE TABLE position_profile_competency (
  profile_id    INT UNSIGNED NOT NULL,
  competency_id INT UNSIGNED NOT NULL,
  level         TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (profile_id, competency_id),
  CONSTRAINT fk_ppc_profile    FOREIGN KEY (profile_id)    REFERENCES position_profile(id) ON DELETE CASCADE,
  CONSTRAINT fk_ppc_competency FOREIGN KEY (competency_id) REFERENCES competencies(id),
  CONSTRAINT chk_ppc_level CHECK (level BETWEEN 1 AND 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE trainings (
  id            INT UNSIGNED PRIMARY KEY,
  title         VARCHAR(200) NOT NULL,
  level         TINYINT UNSIGNED NOT NULL,             -- assessment level 1..4 (required)
  all_positions BOOLEAN NOT NULL,                      -- TRUE = every position; FALSE = only rows in training_position
  mode          ENUM('formal','non-formal','informal') NOT NULL,
  type          VARCHAR(80)  NULL,
  provider      VARCHAR(120) NULL,
  hours         SMALLINT UNSIGNED NULL,
  description   TEXT NULL,
  CONSTRAINT fk_training_level FOREIGN KEY (level) REFERENCES assessment_levels(level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PIVOT: competencies a training covers (optional; shows CETAR which of an employee's gaps it addresses)
CREATE TABLE training_competency (
  training_id    INT UNSIGNED NOT NULL,
  competency_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (training_id, competency_id),
  CONSTRAINT fk_tc_training   FOREIGN KEY (training_id)   REFERENCES trainings(id) ON DELETE CASCADE,
  CONSTRAINT fk_tc_competency FOREIGN KEY (competency_id) REFERENCES competencies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PIVOT: areas a training is limited to (optional: no rows = every area)
CREATE TABLE training_area (
  training_id INT UNSIGNED NOT NULL,
  office_id   INT UNSIGNED NOT NULL,
  PRIMARY KEY (training_id, office_id),
  CONSTRAINT fk_ta_training FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
  CONSTRAINT fk_ta_office   FOREIGN KEY (office_id)   REFERENCES offices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PIVOT: positions that may apply when trainings.all_positions = FALSE
CREATE TABLE training_position (
  training_id INT UNSIGNED NOT NULL,
  position_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (training_id, position_id),
  CONSTRAINT fk_tp_training FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
  CONSTRAINT fk_tp_position FOREIGN KEY (position_id) REFERENCES positions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PIVOT: enrolment requests. Inserted after the gate passes (below) and the employee has rated themselves on
-- every competency of their position profile (position + area). CETAR then approves or rejects.
-- Gate:
--   1. the employee's level (from salary grade, assessment_levels) >= trainings.level
--   2. all_positions, or the employee's position is in training_position
--   3. no training_area rows, or the employee's area is in training_area
CREATE TABLE training_session (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id      VARCHAR(20)  NOT NULL,              -- portal ID number
  position_id      INT UNSIGNED NULL,                  -- snapshot at enrolment
  office_id        INT UNSIGNED NULL,
  salary_grade     TINYINT UNSIGNED NULL,
  assessment_level TINYINT UNSIGNED NULL,
  profile_id       INT UNSIGNED NULL,                  -- the position profile rated (NULL = none set up yet)
  training_id      INT UNSIGNED NOT NULL,
  cycle_year       SMALLINT UNSIGNED NOT NULL,
  enrolled_at      DATETIME NOT NULL,                  -- when the request was submitted
  status           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',   -- CETAR validation
  review_note      VARCHAR(500) NULL,
  reviewed_at      DATETIME NULL,
  KEY ix_session (employee_id, training_id, cycle_year),     -- a rejected request may be followed by a new one
  KEY ix_status (status),
  CONSTRAINT fk_ts_training FOREIGN KEY (training_id) REFERENCES trainings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PIVOT: the self-assessment tally saved with each request (DOH LDNA tally sheet):
-- standard (from the position profile), actual (self-rating), gap = standard - actual.
CREATE TABLE training_session_rating (
  session_id     INT UNSIGNED NOT NULL,
  competency_id  INT UNSIGNED NOT NULL,
  standard_level TINYINT UNSIGNED NOT NULL,
  actual_level   TINYINT UNSIGNED NOT NULL,
  gap            TINYINT AS (CAST(standard_level AS SIGNED) - CAST(actual_level AS SIGNED)) STORED,
  PRIMARY KEY (session_id, competency_id),
  CONSTRAINT fk_tsr_session    FOREIGN KEY (session_id)    REFERENCES training_session(id) ON DELETE CASCADE,
  CONSTRAINT fk_tsr_competency FOREIGN KEY (competency_id) REFERENCES competencies(id),
  CONSTRAINT chk_tsr_levels CHECK (standard_level BETWEEN 1 AND 4 AND actual_level BETWEEN 1 AND 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Level of every position, from its salary grade
CREATE OR REPLACE VIEW v_position_level AS
SELECT p.id AS position_id, p.title, p.salary_grade, l.level AS assessment_level
FROM positions p LEFT JOIN assessment_levels l ON p.salary_grade BETWEEN l.min_sg AND l.max_sg;
