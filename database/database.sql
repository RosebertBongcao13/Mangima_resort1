-- Run this file to create database and tables, then insert sample data
CREATE DATABASE IF NOT EXISTS mangima_resort;
USE mangima_resort;

-- =====================================================
-- DROP EXISTING TABLES
-- Drops tables in reverse order to avoid foreign key errors
-- =====================================================

DROP TABLE IF EXISTS reservation_amenities;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS amenities;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS users;

-- =====================================================
-- USERS TABLE
-- Stores account information for admin, staff, and users
-- =====================================================

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    contact_number VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role ENUM('user','staff','admin') DEFAULT 'user'
);

-- =====================================================
-- ROOMS TABLE
-- Stores resort room information
-- =====================================================

CREATE TABLE rooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    room_name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL,
    capacity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    status ENUM('available','occupied','maintenance') DEFAULT 'available'
);

-- =====================================================
-- RESERVATIONS TABLE
-- Stores booking details made by users
-- =====================================================

CREATE TABLE reservations (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    room_id INT NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('pending','confirmed','cancelled','checked_in','checked_out') DEFAULT 'pending',

    -- Added guest information columns
    guest_name VARCHAR(150) NULL,
    guest_contact VARCHAR(50) NULL,
    guest_email VARCHAR(150) NULL,

    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE
);

CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    payment_status ENUM('paid','unpaid') DEFAULT 'unpaid',
    payment_date DATETIME,
    FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id) ON DELETE CASCADE
);

CREATE TABLE amenities (
    amenity_id INT AUTO_INCREMENT PRIMARY KEY,
    amenity_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL
);

CREATE TABLE reservation_amenities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    amenity_id INT NOT NULL,
    FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id) ON DELETE CASCADE,
    FOREIGN KEY (amenity_id) REFERENCES amenities(amenity_id) ON DELETE CASCADE
);

-- Sample data
INSERT INTO users (full_name, email, contact_number, password, role) VALUES
('Admin User', 'admin@resort.com', '09123456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Staff User', 'staff@resort.com', '09234567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff'),
('Regular User', 'user@resort.com', '09345678901', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user');

INSERT INTO rooms (room_name, type, capacity, price, status) VALUES
('Deluxe Ocean View', 'Deluxe', 2, 5000.00, 'available'),
('Standard Garden', 'Standard', 2, 2500.00, 'available'),
('Family Suite', 'Suite', 4, 8000.00, 'available'),
('Single Budget', 'Budget', 1, 1500.00, 'maintenance');

INSERT INTO amenities (amenity_name, price) VALUES
('Breakfast Buffet', 350.00),
('Airport Shuttle', 500.00),
('Spa Access', 1200.00),
('Late Checkout', 400.00);

-- A sample reservation
INSERT INTO reservations (
    user_id,
    room_id,
    check_in,
    check_out,
    total_price,
    status,
    guest_name,
    guest_contact,
    guest_email
) VALUES (
    3,
    1,
    '2026-05-10',
    '2026-05-12',
    10000.00,
    'confirmed',
    'Juan Dela Cruz',
    '09123456789',
    'juan@email.com'
);

INSERT INTO reservation_amenities (reservation_id, amenity_id)
VALUES (1, 1), (1, 2);

INSERT INTO payments (
    reservation_id,
    amount,
    payment_method,
    payment_status,
    payment_date
) VALUES (
    1,
    10000.00,
    'Credit Card',
    'paid',
    '2026-05-04 10:30:00'
);