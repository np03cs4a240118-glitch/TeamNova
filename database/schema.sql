-- ============================================================
-- MediBook - Doctor Appointment & Booking System
-- Database Schema | dabs_db
-- Stories: DB-1 to DB-9
-- ============================================================

CREATE DATABASE IF NOT EXISTS dabs_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dabs_db;

-- DB-4: patients table
CREATE TABLE IF NOT EXISTS patients (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL,
    email       VARCHAR(150)  NOT NULL UNIQUE,
    password    VARCHAR(255)  NOT NULL,
    phone       VARCHAR(20)   DEFAULT NULL,
    blood_type  VARCHAR(5)    DEFAULT NULL,
    address     VARCHAR(255)  DEFAULT NULL,
    dob         DATE          DEFAULT NULL,
    created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP
);

-- DB-5: doctors table
CREATE TABLE IF NOT EXISTS doctors (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)  NOT NULL,
    email           VARCHAR(150)  NOT NULL UNIQUE,
    password        VARCHAR(255)  NOT NULL,
    specialisation  VARCHAR(100)  NOT NULL,
    qualification   VARCHAR(200)  DEFAULT NULL,
    experience      INT           DEFAULT 0,
    clinic_name     VARCHAR(150)  DEFAULT NULL,
    clinic_address  VARCHAR(255)  DEFAULT NULL,
    clinic_phone    VARCHAR(20)   DEFAULT NULL,
    bio             TEXT          DEFAULT NULL,
    fee             DECIMAL(10,2) DEFAULT 800.00,
    availability    TEXT          DEFAULT NULL,
    status          ENUM('pending','approved','suspended') DEFAULT 'pending',
    created_at      DATETIME      DEFAULT CURRENT_TIMESTAMP
);

-- DB-6: appointments table
CREATE TABLE IF NOT EXISTS appointments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    patient_id  INT           NOT NULL,
    doctor_id   INT           NOT NULL,
    date        DATE          NOT NULL,
    time        TIME          NOT NULL,
    reason      TEXT          DEFAULT NULL,
    doctor_report TEXT        DEFAULT NULL,
    doctor_file VARCHAR(500)  DEFAULT NULL,
    status      ENUM('confirmed','pending','cancelled','completed') DEFAULT 'pending',
    created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id)  REFERENCES doctors(id)  ON DELETE CASCADE
);

-- DB-7: admin table + default admin
CREATE TABLE IF NOT EXISTS admins (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(80)   NOT NULL UNIQUE,
    password   VARCHAR(255)  NOT NULL,
    created_at DATETIME      DEFAULT CURRENT_TIMESTAMP
);

-- Default admin: username=admin, password=Admin@123
INSERT INTO admins (username, password) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE id=id;

-- DB-8: notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT           NOT NULL,
    user_type   ENUM('patient','doctor') NOT NULL,
    message     TEXT          NOT NULL,
    is_read     TINYINT(1)    DEFAULT 0,
    created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP
);

-- DB-9: patient allergies
CREATE TABLE IF NOT EXISTS patient_allergies (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    patient_id  INT            NOT NULL,
    name        VARCHAR(100)   NOT NULL,
    severity    ENUM('mild','moderate','severe') DEFAULT 'mild',
    notes       TEXT           DEFAULT NULL,
    created_at  DATETIME       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

-- DB-10: patient uploaded reports
CREATE TABLE IF NOT EXISTS patient_reports (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    patient_id    INT            NOT NULL,
    file_name     VARCHAR(255)   NOT NULL,
    file_path     VARCHAR(500)   NOT NULL,
    file_type     VARCHAR(50)    NOT NULL,
    file_size     INT            NOT NULL,
    uploaded_at   DATETIME       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

-- Indexes for performance
CREATE INDEX idx_appointments_patient ON appointments(patient_id);
CREATE INDEX idx_appointments_doctor  ON appointments(doctor_id);
CREATE INDEX idx_appointments_date    ON appointments(date);
CREATE INDEX idx_notifications_user   ON notifications(user_id, user_type);
CREATE INDEX idx_doctors_status       ON doctors(status);
CREATE INDEX idx_allergies_patient    ON patient_allergies(patient_id);
CREATE INDEX idx_reports_patient      ON patient_reports(patient_id);
