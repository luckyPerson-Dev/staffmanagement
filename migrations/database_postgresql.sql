-- ============================================
-- STAFF MANAGEMENT SYSTEM - POSTGRESQL VERSION
-- ============================================
-- Converted from MySQL for PostgreSQL compatibility
-- ============================================

-- ============================================
-- SECTION 1: CORE TABLES
-- ============================================

-- Users table
CREATE TABLE IF NOT EXISTS users (
  id SERIAL PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'staff' CHECK (role IN ('superadmin','admin','staff','accountant','hr','auditor','finance','supervisor')),
  status VARCHAR(50) DEFAULT 'active' CHECK (status IN ('active','suspended','banned','inactive')),
  status_reason TEXT DEFAULT NULL,
  status_changed_at TIMESTAMP NULL DEFAULT NULL,
  status_changed_by INTEGER DEFAULT NULL,
  monthly_salary DECIMAL(10,2) DEFAULT 0.00,
  two_factor_enabled BOOLEAN DEFAULT FALSE,
  two_factor_secret VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  deleted_at TIMESTAMP NULL DEFAULT NULL
);

CREATE INDEX idx_email ON users(email);
CREATE INDEX idx_role ON users(role);
CREATE INDEX idx_status ON users(status);
CREATE INDEX idx_deleted ON users(deleted_at);

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
  id SERIAL PRIMARY KEY,
  key VARCHAR(100) NOT NULL UNIQUE,
  value TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL
);

CREATE INDEX idx_key ON settings(key);

-- Customers table
CREATE TABLE IF NOT EXISTS customers (
  id SERIAL PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  whatsapp_group_link VARCHAR(500) DEFAULT NULL,
  ticket_penalty_percent DECIMAL(5,2) DEFAULT NULL,
  group_miss_penalty_percent DECIMAL(5,2) DEFAULT NULL,
  group_partial_penalty_percent DECIMAL(5,2) DEFAULT NULL,
  group_partial_ratio DECIMAL(5,2) DEFAULT NULL,
  group_miss_ratio DECIMAL(5,2) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  deleted_at TIMESTAMP NULL DEFAULT NULL
);

CREATE INDEX idx_deleted ON customers(deleted_at);
CREATE INDEX idx_penalties ON customers(ticket_penalty_percent, group_miss_penalty_percent);

-- Customer groups table
CREATE TABLE IF NOT EXISTS customer_groups (
  id SERIAL PRIMARY KEY,
  customer_id INTEGER NOT NULL,
  name VARCHAR(255) NOT NULL,
  whatsapp_group_link VARCHAR(500) DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'active' CHECK (status IN ('active','inactive')),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

CREATE INDEX idx_customer ON customer_groups(customer_id);
CREATE INDEX idx_deleted ON customer_groups(deleted_at);

-- Teams table
CREATE TABLE IF NOT EXISTS teams (
  id SERIAL PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  deleted_at TIMESTAMP NULL DEFAULT NULL
);

CREATE INDEX idx_deleted ON teams(deleted_at);

-- Team members table
CREATE TABLE IF NOT EXISTS team_members (
  id SERIAL PRIMARY KEY,
  team_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (team_id, user_id),
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_team ON team_members(team_id);
CREATE INDEX idx_user ON team_members(user_id);

-- ============================================
-- SECTION 2: PROGRESS & ATTENDANCE
-- ============================================

-- Daily progress table
CREATE TABLE IF NOT EXISTS daily_progress (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL,
  date DATE NOT NULL,
  tickets_missed INTEGER DEFAULT 0,
  groups_status JSONB DEFAULT NULL,
  customer_id INTEGER DEFAULT NULL,
  notes TEXT,
  progress_percent DECIMAL(5,2) DEFAULT 0.00,
  is_missed BOOLEAN DEFAULT FALSE,
  is_overtime BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE (user_id, date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

CREATE INDEX idx_user ON daily_progress(user_id);
CREATE INDEX idx_date ON daily_progress(date);
CREATE INDEX idx_customer ON daily_progress(customer_id);
CREATE INDEX idx_is_missed ON daily_progress(is_missed);
CREATE INDEX idx_is_overtime ON daily_progress(is_overtime);

-- Attendance table
CREATE TABLE IF NOT EXISTS attendance (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL,
  date DATE NOT NULL,
  clock_in TIME DEFAULT NULL,
  clock_out TIME DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'present' CHECK (status IN ('present','absent','late','half_day')),
  qr_code VARCHAR(255) DEFAULT NULL,
  device_info VARCHAR(255) DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  location_lat DECIMAL(10,8) DEFAULT NULL,
  location_lng DECIMAL(11,8) DEFAULT NULL,
  attendance_method VARCHAR(50) DEFAULT 'manual' CHECK (attendance_method IN ('manual','qr_code','device')),
  notes TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE (user_id, date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_user ON attendance(user_id);
CREATE INDEX idx_date ON attendance(date);

-- Attendance QR codes table
CREATE TABLE IF NOT EXISTS attendance_qr_codes (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL,
  qr_code VARCHAR(255) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  used BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_user ON attendance_qr_codes(user_id);
CREATE INDEX idx_qr ON attendance_qr_codes(qr_code);

-- ============================================
-- SECTION 3: FINANCIAL MANAGEMENT
-- ============================================

-- Advances table
CREATE TABLE IF NOT EXISTS advances (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  reason TEXT,
  status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected')),
  approved_by INTEGER DEFAULT NULL,
  approved_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_user ON advances(user_id);
CREATE INDEX idx_status ON advances(status);
CREATE INDEX idx_approved_by ON advances(approved_by);

-- Advance auto-deductions table
CREATE TABLE IF NOT EXISTS advance_auto_deductions (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL,
  monthly_deduction DECIMAL(10,2) NOT NULL,
  total_advance DECIMAL(10,2) NOT NULL,
  remaining_due DECIMAL(10,2) NOT NULL,
  status VARCHAR(50) DEFAULT 'active' CHECK (status IN ('active','completed')),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_user_id ON advance_auto_deductions(user_id);
CREATE INDEX idx_status ON advance_auto_deductions(status);

-- Salary history table
CREATE TABLE IF NOT EXISTS salary_history (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL,
  month INTEGER NOT NULL,
  year INTEGER NOT NULL,
  gross_salary DECIMAL(10,2) NOT NULL,
  profit_fund DECIMAL(10,2) DEFAULT 0.00,
  monthly_progress DECIMAL(5,2) DEFAULT 0.00,
  payable_before_advance DECIMAL(10,2) DEFAULT 0.00,
  advances_deducted DECIMAL(10,2) DEFAULT 0.00,
  net_payable DECIMAL(10,2) DEFAULT 0.00,
  status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending','approved','paid')),
  approved_by INTEGER DEFAULT NULL,
  approved_at TIMESTAMP NULL DEFAULT NULL,
  paid_at TIMESTAMP NULL DEFAULT NULL,
  payment_method VARCHAR(50) DEFAULT NULL,
  transaction_ref VARCHAR(255) DEFAULT NULL,
  payslip_path VARCHAR(500) DEFAULT NULL,
  payslip_generated_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE (user_id, month, year),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_user ON salary_history(user_id);
CREATE INDEX idx_month_year ON salary_history(month, year);
CREATE INDEX idx_status ON salary_history(status);

-- Payroll run log table
CREATE TABLE IF NOT EXISTS payroll_run_log (
  id SERIAL PRIMARY KEY,
  month SMALLINT NOT NULL,
  year INTEGER NOT NULL,
  run_by INTEGER NOT NULL,
  total_staff_processed INTEGER NOT NULL DEFAULT 0,
  total_salary_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (run_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_month_year ON payroll_run_log(month, year);
CREATE INDEX idx_run_by ON payroll_run_log(run_by);
CREATE INDEX idx_created_at ON payroll_run_log(created_at);

-- Profit fund table
CREATE TABLE IF NOT EXISTS profit_fund (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL,
  month SMALLINT NOT NULL,
  year INTEGER NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE (user_id, month, year),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_user ON profit_fund(user_id);
CREATE INDEX idx_month_year ON profit_fund(month, year);

-- Profit fund balance table
CREATE TABLE IF NOT EXISTS profit_fund_balance (
  user_id INTEGER NOT NULL PRIMARY KEY,
  balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Profit fund withdrawals table
CREATE TABLE IF NOT EXISTS profit_fund_withdrawals (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  approved_by INTEGER DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'requested' CHECK (status IN ('requested','approved','rejected','paid')),
  note TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_user ON profit_fund_withdrawals(user_id);

-- Monthly tickets table
CREATE TABLE IF NOT EXISTS monthly_tickets (
  id SERIAL PRIMARY KEY,
  month SMALLINT NOT NULL,
  year INTEGER NOT NULL,
  total_tickets INTEGER NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE (month, year)
);

CREATE INDEX idx_month_year ON monthly_tickets(month, year);

-- Staff tickets table
CREATE TABLE IF NOT EXISTS staff_tickets (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL,
  month SMALLINT NOT NULL,
  year INTEGER NOT NULL,
  ticket_count INTEGER NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE (user_id, month, year),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_user ON staff_tickets(user_id);
CREATE INDEX idx_month_year ON staff_tickets(month, year);

-- ============================================
-- SECTION 4: ADDITIONAL FEATURES
-- ============================================

-- Bonuses table
CREATE TABLE IF NOT EXISTS bonuses (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency VARCHAR(3) DEFAULT 'USD',
  reason TEXT,
  month INTEGER NOT NULL,
  year INTEGER NOT NULL,
  status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending','approved','paid')),
  approved_by INTEGER DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_user ON bonuses(user_id);
CREATE INDEX idx_month_year ON bonuses(month, year);

-- Loans table
CREATE TABLE IF NOT EXISTS loans (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency VARCHAR(3) DEFAULT 'USD',
  interest_rate DECIMAL(5,2) DEFAULT 0.00,
  installments INTEGER DEFAULT 1,
  monthly_payment DECIMAL(10,2) DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending','approved','active','completed','defaulted')),
  approved_by INTEGER DEFAULT NULL,
  start_date DATE DEFAULT NULL,
  end_date DATE DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_user ON loans(user_id);

-- Loan payments table
CREATE TABLE IF NOT EXISTS loan_payments (
  id SERIAL PRIMARY KEY,
  loan_id INTEGER NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_date DATE NOT NULL,
  status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending','paid')),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
);

CREATE INDEX idx_loan ON loan_payments(loan_id);

-- Expenses table
CREATE TABLE IF NOT EXISTS expenses (
  id SERIAL PRIMARY KEY,
  user_id INTEGER DEFAULT NULL,
  category VARCHAR(100) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency VARCHAR(3) DEFAULT 'USD',
  description TEXT,
  receipt_path VARCHAR(500) DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected')),
  approved_by INTEGER DEFAULT NULL,
  expense_date DATE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_user ON expenses(user_id);
CREATE INDEX idx_status ON expenses(status);

-- ============================================
-- SECTION 5: SYSTEM & COMMUNICATION
-- ============================================

-- Audit logs table
CREATE TABLE IF NOT EXISTS audit_logs (
  id SERIAL PRIMARY KEY,
  user_id INTEGER DEFAULT NULL,
  action VARCHAR(100) NOT NULL,
  resource VARCHAR(100) NOT NULL,
  resource_id INTEGER DEFAULT NULL,
  details TEXT,
  before_snapshot JSONB DEFAULT NULL,
  after_snapshot JSONB DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_user ON audit_logs(user_id);
CREATE INDEX idx_action ON audit_logs(action);
CREATE INDEX idx_resource ON audit_logs(resource);
CREATE INDEX idx_created ON audit_logs(created_at);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL,
  type VARCHAR(50) DEFAULT 'info' CHECK (type IN ('info','success','warning','error')),
  title VARCHAR(255) NOT NULL,
  message TEXT,
  link VARCHAR(500) DEFAULT NULL,
  read BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_user ON notifications(user_id);
CREATE INDEX idx_read ON notifications(read);
CREATE INDEX idx_created ON notifications(created_at);

-- Messages table
CREATE TABLE IF NOT EXISTS messages (
  id SERIAL PRIMARY KEY,
  from_user_id INTEGER NOT NULL,
  to_user_id INTEGER NOT NULL,
  subject VARCHAR(255) DEFAULT NULL,
  message TEXT NOT NULL,
  read BOOLEAN DEFAULT FALSE,
  read_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_from ON messages(from_user_id);
CREATE INDEX idx_to ON messages(to_user_id);
CREATE INDEX idx_read ON messages(read);

-- Message attachments table
CREATE TABLE IF NOT EXISTS message_attachments (
  id SERIAL PRIMARY KEY,
  message_id INTEGER NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_size INTEGER DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
);

CREATE INDEX idx_message ON message_attachments(message_id);

-- Documents table
CREATE TABLE IF NOT EXISTS documents (
  id SERIAL PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_type VARCHAR(50) DEFAULT NULL,
  file_size INTEGER DEFAULT NULL,
  category VARCHAR(100) DEFAULT NULL,
  tags JSONB DEFAULT NULL,
  user_id INTEGER DEFAULT NULL,
  staff_id INTEGER DEFAULT NULL,
  visibility VARCHAR(50) DEFAULT 'private' CHECK (visibility IN ('public','private','staff_only')),
  created_by INTEGER DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_user ON documents(user_id);
CREATE INDEX idx_staff ON documents(staff_id);
CREATE INDEX idx_category ON documents(category);

-- Support tickets table
CREATE TABLE IF NOT EXISTS support_tickets (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  type VARCHAR(50) DEFAULT 'other' CHECK (type IN ('bug','feature','question','other')),
  priority VARCHAR(50) DEFAULT 'medium' CHECK (priority IN ('low','medium','high','urgent')),
  status VARCHAR(50) DEFAULT 'open' CHECK (status IN ('open','in_progress','resolved','closed')),
  assigned_to INTEGER DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_user ON support_tickets(user_id);
CREATE INDEX idx_status ON support_tickets(status);
CREATE INDEX idx_priority ON support_tickets(priority);

-- Support ticket replies table
CREATE TABLE IF NOT EXISTS support_ticket_replies (
  id SERIAL PRIMARY KEY,
  ticket_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  reply TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_ticket ON support_ticket_replies(ticket_id);

-- ============================================
-- SECTION 6: SECURITY & AUTHENTICATION
-- ============================================

-- User IP restrictions table
CREATE TABLE IF NOT EXISTS user_ip_restrictions (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_user ON user_ip_restrictions(user_id);

-- Remember tokens table
CREATE TABLE IF NOT EXISTS remember_tokens (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL,
  token VARCHAR(64) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_token ON remember_tokens(token);
CREATE INDEX idx_user ON remember_tokens(user_id);

-- ============================================
-- SECTION 7: PERMISSIONS & RBAC
-- ============================================

-- Permissions table
CREATE TABLE IF NOT EXISTS permissions (
  id SERIAL PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT,
  resource VARCHAR(100) NOT NULL,
  action VARCHAR(50) NOT NULL
);

CREATE INDEX idx_resource ON permissions(resource);

-- Role permissions table
CREATE TABLE IF NOT EXISTS role_permissions (
  id SERIAL PRIMARY KEY,
  role VARCHAR(50) NOT NULL,
  permission_id INTEGER NOT NULL,
  UNIQUE (role, permission_id),
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- ============================================
-- SECTION 8: ANALYTICS & AI
-- ============================================

-- Analytics cache table
CREATE TABLE IF NOT EXISTS analytics_cache (
  id SERIAL PRIMARY KEY,
  cache_key VARCHAR(255) NOT NULL UNIQUE,
  cache_data JSONB NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_key ON analytics_cache(cache_key);
CREATE INDEX idx_expires ON analytics_cache(expires_at);

-- AI insights table
CREATE TABLE IF NOT EXISTS ai_insights (
  id SERIAL PRIMARY KEY,
  user_id INTEGER DEFAULT NULL,
  type VARCHAR(50) NOT NULL,
  insight TEXT NOT NULL,
  confidence DECIMAL(5,2) DEFAULT NULL,
  data JSONB DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_user ON ai_insights(user_id);
CREATE INDEX idx_type ON ai_insights(type);

-- ============================================
-- SECTION 9: TEAM MANAGEMENT
-- ============================================

-- Team objectives/KPIs table
CREATE TABLE IF NOT EXISTS team_objectives (
  id SERIAL PRIMARY KEY,
  team_id INTEGER NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  target_value DECIMAL(10,2) DEFAULT NULL,
  current_value DECIMAL(10,2) DEFAULT 0.00,
  deadline DATE DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'active' CHECK (status IN ('active','completed','cancelled')),
  created_by INTEGER DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_team ON team_objectives(team_id);

-- ============================================
-- SECTION 10: BULK OPERATIONS
-- ============================================

-- Bulk payments table
CREATE TABLE IF NOT EXISTS bulk_payments (
  id SERIAL PRIMARY KEY,
  uploaded_by INTEGER NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  total_records INTEGER DEFAULT 0,
  processed_records INTEGER DEFAULT 0,
  status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending','processing','completed','failed')),
  errors JSONB DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_uploaded_by ON bulk_payments(uploaded_by);

-- ============================================
-- SECTION 11: DEFAULT DATA
-- ============================================

-- Insert default system settings
INSERT INTO settings (key, value) VALUES
('ticket_penalty_percent', '5'),
('group_miss_percent', '10'),
('group_partial_percent', '5'),
('group_partial_ratio', '0.5'),
('group_miss_ratio', '0.0'),
('profit_fund_percent', '5'),
('working_days_per_month', '26'),
('missing_day_treated_as', '0'),
('attendance_penalty_enabled', '0'),
('daily_penalty_base', 'auto'),
('support_day_penalty_percent', '10'),
('global_support_penalty_percent', '0'),
('website_name', 'Staff Management System'),
('website_logo', ''),
('website_favicon', ''),
('website_email', ''),
('website_phone', ''),
('website_address', ''),
('website_timezone', 'UTC'),
('website_language', 'en')
ON CONFLICT (key) DO NOTHING;

-- Insert default permissions
INSERT INTO permissions (name, description, resource, action) VALUES
('users.create', 'Create users', 'users', 'create'),
('users.read', 'View users', 'users', 'read'),
('users.update', 'Update users', 'users', 'update'),
('users.delete', 'Delete users', 'users', 'delete'),
('payroll.run', 'Run payroll', 'payroll', 'run'),
('payroll.approve', 'Approve payroll', 'payroll', 'approve'),
('settings.manage', 'Manage settings', 'settings', 'manage'),
('reports.view', 'View reports', 'reports', 'view'),
('reports.export', 'Export reports', 'reports', 'export'),
('analytics.view', 'View analytics', 'analytics', 'view')
ON CONFLICT (name) DO NOTHING;

-- Add foreign key constraint for users.status_changed_by (after users table exists)
ALTER TABLE users ADD CONSTRAINT fk_status_changed_by 
  FOREIGN KEY (status_changed_by) REFERENCES users(id) ON DELETE SET NULL;

-- ============================================
-- INSTALLATION NOTES
-- ============================================
-- 
-- 1. This file creates all necessary database tables for the Staff Management System
-- 2. No demo data is included - this is a clean installation
-- 3. After running this file, you need to:
--    a. Create a superadmin account (via installation wizard or manually)
--    b. Configure system settings as needed
--    c. Add users, customers, and teams through the admin panel
--
-- 4. All tables use PostgreSQL native types
-- 5. JSONB is used for JSON data (better performance than JSON)
-- 6. Foreign key constraints are enabled for data integrity
--
-- ============================================

