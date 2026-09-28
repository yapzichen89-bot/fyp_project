<?php
// Show PHP errors so it is easier to understand problems while testing.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start a session so we can remember the logged-in user.
session_start();

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/timetable_data.php';

// Load the database connection file.
$dbFile = __DIR__ . '/../db.php';
if (!file_exists($dbFile)) {
    $dbFile = __DIR__ . '/db.php';
}
if (!file_exists($dbFile)) {
    die('Database file not found. Please make sure db.php exists in the project folder.');
}
require_once $dbFile;

// Message shown when login fails.
$errorMessage = '';

// Check whether the user submitted the login form.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enteredUsername = trim($_POST['username'] ?? '');
    $enteredPassword = $_POST['password'] ?? '';

    // Make sure both boxes are filled.
    if (!empty($enteredUsername) && !empty($enteredPassword)) {
        // Search for the user in the database.
        $statement = $db->prepare("SELECT * FROM users WHERE username = :username AND role = 'student'");
        $statement->execute([':username' => $enteredUsername]);
        $userFromDatabase = $statement->fetch(PDO::FETCH_ASSOC);

        // Check whether the user exists and the password is correct.
        if ($userFromDatabase && password_verify($enteredPassword, $userFromDatabase['password'])) {
            session_regenerate_id(true);

            $dbSemester = !empty($userFromDatabase['semester']) ? trim((string)$userFromDatabase['semester']) : (detectStudentClassFromMatrixId($enteredUsername)['semester'] ?? 'Semester 1');
            $dbClass = !empty($userFromDatabase['class_name']) ? trim((string)$userFromDatabase['class_name']) : (detectStudentClassFromMatrixId($enteredUsername)['class_name'] ?? 'S1');

            $_SESSION['user_id'] = $userFromDatabase['id'];
            $_SESSION['username'] = $userFromDatabase['username'];
            $_SESSION['student_semester'] = in_array($dbSemester, ['Semester 1', 'Semester 2', 'Semester 3', 'Semester 4', 'Semester 5'], true) ? $dbSemester : 'Semester 1';
            $_SESSION['student_class_name'] = in_array($dbClass, ['S1', 'S2', 'S3', 'S4'], true) ? $dbClass : 'S1';

            unset(
                $_SESSION['timetable_data'],
                $_SESSION['timetable_sessions'],
                $_SESSION['timetable_profile'],
                $_SESSION['timetable_cleared'],
                $_SESSION['timetable_user_loaded'],
                $_SESSION['timetable_empty_reason']
            );

            header("Location: mainPage.php");
            exit;
        } else {
            $errorMessage = "Invalid Username or Password!";
        }
    } else {
        $errorMessage = "Please enter both username and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PKS Timetable Management System - Login</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <!-- TOP BAR / HEADER -->
    <header class="header">
        <div class="logo-section">
           <img src="image/logo politeknik kuching sarawak.png" alt="Logo" class="logo">
            <h1>TIMETABLE SCHEDULE SYSTEM</h1>
        </div>
        
        <!-- TOP RIGHT DECORATION -->
        <div class="user-section" aria-label="Decorative header icons">
            <span class="header-emoji" aria-hidden="true">📚</span>
            <span class="header-emoji" aria-hidden="true">🗓️</span>
            <span class="header-emoji" aria-hidden="true">🎓</span>
        </div>
    </header>

    <!-- BANNER IMAGE -->
    <div class="banner">
        <img src="https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=1200&q=80" alt="Tech Banner">
    </div>

    <!-- MAIN LOGIN CONTAINER -->
    <main class="login-container">
        <h2>System Login</h2>

        <?php if (!empty($errorMessage)): ?>
            <p style="color: #e74c3c; text-align: center; margin-bottom: 15px; font-size: 13px; font-weight: bold;">
                <?php echo htmlspecialchars($errorMessage); ?>
            </p>
        <?php endif; ?>

        <form id="loginForm" action="index.php" method="POST" class="login-form">
            <input type="hidden" name="form_action" value="login">
            <div class="input-group">
                <label for="account_type">Login As</label>
                <select id="account_type" name="account_type" required>
                    <option value="student">Student</option>
                    <option value="pa">PA</option>
                </select>
            </div>

            <div class="input-group">
                <label for="username">Matric No / Username</label>
                <input type="text" id="username" name="username" placeholder="e.g. 05DIT23F1001" required>
            </div>

            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter password" required>
            </div>

            <a href="register.php" id="studentRegisterLink" class="btn-register">First Time Login</a>

            <button type="submit" class="btn-login">Login</button>

            <div class="form-footer">
                <a href="#">Forgot Password?</a>
            </div>
        </form>
    </main>

    <script>
        const accountType = document.getElementById('account_type');
        const studentRegisterLink = document.getElementById('studentRegisterLink');
        const loginForm = document.getElementById('loginForm');

        function updateAccountLinks() {
            const isPa = accountType.value === 'pa';
            studentRegisterLink.href = isPa ? 'PA.php?action=register' : 'register.php';
            studentRegisterLink.textContent = isPa ? 'PA First Time Login' : 'First Time Login';
            loginForm.action = isPa ? 'PA.php' : 'index.php';
        }

        accountType.addEventListener('change', updateAccountLinks);
        updateAccountLinks();
    </script>

</body>
</html>