USE musdaa_medical_camp;

UPDATE summit_days SET event_date = CASE day_number
    WHEN 1 THEN '2026-09-27'
    WHEN 2 THEN '2026-09-28'
    WHEN 3 THEN '2026-09-29'
    WHEN 4 THEN '2026-09-30'
    WHEN 5 THEN '2026-10-01'
    WHEN 6 THEN '2026-10-02'
    WHEN 7 THEN '2026-10-03'
END
WHERE day_number BETWEEN 1 AND 7;

ALTER TABLE attendees
    ADD COLUMN email VARCHAR(150) AFTER phone,
    ADD COLUMN district VARCHAR(100) AFTER email,
    ADD COLUMN occupation VARCHAR(150) AFTER address,
    ADD COLUMN course VARCHAR(150) AFTER occupation,
    ADD COLUMN fellowship VARCHAR(150) AFTER church,
    ADD COLUMN emergency_contact_name VARCHAR(150) AFTER fellowship,
    ADD COLUMN emergency_contact_phone VARCHAR(30) AFTER emergency_contact_name;

ALTER TABLE service_records
    ADD COLUMN systolic INT AFTER result,
    ADD COLUMN diastolic INT AFTER systolic,
    ADD COLUMN blood_sugar DECIMAL(5,2) AFTER diastolic,
    ADD COLUMN right_eye VARCHAR(20) AFTER blood_sugar,
    ADD COLUMN left_eye VARCHAR(20) AFTER right_eye,
    ADD COLUMN referral_required BOOLEAN DEFAULT FALSE AFTER notes,
    ADD COLUMN referral_notes TEXT AFTER referral_required;

ALTER TABLE users
    MODIFY role ENUM('Admin', 'Registration', 'Medical Staff', 'Report Officer') DEFAULT 'Registration';

CREATE TABLE IF NOT EXISTS referrals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attendee_id INT NOT NULL,
    service_record_id INT,
    referral_reason VARCHAR(255) NOT NULL,
    referred_to VARCHAR(150),
    status ENUM('Pending', 'Completed', 'Cancelled') DEFAULT 'Pending',
    created_by VARCHAR(150),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attendee_id) REFERENCES attendees(id) ON DELETE CASCADE,
    FOREIGN KEY (service_record_id) REFERENCES service_records(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100),
    entity_id INT,
    description VARCHAR(255),
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);