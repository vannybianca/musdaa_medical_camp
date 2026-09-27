USE musdaa_medical_camp;

ALTER TABLE service_records
    ADD COLUMN tests VARCHAR(255) AFTER service_date,
    ADD COLUMN temperature DECIMAL(4,1) AFTER tests,
    ADD COLUMN pulse INT AFTER temperature,
    ADD COLUMN spo2 INT AFTER pulse,
    ADD COLUMN urine_output VARCHAR(100) AFTER spo2,
    ADD COLUMN consciousness VARCHAR(50) AFTER urine_output,
    ADD COLUMN doctor_recommendation TEXT AFTER blood_sugar;