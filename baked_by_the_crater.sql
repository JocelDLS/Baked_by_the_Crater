-- ---------------------------------------------
-- Database: baked_by_the_crater
-- ---------------------------------------------
CREATE DATABASE IF NOT EXISTS baked_by_the_crater;
USE baked_by_the_crater;

-- ---------------------------------------------
-- Table: users
-- ---------------------------------------------
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    is_verified TINYINT(1) DEFAULT 0,
    provider ENUM('local', 'google') DEFAULT 'local',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL,

    FULLTEXT (first_name, last_name, email)
) ENGINE=InnoDB;

-- ---------------------------------------------
-- Table: admins
-- ---------------------------------------------
CREATE TABLE admins (
    admin_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(150) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------
-- Table: subscribers
-- ---------------------------------------------
CREATE TABLE subscribers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    subscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FULLTEXT (email)
) ENGINE=InnoDB;

-- ---------------------------------------------
-- Table: full_texts (chat/messages)
-- ---------------------------------------------
CREATE TABLE full_texts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    message_text TEXT NOT NULL,
    sender_type ENUM('admin', 'customer') NOT NULL,
    message_type ENUM('text', 'file') DEFAULT 'text',
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,

    FULLTEXT (message_text),

    CONSTRAINT fk_fulltexts_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------
-- End of schema
-- ---------------------------------------------