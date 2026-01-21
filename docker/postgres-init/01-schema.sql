-- docker/postgres-init/01-schema.sql

CREATE TABLE IF NOT EXISTS legacy_users (
    id BIGSERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    full_name VARCHAR(100),
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    bio TEXT NULL
);

INSERT INTO legacy_users (email, full_name, is_active, bio) VALUES
('alice@example.com', 'Alice Engineer', true, 'Loves building parsers.'),
('bob@example.com', 'Bob Builder', false, NULL),
('charlie@example.com', 'Charlie Root', true, 'System Administrator.');
