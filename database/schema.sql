-- ====================================================================
-- BARANGAY DRRM DISASTER OPERATIONS CONSOLE - POSTGRESQL SCHEMA
-- Target Database: PostgreSQL 14+ / 15+ / 16+
-- Timezone: Asia/Manila (UTC+8)
-- ====================================================================

-- 1. Official Barangay Accounts
CREATE TABLE IF NOT EXISTS official_accounts (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    barangay VARCHAR(50) NOT NULL CHECK (barangay IN ('Buang', 'Nagsipit')),
    official_name VARCHAR(150) NOT NULL,
    position VARCHAR(150) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    security_question TEXT NOT NULL,
    security_answer TEXT NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_official_accounts_barangay ON official_accounts (barangay);

-- 2. Evacuation Centers
CREATE TABLE IF NOT EXISTS evacuation_centers (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    barangay VARCHAR(50) NOT NULL CHECK (barangay IN ('Buang', 'Nagsipit')),
    purok VARCHAR(50) NOT NULL,
    capacity_families INT NOT NULL DEFAULT 0,
    assigned_count INT NOT NULL DEFAULT 0,
    location JSONB NOT NULL DEFAULT '{"lat": 0.0, "lng": 0.0}',
    manager_name VARCHAR(150) NOT NULL,
    manager_contact VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Open' CHECK (status IN ('Open', 'Full', 'Standby')),
    facilities JSONB NOT NULL DEFAULT '[]',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_evac_centers_barangay ON evacuation_centers (barangay);
CREATE INDEX IF NOT EXISTS idx_evac_centers_status ON evacuation_centers (status);

-- 3. Registered Households
CREATE TABLE IF NOT EXISTS households (
    id VARCHAR(50) PRIMARY KEY,
    head_of_family VARCHAR(150) NOT NULL,
    birthday DATE NULL,
    age INT NOT NULL,
    contact_number VARCHAR(50) NOT NULL,
    number_of_members INT NOT NULL DEFAULT 1,
    house_number VARCHAR(50) NULL,
    purok VARCHAR(50) NOT NULL,
    barangay VARCHAR(50) NOT NULL CHECK (barangay IN ('Buang', 'Nagsipit')),
    complete_address TEXT NOT NULL,
    location JSONB NOT NULL DEFAULT '{"lat": 0.0, "lng": 0.0}',
    status VARCHAR(20) NOT NULL DEFAULT 'SAFE' CHECK (status IN ('SAFE', 'MODERATE', 'EMERGENCY')),
    status_note TEXT NULL,
    evacuation_center_id VARCHAR(50) NULL REFERENCES evacuation_centers(id) ON DELETE SET NULL,
    verification_status VARCHAR(20) NOT NULL DEFAULT 'Pending' CHECK (verification_status IN ('Pending', 'Verified')),
    special_needs JSONB NOT NULL DEFAULT '[]',
    family_members JSONB NOT NULL DEFAULT '[]',
    last_status_update TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_households_barangay ON households (barangay);
CREATE INDEX IF NOT EXISTS idx_households_purok ON households (purok);
CREATE INDEX IF NOT EXISTS idx_households_status ON households (status);
CREATE INDEX IF NOT EXISTS idx_households_verification ON households (verification_status);
CREATE INDEX IF NOT EXISTS idx_households_evac_center ON households (evacuation_center_id);
CREATE INDEX IF NOT EXISTS idx_households_special_needs ON households USING gin (special_needs);
CREATE INDEX IF NOT EXISTS idx_households_family_members ON households USING gin (family_members);

-- 4. Relief Goods Distributions
CREATE TABLE IF NOT EXISTS relief_distributions (
    id VARCHAR(50) PRIMARY KEY,
    household_id VARCHAR(50) NOT NULL REFERENCES households(id) ON DELETE CASCADE,
    recipient_name VARCHAR(150) NOT NULL,
    barangay VARCHAR(50) NOT NULL CHECK (barangay IN ('Buang', 'Nagsipit')),
    purok VARCHAR(50) NOT NULL,
    item VARCHAR(150) NOT NULL,
    qty VARCHAR(50) NOT NULL,
    location VARCHAR(150) NOT NULL,
    distributed_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    officer_name VARCHAR(150) NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_relief_household ON relief_distributions (household_id);
CREATE INDEX IF NOT EXISTS idx_relief_barangay ON relief_distributions (barangay);

-- 5. Operational Activity & Audit Logs
CREATE TABLE IF NOT EXISTS activity_logs (
    id VARCHAR(60) PRIMARY KEY,
    timestamp TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    action_type VARCHAR(50) NOT NULL,
    actor_name VARCHAR(150) NOT NULL,
    actor_role VARCHAR(100) NOT NULL,
    barangay VARCHAR(50) NOT NULL CHECK (barangay IN ('Buang', 'Nagsipit')),
    target VARCHAR(150) NOT NULL,
    details TEXT NOT NULL,
    is_working_hours BOOLEAN NOT NULL DEFAULT true,
    work_shift_tag VARCHAR(50) NOT NULL DEFAULT 'Standard Working Hours (8:00 AM - 5:00 PM)',
    terminal VARCHAR(100) DEFAULT 'Main Barangay Hall Terminal',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_activity_logs_barangay ON activity_logs (barangay);
CREATE INDEX IF NOT EXISTS idx_activity_logs_action ON activity_logs (action_type);
CREATE INDEX IF NOT EXISTS idx_activity_logs_shift ON activity_logs (is_working_hours);
CREATE INDEX IF NOT EXISTS idx_activity_logs_timestamp ON activity_logs (timestamp DESC);
