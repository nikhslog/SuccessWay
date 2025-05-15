<?php
// student_new_application.php
require_once 'config.php';

// Check if student is logged in
if (!isset($_SESSION['student_id']) || !$_SESSION['student_logged_in']) {
    header("Location: student_login.php");
    exit;
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Personal Information
    $first_name = sanitize_input($_POST["first_name"]);
    $middle_name = sanitize_input($_POST["middle_name"]);
    $last_name = sanitize_input($_POST["last_name"]);
    $date_of_birth = sanitize_input($_POST["date_of_birth"]);
    $gender = sanitize_input($_POST["gender"]);
    $nationality = sanitize_input($_POST["nationality"]);
    $passport_number = sanitize_input($_POST["passport_number"]);
    $email = sanitize_input($_POST["email"]);
    $phone = sanitize_input($_POST["phone"]);
    $current_address = sanitize_input($_POST["current_address"]);
    
    // Educational Background
    $education_level = sanitize_input($_POST["education_level"]);
    $school_name = sanitize_input($_POST["school_name"]);
    $graduation_year = sanitize_input($_POST["graduation_year"]);
    $field_of_study = sanitize_input($_POST["field_of_study"]);
    
    // Program of Interest
    $destination_country = sanitize_input($_POST["destination_country"]);
    $university_name = sanitize_input($_POST["university_name"]);
    $program = sanitize_input($_POST["program"]);
    $intake_date = sanitize_input($_POST["intake_date"]);
    
    // Financial Information
    $funding_source = sanitize_input($_POST["funding_source"]);
    $budget = sanitize_input($_POST["budget"]);
    
    // Language Proficiency
    $english_certificate = isset($_POST["english_certificate"]) ? sanitize_input($_POST["english_certificate"]) : "No";
    $language_instruction = sanitize_input($_POST["language_instruction"]);
    
    // Additional Information
    $accommodation = isset($_POST["accommodation"]) ? sanitize_input($_POST["accommodation"]) : "No";
    $visa_assistance = isset($_POST["visa_assistance"]) ? sanitize_input($_POST["visa_assistance"]) : "No";
    $notes = sanitize_input($_POST["notes"]);
    
    // Consent
    $consent = isset($_POST["consent"]) ? 1 : 0;
    $signature = sanitize_input($_POST["signature"]);
    
    // Insert application into database
    $stmt = $conn->prepare("INSERT INTO applications (
        student_id, 
        first_name, middle_name, last_name, 
        date_of_birth, gender, nationality, passport_number,
        email, phone, current_address,
        education_level, school_name, graduation_year, field_of_study,
        destination_country, university_name, program, intake_date,
        funding_source, budget,
        english_certificate, language_instruction,
        accommodation, visa_assistance, notes,
        consent, signature
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )");
    
    $stmt->bind_param("issssssssssssisssssssssssis", 
        $student_id, 
        $first_name, $middle_name, $last_name,
        $date_of_birth, $gender, $nationality, $passport_number,
        $email, $phone, $current_address,
        $education_level, $school_name, $graduation_year, $field_of_study,
        $destination_country, $university_name, $program, $intake_date,
        $funding_source, $budget,
        $english_certificate, $language_instruction,
        $accommodation, $visa_assistance, $notes,
        $consent, $signature
    );
    
    if ($stmt->execute()) {
        $application_id = $stmt->insert_id;
        $success_message = "Application submitted successfully!";
        
        // Handle document uploads if there are any
        if (isset($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
            $upload_dir = "uploads/";
            
            // Create upload directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Define required document types
            $required_docs = ['Passport', 'Academic', 'Birth', 'Photo'];
            
            for ($i = 0; $i < count($_FILES['documents']['name']); $i++) {
                // Skip empty file uploads for optional documents
                if ($_FILES['documents']['name'][$i] == "" && !in_array($_POST['doc_type'][$i], $required_docs)) {
                    continue;
                }
                
                if ($_FILES['documents']['error'][$i] == 0) {
                    $doc_type = sanitize_input($_POST['doc_type'][$i]);
                    $tmp_name = $_FILES['documents']['tmp_name'][$i];
                    $original_name = $_FILES['documents']['name'][$i];
                    $file_ext = pathinfo($original_name, PATHINFO_EXTENSION);
                    
                    // Create a unique filename
                    $new_filename = "doc_" . $application_id . "_" . $doc_type . "_" . time() . "_" . $i . "." . $file_ext;
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
        }
        
        // Redirect to the view application page
        header("Location: student_view_application.php?id=" . $application_id . "&new=1");
        exit;
    } else {
        $error_message = "Error submitting application: " . $conn->error;
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
    <title>New Application - SuccessWay</title>
    <link rel="stylesheet" href="style.css">
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
        
        /* Sidebar styles - same as dashboard */
        .sidebar {
            width: 250px;
            background-color: #40b3a2;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100%;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }
        
        .logo-text {
            font-size: 24px;
            font-weight: bold;
        }
        
        .user-info {
            margin-top: 20px;
            font-size: 14px;
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
        
        .logout-link {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
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
        }
        
        .page-title {
            margin-top: 0;
            margin-bottom: 30px;
            font-size: 28px;
            color: #333;
        }
        
        .card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 0;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
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
        
        .section-title {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-top: 40px;
            margin-bottom: 25px;
            color: #40b3a2;
        }
        
        .document-uploads {
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        
        .upload-container {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px dashed #ddd;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        
        .add-document-btn {
            background-color: #eee;
            color: #333;
            border: none;
            border-radius: 5px;
            padding: 8px 15px;
            font-size: 14px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        
        .add-document-btn:hover {
            background-color: #e0e0e0;
        }
        
        .submit-btn {
            background-color: #40b3a2;
            color: white;
            border: none;
            border-radius: 30px;
            padding: 12px 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: background-color 0.3s;
        }
        
        .submit-btn:hover {
            background-color: #368f82;
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
        
        .checkbox-container {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .checkbox-container input[type="checkbox"] {
            margin-right: 10px;
            width: auto;
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 15px 0;
            }
            
            .sidebar-header {
                padding: 0 15px 15px;
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .logout-link {
                position: static;
                margin: 20px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
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
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo-text">
                    <span class="success" style="color: white;">Success</span>Way
                </div>
                <div class="user-info">
                    Welcome, 
                    <span><?php echo htmlspecialchars($student_name); ?></span>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="student_dashboard.php">Dashboard</a></li>
                <li><a href="student_new_application.php" class="active">New Application</a></li>
                <li><a href="student_profile.php">My Profile</a></li>
            </ul>
            
            <a href="student_logout.php" class="logout-link">Logout</a>
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
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                    
                    <!-- Personal Information -->
                    <h2 class="section-title">Personal Information</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="nationality">Nationality</label>
                            <input type="text" id="nationality" name="nationality" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="passport_number">Passport Number</label>
                            <input type="text" id="passport_number" name="passport_number">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number (with WhatsApp)</label>
                            <input type="tel" id="phone" name="phone" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="current_address">Current Address (City, Country)</label>
                        <input type="text" id="current_address" name="current_address" required>
                    </div>
                    
                    <!-- Educational Background -->
                    <h2 class="section-title">Educational Background</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="education_level">Highest Level of Education Completed</label>
                            <select id="education_level" name="education_level" required>
                                <option value="">Select Education Level</option>
                                <option value="High School">High School</option>
                                <option value="Bachelor">Bachelor's Degree</option>
                                <option value="Master">Master's Degree</option>
                                <option value="PhD">PhD/Doctorate</option>
                                <option value="Diploma">Diploma</option>
                                <option value="Certificate">Certificate</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="school_name">Name of Last School/University Attended</label>
                            <input type="text" id="school_name" name="school_name" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="graduation_year">Year of Graduation</label>
                            <input type="number" id="graduation_year" name="graduation_year" min="1950" max="2030" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="field_of_study">Field of Study</label>
                            <input type="text" id="field_of_study" name="field_of_study" required>
                        </div>
                    </div>
                    
                    <!-- Program of Interest -->
                    <h2 class="section-title">Program of Interest</h2>
                    
                    <div class="form-group">
                        <label for="destination_country">Preferred Country of Study</label>
                        <select id="destination_country" name="destination_country" required>
                            <option value="">Select Country</option>
                            <option value="Canada">Canada</option>
                            <option value="China">China</option>
                            <option value="India">India</option>
                            <option value="Malaysia">Malaysia</option>
                            <option value="Tunisia">Tunisia</option>
                            <option value="Turkey">Turkey</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="university_name">Preferred University</label>
                            <input type="text" id="university_name" name="university_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="program">Preferred Program/Major</label>
                            <input type="text" id="program" name="program" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="intake_date">Intended Intake (Month & Year)</label>
                        <input type="month" id="intake_date" name="intake_date" required>
                    </div>
                    
                    <!-- Financial Information -->
                    <h2 class="section-title">Financial Information</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="funding_source">Source of Funding</label>
                            <select id="funding_source" name="funding_source" required>
                                <option value="">Select Funding Source</option>
                                <option value="Self">Self-sponsored</option>
                                <option value="Scholarship">Scholarship</option>
                                <option value="Family">Family</option>
                                <option value="Loan">Loan</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="budget">Budget for Studies (Optional)</label>
                            <input type="text" id="budget" name="budget" placeholder="e.g. $20,000 USD">
                        </div>
                    </div>
                    
                    <!-- Language Proficiency -->
                    <h2 class="section-title">Language Proficiency</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Do you have an English Proficiency Certificate?</label>
                            <div class="checkbox-container">
                                <input type="checkbox" id="english_certificate" name="english_certificate" value="Yes">
                                <label for="english_certificate" style="display: inline; margin-bottom: 0;">Yes</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="language_instruction">Preferred Language of Instruction</label>
                            <select id="language_instruction" name="language_instruction" required>
                                <option value="">Select Language</option>
                                <option value="English">English</option>
                                <option value="French">French</option>
                                <option value="Chinese">Chinese</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <h2 class="section-title">Additional Information</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Do you need accommodation assistance?</label>
                            <div class="checkbox-container">
                                <input type="checkbox" id="accommodation" name="accommodation" value="Yes">
                                <label for="accommodation" style="display: inline; margin-bottom: 0;">Yes</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Do you need visa processing assistance?</label>
                            <div class="checkbox-container">
                                <input type="checkbox" id="visa_assistance" name="visa_assistance" value="Yes">
                                <label for="visa_assistance" style="display: inline; margin-bottom: 0;">Yes</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Any special requests or additional notes?</label>
                        <textarea id="notes" name="notes"></textarea>
                    </div>
                    
                    <!-- Document Uploads -->
                    <h2 class="section-title">Document Uploads</h2>
                    <p>Please upload all necessary documents as required by your application.</p>
                    
                    <!-- Required Documents - Fixed Fields -->
                    <div class="upload-container">
                        <h3>Required Documents</h3>
                        
                        <div class="form-group">
                            <label for="passport_doc">Passport Copy</label>
                            <input type="file" id="passport_doc" name="documents[]" required>
                            <input type="hidden" name="doc_type[]" value="Passport">
                        </div>
                        
                        <div class="form-group">
                            <label for="academic_doc">Academic Certificates/Diplomas</label>
                            <input type="file" id="academic_doc" name="documents[]" required>
                            <input type="hidden" name="doc_type[]" value="Academic">
                        </div>
                        
                        <div class="form-group">
                            <label for="birth_doc">Birth Certificate</label>
                            <input type="file" id="birth_doc" name="documents[]" required>
                            <input type="hidden" name="doc_type[]" value="Birth">
                        </div>
                        
                        <div class="form-group">
                            <label for="photo_doc">Photo (Recent passport-size)</label>
                            <input type="file" id="photo_doc" name="documents[]" required>
                            <input type="hidden" name="doc_type[]" value="Photo">
                        </div>
                    </div>
                    
                    <!-- Additional Documents - Dynamic Fields -->
                    <h3>Additional Documents (Optional)</h3>
                    <div id="document-container">
                        <div class="upload-container">
                            <div class="form-group">
                                <label>Document Type</label>
                                <select name="doc_type[]">
                                    <option value="">Select Document Type</option>
                                    <option value="Language">Language Proficiency Certificate</option>
                                    <option value="Transcript">Academic Transcript</option>
                                    <option value="CV">CV/Resume</option>
                                    <option value="Letter">Recommendation Letter</option>
                                    <option value="Other">Other Relevant Document</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Upload File</label>
                                <input type="file" name="documents[]">
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" id="add-document" class="add-document-btn">+ Add Another Document</button>
                    
                    <!-- Consent & Submission -->
                    <h2 class="section-title">Consent & Submission</h2>
                    
                    <div class="form-group">
                        <div class="checkbox-container">
                            <input type="checkbox" id="consent" name="consent" required>
                            <label for="consent" style="display: inline; margin-bottom: 0;">
                                I agree to the Terms & Conditions and consent to the processing of my personal data for the purpose of this application.
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="signature">Digital Signature (Type your full name)</label>
                        <input type="text" id="signature" name="signature" required>
                    </div>
                    
                    <button type="submit" class="submit-btn">Submit Application</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('add-document').addEventListener('click', function() {
            const container = document.getElementById('document-container');
            const newUpload = document.createElement('div');
            newUpload.className = 'upload-container';
            newUpload.innerHTML = `
                <div class="form-group">
                    <label>Document Type</label>
                    <select name="doc_type[]" required>
                        <option value="">Select Document Type</option>
                        <option value="Passport">Passport Copy</option>
                        <option value="Academic">Academic Certificates/Diplomas</option>
                        <option value="Birth">Birth Certificate</option>
                        <option value="Photo">Photo</option>
                        <option value="Language">Language Proficiency Certificate</option>
                        <option value="Transcript">Academic Transcript</option>
                        <option value="CV">CV/Resume</option>
                        <option value="Letter">Recommendation Letter</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Upload File</label>
                    <input type="file" name="documents[]" required>
                </div>
            `;
            container.appendChild(newUpload);
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