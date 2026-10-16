-- =====================================================================
-- CivicLens — Seed Data
-- Database: civiclens_db
-- NOTE: Does NOT contain seeded administrator account for security.
-- Use `setup_admin.php` on first run to provision the initial superadmin.
-- =====================================================================

USE `civiclens_db`;

-- ---------------------------------------------------------------------
-- 1. Default Advertising Packages
-- ---------------------------------------------------------------------
INSERT INTO `advertising_packages` (`id`, `name`, `duration_days`, `price`, `description`, `impressions_estimate`, `is_active`) VALUES
(1, 'Starter Local', 7, 799.00, 'Ideal for neighborhood announcements, local openings, and small business spotlight for 7 days.', 3500, 1),
(2, 'Community Growth', 30, 2499.00, 'Our most popular package. High visibility across civic portal pages for 30 consecutive days.', 18000, 1),
(3, 'Regional Premium', 90, 5999.00, 'Maximum regional reach with top-tier banner placement across all local service zones for 90 days.', 60000, 1)
ON DUPLICATE KEY UPDATE `price` = VALUES(`price`);

-- ---------------------------------------------------------------------
-- 2. Sample Certified Municipal Authorities
-- Password for all sample authorities: Authority#2026
-- Hash: $2y$10$wK1Wq6hC7nJqKqI1y0Q0euL4U8d.F4U3x4Ie8jF9z2JqI8m1K2L3. (placeholder standard bcrypt)
-- ---------------------------------------------------------------------
INSERT INTO `authorities` (`id`, `department`, `official_name`, `designation`, `email`, `phone`, `password_hash`, `jurisdiction`, `is_active`) VALUES
(1, 'Kerala Water Authority (KWA)', 'Er. Rajeev Nambiar', 'Superintending Engineer', 'kwa.officer@civiclens.local', '+91 9847000101', '$2y$10$5M/qf1i6x3E4V3m0u4GvkePzU1L7n2I0Y6C4F1A8k0V2n5B8z6I4.', 'District Central & North Zones', 1),
(2, 'Public Works Department (PWD Roads)', 'Er. Deepa Thomas', 'Executive Engineer', 'pwd.roads@civiclens.local', '+91 9847000102', '$2y$10$5M/qf1i6x3E4V3m0u4GvkePzU1L7n2I0Y6C4F1A8k0V2n5B8z6I4.', 'State Highways & Municipal Major Arteries', 1),
(3, 'Municipal Solid Waste Management (Haritha Karma)', 'Biju Narayanan', 'Chief Sanitation Inspector', 'waste.mgmt@civiclens.local', '+91 9847000103', '$2y$10$5M/qf1i6x3E4V3m0u4GvkePzU1L7n2I0Y6C4F1A8k0V2n5B8z6I4.', 'Urban Sanitation Wards 1-45', 1),
(4, 'Food Safety & Hygiene Department', 'Dr. Suma Nair', 'Assistant Food Safety Commissioner', 'food.safety@civiclens.local', '+91 9847000104', '$2y$10$5M/qf1i6x3E4V3m0u4GvkePzU1L7n2I0Y6C4F1A8k0V2n5B8z6I4.', 'Municipal Food & Hospitality Establishments', 1),
(5, 'Excise & Anti-Narcotics Special Squad (Operation Thoofan)', 'K. Mohandas', 'Circle Inspector of Excise', 'thoofan.squad@civiclens.local', '+91 9847000105', '$2y$10$5M/qf1i6x3E4V3m0u4GvkePzU1L7n2I0Y6C4F1A8k0V2n5B8z6I4.', 'Special Enforcement Narcotics Zone', 1)
ON DUPLICATE KEY UPDATE `official_name` = VALUES(`official_name`);

-- ---------------------------------------------------------------------
-- 3. Sample Authority Subscriptions (₹5,000 / year active)
-- ---------------------------------------------------------------------
INSERT INTO `authority_subscriptions` (`authority_id`, `plan_name`, `amount`, `payment_status`, `payment_method`, `transaction_reference`, `start_date`, `end_date`, `verified_by_admin`) VALUES
(1, 'Annual Official Access', 5000.00, 'active', 'Razorpay', 'pay_sample_kwa_001', NOW(), DATE_ADD(NOW(), INTERVAL 365 DAY), 1),
(2, 'Annual Official Access', 5000.00, 'active', 'Razorpay', 'pay_sample_pwd_002', NOW(), DATE_ADD(NOW(), INTERVAL 365 DAY), 1),
(3, 'Annual Official Access', 5000.00, 'active', 'Razorpay', 'pay_sample_waste_003', NOW(), DATE_ADD(NOW(), INTERVAL 365 DAY), 1),
(4, 'Annual Official Access', 5000.00, 'active', 'Razorpay', 'pay_sample_food_004', NOW(), DATE_ADD(NOW(), INTERVAL 365 DAY), 1),
(5, 'Annual Official Access', 5000.00, 'active', 'Razorpay', 'pay_sample_thoofan_005', NOW(), DATE_ADD(NOW(), INTERVAL 365 DAY), 1)
ON DUPLICATE KEY UPDATE `payment_status` = VALUES(`payment_status`);

-- ---------------------------------------------------------------------
-- 4. Sample Verified Local Businesses
-- ---------------------------------------------------------------------
INSERT INTO `businesses` (`id`, `business_name`, `owner_name`, `email`, `phone`, `category`, `address`, `city`, `website_url`, `password_hash`, `is_verified`) VALUES
(1, 'Apex Hardware & Sanitary Solutions', 'C. V. Radhakrishnan', 'apex.hardware@example.com', '+91 9447100200', 'Construction & Hardware', 'Main Road, Near Town Hall', 'Kozhikode', 'https://example.com', '$2y$10$5M/qf1i6x3E4V3m0u4GvkePzU1L7n2I0Y6C4F1A8k0V2n5B8z6I4.', 1),
(2, 'CleanGreen Organic Waste Recyclers', 'Ananya Menon', 'cleangreen@example.com', '+91 9447100300', 'Waste & Recycling Services', 'Plot 12, Industrial Area', 'Kozhikode', 'https://example.com', '$2y$10$5M/qf1i6x3E4V3m0u4GvkePzU1L7n2I0Y6C4F1A8k0V2n5B8z6I4.', 1)
ON DUPLICATE KEY UPDATE `business_name` = VALUES(`business_name`);

-- ---------------------------------------------------------------------
-- 5. Sample Approved Advertising Campaigns
-- ---------------------------------------------------------------------
INSERT INTO `advertising_campaigns` (`id`, `business_id`, `package_id`, `title`, `description`, `service_area`, `target_url`, `status`, `start_date`, `end_date`) VALUES
(1, 1, 2, '24/7 Emergency Plumbing & Pipe Replacements', 'Certified PVC pipes, leak repairs, and neighborhood plumbing fittings dispatched within 60 minutes.', 'Kozhikode Central', 'https://example.com', 'active', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY)),
(2, 2, 1, 'Organic Composting Units & Segregation Bins', 'Zero-waste home composting kits and bio-degradable waste bins with free municipal delivery.', 'Kozhikode North', 'https://example.com', 'active', NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);
