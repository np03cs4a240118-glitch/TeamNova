-- ============================================================
-- MediBook — Payments Migration
-- Adds payment_status and transaction_id to appointments table
-- Run once on existing databases
-- ============================================================

USE dabs_db;

ALTER TABLE appointments
    ADD COLUMN IF NOT EXISTS payment_status  ENUM('unpaid','paid') DEFAULT 'unpaid'   AFTER status,
    ADD COLUMN IF NOT EXISTS transaction_id  VARCHAR(100)          DEFAULT NULL        AFTER payment_status;

-- Backfill: treat all existing confirmed appointments as paid (cash at clinic)
UPDATE appointments SET payment_status = 'paid' WHERE status = 'confirmed';

-- Index for quick payment status lookups
CREATE INDEX IF NOT EXISTS idx_appointments_payment ON appointments(payment_status);
