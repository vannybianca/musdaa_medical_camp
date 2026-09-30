USE musdaa_medical_camp;

ALTER TABLE attendees
    ADD COLUMN first_name VARCHAR(100) AFTER registration_number,
    ADD COLUMN middle_name VARCHAR(100) AFTER first_name,
    ADD COLUMN last_name VARCHAR(100) AFTER middle_name,
    ADD COLUMN gender VARCHAR(20) AFTER last_name,
    ADD COLUMN phone VARCHAR(30) AFTER gender,
    ADD COLUMN tribe VARCHAR(100) AFTER address,
    ADD COLUMN age INT AFTER address,
    ADD COLUMN days_attend VARCHAR(100) AFTER age,
    ADD COLUMN musdaa_status ENUM('MUSDAA', 'Nonmusdaa') AFTER days_attend;
