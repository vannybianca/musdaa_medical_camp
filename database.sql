CREATE DATABASE IF NOT EXISTS musdaa_medical_camp;
USE musdaa_medical_camp;

CREATE TABLE summit_days (
    id INT AUTO_INCREMENT PRIMARY KEY,
    day_number INT NOT NULL UNIQUE,
    event_date DATE NOT NULL,
    description VARCHAR(255)
);

INSERT INTO summit_days (day_number, event_date, description) VALUES
(1, '2026-09-27', 'Day 1'),
(2, '2026-09-28', 'Day 2'),
(3, '2026-09-29', 'Day 3'),
(4, '2026-09-30', 'Day 4'),
(5, '2026-10-01', 'Day 5'),
(6, '2026-10-02', 'Day 6'),
(7, '2026-10-03', 'Day 7');

CREATE TABLE attendees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registration_number VARCHAR(30) NOT NULL UNIQUE,
    district VARCHAR(100),
    address VARCHAR(255),
    occupation VARCHAR(150),
    course VARCHAR(150),
    year_of_study VARCHAR(50),
    religion VARCHAR(100),
    church VARCHAR(150),
    fellowship VARCHAR(150),
    emergency_contact_name VARCHAR(150),
    emergency_contact_phone VARCHAR(30),
    emergency_contact_relationship VARCHAR(100),
    blood_group VARCHAR(10),
    consent_given BOOLEAN DEFAULT FALSE,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE daily_checkins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attendee_id INT NOT NULL,
    summit_day_id INT NOT NULL,
    checkin_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    checked_in_by VARCHAR(150),
    UNIQUE(attendee_id, summit_day_id),
    FOREIGN KEY (attendee_id) REFERENCES attendees(id) ON DELETE CASCADE,
    FOREIGN KEY (summit_day_id) REFERENCES summit_days(id) ON DELETE CASCADE
);

CREATE TABLE medical_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(150) NOT NULL,
    description TEXT
);

INSERT INTO medical_services (service_name, description) VALUES
('General Consultation', 'General medical consultation'),
('Blood Pressure', 'Blood pressure screening'),
('Blood Sugar', 'Blood glucose screening'),
('Malaria Test', 'Malaria screening'),
('HIV Test', 'HIV testing and counselling'),
('Blood Group Test', 'Blood group determination'),
('Eye Test', 'Basic eye examination'),
('Health Education', 'General health education'),
('Dental Checkup', 'Basic dental examination');

CREATE TABLE service_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attendee_id INT NOT NULL,
    service_id INT NOT NULL,
    service_date DATE NOT NULL,
    result VARCHAR(255),
    systolic INT,
    diastolic INT,
    blood_sugar DECIMAL(5,2),
    right_eye VARCHAR(20),
    left_eye VARCHAR(20),
    notes TEXT,
    referral_required BOOLEAN DEFAULT FALSE,
    referral_notes TEXT,
    attended_by VARCHAR(150),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attendee_id) REFERENCES attendees(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES medical_services(id) ON DELETE CASCADE
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Registration', 'Medical Staff', 'Report Officer') DEFAULT 'Registration',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE referrals (
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

CREATE TABLE audit_logs (
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

-- Development-only account. Change this before real deployment.
INSERT INTO users (full_name, username, password, role)
VALUES ('MUSDAA Administrator', 'admin', 'admin123', 'Admin');
