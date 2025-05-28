<?php
// admin_update_application.php
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['employee_id']) || !$_SESSION['employee_logged_in'] || $_SESSION['employee_role'] != 'Admin') {
    header("Location: employee_login.php");
    exit;
}

$admin_id = $_SESSION['employee_id'];
$admin_name = $_SESSION['employee_name'];
$success_message = '';
$error_message = '';

// Check if application ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_applications.php");
    exit;
}

$application_id = $_GET['id'];

// Set predefined payment limit (currently $1000 for all countries)
$payment_limit = 1000;

// Get current payment information
$payments_query = "SELECT SUM(CASE WHEN status = 'Completed' THEN amount ELSE 0 END) as total_paid,
                  SUM(CASE WHEN status = 'Pending' THEN amount ELSE 0 END) as total_pending
                  FROM payments WHERE application_id = ?";
$payments_stmt = $conn->prepare($payments_query);
$payments_stmt->bind_param("i", $application_id);
$payments_stmt->execute();
$payments_result = $payments_stmt->get_result();
$payment_data = $payments_result->fetch_assoc();
$total_paid = $payment_data['total_paid'] ?: 0;
$total_pending = $payment_data['total_pending'] ?: 0;
$remaining_amount = $payment_limit - $total_paid - $total_pending;
$payments_stmt->close();

// Handle form submission for application update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_application'])) {
    $new_status = sanitize_input($_POST["status"]);
    $university = sanitize_input($_POST["university_name"]);
    $program = sanitize_input($_POST["program"]);
    $country = sanitize_input($_POST["destination_country"]);
    $notes = sanitize_input($_POST["notes"]);
    
    // Get current status and student email
    $status_stmt = $conn->prepare("SELECT a.status, s.email, s.full_name, s.student_id FROM applications a 
                                   JOIN students s ON a.student_id = s.student_id 
                                   WHERE a.application_id = ?");
    $status_stmt->bind_param("i", $application_id);
    $status_stmt->execute();
    $status_result = $status_stmt->get_result();
    $status_data = $status_result->fetch_assoc();
    $current_status = $status_data['status'];
    $student_email = $status_data['email'];
    $student_name = $status_data['full_name'];
    $student_id = $status_data['student_id'];
    $status_stmt->close();
    
    // Update application status
    $stmt = $conn->prepare("UPDATE applications SET status = ?, university_name = ?, program = ?, destination_country = ?, notes = ? WHERE application_id = ?");
    $stmt->bind_param("sssssi", $new_status, $university, $program, $country, $notes, $application_id);
    
    if ($stmt->execute()) {
        // Log status change if status has changed
        if ($current_status != $new_status) {
            $log_stmt = $conn->prepare("INSERT INTO status_updates (application_id, old_status, new_status, updated_by) VALUES (?, ?, ?, ?)");
            $log_stmt->bind_param("issi", $application_id, $current_status, $new_status, $admin_id);
            $log_stmt->execute();
            $log_stmt->close();
            
            // Send email notification to student
            $email_sent = sendStatusUpdateEmail($student_email, $student_name, $application_id, $current_status, $new_status, $university, $program);
            
            if ($email_sent) {
                $success_message = "Application updated successfully! Email notification sent to student.";
            } else {
                $success_message = "Application updated successfully! However, email notification could not be sent.";
            }
        } else {
            $success_message = "Application updated successfully!";
        }
    } else {
        $error_message = "Error updating application: " . $conn->error;
    }
    
    $stmt->close();
}

// Handle payment update form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_payment'])) {
    $payment_amount = floatval($_POST['payment_amount']);
    $payment_method = sanitize_input($_POST['payment_method']);
    $payment_status = sanitize_input($_POST['payment_status']);
    $payment_notes = sanitize_input($_POST['payment_notes']);
    
    // Get student ID for this application
    $student_query = $conn->prepare("SELECT student_id FROM applications WHERE application_id = ?");
    $student_query->bind_param("i", $application_id);
    $student_query->execute();
    $student_result = $student_query->get_result();
    $student_data = $student_result->fetch_assoc();
    $student_id = $student_data['student_id'];
    $student_query->close();
    
    // Validate payment amount
    if ($payment_amount <= 0) {
        $error_message = "Payment amount must be greater than zero.";
    } elseif ($payment_amount > $remaining_amount && $remaining_amount > 0) {
        $error_message = "Payment amount exceeds the remaining balance of $" . number_format($remaining_amount, 2);
    } else {
        // Insert new payment
        $payment_stmt = $conn->prepare("INSERT INTO payments (student_id, application_id, amount, payment_method, status, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $payment_stmt->bind_param("iidsss", $student_id, $application_id, $payment_amount, $payment_method, $payment_status, $payment_notes);
        
        if ($payment_stmt->execute()) {
            $success_message = "Payment of $" . number_format($payment_amount, 2) . " added successfully!";
            
            // Refresh payment data after update
            $payments_stmt = $conn->prepare($payments_query);
            $payments_stmt->bind_param("i", $application_id);
            $payments_stmt->execute();
            $payments_result = $payments_stmt->get_result();
            $payment_data = $payments_result->fetch_assoc();
            $total_paid = $payment_data['total_paid'] ?: 0;
            $total_pending = $payment_data['total_pending'] ?: 0;
            $remaining_amount = $payment_limit - $total_paid - $total_pending;
            $payments_stmt->close();
        } else {
            $error_message = "Error adding payment: " . $conn->error;
        }
        
        $payment_stmt->close();
    }
}

// Function to send email notification
function sendStatusUpdateEmail($email, $student_name, $application_id, $old_status, $new_status, $university, $program) {
    // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: SuccessWay <noreply@successway.com>" . "\r\n";
    
    // Email subject
    $subject = "Your Application Status Has Been Updated - SuccessWay";
    
    // Email body
    $body = "
    <html>
    <head>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                border: 1px solid #ddd;
                border-radius: 5px;
            }
            .header {
                background-color: #40b3a2;
                color: white;
                padding: 15px;
                text-align: center;
                border-radius: 5px 5px 0 0;
            }
            .content {
                padding: 20px;
            }
            .status {
                padding: 10px;
                margin: 15px 0;
                border-radius: 5px;
                text-align: center;
                font-weight: bold;
            }
            .old-status {
                background-color: #f8f9fa;
                color: #666;
            }
            .new-status {
                background-color: #d4edda;
                color: #155724;
            }
            .footer {
                text-align: center;
                margin-top: 20px;
                font-size: 12px;
                color: #777;
            }
            .button {
                display: inline-block;
                background-color: #40b3a2;
                color: white;
                padding: 10px 20px;
                text-decoration: none;
                border-radius: 5px;
                margin-top: 15px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Application Status Update</h2>
            </div>
            <div class='content'>
                <p>Dear $student_name,</p>
                
                <p>We're writing to inform you that the status of your application (ID: #$application_id) for <strong>$program</strong> at <strong>$university</strong> has been updated.</p>
                
                <p>Your application status has changed from:</p>
                <div class='status old-status'>$old_status</div>
                
                <p>to:</p>
                <div class='status new-status'>$new_status</div>";
    
    // Add specific messages based on new status
    switch($new_status) {
        case 'Under Review':
            $body .= "<p>Our team is currently reviewing your application materials. We'll update you once we've completed the review process.</p>";
            break;
        case 'Sent to University':
            $body .= "<p>Congratulations! Your application has been processed and sent to the university. The university will now review your application and make a decision.</p>";
            break;
        case 'Accepted':
            $body .= "<p>Congratulations! Your application has been accepted by the university. Please log in to your account for further instructions on next steps.</p>";
            break;
        case 'Rejected':
            $body .= "<p>We regret to inform you that your application was not successful. Please log in to your account to see if there are any notes that might help you with future applications. Our team is available to discuss alternative options with you.</p>";
            break;
    }
    
    $body .= "
                <p>You can log in to your SuccessWay account to view more details about your application.</p>
                
                <div style='text-align: center;'>
                    <a href='https://www.successway.com/student_login.php' class='button'>View Your Application</a>
                </div>
                
                <p>If you have any questions or need further assistance, please don't hesitate to contact our support team.</p>
                
                <p>Best regards,<br>The SuccessWay Team</p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " SuccessWay. All rights reserved.</p>
                <p>This is an automated email, please do not reply directly to this message.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Attempt to send the email
    return mail($email, $subject, $body, $headers);
}

// Get application details
$app_stmt = $conn->prepare("SELECT a.*, s.full_name, s.email, s.phone FROM applications a
                          JOIN students s ON a.student_id = s.student_id
                          WHERE a.application_id = ?");
$app_stmt->bind_param("i", $application_id);
$app_stmt->execute();
$result = $app_stmt->get_result();

if ($result->num_rows === 0) {
    // Application not found
    header("Location: admin_applications.php");
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

// Get status update history
$history_stmt = $conn->prepare("SELECT su.*, e.full_name as employee_name 
                              FROM status_updates su
                              JOIN employees e ON su.updated_by = e.employee_id
                              WHERE su.application_id = ?
                              ORDER BY su.update_date DESC");
$history_stmt->bind_param("i", $application_id);
$history_stmt->execute();
$history = $history_stmt->get_result();
$history_stmt->close();

// Get payment history
$payment_history_stmt = $conn->prepare("SELECT * FROM payments WHERE application_id = ? ORDER BY payment_date DESC");
$payment_history_stmt->bind_param("i", $application_id);
$payment_history_stmt->execute();
$payment_history = $payment_history_stmt->get_result();
$payment_history_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Application - SuccessWay</title>
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
        
        /* Sidebar styles */
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
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #40b3a2;
            text-decoration: none;
            font-weight: 500;
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
        
        .payment-completed {
            background-color: #c6f6d5;
            color: #38a169;
        }
        
        .payment-pending {
            background-color: #ffeaa7;
            color: #d69e2e;
        }
        
        .payment-failed {
            background-color: #fed7d7;
            color: #e53e3e;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-size: 14px;
            color: #777;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: 500;
            color: #333;
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
        
        input[type="text"],
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
        
        .submit-btn {
            background-color: #40b3a2;
            color: white;
            border: none;
            border-radius: 30px;
            padding: 12px 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .submit-btn:hover {
            background-color: #368f82;
        }
        
        .payment-summary {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .payment-summary h3 {
            margin-top: 0;
            color: #333;
            font-size: 18px;
        }
        
        .payment-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .payment-detail:last-child {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
        }
        
        .payment-label {
            font-weight: 500;
            color: #555;
        }
        
        .payment-value {
            font-weight: 600;
            color: #333;
        }
        
        .remaining-positive {
            color: #2c7a7b;
        }
        
        .remaining-zero {
            color: #38a169;
        }
        
        .payment-history {
            margin-top: 20px;
        }
        
        .payment-item {
            display: flex;
            justify-content: space-between;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .payment-item:last-child {
            border-bottom: none;
        }
        
        .payment-info {
            flex: 1;
        }
        
        .payment-amount {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .payment-meta {
            font-size: 14px;
            color: #777;
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
        
        .history-list {
            margin-top: 20px;
        }
        
        .history-item {
            display: flex;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .history-item:last-child {
            border-bottom: none;
        }
        
        .history-icon {
            margin-right: 15px;
            color: #40b3a2;
        }
        
        .history-content {
            flex: 1;
        }
        
        .history-text {
            margin-bottom: 5px;
        }
        
        .history-meta {
            font-size: 14px;
            color: #777;
        }
        
        .tab-container {
            margin-bottom: 20px;
        }
        
        .tabs {
            display: flex;
            border-bottom: 2px solid #eee;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 500;
            color: #777;
            transition: all 0.3s;
            position: relative;
        }
        
        .tab.active {
            color: #40b3a2;
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #40b3a2;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .logout-link {
                position: static;
                margin: 20px;
            }
            
            .tabs {
                overflow-x: auto;
                white-space: nowrap;
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
                <div class="logo-text" translate="no">
                    <span class="success" style="color: white;">Success</span>Way
                </div>
                <div class="user-info">
                    Welcome, 
                    <span><?php echo htmlspecialchars($admin_name); ?></span>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="admin_dashboard.php">Dashboard</a></li>
            
                <li><a href="admin_finances.php">Finances</a></li>
                <li><a href="admin_reports.php">Reports</a></li>
            </ul>
            
            <a href="employee_logout.php" class="logout-link">Logout</a>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <a href="admin_applications.php" class="back-link">← Back to Applications</a>
            
            <h1 class="page-title">Update Application</h1>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Student Information -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Student Information</h2>
                </div>
                
                <div class="info-grid">
                    <div>
                        <div class="info-item">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($application['full_name']); ?></div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($application['email']); ?></div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="info-item">
                            <div class="info-label">Phone</div>
                            <div class="info-value"><?php echo htmlspecialchars($application['phone']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tab navigation -->
            <div class="tab-container">
                <div class="tabs">
                    <div class="tab active" onclick="switchTab('application-tab')">Application Details</div>
                    <div class="tab" onclick="switchTab('payment-tab')">Payment Information</div>
                    <div class="tab" onclick="switchTab('documents-tab')">Documents</div>
                    <div class="tab" onclick="switchTab('history-tab')">Status History</div>
                </div>
                
                <!-- Tab Content: Application Details -->
                <div id="application-tab" class="tab-content active">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Update Application #<?php echo $application_id; ?></h2>
                        </div>
                        
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $application_id; ?>">
                            <div class="info-grid">
                                <div class="form-group">
                                    <label for="university_name">University Name</label>
                                    <input type="text" id="university_name" name="university_name" value="<?php echo htmlspecialchars($application['university_name']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="program">Program</label>
                                    <input type="text" id="program" name="program" value="<?php echo htmlspecialchars($application['program']); ?>" required>
                                </div>
                            
                                <div class="form-group">
                                    <label for="destination_country">Destination Country</label>
                                    <select id="destination_country" name="destination_country" required>
                                        <option value="">Select Country</option>
                                        <option value="Canada" <?php echo ($application['destination_country'] == 'Canada') ? 'selected' : ''; ?>>Canada</option>
                                        <option value="China" <?php echo ($application['destination_country'] == 'China') ? 'selected' : ''; ?>>China</option>
                                        <option value="India" <?php echo ($application['destination_country'] == 'India') ? 'selected' : ''; ?>>India</option>
                                        <option value="Malaysia" <?php echo ($application['destination_country'] == 'Malaysia') ? 'selected' : ''; ?>>Malaysia</option>
                                        <option value="Tunisia" <?php echo ($application['destination_country'] == 'Tunisia') ? 'selected' : ''; ?>>Tunisia</option>
                                        <option value="Turkey" <?php echo ($application['destination_country'] == 'Turkey') ? 'selected' : ''; ?>>Turkey</option>
                                        <option value="Other" <?php echo (!in_array($application['destination_country'], ['Canada', 'China', 'India', 'Malaysia', 'Tunisia', 'Turkey'])) ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="status">Application Status</label>
                                    <select id="status" name="status" required>
                                        <option value="Pending" <?php echo ($application['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Under Review" <?php echo ($application['status'] == 'Under Review') ? 'selected' : ''; ?>>Under Review</option>
                                        <option value="Sent to University" <?php echo ($application['status'] == 'Sent to University') ? 'selected' : ''; ?>>Sent to University</option>
                                        <option value="Accepted" <?php echo ($application['status'] == 'Accepted') ? 'selected' : ''; ?>>Accepted</option>
                                        <option value="Rejected" <?php echo ($application['status'] == 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea id="notes" name="notes"><?php echo htmlspecialchars($application['notes']); ?></textarea>
                            </div>
                            
                            <input type="hidden" name="update_application" value="1">
                            <button type="submit" class="submit-btn">Update Application</button>
                        </form>
                    </div>
                </div>
                
                <!-- Tab Content: Payment Information -->
                <div id="payment-tab" class="tab-content">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Payment Management</h2>
                        </div>
                        
                        <!-- Payment Summary -->
                        <div class="payment-summary">
                            <h3>Payment Summary</h3>
                            <div class="payment-detail">
                                <span class="payment-label">Application Fee:</span>
                                <span class="payment-value">$<?php echo number_format($payment_limit, 2); ?></span>
                            </div>
                            <div class="payment-detail">
                                <span class="payment-label">Total Paid:</span>
                                <span class="payment-value">$<?php echo number_format($total_paid, 2); ?></span>
                            </div>
                            <div class="payment-detail">
                                <span class="payment-label">Pending Payments:</span>
                                <span class="payment-value">$<?php echo number_format($total_pending, 2); ?></span>
                            </div>
                            <div class="payment-detail">
                                <span class="payment-label">Remaining Balance:</span>
                                <span class="payment-value <?php echo $remaining_amount > 0 ? 'remaining-positive' : 'remaining-zero'; ?>">
                                    $<?php echo number_format($remaining_amount, 2); ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Add Payment Form -->
                        <?php if ($remaining_amount > 0): ?>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $application_id; ?>">
                            <h3>Add New Payment</h3>
                            
                            <div class="info-grid">
                                <div class="form-group">
                                    <label for="payment_amount">Payment Amount ($)</label>
                                    <input type="number" id="payment_amount" name="payment_amount" step="0.01" min="0.01" max="<?php echo $remaining_amount; ?>" value="<?php echo $remaining_amount; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="payment_method">Payment Method</label>
                                    <select id="payment_method" name="payment_method" required>
                                        <option value="Credit Card">Credit Card</option>
                                        <option value="Bank Transfer">Bank Transfer</option>
                                        <option value="Cash">Cash</option>
                                        <option value="PayPal">PayPal</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="payment_status">Payment Status</label>
                                    <select id="payment_status" name="payment_status" required>
                                        <option value="Completed">Completed</option>
                                        <option value="Pending">Pending</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="payment_notes">Payment Notes</label>
                                <textarea id="payment_notes" name="payment_notes" rows="3"></textarea>
                            </div>
                            
                            <input type="hidden" name="update_payment" value="1">
                            <button type="submit" class="submit-btn">Add Payment</button>
                        </form>
                        <?php else: ?>
                        <div style="text-align: center; padding: 20px; color: #38a169;">
                            <h3>✓ Payment Complete</h3>
                            <p>This application has been fully paid.</p>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Payment History -->
                        <div class="payment-history">
                            <h3>Payment History</h3>
                            <?php if ($payment_history->num_rows > 0): ?>
                                <?php while ($payment = $payment_history->fetch_assoc()): ?>
                                <div class="payment-item">
                                    <div class="payment-info">
                                        <div class="payment-amount">$<?php echo number_format($payment['amount'], 2); ?></div>
                                        <div class="payment-meta">
                                            <?php echo htmlspecialchars($payment['payment_method']); ?> • 
                                            <?php echo date('F d, Y', strtotime($payment['payment_date'])); ?>
                                            <?php if (!empty($payment['notes'])): ?>
                                                <br><small><?php echo htmlspecialchars($payment['notes']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php 
                                    $payment_status_class = '';
                                    switch($payment['status']) {
                                        case 'Completed':
                                            $payment_status_class = 'payment-completed';
                                            break;
                                        case 'Pending':
                                            $payment_status_class = 'payment-pending';
                                            break;
                                        case 'Failed':
                                            $payment_status_class = 'payment-failed';
                                            break;
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $payment_status_class; ?>">
                                        <?php echo $payment['status']; ?>
                                    </span>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p style="text-align: center; padding: 20px; color: #777;">No payment history available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Tab Content: Documents -->
                <div id="documents-tab" class="tab-content">
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
                                    <a href="uploads/<?php echo $doc['file_name']; ?>" class="document-link" target="_blank">View</a>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p style="text-align: center; padding: 20px; color: #777;">No documents uploaded for this application.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Tab Content: Status History -->
                <div id="history-tab" class="tab-content">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Status History</h2>
                        </div>
                        
                        <div class="history-list">
                            <?php if ($history->num_rows > 0): ?>
                                <?php while ($update = $history->fetch_assoc()): ?>
                                <div class="history-item">
                                    <div class="history-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <polyline points="12 6 12 12 16 14"></polyline>
                                        </svg>
                                    </div>
                                    <div class="history-content">
                                        <div class="history-text">
                                            Status changed from <strong><?php echo htmlspecialchars($update['old_status']); ?></strong> to <strong><?php echo htmlspecialchars($update['new_status']); ?></strong>
                                        </div>
                                        <div class="history-meta">
                                            Updated by <?php echo htmlspecialchars($update['employee_name']); ?> on <?php echo date('F d, Y \a\t h:i A', strtotime($update['update_date'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p style="text-align: center; padding: 20px; color: #777;">No status updates yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Tab switching functionality
        function switchTab(tabId) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });
            
            // Deactivate all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Activate the selected tab
            document.getElementById(tabId).classList.add('active');
            
            // Find and activate the tab button
            const clickedTab = Array.from(tabs).find(tab => {
                return tab.getAttribute('onclick').includes(tabId);
            });
            
            if (clickedTab) {
                clickedTab.classList.add('active');
            }
        }
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