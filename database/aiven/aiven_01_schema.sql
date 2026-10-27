-- ============================================================
-- Hostel ballot: database structure (MySQL 8 / MariaDB)
-- Creates every table and the room_status_view view.
--
-- Run this on an EMPTY database. Plain SQL only: no DELIMITER
-- lines, so it runs in the mysql client, DBeaver, phpMyAdmin
-- or any other tool.
--
-- Laravel's own tables (migrations, personal_access_tokens) are
-- NOT here. Create them with: php artisan migrate
-- ============================================================

-- ---------- Users ----------
CREATE TABLE users (
  user_id       VARCHAR(10)  NOT NULL,
  email         VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','student') NOT NULL,
  PRIMARY KEY (user_id),
  UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE admins (
  admin_id   VARCHAR(10)  NOT NULL,
  user_id    VARCHAR(10)  NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name  VARCHAR(100) NOT NULL,
  PRIMARY KEY (admin_id),
  UNIQUE KEY user_id (user_id),
  CONSTRAINT admins_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE students (
  student_id VARCHAR(10)  NOT NULL,
  user_id    VARCHAR(10)  NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name  VARCHAR(100) NOT NULL,
  gender     ENUM('Male','Female') DEFAULT NULL,
  level      INT DEFAULT NULL,
  department VARCHAR(100) DEFAULT NULL,
  phone      VARCHAR(20)  DEFAULT NULL,
  PRIMARY KEY (student_id),
  UNIQUE KEY user_id (user_id),
  CONSTRAINT students_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------- Hostel structure ----------
CREATE TABLE hostel_details (
  hostel_id   VARCHAR(10)  NOT NULL,
  hostel_name VARCHAR(100) NOT NULL,
  warden_name VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (hostel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE blocks (
  block_id     VARCHAR(10) NOT NULL,
  hostel_id    VARCHAR(10) NOT NULL,
  gender_type  ENUM('Male','Female') NOT NULL,
  block_number INT NOT NULL,
  total_floors INT NOT NULL,
  PRIMARY KEY (block_id),
  UNIQUE KEY hostel_id (hostel_id, block_number),
  CONSTRAINT blocks_ibfk_1 FOREIGN KEY (hostel_id) REFERENCES hostel_details (hostel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- room_type: S = 4-man, L = 6-man, Other = non-bedroom (no beds)
-- status:    Available / Allocated for S and L rooms (kept up to date by the triggers in
--            aiven_02_triggers.sql); Utility for common rooms; Restricted for the rest
CREATE TABLE rooms (
  room_id      VARCHAR(10) NOT NULL,
  block_id     VARCHAR(10) NOT NULL,
  room_number  INT NOT NULL,
  floor_number INT NOT NULL,
  capacity     INT NOT NULL,
  room_type    ENUM('S','L','Other') NOT NULL,
  status       ENUM('Available','Allocated','Utility','Restricted') NOT NULL DEFAULT 'Available',
  PRIMARY KEY (room_id),
  UNIQUE KEY block_id (block_id, room_number),
  CONSTRAINT rooms_ibfk_1 FOREIGN KEY (block_id) REFERENCES blocks (block_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- A bed is Allocated exactly when it has a student; a student holds one bed
CREATE TABLE beds (
  bed_id     VARCHAR(10) NOT NULL,
  room_id    VARCHAR(10) NOT NULL,
  bed_label  VARCHAR(30) NOT NULL,
  student_id VARCHAR(10) NULL,
  status     ENUM('Available','Allocated') NOT NULL DEFAULT 'Available',
  PRIMARY KEY (bed_id),
  UNIQUE KEY room_id (room_id, bed_label),
  UNIQUE KEY uq_beds_student (student_id),
  CONSTRAINT beds_ibfk_1 FOREIGN KEY (room_id) REFERENCES rooms (room_id),
  CONSTRAINT fk_beds_student FOREIGN KEY (student_id) REFERENCES students (student_id),
  CONSTRAINT chk_beds_status_student CHECK (
    (status = 'Allocated' AND student_id IS NOT NULL) OR
    (status = 'Available' AND student_id IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------- View used by the API ----------
CREATE OR REPLACE VIEW room_status_view AS
SELECT
  r.room_id, r.block_id, r.room_number, r.floor_number, r.room_type, r.capacity,
  COALESCE(SUM(b.status = 'Available'), 0) AS available_beds,
  COALESCE(SUM(b.status = 'Allocated'), 0) AS allocated_beds,
  r.status AS room_status
FROM rooms r
LEFT JOIN beds b ON b.room_id = r.room_id
GROUP BY r.room_id, r.block_id, r.room_number, r.floor_number, r.room_type, r.capacity, r.status;

