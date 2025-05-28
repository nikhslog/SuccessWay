<?php
require_once 'config.php';

$login_error = '';
$register_error = '';
$register_success = false;

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["login"])) {
    $email = sanitize_input($_POST["email"]);
    $password = $_POST["password"];
    
    // Prepare SQL statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT student_id, full_name, password FROM students WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify the password
        if (password_verify($password, $user['password'])) {
            // Password is correct, create session
            $_SESSION['student_id'] = $user['student_id'];
            $_SESSION['student_name'] = $user['full_name'];
            $_SESSION['student_logged_in'] = true;
            
            // Redirect to student dashboard
            header("Location: student_dashboard.php");
            exit;
        } else {
            $login_error = "Invalid password";
        }
    } else {
        $login_error = "No account found with that email";
    }
    
    $stmt->close();
}

// Handle registration form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["register"])) {
    $fullname = sanitize_input($_POST["fullname"]);
    $email = sanitize_input($_POST["email"]);
    $phone = sanitize_input($_POST["phone"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        $register_error = "Passwords do not match";
    } else {
        // Check if email already exists
        $check_stmt = $conn->prepare("SELECT email FROM students WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $register_error = "Email already registered";
        } else {
            // Hash password for security
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Prepare and execute the SQL statement
            $stmt = $conn->prepare("INSERT INTO students (full_name, email, phone, password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $fullname, $email, $phone, $hashed_password);
            
            if ($stmt->execute()) {
                $register_success = true;
            } else {
                $register_error = "Registration failed: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

$conn->close();

// Set container class based on form
$container_class = '';
$current_file = basename($_SERVER['PHP_SELF']);

if ($current_file === 'students_register_en.php') {
    $container_class = 'active';
} else {
    $container_class = '';
}

if ($register_success) {
    $container_class = '';  // Show login form after successful registration
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuccessWay - Login/Signup</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: "Poppins", sans-serif;
    text-decoration: none;
    list-style: none;
}

body {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background: #e8f4f4; /* Light mint background */
    padding: 10px;
}

.container {
    position: relative;
    width: 100%;
    max-width: 850px;
    height: 550px;
    background: #fff;
    margin: 20px auto;
    border-radius: 20px;
    box-shadow: 0 0 30px rgba(0, 0, 0, .1);
    overflow: hidden;
}

.container h1 {
    font-size: clamp(24px, 5vw, 32px);
    margin: -10px 0;
    color: #333;
}

.container p {
    font-size: clamp(12px, 3vw, 14px);
    margin: 15px 0;
    color: #666;
}

.logo {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
}

.logo a {
    text-decoration: none;
}

.logo-text {
    font-size: clamp(20px, 4vw, 28px);
    font-weight: 600;
}

.logo-text span:first-child {
    color: #5cbfb9; /* Teal */
}

.logo-text span:last-child {
    color: #333; /* Dark gray */
}

form { 
    width: 100%; 
}

.form-box {
    position: absolute;
    right: 0;
    width: 50%;
    height: 100%;
    background: #fff;
    display: flex;
    align-items: center;
    color: #333;
    text-align: center;
    padding: clamp(20px, 5vw, 40px);
    z-index: 1;
    transition: .6s ease-in-out 1.2s, visibility 0s 1s;
    overflow-y: auto;
}

.container.active .form-box { 
    right: 50%; 
}

.form-box.register { 
    visibility: hidden; 
}

.container.active .form-box.register { 
    visibility: visible; 
}

.input-box {
    position: relative;
    margin: 25px 0;
}

.input-box input {
    width: 100%;
    padding: 13px 50px 13px 20px;
    background: #f5f5f5;
    border-radius: 8px;
    border: 1px solid #eee;
    outline: none;
    font-size: clamp(14px, 3vw, 16px);
    color: #333;
    font-weight: 500;
    transition: border-color 0.3s;
}

.input-box input:focus {
    border-color: #5cbfb9;
}

.input-box input::placeholder {
    color: #999;
    font-weight: 400;
}

.input-box i {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 20px;
    color: #5cbfb9;
}

.input-box i.show-password {
    right: 50px;
    cursor: pointer;
    color: #888;
}

.input-box i.show-password:hover {
    color: #5cbfb9;
}

.forgot-link { 
    margin: -15px 0 15px; 
    text-align: right;
}

.forgot-link a {
    font-size: clamp(12px, 3vw, 14px);
    color: #5cbfb9;
    transition: color 0.3s;
}

.forgot-link a:hover {
    color: #45a3a0;
}

.btn {
    width: 100%;
    height: 48px;
    background: #5cbfb9;
    border-radius: 30px;
    box-shadow: 0 0 10px rgba(92, 191, 185, .2);
    border: none;
    cursor: pointer;
    font-size: clamp(14px, 3vw, 16px);
    color: #fff;
    font-weight: 600;
    transition: background-color 0.3s;
}

.btn:hover {
    background: #45a3a0;
}

.social-icons {
    display: flex;
    justify-content: center;
    margin-top: 20px;
}

.social-icons a {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    width: 45px;
    height: 45px;
    border: 1px solid #eee;
    border-radius: 50%;
    font-size: 22px;
    color: #666;
    margin: 0 8px;
    transition: all 0.3s;
}

.social-icons a:hover {
    color: #5cbfb9;
    border-color: #5cbfb9;
}

.toggle-box {
    position: absolute;
    width: 100%;
    height: 100%;
}

.toggle-box::before {
    content: '';
    position: absolute;
    left: -250%;
    width: 300%;
    height: 100%;
    background: #5cbfb9; /* Teal background */
    border-radius: 150px;
    z-index: 2;
    transition: 1.8s ease-in-out;
}

.container.active .toggle-box::before { 
    left: 50%; 
}

.toggle-panel {
    position: absolute;
    width: 50%;
    height: 100%;
    color: #fff;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    z-index: 2;
    transition: .6s ease-in-out;
    padding: 0 clamp(20px, 5vw, 40px);
}

.toggle-panel h1 {
    color: #fff;
    font-size: clamp(24px, 5vw, 32px);
    margin-bottom: 15px;
}

.toggle-panel.toggle-left { 
    left: 0;
    transition-delay: 1.2s; 
}

.container.active .toggle-panel.toggle-left {
    left: -50%;
    transition-delay: .6s;
}

.toggle-panel.toggle-right { 
    right: -50%;
    transition-delay: .6s;
}

.container.active .toggle-panel.toggle-right {
    right: 0;
    transition-delay: 1.2s;
}

.toggle-panel p { 
    margin-bottom: 20px;
    text-align: center;
}

.toggle-panel .btn {
    width: clamp(140px, 30vw, 160px);
    height: 46px;
    background: transparent;
    border: 2px solid #fff;
    box-shadow: none;
}

.toggle-panel .btn:hover {
    background: rgba(255, 255, 255, 0.1);
}

.error-message {
    color: #e74c3c;
    font-size: clamp(12px, 3vw, 14px);
    text-align: center;
    margin-bottom: 10px;
}

.success-message {
    color: #2ecc71;
    font-size: clamp(12px, 3vw, 14px);
    text-align: center;
    margin-bottom: 10px;
}

.back-btn {
    position: absolute;
    top: 20px;
    left: 20px;
    padding: 8px 15px;
    background: #5cbfb9;
    color: white;
    border: none;
    border-radius: 20px;
    font-size: clamp(12px, 3vw, 14px);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    z-index: 10;
    transition: background-color 0.3s, left 1.2s ease-in-out, right 1.2s ease-in-out, top 1.2s ease-in-out;
}

.container.active .back-btn {
    left: auto;
    right: 20px;
    top: 20px;
}

.back-btn:hover {
    background: #45a3a0;
}

/* Responsive Adjustments */
@media screen and (max-width: 900px) {
    .container {
        max-width: 95%;
        height: auto;
        min-height: 550px;
    }
}

@media screen and (max-width: 768px) {
    .container {
        height: auto;
        min-height: 100vh;
        margin: 0;
        border-radius: 0;
    }

    .form-box {
        padding: 30px 20px;
    }

    .toggle-panel {
        padding: 0 20px;
    }
}

@media screen and (max-width: 650px) {
    .container { 
        height: 100vh;
        margin: 0;
        border-radius: 0;
    }

    .form-box {
        bottom: 0;
        width: 100%;
        height: 70%;
        padding: 20px 15px;
    }

    .container.active .form-box {
        right: 0;
        bottom: 30%;
    }

    .toggle-box::before {
        left: 0;
        top: -270%;
        width: 100%;
        height: 300%;
        border-radius: 20vw;
    }

    .container.active .toggle-box::before {
        left: 0;
        top: 70%;
    }

    .container.active .toggle-panel.toggle-left {
        left: 0;
        top: -30%;
    }

    .toggle-panel { 
        width: 100%;
        height: 30%;
        padding: 0 15px;
    }

    .toggle-panel.toggle-left { 
        top: 0; 
    }

    .toggle-panel.toggle-right {
        right: 0;
        bottom: -30%;
    }

    .container.active .toggle-panel.toggle-right { 
        bottom: 0; 
    }

    .back-btn {
        top: 10px;
        left: 10px;
        padding: 6px 12px;
        font-size: 12px;
    }

    .container.active .back-btn {
        right: 10px;
        left: auto;
        top: 10px;
    }

    .input-box {
        margin: 15px 0;
    }

    .input-box input {
        padding: 10px 45px 10px 15px;
    }
}

@media screen and (max-width: 480px) {
    .container h1 {
        font-size: 22px;
    }

    .container p {
        font-size: 12px;
        margin: 10px 0;
    }

    .form-box {
        padding: 15px 10px;
    }

    .toggle-panel h1 {
        font-size: 20px;
    }

    .toggle-panel p {
        font-size: 12px;
    }

    .toggle-panel .btn {
        width: 130px;
        height: 40px;
        font-size: 14px;
    }

    .input-box {
        margin: 12px 0;
    }

    .input-box input {
        padding: 8px 40px 8px 12px;
        font-size: 14px;
    }

    .input-box i {
        font-size: 18px;
        right: 15px;
    }

    .input-box i.show-password {
        right: 40px;
    }

    .btn {
        height: 40px;
        font-size: 14px;
    }
}

@media screen and (max-height: 600px) {
    .container {
        height: auto;
        min-height: 100vh;
    }

    .form-box {
        padding-top: 50px;
        padding-bottom: 50px;
    }
}

/* Preloader styles */
.sw-preloader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: #ffffff;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    pointer-events: none; 
}

.sw-preloader.hidden {
    display: none;
}

.sw-plane-container {
    position: relative;
    width: 200px;
    height: 60px;
    margin-bottom: 20px;
}

.sw-plane {
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    font-size: 2rem;
    animation: sw-fly 2s infinite ease-in-out;
}

.sw-plane-line {
    position: absolute;
    top: 50%;
    left: 0;
    width: 0;
    height: 2px;
    background-color: #40b3a2;
    transform: translateY(-50%);
    animation: sw-drawLine 2s infinite ease-in-out;
}

.sw-loading-text {
    font-size: 1.5rem;
    font-weight: 600;
    color: #40b3a2;
    letter-spacing: 2px;
    opacity: 0;
    animation: sw-fadeText 2s infinite ease-in-out;
}

@keyframes sw-fly {
    0% {
        left: 0;
    }
    50% {
        left: 180px;
    }
    100% {
        left: 0;
    }
}

@keyframes sw-drawLine {
    0% {
        width: 0;
    }
    50% {
        width: 100%;
    }
    100% {
        width: 0;
    }
}

@keyframes sw-fadeText {
    0% {
        opacity: 0.2;
    }
    50% {
        opacity: 1;
    }
    100% {
        opacity: 0.2;
    }
}

/* Translation Toggle Button */
.translation-toggle {
    position: fixed;
    bottom: 20px;
    left: 20px;
    background-color: white;
    border-radius: 50px;
    padding: 8px 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    display: flex;
    align-items: center;
    cursor: pointer;
    z-index: 1000;
    transition: all 0.3s ease;
    border: 1px solid rgba(92, 184, 178, 0.2);
}

.translation-toggle:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25);
}

.translation-toggle .icon {
    margin-right: 8px;
    color: #5cbfb9;
}

.translation-toggle .label {
    font-weight: 500;
    color: #333;
    font-size: 0.95rem;
}

.language-options {
    position: fixed;
    bottom: 20px;
    left: 20px;
    background-color: white;
    border-radius: 50px;
    padding: 6px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    display: none;
    z-index: 1000;
    border: 1px solid rgba(92, 184, 178, 0.2);
}

.language-options.active {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
}

.language-option {
    padding: 6px 12px;
    border-radius: 50px;
    margin: 4px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s;
    font-size: 0.9rem;
}

.language-option:hover,
.language-option.active {
    background-color: #5cbfb9;
    color: white;
}

/* Google Translate notification bar remover */
.goog-te-banner-frame {
    display: none !important;
}

.skiptranslate {
    display: none !important;
}

body {
    top: 0 !important;
}

/* Mobile responsiveness for translation toggle */
@media (max-width: 768px) {
    .translation-toggle,
    .language-options {
        bottom: 15px;
        left: 15px;
    }
    
    .translation-toggle {
        padding: 6px 10px;
    }
    
    .translation-toggle .label {
        font-size: 0.8rem;
    }
    
    .language-option {
        padding: 5px 10px;
        font-size: 0.8rem;
    }
}

@media (max-width: 480px) {
    .translation-toggle,
    .language-options {
        bottom: 10px;
        left: 10px;
    }
    
    .translation-toggle {
        padding: 5px 8px;
    }
    
    .translation-toggle .label {
        font-size: 0.75rem;
    }
    
    .language-option {
        padding: 4px 8px;
        font-size: 0.75rem;
        margin: 2px;
    }
    
    .sw-plane-container {
        width: 160px;
    }
    
    .sw-loading-text {
        font-size: 1.2rem;
    }
}
    </style>
</head>
<body>
<div class="sw-preloader" id="sw-preloader">
    <div class="sw-plane-container">
        <div class="sw-plane">✈️</div>
        <div class="sw-plane-line"></div>
    </div>
    <div class="sw-loading-text">SuccessWay</div>
</div>
    <div class="container <?php echo $container_class; ?>">
        <a href="index.html" class="back-btn">
            <i class='bx bx-arrow-back'></i> Retour au site Web
        </a>
        <div class="form-box login">
            <form action="student_login.php" method="post">
                <div class="logo">
                    <a href="index.html">
                    </a>
                </div>
                <h1>Login</h1>
                <p>Welcome back to your educational journey</p>
                
                <?php if ($login_error): ?>
                    <div class="error-message"><?php echo $login_error; ?></div>
                <?php endif; ?>
                
                <?php if ($register_success): ?>
                    <div class="success-message">Registration successful! You can now login.</div>
                <?php endif; ?>
                
                <div class="input-box">
                    <input type="email" name="email" placeholder="Email" required>
                    <i class='bx bxs-envelope'></i>
                </div>
                <div class="input-box">
                    <input type="password" name="password" id="login-password" placeholder="Password" required>
                    <i class='bx bx-show show-password' id="login-show-password"></i>
                    <i class='bx bxs-lock-alt'></i>
                </div>
                <div class="forgot-link">
                    <a href="#">Forgot Password?</a>
                </div>
                <button type="submit" name="login" class="btn">Login</button>
                <!-- <p>or login with</p>
                <div class="social-icons">
                    <a href="#"><i class='bx bxl-google'></i></a>
                    <a href="#"><i class='bx bxl-facebook'></i></a>
                    <a href="#"><i class='bx bxl-linkedin'></i></a>
                </div> -->
            </form>
        </div>

        <div class="form-box register">
            <form action="students_register.php" method="post">
                <div class="logo">
                    <a href="index.html">
                        
                    </a>
                </div>
                <h1>Register</h1>
                <p>Start your educational journey with us</p>
                
                <?php if ($register_error): ?>
                    <div class="error-message"><?php echo $register_error; ?></div>
                <?php endif; ?>
                
                <div class="input-box">
                    <input type="text" name="fullname" placeholder="Full Name" required>
                    <i class='bx bxs-user'></i>
                </div>
                <div class="input-box">
                    <input type="email" name="email" placeholder="Email" required>
                    <i class='bx bxs-envelope'></i>
                </div>
                <div class="input-box">
                    <input type="tel" name="phone" placeholder="Phone Number" required>
                    <i class='bx bxs-phone'></i>
                </div>
                <div class="input-box">
                    <input type="password" name="password" id="register-password" placeholder="Password" required>
                    <i class='bx bx-show show-password' id="register-show-password"></i>
                    <i class='bx bxs-lock-alt'></i>
                </div>
                <div class="input-box">
                    <input type="password" name="confirm_password" id="confirm-password" placeholder="Confirm Password" required>
                    <i class='bx bx-show show-password' id="confirm-show-password"></i>
                    <i class='bx bxs-lock-alt'></i>
                </div>
                <button type="submit" name="register" class="btn">Register</button>
                <!-- <p>or register with</p>
                <div class="social-icons">
                    <a href="#"><i class='bx bxl-google'></i></a>
                    <a href="#"><i class='bx bxl-facebook'></i></a>
                    <a href="#"><i class='bx bxl-linkedin'></i></a>
                </div> -->
            </form>
        </div>

        <div class="toggle-box">
            <div class="toggle-panel toggle-left">
                <h1>Unlock Your Educational Journey</h1>
                <p>Don't have an account yet? Register to discover our comprehensive student services.</p>
                <button class="btn register-btn">Register</button>
            </div>

            <div class="toggle-panel toggle-right">
                <h1>Welcome Back!</h1>
                <p>Already have an account? Login to continue your educational journey.</p>
                <button class="btn login-btn">Login</button>
            </div>
        </div>
    </div>

    <script>
        const container = document.querySelector('.container');
        const registerBtn = document.querySelector('.register-btn');
        const loginBtn = document.querySelector('.login-btn');

        registerBtn.addEventListener('click', () => {
            window.location.href = 'students_register.php';
        });

        loginBtn.addEventListener('click', () => {
            window.location.href = 'student_login.php';
        });

        // Show/hide password functionality
        function togglePasswordVisibility(passwordInput, toggleIcon) {
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                toggleIcon.classList.remove('bx-show');
                toggleIcon.classList.add('bx-hide');
            } else {
                passwordInput.type = "password";
                toggleIcon.classList.remove('bx-hide');
                toggleIcon.classList.add('bx-show');
            }
        }

        // Login password
        const loginPassword = document.getElementById('login-password');
        const loginShowPassword = document.getElementById('login-show-password');
        loginShowPassword.addEventListener('click', () => {
            togglePasswordVisibility(loginPassword, loginShowPassword);
        });

        // Register password
        const registerPassword = document.getElementById('register-password');
        const registerShowPassword = document.getElementById('register-show-password');
        registerShowPassword.addEventListener('click', () => {
            togglePasswordVisibility(registerPassword, registerShowPassword);
        });

        // Confirm password
        const confirmPassword = document.getElementById('confirm-password');
        const confirmShowPassword = document.getElementById('confirm-show-password');
        confirmShowPassword.addEventListener('click', () => {
            togglePasswordVisibility(confirmPassword, confirmShowPassword);
        });
    </script>
    <!-- Translation Toggle Button -->
    <div class="translation-toggle" id="translationToggle">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 8l6 6"></path>
                <path d="M4 14l6-6 2-3"></path>
                <path d="M2 5h12"></path>
                <path d="M7 2h1"></path>
                <path d="M22 22l-5-10-5 10"></path>
                <path d="M14 18h6"></path>
            </svg>
        </div>
        <div class="label">Translate into french</div>
    </div>
    <script>
        document.getElementById('translationToggle').addEventListener('click', function() {
            window.location.href = 'students_register.php';
        });
    </script>

<script>
    // Preloader control
    function hidePreloader() {
        document.getElementById('sw-preloader').classList.add('hidden');
    }
    
    // Hide preloader after page load
    window.addEventListener('load', function() {
        setTimeout(hidePreloader, 2000);
    });
    
    // Fallback
    setTimeout(hidePreloader, 5000);
    
    // Show preloader with custom text
    function showPreloader(text) {
        if (text) {
            const loadingText = document.querySelector('.sw-loading-text');
            if (loadingText) {
                loadingText.textContent = text;
            }
        }
        document.getElementById('sw-preloader').classList.remove('hidden');
    }

    // Translation functionality
</script>
</body>
</html>