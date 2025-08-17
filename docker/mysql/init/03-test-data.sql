-- Sample data for testing Stellar Dominion Game
-- This file will be executed after the main database.sql schema

USE users;

-- Insert test users
INSERT INTO users (character_name, email, password_hash, race, class, credits, level, experience, untrained_citizens, attack_turns, last_updated, created_at) VALUES
('TestCommander1', 'test1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Human', 'Commander', 10000, 1, 100, 50, 10, NOW(), NOW()),
('TestCommander2', 'test2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cyborg', 'Warrior', 15000, 2, 250, 75, 10, NOW(), NOW()),
('TestCommander3', 'test3@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Android', 'Strategist', 20000, 3, 500, 100, 10, NOW(), NOW());

-- Insert test alliances
INSERT INTO alliances (name, tag, description, leader_id, created_at) VALUES
('Test Alliance Alpha', 'TAA', 'A test alliance for development purposes', 1, NOW()),
('Test Alliance Beta', 'TAB', 'Another test alliance for development', 2, NOW());

-- Add users to alliances
UPDATE users SET alliance_id = 1 WHERE id IN (1, 3);
UPDATE users SET alliance_id = 2 WHERE id = 2;

-- Note: alliance_role_id would need to reference actual alliance_roles records
-- For now, leaving alliance_role_id as NULL (default)

-- Insert some test structures (now that the structures table exists via 01-missing-tables.sql)
INSERT INTO structures (user_id, structure_type, level, quantity, created_at) VALUES
(1, 'barracks', 1, 5, NOW()),
(1, 'armory', 1, 3, NOW()),
(1, 'treasury', 1, 2, NOW()),
(2, 'barracks', 2, 7, NOW()),
(2, 'armory', 1, 4, NOW()),
(3, 'barracks', 1, 4, NOW());

-- Insert test biography data
UPDATE users SET biography = 'A veteran commander with years of experience in galactic warfare. Known for strategic thinking and diplomatic solutions.' WHERE id = 1;
UPDATE users SET biography = 'Rising star in the galactic community. Focuses on rapid expansion and aggressive tactics.' WHERE id = 2;
UPDATE users SET biography = 'Peaceful trader and alliance builder. Prefers economic growth over military conquest.' WHERE id = 3;

-- Insert test admin user
INSERT INTO users (character_name, email, password_hash, race, class, credits, level, experience, untrained_citizens, attack_turns, last_updated, created_at) VALUES
('AdminCommander', 'admin@stellar-dominion.com', '$2y$10$FaUn8W5m5n6Z8Fz9J9r8kOh3.abc123xyz789def456ghi789jkl', 'Human', 'Administrator', 100000, 10, 5000, 200, 15, NOW(), NOW());
