<?php
/**
 * DSA Forge - PHP Backend API
 * File: api.php
 * 
 * Setup:
 * 1. Create MySQL database: CREATE DATABASE dsa_forge;
 * 2. Update DB config below
 * 3. Run: php api.php (or place in web server root)
 * 4. Import schema from schema.sql
 * 
 * Endpoints:
 *   GET    /api/problems          - List all problems
 *   POST   /api/problems          - Add problem
 *   PUT    /api/problems/{id}     - Update problem
 *   DELETE /api/problems/{id}     - Delete problem
 *   GET    /api/revision/due      - Get today's revision queue
 *   POST   /api/revision/{id}     - Mark revision complete
 *   GET    /api/analytics         - Get analytics data
 *   GET    /api/notes             - List notes
 *   POST   /api/notes             - Create note
 *   GET    /api/streak            - Get streak info
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

// ── DB CONFIG ──────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('DB_NAME', 'dsa_forge');

// ── CONNECT ────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

// ── ROUTER ─────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim(preg_replace('#^/api#', '', $uri), '/');
$parts  = explode('/', ltrim($uri, '/'));

try {
    match(true) {
        // Problems
        $uri === '/problems' && $method === 'GET'    => listProblems(),
        $uri === '/problems' && $method === 'POST'   => createProblem(),
        preg_match('#^/problems/(\d+)$#', $uri, $m) && $method === 'PUT'    => updateProblem((int)$m[1]),
        preg_match('#^/problems/(\d+)$#', $uri, $m) && $method === 'DELETE' => deleteProblem((int)$m[1]),

        // Revision
        $uri === '/revision/due'  && $method === 'GET'  => getDueRevisions(),
        preg_match('#^/revision/(\d+)$#', $uri, $m) && $method === 'POST' => completeRevision((int)$m[1]),

        // Analytics
        $uri === '/analytics' && $method === 'GET' => getAnalytics(),

        // Notes
        $uri === '/notes' && $method === 'GET'  => listNotes(),
        $uri === '/notes' && $method === 'POST' => createNote(),
        preg_match('#^/notes/(\d+)$#', $uri, $m) && $method === 'DELETE' => deleteNote((int)$m[1]),

        // Streak & activity
        $uri === '/streak'   && $method === 'GET'  => getStreak(),
        $uri === '/activity' && $method === 'POST' => logActivity(),

        default => json(404, ['error' => "Route not found: $method $uri"])
    };
} catch (PDOException $e) {
    json(500, ['error' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    json(500, ['error' => $e->getMessage()]);
}

// ══════════════════════════════════════════════
// PROBLEM HANDLERS
// ══════════════════════════════════════════════

function listProblems(): void {
    $filters = [];
    $params  = [];
    if (!empty($_GET['difficulty'])) { $filters[] = 'difficulty = ?'; $params[] = $_GET['difficulty']; }
    if (!empty($_GET['status']))     { $filters[] = 'status = ?';     $params[] = $_GET['status']; }
    if (!empty($_GET['topic']))      { $filters[] = 'topic = ?';      $params[] = $_GET['topic']; }
    if (!empty($_GET['search']))     {
        $filters[] = '(name LIKE ? OR topic LIKE ? OR company_tags LIKE ?)';
        $like = '%' . $_GET['search'] . '%';
        array_push($params, $like, $like, $like);
    }

    $where = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';
    $stmt  = db()->prepare("SELECT * FROM problems $where ORDER BY created_at DESC");
    $stmt->execute($params);
    json(200, $stmt->fetchAll());
}

function createProblem(): void {
    $d = json_decode(file_get_contents('php://input'), true);
    if (empty($d['name'])) { json(422, ['error' => 'name is required']); return; }

    $REVISION_DAYS = [1, 3, 7, 15, 30];
    $nextRevision  = $d['status'] === 'Solved' 
        ? date('Y-m-d', strtotime("+{$REVISION_DAYS[0]} days"))
        : null;

    $stmt = db()->prepare("
        INSERT INTO problems 
          (name, platform, difficulty, topic, status, time_taken, confidence, url, company_tags, notes, next_revision, revision_level)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $d['name'],
        $d['platform']     ?? 'LeetCode',
        $d['difficulty']   ?? 'Medium',
        $d['topic']        ?? 'Arrays',
        $d['status']       ?? 'Pending',
        (int)($d['time']   ?? 0),
        (int)($d['confidence'] ?? 0),
        $d['url']          ?? null,
        $d['company']      ?? null,
        $d['notes']        ?? null,
        $nextRevision,
        $d['status'] === 'Solved' ? 1 : 0,
    ]);

    logActivityInternal();
    json(201, ['id' => db()->lastInsertId(), 'message' => 'Problem created']);
}

function updateProblem(int $id): void {
    $d = json_decode(file_get_contents('php://input'), true);

    $stmt = db()->prepare("
        UPDATE problems SET
          name=?, platform=?, difficulty=?, topic=?, status=?,
          time_taken=?, confidence=?, url=?, company_tags=?, notes=?
        WHERE id=?
    ");
    $stmt->execute([
        $d['name'], $d['platform'], $d['difficulty'], $d['topic'],
        $d['status'], (int)$d['time'], (int)$d['confidence'],
        $d['url'], $d['company'], $d['notes'], $id
    ]);

    json(200, ['message' => 'Updated']);
}

function deleteProblem(int $id): void {
    db()->prepare("DELETE FROM problems WHERE id=?")->execute([$id]);
    json(200, ['message' => 'Deleted']);
}

// ══════════════════════════════════════════════
// REVISION HANDLERS
// ══════════════════════════════════════════════

function getDueRevisions(): void {
    $today = date('Y-m-d');
    $stmt  = db()->prepare("SELECT * FROM problems WHERE next_revision <= ? AND status != 'Pending' ORDER BY next_revision ASC");
    $stmt->execute([$today]);
    json(200, $stmt->fetchAll());
}

function completeRevision(int $id): void {
    $REVISION_DAYS  = [1, 3, 7, 15, 30];
    $today          = date('Y-m-d');

    $stmt = db()->prepare("SELECT revision_level FROM problems WHERE id=?");
    $stmt->execute([$id]);
    $prob = $stmt->fetch();

    if (!$prob) { json(404, ['error' => 'Problem not found']); return; }

    $newLevel = min($prob['revision_level'] + 1, count($REVISION_DAYS));
    $nextRev  = $newLevel < count($REVISION_DAYS)
        ? date('Y-m-d', strtotime("+{$REVISION_DAYS[$newLevel]} days"))
        : null;
    $newStatus = ($nextRev === null) ? 'Solved' : 'Revision';

    db()->prepare("
        UPDATE problems SET revision_level=?, next_revision=?, status=?, last_reviewed=? WHERE id=?
    ")->execute([$newLevel, $nextRev, $newStatus, $today, $id]);

    db()->prepare("INSERT INTO revisions (problem_id, reviewed_at) VALUES (?,?)")->execute([$id, $today]);
    logActivityInternal();

    json(200, ['next_revision' => $nextRev, 'level' => $newLevel, 'mastered' => $nextRev === null]);
}

// ══════════════════════════════════════════════
// ANALYTICS
// ══════════════════════════════════════════════

function getAnalytics(): void {
    $pdo = db();

    $total  = $pdo->query("SELECT COUNT(*) FROM problems")->fetchColumn();
    $solved = $pdo->query("SELECT COUNT(*) FROM problems WHERE status='Solved'")->fetchColumn();
    $avgTime = $pdo->query("SELECT AVG(time_taken) FROM problems WHERE time_taken > 0")->fetchColumn();
    $avgConf = $pdo->query("SELECT AVG(confidence) FROM problems WHERE confidence > 0")->fetchColumn();

    // By topic
    $byTopic = $pdo->query("
        SELECT topic, COUNT(*) as total,
               SUM(CASE WHEN status='Solved' THEN 1 ELSE 0 END) as solved
        FROM problems GROUP BY topic ORDER BY total DESC
    ")->fetchAll();

    // By difficulty
    $byDiff = $pdo->query("
        SELECT difficulty, COUNT(*) as count FROM problems GROUP BY difficulty
    ")->fetchAll();

    // Weak topics (< 50% solved, at least 2 problems)
    $weak = array_filter($byTopic, fn($t) => $t['total'] >= 2 && ($t['solved']/$t['total']) < 0.5);

    // Company tags
    $allCompanies = $pdo->query("SELECT company_tags FROM problems WHERE company_tags IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    $companyCounts = [];
    foreach ($allCompanies as $tags) {
        foreach (explode(',', $tags) as $c) {
            $c = trim($c);
            if ($c) $companyCounts[$c] = ($companyCounts[$c] ?? 0) + 1;
        }
    }
    arsort($companyCounts);

    json(200, [
        'total'       => (int)$total,
        'solved'      => (int)$solved,
        'accuracy'    => $total > 0 ? round(($solved / $total) * 100, 1) : 0,
        'avg_time'    => round($avgTime ?? 0, 1),
        'avg_confidence' => round($avgConf ?? 0, 1),
        'by_topic'    => $byTopic,
        'by_difficulty' => $byDiff,
        'weak_topics' => array_values($weak),
        'top_companies' => array_slice($companyCounts, 0, 10, true),
    ]);
}

// ══════════════════════════════════════════════
// NOTES
// ══════════════════════════════════════════════

function listNotes(): void {
    $stmt = db()->query("SELECT * FROM notes ORDER BY created_at DESC");
    json(200, $stmt->fetchAll());
}

function createNote(): void {
    $d = json_decode(file_get_contents('php://input'), true);
    if (empty($d['title']) || empty($d['content'])) {
        json(422, ['error' => 'title and content are required']); return;
    }
    $stmt = db()->prepare("INSERT INTO notes (title, content, category) VALUES (?,?,?)");
    $stmt->execute([$d['title'], $d['content'], $d['category'] ?? 'General']);
    json(201, ['id' => db()->lastInsertId()]);
}

function deleteNote(int $id): void {
    db()->prepare("DELETE FROM notes WHERE id=?")->execute([$id]);
    json(200, ['message' => 'Deleted']);
}

// ══════════════════════════════════════════════
// STREAK & ACTIVITY
// ══════════════════════════════════════════════

function getStreak(): void {
    $stmt = db()->query("SELECT * FROM streak_data WHERE id=1");
    $row  = $stmt->fetch() ?? ['streak' => 0, 'last_active' => null];
    $activity = db()->query("SELECT activity_date, COUNT(*) as count FROM activity_log GROUP BY activity_date ORDER BY activity_date DESC LIMIT 180")->fetchAll();
    json(200, ['streak' => (int)$row['streak'], 'last_active' => $row['last_active'], 'activity' => $activity]);
}

function logActivity(): void {
    logActivityInternal();
    json(200, ['message' => 'Activity logged']);
}

function logActivityInternal(): void {
    $today = date('Y-m-d');
    db()->prepare("INSERT INTO activity_log (activity_date) VALUES (?)")->execute([$today]);

    // Update streak
    $row = db()->query("SELECT * FROM streak_data WHERE id=1")->fetch();
    if (!$row) {
        db()->exec("INSERT INTO streak_data (id, streak, last_active) VALUES (1, 1, '$today')");
    } elseif ($row['last_active'] !== $today) {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $newStreak = $row['last_active'] === $yesterday ? $row['streak'] + 1 : 1;
        db()->prepare("UPDATE streak_data SET streak=?, last_active=? WHERE id=1")->execute([$newStreak, $today]);
    }
}

// ── HELPERS ───────────────────────────────────
function json(int $status, mixed $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}