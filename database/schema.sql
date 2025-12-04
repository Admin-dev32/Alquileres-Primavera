-- Schema for Alquileres Primavera
CREATE DATABASE IF NOT EXISTS alquileres_primavera
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE alquileres_primavera;

CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  business_name VARCHAR(255) DEFAULT 'Alquileres Primavera',
  business_logo VARCHAR(255),
  business_address VARCHAR(255),
  business_phone VARCHAR(50),
  business_whatsapp VARCHAR(50),
  business_email VARCHAR(255),
  iva_percentage DECIMAL(5,2) NOT NULL DEFAULT 13.00,
  default_notes TEXT,
  logo_path VARCHAR(255),
  currency_code VARCHAR(10) DEFAULT 'USD',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  unit_label VARCHAR(50) DEFAULT 'unidad',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  doc_type ENUM('estimate','invoice') NOT NULL,
  doc_number INT NOT NULL,
  doc_code VARCHAR(50),
  public_token VARCHAR(64),
  status ENUM('draft','sent','paid','cancelled') DEFAULT 'draft',
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  document_date DATE NOT NULL,
  client_name VARCHAR(255) NOT NULL,
  client_company VARCHAR(255),
  client_address VARCHAR(255),
  client_phone VARCHAR(50),
  representative VARCHAR(255),
  event_type VARCHAR(255),
  rental_end_date DATE,
  subtotal DECIMAL(10,2) DEFAULT 0,
  tax DECIMAL(10,2) DEFAULT 0,
  total DECIMAL(10,2) DEFAULT 0,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  document_id INT NOT NULL,
  item_name VARCHAR(255) NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL,
  rental_days INT NOT NULL DEFAULT 1,
  line_total DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  document_id INT NOT NULL,
  payment_date DATE NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  method VARCHAR(50) NOT NULL,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  expense_date DATE NOT NULL,
  category VARCHAR(100),
  description VARCHAR(255),
  amount DECIMAL(10,2) NOT NULL,
  payment_method VARCHAR(50),
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_owner TINYINT(1) NOT NULL DEFAULT 0,
  can_view_documents TINYINT(1) NOT NULL DEFAULT 1,
  can_create_documents TINYINT(1) NOT NULL DEFAULT 1,
  can_edit_documents TINYINT(1) NOT NULL DEFAULT 1,
  can_delete_documents TINYINT(1) NOT NULL DEFAULT 1,
  can_manage_payments TINYINT(1) NOT NULL DEFAULT 1,
  can_view_finances TINYINT(1) NOT NULL DEFAULT 1,
  can_manage_settings TINYINT(1) NOT NULL DEFAULT 1,
  can_manage_users TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
