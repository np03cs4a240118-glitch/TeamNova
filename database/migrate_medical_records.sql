-- ============================================================
-- MediBook - Medical Records Migration
-- Adds: patient_allergies, patient_reports tables
-- Run this on existing dabs_db to add the new features
-- ============================================================

USE dabs_db;

-- Medication allergies tracker
CREATE TABLE IF NOT EXISTS patient_allergies (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    patient_id  INT            NOT NULL,
    name        VARCHAR(100)   NOT NULL,
    severity    ENUM('mild','moderate','severe') DEFAULT 'mild',
    notes       TEXT           DEFAULT NULL,
    created_at  DATETIME       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

-- Uploaded medical reports (PDF, images)
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

-- Indexes
CREATE INDEX idx_allergies_patient ON patient_allergies(patient_id);
CREATE INDEX idx_reports_patient   ON patient_reports(patient_id);
