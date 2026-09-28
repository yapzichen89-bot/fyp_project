<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/timetable_data.php';

$availableClassesBySemester = [
    'Semester 1' => ['S1', 'S2', 'S3'],
    'Semester 2' => ['S1', 'S3'],
    'Semester 3' => ['S1', 'S2', 'S3', 'S4'],
    'Semester 4' => ['S1', 'S3'],
    'Semester 5' => ['S1', 'S2', 'S3', 'S4'],
];

$message = '';
$statusClass = '';
$action = $_GET['action'] ?? '';

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'register') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $semester = trim($_POST['semester'] ?? '');
        $className = trim($_POST['class_name'] ?? '');
        $allowedClasses = $availableClassesBySemester[$semester] ?? [];

        if ($username === '' || $password === '' || $semester === '' || $className === '') {
            $message = 'Please fill in all fields.';
            $statusClass = 'error';
        } elseif (!in_array($className, $allowedClasses, true)) {
            $message = 'That class is not available for the selected semester.';
            $statusClass = 'error';
        } else {
            try {
                $statement = $db->prepare("INSERT INTO users (username, password, role, semester, class_name, approval_status) VALUES (:username, :password, 'pa', :semester, :class_name, 'approved')");
                $statement->execute([
                    ':username' => $username,
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':semester' => $semester,
                    ':class_name' => $className,
                ]);
                $message = 'PA account created successfully. You can now log in.';
                $statusClass = 'success';
                $action = '';
            } catch (PDOException $exception) {
                $message = $exception->getCode() === '23000' ? 'Username already exists.' : 'Registration error: ' . $exception->getMessage();
                $statusClass = 'error';
            }
        }
    } elseif ($formAction === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $statement = $db->prepare("SELECT * FROM users WHERE username = :username AND role = 'pa' LIMIT 1");
        $statement->execute([':username' => $username]);
        $pa = $statement->fetch(PDO::FETCH_ASSOC);

        if ($pa && password_verify($password, $pa['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $pa['id'];
            $_SESSION['username'] = $pa['username'];
            $_SESSION['role'] = 'pa';
            header('Location: PA.php');
            exit;
        }

        $message = 'Invalid PA username or password.';
        $statusClass = 'error';
    } elseif ($formAction === 'approval' && !empty($_SESSION['user_id'])) {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $decision = $_POST['decision'] ?? '';
        $paStatement = $db->prepare("SELECT semester, class_name FROM users WHERE id = :id AND role = 'pa' LIMIT 1");
        $paStatement->execute([':id' => $_SESSION['user_id']]);
        $pa = $paStatement->fetch(PDO::FETCH_ASSOC);

        if ($pa && in_array($decision, ['approved', 'rejected'], true)) {
            $approvalMessage = $decision === 'approved'
                ? 'Your timetable has been approved by your PA.'
                : 'Your timetable was rejected by your PA. Please review and update it.';
            $studentStatement = $db->prepare("UPDATE users SET approval_status = :status, approved_by = :approved_by, approved_at = :approved_at, approval_message = :approval_message WHERE id = :student_id AND role = 'student' AND semester = :semester AND class_name = :class_name");
            $studentStatement->execute([
                ':status' => $decision,
                ':approved_by' => $_SESSION['user_id'],
                ':approved_at' => date('Y-m-d H:i:s'),
                ':approval_message' => $approvalMessage,
                ':student_id' => $studentId,
                ':semester' => $pa['semester'],
                ':class_name' => $pa['class_name'],
            ]);
            $message = $studentStatement->rowCount() ? 'Student timetable status updated.' : 'Student was not found in your assigned class.';
            $statusClass = $studentStatement->rowCount() ? 'success' : 'error';
        }
    }
}

$isPaLoggedIn = !empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'pa';
$pa = null;
$students = [];

if ($isPaLoggedIn) {
    $paStatement = $db->prepare("SELECT username, semester, class_name FROM users WHERE id = :id AND role = 'pa' LIMIT 1");
    $paStatement->execute([':id' => $_SESSION['user_id']]);
    $pa = $paStatement->fetch(PDO::FETCH_ASSOC);

    if (!$pa) {
        session_unset();
        session_destroy();
        header('Location: PA.php');
        exit;
    }

    $studentStatement = $db->prepare("SELECT id, username, timetable_data, approval_status, approved_at FROM users WHERE role = 'student' AND semester = :semester AND class_name = :class_name ORDER BY username");
    $studentStatement->execute([
        ':semester' => $pa['semester'],
        ':class_name' => $pa['class_name'],
    ]);
    $students = $studentStatement->fetchAll(PDO::FETCH_ASSOC);
}

function color_for_code($code)
{
    $palette = ['#FFB4A2', '#FFDAC1', '#E2F0CB', '#B5EAD7', '#C7CEEA', '#D6C7FF', '#F3D1FF', '#CDE7FF', '#FFE0B2', '#D0F0C0'];
    return $palette[abs(crc32((string)$code)) % count($palette)];
}

function render_timetable($json)
{
    $data = json_decode((string)$json, true);
    $days = ['Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat'];
    $timeSlots = [
        '08:00-09:00', '09:00-10:00', '10:00-11:00', '11:00-12:00',
        '12:00-13:00', '13:00-14:00', '14:00-15:00', '15:00-16:00', '16:00-17:00',
    ];

    if (!is_array($data) || !$data) {
        return '<p class="muted">No timetable submitted yet.</p>';
    }

    $html = '<div class="timetable-wrap"><table class="timetable-table" aria-label="Student weekly timetable"><thead><tr><th class="time-col">Masa</th>';
    foreach ($days as $day) {
        $html .= '<th>' . htmlspecialchars($day, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    foreach ($timeSlots as $slot) {
        $html .= '<tr><th class="time-col">' . htmlspecialchars($slot, ENT_QUOTES, 'UTF-8') . '</th>';
        foreach ($days as $day) {
            $entry = $data[$day . '|' . $slot] ?? null;
            $html .= '<td class="' . ($entry ? 'filled-cell' : 'empty-cell') . '">';
            if (is_array($entry)) {
                $code = $entry['code'] ?? '';
                $displayTitle = $entry['subject'] ?? ($entry['title'] ?? '');
                $lecturer = $entry['lecturer'] ?? '';
                $room = $entry['room'] ?? '';
                $html .= '<div class="subject-box" style="background: ' . htmlspecialchars(color_for_code($code), ENT_QUOTES, 'UTF-8') . ';">';
                $html .= '<div class="subject-content">';
                $html .= '<div class="subject-title">' . htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8') . '</div>';
                if ($lecturer !== '') {
                    $html .= '<div class="subject-detail">' . htmlspecialchars($lecturer, ENT_QUOTES, 'UTF-8') . '</div>';
                }
                if ($room !== '') {
                    $html .= '<div class="subject-detail">Room: ' . htmlspecialchars($room, ENT_QUOTES, 'UTF-8') . '</div>';
                }
                $html .= '</div></div>';
            }
            $html .= '</td>';
        }
        $html .= '</tr>';
    }

    $totalCredits = getTimetableCreditTotal($data);
    $html .= '</tbody></table><div class="credit-summary">Total Credit Hours: ' . number_format($totalCredits, 1, '.', '') . '</div></div>';

    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PA Timetable Approval</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background: #f4f6f9; color: #1f2937; }
        .pa-shell { max-width: 1200px; margin: 24px auto; padding: 0 16px 30px; }
        .pa-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; background: linear-gradient(135deg, #0b3c7d, #145fbf); color: #fff; padding: 22px 28px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,.08); margin-bottom: 20px; }
        .pa-header h1 { margin: 0 0 6px; font-size: 2.1rem; }
        .pa-header p { margin: 0; }
        .pa-link { color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,.6); padding: 9px 13px; border-radius: 5px; }
        .pa-card { background: #fff; margin-top: 20px; padding: 20px; border: 1px solid #dfe3e8; border-radius: 16px; box-shadow: 0 8px 20px rgba(0,0,0,.08); }
        .pa-card h2 { margin-top: 0; }
        .pa-form { max-width: 420px; margin: 0 auto; }
        .pa-form .btn-login { margin-top: 8px; }
        .status { text-align: center; font-weight: bold; margin: 12px 0; }
        .success { color: #16803c; }
        .error { color: #c0392b; }
        .muted { color: #6b7280; }
        .student-card { border: 1px solid #dfe3e8; border-radius: 12px; padding: 18px; margin-top: 18px; }
        .student-heading { display: flex; justify-content: space-between; gap: 15px; align-items: center; flex-wrap: wrap; }
        .student-heading h3 { margin: 0; }
        .badge { padding: 5px 9px; border-radius: 12px; font-size: 12px; font-weight: bold; text-transform: capitalize; background: #fff3cd; color: #856404; }
        .badge.approved { background: #d4edda; color: #155724; }
        .badge.rejected { background: #f8d7da; color: #721c24; }
        .timetable-wrap { overflow-x: auto; margin: 16px 0; }
        .timetable-table { width: 100%; border-collapse: collapse; min-width: 860px; table-layout: fixed; }
        .timetable-table th, .timetable-table td { border: 1px solid #dfe3e8; padding: 5px; text-align: center; min-height: 64px; vertical-align: middle; background: #fff; }
        .timetable-table thead th { background: #1f2d3d; color: #fff; font-weight: 700; padding: 8px 6px; }
        .time-col { width: 105px; background: #f5f7fb !important; color: #1f2937; font-weight: 700; }
        .empty-cell { background: #fbfcfe !important; }
        .filled-cell { background: #dff3ff !important; border: 1px solid #7cc2f4; }
        .subject-box { display: flex; align-items: center; min-height: 54px; border: 1px solid #7cc2f4; border-radius: 7px; padding: 5px; text-align: left; color: #134680; font-weight: 700; }
        .subject-content { width: 100%; }
        .subject-title { font-size: .78rem; line-height: 1.15; color: #073b53; }
        .subject-detail { font-size: .65rem; line-height: 1.15; margin-top: 3px; color: #0b3b4e; }
        .credit-summary { margin-top: 14px; padding: 12px 16px; border-radius: 10px; background: #edf6ff; border: 1px solid #cbe2ff; color: #0b3c7d; font-weight: 800; text-align: right; }
        .approval-actions { display: flex; gap: 8px; }
        .approval-actions button { border: 0; border-radius: 4px; padding: 9px 14px; color: #fff; cursor: pointer; font-weight: bold; }
        .approve { background: #16803c; }
        .reject { background: #c0392b; }
        @media (max-width: 650px) { .pa-header { align-items: flex-start; flex-direction: column; } .pa-header h1 { font-size: 1.5rem; } }
    </style>
</head>
<body>
    <div class="pa-shell">
        <?php if (!$isPaLoggedIn): ?>
            <div class="pa-header">
                <div><h1>PA Timetable Portal</h1><p>Personal Advisor access</p></div>
                <a class="pa-link" href="index.php">Student Login</a>
            </div>

            <?php if ($message !== ''): ?><p class="status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

            <section class="pa-card pa-form">
                <?php if ($action === 'register'): ?>
                    <h2>PA First Time Login</h2>
                    <form method="post" action="PA.php?action=register">
                        <input type="hidden" name="form_action" value="register">
                        <div class="input-group"><label for="username">Username</label><input id="username" name="username" type="text" required></div>
                        <div class="input-group"><label for="semester">Assigned Semester</label><select id="semester" name="semester" required><option value="">Select semester</option><?php foreach (array_keys($availableClassesBySemester) as $semester): ?><option value="<?php echo htmlspecialchars($semester, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($semester, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
                        <div class="input-group"><label for="class_name">Assigned Class</label><select id="class_name" name="class_name" required><option value="">Select class</option><?php foreach (['S1', 'S2', 'S3', 'S4'] as $className): ?><option value="<?php echo $className; ?>"><?php echo $className; ?></option><?php endforeach; ?></select></div>
                        <div class="input-group"><label for="password">Create Password</label><input id="password" name="password" type="password" required></div>
                        <button class="btn-login" type="submit">Create PA Account</button>
                        <div class="form-footer"><a href="PA.php">Already registered? PA Login</a></div>
                    </form>
                <?php else: ?>
                    <h2>PA Login</h2>
                    <form method="post" action="PA.php">
                        <input type="hidden" name="form_action" value="login">
                        <div class="input-group"><label for="username">Username</label><input id="username" name="username" type="text" required></div>
                        <div class="input-group"><label for="password">Password</label><input id="password" name="password" type="password" required></div>
                        <button class="btn-login" type="submit">Login</button>
                        <div class="form-footer"><a href="PA.php?action=register">First Time Login</a></div>
                    </form>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <header class="pa-header">
                <div><h1>PA Timetable Approval</h1><p><?php echo htmlspecialchars($pa['username'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars($pa['semester'] . ' - ' . $pa['class_name'], ENT_QUOTES, 'UTF-8'); ?></p></div>
                <a class="pa-link" href="PA.php?logout=1">Logout</a>
            </header>
            <?php if ($message !== ''): ?><p class="status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            <section class="pa-card">
                <h2>Students in your class</h2>
                <?php if (!$students): ?><p class="muted">No students have registered for this semester and class yet.</p><?php endif; ?>
                <?php foreach ($students as $student): ?>
                    <?php $approvalStatus = $student['approval_status'] ?: 'pending'; ?>
                    <article class="student-card">
                        <div class="student-heading"><h3><?php echo htmlspecialchars($student['username'], ENT_QUOTES, 'UTF-8'); ?></h3><span class="badge <?php echo htmlspecialchars($approvalStatus, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($approvalStatus, ENT_QUOTES, 'UTF-8'); ?></span></div>
                        <?php echo render_timetable($student['timetable_data']); ?>
                        <?php
                            $studentCreditTotal = getTimetableCreditTotal(json_decode((string)($student['timetable_data'] ?? '[]'), true) ?: []);
                            if ($studentCreditTotal > 20.0):
                                echo '<div class="status error" style="text-align:left; margin-top:12px;">This timetable exceeds 20.0 credit hours. The student must obtain permission from Ketua Jabatan (KJ) before approval can proceed. Please wait for the KJ approval.</div>';
                            endif;
                        ?>
                        <?php if (!empty($student['approved_at'])): ?><p class="muted">Updated: <?php echo htmlspecialchars($student['approved_at'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                        <form class="approval-actions" method="post" action="PA.php">
                            <input type="hidden" name="form_action" value="approval">
                            <input type="hidden" name="student_id" value="<?php echo (int)$student['id']; ?>">
                            <button class="approve" name="decision" value="approved" type="submit">Approve</button>
                            <button class="reject" name="decision" value="rejected" type="submit">Reject</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>
</body>
</html>
