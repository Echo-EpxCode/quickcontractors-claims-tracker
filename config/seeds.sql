-- ============================================================================
-- QuickContractors Claims Tracker System V1.0
-- Initial Seed Data Script
-- ============================================================================

-- Seed Claim Types
INSERT INTO claim_types (type_name) VALUES 
('Leak'), 
('Damage'), 
('Outdoor Damage');

-- Seed Claim Statuses
INSERT INTO claim_statuses (status_name) VALUES 
('Open'), 
('In_Progress'), 
('Waiting'), 
('Resolved'), 
('Closed');

-- Seed Initial Default Users (Password for all: 'Password123!' -> hashed with password_hash)
-- Director: director@quickcontractors.com
-- Supervisor: supervisor@quickcontractors.com
-- Adjuster: adjuster@quickcontractors.com
INSERT INTO users (username, password_hash, full_name, email, role, is_active) VALUES 
('director_admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Director', 'director@quickcontractors.com', 'Director', 1),
('supervisor_lead', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Operations Supervisor', 'supervisor@quickcontractors.com', 'Supervisor', 1),
('adjuster_john', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Adjuster', 'adjuster@quickcontractors.com', 'Claim_Adjuster', 1);

-- Seed Initial Sample Store
INSERT INTO stores (store_code, store_name, address, is_active) VALUES 
('HD-1001', 'Home Depot - Store #1001', '123 Contractor Way, Toronto, ON', 1);