<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Helper: map course code to a consistent color from a palette
function color_for_code($code)
{
    $palette = [
        '#FFB4A2', '#FFDAC1', '#E2F0CB', '#B5EAD7', '#C7CEEA', '#D6C7FF', '#F3D1FF', '#CDE7FF', '#FFE0B2', '#D0F0C0'
    ];
    $h = crc32($code);
    return $palette[$h % count($palette)];
}

// If asked to export calendar, generate .ics from session-stored sessions
if (isset($_GET['export_ics']) && !empty($_SESSION['timetable_sessions'])) {
    $sessions = $_SESSION['timetable_sessions'];
    $tz = date_default_timezone_get();
    $lines = [];
    $lines[] = "BEGIN:VCALENDAR";
    $lines[] = "VERSION:2.0";
    $lines[] = "PRODID:-//PKS Timetable//EN";

    foreach ($sessions as $s) {
        // compute next occurrence date for the given weekday
        $weekdayMap = ['Isnin' => 'Monday','Selasa'=>'Tuesday','Rabu'=>'Wednesday','Khamis'=>'Thursday','Jumaat'=>'Friday','Saturday'=>'Saturday','Sunday'=>'Sunday'];
        $dayName = $s['day'] ?? $s['day'];
        $english = $weekdayMap[$dayName] ?? $dayName;
        $next = strtotime('next ' . $english);
        if (!$next) { $next = time(); }

        $startParts = explode(':', $s['start']);
        $endParts = explode(':', $s['end']);

        $dtStart = mktime((int)$startParts[0], (int)$startParts[1], 0, date('n', $next), date('j', $next), date('Y', $next));
        $dtEnd = mktime((int)$endParts[0], (int)$endParts[1], 0, date('n', $next), date('j', $next), date('Y', $next));

        $uid = uniqid('', true) . '@pks-timetable.local';
        $summary = ($s['code'] ?? '') . ' - ' . ($s['title'] ?? '');
        $location = $s['room'] ?? '';
        $description = trim(($s['lecturer'] ?? '') . '\n' . ($s['code'] ?? '') . ' ' . ($s['title'] ?? ''));

        $lines[] = "BEGIN:VEVENT";
        $lines[] = "UID:$uid";
        $lines[] = "DTSTAMP:" . gmdate('Ymd\THis\Z');
        $lines[] = "DTSTART;TZID=$tz:" . date('Ymd\THis', $dtStart);
        $lines[] = "DTEND;TZID=$tz:" . date('Ymd\THis', $dtEnd);
        $lines[] = "RRULE:FREQ=WEEKLY";
        $lines[] = "SUMMARY:" . addcslashes($summary, "\n,;");
        $lines[] = "LOCATION:" . addcslashes($location, "\n,;");
        $lines[] = "DESCRIPTION:" . addcslashes($description, "\n,;");
        $lines[] = "END:VEVENT";
    }

    $lines[] = "END:VCALENDAR";
    $content = implode("\r\n", $lines);

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="timetable.ics"');
    echo $content;
    exit;
}

// Load timetable/course helper (still useful elsewhere)
require_once __DIR__ . '/timetable_data.php';
require_once __DIR__ . '/db.php';

$currentUserStmt = $db->prepare('SELECT semester, class_name, username, role, approval_status, approval_message FROM users WHERE id = :id LIMIT 1');
$currentUserStmt->execute([':id' => $_SESSION['user_id']]);
$currentUser = $currentUserStmt->fetch(PDO::FETCH_ASSOC);

if ($currentUser) {
    if (($currentUser['role'] ?? 'student') === 'pa') {
        header('Location: PA.php');
        exit;
    }

    $_SESSION['username'] = $currentUser['username'] ?? $_SESSION['username'] ?? 'User';

    if (!isset($_SESSION['student_semester']) && !empty($currentUser['semester'])) {
        $_SESSION['student_semester'] = trim((string)$currentUser['semester']);
    }
    if (!isset($_SESSION['student_class_name']) && !empty($currentUser['class_name'])) {
        $_SESSION['student_class_name'] = trim((string)$currentUser['class_name']);
    }

    if (empty($_SESSION['student_semester'])) {
        $_SESSION['student_semester'] = 'Semester 1';
    }
    if (empty($_SESSION['student_class_name'])) {
        $_SESSION['student_class_name'] = 'S1';
    }
}

$loggedUsername = $_SESSION['username'] ?? 'User';
$loggedSemester = $_SESSION['student_semester'] ?? 'Semester 1';
$loggedClass = $_SESSION['student_class_name'] ?? 'S1';
$approvalStatus = $currentUser['approval_status'] ?? 'pending';
$approvalMessage = $currentUser['approval_message'] ?? '';
$studentCreditTotal = getTimetableCreditTotal($_SESSION['timetable_data'] ?? []);
$creditLimitExceeded = $studentCreditTotal > 20.0;
$creditLimitWarning = $creditLimitExceeded
    ? 'Credit hours exceed 20.0. Permission from Ketua Jabatan (KJ) is required before this timetable can be approved.'
    : '';

// Use a fixed course list provided by the user and mark them selectable.
$courseCatalog = [
    ['code' => 'MPU22071', 'name' => 'KURSUS INTEGRITI DAN ANTI RASUAH', 'credit' => 1.0, 'default_duration_hours' => 2],
    ['code' => 'MPU22153', 'name' => 'ENGLISH FOR DIGITAL TECHNOLOGY', 'credit' => 3.0, 'default_duration_hours' => 2],
    ['code' => 'MPU21072', 'name' => 'PENGHAYATAN ETIKA DAN PERADABAN', 'credit' => 2.0, 'default_duration_hours' => 2],
    ['code' => 'MPU23162', 'name' => 'PENGAJIAN ISLAM', 'credit' => 2.0, 'default_duration_hours' => 2],
    ['code' => 'MPU23272', 'name' => 'PENDIDIKAN MORAL', 'credit' => 2.0, 'default_duration_hours' => 2],
    ['code' => 'DBM20153', 'name' => 'DISCRETE MATHEMATICS', 'credit' => 3.0, 'default_duration_hours' => 2],
    ['code' => 'DBM10143', 'name' => 'CALCULUS AND ALGEBRA', 'credit' => 3.0, 'default_duration_hours' => 2],
    ['code' => 'DBM30263', 'name' => 'STATISTICS AND PROBABILITY', 'credit' => 3.0, 'default_duration_hours' => 2],
    ['code' => 'DFC20313', 'name' => 'CYBERSECURITY FUNDAMENTALS', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFC20303', 'name' => 'PROGRAMMING FUNDAMENTALS', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFC10353', 'name' => 'PROGRAMMING FUNDAMENTALS', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFC20293', 'name' => 'NETWORK AND DATA COMMUNICATION', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFC20283', 'name' => 'DATABASE FUNDAMENTALS', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFC10273', 'name' => 'OPERATING SYSTEMS', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFC10263', 'name' => 'COMPUTER ARCHITECTURE', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFK50093', 'name' => 'COMPUTER NETWORK SECURITY', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFN50563', 'name' => 'ADVANCED SERVER ADMINISTRATION', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFP50463', 'name' => 'JAVA-BASED APPLICATION DEVELOPMENT', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFN40543', 'name' => 'ENTERPRISE NETWORK', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFK50083', 'name' => 'PYTHON PROGRAMMING', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFP40443', 'name' => 'FULL STACK WEB DEVELOPMENT', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFP30313', 'name' => 'DIGITAL MULTIMEDIA', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFN40553', 'name' => 'STRUCTURED CABLING IN NETWORKING', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFN40503', 'name' => 'EMBEDDED INTERNET OF THINGS (IOT)', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFK40073', 'name' => 'CLOUD COMPUTING', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFP40453', 'name' => 'MOBILE APPLICATION DEVELOPMENT', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFP40433', 'name' => 'BUSINESS INTELLIGENCE', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFK40063', 'name' => 'SERVER ADMINISTRATION', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFC40343', 'name' => 'SYSTEM ANALYSIS AND DESIGN FUNDAMENTALS', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFN30533', 'name' => 'SWITCHING AND ROUTING ESSENTIALS', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFK30023', 'name' => 'USER EXPERIENCE FUNDAMENTALS', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFK30053', 'name' => 'OBJECT ORIENTED PROGRAMMING', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFK30033', 'name' => 'CYBERPRENEURSHIP', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFC30333', 'name' => 'ETHICS IN COMPUTING', 'credit' => 3.0, 'default_duration_hours' => 3],
    ['code' => 'DFK20013', 'name' => 'WEB DESIGN TECHNOLOGY', 'credit' => 3.0, 'default_duration_hours' => 3],
];

$courseCreditTotal = 0.0;
foreach ($courseCatalog as $c) { $courseCreditTotal += (float)($c['credit'] ?? 0); }

$availableClassesBySemester = [
    'Semester 1' => ['S1', 'S2', 'S3'],
    'Semester 2' => ['S1', 'S3'],
    'Semester 3' => ['S1', 'S2', 'S3', 'S4'],
    'Semester 4' => ['S1', 'S3'],
    'Semester 5' => ['S1', 'S2', 'S3', 'S4'],
];

$supportedTimetableProfiles = [
    'Semester 1|S1', 'Semester 1|S2', 'Semester 1|S3',
    'Semester 2|S1', 'Semester 2|S3',
    'Semester 3|S1', 'Semester 3|S2', 'Semester 3|S3', 'Semester 3|S4',
    'Semester 4|S1', 'Semester 4|S3',
    'Semester 5|S1', 'Semester 5|S2', 'Semester 5|S3', 'Semester 5|S4',
];

$legacyClassMap = [
    'S1' => 'Class A',
    'S2' => 'Class B',
    'S3' => 'Class C',
    'S4' => 'Class D',
];

$selectedImagePath = null;
$classWarning = '';
if (!empty($loggedSemester) && !empty($loggedClass)) {
    $allowedClasses = $availableClassesBySemester[$loggedSemester] ?? [];
    $selectedProfile = $loggedSemester . '|' . $loggedClass;

    if (!in_array($loggedClass, $allowedClasses, true)) {
        $classWarning = 'Class ' . htmlspecialchars($loggedClass, ENT_QUOTES, 'UTF-8') . ' is not available for this semester.';
    } elseif (!in_array($selectedProfile, $supportedTimetableProfiles, true)) {
        $classWarning = 'This class does not have a timetable yet. Please select another class.';
    } else {
        $classVariants = array_values(array_unique([
            $loggedClass,
            $legacyClassMap[$loggedClass] ?? $loggedClass,
        ]));

        $imageCandidates = [];
        foreach ($classVariants as $classVariant) {
            $imageCandidates[] = __DIR__ . '/image/' . $loggedSemester . ' ' . $classVariant . '.png';
            $imageCandidates[] = __DIR__ . '/image/' . $loggedSemester . ' ' . $classVariant . '.jpg';
            $imageCandidates[] = __DIR__ . '/image/' . $loggedSemester . ' ' . $classVariant . '.jpeg';
        }

        foreach ($imageCandidates as $candidate) {
            if (file_exists($candidate)) {
                $selectedImagePath = str_replace(__DIR__ . '/image/', 'image/', $candidate);
                break;
            }
        }
    }
}

$days = ['Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat'];
$timeSlots = [
    '08:00-09:00',
    '09:00-10:00',
    '10:00-11:00',
    '11:00-12:00',
    '12:00-13:00',
    '13:00-14:00',
    '14:00-15:00',
    '15:00-16:00',
    '16:00-17:00',
];
$timeBoundaries = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
$timeRanges = [];
for ($startIndex = 0; $startIndex < count($timeSlots); $startIndex++) {
    $startTime = explode('-', $timeSlots[$startIndex])[0];
    for ($endIndex = $startIndex + 1; $endIndex <= count($timeSlots); $endIndex++) {
        $endTime = explode('-', $timeSlots[$endIndex - 1])[1];
        $timeRanges[] = $startTime . '-' . $endTime;
    }
}

if (!isset($_SESSION['timetable_data'])) {
    $_SESSION['timetable_data'] = [];
}

$timetableProfile = $loggedSemester . '|' . $loggedClass;
$timetableAlreadyLoaded = !empty($_SESSION['timetable_user_loaded']);
if (!$timetableAlreadyLoaded) {
    $savedTimetableStatement = $db->prepare('SELECT timetable_data FROM users WHERE id = :id');
    $savedTimetableStatement->execute([':id' => $_SESSION['user_id']]);
    $savedTimetableJson = $savedTimetableStatement->fetchColumn();
    if (is_string($savedTimetableJson) && $savedTimetableJson !== '') {
        $decodedTimetable = json_decode($savedTimetableJson, true);
        if (is_array($decodedTimetable)) {
            $_SESSION['timetable_data'] = $decodedTimetable;
        }
    }
    $_SESSION['timetable_user_loaded'] = true;
}

$autoLoadUserTimetable = !$timetableAlreadyLoaded
    && empty($_SESSION['timetable_data'])
    && !empty($loggedSemester)
    && !empty($loggedClass);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];

    if ($action === 'add_subject') {
        $subjectName = trim((string)($_POST['subject_name'] ?? ''));
        $day = trim((string)($_POST['day'] ?? ''));
        $timeSlot = trim((string)($_POST['time_slot'] ?? ''));

        $rangeParts = explode('-', $timeSlot);
        $startIndex = array_search($rangeParts[0] ?? '', array_map(function ($slot) {
            return explode('-', $slot)[0];
        }, $timeSlots), true);
        $endIndex = array_search($rangeParts[1] ?? '', array_map(function ($slot) {
            return explode('-', $slot)[1];
        }, $timeSlots), true);

        if ($subjectName === '' || !in_array($day, $days, true) || !in_array($timeSlot, $timeRanges, true) || $startIndex === false || $endIndex === false || $endIndex < $startIndex) {
            echo json_encode(['success' => false, 'message' => 'Maklumat tidak lengkap.']);
            exit;
        }

        for ($slotIndex = $startIndex; $slotIndex <= $endIndex; $slotIndex++) {
            $slotKey = $day . '|' . $timeSlots[$slotIndex];
            if (isset($_SESSION['timetable_data'][$slotKey])) {
                echo json_encode(['success' => false, 'message' => 'the subject is clash!']);
                exit;
            }
        }

        for ($slotIndex = $startIndex; $slotIndex <= $endIndex; $slotIndex++) {
            $slot = $timeSlots[$slotIndex];
            $slotKey = $day . '|' . $slot;
            $courseCode = extractSubjectCode($subjectName);
            $_SESSION['timetable_data'][$slotKey] = [
                'subject' => $subjectName,
                'code' => $courseCode,
                'day' => $day,
                'time_slot' => $slot,
            ];
        }

        echo json_encode(['success' => true, 'message' => 'Subjek berjaya ditambah.']);
        exit;
    }

    if ($action === 'delete_subject') {
        $day = trim((string)($_POST['day'] ?? ''));
        $timeSlot = trim((string)($_POST['time_slot'] ?? ''));
        $slotKey = $day . '|' . $timeSlot;

        unset($_SESSION['timetable_data'][$slotKey]);
        echo json_encode(['success' => true, 'message' => 'Subjek dipadam.']);
        exit;
    }

    if ($action === 'save_timetable') {
        $timetableJson = json_encode($_SESSION['timetable_data'], JSON_UNESCAPED_UNICODE);
        $saveStatement = $db->prepare("UPDATE users SET timetable_data = :timetable_data, approval_status = 'pending', approved_by = NULL, approved_at = NULL, approval_message = 'Your timetable was updated and is waiting for PA approval.' WHERE id = :id");
        $saveStatement->execute([
            ':timetable_data' => $timetableJson,
            ':id' => $_SESSION['user_id'],
        ]);

        echo json_encode(['success' => true, 'message' => 'Timetable saved successfully.']);
        exit;
    }

    if ($action === 'change_profile') {
        $newSemester = trim((string)($_POST['semester'] ?? ''));
        $newClass = trim((string)($_POST['class'] ?? ''));
        $allowedSemesters = ['Semester 1', 'Semester 2', 'Semester 3', 'Semester 4', 'Semester 5'];
        $allowedClasses = ['S1', 'S2', 'S3', 'S4'];

        if (in_array($newSemester, $allowedSemesters, true) && in_array($newClass, $allowedClasses, true)) {
            $_SESSION['student_semester'] = $newSemester;
            $_SESSION['student_class_name'] = $newClass;
            $_SESSION['timetable_profile'] = $newSemester . '|' . $newClass;

            // Keep the timetable below untouched. Only the upper image preview should react to this dropdown change.
            echo json_encode(['success' => true, 'message' => 'Profile preview updated. Timetable remains unchanged.']);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Invalid profile selection.']);
        exit;
    }

    if ($action === 'clear_all') {
        $_SESSION['timetable_data'] = [];
        echo json_encode(['success' => true, 'message' => 'Semua jadual dipadam.']);
        exit;
    }

    if ($action === 'load_user_timetable') {
        $weekdayMap = ['Monday'=>'Isnin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Khamis','Friday'=>'Jumaat'];

        $requestedSemester = trim((string)($_POST['semester'] ?? $_SESSION['student_semester'] ?? $loggedSemester));
        $requestedClass = trim((string)($_POST['class'] ?? $_SESSION['student_class_name'] ?? $loggedClass));

        if (in_array($requestedSemester, ['Semester 1', 'Semester 2', 'Semester 3', 'Semester 4', 'Semester 5'], true) && in_array($requestedClass, ['S1', 'S2', 'S3', 'S4'], true)) {
            $loggedSemester = $requestedSemester;
            $loggedClass = $requestedClass;
            $_SESSION['student_semester'] = $requestedSemester;
            $_SESSION['student_class_name'] = $requestedClass;
        }

        $timetableProfile = $loggedSemester . '|' . $loggedClass;

        if (!in_array($timetableProfile, $supportedTimetableProfiles, true)) {
            $_SESSION['timetable_data'] = [];
            $_SESSION['timetable_sessions'] = [];
            $_SESSION['timetable_profile'] = $timetableProfile;
            $timetableCourses = [];
        } elseif ($timetableProfile === 'Semester 1|S1') {
            $timetableCourses = [
                [
                    'code' => 'MPU22153', 'title' => 'ENGLISH FOR DIGITAL TECHNOLOGY', 'lecturer' => 'ANSA / ASZO',
                    'sessions' => [ ['day'=>'Tuesday','startTime'=>'08:00','endTime'=>'10:00','room'=>'BPJPA2* (A)'], ['day'=>'Thursday','startTime'=>'13:00','endTime'=>'15:00','room'=>'BK43 (C)'] ]
                ],
                [
                    'code' => 'DBM10143', 'title' => 'CALCULUS AND ALGEBRA', 'lecturer' => 'BMAO',
                    'sessions' => [ ['day'=>'Monday','startTime'=>'13:00','endTime'=>'15:00','room'=>'BK4 (B)'], ['day'=>'Wednesday','startTime'=>'13:00','endTime'=>'15:00','room'=>'BK3 (B)'] ]
                ],
                [
                    'code' => 'DFC10263', 'title' => 'COMPUTER ARCHITECTURE', 'lecturer' => 'FNBZ',
                    'sessions' => [ ['day'=>'Monday','startTime'=>'10:00','endTime'=>'12:00','room'=>'ITL'], ['day'=>'Friday','startTime'=>'08:00','endTime'=>'10:00','room'=>'PGL1'] ]
                ],
                [
                    'code' => 'DFC10353', 'title' => 'PROGRAMMING FUNDAMENTALS', 'lecturer' => 'FCMC',
                    'sessions' => [ ['day'=>'Monday','startTime'=>'15:00','endTime'=>'17:00','room'=>'ITL'], ['day'=>'Friday','startTime'=>'15:00','endTime'=>'17:00','room'=>'PGL1'] ]
                ],
                [
                    'code' => 'DFC10273', 'title' => 'OPERATING SYSTEMS', 'lecturer' => 'FAZO',
                    'sessions' => [ ['day'=>'Tuesday','startTime'=>'15:00','endTime'=>'17:00','room'=>'ITL'], ['day'=>'Thursday','startTime'=>'08:00','endTime'=>'10:00','room'=>'PGL1'] ]
                ],
                [
                    'code' => 'MPU24XX1', 'title' => 'PROGRAM', 'lecturer' => 'PROGRAM',
                    'sessions' => [ ['day'=>'Wednesday','startTime'=>'15:00','endTime'=>'17:00','room'=>'BK30* (A)'] ]
                ],
                [
                    'code' => 'PA', 'title' => 'PERJUMPAAN PELAJAR', 'lecturer' => 'FABM',
                    'sessions' => [ ['day'=>'Thursday','startTime'=>'16:00','endTime'=>'17:00','room'=>'PA'] ]
                ],
            ];
        } elseif ($timetableProfile === 'Semester 1|S2') {
            $timetableCourses = [
                [
                    'code' => 'DFC10353', 'title' => 'PROGRAMMING FUNDAMENTALS', 'lecturer' => 'FCMC',
                    'sessions' => [ ['day'=>'Monday','startTime'=>'12:00','endTime'=>'14:00','room'=>'PGL1'], ['day'=>'Tuesday','startTime'=>'08:00','endTime'=>'10:00','room'=>'MML'] ]
                ],
                [
                    'code' => 'MPU22153', 'title' => 'ENGLISH FOR DIGITAL TECHNOLOGY', 'lecturer' => 'ASZO',
                    'sessions' => [ ['day'=>'Tuesday','startTime'=>'10:00','endTime'=>'12:00','room'=>'BPJPA2* (A)'], ['day'=>'Thursday','startTime'=>'10:00','endTime'=>'12:00','room'=>'BPJPA2* (A)'] ]
                ],
                [
                    'code' => 'DBM10143', 'title' => 'CALCULUS AND ALGEBRA', 'lecturer' => 'BSNH',
                    'sessions' => [ ['day'=>'Tuesday','startTime'=>'13:00','endTime'=>'15:00','room'=>'BK12 (B)'], ['day'=>'Thursday','startTime'=>'08:00','endTime'=>'10:00','room'=>'BK2 (B)'] ]
                ],
                [
                    'code' => 'DFC10273', 'title' => 'OPERATING SYSTEMS', 'lecturer' => 'FFWA',
                    'sessions' => [ ['day'=>'Wednesday','startTime'=>'08:00','endTime'=>'10:00','room'=>'HYP'], ['day'=>'Friday','startTime'=>'10:00','endTime'=>'12:00','room'=>'PGL2'] ]
                ],
                [
                    'code' => 'DFC10263', 'title' => 'COMPUTER ARCHITECTURE', 'lecturer' => 'FSMY',
                    'sessions' => [ ['day'=>'Wednesday','startTime'=>'13:00','endTime'=>'15:00','room'=>'PGL1'], ['day'=>'Friday','startTime'=>'08:00','endTime'=>'10:00','room'=>'HYP'] ]
                ],
                [
                    'code' => 'MPU24XX1', 'title' => 'PROGRAM DIT1-S2', 'lecturer' => 'PROGRAM',
                    'sessions' => [ ['day'=>'Wednesday','startTime'=>'15:00','endTime'=>'17:00','room'=>'DIT1-S2'] ]
                ],
                [
                    'code' => 'PA', 'title' => 'PERJUMPAAN PELAJAR', 'lecturer' => 'FFWA',
                    'sessions' => [ ['day'=>'Thursday','startTime'=>'16:00','endTime'=>'17:00','room'=>'PA'] ]
                ],
            ];
        } elseif ($timetableProfile === 'Semester 1|S3') {
            $timetableCourses = [
                [
                    'code' => 'MPU22153', 'title' => 'ENGLISH FOR DIGITAL TECHNOLOGY', 'lecturer' => 'ASZO',
                    'sessions' => [ ['day'=>'Monday','startTime'=>'08:00','endTime'=>'10:00','room'=>'BPJPA2* (A)'], ['day'=>'Thursday','startTime'=>'08:00','endTime'=>'10:00','room'=>'BPJPA2* (A)'] ]
                ],
                [
                    'code' => 'DFC10273', 'title' => 'PROGRAMMING FUNDAMENTALS', 'lecturer' => 'FHER',
                    'sessions' => [ ['day'=>'Monday','startTime'=>'13:00','endTime'=>'15:00','room'=>'HYP'], ['day'=>'Thursday','startTime'=>'12:00','endTime'=>'14:00','room'=>'PGL2'] ]
                ],
                [
                    'code' => 'DFC10353', 'title' => 'PROGRAMMING FUNDAMENTALS', 'lecturer' => 'FSBM',
                    'sessions' => [ ['day'=>'Monday','startTime'=>'15:00','endTime'=>'17:00','room'=>'HYP'], ['day'=>'Tuesday','startTime'=>'10:00','endTime'=>'12:00','room'=>'MML'] ]
                ],
                [
                    'code' => 'DBM10143', 'title' => 'CALCULUS AND ALGEBRA', 'lecturer' => 'BLKM',
                    'sessions' => [ ['day'=>'Tuesday','startTime'=>'08:00','endTime'=>'10:00','room'=>'TECC* (F)'], ['day'=>'Wednesday','startTime'=>'10:00','endTime'=>'12:00','room'=>'BK32# (C)'] ]
                ],
                [
                    'code' => 'DFC10263', 'title' => 'COMPUTER ARCHITECTURE', 'lecturer' => 'FDBM',
                    'sessions' => [ ['day'=>'Tuesday','startTime'=>'15:00','endTime'=>'17:00','room'=>'DCN'], ['day'=>'Friday','startTime'=>'08:00','endTime'=>'10:00','room'=>'PGL2'] ]
                ],
                [
                    'code' => 'MPU24XX1', 'title' => 'PROGRAM DIT1-S3', 'lecturer' => 'PROGRAM',
                    'sessions' => [ ['day'=>'Wednesday','startTime'=>'15:00','endTime'=>'17:00','room'=>'DIT1-S3'] ]
                ],
                [
                    'code' => 'PA', 'title' => 'PERJUMPAAN PELAJAR', 'lecturer' => 'FSYA',
                    'sessions' => [ ['day'=>'Thursday','startTime'=>'16:00','endTime'=>'17:00','room'=>'PA'] ]
                ],
            ];
        } elseif ($timetableProfile === 'Semester 1|S4') {
            $timetableCourses = [
                [
                    'code' => 'MPU22153', 'title' => 'ENGLISH FOR DIGITAL TECHNOLOGY', 'lecturer' => 'ASZO',
                    'sessions' => [ ['day'=>'Monday','startTime'=>'08:00','endTime'=>'10:00','room'=>'BPJPA2* (A)'], ['day'=>'Thursday','startTime'=>'08:00','endTime'=>'10:00','room'=>'BPJPA2* (A)'] ]
                ],
                [
                    'code' => 'DBM10143', 'title' => 'CALCULUS AND ALGEBRA', 'lecturer' => 'BSNH',
                    'sessions' => [ ['day'=>'Monday','startTime'=>'13:00','endTime'=>'15:00','room'=>'BK12 (B)'], ['day'=>'Wednesday','startTime'=>'10:00','endTime'=>'12:00','room'=>'BK10 (B)'] ]
                ],
                [
                    'code' => 'DFC10263', 'title' => 'COMPUTER ARCHITECTURE', 'lecturer' => 'FSMY',
                    'sessions' => [ ['day'=>'Tuesday','startTime'=>'08:00','endTime'=>'10:00','room'=>'ITL'], ['day'=>'Friday','startTime'=>'08:00','endTime'=>'10:00','room'=>'PGL1'] ]
                ],
                [
                    'code' => 'DFC10353', 'title' => 'PROGRAMMING FUNDAMENTALS', 'lecturer' => 'FHER',
                    'sessions' => [ ['day'=>'Tuesday','startTime'=>'10:00','endTime'=>'12:00','room'=>'MML'], ['day'=>'Friday','startTime'=>'15:00','endTime'=>'17:00','room'=>'PGL2'] ]
                ],
                [
                    'code' => 'DFC10273', 'title' => 'OPERATING SYSTEMS', 'lecturer' => 'FAZO',
                    'sessions' => [ ['day'=>'Wednesday','startTime'=>'08:00','endTime'=>'10:00','room'=>'HYP'], ['day'=>'Thursday','startTime'=>'13:00','endTime'=>'15:00','room'=>'PGL1'] ]
                ],
                [
                    'code' => 'MPU24XX1', 'title' => 'PROGRAM DIT1-S4', 'lecturer' => 'PROGRAM',
                    'sessions' => [ ['day'=>'Wednesday','startTime'=>'15:00','endTime'=>'17:00','room'=>'DIT1-S4'] ]
                ],
                [
                    'code' => 'PA', 'title' => 'PERJUMPAAN PELAJAR', 'lecturer' => 'FSYA',
                    'sessions' => [ ['day'=>'Thursday','startTime'=>'16:00','endTime'=>'17:00','room'=>'PA'] ]
                ],
            ];
        } elseif ($timetableProfile === 'Semester 2|S1') {
            $timetableCourses = [
                ['code' => 'DFC20283', 'title' => 'DATABASE FUNDAMENTALS', 'lecturer' => 'FFWA', 'sessions' => [ ['day'=>'Monday','startTime'=>'12:00','endTime'=>'14:00','room'=>'CSD'], ['day'=>'Tuesday','startTime'=>'08:00','endTime'=>'10:00','room'=>'CSD'] ]],
                ['code' => 'DFC20293', 'title' => 'NETWORK AND DATA COMMUNICATION', 'lecturer' => 'FMNR', 'sessions' => [ ['day'=>'Monday','startTime'=>'14:00','endTime'=>'17:00','room'=>'CSD'], ['day'=>'Thursday','startTime'=>'08:00','endTime'=>'10:00','room'=>'CSD'] ]],
                ['code' => 'DFK20013', 'title' => 'WEB DESIGN TECHNOLOGY', 'lecturer' => 'FNOM', 'sessions' => [ ['day'=>'Tuesday','startTime'=>'10:00','endTime'=>'13:00','room'=>'CSD'], ['day'=>'Friday','startTime'=>'10:00','endTime'=>'13:00','room'=>'APL'] ]],
                ['code' => 'MPU23162 / MPU23272', 'title' => 'PENGAJIAN ISLAM / PENDIDIKAN MORAL', 'lecturer' => 'ANMN / ADAJ', 'sessions' => [ ['day'=>'Tuesday','startTime'=>'13:00','endTime'=>'14:00','room'=>'BK30* (A) / BPJPA3* (A)'], ['day'=>'Wednesday','startTime'=>'10:00','endTime'=>'12:00','room'=>'BK30* (A) / BK33 (J)'] ]],
                ['code' => 'DFC20313', 'title' => 'CYBERSECURITY FUNDAMENTALS', 'lecturer' => 'FNBM', 'sessions' => [ ['day'=>'Wednesday','startTime'=>'08:00','endTime'=>'10:00','room'=>'CSD'], ['day'=>'Friday','startTime'=>'15:00','endTime'=>'17:00','room'=>'CSD'] ]],
                ['code' => 'DBM20153', 'title' => 'DISCRETE MATHEMATICS', 'lecturer' => 'BTES', 'sessions' => [ ['day'=>'Wednesday','startTime'=>'13:00','endTime'=>'15:00','room'=>'BK4 (B)'], ['day'=>'Friday','startTime'=>'08:00','endTime'=>'10:00','room'=>'BK41 (P)'] ]],
                ['code' => 'MPU24XX1', 'title' => 'PROGRAM DIT2-S1', 'lecturer' => 'PROGRAM', 'sessions' => [ ['day'=>'Wednesday','startTime'=>'15:00','endTime'=>'17:00','room'=>'PROGRAM'] ]],
                ['code' => 'PA', 'title' => 'PERJUMPAAN PELAJAR', 'lecturer' => 'FAAH', 'sessions' => [ ['day'=>'Thursday','startTime'=>'16:00','endTime'=>'17:00','room'=>'PA'] ]],
            ];
        } elseif ($timetableProfile === 'Semester 2|S2') {
            $timetableCourses = [
                ['code' => 'DFK20013', 'title' => 'WEB DESIGN TECHNOLOGY', 'lecturer' => 'FNOM', 'sessions' => [
                    ['day' => 'Monday', 'startTime' => '08:00', 'endTime' => '10:00', 'room' => 'CSD'],
                    ['day' => 'Tuesday', 'startTime' => '08:00', 'endTime' => '10:00', 'room' => 'CSD'],
                    ['day' => 'Wednesday', 'startTime' => '08:00', 'endTime' => '10:00', 'room' => 'CSD'],
                    ['day' => 'Thursday', 'startTime' => '08:00', 'endTime' => '10:00', 'room' => 'APL']
                ]],
                ['code' => 'MPU24XX1', 'title' => 'PROGRAM DIT2-S2', 'lecturer' => 'PROGRAM', 'sessions' => [
                    ['day' => 'Wednesday', 'startTime' => '15:00', 'endTime' => '17:00', 'room' => 'DIT2-S2']
                ]],
                ['code' => 'PA', 'title' => 'PERJUMPAAN PELAJAR', 'lecturer' => 'FAAH', 'sessions' => [
                    ['day' => 'Thursday', 'startTime' => '16:00', 'endTime' => '17:00', 'room' => 'PA']
                ]]
            ];
        } elseif ($timetableProfile === 'Semester 2|S3') {
            $timetableCourses = [
                ['code' => 'DFC20293', 'title' => 'NETWORK AND DATA COMMUNICATION', 'lecturer' => 'FSYA', 'sessions' => [
                    ['day' => 'Monday', 'startTime' => '08:00', 'endTime' => '10:00', 'room' => 'APL'],
                    ['day' => 'Friday', 'startTime' => '14:00', 'endTime' => '17:00', 'room' => 'APL']
                ]],
                ['code' => 'DFK20013', 'title' => 'WEB DESIGN TECHNOLOGY', 'lecturer' => 'FNOM', 'sessions' => [
                    ['day' => 'Monday', 'startTime' => '14:00', 'endTime' => '17:00', 'room' => 'APL'],
                    ['day' => 'Thursday', 'startTime' => '08:00', 'endTime' => '11:00', 'room' => 'APL']
                ]],
                ['code' => 'DFC20283', 'title' => 'DATABASE FUNDAMENTALS', 'lecturer' => 'FWAH', 'sessions' => [
                    ['day' => 'Tuesday', 'startTime' => '08:00', 'endTime' => '10:00', 'room' => 'APL'],
                    ['day' => 'Friday', 'startTime' => '10:00', 'endTime' => '12:00', 'room' => 'CSD']
                ]],
                ['code' => 'DFC20313', 'title' => 'CYBERSECURITY FUNDAMENTALS', 'lecturer' => 'FMNR', 'sessions' => [
                    ['day' => 'Tuesday', 'startTime' => '10:00', 'endTime' => '13:00', 'room' => 'APL'],
                    ['day' => 'Thursday', 'startTime' => '12:00', 'endTime' => '14:00', 'room' => 'APL']
                ]],
                ['code' => 'MPU23162/MPU23272', 'title' => 'PENGAJIAN ISLAM / PENDIDIKAN MORAL', 'lecturer' => 'ANMN / ADAJ', 'sessions' => [
                    ['day' => 'Tuesday', 'startTime' => '13:00', 'endTime' => '14:00', 'room' => 'BK30* (A) / BPJPA3* (A)'],
                    ['day' => 'Wednesday', 'startTime' => '10:00', 'endTime' => '12:00', 'room' => 'BK30* (A) / BK33 (J)']
                ]],
                ['code' => 'DBM20153', 'title' => 'DISCRETE MATHEMATICS', 'lecturer' => 'BTES', 'sessions' => [
                    ['day' => 'Wednesday', 'startTime' => '13:00', 'endTime' => '15:00', 'room' => 'BK4 (B)'],
                    ['day' => 'Friday', 'startTime' => '08:00', 'endTime' => '10:00', 'room' => 'BK41 (P)']
                ]],
                ['code' => 'MPU24XX1', 'title' => 'PROGRAM DIT2-S3', 'lecturer' => 'PROGRAM', 'sessions' => [
                    ['day' => 'Wednesday', 'startTime' => '15:00', 'endTime' => '17:00', 'room' => 'PROGRAM']
                ]],
                ['code' => 'PA', 'title' => 'PERJUMPAAN PELAJAR', 'lecturer' => 'FMNR', 'sessions' => [
                    ['day' => 'Thursday', 'startTime' => '16:00', 'endTime' => '17:00', 'room' => 'PA']
                ]]
            ];
        } elseif ($timetableProfile === 'Semester 3|S1') {
            $timetableCourses = [
                ['code' => 'DFK30053', 'title' => 'OBJECT ORIENTED PROGRAMMING', 'lecturer' => 'FNBD', 'sessions' => [
                    ['day' => 'Monday', 'startTime' => '08:00', 'endTime' => '10:00', 'room' => 'MML'],
                    ['day' => 'Friday', 'startTime' => '14:00', 'endTime' => '17:00', 'room' => 'MML']
                ]],
                ['code' => 'DFC30333', 'title' => 'ETHICS IN COMPUTING', 'lecturer' => 'FSBR', 'sessions' => [
                    ['day' => 'Monday', 'startTime' => '10:00', 'endTime' => '13:00', 'room' => 'MML'],
                    ['day' => 'Tuesday', 'startTime' => '15:00', 'endTime' => '17:00', 'room' => 'MML'],
                    ['day' => 'Wednesday', 'startTime' => '15:00', 'endTime' => '17:00', 'room' => 'MML']
                ]],
                ['code' => 'DFK30023', 'title' => 'USER EXPERIENCE FUNDAMENTALS', 'lecturer' => 'FKEF', 'sessions' => [
                    ['day' => 'Tuesday', 'startTime' => '08:00', 'endTime' => '10:00', 'room' => 'MML'],
                    ['day' => 'Thursday', 'startTime' => '08:00', 'endTime' => '11:00', 'room' => 'MML']
                ]],
                ['code' => 'DFK30033', 'title' => 'CYBERPRENEURSHIP', 'lecturer' => 'FTSK', 'sessions' => [
                    ['day' => 'Tuesday', 'startTime' => '12:00', 'endTime' => '15:00', 'room' => 'TECC* (F)'],
                    ['day' => 'Wednesday', 'startTime' => '15:00', 'endTime' => '17:00', 'room' => 'MML']
                ]],
                ['code' => 'DFP30313', 'title' => 'DIGITAL MULTIMEDIA', 'lecturer' => 'FAAH', 'sessions' => [
                    ['day' => 'Wednesday', 'startTime' => '08:00', 'endTime' => '11:00', 'room' => 'MML'],
                    ['day' => 'Friday', 'startTime' => '10:00', 'endTime' => '13:00', 'room' => 'MML']
                ]],
                ['code' => 'DBM30263', 'title' => 'STATISTICS AND PROBABILITY', 'lecturer' => 'BLYL', 'sessions' => [
                    ['day' => 'Wednesday', 'startTime' => '13:00', 'endTime' => '15:00', 'room' => 'DK2* (C)'],
                    ['day' => 'Thursday', 'startTime' => '13:00', 'endTime' => '15:00', 'room' => 'BK39 (J)']
                ]],
                ['code' => 'PA', 'title' => 'PERJUMPAAN PELAJAR', 'lecturer' => 'FAAN', 'sessions' => [
                    ['day' => 'Thursday', 'startTime' => '16:00', 'endTime' => '17:00', 'room' => 'HYP']
                ]]
            ];
        } elseif ($timetableProfile === 'Semester 3|S2') {
            $timetableCourses = [
                ['code' => 'DFP30313', 'title' => 'DIGITAL MULTIMEDIA', 'lecturer' => 'FTSK', 'sessions' => [
                    ['day' => 'Monday', 'startTime' => '08:00', 'endTime' => '10:00', 'room' => 'HYP'],
                    ['day' => 'Friday', 'startTime' => '13:00', 'endTime' => '15:00', 'room' => 'HYP'],
                    ['day' => 'Friday', 'startTime' => '15:00', 'endTime' => '17:00', 'room' => 'HYP']
                ]],
                ['code' => 'DFK30053', 'title' => 'OBJECT ORIENTED PROGRAMMING', 'lecturer' => 'FABM', 'sessions' => [
                    ['day' => 'Monday', 'startTime' => '10:00', 'endTime' => '13:00', 'room' => 'HYP'],
                    ['day' => 'Wednesday', 'startTime' => '10:00', 'endTime' => '13:00', 'room' => 'HYP']
                ]],
                ['code' => 'DFC30333', 'title' => 'ETHICS IN COMPUTING', 'lecturer' => 'FNBM', 'sessions' => [
                    ['day' => 'Tuesday', 'startTime' => '08:00', 'endTime' => '10:00', 'room' => 'HYP'],
                    ['day' => 'Thursday', 'startTime' => '08:00', 'endTime' => '11:00', 'room' => 'HYP']
                ]],
                ['code' => 'DFK30033', 'title' => 'CYBERPRENEURSHIP', 'lecturer' => 'FSBM', 'sessions' => [
                    ['day' => 'Tuesday', 'startTime' => '14:00', 'endTime' => '17:00', 'room' => 'HYP'],
                    ['day' => 'Wednesday', 'startTime' => '08:00', 'endTime' => '10:00', 'room' => 'HYP']
                ]],
                ['code' => 'DBM30263', 'title' => 'STATISTICS AND PROBABILITY', 'lecturer' => 'BLYL', 'sessions' => [
                    ['day' => 'Wednesday', 'startTime' => '13:00', 'endTime' => '15:00', 'room' => 'DK2* (C)'],
                    ['day' => 'Friday', 'startTime' => '10:00', 'endTime' => '12:00', 'room' => 'BK6 (J)']
                ]],
                ['code' => 'DFK30023', 'title' => 'USER EXPERIENCE FUNDAMENTALS', 'lecturer' => 'FNBZ', 'sessions' => [
                    ['day' => 'Thursday', 'startTime' => '11:00', 'endTime' => '14:00', 'room' => 'HYP'],
                    ['day' => 'Wednesday', 'startTime' => '15:00', 'endTime' => '17:00', 'room' => 'HYP']
                ]],
                ['code' => 'PA', 'title' => 'PERJUMPAAN PELAJAR', 'lecturer' => 'FKEF', 'sessions' => [
                    ['day' => 'Thursday', 'startTime' => '16:00', 'endTime' => '17:00', 'room' => 'PA']
                ]]
            ];
        } elseif ($timetableProfile === 'Semester 3|S3') {
            $timetableCourses = [
                ['code' => 'DFN30533', 'title' => 'SWITCHING AND ROUTING ESSENTIALS', 'lecturer' => 'FSFM', 'sessions' => [
                    ['day' => 'Monday', 'startTime' => '10:00', 'endTime' => '11:00', 'room' => 'NWL'],
                    ['day' => 'Monday', 'startTime' => '11:00', 'endTime' => '13:00', 'room' => 'NWL'],
                    ['day' => 'Wednesday', 'startTime' => '15:00', 'endTime' => '17:00', 'room' => 'NWL'],
                    ['day' => 'Thursday', 'startTime' => '13:00', 'endTime' => '15:00', 'room' => 'DCN']
                ]],
                ['code' => 'DBM30263', 'title' => 'STATISTICS AND PROBABILITY', 'lecturer' => 'BLYL', 'sessions' => [
                    ['day' => 'Tuesday', 'startTime' => '08:00', 'endTime' => '10:00', 'room' => 'DK2* (C)'],
                    ['day' => 'Wednesday', 'startTime' => '08:00', 'endTime' => '10:00', 'room' => 'BK3 (B)']
                ]],
                ['code' => 'DFK30053', 'title' => 'OBJECT ORIENTED PROGRAMMING', 'lecturer' => 'FCMC', 'sessions' => [
                    ['day' => 'Tuesday', 'startTime' => '12:00', 'endTime' => '15:00', 'room' => 'MML'],
                    ['day' => 'Friday', 'startTime' => '10:00', 'endTime' => '13:00', 'room' => 'PGL1']
                ]],
                ['code' => 'DFK30023', 'title' => 'USER EXPERIENCE FUNDAMENTALS', 'lecturer' => 'FNYH', 'sessions' => [
                    ['day' => 'Tuesday', 'startTime' => '15:00', 'endTime' => '17:00', 'room' => 'TECC* (F)'],
                    ['day' => 'Thursday', 'startTime' => '10:00', 'endTime' => '13:00', 'room' => 'PGL1']
                ]],
                ['code' => 'DFK30033', 'title' => 'CYBERPRENEURSHIP', 'lecturer' => 'FSBR', 'sessions' => [
                    ['day' => 'Wednesday', 'startTime' => '10:00', 'endTime' => '13:00', 'room' => 'PGL1'],
                    ['day' => 'Thursday', 'startTime' => '08:00', 'endTime' => '10:00', 'room' => 'PGL1']
                ]],
                ['code' => 'DFC30333', 'title' => 'ETHICS IN COMPUTING', 'lecturer' => 'FNZH', 'sessions' => [
                    ['day' => 'Thursday', 'startTime' => '13:00', 'endTime' => '15:00', 'room' => 'PGL1'],
                    ['day' => 'Friday', 'startTime' => '14:00', 'endTime' => '17:00', 'room' => 'PGL1']
                ]],
                ['code' => 'PA', 'title' => 'PERJUMPAAN PELAJAR', 'lecturer' => 'FSFM', 'sessions' => [
                    ['day' => 'Thursday', 'startTime' => '16:00', 'endTime' => '17:00', 'room' => 'PA']
                ]]
            ];
        } elseif ($timetableProfile === 'Semester 3|S4') {
            $timetableCourses = [
                ['code' => 'DFK30053', 'title' => 'OBJECT ORIENTED PROGRAMMING', 'lecturer' => 'FDBM', 'sessions' => [
                    ['day' => 'Monday', 'startTime' => '08:00', 'endTime' => '11:00', 'room' => 'ITL'],
                    ['day' => 'Friday', 'startTime' => '10:00', 'endTime' => '13:00', 'room' => 'PGL2']
                ]],
                ['code' => 'DFK30033', 'title' => 'CYBERPRENEURSHIP', 'lecturer' => 'FSMY', 'sessions' => [
                    ['day' => 'Monday', 'startTime' => '11:00', 'endTime' => '14:00', 'room' => 'PGL2'],
                    ['day' => 'Friday', 'startTime' => '10:00', 'endTime' => '13:00', 'room' => 'PGL2']
                ]],
                ['code' => 'DBM30263', 'title' => 'STATISTICS AND PROBABILITY', 'lecturer' => 'BLYL', 'sessions' => [
                    ['day' => 'Tuesday', 'startTime' => '08:00', 'endTime' => '10:00', 'room' => 'DK2* (C)'],
                    ['day' => 'Thursday', 'startTime' => '08:00', 'endTime' => '10:00', 'room' => 'BK41 (P)']
                ]],
                ['code' => 'DFN30533', 'title' => 'SWITCHING AND ROUTING ESSENTIALS', 'lecturer' => 'FSYA', 'sessions' => [
                    ['day' => 'Tuesday', 'startTime' => '12:00', 'endTime' => '14:00', 'room' => 'DCN'],
                    ['day' => 'Tuesday', 'startTime' => '14:00', 'endTime' => '15:00', 'room' => 'DCN'],
                    ['day' => 'Thursday', 'startTime' => '13:00', 'endTime' => '15:00', 'room' => 'NWL']
                ]],
                ['code' => 'DFC30333', 'title' => 'ETHICS IN COMPUTING', 'lecturer' => 'FNMP', 'sessions' => [
                    ['day' => 'Tuesday', 'startTime' => '15:00', 'endTime' => '17:00', 'room' => 'PGL1'],
                    ['day' => 'Wednesday', 'startTime' => '08:00', 'endTime' => '11:00', 'room' => 'PGL2']
                ]],
                ['code' => 'DFK30023', 'title' => 'USER EXPERIENCE FUNDAMENTALS', 'lecturer' => 'FAFO', 'sessions' => [
                    ['day' => 'Wednesday', 'startTime' => '11:00', 'endTime' => '14:00', 'room' => 'PGL2'],
                    ['day' => 'Thursday', 'startTime' => '10:00', 'endTime' => '13:00', 'room' => 'PGL2']
                ]],
                ['code' => 'PA', 'title' => 'PERJUMPAAN PELAJAR', 'lecturer' => 'FSCA', 'sessions' => [
                    ['day' => 'Thursday', 'startTime' => '16:00', 'endTime' => '17:00', 'room' => 'PA']
                ]]
            ];
        } elseif ($timetableProfile === 'Semester 4|S1') {
            $timetableCourses = [
                ['code' => 'DFK40083', 'title' => 'STUDY SUBJECT', 'lecturer' => 'FJAG / FNAB', 'sessions' => [ ['day'=>'Monday','startTime'=>'08:00','endTime'=>'10:00','room'=>'DCN'], ['day'=>'Tuesday','startTime'=>'14:00','endTime'=>'17:00','room'=>'NWL'] ]],
                ['code' => 'DFK40073', 'title' => 'CLOUD COMPUTING', 'lecturer' => 'FZHI', 'sessions' => [ ['day'=>'Monday','startTime'=>'10:00','endTime'=>'12:00','room'=>'CSD'], ['day'=>'Wednesday','startTime'=>'14:00','endTime'=>'17:00','room'=>'APL'] ]],
                ['code' => 'DFP40453', 'title' => 'MOBILE APPLICATION DEVELOPMENT', 'lecturer' => 'FAAH', 'sessions' => [ ['day'=>'Monday','startTime'=>'14:00','endTime'=>'17:00','room'=>'PGL2'], ['day'=>'Tuesday','startTime'=>'08:00','endTime'=>'09:00','room'=>'PGL1'], ['day'=>'Thursday','startTime'=>'08:00','endTime'=>'11:00','room'=>'ITL'] ]],
                ['code' => 'DFP30313', 'title' => 'DIGITAL MULTIMEDIA', 'lecturer' => 'FNBZ', 'sessions' => [ ['day'=>'Tuesday','startTime'=>'09:00','endTime'=>'12:00','room'=>'PGL1'], ['day'=>'Thursday','startTime'=>'08:00','endTime'=>'11:00','room'=>'ITL'] ]],
                ['code' => 'DFP40443', 'title' => 'FULL STACK WEB DEVELOPMENT', 'lecturer' => 'FNOM', 'sessions' => [ ['day'=>'Wednesday','startTime'=>'08:00','endTime'=>'12:00','room'=>'APL'], ['day'=>'Friday','startTime'=>'08:00','endTime'=>'11:00','room'=>'APL'] ]],
                ['code' => 'DFC40343', 'title' => 'SYSTEM ANALYSIS AND DESIGN FUNDAMENTALS', 'lecturer' => 'FFWA', 'sessions' => [ ['day'=>'Wednesday','startTime'=>'12:00','endTime'=>'13:00','room'=>'APL'], ['day'=>'Thursday','startTime'=>'11:00','endTime'=>'13:00','room'=>'APL'] ]],
                ['code' => 'PA', 'title' => 'PERJUMPAAN PELAJAR', 'lecturer' => 'FAZO', 'sessions' => [ ['day'=>'Thursday','startTime'=>'16:00','endTime'=>'17:00','room'=>'MML'] ]],
            ];
        } elseif ($timetableProfile === 'Semester 4|S3') {
            $timetableCourses = [
                ['code' => 'DFK40083', 'title' => 'STUDY SUBJECT', 'lecturer' => 'FHBA', 'sessions' => [ ['day'=>'Monday','startTime'=>'08:00','endTime'=>'10:00','room'=>'NWL'], ['day'=>'Wednesday','startTime'=>'14:00','endTime'=>'17:00','room'=>'DCN'] ]],
                ['code' => 'DFK40073', 'title' => 'CLOUD COMPUTING', 'lecturer' => 'FKEF', 'sessions' => [ ['day'=>'Monday','startTime'=>'10:00','endTime'=>'13:00','room'=>'APL'], ['day'=>'Wednesday','startTime'=>'12:00','endTime'=>'13:00','room'=>'NWL'] ]],
                ['code' => 'DFK30023', 'title' => 'USER EXPERIENCE FUNDAMENTALS', 'lecturer' => 'FNMP', 'sessions' => [ ['day'=>'Tuesday','startTime'=>'08:00','endTime'=>'11:00','room'=>'ITL'], ['day'=>'Thursday','startTime'=>'08:00','endTime'=>'11:00','room'=>'PGL2'] ]],
                ['code' => 'DFN40503', 'title' => 'EMBEDDED INTERNET OF THINGS (IOT)', 'lecturer' => 'FAAN', 'sessions' => [ ['day'=>'Tuesday','startTime'=>'11:00','endTime'=>'14:00','room'=>'ITL'], ['day'=>'Friday','startTime'=>'08:00','endTime'=>'10:00','room'=>'CSD'] ]],
                ['code' => 'DFC40343', 'title' => 'SYSTEM ANALYSIS AND DESIGN FUNDAMENTALS', 'lecturer' => 'FRBM', 'sessions' => [ ['day'=>'Tuesday','startTime'=>'15:00','endTime'=>'17:00','room'=>'APL'], ['day'=>'Thursday','startTime'=>'13:00','endTime'=>'15:00','room'=>'CSD'] ]],
                ['code' => 'DFN40553', 'title' => 'STRUCTURED CABLING IN NETWORKING', 'lecturer' => 'FSFM', 'sessions' => [ ['day'=>'Wednesday','startTime'=>'08:00','endTime'=>'12:00','room'=>'NWL'], ['day'=>'Friday','startTime'=>'10:00','endTime'=>'12:00','room'=>'NWL'] ]],
                ['code' => 'PA', 'title' => 'PERJUMPAAN PELAJAR', 'lecturer' => 'FAFO', 'sessions' => [ ['day'=>'Thursday','startTime'=>'16:00','endTime'=>'17:00','room'=>'PA'] ]],
            ];
        } elseif ($timetableProfile === 'Semester 5|S1') {
            $timetableCourses = [
                ['code' => 'DFK50083', 'title' => 'PYTHON PROGRAMMING', 'lecturer' => 'FRBS', 'sessions' => [ ['day'=>'Monday','startTime'=>'08:00','endTime'=>'11:00','room'=>'PGL2'], ['day'=>'Wednesday','startTime'=>'12:00','endTime'=>'14:00','room'=>'MML'] ]],
                ['code' => 'DFK50093', 'title' => 'COMPUTER NETWORK SECURITY', 'lecturer' => 'FNZH', 'sessions' => [ ['day'=>'Tuesday','startTime'=>'08:00','endTime'=>'11:00','room'=>'NWL'], ['day'=>'Thursday','startTime'=>'08:00','endTime'=>'10:00','room'=>'DCN'] ]],
                ['code' => 'DFP40463', 'title' => 'PROJECT / FINAL YEAR', 'lecturer' => 'FWAH', 'sessions' => [ ['day'=>'Tuesday','startTime'=>'11:00','endTime'=>'14:00','room'=>'PGL2'], ['day'=>'Friday','startTime'=>'14:00','endTime'=>'17:00','room'=>'ITL'] ]],
                ['code' => 'DFP40433', 'title' => 'BUSINESS INTELLIGENCE', 'lecturer' => 'FZHI', 'sessions' => [ ['day'=>'Wednesday','startTime'=>'08:00','endTime'=>'11:00','room'=>'ITL'], ['day'=>'Friday','startTime'=>'08:00','endTime'=>'10:00','room'=>'MML'] ]],
                ['code' => 'MPU21072', 'title' => 'PENGHAYATAN ETIKA DAN PERADABAN', 'lecturer' => 'ANFT', 'sessions' => [ ['day'=>'Wednesday','startTime'=>'11:00','endTime'=>'12:00','room'=>'BK41(P)'], ['day'=>'Thursday','startTime'=>'12:00','endTime'=>'14:00','room'=>'BK37(G)'] ]],
                ['code' => 'MPU22071', 'title' => 'KURSUS INTEGRITI DAN ANTI RASUAH', 'lecturer' => 'AHAJ', 'sessions' => [ ['day'=>'Thursday','startTime'=>'10:00','endTime'=>'12:00','room'=>'BK45*(G)'] ]],
                ['code' => 'PA', 'title' => 'PERJUMPAAN PELAJAR', 'lecturer' => 'FZHI', 'sessions' => [ ['day'=>'Thursday','startTime'=>'16:00','endTime'=>'17:00','room'=>'CSD'] ]],
            ];
        } elseif ($timetableProfile === 'Semester 5|S2') {
            $timetableCourses = [
                ['code' => 'MPU21072', 'title' => 'PENGHAYATAN ETIKA DAN PERADABAN', 'lecturer' => 'ANFT', 'sessions' => [ ['day'=>'Monday','startTime'=>'08:00','endTime'=>'10:00','room'=>'BK44 (C)'], ['day'=>'Wednesday','startTime'=>'10:00','endTime'=>'11:00','room'=>'BK41 (P)'] ]],
                ['code' => 'DFK50083', 'title' => 'PYTHON PROGRAMMING', 'lecturer' => 'FAAN', 'sessions' => [ ['day'=>'Monday','startTime'=>'14:00','endTime'=>'17:00','room'=>'MML'], ['day'=>'Wednesday','startTime'=>'08:00','endTime'=>'10:00','room'=>'PGL1'], ['day'=>'Wednesday','startTime'=>'11:00','endTime'=>'13:00','room'=>'DCN'] ]],
                ['code' => 'MPU22071', 'title' => 'KURSUS INTEGRITI DAN ANTI RASUAH', 'lecturer' => 'AHAJ', 'sessions' => [ ['day'=>'Tuesday','startTime'=>'08:00','endTime'=>'10:00','room'=>'BK12 (B)'] ]],
                ['code' => 'DFP50463', 'title' => 'JAVA-BASED APPLICATION DEVELOPMENT', 'lecturer' => 'FZHI', 'sessions' => [ ['day'=>'Tuesday','startTime'=>'10:00','endTime'=>'13:00','room'=>'HYP'], ['day'=>'Thursday','startTime'=>'13:00','endTime'=>'15:00','room'=>'MML'] ]],
                ['code' => 'DFP40433', 'title' => 'BUSINESS INTELLIGENCE', 'lecturer' => 'FSNA', 'sessions' => [ ['day'=>'Tuesday','startTime'=>'13:00','endTime'=>'15:00','room'=>'PGL1'], ['day'=>'Friday','startTime'=>'08:00','endTime'=>'11:00','room'=>'ITL'] ]],
                ['code' => 'DFK50093', 'title' => 'COMPUTER NETWORK SECURITY', 'lecturer' => 'FSCA', 'sessions' => [ ['day'=>'Thursday','startTime'=>'11:00','endTime'=>'13:00','room'=>'NWL'] ]],
                ['code' => 'PA', 'title' => 'PERJUMPAAN PELAJAR', 'lecturer' => 'FDBM', 'sessions' => [ ['day'=>'Thursday','startTime'=>'16:00','endTime'=>'17:00','room'=>'PA'] ]],
            ];
        } elseif ($timetableProfile === 'Semester 5|S3') {
            $timetableCourses = [
                ['code' => 'MPU21072', 'title' => 'PENGHAYATAN ETIKA DAN PERADABAN', 'lecturer' => 'ANKS / ANMN', 'sessions' => [ ['day'=>'Monday','startTime'=>'08:00','endTime'=>'09:00','room'=>'BK30*(A)'], ['day'=>'Thursday','startTime'=>'11:00','endTime'=>'13:00','room'=>'BK41(P)'] ]],
                ['code' => 'DFN40543', 'title' => 'ENTERPRISE NETWORK', 'lecturer' => 'FSNA / FSCA', 'sessions' => [ ['day'=>'Monday','startTime'=>'09:00','endTime'=>'10:00','room'=>'NWL'], ['day'=>'Monday','startTime'=>'10:00','endTime'=>'12:00','room'=>'PGL1'], ['day'=>'Wednesday','startTime'=>'08:00','endTime'=>'11:00','room'=>'DCN'], ['day'=>'Friday','startTime'=>'10:00','endTime'=>'12:00','room'=>'DCN'] ]],
                ['code' => 'DFK50083', 'title' => 'PYTHON PROGRAMMING', 'lecturer' => 'FSNA / FNZH', 'sessions' => [ ['day'=>'Monday','startTime'=>'14:00','endTime'=>'15:00','room'=>'DCN'], ['day'=>'Monday','startTime'=>'15:00','endTime'=>'17:00','room'=>'DCN'], ['day'=>'Tuesday','startTime'=>'08:00','endTime'=>'10:00','room'=>'PGL2'], ['day'=>'Wednesday','startTime'=>'11:00','endTime'=>'13:00','room'=>'NWL'], ['day'=>'Friday','startTime'=>'08:00','endTime'=>'10:00','room'=>'NWL'] ]],
                ['code' => 'DFN50563', 'title' => 'ADVANCED SERVER ADMINISTRATION', 'lecturer' => 'FJAG', 'sessions' => [ ['day'=>'Monday','startTime'=>'14:00','endTime'=>'15:00','room'=>'DCN'], ['day'=>'Monday','startTime'=>'15:00','endTime'=>'17:00','room'=>'DCN'], ['day'=>'Thursday','startTime'=>'13:00','endTime'=>'15:00','room'=>'DCN'] ]],
                ['code' => 'MPU22071', 'title' => 'KURSUS INTEGRITI DAN ANTI RASUAH', 'lecturer' => 'AHAJ', 'sessions' => [ ['day'=>'Tuesday','startTime'=>'10:00','endTime'=>'12:00','room'=>'BK41(P)'] ]],
                ['code' => 'DFK50083', 'title' => 'PYTHON PROGRAMMING', 'lecturer' => 'FNZH', 'sessions' => [ ['day'=>'Wednesday','startTime'=>'11:00','endTime'=>'13:00','room'=>'NWL'] ]],
                ['code' => 'PA', 'title' => 'PERJUMPAAN PELAJAR', 'lecturer' => 'FNZH', 'sessions' => [ ['day'=>'Thursday','startTime'=>'16:00','endTime'=>'17:00','room'=>'PA'] ]],
            ];
        } elseif ($timetableProfile === 'Semester 5|S4') {
            $timetableCourses = [
                ['code' => 'DFN40543', 'title' => 'ENTERPRISE NETWORK', 'lecturer' => 'FSAN', 'sessions' => [ ['day'=>'Monday','startTime'=>'10:00','endTime'=>'13:00','room'=>'DCN'], ['day'=>'Tuesday','startTime'=>'08:00','endTime'=>'11:00','room'=>'DCN'] ]],
                ['code' => 'DFK50083', 'title' => 'PYTHON PROGRAMMING', 'lecturer' => 'FRBS', 'sessions' => [ ['day'=>'Monday','startTime'=>'14:00','endTime'=>'16:00','room'=>'PGL1'], ['day'=>'Friday','startTime'=>'10:00','endTime'=>'13:00','room'=>'HYP'] ]],
                ['code' => 'DFK50093', 'title' => 'COMPUTER NETWORK SECURITY', 'lecturer' => 'FRBS', 'sessions' => [ ['day'=>'Tuesday','startTime'=>'11:00','endTime'=>'13:00','room'=>'NWL'], ['day'=>'Thursday','startTime'=>'08:00','endTime'=>'11:00','room'=>'NWL'] ]],
                ['code' => 'MPU22071', 'title' => 'KURSUS INTEGRITI DAN ANTI RASUAH', 'lecturer' => 'AHAJ', 'sessions' => [ ['day'=>'Tuesday','startTime'=>'13:00','endTime'=>'15:00','room'=>'BK45*(G)'] ]],
                ['code' => 'MPU21072', 'title' => 'PENGHAYATAN ETIKA DAN PERADABAN', 'lecturer' => 'ANMN', 'sessions' => [ ['day'=>'Wednesday','startTime'=>'13:00','endTime'=>'14:00','room'=>'BK30*(A)'], ['day'=>'Thursday','startTime'=>'12:00','endTime'=>'14:00','room'=>'BPJPA1*(A)'] ]],
                ['code' => 'DFN50563', 'title' => 'ADVANCED SERVER ADMINISTRATION', 'lecturer' => 'FHBA', 'sessions' => [ ['day'=>'Friday','startTime'=>'08:00','endTime'=>'10:00','room'=>'DCN'], ['day'=>'Friday','startTime'=>'14:00','endTime'=>'17:00','room'=>'NWL'] ]],
                ['code' => 'PA', 'title' => 'PERJUMPAAN PELAJAR', 'lecturer' => 'FTSK', 'sessions' => [ ['day'=>'Thursday','startTime'=>'16:00','endTime'=>'17:00','room'=>'PA'] ]],
            ];
        } elseif ($timetableProfile === 'Semester 2|S4') {
            $timetableCourses = [
                ['code' => 'DFC20293', 'title' => 'NETWORK AND DATA COMMUNICATION', 'lecturer' => 'FSYA', 'sessions' => [ ['day'=>'Monday','startTime'=>'08:00','endTime'=>'10:00','room'=>'APL'], ['day'=>'Thursday','startTime'=>'08:00','endTime'=>'10:00','room'=>'CSD'] ]],
                ['code' => 'DFK20013', 'title' => 'WEB DESIGN TECHNOLOGY', 'lecturer' => 'FNOM', 'sessions' => [ ['day'=>'Monday','startTime'=>'10:00','endTime'=>'13:00','room'=>'APL'], ['day'=>'Tuesday','startTime'=>'10:00','endTime'=>'13:00','room'=>'APL'] ]],
                ['code' => 'DFC20283', 'title' => 'DATABASE FUNDAMENTALS', 'lecturer' => 'FFWA', 'sessions' => [ ['day'=>'Tuesday','startTime'=>'08:00','endTime'=>'10:00','room'=>'CSD'], ['day'=>'Friday','startTime'=>'10:00','endTime'=>'12:00','room'=>'CSD'] ]],
                ['code' => 'DFC20313', 'title' => 'CYBERSECURITY FUNDAMENTALS', 'lecturer' => 'FNBM', 'sessions' => [ ['day'=>'Wednesday','startTime'=>'08:00','endTime'=>'10:00','room'=>'CSD'], ['day'=>'Friday','startTime'=>'15:00','endTime'=>'17:00','room'=>'CSD'] ]],
                ['code' => 'MPU23162 / MPU23272', 'title' => 'PENGAJIAN ISLAM / PENDIDIKAN MORAL', 'lecturer' => 'ANMN / ADAJ', 'sessions' => [ ['day'=>'Tuesday','startTime'=>'13:00','endTime'=>'14:00','room'=>'BK30* (A) / BPJPA3* (A)'], ['day'=>'Wednesday','startTime'=>'10:00','endTime'=>'12:00','room'=>'BK30* (A) / BK33 (J)'] ]],
                ['code' => 'DBM20153', 'title' => 'DISCRETE MATHEMATICS', 'lecturer' => 'BTES', 'sessions' => [ ['day'=>'Wednesday','startTime'=>'13:00','endTime'=>'15:00','room'=>'BK4 (B)'], ['day'=>'Friday','startTime'=>'08:00','endTime'=>'10:00','room'=>'BK41 (P)'] ]],
                ['code' => 'MPU24XX1', 'title' => 'PROGRAM DIT2-S4', 'lecturer' => 'PROGRAM', 'sessions' => [ ['day'=>'Wednesday','startTime'=>'15:00','endTime'=>'17:00','room'=>'DIT2-S4'] ]],
                ['code' => 'PA', 'title' => 'PERJUMPAAN PELAJAR', 'lecturer' => 'FAAH', 'sessions' => [ ['day'=>'Thursday','startTime'=>'16:00','endTime'=>'17:00','room'=>'PA'] ]],
            ];
        } elseif ($timetableProfile === 'Semester 4|S2') {
            $timetableCourses = [
                ['code' => 'DFK40083', 'title' => 'STUDY SUBJECT', 'lecturer' => 'FJAG / FNAB', 'sessions' => [ ['day'=>'Monday','startTime'=>'08:00','endTime'=>'10:00','room'=>'DCN'], ['day'=>'Tuesday','startTime'=>'14:00','endTime'=>'17:00','room'=>'NWL'] ]],
                ['code' => 'DFK40073', 'title' => 'CLOUD COMPUTING', 'lecturer' => 'FZHI', 'sessions' => [ ['day'=>'Monday','startTime'=>'10:00','endTime'=>'12:00','room'=>'CSD'], ['day'=>'Wednesday','startTime'=>'14:00','endTime'=>'17:00','room'=>'APL'] ]],
                ['code' => 'DFP40453', 'title' => 'MOBILE APPLICATION DEVELOPMENT', 'lecturer' => 'FAAH', 'sessions' => [ ['day'=>'Monday','startTime'=>'14:00','endTime'=>'17:00','room'=>'PGL2'], ['day'=>'Tuesday','startTime'=>'08:00','endTime'=>'09:00','room'=>'PGL1'], ['day'=>'Thursday','startTime'=>'08:00','endTime'=>'11:00','room'=>'ITL'] ]],
                ['code' => 'DFP40433', 'title' => 'BUSINESS INTELLIGENCE', 'lecturer' => 'FZHI', 'sessions' => [ ['day'=>'Tuesday','startTime'=>'09:00','endTime'=>'12:00','room'=>'PGL1'], ['day'=>'Friday','startTime'=>'08:00','endTime'=>'10:00','room'=>'MML'] ]],
                ['code' => 'DFC40343', 'title' => 'SYSTEM ANALYSIS AND DESIGN FUNDAMENTALS', 'lecturer' => 'FFWA', 'sessions' => [ ['day'=>'Wednesday','startTime'=>'08:00','endTime'=>'10:00','room'=>'APL'], ['day'=>'Thursday','startTime'=>'11:00','endTime'=>'13:00','room'=>'APL'] ]],
                ['code' => 'DFN40553', 'title' => 'STRUCTURED CABLING IN NETWORKING', 'lecturer' => 'FSFM', 'sessions' => [ ['day'=>'Wednesday','startTime'=>'10:00','endTime'=>'12:00','room'=>'NWL'], ['day'=>'Friday','startTime'=>'10:00','endTime'=>'12:00','room'=>'NWL'] ]],
                ['code' => 'PA', 'title' => 'PERJUMPAAN PELAJAR', 'lecturer' => 'FAZO', 'sessions' => [ ['day'=>'Thursday','startTime'=>'16:00','endTime'=>'17:00','room'=>'MML'] ]],
            ];
        } elseif ($timetableProfile === 'Semester 4|S4') {
            $timetableCourses = [
                ['code' => 'DFK40083', 'title' => 'STUDY SUBJECT', 'lecturer' => 'FHBA', 'sessions' => [ ['day'=>'Monday','startTime'=>'08:00','endTime'=>'10:00','room'=>'NWL'], ['day'=>'Wednesday','startTime'=>'14:00','endTime'=>'17:00','room'=>'DCN'] ]],
                ['code' => 'DFK40073', 'title' => 'CLOUD COMPUTING', 'lecturer' => 'FKEF', 'sessions' => [ ['day'=>'Monday','startTime'=>'10:00','endTime'=>'13:00','room'=>'APL'], ['day'=>'Wednesday','startTime'=>'12:00','endTime'=>'13:00','room'=>'NWL'] ]],
                ['code' => 'DFP40453', 'title' => 'MOBILE APPLICATION DEVELOPMENT', 'lecturer' => 'FAAH', 'sessions' => [ ['day'=>'Tuesday','startTime'=>'08:00','endTime'=>'10:00','room'=>'PGL2'], ['day'=>'Thursday','startTime'=>'08:00','endTime'=>'11:00','room'=>'ITL'] ]],
                ['code' => 'DFP40443', 'title' => 'FULL STACK WEB DEVELOPMENT', 'lecturer' => 'FNOM', 'sessions' => [ ['day'=>'Tuesday','startTime'=>'10:00','endTime'=>'13:00','room'=>'APL'], ['day'=>'Friday','startTime'=>'08:00','endTime'=>'11:00','room'=>'APL'] ]],
                ['code' => 'DFC40343', 'title' => 'SYSTEM ANALYSIS AND DESIGN FUNDAMENTALS', 'lecturer' => 'FRBM', 'sessions' => [ ['day'=>'Tuesday','startTime'=>'15:00','endTime'=>'17:00','room'=>'APL'], ['day'=>'Thursday','startTime'=>'13:00','endTime'=>'15:00','room'=>'CSD'] ]],
                ['code' => 'DFN40503', 'title' => 'EMBEDDED INTERNET OF THINGS (IOT)', 'lecturer' => 'FAAN', 'sessions' => [ ['day'=>'Wednesday','startTime'=>'08:00','endTime'=>'11:00','room'=>'ITL'], ['day'=>'Friday','startTime'=>'08:00','endTime'=>'10:00','room'=>'CSD'] ]],
                ['code' => 'PA', 'title' => 'PERJUMPAAN PELAJAR', 'lecturer' => 'FAFO', 'sessions' => [ ['day'=>'Thursday','startTime'=>'16:00','endTime'=>'17:00','room'=>'PA'] ]],
            ];
        } else {
            $timetableCourses = [];
        }

        $_SESSION['timetable_sessions'] = [];
        $_SESSION['timetable_data'] = [];

        foreach ($timetableCourses as $course) {
            foreach ($course['sessions'] as $sess) {
                $day = $weekdayMap[$sess['day']] ?? $sess['day'];
                $start = $sess['startTime'];
                $end = $sess['endTime'];

                // store session record for export and reference
                $_SESSION['timetable_sessions'][] = [
                    'code'=>$course['code'],'title'=>$course['title'],'lecturer'=>$course['lecturer'],'room'=>$sess['room'],'day'=>$day,'start'=>$start,'end'=>$end
                ];

                // populate hourly slots between start and end (end exclusive)
                $curr = strtotime($start);
                $endT = strtotime($end);
                while ($curr < $endT) {
                    $next = strtotime('+1 hour', $curr);
                    $slot = date('H:00', $curr) . '-' . date('H:00', $next);
                    // only insert if that slot exists in the timetable's timeSlots
                    if (in_array($slot, $timeSlots, true)) {
                        $slotKey = $day . '|' . $slot;
                        if (!isset($_SESSION['timetable_data'][$slotKey])) {
                            $_SESSION['timetable_data'][$slotKey] = [
                                'subject' => $course['code'] . ' — ' . $course['title'],
                                'code' => $course['code'],
                                'title' => $course['title'],
                                'lecturer' => $course['lecturer'],
                                'room' => $sess['room'],
                                'day' => $day,
                                'time_slot' => $slot,
                            ];
                        }
                    }
                    $curr = $next;
                }
            }
        }

        $_SESSION['timetable_profile'] = $timetableProfile;
        echo json_encode(['success' => true, 'message' => empty($timetableCourses) ? 'No timetable available for this class.' : 'Timetable loaded.']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JTMK Timetable Management System</title>
    <style>
        :root {
            --primary: #0d6efd;
            --primary-dark: #0a58ca;
            --border: #dfe3e8;
            --text: #1f2937;
            --muted: #6b7280;
            --panel: #ffffff;
            --shadow: rgba(0, 0, 0, 0.08);
            --filled: #dff3ff;
            --filled-border: #7cc2f4;
            --danger: #dc3545;
            --danger-dark: #b02a37;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: var(--text);
        }

        .page-shell {
            max-width: 1200px;
            margin: 24px auto;
            padding: 0 16px 30px;
        }

        .topbar {
            background: linear-gradient(135deg, #0b3c7d, #145fbf);
            padding: 22px 28px;
            border-radius: 16px;
            box-shadow: 0 10px 25px var(--shadow);
            margin-bottom: 20px;
        }

        .title-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .brand-wrap {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .brand-badge {
            background: rgba(255,255,255,0.16);
            border: 1px solid rgba(255,255,255,0.35);
            border-radius: 12px;
            padding: 9px 14px;
            color: #fff;
            font-weight: 700;
            letter-spacing: 0.08em;
        }

        .logout-btn {
            display: inline-block;
            text-decoration: none;
            background: rgba(255,255,255,0.14);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.35);
            border-radius: 10px;
            padding: 8px 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.22);
        }

        h1 {
            margin: 0;
            color: #fff;
            font-size: clamp(1.4rem, 2vw, 2.1rem);
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 8px 20px var(--shadow);
            padding: 20px;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 14px;
            margin-bottom: 22px;
        }

        .user-summary {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 10px;
            margin: 0 0 18px;
            color: #274c77;
            font-size: 0.95rem;
            font-weight: 700;
        }

        .approval-notification {
            margin: 0 0 18px;
            padding: 13px 16px;
            border-radius: 10px;
            border: 1px solid #f0c36d;
            background: #fff8e6;
            color: #7a4b00;
            font-weight: 700;
        }

        .approval-notification.approved {
            border-color: #8ad1a2;
            background: #ebf8ef;
            color: #176b35;
        }

        .approval-notification.rejected {
            border-color: #e9a3a3;
            background: #fff0f0;
            color: #a12b2b;
        }

        .field {
            display: flex;
            flex-direction: column;
            min-width: 160px;
            flex: 1 1 160px;
        }

        .field label {
            font-size: 0.82rem;
            color: var(--muted);
            margin-bottom: 6px;
            font-weight: 700;
        }

        select, input, button {
            font: inherit;
        }

        select, input {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
            color: var(--text);
        }

        .primary-btn, .secondary-btn, .danger-btn {
            border: none;
            border-radius: 10px;
            padding: 11px 18px;
            cursor: pointer;
            font-weight: 700;
            transition: 0.2s ease;
        }

        .primary-btn {
            background: var(--primary);
            color: #fff;
        }

        .primary-btn:hover { background: var(--primary-dark); }

        .secondary-btn {
            background: #e9ecef;
            color: var(--text);
        }

        .secondary-btn:hover { background: #dde3ea; }

        .danger-btn {
            background: var(--danger);
            color: #fff;
        }

        .danger-btn:hover { background: var(--danger-dark); }

        .class-image-card {
            margin: 0 0 22px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 8px 20px var(--shadow);
            padding: 18px;
            width: 100%;
        }

        .class-image-card img {
            display: block;
            width: 100%;
            height: auto;
            max-height: 520px;
            object-fit: contain;
            border-radius: 10px;
            background: #f8fafc;
            aspect-ratio: 16 / 9;
        }

        .class-warning {
            margin-bottom: 14px;
            padding: 10px 14px;
            border: 1px solid #f7c6c6;
            background: #fff4f4;
            color: #b42318;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .timetable-wrap {
            overflow-x: auto;
        }

        .timetable-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 860px;
            table-layout: fixed;
        }

        .timetable-table th,
        .timetable-table td {
            border: 1px solid var(--border);
            padding: 5px;
            text-align: center;
            min-height: 64px;
            vertical-align: middle;
            background: #fff;
        }

        .timetable-table thead th {
            background: #1f2d3d;
            color: #fff;
            font-weight: 700;
            padding: 8px 6px;
        }

        .time-col {
            width: 105px;
            background: #f5f7fb;
            color: var(--text);
            font-weight: 700;
        }

        .empty-cell { background: #fbfcfe; }

        .filled-cell {
            background: var(--filled);
            border: 1px solid var(--filled-border);
        }

        .subject-box {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 54px;
            background: linear-gradient(180deg, #edf8ff, #dff3ff);
            border: 1px solid var(--filled-border);
            border-radius: 7px;
            padding: 5px 22px 5px 5px;
            text-align: center;
            color: #134680;
            font-weight: 700;
        }

        .subject-box > div {
            font-size: 0.78rem !important;
            line-height: 1.15;
        }

        .subject-box > div > div:nth-child(2) {
            font-size: 0.68rem !important;
            margin-top: 2px !important;
        }

        .subject-box > div > div:nth-child(3) {
            font-size: 0.65rem !important;
            margin-top: 2px !important;
        }

        .delete-cell {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: none;
            background: rgba(220, 53, 69, 0.12);
            color: var(--danger);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            line-height: 1;
        }

        .delete-cell:hover { background: rgba(220, 53, 69, 0.2); }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 999;
            padding: 20px;
        }

        .modal-overlay.show { display: flex; }

        .modal {
            width: min(100%, 480px);
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.22);
            overflow: hidden;
        }

        .modal-header {
            background: #0b3c7d;
            color: #fff;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.2rem;
        }

        .close-modal {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .modal-body { padding: 20px; }

        .form-group { margin-bottom: 16px; }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 700;
            color: var(--text);
        }

        .time-choice-fields {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 8px;
            align-items: center;
        }

        .time-choice-fields span {
            color: var(--muted);
            font-weight: 700;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 18px;
        }

        /* Collapsible courses bar */
        .collapse-card { margin: 18px 0; }
        .collapse-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 12px;
            background: linear-gradient(180deg,#f8fbff,#eef7ff);
            border: 1px solid var(--border);
            cursor: pointer;
            font-weight: 700;
        }

        .collapse-title { color: var(--text); }
        .collapse-meta { color: var(--muted); font-weight: 600; font-size: 0.95rem; }

        .collapse-arrow {
            width: 18px;
            height: 18px;
            transition: transform 0.18s ease;
            display: inline-block;
        }

        .collapse-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.24s ease;
            border-top: 1px solid var(--border);
            background: #fff;
            border-radius: 0 0 12px 12px;
        }

        /* When open, limit height and allow scrolling inside the courses area */
        .collapse-content.open {
            max-height: 360px;
            overflow-y: auto;
            padding: 8px 0;
        }

        /* Custom lightweight scrollbar for modern browsers */
        .collapse-content.open::-webkit-scrollbar { width: 10px; }
        .collapse-content.open::-webkit-scrollbar-track { background: transparent; }
        .collapse-content.open::-webkit-scrollbar-thumb { background: rgba(16,24,40,0.06); border-radius: 10px; border: 2px solid rgba(255,255,255,0.7); }

        .credit-summary {
            margin-top: 14px;
            padding: 12px 16px;
            border-radius: 10px;
            background: #edf6ff;
            border: 1px solid #cbe2ff;
            color: #0b3c7d;
            font-weight: 800;
            text-align: right;
        }
        .credit-summary.warning {
            background: #fff3cd;
            border-color: #f4d35e;
            color: #7a4d00;
        }

        .course-list { padding: 12px 16px; display: grid; grid-template-columns: repeat(auto-fill,minmax(260px,1fr)); gap: 10px; }

        .course-item {
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: #fbfdff;
            font-weight: 700;
            color: #11406a;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .toolbar { flex-direction: column; align-items: stretch; }
            .field { width: 100%; }
            .modal-actions { flex-direction: column-reverse; }
            .modal-actions button { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <header class="topbar">
            <div class="title-wrap">
                <div class="brand-wrap">
                    <div class="brand-badge">JTMK</div>
                    <h1>JTMK Timetable Management System</h1>
                </div>
                <button type="button" class="logout-btn" id="logoutBtn">Logout</button>
            </div>
        </header>

        <main>
            <div class="user-summary">
                <span>Username: <?php echo htmlspecialchars($loggedUsername); ?></span>
                <span>Semester: <?php echo htmlspecialchars($loggedSemester); ?></span>
                <span>Class: <?php echo htmlspecialchars($loggedClass); ?></span>
            </div>

            <?php if ($approvalMessage !== ''): ?>
                <div class="approval-notification <?php echo htmlspecialchars($approvalStatus, ENT_QUOTES, 'UTF-8'); ?>" role="status">
                    <?php echo htmlspecialchars($approvalMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($classWarning)): ?>
                <div class="class-warning"><?php echo $classWarning; ?></div>
            <?php endif; ?>

            <?php if ($creditLimitExceeded): ?>
                <div class="class-warning" style="background:#fff3cd; border-color:#f4d35e; color:#7a4d00;">
                    Credit hours exceed 20.0. Please get permission from Ketua Jabatan (KJ) before this timetable can be approved.
                </div>
            <?php endif; ?>

            <?php if ($selectedImagePath): ?>
                <div class="class-image-card">
                    <img src="<?php echo htmlspecialchars($selectedImagePath); ?>" alt="Timetable for <?php echo htmlspecialchars($loggedSemester . ' ' . $loggedClass); ?>">
                </div>
            <?php endif; ?>

            <section class="card toolbar">
                <div class="field">
                    <label for="semesterSelect">Semester</label>
                    <select id="semesterSelect">
                        <option value="Semester 1" <?php echo ($loggedSemester === 'Semester 1') ? 'selected' : ''; ?>>Semester 1</option>
                        <option value="Semester 2" <?php echo ($loggedSemester === 'Semester 2') ? 'selected' : ''; ?>>Semester 2</option>
                        <option value="Semester 3" <?php echo ($loggedSemester === 'Semester 3') ? 'selected' : ''; ?>>Semester 3</option>
                        <option value="Semester 4" <?php echo ($loggedSemester === 'Semester 4') ? 'selected' : ''; ?>>Semester 4</option>
                        <option value="Semester 5" <?php echo ($loggedSemester === 'Semester 5') ? 'selected' : ''; ?>>Semester 5</option>
                    </select>
                </div>

                <div class="field">
                    <label for="classSelect">Class</label>
                    <select id="classSelect">
                        <option value="S1" <?php echo ($loggedClass === 'S1') ? 'selected' : ''; ?>>S1</option>
                        <option value="S2" <?php echo ($loggedClass === 'S2') ? 'selected' : ''; ?>>S2</option>
                        <option value="S3" <?php echo ($loggedClass === 'S3') ? 'selected' : ''; ?>>S3</option>
                        <option value="S4" <?php echo ($loggedClass === 'S4') ? 'selected' : ''; ?>>S4</option>
                    </select>
                </div>

                <button type="button" class="primary-btn" id="openModalBtn">Tambah</button>
                <button type="button" class="danger-btn" id="clearAllBtn">Padam</button>
                <button type="button" class="secondary-btn" id="saveTimetableBtn">Save timetable</button>
                <a href="?export_ics=1" class="secondary-btn" id="exportIcsBtn">Export to iCalendar</a>
            </section>

                <!-- Collapsible Courses Bar -->
                <section class="card collapse-card" aria-hidden="false">
                    <div class="collapse-header" id="coursesToggle" role="button" tabindex="0" aria-expanded="false">
                        <div>
                            <div class="collapse-title">Courses for <?php echo htmlspecialchars($loggedSemester); ?></div>
                            <div class="collapse-meta"><?php echo count($courseCatalog); ?> courses · <?php echo $courseCreditTotal; ?> credits</div>
                        </div>
                        <div>
                            <svg class="collapse-arrow" id="coursesArrow" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill="currentColor" d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z"/></svg>
                        </div>
                    </div>

                    <div class="collapse-content" id="coursesContent">
                        <div class="course-list" id="courseList">
                            <?php foreach ($courseCatalog as $course): ?>
                                <div class="course-item" data-name="<?php echo htmlspecialchars($course['code'] . ' ' . $course['name']); ?>">
                                    <div><?php echo htmlspecialchars($course['code']); ?> — <?php echo htmlspecialchars($course['name']); ?></div>
                                    <div style="font-weight:600; color:var(--muted); font-size:0.9rem; margin-top:6px;">Credits: <?php echo htmlspecialchars($course['credit']); ?> · Duration: <?php echo htmlspecialchars($course['default_duration_hours']); ?>h</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

            <section class="card timetable-wrap">
                <table class="timetable-table" aria-label="Weekly timetable">
                    <thead>
                        <tr>
                            <th class="time-col">Masa</th>
                            <?php foreach ($days as $day): ?>
                                <th><?php echo htmlspecialchars($day); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timeSlots as $slot): ?>
                            <tr>
                                <th class="time-col"><?php echo htmlspecialchars($slot); ?></th>
                                <?php foreach ($days as $day): ?>
                                    <?php $slotKey = $day . '|' . $slot; ?>
                                    <?php $entry = $_SESSION['timetable_data'][$slotKey] ?? null; ?>
                                    <td class="<?php echo $entry ? 'filled-cell' : 'empty-cell'; ?>">
                                        <?php if ($entry): ?>
                                            <?php
                                                $code = $entry['code'] ?? '';
                                                $bg = $code ? color_for_code($code) : '';
                                                $displayTitle = $entry['subject'] ?? ($entry['title'] ?? '');
                                                $lect = $entry['lecturer'] ?? '';
                                                $room = $entry['room'] ?? '';
                                            ?>
                                            <div class="subject-box" style="background: <?php echo htmlspecialchars($bg); ?>;">
                                                <button type="button" class="delete-cell" data-day="<?php echo htmlspecialchars($day); ?>" data-slot="<?php echo htmlspecialchars($slot); ?>" aria-label="Padam subjek">×</button>
                                                <div style="text-align:left; width:100%;">
                                                    <div style="font-weight:800; font-size:0.95rem; color:#073b53;"><?php echo htmlspecialchars($displayTitle); ?></div>
                                                    <?php if ($lect): ?><div style="font-weight:700; font-size:0.82rem; color:#114b67; margin-top:4px;"><?php echo htmlspecialchars($lect); ?></div><?php endif; ?>
                                                    <?php if ($room): ?><div style="font-weight:700; font-size:0.78rem; color:#0b3b4e; margin-top:4px;">Room: <?php echo htmlspecialchars($room); ?></div><?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="credit-summary <?php echo $creditLimitExceeded ? 'warning' : ''; ?>" id="creditHoursSummary">
                    Total Credit Hours: <?php echo number_format($studentCreditTotal, 1, '.', ''); ?>
                    <?php if ($creditLimitExceeded): ?>
                        <div style="margin-top:6px; font-size:0.9rem;">KJ permission required before approval.</div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <div class="modal-overlay" id="subjectModal" aria-hidden="true">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <div class="modal-header">
                <h3 id="modalTitle">Tambah Subjek</h3>
                <button type="button" class="close-modal" id="closeModalBtn" aria-label="Tutup">&times;</button>
            </div>

            <div class="modal-body">
                <form id="subjectForm">
                    <input type="hidden" name="action" value="add_subject">

                    <div class="form-group">
                        <label for="subjectName">Nama Subjek</label>
                        <input type="text" id="subjectName" name="subject_name" placeholder="Contoh: Matematik" required>
                    </div>

                    <div class="form-group">
                        <label for="daySelect">Hari</label>
                        <select id="daySelect" name="day" required>
                            <?php foreach ($days as $day): ?>
                                <option value="<?php echo htmlspecialchars($day); ?>"><?php echo htmlspecialchars($day); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group time-choice-group">
                        <label>Masa</label>
                        <div class="time-choice-fields">
                            <select id="startTime" aria-label="Start time" required>
                                <?php foreach (array_slice($timeBoundaries, 0, -1) as $time): ?>
                                    <option value="<?php echo htmlspecialchars($time); ?>"><?php echo htmlspecialchars($time); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span aria-hidden="true">to</span>
                            <select id="endTime" aria-label="End time" required>
                                <?php foreach (array_slice($timeBoundaries, 1) as $time): ?>
                                    <option value="<?php echo htmlspecialchars($time); ?>"><?php echo htmlspecialchars($time); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="hidden" id="modalTimeSlot" name="time_slot">
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="secondary-btn" id="cancelBtn">Batal</button>
                        <button type="submit" class="primary-btn">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        <?php
            $creditMap = [];
            foreach (getCourseCreditCatalog() as $code => $course) {
                $creditMap[$code] = (float)($course['credit'] ?? 0);
            }
        ?>
        const modal = document.getElementById('subjectModal');
        const openModalBtn = document.getElementById('openModalBtn');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const modalTimeSlot = document.getElementById('modalTimeSlot');
        const startTime = document.getElementById('startTime');
        const endTime = document.getElementById('endTime');
        const subjectForm = document.getElementById('subjectForm');
        const clearAllBtn = document.getElementById('clearAllBtn');
        const saveTimetableBtn = document.getElementById('saveTimetableBtn');
        const logoutBtn = document.getElementById('logoutBtn');
        const semesterSelect = document.getElementById('semesterSelect');
        const classSelect = document.getElementById('classSelect');
        const classImage = document.querySelector('.class-image-card img');
        const autoLoadUserTimetable = <?php echo $autoLoadUserTimetable ? 'true' : 'false'; ?>;
        const courseCreditMap = <?php echo json_encode($creditMap, JSON_UNESCAPED_UNICODE); ?>;

        function updateCreditSummary() {
            const summaryEl = document.getElementById('creditHoursSummary');
            if (!summaryEl) {
                return;
            }

            const seen = new Set();
            let total = 0;

            document.querySelectorAll('.subject-box').forEach(function (box) {
                const text = (box.textContent || '').replace(/\s+/g, ' ').trim();
                const match = text.match(/\b[A-Z]{2,}\d{3,5}\b/);
                const code = match ? match[0] : null;

                if (!code || !courseCreditMap[code] || seen.has(code)) {
                    return;
                }

                seen.add(code);
                total += Number(courseCreditMap[code]);
            });

            const overLimit = total > 20.0;
            summaryEl.classList.toggle('warning', overLimit);
            let noteHtml = '';
            if (overLimit) {
                noteHtml = '<div style="margin-top:6px; font-size:0.9rem;">KJ permission required before approval.</div>';
            }
            summaryEl.innerHTML = 'Total Credit Hours: ' + total.toFixed(1) + noteHtml;
        }

        if (autoLoadUserTimetable) {
            const formData = new FormData();
            formData.append('action', 'load_user_timetable');
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(function (response) { return response.json(); })
                .then(function (result) {
                    if (result.success) {
                        window.location.reload();
                    }
                });
        }

        const availableClassesBySemester = {
            'Semester 1': ['S1', 'S2', 'S3'],
            'Semester 2': ['S1', 'S3'],
            'Semester 3': ['S1', 'S2', 'S3', 'S4'],
            'Semester 4': ['S1', 'S3'],
            'Semester 5': ['S1', 'S2', 'S3', 'S4']
        };

        const legacyClassMap = {
            S1: 'Class A',
            S2: 'Class B',
            S3: 'Class C',
            S4: 'Class D'
        };

        function buildImageCandidates(semester, className) {
            const mappedClass = legacyClassMap[className] || className;
            const classVariants = Array.from(new Set([className, mappedClass]));
            const candidates = [];

            classVariants.forEach(function (variant) {
                candidates.push('image/' + semester + ' ' + variant + '.png');
                candidates.push('image/' + semester + ' ' + variant + '.jpg');
                candidates.push('image/' + semester + ' ' + variant + '.jpeg');
            });

            return candidates;
        }

        function syncClassOptionsForSemester(selectedSemester) {
            const allowedClasses = availableClassesBySemester[selectedSemester] || ['S1', 'S2', 'S3', 'S4'];
            const currentClass = classSelect.value;

            classSelect.innerHTML = '';
            allowedClasses.forEach(function (className) {
                const option = document.createElement('option');
                option.value = className;
                option.textContent = className;
                if (className === currentClass || (!allowedClasses.includes(currentClass) && className === allowedClasses[0])) {
                    option.selected = true;
                }
                classSelect.appendChild(option);
            });
        }

        function updateImagePreview() {
            const selectedSemester = semesterSelect.value;
            const selectedClass = classSelect.value;
            const allowedClasses = availableClassesBySemester[selectedSemester] || [];

            if (!allowedClasses.includes(selectedClass)) {
                classImage.style.display = 'none';
                return;
            }

            const candidates = buildImageCandidates(selectedSemester, selectedClass);
            let imageFound = false;

            candidates.forEach(function (candidate) {
                if (imageFound) {
                    return;
                }

                const tempImage = new Image();
                tempImage.onload = function () {
                    if (!imageFound) {
                        imageFound = true;
                        classImage.src = candidate;
                        classImage.style.display = 'block';
                    }
                };
                tempImage.onerror = function () {};
                tempImage.src = candidate;
            });

            if (!imageFound) {
                classImage.style.display = 'none';
            }
        }

        function openModal() {
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeModal() {
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
            subjectForm.reset();
        }

        function updateEndTimes() {
            let firstAvailableEnd = '';
            Array.from(endTime.options).forEach(function (option) {
                const isBeforeStart = option.value <= startTime.value;
                option.disabled = isBeforeStart;
                if (!isBeforeStart && firstAvailableEnd === '') {
                    firstAvailableEnd = option.value;
                }
            });

            if (endTime.value <= startTime.value) {
                endTime.value = firstAvailableEnd;
            }
        }

        semesterSelect.addEventListener('change', function () {
            syncClassOptionsForSemester(semesterSelect.value);
            updateImagePreview();
        });

        classSelect.addEventListener('change', function () {
            updateImagePreview();
        });

        startTime.addEventListener('change', updateEndTimes);
        syncClassOptionsForSemester(semesterSelect.value);
        updateEndTimes();

        openModalBtn.addEventListener('click', openModal);
        closeModalBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);

        saveTimetableBtn.addEventListener('click', async function () {
            const formData = new FormData();
            formData.append('action', 'save_timetable');

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            alert(result.message || 'Timetable saved.');
        });

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });

        subjectForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            if (endTime.value <= startTime.value) {
                alert('Please choose an end time after the start time.');
                return;
            }

            modalTimeSlot.value = startTime.value + '-' + endTime.value;
            const formData = new FormData(subjectForm);
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (!result.success) {
                alert(result.message || 'Ralat berlaku.');
                return;
            }

            closeModal();
            window.location.reload();
        });

        document.querySelectorAll('.delete-cell').forEach(function (button) {
            button.addEventListener('click', async function () {
                const formData = new FormData();
                formData.append('action', 'delete_subject');
                formData.append('day', button.dataset.day);
                formData.append('time_slot', button.dataset.slot);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                if (result.success) {
                    window.location.reload();
                } else {
                    alert(result.message || 'Ralat berlaku.');
                }
            });
        });

        clearAllBtn.addEventListener('click', async function () {
            const confirmed = window.confirm('Padam semua data jadual?');
            if (!confirmed) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'clear_all');

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                window.location.reload();
            } else {
                alert(result.message || 'Ralat berlaku.');
            }
        });

        logoutBtn.addEventListener('click', function () {
            const confirmed = window.confirm('Adakah anda pasti mahu log keluar?');
            if (confirmed) {
                window.location.href = 'index.php?logout=1';
            }
        });

        updateImagePreview();
        updateCreditSummary();

        // Collapsible courses toggle
        const coursesToggle = document.getElementById('coursesToggle');
        const coursesContent = document.getElementById('coursesContent');
        const coursesArrow = document.getElementById('coursesArrow');

        function setCoursesOpen(open) {
            if (open) {
                coursesContent.classList.add('open');
                coursesToggle.setAttribute('aria-expanded', 'true');
                coursesArrow.style.transform = 'rotate(180deg)';
            } else {
                coursesContent.classList.remove('open');
                coursesToggle.setAttribute('aria-expanded', 'false');
                coursesArrow.style.transform = 'rotate(0deg)';
            }
        }

        coursesToggle.addEventListener('click', function () {
            setCoursesOpen(!coursesContent.classList.contains('open'));
        });

        coursesToggle.addEventListener('keypress', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                setCoursesOpen(!coursesContent.classList.contains('open'));
            }
        });

        // Allow students to click a course to add it to the timetable.
        document.querySelectorAll('.course-item').forEach(function (el) {
            el.addEventListener('click', function () {
                const name = el.dataset.name || el.textContent.trim();
                const subjectInput = document.getElementById('subjectName');
                if (subjectInput) {
                    subjectInput.value = name;
                }
                // Open the modal for final day/time confirmation
                openModal();
                // Close courses panel optionally
                setCoursesOpen(false);
            });
        });
    </script>
</body>
</html>
