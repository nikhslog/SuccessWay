<?php
// student_new_application.php
require_once 'config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Check if student is logged in
if (!isset($_SESSION['student_id']) || !$_SESSION['student_logged_in']) {
    header("Location: student_login.php");
    exit;
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];
$success_message = '';
$error_message = '';

// Get student's basic info to pre-fill some fields
$stmt = $conn->prepare("SELECT full_name, email, phone FROM students WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student_data = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Personal Information
    $first_name = sanitize_input($_POST["first_name"]);
    $middle_name = sanitize_input($_POST["middle_name"]);
    $last_name = sanitize_input($_POST["last_name"]);
    $dob = sanitize_input($_POST["dob"]);
    $gender = sanitize_input($_POST["gender"]);
    $nationality = sanitize_input($_POST["nationality"]);
    $passport_number = sanitize_input($_POST["passport_number"]);
    $email = sanitize_input($_POST["email"]);
    $phone = sanitize_input($_POST["phone"]);
    $whatsapp = sanitize_input($_POST["whatsapp"]);
    $city = sanitize_input($_POST["city"]);
    $country = sanitize_input($_POST["country"]);
    
    // Educational Background
    $education_level = sanitize_input($_POST["education_level"]);
    $last_school = sanitize_input($_POST["last_school"]);
    $graduation_year = sanitize_input($_POST["graduation_year"]);
    $field_of_study = sanitize_input($_POST["field_of_study"]);
    
    // Program of Interest
    $destination_country = sanitize_input($_POST["destination_country"]);
    $university_name = sanitize_input($_POST["university_name"]);
    $program = sanitize_input($_POST["program"]);
    $intake_month = sanitize_input($_POST["intake_month"]);
    $intake_year = sanitize_input($_POST["intake_year"]);
    
    // Financial Information
    $funding_source = sanitize_input($_POST["funding_source"]);
    $budget = sanitize_input($_POST["budget"]);
    
    // Language Proficiency
    $english_certificate = isset($_POST["english_certificate"]) ? 1 : 0;
    $language_score = sanitize_input($_POST["language_score"]);
    $language_preference = sanitize_input($_POST["language_preference"]);
    
    // Additional Information
    $need_accommodation = isset($_POST["need_accommodation"]) ? 1 : 0;
    $need_visa = isset($_POST["need_visa"]) ? 1 : 0;
    $special_requests = sanitize_input($_POST["special_requests"]);
    
    // Terms and Signature
    $agree_terms = isset($_POST["agree_terms"]) ? 1 : 0;
    $signature = sanitize_input($_POST["signature"]);
    
    if (!$agree_terms) {
        $error_message = "You must agree to the terms and conditions.";
    } else {
        // Start transaction for database operations
        $conn->begin_transaction();
        
        try {
            // Create formatted full name
            $full_name = trim("$first_name $middle_name $last_name");
            
            // Insert application with intake_month and intake_year
            $stmt = $conn->prepare("INSERT INTO applications (student_id, destination_country, university_name, program, intake_month, intake_year, status, notes) VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?)");
            $stmt->bind_param("issssss", $student_id, $destination_country, $university_name, $program, $intake_month, $intake_year, $special_requests);
            $stmt->execute();
            $application_id = $stmt->insert_id;
            $stmt->close();
            
            // Insert application details
            $sql = "INSERT INTO application_details (
                application_id, first_name, middle_name, last_name, dob, gender,
                nationality, passport_number, email, phone, whatsapp, city, country,
                education_level, last_school, graduation_year, field_of_study,
                intake_month, intake_year, funding_source, budget, english_certificate,
                language_score, language_preference, need_accommodation, need_visa, signature
            ) VALUES (
                $application_id, 
                '" . $conn->real_escape_string($first_name) . "',
                '" . $conn->real_escape_string($middle_name) . "',
                '" . $conn->real_escape_string($last_name) . "',
                '" . $conn->real_escape_string($dob) . "',
                '" . $conn->real_escape_string($gender) . "',
                '" . $conn->real_escape_string($nationality) . "',
                '" . $conn->real_escape_string($passport_number) . "',
                '" . $conn->real_escape_string($email) . "',
                '" . $conn->real_escape_string($phone) . "',
                '" . $conn->real_escape_string($whatsapp) . "',
                '" . $conn->real_escape_string($city) . "',
                '" . $conn->real_escape_string($country) . "',
                '" . $conn->real_escape_string($education_level) . "',
                '" . $conn->real_escape_string($last_school) . "',
                '" . $conn->real_escape_string($graduation_year) . "',
                '" . $conn->real_escape_string($field_of_study) . "',
                '" . $conn->real_escape_string($intake_month) . "',
                '" . $conn->real_escape_string($intake_year) . "',
                '" . $conn->real_escape_string($funding_source) . "',
                " . ($budget ? floatval($budget) : 'NULL') . ",
                " . ($english_certificate ? 1 : 0) . ",
                '" . $conn->real_escape_string($language_score) . "',
                '" . $conn->real_escape_string($language_preference) . "',
                " . ($need_accommodation ? 1 : 0) . ",
                " . ($need_visa ? 1 : 0) . ",
                '" . $conn->real_escape_string($signature) . "'
            )";
            
            if (!$conn->query($sql)) {
                throw new Exception("Error inserting application details: " . $conn->error);
            }
            
            // Handle document uploads
            $upload_dir = "uploads/";
            
            // Create upload directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Define document types from the form
            $document_types = array(
                'transcript' => 'Academic Transcript',
                'language_certificate' => 'Language Certificate',
                'passport' => 'Passport Copy',
                'certificates' => 'Academic Certificates',
                'birth_certificate' => 'Birth Certificate',
                'photo' => 'Photo',
                'other_docs' => 'Other Documents'
            );
            
            // Process each document upload
            foreach ($document_types as $field_name => $doc_type) {
                if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] == 0) {
                    $tmp_name = $_FILES[$field_name]['tmp_name'];
                    $original_name = $_FILES[$field_name]['name'];
                    $file_ext = pathinfo($original_name, PATHINFO_EXTENSION);
                    
                    // Create a unique filename
                    $new_filename = "doc_" . $application_id . "_" . $field_name . "_" . time() . "." . $file_ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    // Move the file and record in database
                    if (move_uploaded_file($tmp_name, $upload_path)) {
                        $doc_stmt = $conn->prepare("INSERT INTO documents (application_id, document_type, file_name) VALUES (?, ?, ?)");
                        $doc_stmt->bind_param("iss", $application_id, $doc_type, $new_filename);
                        $doc_stmt->execute();
                        $doc_stmt->close();
                    }
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Your application has been submitted successfully! Application ID: " . $application_id;
            
            // Redirect to view the application
            header("Location: student_view_application.php?id=" . $application_id . "&new=1");
            exit;
            
        } catch (Exception $e) {
            // Rollback in case of error
            $conn->rollback();
            $error_message = "Error submitting application: " . $e->getMessage();
        }
    }
}
// Check if application_details table exists, create if not
$result = $conn->query("SHOW TABLES LIKE 'application_details'");
if ($result->num_rows == 0) {
    // Table creation code (unchanged)
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Application - SuccessWay</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        body {
            font-family: 'Montserrat', system-ui, -apple-system, sans-serif;
            background-color: #f5f8f9;
            margin: 0;
            padding: 0;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar styles */
        .sidebar {
            width: 250px;
            background-color: #40b3a2;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100%;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
        }
        
        .logo-img {
            width: 30px;
            height: 30px;
            margin-right: 8px;
            background-color: white;
            border-radius: 50%;
        }
        
        .logo-text {
            font-size: 24px;
            font-weight: bold;
        }
        
        .logo-text .success {
            color: white;
        }
        
        .logo-text .way {
            color: black;
        }
        
        .user-info {
            margin-top: 18px;
            font-size: 14px;
            margin-left: 20px;
            margin-bottom: 10px;
        }
        
        .user-info span {
            display: block;
            font-size: 18px;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: block;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-footer {
            padding: 0 20px;
            position: absolute;
            bottom: 20px;
            width: calc(100% - 40px);
            margin-bottom: 30px;
        }
        
        .logout-link {
            display: block;
            padding: 12px 20px;
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
            text-align: center;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .logout-link:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        /* Main content styles */
        .main-content {
            flex: 1;
            padding: 30px;
            margin-left: 250px;
            transition: margin-left 0.3s ease;
        }
        
        .page-title {
            margin-top: 0;
            margin-bottom: 30px;
            font-size: 28px;
            color: #333;
        }
        
        .mobile-header {
            display: none;
        }
        
        .hamburger-menu {
            display: none;
            position: fixed;
            top: 7px;
            right: 20px;
            z-index: 1001;
            background-color: #40b3a2;
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .card-header {
            margin-bottom: 25px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        
        .card-title {
            margin: 0;
            font-size: 20px;
            color: #333;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #40b3a2;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            grid-gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .required-label::after {
            content: " *";
            color: #e53e3e;
        }
        
        input[type="text"], 
        input[type="email"],
        input[type="tel"],
        input[type="date"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
            box-sizing: border-box;
            font-family: inherit;
        }
        
        input:focus, 
        select:focus,
        textarea:focus {
            border-color: #40b3a2;
            outline: none;
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .file-upload {
            margin-bottom: 20px;
        }
        
        .file-upload label {
            display: block;
            margin-bottom: 8px;
        }
        
        .file-upload input[type="file"] {
            display: block;
            width: 100%;
            padding: 10px;
            border: 1px dashed #ddd;
            border-radius: 8px;
            background-color: #f9f9f9;
            cursor: pointer;
        }
        
        .submit-btn {
            background-color: #40b3a2;
            color: white;
            border: none;
            border-radius: 30px;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: background-color 0.3s;
            display: inline-block;
        }
        
        .submit-btn:hover {
            background-color: #368f82;
        }
        
        .terms-container {
            margin: 20px 0;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            border: 1px solid #eee;
        }
        
        .terms-scroll {
            max-height: 150px;
            overflow-y: auto;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #eee;
            border-radius: 5px;
            background-color: white;
        }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        
        /* Responsive styles */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .mobile-header {
                display: flex;
                align-items: center;
                padding: 15px;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                background-color: white;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
                z-index: 998;
            }
            
            .mobile-logo-container {
                display: flex;
                align-items: center;
            }
            
            .mobile-logo-img {
                width: 25px;
                height: 25px;
                margin-right: 8px;
                background-color: #ffffff;
                border-radius: 50%;
            }
            
            .mobile-logo-text {
                font-size: 20px;
                font-weight: bold;
            }
            
            .mobile-logo-text .success {
                color: #40b3a2;
            }
            
            .mobile-logo-text .way {
                color: black;
            }
            
            .hamburger-menu {
                display: flex;
                justify-content: center;
                align-items: center;
            }
            
            .overlay.active {
                display: block;
            }
            
            .page-title {
                margin-top: 60px;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .card {
                padding: 20px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .form-section {
                margin-bottom: 20px;
            }
            
            .terms-container {
                padding: 15px;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 20px 15px;
            }
            
            .page-title {
                font-size: 24px;
                margin-top: 70px;
            }
            
            .checkbox-group {
                align-items: flex-start;
            }
            
            .checkbox-group input[type="checkbox"] {
                margin-top: 4px;
            }
            
            .submit-btn {
                width: 100%;
                padding: 12px 20px;
            }
        }
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
    z-index: 9999; /* High z-index to stay on top */
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
    background-color: #40b3a2; /* Hardcoded color instead of var to avoid conflicts */
    transform: translateY(-50%);
    animation: sw-drawLine 2s infinite ease-in-out;
}

.sw-loading-text {
    font-size: 1.5rem;
    font-weight: 600;
    color: #40b3a2; /* Hardcoded color instead of var to avoid conflicts */
    letter-spacing: 2px;
    opacity: 0;
    animation: sw-fadeText 2s infinite ease-in-out;
}

/* Scoped animations with unique names */
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
    bottom: 70px;
    left: 30px;
    background-color: white;
    border-radius: 50px;
    padding: 10px 15px;
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
    bottom: 30px;
    left: 30px;
    background-color: white;
    border-radius: 50px;
    padding: 8px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    display: none;
    z-index: 1000;
    border: 1px solid rgba(92, 184, 178, 0.2);
}

.language-options.active {
    display: flex;
}

.language-option {
    padding: 8px 15px;
    border-radius: 50px;
    margin: 0 4px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s;
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
        padding: 8px 12px;
    }
    
    .translation-toggle .label {
        font-size: 0.85rem;
    }
    
    .language-option {
        padding: 6px 12px;
        font-size: 0.85rem;
    }
}

@media (max-width: 480px) {
    .translation-toggle,
    .language-options {
        bottom: 15px;
        left: 15px;
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
    <!-- Mobile header with logo -->
    <div class="mobile-header">
        <div class="mobile-logo-container">
            <div class="mobile-logo-img">
            <div class="mobile-logo-container">
    <img src="successway_logo.png" alt="SuccessWay Logo" class="mobile-logo-img">
    <div class="mobile-logo-text">
        <span class="success">Success</span><span class="way">Way</span>
    </div>
</div>
            </div>
            <div class="mobile-logo-text">
                <span class="success">Success</span><span class="way">Way</span>
            </div>
        </div>
    </div>
    
    <!-- Hamburger menu button -->
    <button class="hamburger-menu" id="hamburgerMenu">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="overlay" id="overlay"></div>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-container">
                    <div class="logo-img">
                    <img src="successway_logo.png" alt="SuccessWay Logo" class="logo-img">
                    </div>
                    <div class="logo-text">
                        <span class="success">Success</span><span class="way">Way</span>
                    </div>
                </div>
            </div>
            
            <div class="user-info">
                Welcome, 
                <span><?php echo htmlspecialchars($student_name); ?></span>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="student_dashboard_en.php">Dashboard</a></li>
                <li><a href="student_new_application_en.php" class="active">New Application</a></li>
                <li><a href="student_payments_en.php">My Payments</a></li>
                <li><a href="student_profile_en.php">My Profile</a></li>
            </ul>
            
            <div class="sidebar-footer">
                <a href="student_logout.php" class="logout-link">Logout</a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <h1 class="page-title">New Application</h1>
            
            <?php if($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-file-alt"></i> Application Form</h2>
                    <p>Please fill in all required fields with accurate information for your application.</p>
                </div>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                    <!-- Personal Information -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-user"></i> Personal Information</h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="first_name" class="required-label">First Name</label>
                                <input type="text" id="first_name" name="first_name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="middle_name">Middle Name</label>
                                <input type="text" id="middle_name" name="middle_name">
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name" class="required-label">Last Name</label>
                                <input type="text" id="last_name" name="last_name" required>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="dob" class="required-label">Date of Birth</label>
                                <input type="date" id="dob" name="dob" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="gender" class="required-label">Gender</label>
                                <select id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="nationality" class="required-label">Nationality</label>
                                <input type="text" id="nationality" name="nationality" required>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="passport_number">Passport Number (if available)</label>
                                <input type="text" id="passport_number" name="passport_number">
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="required-label">Email Address</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($student_data['email'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone" class="required-label">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($student_data['phone'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="whatsapp">WhatsApp Number (if different)</label>
                                <input type="tel" id="whatsapp" name="whatsapp">
                            </div>
                            
                            <div class="form-group">
                                <label for="city" class="required-label">City</label>
                                <input type="text" id="city" name="city" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="country" class="required-label">Country</label>
                                <input type="text" id="country" name="country" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Educational Background -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-graduation-cap"></i> Educational Background</h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="education_level" class="required-label">Highest Level of Education Completed</label>
                                <select id="education_level" name="education_level" required>
                                    <option value="">Select Education Level</option>
                                    <option value="High School">High School</option>
                                    <option value="Diploma">Diploma</option>
                                    <option value="Associate Degree">Associate Degree</option>
                                    <option value="Bachelor's Degree">Bachelor's Degree</option>
                                    <option value="Master's Degree">Master's Degree</option>
                                    <option value="Doctorate">Doctorate</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="last_school" class="required-label">Name of Last School/University Attended</label>
                                <input type="text" id="last_school" name="last_school" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="graduation_year" class="required-label">Year of Graduation</label>
                                <input type="text" id="graduation_year" name="graduation_year" placeholder="YYYY" pattern="[0-9]{4}" required>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="field_of_study" class="required-label">Field of Study</label>
                                <input type="text" id="field_of_study" name="field_of_study" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="transcript" class="required-label">Academic Transcripts Upload</label>
                                <input type="file" id="transcript" name="transcript" accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Program of Interest -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-university"></i> Program of Interest</h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="destination_country" class="required-label">Preferred Country of Study</label>
                                <select id="destination_country" name="destination_country" required>
                                    <option value="">Select Country</option>
                                    <option value="Canada">Canada</option>
                                    <option value="China">China</option>
                                    <option value="India">India</option>
                                    <option value="Malaysia">Malaysia</option>
                                    <option value="Tunisia">Tunisia</option>
                                    <option value="Turkey">Turkey</option>
                                    <option value="Other">USA</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="university_name" class="required-label">Preferred University</label>
                                <input type="text" id="university_name" name="university_name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="program" class="required-label">Preferred Program/Major</label>
                                <input type="text" id="program" name="program" required>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="intake_month" class="required-label">Intended Intake Month</label>
                                <select id="intake_month" name="intake_month" required>
                                    <option value="">Select Month</option>
                                    <option value="January">January</option>
                                    <option value="February">February</option>
                                    <option value="March">March</option>
                                    <option value="April">April</option>
                                    <option value="May">May</option>
                                    <option value="June">June</option>
                                    <option value="July">July</option>
                                    <option value="August">August</option>
                                    <option value="September">September</option>
                                    <option value="October">October</option>
                                    <option value="November">November</option>
                                    <option value="December">December</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="intake_year" class="required-label">Intended Intake Year</label>
                                <select id="intake_year" name="intake_year" required>
                                    <option value="">Select Year</option>
                                    <?php 
                                    $current_year = date('Y');
                                    for ($i = $current_year; $i <= $current_year + 3; $i++) {
                                        echo "<option value=\"$i\">$i</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Financial Information -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-money-bill-wave"></i> Financial Information</h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="funding_source" class="required-label">Source of Funding</label>
                                <select id="funding_source" name="funding_source" required>
                                    <option value="">Select Funding Source</option>
                                    <option value="Self-sponsored">Self-sponsored</option>
                                    <option value="Scholarship">Scholarship</option>
                                    <option value="Family">Family</option>
                                    <option value="Loan">Loan</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="budget">Budget for Studies (USD)</label>
                                <input type="number" id="budget" name="budget" min="0" step="100" placeholder="Optional">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Language Proficiency -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-language"></i> Language Proficiency</h3>
                        
                        <div class="form-grid">
                            <div class="form-group checkbox-group">
                                <input type="checkbox" id="english_certificate" name="english_certificate">
                                <label for="english_certificate">I have an English Proficiency Certificate</label>
                            </div>
                            
                            <div class="form-group" id="language_score_group" style="display:none;">
                                <label for="language_certificate">Upload IELTS/TOEFL/PTE/Duolingo Score</label>
                                <input type="file" id="language_certificate" name="language_certificate" accept=".pdf,.jpg,.jpeg,.png">
                                
                                <div class="form-group">
                                    <label for="language_score">Score</label>
                                    <input type="text" id="language_score" name="language_score" placeholder="e.g., IELTS 7.0, TOEFL 95">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="language_preference" class="required-label">Preferred Language of Instruction</label>
                                <select id="language_preference" name="language_preference" required>
                                    <option value="">Select Language</option>
                                    <option value="English">English</option>
                                    <option value="French">French</option>
                                    <option value="Arabic">Arabic</option>
                                    <option value="Chinese">Chinese</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-info-circle"></i> Additional Information</h3>
                        
                        <div class="form-grid">
                            <div class="form-group checkbox-group">
                                <input type="checkbox" id="need_accommodation" name="need_accommodation">
                                <label for="need_accommodation">I need accommodation assistance</label>
                            </div>
                            
                            <div class="form-group checkbox-group">
                                <input type="checkbox" id="need_visa" name="need_visa">
                                <label for="need_visa">I need visa processing assistance</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="special_requests">Any special requests or additional notes?</label>
                            <textarea id="special_requests" name="special_requests" rows="4" placeholder="Please let us know if you have any special requirements or information to share."></textarea>
                        </div>
                    </div>
                    
                    <!-- Documents Upload -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-file-upload"></i> Documents Upload</h3>
                        
                        <div class="form-grid">
                            <div class="file-upload">
                                <label for="passport" class="required-label">Passport Copy</label>
                                <input type="file" id="passport" name="passport" accept=".pdf,.jpg,.jpeg,.png" required>
                            </div>
                            
                            <div class="file-upload">
                                <label for="certificates" class="required-label">Academic Certificates/Diplomas</label>
                                <input type="file" id="certificates" name="certificates" accept=".pdf,.jpg,.jpeg,.png" required>
                            </div>
                            
                            <div class="file-upload">
                                <label for="birth_certificate">Birth Certificate</label>
                                <input type="file" id="birth_certificate" name="birth_certificate" accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="file-upload">
                                <label for="photo" class="required-label">Photo (Passport Size)</label>
                                <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png" required>
                            </div>
                            
                            <div class="file-upload">
                                <label for="other_docs">Other Relevant Documents (Optional)</label>
                                <input type="file" id="other_docs" name="other_docs" accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Terms and Conditions -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-file-contract"></i> Terms & Conditions</h3>
                        
                        <div class="terms-container">
                            <div class="terms-scroll">
                                <p><strong>Terms and Conditions for SuccessWay Application Services</strong></p>
                                <p>By submitting this application, I acknowledge and agree to the following terms:</p>
                                <ol>
                                    <li>All information provided in this application is true and accurate to the best of my knowledge.</li>
                                    <li>I authorize SuccessWay to use my personal information for processing my application and communicating with educational institutions on my behalf.</li>
                                    <li>I understand that SuccessWay will assist with my application but cannot guarantee acceptance by any institution.</li>
                                    <li>I agree to pay any applicable service fees in accordance with SuccessWay's payment policies.</li>
                                    <li>I understand that application fees paid to institutions are non-refundable regardless of acceptance or rejection.</li>
                                    <li>I will provide additional documentation as requested in a timely manner.</li>
                                    <li>I authorize SuccessWay to verify my academic credentials with relevant institutions.</li>
                                    <li>I understand that false information may result in rejection of my application and termination of services without refund.</li>
                                </ol>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="agree_terms" name="agree_terms" required>
                                <label for="agree_terms" class="required-label">I have read and agree to the Terms and Conditions</label>
                            </div>
                            
                            <div class="form-group">
                                <label for="signature" class="required-label">Digital Signature (Type your full name)</label>
                                <input type="text" id="signature" name="signature" required>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn"><i class="fas fa-paper-plane"></i> Submit Application</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Show/hide language score field based on checkbox
        document.getElementById('english_certificate').addEventListener('change', function() {
            const languageScoreGroup = document.getElementById('language_score_group');
            if (this.checked) {
                languageScoreGroup.style.display = 'block';
            } else {
                languageScoreGroup.style.display = 'none';
            }
        });
        
        // Pre-fill form with student's name if available
        window.addEventListener('DOMContentLoaded', (event) => {
            const fullNameParts = "<?php echo htmlspecialchars($student_data['full_name'] ?? ''); ?>".split(' ');
            
            if (fullNameParts.length > 0 && fullNameParts[0]) {
                document.getElementById('first_name').value = fullNameParts[0];
            }
            
            if (fullNameParts.length > 2) {
                document.getElementById('middle_name').value = fullNameParts[1];
                document.getElementById('last_name').value = fullNameParts.slice(2).join(' ');
            } else if (fullNameParts.length > 1) {
                document.getElementById('last_name').value = fullNameParts[1];
            }
        });
        
        // JavaScript for responsive sidebar toggle
        const hamburgerMenu = document.getElementById('hamburgerMenu');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        
        hamburgerMenu.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });
        
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
        
        // Close sidebar on window resize to desktop size
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            }
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
        <div class="label">Translate into English</div>
    </div>
    <script>
        document.getElementById('translationToggle').addEventListener('click', function() {
            window.location.href = 'student_new_application.php';
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