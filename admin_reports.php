<?php
// admin_reports.php
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['employee_id']) || !$_SESSION['employee_logged_in'] || $_SESSION['employee_role'] != 'Admin') {
    header("Location: employee_login.php");
    exit;
}

$admin_id = $_SESSION['employee_id'];
$admin_name = $_SESSION['employee_name'];

// Default report period
$start_date = date('Y-m-01'); // First day of current month
$end_date = date('Y-m-d'); // Today

// Handle custom date range
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['generate_report'])) {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
}

// Get applications data for the period
$apps_query = "SELECT 
              COUNT(*) as total_applications,
              SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
              SUM(CASE WHEN status = 'Under Review' THEN 1 ELSE 0 END) as under_review,
              SUM(CASE WHEN status = 'Sent to University' THEN 1 ELSE 0 END) as sent,
              SUM(CASE WHEN status = 'Accepted' THEN 1 ELSE 0 END) as accepted,
              SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
              FROM applications
              WHERE submission_date BETWEEN ? AND ?";

$apps_stmt = $conn->prepare($apps_query);
$apps_stmt->bind_param("ss", $start_date, $end_date);
$apps_stmt->execute();
$apps_result = $apps_stmt->get_result();
$apps_data = $apps_result->fetch_assoc();
$apps_stmt->close();

// Get payments data for the period - Fixed to handle NULL values
$payments_query = "SELECT 
                 COUNT(*) as total_payments,
                 COALESCE(SUM(amount), 0) as total_amount,
                 COALESCE(SUM(CASE WHEN status = 'Completed' THEN amount ELSE 0 END), 0) as received_amount,
                 COUNT(CASE WHEN status = 'Completed' THEN 1 ELSE NULL END) as completed_payments,
                 COALESCE(SUM(CASE WHEN status = 'Pending' THEN amount ELSE 0 END), 0) as pending_amount,
                 COUNT(CASE WHEN status = 'Pending' THEN 1 ELSE NULL END) as pending_payments
                 FROM payments
                 WHERE payment_date BETWEEN ? AND ?";

$payments_stmt = $conn->prepare($payments_query);
$payments_stmt->bind_param("ss", $start_date, $end_date);
$payments_stmt->execute();
$payments_result = $payments_stmt->get_result();
$payments_data = $payments_result->fetch_assoc();
$payments_stmt->close();

// Set default values if no payment data is found
if ($payments_data['total_payments'] == 0) {
    $payments_data['total_amount'] = 0;
    $payments_data['received_amount'] = 0;
    $payments_data['pending_amount'] = 0;
    $payments_data['completed_payments'] = 0;
    $payments_data['pending_payments'] = 0;
}

// Get top countries data
$countries_query = "SELECT destination_country, COUNT(*) as count
                  FROM applications
                  WHERE submission_date BETWEEN ? AND ?
                  GROUP BY destination_country
                  ORDER BY count DESC
                  LIMIT 5";

$countries_stmt = $conn->prepare($countries_query);
$countries_stmt->bind_param("ss", $start_date, $end_date);
$countries_stmt->execute();
$countries_result = $countries_stmt->get_result();
$countries_stmt->close();

// Get top universities data
$universities_query = "SELECT university_name, COUNT(*) as count
                     FROM applications
                     WHERE submission_date BETWEEN ? AND ?
                     GROUP BY university_name
                     ORDER BY count DESC
                     LIMIT 5";

$universities_stmt = $conn->prepare($universities_query);
$universities_stmt->bind_param("ss", $start_date, $end_date);
$universities_stmt->execute();
$universities_result = $universities_stmt->get_result();
$universities_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - SuccessWay</title>
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
        }
        
        .card-title {
            margin: 0;
            font-size: 20px;
            color: #333;
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 0;
            flex: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .submit-btn {
            background-color: #40b3a2;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .submit-btn:hover {
            background-color: #368f82;
        }
        
        .report-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .summary-card h3 {
            margin-top: 0;
            margin-bottom: 5px;
            font-size: 28px;
            color: #40b3a2;
        }
        
        .summary-card p {
            margin-bottom: 0;
            color: #777;
            font-size: 14px;
        }
        
        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .report-chart {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 20px;
        }
        
        .report-chart h3 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 18px;
            color: #333;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .date-range {
            font-size: 14px;
            color: #666;
            margin-left: 10px;
        }
        
        .stat-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .stat-item:last-child {
            border-bottom: none;
        }
        
        .stat-label {
            color: #555;
        }
        
        .stat-value {
            font-weight: 600;
            color: #333;
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
            
            .card-header {
                margin-top: 60px;
            }
        }
        
        @media (max-width: 768px) {
            .card {
                padding: 20px;
            }
            
            .report-summary {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-form {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            
            .form-group {
                margin-bottom: 10px;
            }
            
            .submit-btn {
                width: 100%;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .date-range {
                margin-left: 0;
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
            
            .report-summary {
                grid-template-columns: 1fr;
            }
            
            .report-grid {
                grid-template-columns: 1fr;
            }
            
            .card-header {
                margin-top: 70px;
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
            <div class="mobile-logo-text" translate="no">
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
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-container">
                    <div class="logo-img">
                    <img src="successway_logo.png" alt="SuccessWay Logo" class="logo-img">
                    </div>
                    <div class="logo-text" translate="no">
                        <span class="success">Success</span><span class="way">Way</span>
                    </div>
                </div>
            </div>
            
            <div class="user-info">
                Welcome, 
                <span><?php echo htmlspecialchars($admin_name); ?></span>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="admin_dashboard.php">Dashboard</a></li>
                <li><a href="admin_finances.php">Finances</a></li>
                <li><a href="admin_reports.php" class="active">Reports</a></li>
            </ul>
            
            <div class="sidebar-footer">
                <a href="employee_logout.php" class="logout-link">Logout</a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="card-header">
                <h1 class="page-title">Reports & Analytics</h1>
                <div>
                    <span class="date-range">
                        <i class="fas fa-calendar-alt"></i> 
                        <?php echo date('M d, Y', strtotime($start_date)); ?> - 
                        <?php echo date('M d, Y', strtotime($end_date)); ?>
                    </span>
                </div>
            </div>
            
            <!-- Date Range Filter -->
            <div class="card">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="filter-form">
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" required>
                    </div>
                    
                    <button type="submit" name="generate_report" class="submit-btn">
                        <i class="fas fa-chart-line"></i> Generate Report
                    </button>
                </form>
            </div>
            
            <!-- Applications Summary -->
            <h2>Applications</h2>
            <div class="report-summary">
                <div class="summary-card">
                    <h3><?php echo $apps_data['total_applications']; ?></h3>
                    <p>Total Applications</p>
                </div>
                
                <div class="summary-card">
                    <h3><?php echo $apps_data['under_review'] + $apps_data['sent']; ?></h3>
                    <p>In Progress</p>
                </div>
                
                <div class="summary-card">
                    <h3><?php echo $apps_data['accepted']; ?></h3>
                    <p>Accepted</p>
                </div>
                
                <div class="summary-card">
                    <h3><?php echo $apps_data['rejected']; ?></h3>
                    <p>Rejected</p>
                </div>
            </div>
            
            <!-- Financial Summary -->
            <h2>Financial Overview</h2>
            <div class="report-summary">
                <div class="summary-card">
                    <h3>$<?php echo number_format($payments_data['total_amount'], 2); ?></h3>
                    <p>Total Amount</p>
                </div>
                
                <div class="summary-card">
                    <h3><?php echo $payments_data['total_payments']; ?></h3>
                    <p>Total Payments</p>
                </div>
                
                <div class="summary-card">
                    <h3>$<?php echo number_format($payments_data['received_amount'], 2); ?></h3>
                    <p>Received Amount</p>
                </div>
                
                <div class="summary-card">
                    <h3>$<?php echo number_format($payments_data['pending_amount'], 2); ?></h3>
                    <p>Pending Amount</p>
                </div>
            </div>
            
            <!-- Detailed Reports -->
            <div class="report-grid">
                <!-- Top Destinations -->
                <div class="report-chart">
                    <h3><i class="fas fa-globe-americas"></i> Top Destination Countries</h3>
                    <ul class="stat-list">
                        <?php if ($countries_result->num_rows > 0): ?>
                            <?php while ($country = $countries_result->fetch_assoc()): ?>
                                <li class="stat-item">
                                    <span class="stat-label"><?php echo htmlspecialchars($country['destination_country']); ?></span>
                                    <span class="stat-value"><?php echo $country['count']; ?> applications</span>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="stat-item">
                                <span class="stat-label">No data available</span>
                                <span class="stat-value">0</span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- Top Universities -->
                <div class="report-chart">
                    <h3><i class="fas fa-university"></i> Top Universities</h3>
                    <ul class="stat-list">
                        <?php if ($universities_result->num_rows > 0): ?>
                            <?php while ($university = $universities_result->fetch_assoc()): ?>
                                <li class="stat-item">
                                    <span class="stat-label"><?php echo htmlspecialchars($university['university_name']); ?></span>
                                    <span class="stat-value"><?php echo $university['count']; ?> applications</span>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="stat-item">
                                <span class="stat-label">No data available</span>
                                <span class="stat-value">0</span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- Application Status Breakdown -->
                <div class="report-chart">
                    <h3><i class="fas fa-tasks"></i> Application Status Breakdown</h3>
                    <ul class="stat-list">
                        <li class="stat-item">
                            <span class="stat-label">Pending</span>
                            <span class="stat-value"><?php echo $apps_data['pending']; ?></span>
                        </li>
                        <li class="stat-item">
                            <span class="stat-label">Under Review</span>
                            <span class="stat-value"><?php echo $apps_data['under_review']; ?></span>
                        </li>
                        <li class="stat-item">
                            <span class="stat-label">Sent to University</span>
                            <span class="stat-value"><?php echo $apps_data['sent']; ?></span>
                        </li>
                        <li class="stat-item">
                            <span class="stat-label">Accepted</span>
                            <span class="stat-value"><?php echo $apps_data['accepted']; ?></span>
                        </li>
                        <li class="stat-item">
                            <span class="stat-label">Rejected</span>
                            <span class="stat-value"><?php echo $apps_data['rejected']; ?></span>
                        </li>
                    </ul>
                </div>
                
                <!-- Payment Stats -->
                <div class="report-chart">
                    <h3><i class="fas fa-money-bill-wave"></i> Payment Statistics</h3>
                    <ul class="stat-list">
                        <li class="stat-item">
                            <span class="stat-label">Completed Payments</span>
                            <span class="stat-value"><?php echo $payments_data['completed_payments']; ?></span>
                        </li>
                        <li class="stat-item">
                            <span class="stat-label">Pending Payments</span>
                            <span class="stat-value"><?php echo $payments_data['pending_payments']; ?></span>
                        </li>
                        <li class="stat-item">
                            <span class="stat-label">Average Payment</span>
                            <span class="stat-value">
                                $<?php
                                $avg_payment = 0;
                                if ($payments_data['total_payments'] > 0) {
                                    $avg_payment = $payments_data['total_amount'] / $payments_data['total_payments'];
                                }
                                echo number_format($avg_payment, 2);
                                ?>
                            </span>
                        </li>
                        <li class="stat-item">
                            <span class="stat-label">Completion Rate</span>
                            <span class="stat-value">
                                <?php 
                                $completion_rate = 0;
                                if ($payments_data['total_payments'] > 0) {
                                    $completion_rate = ($payments_data['completed_payments'] / $payments_data['total_payments']) * 100;
                                }
                                echo number_format($completion_rate, 1) . '%';
                                ?>
                            </span>
                        </li>
                    </ul>
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