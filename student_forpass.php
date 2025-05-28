<?php
require_once 'config.php';

$error = '';
$success = '';

// Function to generate a random token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Handle form submission for password reset request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["reset_request"])) {
    $email = sanitize_input($_POST["email"]);
    
    // Check if email exists in database
    $stmt = $conn->prepare("SELECT student_id, full_name FROM students WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Generate token and expiry time (24 hours from now)
        $token = generateToken();
        $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Store token in database
        $reset_stmt = $conn->prepare("INSERT INTO password_resets (student_id, token, expiry) VALUES (?, ?, ?) 
                                     ON DUPLICATE KEY UPDATE token = ?, expiry = ?");
        $reset_stmt->bind_param("issss", $user['student_id'], $token, $expiry, $token, $expiry);
        
        if ($reset_stmt->execute()) {
            // Create reset link
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
            
            // Here you would normally send an email with the reset link
            // For demonstration purposes, we'll just show the link
            $success = "Un lien de réinitialisation a été envoyé à votre adresse e-mail. Il expirera dans 24 heures.";
            
            // For testing/demonstration - remove this in production
            $success .= "<br><br>Lien de réinitialisation (pour démonstration seulement): <a href='$reset_link'>$reset_link</a>";
        } else {
            $error = "Une erreur s'est produite. Veuillez réessayer.";
        }
        $reset_stmt->close();
    } else {
        // Don't reveal if email exists or not for security
        $success = "Si l'adresse e-mail existe dans notre système, un lien de réinitialisation sera envoyé. Veuillez vérifier votre boîte de réception.";
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuccessWay - Mot de passe oublié</title>
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
    max-width: 500px;
    background: #fff;
    margin: 20px auto;
    border-radius: 20px;
    box-shadow: 0 0 30px rgba(0, 0, 0, .1);
    padding: 40px;
    overflow: hidden;
}

.container h1 {
    font-size: clamp(24px, 5vw, 32px);
    margin-bottom: 20px;
    color: #333;
    text-align: center;
}

.container p {
    font-size: clamp(12px, 3vw, 14px);
    margin: 15px 0;
    color: #666;
    text-align: center;
}

.logo {
    display: flex;
    align-items: center;
    justify-content: center;
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
    margin-top: 10px;
}

.btn:hover {
    background: #45a3a0;
}

.back-link {
    display: block;
    text-align: center;
    margin-top: 20px;
    color: #5cbfb9;
    font-size: clamp(12px, 3vw, 14px);
    font-weight: 500;
    transition: color 0.3s;
}

.back-link:hover {
    color: #45a3a0;
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
    transition: background-color 0.3s;
}

.back-btn:hover {
    background: #45a3a0;
}

.error-message {
    color: #e74c3c;
    font-size: clamp(12px, 3vw, 14px);
    text-align: center;
    margin: 10px 0;
    padding: 10px;
    background-color: rgba(231, 76, 60, 0.1);
    border-radius: 8px;
}

.success-message {
    color: #2ecc71;
    font-size: clamp(12px, 3vw, 14px);
    text-align: center;
    margin: 10px 0;
    padding: 10px;
    background-color: rgba(46, 204, 113, 0.1);
    border-radius: 8px;
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

/* Mobile responsiveness */
@media screen and (max-width: 600px) {
    .container {
        max-width: 95%;
        padding: 30px 20px;
    }
    
    .container h1 {
        font-size: 22px;
    }
    
    .input-box {
        margin: 15px 0;
    }
    
    .input-box input {
        padding: 10px 40px 10px 15px;
    }
    
    .back-btn {
        top: 5px;
        left: 5px;
        padding: 4px 8px;
        font-size: 11px;
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
    <div class="container">
        <a href="student_login.php" class="back-btn">
            <i class='bx bx-arrow-back'></i> Retour
        </a>
        <div class="form-section">
            <div class="logo">
                <a href="index.html">
                    <div class="logo-text">
                        <span>Success</span><span>Way</span>
                    </div>
                </a>
            </div>
            <h1>Forgotten password</h1>
            <p>Enter your email address to reset your password</p>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php else: ?>
                <form action="forgot_password.php" method="post">
                    <div class="input-box">
                        <input type="email" name="email" placeholder="Adresse e-mail" required>
                        <i class='bx bxs-envelope'></i>
                    </div>
                    <button type="submit" name="reset_request" class="btn">Reset password</button>
                </form>
            <?php endif; ?>
            
            <a href="student_login.php" class="back-link">Return to login page</a>
        </div>
    </div>

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
</script>
</body>
</html>