# MUSDAA Medical Camp Data Model

```mermaid
erDiagram
    USERS ||--o{ AUDIT_LOGS : creates
    ATTENDEES ||--o{ DAILY_CHECKINS : makes
    SUMMIT_DAYS ||--o{ DAILY_CHECKINS : contains
    ATTENDEES ||--o{ SERVICE_RECORDS : receives
    MEDICAL_SERVICES ||--o{ SERVICE_RECORDS : defines
    ATTENDEES ||--o{ REFERRALS : has
    SERVICE_RECORDS ||--o{ REFERRALS : may_create

    ATTENDEES {
        int id PK
        string registration_number UK
        string full_name
        string contact_details
        string consent
    }
    DAILY_CHECKINS {
        int id PK
        int attendee_id FK
        int summit_day_id FK
        datetime checkin_time
    }
    SERVICE_RECORDS {
        int id PK
        int attendee_id FK
        int service_id FK
        date service_date
        string results
        boolean referral_required
    }
    REFERRALS {
        int id PK
        int attendee_id FK
        int service_record_id FK
        string status
    }
    AUDIT_LOGS {
        int id PK
        int user_id FK
        string action
        datetime created_at
    }
```

## Module boundaries

- Registration owns attendee identity, consent, and registration cards.
- Check-in owns one unique attendance record per attendee per summit day.
- Medical services owns structured screening results and referrals.
- Reports reads attendance, registration, services, and referrals without exposing public medical data.
- Security owns users, roles, sessions, and audit logs.