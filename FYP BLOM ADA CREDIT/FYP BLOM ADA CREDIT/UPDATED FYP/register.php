<?php
session_start();

// Connect to the database file.
$dbFile = __DIR__ . '/../db.php';
if (!file_exists($dbFile)) {
    $dbFile = __DIR__ . '/db.php';
}
if (!file_exists($dbFile)) {
    die('Database file not found. Please make sure db.php exists in the project folder.');
}
require_once $dbFile;

// This message is shown to the user after registration.
$message = '';
$statusClass = '';

$availableClassesBySemester = [
    'Semester 1' => ['S1', 'S2', 'S3'],
    'Semester 2' => ['S1', 'S3'],
    'Semester 3' => ['S1', 'S2', 'S3', 'S4'],
    'Semester 4' => ['S1', 'S3'],
    'Semester 5' => ['S1', 'S2', 'S3', 'S4'],
];

// Check whether the registration form was submitted.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $semester = trim($_POST['semester'] ?? '');
    $className = trim($_POST['class_name'] ?? '');

    // Only continue if required fields are not empty.
    if (!empty($username) && !empty($password) && !empty($semester) && !empty($className)) {
        $allowedClasses = $availableClassesBySemester[$semester] ?? [];

        if (!in_array($className, $allowedClasses, true)) {
            $message = "Class " . htmlspecialchars($className, ENT_QUOTES, 'UTF-8') . " is not available for this semester.";
            $statusClass = "status-error";
        } else {
            // Encrypt the password before saving it.
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            try {
                // Save the new user into the users table.
                $statement = $db->prepare("INSERT INTO users (username, password, role, semester, class_name, approval_status) VALUES (:username, :password, 'student', :semester, :class_name, 'pending')");
                $statement->execute([
                    ':username' => $username,
                    ':password' => $hashedPassword,
                    ':semester' => $semester,
                    ':class_name' => $className
                ]);

                $message = "Account created successfully! <a href='index.php'>Click here to login</a>";
                $statusClass = "status-success";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $message = "Username / Matric No already exists!";
                } else {
                    $message = "Registration error: " . $e->getMessage();
                }
                $statusClass = "status-error";
            }
        }
    } else {
        $message = "Please fill in all fields.";
        $statusClass = "status-error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PKS Timetable System - First Time Registration</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <header class="header">
        <div class="logo-section">
            <img src="image/logo politeknik kuching sarawak.png" alt="Logo" class="logo">
            <h1>TIMETABLE SCHEDULE SYSTEM</h1>
        </div>
        <div class="user-section">
            <i class="fa-solid fa-envelope icon"></i>
            <i class="fa-solid fa-bell icon"></i>
            <i class="fa-solid fa-comment-dots icon"></i>
        </div>
    </header>

    <div class="banner">
        <img src="https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=1200&q=80" alt="Tech Banner">
    </div>

    <main class="login-container">
        <h2>First Time Registration</h2>

        <?php if (!empty($message)): ?>
            <p style="text-align: center; margin-bottom: 15px; font-size: 13px; font-weight: bold;" class="<?php echo $statusClass; ?>">
                <?php echo $message; ?>
            </p>
        <?php endif; ?>

        <form action="register.php" method="POST" class="login-form">
            <div class="input-group">
                <label for="username">Matric No / Username</label>
                <input type="text" id="username" name="username" placeholder="e.g. 05DIT23F1001" required>
            </div>

            <div class="input-group">
                <label for="semester">Semester</label>
                <select id="semester" name="semester" required>
                    <option value="">-- Pilih Semester --</option>
                    <option value="Semester 1">Semester 1</option>
                    <option value="Semester 2">Semester 2</option>
                    <option value="Semester 3">Semester 3</option>
                    <option value="Semester 4">Semester 4</option>
                    <option value="Semester 5">Semester 5</option>
                </select>
            </div>

            <div class="input-group">
                <label for="class_name">Class</label>
                <select id="class_name" name="class_name" required>
                    <option value="">-- Pilih Class --</option>
                    <option value="S1">S1</option>
                    <option value="S2">S2</option>
                    <option value="S3">S3</option>
                    <option value="S4">S4</option>
                </select>
                <div id="classWarning" class="class-warning" style="display:none; margin-top:8px;"></div>
            </div>

            <div class="input-group">
                <label for="password">Create Password</label>
                <input type="password" id="password" name="password" placeholder="Create a password" required>
            </div>

            <button type="submit" class="btn-login">Create Account</button>

            <div class="form-footer">
                <a href="index.php">Already have an account? Login here</a>
            </div>
        </form>

        <div id="semesterPreviewCard" class="semester-preview-card" hidden>
            <h3 id="semesterPreviewTitle">Semester / Class</h3>
            <div id="timetableImageContainer" class="empty-preview-box">Kosong</div>
        </div>
    </main>

    <script>
        const semesterSelect = document.getElementById('semester');
        const classSelect = document.getElementById('class_name');
        const previewCard = document.getElementById('semesterPreviewCard');
        const previewTitle = document.getElementById('semesterPreviewTitle');
        const timetableImageContainer = document.getElementById('timetableImageContainer');
        const classWarning = document.getElementById('classWarning');

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
            const semesterLabel = String(semester).trim();
            const classLabel = String(className).trim();
            const mappedClass = legacyClassMap[className] || className;
            const classVariants = Array.from(new Set([classLabel, mappedClass]));
            const candidates = [];

            classVariants.forEach(function (variant) {
                candidates.push('image/' + semesterLabel + ' ' + variant + '.png');
                candidates.push('image/' + semesterLabel + ' ' + variant + '.jpg');
                candidates.push('image/' + semesterLabel + ' ' + variant + '.jpeg');
                candidates.push('image/' + semesterLabel + variant + '.png');
                candidates.push('image/' + semesterLabel + variant + '.jpg');
                candidates.push('image/' + encodeURI(semesterLabel + ' ' + variant + '.png'));
                candidates.push('image/' + encodeURI(semesterLabel + ' ' + variant + '.jpg'));
            });

            return candidates;
        }

        function updateTimetableImage(semester, className) {
            const candidates = buildImageCandidates(semester, className);
            const fallbackMarkup = '<div class="empty-preview-box">Kosong</div>';

            if (!semester || !className) {
                timetableImageContainer.innerHTML = fallbackMarkup;
                return;
            }

            let loaded = false;

            candidates.forEach(function (imagePath) {
                if (loaded) {
                    return;
                }

                const img = document.createElement('img');
                img.src = imagePath;
                img.alt = semester + ' ' + className + ' timetable';
                img.className = 'preview-image';
                img.onload = function () {
                    if (loaded) {
                        return;
                    }
                    loaded = true;
                    timetableImageContainer.innerHTML = '';
                    timetableImageContainer.appendChild(img);
                };
                img.onerror = function () {
                    if (!loaded) {
                        img.remove();
                    }
                };
            });

            if (!loaded) {
                timetableImageContainer.innerHTML = fallbackMarkup;
            }
        }

        function updateClassAvailability() {
            const selectedSemester = semesterSelect.value;
            const allowedClasses = availableClassesBySemester[selectedSemester] || [];

            Array.from(classSelect.options).forEach(function (option) {
                if (option.value === '') {
                    option.disabled = false;
                    return;
                }

                option.disabled = !allowedClasses.includes(option.value);
            });

            if (!selectedSemester) {
                classSelect.value = '';
                classWarning.style.display = 'none';
                classWarning.textContent = '';
                return;
            }

            if (classSelect.value && !allowedClasses.includes(classSelect.value)) {
                classWarning.textContent = 'Class ' + classSelect.value + ' is not available for this semester.';
                classWarning.style.display = 'block';
                classSelect.value = '';
            } else {
                classWarning.style.display = 'none';
                classWarning.textContent = '';
            }
        }

        function updatePreview() {
            const selectedSemester = semesterSelect.value;
            const selectedClass = classSelect.value;

            if (!selectedSemester || !selectedClass) {
                previewCard.hidden = true;
                return;
            }

            previewCard.hidden = false;
            previewTitle.textContent = selectedSemester + ' / ' + selectedClass;
            updateTimetableImage(selectedSemester, selectedClass);
        }

        semesterSelect.addEventListener('change', function () {
            updateClassAvailability();
            updatePreview();
        });

        classSelect.addEventListener('change', function () {
            updateClassAvailability();
            updatePreview();
        });

        updateClassAvailability();
    </script>

</body>
</html>
