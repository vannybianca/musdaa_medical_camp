USE musdaa_medical_camp;

ALTER TABLE attendees
    ADD COLUMN tribe VARCHAR(100) AFTER address;

ALTER TABLE service_records
    ADD COLUMN weight DECIMAL(5,2) AFTER spo2,
    ADD COLUMN height DECIMAL(5,2) AFTER weight,
    ADD COLUMN clinical_history TEXT AFTER height,
    ADD COLUMN diagnosis TEXT AFTER clinical_history,
    ADD COLUMN scd_family_history TEXT AFTER diagnosis,
    ADD COLUMN blood_sugar_unit VARCHAR(10) NOT NULL DEFAULT 'mg/dL' AFTER blood_sugar;