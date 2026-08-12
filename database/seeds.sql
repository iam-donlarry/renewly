-- Renewly Baseline Seeds File

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Insert Roles
INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Super Admin', 'Full access to all system modules, configurations, and permissions'),
(2, 'Account Manager', 'Manages clients, contracts, mid-term seat adjustments, and renewal pipelines'),
(3, 'Billing Specialist', 'Manages payment schedules, collection tracking, and financial ledgers'),
(4, 'Viewer', 'Read-only access to contracts, clients, and dashboards');

-- 2. Insert Permissions Registry
INSERT INTO `permissions` (`id`, `permission_key`, `module`, `description`) VALUES
(1, 'clients.view', 'clients', 'View client companies and details'),
(2, 'clients.manage', 'clients', 'Create and edit client companies'),
(3, 'vendors.view', 'vendors', 'View vendor catalog and products'),
(4, 'vendors.manage', 'vendors', 'Manage vendors and product pricing models'),
(5, 'contracts.view', 'contracts', 'View contract agreements and line items'),
(6, 'contracts.create', 'contracts', 'Create new subscription contracts'),
(7, 'contracts.edit', 'contracts', 'Modify contract details and line items'),
(8, 'contracts.approve', 'contracts', 'Approve or reject contract proposals'),
(9, 'subscriptions.adjust', 'subscriptions', 'Execute seat additions or queue reductions'),
(10, 'renewals.view', 'renewals', 'Access renewal pipeline and forecasting dashboards'),
(11, 'renewals.manage', 'renewals', 'Advance renewal stages and update action items'),
(12, 'payments.view', 'payments', 'View payment installment schedules'),
(13, 'payments.manage', 'payments', 'Mark payment status paid, overdue, or partially paid'),
(14, 'reports.view', 'reports', 'View revenue analytics and renewal forecasting reports'),
(15, 'users.manage', 'users', 'Create and manage system user accounts'),
(16, 'roles.manage', 'roles', 'Configure roles and assign permissions'),
(17, 'settings.manage', 'settings', 'Configure global application preferences');

-- 3. Insert Role Permissions Map
-- Super Admin (Role 1): All permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

-- Account Manager (Role 2): Clients, Contracts, Subscriptions, Renewals, Payments view, Reports view
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(2, 1), (2, 2), (2, 3), (2, 5), (2, 6), (2, 7), (2, 9), (2, 10), (2, 11), (2, 12), (2, 14);

-- Billing Specialist (Role 3): Clients view, Contracts view, Payments view & manage, Reports view
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(3, 1), (3, 3), (3, 5), (3, 10), (3, 12), (3, 13), (3, 14);

-- Viewer (Role 4): Read-only view permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(4, 1), (4, 3), (4, 5), (4, 10), (4, 12);

-- 4. Default Admin User
-- Email: admin@renewly.com | Password: password123 ($2y$10$e.0u1vVzYd41c9A7uW8u5.x0Y0/7m...)
INSERT INTO `users` (`id`, `role_id`, `first_name`, `last_name`, `email`, `password_hash`, `status`) VALUES
(1, 1, 'System', 'Admin', 'admin@renewly.com', '$2y$10$x9ZenzRqJEkVBtd6Gs5tTeziMxjv.8/arMEpMKlhOSwRwQEziNN2.', 'active'),
(2, 2, 'Account', 'Manager', 'am@renewly.com', '$2y$10$x9ZenzRqJEkVBtd6Gs5tTeziMxjv.8/arMEpMKlhOSwRwQEziNN2.', 'active');

-- 5. Default Vendors
INSERT INTO `vendors` (`id`, `vendor_name`, `website`, `support_email`, `status`) VALUES
(1, 'Microsoft Corporation', 'https://microsoft.com', 'support@microsoft.com', 'active'),
(2, 'Adobe Inc.', 'https://adobe.com', 'support@adobe.com', 'active'),
(3, 'Amazon Web Services', 'https://aws.amazon.com', 'support@aws.com', 'active'),
(4, 'Zoom Video Communications', 'https://zoom.us', 'support@zoom.us', 'active');

-- 6. Default Products
INSERT INTO `products` (`id`, `vendor_id`, `product_name`, `pricing_model`, `default_unit_cost`, `currency`) VALUES
(1, 1, 'Microsoft 365 Business Premium', 'per_seat', 22.0000, 'USD'),
(2, 1, 'Microsoft 365 Business Standard', 'per_seat', 12.5000, 'USD'),
(3, 1, 'Exchange Online (Plan 1)', 'per_seat', 4.0000, 'USD'),
(4, 2, 'Adobe Creative Cloud All Apps', 'per_seat', 55.0000, 'USD'),
(5, 2, 'Acrobat Pro for Teams', 'per_seat', 15.0000, 'USD'),
(6, 3, 'AWS Production Server (c5.xlarge)', 'flat_rate', 450.0000, 'USD'),
(7, 4, 'Zoom One Pro', 'per_seat', 14.9900, 'USD');

-- 7. Sample Clients
INSERT INTO `clients` (`id`, `company_name`, `account_manager_id`, `primary_contact_name`, `primary_contact_email`, `primary_contact_phone`, `status`) VALUES
(1, 'Baye Business Solutions', 2, 'Adekunle Quadri', 'adekunle@bayebusiness.com', '+2348012345678', 'active'),
(2, 'SoftPlus InfoTech', 2, 'Info Team', 'info@softplusinfotechsolutions.com', '+2348023456789', 'active'),
(3, 'Alterverse Group', 2, 'Operations Manager', 'info@alterversegroup.com', '+2348034567890', 'active');

-- 8. Sample App Settings
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES
('global_exchange_rate', '1550.00'),
('default_currency', 'USD'),
('default_grace_period', '7'),
('allowed_currencies', '["USD", "NGN", "EUR", "GBP"]');

SET FOREIGN_KEY_CHECKS = 1;
