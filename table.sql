-- DSA Forge - Database Schema
-- Run: mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS dsa_forge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dsa_forge;

-- Problems table
CREATE TABLE IF NOT EXISTS problems (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    platform        ENUM('LeetCode','Codeforces','GeeksforGeeks','HackerRank','InterviewBit','Custom') DEFAULT 'LeetCode',
    difficulty      ENUM('Easy','Medium','Hard') NOT NULL DEFAULT 'Medium',
    topic           VARCHAR(100) NOT NULL,
    status          ENUM('Solved','Revision','Pending') DEFAULT 'Pending',
    time_taken      INT DEFAULT 0 COMMENT 'minutes',
    confidence      TINYINT DEFAULT 0 COMMENT '1-5',
    url             VARCHAR(512),
    company_tags    VARCHAR(255) COMMENT 'comma-separated: Amazon,Google,Microsoft',
    notes           LONGTEXT COMMENT 'Markdown content',
    revision_level  TINYINT DEFAULT 0 COMMENT '0=unseen, 1-5=revision stages',
    next_revision   DATE,
    last_reviewed   DATE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_topic (topic),
    INDEX idx_difficulty (difficulty),
    INDEX idx_next_revision (next_revision)
);

-- Revisions log
CREATE TABLE IF NOT EXISTS revisions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    problem_id  INT NOT NULL,
    reviewed_at DATE NOT NULL,
    FOREIGN KEY (problem_id) REFERENCES problems(id) ON DELETE CASCADE,
    INDEX idx_problem (problem_id)
);

-- Notes table
CREATE TABLE IF NOT EXISTS notes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    content     LONGTEXT NOT NULL COMMENT 'Markdown',
    category    VARCHAR(100) DEFAULT 'General',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Activity log (for heatmap)
CREATE TABLE IF NOT EXISTS activity_log (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    activity_date   DATE NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date (activity_date)
);

-- Streak tracking
CREATE TABLE IF NOT EXISTS streak_data (
    id          INT PRIMARY KEY DEFAULT 1,
    streak      INT DEFAULT 0,
    longest     INT DEFAULT 0,
    last_active DATE
);
INSERT IGNORE INTO streak_data VALUES (1, 0, 0, NULL);

-- Mock interview sessions
CREATE TABLE IF NOT EXISTS interview_sessions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    problem_id  INT,
    problem_name VARCHAR(255),
    solved      BOOLEAN DEFAULT FALSE,
    time_used   INT COMMENT 'seconds',
    session_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (problem_id) REFERENCES problems(id) ON DELETE SET NULL
);

-- Sample data
INSERT INTO problems (name, platform, difficulty, topic, status, time_taken, confidence, url, company_tags, notes, revision_level, next_revision) VALUES
('Two Sum', 'LeetCode', 'Easy', 'Arrays', 'Solved', 12, 5, 'https://leetcode.com/problems/two-sum/', 'Amazon,Google', '## Approach\n- Use HashMap to store complement\n- O(n) time, O(n) space', 3, DATE_ADD(CURDATE(), INTERVAL 7 DAY)),
('Longest Substring Without Repeating', 'LeetCode', 'Medium', 'Sliding Window', 'Solved', 25, 4, 'https://leetcode.com/problems/longest-substring-without-repeating-characters/', 'Google,Facebook', '## Approach\n- Sliding window + HashSet\n- Move left pointer when duplicate found', 2, DATE_ADD(CURDATE(), INTERVAL 3 DAY)),
('Coin Change', 'LeetCode', 'Medium', 'Dynamic Programming', 'Revision', 38, 2, 'https://leetcode.com/problems/coin-change/', 'Amazon', '## DP Approach\n- dp[i] = min coins for amount i\n- Transition: dp[i] = min(dp[i], dp[i-coin]+1)', 1, CURDATE()),
('Number of Islands', 'LeetCode', 'Medium', 'Graphs', 'Solved', 20, 4, 'https://leetcode.com/problems/number-of-islands/', 'Amazon,Google,Microsoft', '## BFS/DFS\n- Flood fill approach\n- Mark visited cells as visited', 4, DATE_ADD(CURDATE(), INTERVAL 15 DAY)),
('Merge K Sorted Lists', 'LeetCode', 'Hard', 'Heap', 'Pending', 0, 0, 'https://leetcode.com/problems/merge-k-sorted-lists/', 'Google', '', 0, NULL);