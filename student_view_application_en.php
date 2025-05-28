<?php
// student_view_application.php
require_once 'config.php';

// Check if student is logged in
if (!isset($_SESSION['student_id']) || !$_SESSION['student_logged_in']) {
    header("Location: student_login.php");
    exit;
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

// Check if application ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: student_dashboard.php");
    exit;
}

$application_id = $_GET['id'];
$is_new_application = isset($_GET['new']) && $_GET['new'] == 1;

// Get application details
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE application_id = ? AND student_id = ?");
$app_stmt->bind_param("ii", $application_id, $student_id);
$app_stmt->execute();
$result = $app_stmt->get_result();

if ($result->num_rows === 0) {
    // Application not found or doesn't belong to this student
    header("Location: student_dashboard.php");
    exit;
}

$application = $result->fetch_assoc();
$app_stmt->close();

// Get documents for this application
$doc_stmt = $conn->prepare("SELECT * FROM documents WHERE application_id = ? ORDER BY upload_date DESC");
$doc_stmt->bind_param("i", $application_id);
$doc_stmt->execute();
$documents = $doc_stmt->get_result();
$doc_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Details - SuccessWay</title>
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
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #40b3a2;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
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
        
        .card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .card-title {
            margin: 0;
            font-size: 20px;
            color: #333;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: #ffeaa7;
            color: #d69e2e;
        }
        
        .status-review {
            background-color: #bee3f8;
            color: #3182ce;
        }
        
        .status-sent {
            background-color: #c6f6d5;
            color: #38a169;
        }
        
        .status-accepted {
            background-color: #c6f6d5;
            color: #38a169;
        }
        
        .status-rejected {
            background-color: #fed7d7;
            color: #e53e3e;
        }
        
        .application-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .detail-item {
            margin-bottom: 15px;
        }
        
        .detail-label {
            font-size: 14px;
            color: #777;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 16px;
            font-weight: 500;
            color: #333;
        }
        
        .notes-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .documents-list {
            margin-top: 20px;
        }
        
        .document-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: background-color 0.3s;
        }
        
        .document-item:hover {
            background-color: #f9f9f9;
        }
        
        .document-icon {
            width: 40px;
            height: 40px;
            background-color: #e9f5f3;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #40b3a2;
            font-size: 20px;
        }
        
        .document-info {
            flex: 1;
        }
        
        .document-type {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .document-date {
            font-size: 14px;
            color: #777;
        }
        
        .document-link {
            color: #40b3a2;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .document-link:hover {
            text-decoration: underline;
        }
        
        .status-timeline {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .timeline-item {
            display: flex;
            margin-bottom: 20px;
            position: relative;
        }
        
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        
        .timeline-item:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 16px;
            top: 32px;
            bottom: -12px;
            width: 2px;
            background-color: #eee;
        }
        
        .timeline-dot {
            width: 34px;
            height: 34px;
            background-color: #40b3a2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
            color: white;
            font-size: 14px;
        }
        
        .timeline-content {
            padding-top: 5px;
        }
        
        .timeline-status {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .timeline-date {
            font-size: 14px;
            color: #777;
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
            
            .back-link {
                margin-top: 60px;
                display: block;
            }
        }
        
        @media (max-width: 768px) {
            .card {
                padding: 20px;
            }
            
            .application-details {
                grid-template-columns: 1fr;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .status-badge {
                margin-top: 10px;
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
            
            .back-link {
                margin-top: 70px;
            }
            
            .document-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .document-icon {
                margin-bottom: 10px;
            }
            
            .document-link {
                margin-top: 10px;
                align-self: flex-start;
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
            <img src="successway_logo.png" alt="SuccessWay Logo" class="mobile-logo-img">
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
    
    <div class="dashboard-container">
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
                <li><a href="student_dashboard.php">Dashboard</a></li>
                <li><a href="student_new_application.php">New Application</a></li>
                <li><a href="student_payments.php">My Payments</a></li>
                <li><a href="student_profile.php">My Profile</a></li>
            </ul>
            
            <div class="sidebar-footer">
                <a href="student_logout.php" class="logout-link">Logout</a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <a href="student_dashboard.php" class="back-link">← Back to Dashboard</a>
            
            <h1 class="page-title">Application Details</h1>
            
            <?php if ($is_new_application): ?>
            <div class="alert alert-success">
                Your application has been submitted successfully! We will review it shortly.
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Application #<?php echo $application_id; ?></h2>
                    <?php 
                    $status_class = '';
                    switch($application['status']) {
                        case 'Pending':
                            $status_class = 'status-pending';
                            break;
                        case 'Under Review':
                            $status_class = 'status-review';
                            break;
                        case 'Sent to University':
                            $status_class = 'status-sent';
                            break;
                        case 'Accepted':
                            $status_class = 'status-accepted';
                            break;
                        case 'Rejected':
                            $status_class = 'status-rejected';
                            break;
                    }
                    ?>
                    <span class="status-badge <?php echo $status_class; ?>">
                        <?php echo $application['status']; ?>
                    </span>
                </div>
                
                <div class="application-details">
                    <div>
                        <div class="detail-item">
                            <div class="detail-label">University</div>
                            <div class="detail-value"><?php echo htmlspecialchars($application['university_name']); ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Program</div>
                            <div class="detail-value"><?php echo htmlspecialchars($application['program']); ?></div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="detail-item">
                            <div class="detail-label">Destination Country</div>
                            <div class="detail-value"><?php echo htmlspecialchars($application['destination_country']); ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Submission Date</div>
                            <div class="detail-value"><?php echo date('F d, Y', strtotime($application['submission_date'])); ?></div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($application['notes'])): ?>
                <div class="notes-section">
                    <h3>Additional Notes</h3>
                    <p><?php echo nl2br(htmlspecialchars($application['notes'])); ?></p>
                </div>
                <?php endif; ?>
                
                <!-- Status Timeline -->
                <div class="status-timeline">
                    <h3>Application Progress</h3>
                    
                    <div class="timeline-item">
                        <div class="timeline-dot">✓</div>
                        <div class="timeline-content">
                            <div class="timeline-status">Application Submitted</div>
                            <div class="timeline-date"><?php echo date('F d, Y', strtotime($application['submission_date'])); ?></div>
                        </div>
                    </div>
                    
                    <?php if ($application['status'] != 'Pending'): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot">✓</div>
                        <div class="timeline-content">
                            <div class="timeline-status">Under Review</div>
                            <div class="timeline-date"><?php echo date('F d, Y', strtotime($application['last_update'])); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($application['status'] == 'Sent to University' || $application['status'] == 'Accepted' || $application['status'] == 'Rejected'): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot">✓</div>
                        <div class="timeline-content">
                            <div class="timeline-status">Sent to University</div>
                            <div class="timeline-date"><?php echo date('F d, Y', strtotime($application['last_update'])); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($application['status'] == 'Accepted'): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot">✓</div>
                        <div class="timeline-content">
                            <div class="timeline-status">Application Accepted</div>
                            <div class="timeline-date"><?php echo date('F d, Y', strtotime($application['last_update'])); ?></div>
                        </div>
                    </div>
                    <?php elseif ($application['status'] == 'Rejected'): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot">✕</div>
                        <div class="timeline-content">
                            <div class="timeline-status">Application Rejected</div>
                            <div class="timeline-date"><?php echo date('F d, Y', strtotime($application['last_update'])); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Documents -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Uploaded Documents</h2>
                </div>
                
                <div class="documents-list">
                    <?php if ($documents->num_rows > 0): ?>
                        <?php while ($doc = $documents->fetch_assoc()): ?>
                        <div class="document-item">
                            <div class="document-icon">📄</div>
                            <div class="document-info">
                                <div class="document-type"><?php echo htmlspecialchars($doc['document_type']); ?></div>
                                <div class="document-date">Uploaded on <?php echo date('F d, Y', strtotime($doc['upload_date'])); ?></div>
                            </div>
                            <a href="uploads/<?php echo $doc['file_name']; ?>" class="document-link" target="_blank">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No documents uploaded for this application.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
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
            window.location.href = 'student_view_application.php';
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