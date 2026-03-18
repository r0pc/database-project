DROP DATABASE IF EXISTS internship_result_management;
CREATE DATABASE internship_result_management;
USE internship_result_management;

CREATE TABLE users (
  user_id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  role ENUM('Admin', 'Assessor') NOT NULL
);

CREATE TABLE students (
  student_id VARCHAR(20) PRIMARY KEY,
  student_name VARCHAR(100) NOT NULL,
  programme VARCHAR(100) NOT NULL,
  company_name VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE internships (
  internship_id INT PRIMARY KEY AUTO_INCREMENT,
  student_id VARCHAR(20) NOT NULL UNIQUE,
  assessor_id INT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status ENUM('Active', 'Completed') NOT NULL DEFAULT 'Active',
  notes VARCHAR(255),
  CONSTRAINT fk_internships_student
    FOREIGN KEY (student_id) REFERENCES students (student_id),
  CONSTRAINT fk_internships_assessor
    FOREIGN KEY (assessor_id) REFERENCES users (user_id),
  CONSTRAINT chk_internship_dates
    CHECK (end_date >= start_date)
);

CREATE TABLE assessments (
  assessment_id INT PRIMARY KEY AUTO_INCREMENT,
  internship_id INT NOT NULL UNIQUE,
  undertaking_tasks DECIMAL(5,2) NOT NULL,
  health_safety DECIMAL(5,2) NOT NULL,
  theoretical_knowledge DECIMAL(5,2) NOT NULL,
  written_report DECIMAL(5,2) NOT NULL,
  language_clarity DECIMAL(5,2) NOT NULL,
  lifelong_learning DECIMAL(5,2) NOT NULL,
  project_management DECIMAL(5,2) NOT NULL,
  time_management DECIMAL(5,2) NOT NULL,
  final_mark DECIMAL(6,2) GENERATED ALWAYS AS (
    undertaking_tasks * 0.10 +
    health_safety * 0.10 +
    theoretical_knowledge * 0.10 +
    written_report * 0.15 +
    language_clarity * 0.10 +
    lifelong_learning * 0.15 +
    project_management * 0.15 +
    time_management * 0.15
  ) STORED,
  comments TEXT,
  assessed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_assessments_internship
    FOREIGN KEY (internship_id) REFERENCES internships (internship_id),
  CONSTRAINT chk_undertaking_tasks CHECK (undertaking_tasks BETWEEN 0 AND 100),
  CONSTRAINT chk_health_safety CHECK (health_safety BETWEEN 0 AND 100),
  CONSTRAINT chk_theoretical_knowledge CHECK (theoretical_knowledge BETWEEN 0 AND 100),
  CONSTRAINT chk_written_report CHECK (written_report BETWEEN 0 AND 100),
  CONSTRAINT chk_language_clarity CHECK (language_clarity BETWEEN 0 AND 100),
  CONSTRAINT chk_lifelong_learning CHECK (lifelong_learning BETWEEN 0 AND 100),
  CONSTRAINT chk_project_management CHECK (project_management BETWEEN 0 AND 100),
  CONSTRAINT chk_time_management CHECK (time_management BETWEEN 0 AND 100)
);

INSERT INTO users (username, password_hash, full_name, role) VALUES
('admin01', '$2y$10$AG/6mCKGKlT3fLYiwpY2tu1Gcp1oZyl1oUkpU9IaJTA5d2Mg.v6D2', 'System Administrator', 'Admin'),
('assessor01', '$2y$10$Qwlustr6nlrTMcBqQMMIAOoXKuSLjKW5E4DEHG66RTkfeKRrKcirC', 'Dr. Lee Kah Wei', 'Assessor'),
('assessor02', '$2y$10$6h/0SwbkzabJrqMkUgwd/eufEjLIhC/lClwegMaumPBrOMxg.8leq', 'Ms. Lim Siew Ling', 'Assessor');

INSERT INTO students (student_id, student_name, programme, company_name) VALUES
('TP067890', 'Nur Aina Binti Rahman', 'Diploma in Information Technology', 'Data Matrix Solutions'),
('TP068321', 'Adam Tan Wei Jian', 'Diploma in Software Engineering', 'NextWave Systems'),
('TP069114', 'Siti Noor Iman', 'Diploma in Information Systems', 'Cloud Axis Sdn Bhd');

INSERT INTO internships (student_id, assessor_id, start_date, end_date, status, notes) VALUES
('TP067890', 2, '2026-01-05', '2026-05-05', 'Active', 'Assigned to application support team'),
('TP068321', 3, '2026-01-12', '2026-05-12', 'Completed', 'Assigned to frontend dashboard project'),
('TP069114', 2, '2026-02-01', '2026-06-01', 'Active', 'Working in business analytics unit');

INSERT INTO assessments (
  internship_id,
  undertaking_tasks,
  health_safety,
  theoretical_knowledge,
  written_report,
  language_clarity,
  lifelong_learning,
  project_management,
  time_management,
  comments
) VALUES
(1, 82, 88, 80, 84, 81, 86, 83, 87, 'Consistent performance and good attitude.'),
(2, 76, 80, 78, 82, 79, 81, 84, 80, 'Solid technical delivery with clear reporting.');

CREATE OR REPLACE VIEW vw_student_assessment_summary AS
SELECT
  s.student_id,
  s.student_name,
  s.programme,
  s.company_name,
  u.full_name AS assessor_name,
  i.status AS internship_status,
  a.assessment_id,
  a.final_mark,
  a.assessed_at
FROM students s
JOIN internships i ON i.student_id = s.student_id
JOIN users u ON u.user_id = i.assessor_id
LEFT JOIN assessments a ON a.internship_id = i.internship_id;

SELECT * FROM vw_student_assessment_summary;
