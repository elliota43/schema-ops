-- docker/init/01-schema.sql

CREATE TABLE IF NOT EXISTS `legacy_users` (
`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
`email` VARCHAR(255) NOT NULL UNIQUE,
`full_name` VARCHAR(100),
`is_active` TINYINT(1) DEFAULT 1,
`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
`bio` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `legacy_users` (`email`, `full_name`, `is_active`, `bio`) VALUES
('alice@example.com', 'Alice Engineer', 1, 'Loves building parsers.'),
('bob@example.com', 'Bob Builder', 0, NULL),
('charlie@example.com', 'Charlie Root', 1, 'System Administrator.');