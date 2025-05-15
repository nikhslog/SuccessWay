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

if ($current_file === 'student_register.php') {
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
    flex-wrap: wrap;
}

.social-icons a {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    width: clamp(38px, 10vw, 45px);
    height: clamp(38px, 10vw, 45px);
    border: 1px solid #eee;
    border-radius: 50%;
    font-size: clamp(18px, 5vw, 22px);
    color: #666;
    margin: 0 8px 8px 0;
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
    text-align: center;
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
    font-size: clamp(12px, 3vw, 14px);
}

.toggle-panel .btn {
    width: clamp(120px, 30vw, 160px);
    height: clamp(40px, 10vw, 46px);
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
    top: 10px;
    left: 10px;
    padding: clamp(5px, 1.5vw, 8px) clamp(8px, 2vw, 15px);
    background: #5cbfb9;
    color: white;
    border: none;
    border-radius: 20px;
    font-size: clamp(11px, 2.5vw, 14px);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 3px;
    z-index: 10;
    transition: background-color 0.3s, left 1.2s ease-in-out, right 1.2s ease-in-out, top 1.2s ease-in-out;
}

.container.active .back-btn {
    left: auto;
    right: 10px;
    top: 10px;
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
        padding: 40px 20px 30px; /* Increased top padding to make room for the back button */
    }

    .toggle-panel {
        padding: 0 20px;
    }
    
    /* Back button styles moved to global styles */
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
        padding: 40px 15px 20px; /* Increased top padding for back button */
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
        padding-top: 30px; /* Add padding to avoid overlap with back button */
    }

    .toggle-panel.toggle-right {
        right: 0;
        bottom: -30%;
    }

    .container.active .toggle-panel.toggle-right { 
        bottom: 0; 
    }

    .input-box {
        margin: 15px 0;
    }

    .input-box input {
        padding: 10px 45px 10px 15px;
    }
    
    /* Set logo margin to create more space */
    .logo {
        margin-top: 5px;
        margin-bottom: 15px;
    }
}

@media screen and (max-width: 480px) {
    .container h1 {
        font-size: 20px;
        margin-top: 5px; /* Adjust margin to prevent overlap */
    }

    .container p {
        font-size: 12px;
        margin: 8px 0;
    }

    .form-box {
        padding: 35px 10px 15px; /* Increased top padding for back button */
    }

    .toggle-panel h1 {
        font-size: 18px;
        margin-top: 30px; /* Create space for back button */
    }

    .toggle-panel p {
        font-size: 12px;
    }

    .toggle-panel .btn {
        width: 120px;
        height: 36px;
        font-size: 13px;
    }

    .input-box {
        margin: 10px 0;
    }

    .input-box input {
        padding: 8px 40px 8px 12px;
        font-size: 13px;
    }

    .input-box i {
        font-size: 16px;
        right: 15px;
    }

    .input-box i.show-password {
        right: 38px;
    }

    .btn {
        height: 38px;
        font-size: 13px;
    }
    
    /* Adjust the logo for smaller screens */
    .logo {
        margin-top: 0;
        margin-bottom: 10px;
    }
    
    /* Ensure there's enough space at the top */
    .form-box.login, .form-box.register {
        padding-top: 40px;
    }
}

@media screen and (max-height: 600px) and (orientation: landscape) {
    .container {
        height: auto;
        min-height: 100vh;
    }

    .form-box {
        padding-top: 50px;
        padding-bottom: 50px;
    }
    
    .toggle-panel {
        padding: 10px;
    }
    
    .toggle-panel h1 {
        font-size: 20px;
        margin-bottom: 5px;
    }
    
    .toggle-panel p {
        margin-bottom: 10px;
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
    width: clamp(160px, 40vw, 200px);
    height: 60px;
    margin-bottom: 20px;
}

.sw-plane {
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    font-size: clamp(1.5rem, 4vw, 2rem);
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
    font-size: clamp(1.2rem, 3vw, 1.5rem);
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
        left: 80%;
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
    bottom: clamp(20px, 5vw, 70px);
    left: clamp(15px, 4vw, 30px);
    background-color: white;
    border-radius: 50px;
    padding: clamp(6px, 2vw, 10px) clamp(10px, 3vw, 15px);
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
    font-size: clamp(0.75rem, 2.5vw, 0.95rem);
    white-space: nowrap;
}

.language-options {
    position: fixed;
    bottom: clamp(15px, 4vw, 30px);
    left: clamp(15px, 4vw, 30px);
    background-color: white;
    border-radius: 50px;
    padding: clamp(4px, 1.5vw, 8px);
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
    padding: clamp(4px, 1.5vw, 8px) clamp(8px, 2.5vw, 15px);
    border-radius: 50px;
    margin: clamp(2px, 1vw, 4px);
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s;
    font-size: clamp(0.7rem, 2.5vw, 0.9rem);
    white-space: nowrap;
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
        bottom: 20px;
        left: 20px;
    }
    
    .translation-toggle {
        padding: 6px 10px;
    }
    
    .translation-toggle .label {
        font-size: 0.85rem;
    }
    
    .language-option {
        padding: 5px 10px;
        font-size: 0.85rem;
    }
}

@media (max-width: 480px) {
    .translation-toggle,
    .language-options {
        bottom: 15px;
        left: 15px;
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
}

/* Fix for Safari browser */
@supports (-webkit-touch-callout: none) {
    .container {
        height: -webkit-fill-available;
    }
}

/* Fix for landscape mode on mobile */
@media (max-height: 500px) and (orientation: landscape) {
    body {
        align-items: flex-start;
        padding-top: 10px;
    }
    
    .container {
        margin-top: 0;
        margin-bottom: 0;
        max-height: 95vh;
    }
    
    .form-box {
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        padding-top: 40px; /* More space for back button */
    }
    
    /* Adjust back button positioning for landscape */
    .back-btn {
        top: 5px;
        left: 5px;
        padding: 4px 8px;
        font-size: 11px;
    }
    
    .container.active .back-btn {
        right: 5px;
        top: 5px;
        left: auto;
    }
    
    /* Make content more compact in landscape */
    .input-box {
        margin: 8px 0;
    }
    
    .toggle-panel h1 {
        margin-top: 25px; /* Ensure no overlap with back button */
        font-size: 18px;
    }
    
    .toggle-panel p {
        margin-bottom: 10px;
    }
    
    .toggle-panel .btn {
        height: 34px;
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
            <i class='bx bx-arrow-back'></i> Back to Website
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
            <form action="student_register.php" method="post">
                <div class="logo">
                    <a href="index.html">
                        <div class="logo-text">
                            <span>Success</span><span>Way</span>
                        </div>
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
            window.location.href = 'student_register.php';
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
    <div class="label">Translate</div>
</div>

<!-- Language Options -->
<div class="language-options" id="languageOptions">
    <div class="language-option" data-lang="en">English</div>
    <div class="language-option" data-lang="fr">Français</div>
</div>

<!-- Hidden element for Google Translate -->
<div id="google_translate_element" style="display: none;"></div>
<!-- Google Translate Script -->
<script type="text/javascript">
    function googleTranslateElementInit() {
        new google.translate.TranslateElement({
            pageLanguage: 'en',
            includedLanguages: 'en,fr',
            layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
            autoDisplay: false
        }, 'google_translate_element');
    }
</script>
<script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

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
    document.addEventListener('DOMContentLoaded', function() {
        const translationToggle = document.getElementById('translationToggle');
        const languageOptions = document.getElementById('languageOptions');
        const languageOptionBtns = document.querySelectorAll('.language-option');
        const preloader = document.getElementById('sw-preloader');
        let isTranslating = false;
        
        // ----- COMPREHENSIVE COOKIE CLEARING -----
        function nukeGoogleTranslateCookies() {
            // Get all possible domain variations
            const hostname = window.location.hostname;
            const domains = [
                hostname,
                '.' + hostname,
                hostname.split('.').slice(1).join('.'),
                '.' + hostname.split('.').slice(1).join('.')
            ];
            
            // Get all possible paths
            const paths = ['/', '/en/', '/fr/', window.location.pathname];
            
            // Clear all variations of googtrans cookies
            domains.forEach(domain => {
                paths.forEach(path => {
                    document.cookie = `googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=${path}; domain=${domain};`;
                    document.cookie = `googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=${path};`;
                });
            });
            
            // Also clear without specifying domain
            paths.forEach(path => {
                document.cookie = `googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=${path};`;
            });
            
            // And root cookie without path/domain
            document.cookie = `googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC;`;
        }
        
        // ----- CLEAR ALL STORAGE TYPES -----
        function clearAllStorage() {
            // Clear all storage types
            localStorage.removeItem('successway_language');
            sessionStorage.removeItem('successway_language');
            localStorage.removeItem('googtrans');
            sessionStorage.removeItem('googtrans');
            
            // Also try to clear any other Google Translate related items
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && (key.includes('goog') || key.includes('trans'))) {
                    localStorage.removeItem(key);
                }
            }
            
            for (let i = 0; i < sessionStorage.length; i++) {
                const key = sessionStorage.key(i);
                if (key && (key.includes('goog') || key.includes('trans'))) {
                    sessionStorage.removeItem(key);
                }
            }
        }
        
        // ----- REMOVE ALL GOOGLE TRANSLATE ELEMENTS -----
        function removeGoogleTranslateElements() {
            // Remove all translate elements
            document.querySelectorAll('#google_translate_element, .skiptranslate, .goog-te-gadget, .goog-te-banner-frame, iframe[src*="translate.google"]')
                .forEach(el => {
                    if (el) el.remove();
                });
            
            // Reset body positioning
            document.body.style.removeProperty('top');
            document.body.style.position = '';
            document.documentElement.style.removeProperty('overflow');
            
            // Remove any translate-specific classes
            document.body.classList.remove('translated-ltr');
            document.body.classList.remove('translated-rtl');
        }
        
        // ----- REMOVE ALL GOOGLE TRANSLATE SCRIPTS -----
        function removeGoogleTranslateScripts() {
            document.querySelectorAll('script[src*="translate.google"], script[src*="element.js"]')
                .forEach(script => {
                    if (script) script.remove();
                });
            
            // Also remove dynamically added scripts
            document.querySelectorAll('script').forEach(script => {
                if (script && script.textContent && script.textContent.includes('googleTranslateElementInit')) {
                    script.remove();
                }
            });
            
            // Clean up global objects
            delete window.googleTranslateElementInit;
            if (window.google && window.google.translate) {
                delete window.google.translate;
            }
        }
        
        // ----- COMPREHENSIVE GOOGLE TRANSLATE RESET -----
        function resetTranslation() {
            nukeGoogleTranslateCookies();
            clearAllStorage();
            removeGoogleTranslateElements();
            removeGoogleTranslateScripts();
            
            // Also remove any meta tags Google might use
            document.querySelectorAll('meta[name*="translate"], meta[http-equiv="Content-Language"]')
                .forEach(meta => {
                    if (meta) meta.remove();
                });
        }
        
        // Toggle language options display
        translationToggle.addEventListener('click', function() {
            languageOptions.classList.toggle('active');
            translationToggle.style.display = languageOptions.classList.contains('active') ? 'none' : 'flex';
        });
        
        // Handle language selection
        languageOptionBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                // Prevent multiple clicks
                if (isTranslating) return;
                isTranslating = true;
                
                const lang = this.getAttribute('data-lang');
                
                // Remove active class from all buttons
                languageOptionBtns.forEach(b => b.classList.remove('active'));
                
                // Add active class to clicked button
                this.classList.add('active');
                
                // Hide language options and show toggle
                languageOptions.classList.remove('active');
                translationToggle.style.display = 'flex';
                
                if (lang === 'en') {
                    // Show preloader for English (reset to original)
                    showPreloader('Resetting to English...');
                    
                    // Reset translation then force reload with special parameters
                    resetTranslation();
                    
                    // Create a special reload URL that ensures cache busting
                    const newUrl = new URL(window.location.href);
                    
                    // Clear any existing language params
                    newUrl.searchParams.delete('lang');
                    newUrl.searchParams.delete('googtrans');
                    
                    // Add special params to prevent translation and force reload
                    newUrl.searchParams.set('notranslate', 'true');
                    newUrl.searchParams.set('clearcache', Date.now());
                    
                    // Small delay to ensure preloader is visible
                    setTimeout(() => {
                        // Use location.replace to avoid history entries
                        window.location.replace(newUrl.toString());
                    }, 300);
                    return;
                }
                
                // Show preloader for other languages
                showPreloader('Translating to ' + (lang === 'fr' ? 'French' : lang.toUpperCase()) + '...');
                
                // For other languages
                setLanguage(lang);
            });
        });
        
        // Click outside to close language options
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.language-options') && 
                !event.target.closest('.translation-toggle') && 
                languageOptions.classList.contains('active')) {
                
                languageOptions.classList.remove('active');
                translationToggle.style.display = 'flex';
            }
        });
        
        // Set up translation for a specific language
        function setLanguage(lang) {
            // First reset everything
            resetTranslation();
            
            // Create new translate element
            const translateElement = document.createElement('div');
            translateElement.id = 'google_translate_element';
            translateElement.style.display = 'none';
            document.body.appendChild(translateElement);
            
            // Pre-define the cookie approach as a backup
            const setDirectCookieApproach = () => {
                const langPair = "/en/" + lang;
                document.cookie = `googtrans=${langPair};path=/;`;
                
                // Multiple domains for broader compatibility
                if (window.location.hostname !== 'localhost') {
                    document.cookie = `googtrans=${langPair};path=/;domain=${window.location.hostname};`;
                    document.cookie = `googtrans=${langPair};path=/;domain=.${window.location.hostname};`;
                }
                
                // Use a custom event to force Google Translate to recognize the cookie
                const event = new Event('gtrans');
                window.dispatchEvent(event);
                
                // Save to localStorage as well for persistence
                localStorage.setItem('successway_language', lang);
                
                // Reload after setting cookie
                window.location.reload();
            };
            
            // Define the initialization function
            window.googleTranslateElementInit = function() {
                new google.translate.TranslateElement({
                    pageLanguage: 'en',
                    includedLanguages: 'en,fr',
                    autoDisplay: false,
                    layout: google.translate.TranslateElement.InlineLayout.HORIZONTAL
                }, 'google_translate_element');
            };
            
            // Load script
            const script = document.createElement('script');
            script.src = 'https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
            script.async = true;
            script.onerror = () => {
                console.error("Error loading Google Translate script");
                setDirectCookieApproach();
            };
            document.body.appendChild(script);
            
            // Set timeout to wait for script to load
            let attempts = 0;
            const maxAttempts = 30;
            const checkInterval = 300; // Check every 300ms
            
            const waitForTranslateCombo = setInterval(() => {
                attempts++;
                const select = document.querySelector('.goog-te-combo');
                
                if (select) {
                    clearInterval(waitForTranslateCombo);
                    
                    // Add a small delay to ensure the widget is fully loaded
                    setTimeout(() => {
                        // Set the language
                        select.value = lang;
                        select.dispatchEvent(new Event('change'));
                        
                        // Hide the Google elements
                        hideGoogleElements();
                        
                        // Save to localStorage for persistence
                        localStorage.setItem('successway_language', lang);
                        
                        // Hide preloader after a bit
                        setTimeout(() => {
                            hidePreloader();
                            isTranslating = false;
                        }, 1000);
                    }, 500);
                } 
                else if (attempts >= maxAttempts) {
                    clearInterval(waitForTranslateCombo);
                    console.log("Failed to find Google Translate combo, trying direct cookie approach");
                    setDirectCookieApproach();
                }
            }, checkInterval);
        }
        
        // Function to hide Google elements
        function hideGoogleElements() {
            document.querySelectorAll('.goog-te-banner-frame, .skiptranslate')
                .forEach(el => {
                    if (el) el.style.display = 'none';
                });
            
            document.body.style.top = '0';
        }
        
        // Set up observer to keep hiding Google elements
        const observer = new MutationObserver(hideGoogleElements);
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        // Check URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        
        // If notranslate is set, clear everything to ensure no translation
        if (urlParams.has('notranslate')) {
            resetTranslation();
            
            // Clean URL without reload
            const cleanUrl = new URL(window.location.href);
            cleanUrl.searchParams.delete('notranslate');
            cleanUrl.searchParams.delete('clearcache');
            window.history.replaceState({}, document.title, cleanUrl.toString());
        } 
        // Otherwise check for active language
        else {
            // Try to find translation language from URL or cookie or localStorage
            let currentLang = null;
            
            // Check localStorage first (most reliable)
            currentLang = localStorage.getItem('successway_language');
            
            // If not in localStorage, check URL
            if (!currentLang && urlParams.has('lang')) {
                currentLang = urlParams.get('lang');
            }
            
            // If not in URL, check cookies
            if (!currentLang) {
                const match = document.cookie.match(/googtrans=\/en\/([a-z]{2})/);
                if (match && match[1]) {
                    currentLang = match[1];
                }
            }
            
            // Apply translation if needed
            if (currentLang && currentLang !== 'en') {
                // Mark the correct button
                languageOptionBtns.forEach(btn => {
                    if (btn.getAttribute('data-lang') === currentLang) {
                        btn.classList.add('active');
                    }
                });
                
                // Show preloader
                showPreloader('Translating to ' + (currentLang === 'fr' ? 'French' : currentLang.toUpperCase()) + '...');
                
                // Start translation
                setLanguage(currentLang);
            }
        }
    });
</script>
</body>
</html>