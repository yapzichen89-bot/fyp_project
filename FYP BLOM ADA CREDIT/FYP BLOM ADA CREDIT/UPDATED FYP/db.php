<?php
// Minimal SQLite DB initializer for local testing.
try {
    $db = new PDO('sqlite:' . __DIR__ . '/db.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create users table if it doesn't exist.
    $db->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE,
            password TEXT,
            role TEXT DEFAULT 'student',
            semester TEXT,
            class_name TEXT,
            timetable_data TEXT,
            approval_status TEXT DEFAULT 'pending',
            approved_by INTEGER,
            approved_at TEXT,
            approval_message TEXT
        )"
    );

    $userColumns = $db->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
    $missingColumns = [
        'role' => "ALTER TABLE users ADD COLUMN role TEXT DEFAULT 'student'",
        'approval_status' => "ALTER TABLE users ADD COLUMN approval_status TEXT DEFAULT 'pending'",
        'approved_by' => "ALTER TABLE users ADD COLUMN approved_by INTEGER",
        'approved_at' => "ALTER TABLE users ADD COLUMN approved_at TEXT",
        'approval_message' => "ALTER TABLE users ADD COLUMN approval_message TEXT",
    ];

    foreach ($missingColumns as $column => $alterSql) {
        if (!in_array($column, $userColumns, true)) {
            $db->exec($alterSql);
        }
    }

    if (!in_array('timetable_data', $userColumns, true)) {
        $db->exec('ALTER TABLE users ADD COLUMN timetable_data TEXT');
    }

    $db->exec("UPDATE users SET role = 'student' WHERE role IS NULL OR role = ''");
    $db->exec("UPDATE users SET approval_status = 'pending' WHERE approval_status IS NULL OR approval_status = ''");

    // Insert a default test user if not present.
    $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE username = :u');
    $stmt->execute([':u' => 'test']);
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('password', PASSWORD_DEFAULT);
        $ins = $db->prepare('INSERT INTO users (username, password, semester, class_name) VALUES (:u, :p, :s, :c)');
        $ins->execute([':u' => 'test', ':p' => $hash, ':s' => 'Semester 1', ':c' => 'S1']);
    }
} catch (Exception $e) {
    die('DB error: ' . $e->getMessage());
}
