-- ============================================================
--  MediCore HMS — MySQL Database Schema
--  Version 1.0 | Ministry of Health & Population — Egypt
-- ============================================================

CREATE DATABASE IF NOT EXISTS medicore_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE medicore_db;

-- ── 1. USERS ──────────────────────────────────────────────────
CREATE TABLE users (
  id            CHAR(36)        NOT NULL DEFAULT (UUID()),
  name          VARCHAR(120)    NOT NULL,
  email         VARCHAR(180)    NOT NULL UNIQUE,
  password_hash VARCHAR(255)    NOT NULL,
  role          ENUM('admin','doctor','patient') NOT NULL,
  phone         VARCHAR(20),
  is_active     TINYINT(1)      NOT NULL DEFAULT 1,
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

-- ── 2. DEPARTMENTS ────────────────────────────────────────────
CREATE TABLE departments (
  id       CHAR(36)     NOT NULL DEFAULT (UUID()),
  name     VARCHAR(100) NOT NULL,
  code     VARCHAR(10)  NOT NULL UNIQUE,
  capacity INT          NOT NULL DEFAULT 20,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

-- ── 3. DOCTORS ────────────────────────────────────────────────
CREATE TABLE doctors (
  id              CHAR(36)     NOT NULL DEFAULT (UUID()),
  user_id         CHAR(36)     NOT NULL,
  department_id   CHAR(36)     NOT NULL,
  license_number  VARCHAR(50)  NOT NULL UNIQUE,
  specialization  VARCHAR(100) NOT NULL,
  status          ENUM('available','busy','on_leave','inactive') NOT NULL DEFAULT 'available',
  rating          FLOAT        NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE CASCADE,
  FOREIGN KEY (department_id) REFERENCES departments(id)  ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ── 4. PATIENTS ───────────────────────────────────────────────
CREATE TABLE patients (
  id             CHAR(36)   NOT NULL DEFAULT (UUID()),
  user_id        CHAR(36)   NOT NULL,
  patient_code   VARCHAR(10) NOT NULL UNIQUE,
  date_of_birth  DATE,
  blood_type     ENUM('A+','A-','B+','B-','O+','O-','AB+','AB-'),
  allergies      TEXT,
  status         ENUM('active','under_review','discharged') NOT NULL DEFAULT 'active',
  PRIMARY KEY (id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 5. APPOINTMENTS ───────────────────────────────────────────
CREATE TABLE appointments (
  id           CHAR(36)   NOT NULL DEFAULT (UUID()),
  appt_ref     VARCHAR(10) NOT NULL UNIQUE,
  patient_id   CHAR(36)   NOT NULL,
  doctor_id    CHAR(36)   NOT NULL,
  appt_date    DATE       NOT NULL,
  appt_time    TIME       NOT NULL,
  type         ENUM('consultation','follow_up','procedure','check_up') NOT NULL DEFAULT 'consultation',
  status       ENUM('scheduled','completed','cancelled','pending') NOT NULL DEFAULT 'scheduled',
  notes        TEXT,
  reminder_24h TINYINT(1) NOT NULL DEFAULT 0,
  reminder_1h  TINYINT(1) NOT NULL DEFAULT 0,
  created_at   DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (doctor_id)  REFERENCES doctors(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 6. MEDICAL RECORDS ────────────────────────────────────────
CREATE TABLE medical_records (
  id              CHAR(36)    NOT NULL DEFAULT (UUID()),
  patient_id      CHAR(36)    NOT NULL,
  doctor_id       CHAR(36)    NOT NULL,
  appointment_id  CHAR(36),
  record_type     ENUM('consultation','lab_result','imaging','prescription','surgery') NOT NULL,
  diagnosis       TEXT,
  notes           TEXT,
  icd10_code      VARCHAR(10),
  blood_pressure  VARCHAR(10),
  heart_rate      INT,
  temperature     FLOAT,
  spo2            INT,
  weight          FLOAT,
  height          FLOAT,
  record_date     DATE        NOT NULL DEFAULT (CURRENT_DATE),
  created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (patient_id)     REFERENCES patients(id)     ON DELETE CASCADE,
  FOREIGN KEY (doctor_id)      REFERENCES doctors(id)      ON DELETE CASCADE,
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── 7. WARDS ──────────────────────────────────────────────────
CREATE TABLE wards (
  id            CHAR(36)    NOT NULL DEFAULT (UUID()),
  department_id CHAR(36)    NOT NULL,
  name          VARCHAR(80) NOT NULL,
  ward_type     ENUM('general','icu','private','pediatric','surgical') NOT NULL DEFAULT 'general',
  total_beds    INT         NOT NULL DEFAULT 10,
  PRIMARY KEY (id),
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 8. BEDS ───────────────────────────────────────────────────
CREATE TABLE beds (
  id         CHAR(36)   NOT NULL DEFAULT (UUID()),
  ward_id    CHAR(36)   NOT NULL,
  bed_number VARCHAR(10) NOT NULL,
  status     ENUM('available','occupied','reserved','maintenance') NOT NULL DEFAULT 'available',
  PRIMARY KEY (id),
  UNIQUE KEY uq_bed (ward_id, bed_number),
  FOREIGN KEY (ward_id) REFERENCES wards(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 9. BED ASSIGNMENTS ────────────────────────────────────────
CREATE TABLE bed_assignments (
  id            CHAR(36)  NOT NULL DEFAULT (UUID()),
  bed_id        CHAR(36)  NOT NULL,
  patient_id    CHAR(36)  NOT NULL,
  doctor_id     CHAR(36)  NOT NULL,
  admitted_at   DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  discharged_at DATETIME,
  PRIMARY KEY (id),
  FOREIGN KEY (bed_id)     REFERENCES beds(id)     ON DELETE RESTRICT,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (doctor_id)  REFERENCES doctors(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 10. INVOICES ──────────────────────────────────────────────
CREATE TABLE invoices (
  id                 CHAR(36)       NOT NULL DEFAULT (UUID()),
  invoice_ref        VARCHAR(12)    NOT NULL UNIQUE,
  patient_id         CHAR(36)       NOT NULL,
  appointment_id     CHAR(36),
  service_name       VARCHAR(150)   NOT NULL,
  amount             DECIMAL(10,2)  NOT NULL,
  insurance_coverage DECIMAL(10,2)  NOT NULL DEFAULT 0,
  status             ENUM('issued','pending','paid','overdue','cancelled') NOT NULL DEFAULT 'issued',
  due_date           DATE,
  paid_at            DATETIME,
  created_at         DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (patient_id)     REFERENCES patients(id)     ON DELETE CASCADE,
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── 11. NOTIFICATION LOG ──────────────────────────────────────
CREATE TABLE notification_logs (
  id          CHAR(36)  NOT NULL DEFAULT (UUID()),
  patient_id  CHAR(36)  NOT NULL,
  channel     ENUM('sms','email','push') NOT NULL,
  event_type  VARCHAR(50) NOT NULL,
  message     TEXT,
  provider_id VARCHAR(100),
  delivered   TINYINT(1) NOT NULL DEFAULT 0,
  sent_at     DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 12. AUDIT LOG ─────────────────────────────────────────────
CREATE TABLE audit_logs (
  id          CHAR(36)    NOT NULL DEFAULT (UUID()),
  user_id     CHAR(36),
  action      VARCHAR(50) NOT NULL,
  model_name  VARCHAR(50) NOT NULL,
  object_id   VARCHAR(36),
  changes     TEXT,
  ip_address  VARCHAR(45),
  created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
--  SEED DATA
-- ============================================================

-- Departments
INSERT INTO departments (id, name, code, capacity) VALUES
  ('dept-card-0001', 'Cardiology',   'CARD', 20),
  ('dept-neur-0001', 'Neurology',    'NEUR', 16),
  ('dept-orth-0001', 'Orthopedics',  'ORTH', 10),
  ('dept-pedi-0001', 'Pediatrics',   'PEDI', 12),
  ('dept-derm-0001', 'Dermatology',  'DERM', 8),
  ('dept-admi-0001', 'Administration','ADMIN',0);

-- Users (passwords = bcrypt of "admin123" / "doctor123" / "patient123")
INSERT INTO users (id, name, email, password_hash, role, phone) VALUES
  ('user-adm-00001','Dr. Admin Hassan',   'admin@hospital.gov.eg',
   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',   '+201001234560'),
  ('user-doc-00001','Dr. James Wilson',   'doctor@hospital.gov.eg',
   '$2y$12$T9tZSNYXsQdZGYXWqiKnXuKkRl.Jx7T0.4IQ3kNOyHbEYp6O7Wpe', 'doctor',  '+201001234561'),
  ('user-doc-00002','Dr. Emily Clarke',   'emily@hospital.gov.eg',
   '$2y$12$T9tZSNYXsQdZGYXWqiKnXuKkRl.Jx7T0.4IQ3kNOyHbEYp6O7Wpe', 'doctor',  '+201001234562'),
  ('user-doc-00003','Dr. Marcus Lee',     'marcus@hospital.gov.eg',
   '$2y$12$T9tZSNYXsQdZGYXWqiKnXuKkRl.Jx7T0.4IQ3kNOyHbEYp6O7Wpe', 'doctor',  '+201001234563'),
  ('user-doc-00004','Dr. Anna Patel',     'anna@hospital.gov.eg',
   '$2y$12$T9tZSNYXsQdZGYXWqiKnXuKkRl.Jx7T0.4IQ3kNOyHbEYp6O7Wpe', 'doctor',  '+201001234564'),
  ('user-pat-00001','Sarah Mitchell',     'patient@hospital.gov.eg',
   '$2y$12$G8Vj.b7V2U.7WtVl1PRPZuFxHKbPmrb8Wg7wqEJH.RY4PnN3Cqoq', 'patient', '+201001234565'),
  ('user-pat-00002','Robert Chen',        'robert@hospital.gov.eg',
   '$2y$12$G8Vj.b7V2U.7WtVl1PRPZuFxHKbPmrb8Wg7wqEJH.RY4PnN3Cqoq', 'patient', '+201001234566'),
  ('user-pat-00003','Amara Johnson',      'amara@hospital.gov.eg',
   '$2y$12$G8Vj.b7V2U.7WtVl1PRPZuFxHKbPmrb8Wg7wqEJH.RY4PnN3Cqoq', 'patient', '+201001234567');

-- Doctors
INSERT INTO doctors (id, user_id, department_id, license_number, specialization, status, rating) VALUES
  ('doc-jw-000001','user-doc-00001','dept-card-0001','LIC-JW-001','Cardiologist',    'available', 4.9),
  ('doc-ec-000001','user-doc-00002','dept-neur-0001','LIC-EC-001','Neurologist',     'available', 4.7),
  ('doc-ml-000001','user-doc-00003','dept-orth-0001','LIC-ML-001','Orthopedic',      'busy',      4.8),
  ('doc-ap-000001','user-doc-00004','dept-pedi-0001','LIC-AP-001','Pediatrician',    'available', 4.9);

-- Patients
INSERT INTO patients (id, user_id, patient_code, date_of_birth, blood_type, allergies, status) VALUES
  ('pat-sm-000001','user-pat-00001','P-1042','1990-05-12','O+','Penicillin','active'),
  ('pat-rc-000001','user-pat-00002','P-1043','1985-11-30','B+',NULL,'active'),
  ('pat-aj-000001','user-pat-00003','P-1044','1978-03-22','A-','Aspirin','under_review');

-- Wards
INSERT INTO wards (id, department_id, name, ward_type, total_beds) VALUES
  ('ward-card-001','dept-card-0001','Cardiology Ward A','general', 20),
  ('ward-neur-001','dept-neur-0001','Neurology Ward B', 'general', 16),
  ('ward-orth-001','dept-orth-0001','Orthopedics Ward C','surgical',10),
  ('ward-pedi-001','dept-pedi-0001','Pediatrics Ward D','pediatric',12);

-- Beds (sample for Cardiology)
INSERT INTO beds (id, ward_id, bed_number, status) VALUES
  ('bed-c-01','ward-card-001','C-01','occupied'),
  ('bed-c-02','ward-card-001','C-02','occupied'),
  ('bed-c-03','ward-card-001','C-03','available'),
  ('bed-c-04','ward-card-001','C-04','reserved'),
  ('bed-c-05','ward-card-001','C-05','available'),
  ('bed-n-01','ward-neur-001','N-01','occupied'),
  ('bed-n-02','ward-neur-001','N-02','available'),
  ('bed-n-03','ward-neur-001','N-03','occupied'),
  ('bed-o-01','ward-orth-001','O-01','occupied'),
  ('bed-o-02','ward-orth-001','O-02','maintenance'),
  ('bed-p-01','ward-pedi-001','P-01','available'),
  ('bed-p-02','ward-pedi-001','P-02','occupied');

-- Appointments
INSERT INTO appointments (id, appt_ref, patient_id, doctor_id, appt_date, appt_time, type, status) VALUES
  ('appt-001','A-101','pat-sm-000001','doc-jw-000001','2026-03-01','09:00:00','consultation','completed'),
  ('appt-002','A-102','pat-rc-000001','doc-ec-000001','2026-03-01','10:30:00','follow_up','scheduled'),
  ('appt-003','A-103','pat-aj-000001','doc-ml-000001','2026-03-01','11:00:00','procedure','scheduled'),
  ('appt-004','A-108','pat-sm-000001','doc-jw-000001','2026-03-15','10:00:00','follow_up','scheduled');

-- Medical Records
INSERT INTO medical_records (id, patient_id, doctor_id, appointment_id, record_type, diagnosis, notes, icd10_code, blood_pressure, heart_rate, temperature, spo2) VALUES
  ('rec-001','pat-sm-000001','doc-jw-000001','appt-001','lab_result',
   'Mild Arrhythmia','Holter Monitor: occasional PVCs detected (5-8/hour). Clinically significant.','I49.3','120/80',78,36.8,98),
  ('rec-002','pat-sm-000001','doc-jw-000001','appt-001','prescription',
   'Metoprolol 25mg + Ramipril 5mg','Metoprolol 25mg twice daily. Ramipril 5mg once daily. Review in 4 weeks.',NULL,'122/82',82,36.7,99);

-- Invoices
INSERT INTO invoices (id, invoice_ref, patient_id, appointment_id, service_name, amount, insurance_coverage, status, due_date) VALUES
  ('inv-001','INV-2240','pat-sm-000001','appt-001','Cardiology Consultation', 350.00, 30.00,'paid',  '2026-03-15'),
  ('inv-002','INV-2241','pat-sm-000001', NULL,      'Echocardiogram',         800.00,  0.00,'pending','2026-03-31'),
  ('inv-003','INV-2239','pat-rc-000001','appt-002','Neurology Follow-up',    200.00, 20.00,'pending','2026-03-20');

-- Bed Assignments
INSERT INTO bed_assignments (id, bed_id, patient_id, doctor_id, admitted_at) VALUES
  ('ba-001','bed-c-01','pat-sm-000001','doc-jw-000001','2026-03-01 09:00:00'),
  ('ba-002','bed-n-01','pat-rc-000001','doc-ec-000001','2026-03-01 10:00:00');

-- ============================================================
--  USEFUL VIEWS
-- ============================================================

-- Patient full profile view
CREATE VIEW v_patient_profile AS
SELECT
  p.id, p.patient_code, p.date_of_birth, p.blood_type, p.allergies, p.status,
  u.name, u.email, u.phone,
  d.name AS doctor_name, dep.name AS department
FROM patients p
JOIN users u   ON u.id = p.user_id
LEFT JOIN doctors doc ON doc.id = (
  SELECT doctor_id FROM appointments
  WHERE patient_id = p.id ORDER BY created_at DESC LIMIT 1
)
LEFT JOIN users d ON d.id = doc.user_id
LEFT JOIN departments dep ON dep.id = doc.department_id;

-- Today's appointments view
CREATE VIEW v_today_appointments AS
SELECT
  a.id, a.appt_ref, a.appt_time, a.type, a.status,
  pu.name AS patient_name, p.patient_code,
  du.name AS doctor_name, dep.name AS department
FROM appointments a
JOIN patients p   ON p.id = a.patient_id
JOIN users pu     ON pu.id = p.user_id
JOIN doctors doc  ON doc.id = a.doctor_id
JOIN users du     ON du.id = doc.user_id
JOIN departments dep ON dep.id = doc.department_id
WHERE a.appt_date = CURRENT_DATE
ORDER BY a.appt_time;

-- Bed occupancy view
CREATE VIEW v_bed_occupancy AS
SELECT
  dep.name AS department, w.name AS ward,
  COUNT(b.id) AS total_beds,
  SUM(b.status = 'occupied') AS occupied,
  SUM(b.status = 'available') AS available,
  ROUND(SUM(b.status='occupied')/COUNT(b.id)*100,1) AS occupancy_pct
FROM beds b
JOIN wards w ON w.id = b.ward_id
JOIN departments dep ON dep.id = w.department_id
GROUP BY dep.id, w.id;