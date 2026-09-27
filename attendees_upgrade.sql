USE musdaa_medical_camp;

ALTER TABLE attendees
    ADD COLUMN age INT AFTER address,
    ADD COLUMN days_attend VARCHAR(100) AFTER age,
    ADD COLUMN musdaa_status ENUM('MUSDAA', 'Nonmusdaa') AFTER days_attend;
