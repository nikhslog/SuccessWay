<?php
// admin_finances.php
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['employee_id']) || !$_SESSION['employee_logged_in'] || $_SESSION['employee_role'] != 'Admin') {
    header("Location: employee_login.php");
    exit;
}

$admin_id = $_SESSION['employee_id'];
$admin_name = $_SESSION['employee_name'];
$search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$success_message = '';
$error_message = '';

// Get default fee settings
$fee_query = "SELECT * FROM fee_settings";
$fee_result = $conn->query($fee_query);
$default_fee_settings = [];

if ($fee_result->num_rows > 0) {
    while ($fee = $fee_result->fetch_assoc()) {
        $default_fee_settings[$fee['fee_type']] = $fee['amount'];
    }
} else {
    // Insert default fee values if they don't exist
    $insert_fees = "INSERT INTO fee_settings (fee_type, amount) VALUES 
                    ('Admission Fee', 500.00), 
                    ('Agency Fee', 500.00), 
                    ('Visa Processing Fee', 500.00)";
    $conn->query($insert_fees);
    
    $default_fee_settings = [
        'Admission Fee' => 500.00,
        'Agency Fee' => 500.00,
        'Visa Processing Fee' => 500.00
    ];
}

// Update default fee settings
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_default_fees'])) {
    $admission_fee = floatval($_POST['admission_fee']);
    $agency_fee = floatval($_POST['agency_fee']);
    $visa_fee = floatval($_POST['visa_fee']);
    
    // Validate fees
    if ($admission_fee < 0 || $agency_fee < 0 || $visa_fee < 0) {
        $error_message = "Fee amounts cannot be negative.";
    } else {
        // Update fee settings
        $update_admission = $conn->prepare("UPDATE fee_settings SET amount = ? WHERE fee_type = 'Admission Fee'");
        $update_admission->bind_param("d", $admission_fee);
        
        $update_agency = $conn->prepare("UPDATE fee_settings SET amount = ? WHERE fee_type = 'Agency Fee'");
        $update_agency->bind_param("d", $agency_fee);
        
        $update_visa = $conn->prepare("UPDATE fee_settings SET amount = ? WHERE fee_type = 'Visa Processing Fee'");
        $update_visa->bind_param("d", $visa_fee);
        
        if ($update_admission->execute() && $update_agency->execute() && $update_visa->execute()) {
            $success_message = "Default fee settings updated successfully!";
            // Update local fee settings array
            $default_fee_settings['Admission Fee'] = $admission_fee;
            $default_fee_settings['Agency Fee'] = $agency_fee;
            $default_fee_settings['Visa Processing Fee'] = $visa_fee;
        } else {
            $error_message = "Error updating fee settings: " . $conn->error;
        }
        
        $update_admission->close();
        $update_agency->close();
        $update_visa->close();
    }
}

// Update individual student fees
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_student_fees'])) {
    $student_id = intval($_POST['student_id']);
    $admission_fee = floatval($_POST['student_admission_fee']);
    $agency_fee = floatval($_POST['student_agency_fee']);
    $visa_fee = floatval($_POST['student_visa_fee']);
    
    // Validate fees
    if ($admission_fee < 0 || $agency_fee < 0 || $visa_fee < 0) {
        $error_message = "Fee amounts cannot be negative.";
    } else {
        $conn->begin_transaction();
        try {
            // Insert or update admission fee
            $stmt = $conn->prepare("INSERT INTO student_fees (student_id, fee_type, amount) 
                                    VALUES (?, 'Admission Fee', ?) 
                                    ON DUPLICATE KEY UPDATE amount = ?");
            $stmt->bind_param("idd", $student_id, $admission_fee, $admission_fee);
            $stmt->execute();
            
            // Insert or update agency fee
            $stmt = $conn->prepare("INSERT INTO student_fees (student_id, fee_type, amount) 
                                    VALUES (?, 'Agency Fee', ?) 
                                    ON DUPLICATE KEY UPDATE amount = ?");
            $stmt->bind_param("idd", $student_id, $agency_fee, $agency_fee);
            $stmt->execute();
            
            // Insert or update visa processing fee
            $stmt = $conn->prepare("INSERT INTO student_fees (student_id, fee_type, amount) 
                                    VALUES (?, 'Visa Processing Fee', ?) 
                                    ON DUPLICATE KEY UPDATE amount = ?");
            $stmt->bind_param("idd", $student_id, $visa_fee, $visa_fee);
            $stmt->execute();
            
            $conn->commit();
            $success_message = "Student fee settings updated successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error updating student fees: " . $e->getMessage();
        }
    }
}

// Handle payment submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_payment'])) {
    $student_id = sanitize_input($_POST['student_id']);
    $amount = floatval($_POST['amount']);
    $payment_type = sanitize_input($_POST['payment_type']);
    $payment_date = sanitize_input($_POST['payment_date']);
    $payment_method = sanitize_input($_POST['payment_method']);
    $notes = sanitize_input($_POST['notes']);
    $application_id = !empty($_POST['application_id']) ? sanitize_input($_POST['application_id']) : null;
    
    // Validate amount
    if ($amount <= 0) {
        $error_message = "Payment amount must be greater than zero.";
    } else {
        // Insert payment
        $stmt = $conn->prepare("INSERT INTO payments (student_id, application_id, amount, payment_type, payment_date, payment_method, status, notes) 
                                VALUES (?, ?, ?, ?, ?, ?, 'Completed', ?)");
        
        if ($application_id) {
            $stmt->bind_param("iidssss", $student_id, $application_id, $amount, $payment_type, $payment_date, $payment_method, $notes);
        } else {
            $application_id = null;
            $stmt->bind_param("iidssss", $student_id, $application_id, $amount, $payment_type, $payment_date, $payment_method, $notes);
        }
        
        if ($stmt->execute()) {
            $success_message = "Payment of $" . number_format($amount, 2) . " for " . $payment_type . " recorded successfully!";
        } else {
            $error_message = "Error recording payment: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle payment deletion
if (isset($_GET['delete_payment']) && is_numeric($_GET['delete_payment'])) {
    $payment_id = intval($_GET['delete_payment']);
    
    // First get the payment details to show in the success message
    $payment_query = $conn->prepare("SELECT amount, payment_type FROM payments WHERE payment_id = ?");
    $payment_query->bind_param("i", $payment_id);
    $payment_query->execute();
    $payment_result = $payment_query->get_result();
    
    if ($payment_result->num_rows > 0) {
        $payment_data = $payment_result->fetch_assoc();
        $amount = $payment_data['amount'];
        $payment_type = $payment_data['payment_type'];
        
        // Now delete the payment
        $delete_stmt = $conn->prepare("DELETE FROM payments WHERE payment_id = ?");
        $delete_stmt->bind_param("i", $payment_id);
        
        if ($delete_stmt->execute()) {
            $success_message = "Payment of $" . number_format($amount, 2) . " for " . $payment_type . " deleted successfully!";
        } else {
            $error_message = "Error deleting payment: " . $conn->error;
        }
        $delete_stmt->close();
    } else {
        $error_message = "Payment not found.";
    }
    $payment_query->close();
}

// Get students with payment details based on search query
$students_query = "SELECT s.*,
                    (SELECT SUM(amount) FROM payments WHERE student_id = s.student_id AND status = 'Completed') as total_paid,
                    (SELECT SUM(amount) FROM payments WHERE student_id = s.student_id AND status = 'Completed' AND payment_type = 'Admission Fee') as admission_paid,
                    (SELECT SUM(amount) FROM payments WHERE student_id = s.student_id AND status = 'Completed' AND payment_type = 'Agency Fee') as agency_paid,
                    (SELECT SUM(amount) FROM payments WHERE student_id = s.student_id AND status = 'Completed' AND payment_type = 'Visa Processing Fee') as visa_paid
                FROM students s 
                WHERE s.full_name LIKE ? OR s.email LIKE ?
                ORDER BY s.full_name";

$stmt = $conn->prepare($students_query);
$search_param = "%" . $search_query . "%";
$stmt->bind_param("ss", $search_param, $search_param);
$stmt->execute();
$students_result = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Management - SuccessWay</title>
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
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .search-container {
            margin-bottom: 30px;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 15px 20px;
            border: 1px solid #ddd;
            border-radius: 30px;
            font-size: 16px;
            transition: all 0.3s;
            box-sizing: border-box;
        }
        
        .search-input:focus {
            border-color: #40b3a2;
            outline: none;
            box-shadow: 0 0 0 2px rgba(64, 179, 162, 0.2);
        }
        
        .search-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .filter-options {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            background-color: #f5f8f9;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .filter-btn:hover, .filter-btn.active {
            background-color: #40b3a2;
            color: white;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th, table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        table th {
            font-weight: 600;
            color: #333;
            background-color: #f9f9f9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-complete {
            background-color: #c6f6d5;
            color: #38a169;
        }
        
        .status-pending {
            background-color: #ffeaa7;
            color: #d69e2e;
        }
        
        .action-btn {
            background-color: #40b3a2;
            color: white;
            border: none;
            border-radius: 20px;
            padding: 8px 15px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            background-color: #368f82;
        }
        
        .delete-btn {
            background-color: #e53e3e;
            color: white;
            border: none;
            border-radius: 20px;
            padding: 5px 10px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 5px;
        }
        
        .delete-btn:hover {
            background-color: #c53030;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            width: 80%;
            max-width: 800px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .close-btn {
            position: absolute;
            top: 20px;
            right: 30px;
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close-btn:hover {
            color: #555;
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
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        
        input:focus,
        select:focus,
        textarea:focus {
            border-color: #40b3a2;
            outline: none;
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
            margin-top: 10px;
            transition: background-color 0.3s;
        }
        
        .submit-btn:hover {
            background-color: #368f82;
        }
        
        .student-details {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .student-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .student-email {
            color: #666;
            margin-bottom: 15px;
        }
        
        .payment-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .payment-item {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            flex: 1;
            min-width: 200px;
        }
        
        .payment-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .payment-value {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .payment-value.complete {
            color: #38a169;
        }
        
        .payment-value.pending {
            color: #dd6b20;
        }
        
        .payment-history {
            margin-top: 30px;
        }
        
        .payment-history h3 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 18px;
            color: #333;
        }
        
        .history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .history-date {
            color: #666;
        }
        
        .history-amount {
            font-weight: 600;
        }
        
        .history-actions {
            display: flex;
            gap: 10px;
        }
        
        .no-results {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .confirmation-dialog {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 200;
            align-items: center;
            justify-content: center;
        }
        
        .confirmation-box {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            text-align: center;
        }
        
        .confirmation-title {
            font-size: 20px;
            margin-bottom: 20px;
            color: #333;
        }
        
        .confirmation-text {
            margin-bottom: 30px;
            color: #555;
        }
        
        .confirmation-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
        }
        
        .confirm-btn {
            background-color: #e53e3e;
            color: white;
            border: none;
            border-radius: 20px;
            padding: 10px 20px;
            font-size: 14px;
            cursor: pointer;
        }
        
        .cancel-btn {
            background-color: #ccc;
            color: #333;
            border: none;
            border-radius: 20px;
            padding: 10px 20px;
            font-size: 14px;
            cursor: pointer;
        }
        
        .fee-settings {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .fee-type {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
        }
        
        .fee-input {
            display: flex;
            align-items: center;
        }
        
        .fee-input .currency-symbol {
            font-size: 18px;
            margin-right: 5px;
            color: #333;
        }
        
        .fee-input input {
            width: 100%;
        }
        
        .payment-breakdown {
            margin-top: 15px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        
        .breakdown-title {
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .breakdown-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .breakdown-label {
            color: #666;
        }
        
        .breakdown-value {
            font-weight: 500;
        }
        
        /* Student fee settings section */
        .student-fee-settings {
            border-top: 1px solid #eee;
            margin-top: 20px;
            padding-top: 20px;
        }
        
        .fee-section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            background-color: #f5f8f9;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .tab.active {
            background-color: #40b3a2;
            color: white;
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
            
            .filter-options {
                flex-wrap: wrap;
            }
            
            .modal-content {
                width: 90%;
                margin: 10% auto;
                padding: 20px;
            }
            
            .payment-summary {
                flex-direction: column;
                gap: 10px;
            }
            
            .fee-settings {
                grid-template-columns: 1fr;
            }
            
            .table-responsive {
                overflow-x: auto;
            }
            
            table {
                min-width: 700px;
            }
            
            .tabs {
                flex-wrap: wrap;
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
            
            .confirmation-box {
                width: 85%;
                padding: 20px;
            }
            
            .confirmation-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .submit-btn {
                width: 100%;
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
                <li><a href="admin_finances.php" class="active">Finances</a></li>
                <li><a href="admin_reports.php">Reports</a></li>
            </ul>
            
            <div class="sidebar-footer">
                <a href="employee_logout.php" class="logout-link">Logout</a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <h1 class="page-title">Financial Management</h1>
            
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
            
            <!-- Default Fee Settings -->
            <div class="card">
                <h2>Default Fee Settings</h2>
                <p>These default fee values will be used as a template when creating fees for new students.</p>
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="fee-settings">
                        <div class="fee-type">
                            <label for="admission_fee">Admission Fee</label>
                            <div class="fee-input">
                                <span class="currency-symbol">$</span>
                                <input type="number" id="admission_fee" name="admission_fee" min="0" step="0.01" value="<?php echo $default_fee_settings['Admission Fee']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="fee-type">
                            <label for="agency_fee">Agency Fee</label>
                            <div class="fee-input">
                                <span class="currency-symbol">$</span>
                                <input type="number" id="agency_fee" name="agency_fee" min="0" step="0.01" value="<?php echo $default_fee_settings['Agency Fee']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="fee-type">
                            <label for="visa_fee">Visa Processing Fee</label>
                            <div class="fee-input">
                                <span class="currency-symbol">$</span>
                                <input type="number" id="visa_fee" name="visa_fee" min="0" step="0.01" value="<?php echo $default_fee_settings['Visa Processing Fee']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="fee-type">
                            <label>Total Default Fee</label>
                            <div class="fee-input">
                                <span class="currency-symbol">$</span>
                                <input type="text" id="total_fee" value="<?php echo number_format(array_sum($default_fee_settings), 2); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="update_default_fees" class="submit-btn">Update Default Fee Settings</button>
                </form>
            </div>
            
            <!-- Search Bar -->
            <div class="search-container">
                <input type="text" id="student-search" class="search-input" placeholder="Search students by name or email..." value="<?php echo htmlspecialchars($search_query); ?>">
                <div class="search-icon">
                    <i class="fas fa-search"></i>
                </div>
            </div>
            
            <!-- Filter Options -->
            <div class="filter-options">
                <button class="filter-btn active" data-filter="all">All Students</button>
                <button class="filter-btn" data-filter="pending">Pending Payments</button>
                <button class="filter-btn" data-filter="partial">Partial Payments</button>
                <button class="filter-btn" data-filter="complete">Complete Payments</button>
            </div>
            
            <!-- Students List -->
            <div class="card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Total Fee</th>
                                <th>Amount Paid</th>
                                <th>Remaining</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="students-table">
                            <?php if ($students_result->num_rows > 0): ?>
                                <?php while ($student = $students_result->fetch_assoc()): 
                                    // Get student's individual fee settings
                                    $student_id = $student['student_id'];
                                    $fee_query = "SELECT fee_type, amount FROM student_fees WHERE student_id = ?";
                                    $fee_stmt = $conn->prepare($fee_query);
                                    $fee_stmt->bind_param("i", $student_id);
                                    $fee_stmt->execute();
                                    $fee_result = $fee_stmt->get_result();
                                    
                                    $student_fees = [];
                                    if ($fee_result->num_rows > 0) {
                                        while ($fee = $fee_result->fetch_assoc()) {
                                            $student_fees[$fee['fee_type']] = $fee['amount'];
                                        }
                                    } else {
                                        // If no individual fees set, use default fees
                                        $student_fees = $default_fee_settings;
                                    }
                                    
                                    $fee_stmt->close();
                                    
                                    $total_fee = array_sum($student_fees);
                                    $total_paid = $student['total_paid'] ?: 0;
                                    $admission_paid = $student['admission_paid'] ?: 0;
                                    $agency_paid = $student['agency_paid'] ?: 0;
                                    $visa_paid = $student['visa_paid'] ?: 0;
                                    $remaining = $total_fee - $total_paid;
                                    $is_complete = $remaining <= 0;
                                    $payment_status = $is_complete ? 'Complete' : 'Pending';
                                    $status_class = $is_complete ? 'status-complete' : 'status-pending';
                                    $filter_class = $is_complete ? 'complete' : ($total_paid > 0 ? 'partial' : 'pending');
                                ?>
                                <tr class="student-row" data-filter="<?php echo $filter_class; ?>" data-id="<?php echo $student['student_id']; ?>">
                                    <td>
                                        <div class="student-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                        <div class="student-email"><?php echo htmlspecialchars($student['email']); ?></div>
                                    </td>
                                    <td>$<?php echo number_format($total_fee, 2); ?></td>
                                    <td>$<?php echo number_format($total_paid, 2); ?></td>
                                    <td>$<?php echo number_format($remaining, 2); ?></td>
                                    <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $payment_status; ?></span></td>
                                    <td>
                                        <button class="action-btn view-payments-btn" 
                                                data-id="<?php echo $student['student_id']; ?>" 
                                                data-name="<?php echo htmlspecialchars($student['full_name']); ?>"
                                                data-email="<?php echo htmlspecialchars($student['email']); ?>"
                                                data-paid="<?php echo $total_paid; ?>"
                                                data-admission="<?php echo $admission_paid; ?>"
                                                data-agency="<?php echo $agency_paid; ?>"
                                                data-visa="<?php echo $visa_paid; ?>"
                                                data-fee="<?php echo $total_fee; ?>"
                                                data-admission-fee="<?php echo isset($student_fees['Admission Fee']) ? $student_fees['Admission Fee'] : $default_fee_settings['Admission Fee']; ?>"
                                                data-agency-fee="<?php echo isset($student_fees['Agency Fee']) ? $student_fees['Agency Fee'] : $default_fee_settings['Agency Fee']; ?>"
                                                data-visa-fee="<?php echo isset($student_fees['Visa Processing Fee']) ? $student_fees['Visa Processing Fee'] : $default_fee_settings['Visa Processing Fee']; ?>"
                                                data-remaining="<?php echo $remaining; ?>">
                                            <i class="fas fa-money-bill-wave"></i> View & Update
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="no-results">
                                        <?php if ($search_query): ?>
                                            No students found matching "<?php echo htmlspecialchars($search_query); ?>".
                                        <?php else: ?>
                                            No students found in the system.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Payment Modal -->
    <div id="payment-modal" class="modal">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            
            <div class="student-details">
                <div class="student-name" id="modal-student-name">John Doe</div>
                <div class="student-email" id="modal-student-email">john@example.com</div>
                
                <div class="tabs">
                    <div class="tab active" data-tab="payment-info">Payment Information</div>
                    <div class="tab" data-tab="fee-settings">Fee Settings</div>
                </div>
                
                <div id="payment-info-tab" class="tab-content">
                    <div class="payment-summary">
                        <div class="payment-item">
                            <div class="payment-label">Total Fee</div>
                            <div class="payment-value" id="modal-total-fee">$1,500.00</div>
                        </div>
                        
                        <div class="payment-item">
                            <div class="payment-label">Amount Paid</div>
                            <div class="payment-value" id="modal-amount-paid">$500.00</div>
                        </div>
                        
                        <div class="payment-item">
                            <div class="payment-label">Remaining</div>
                            <div class="payment-value" id="modal-remaining">$1,000.00</div>
                        </div>
                        
                        <div class="payment-item">
                            <div class="payment-label">Status</div>
                            <div class="payment-value" id="modal-status">Pending</div>
                        </div>
                    </div>
                    
                    <div class="payment-breakdown">
                        <div class="breakdown-title">Payment Breakdown</div>
                        
                        <div class="breakdown-item">
                            <div class="breakdown-label">Admission Fee (<span id="modal-admission-fee">$500.00</span>)</div>
                            <div class="breakdown-value">
                                <span id="modal-admission-paid">$0.00</span>
                                <span id="modal-admission-status" class="status-badge status-pending">Pending</span>
                            </div>
                        </div>
                        
                        <div class="breakdown-item">
                            <div class="breakdown-label">Agency Fee (<span id="modal-agency-fee">$500.00</span>)</div>
                            <div class="breakdown-value">
                                <span id="modal-agency-paid">$0.00</span>
                                <span id="modal-agency-status" class="status-badge status-pending">Pending</span>
                            </div>
                        </div>
                        
                        <div class="breakdown-item">
                            <div class="breakdown-label">Visa Processing Fee (<span id="modal-visa-fee">$500.00</span>)</div>
                            <div class="breakdown-value">
                                <span id="modal-visa-paid">$0.00</span>
                                <span id="modal-visa-status" class="status-badge status-pending">Pending</span>
                            </div>
                        </div>
                    </div>
                    
                    <h3>Add New Payment</h3>
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <input type="hidden" id="student_id" name="student_id" value="">
                        <input type="hidden" id="application_id" name="application_id" value="">
                        
                        <div class="form-group">
                            <label for="payment_type">Payment Type</label>
                            <select id="payment_type" name="payment_type" required>
                                <option value="">Select Payment Type</option>
                                <option value="Admission Fee">Admission Fee</option>
                                <option value="Agency Fee">Agency Fee</option>
                                <option value="Visa Processing Fee">Visa Processing Fee</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="amount">Payment Amount ($)</label>
                            <input type="number" id="amount" name="amount" min="1" step="0.01" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_date">Payment Date</label>
                            <input type="date" id="payment_date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_method">Payment Method</label>
                            <select id="payment_method" name="payment_method" required>
                                <option value="Cash">Cash</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Check">Check</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes (Optional)</label>
                            <textarea id="notes" name="notes" rows="3"></textarea>
                        </div>
                        
                        <button type="submit" name="add_payment" class="submit-btn">Record Payment</button>
                    </form>
                    
                    <div class="payment-history">
                        <h3>Payment History</h3>
                        <div id="payment-history-list">
                            <!-- Payment history will be loaded via AJAX -->
                            <div class="history-item">
                                <div class="history-date">Loading payment history...</div>
                                <div class="history-amount"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="fee-settings-tab" class="tab-content" style="display: none;">
                    <h3>Individual Fee Settings</h3>
                    <p>Set custom fee values for this student. These will override the default fee settings.</p>
                    
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <input type="hidden" id="student_id_for_fees" name="student_id" value="">
                        
                        <div class="fee-settings">
                            <div class="fee-type">
                                <label for="student_admission_fee">Admission Fee</label>
                                <div class="fee-input">
                                    <span class="currency-symbol">$</span>
                                    <input type="number" id="student_admission_fee" name="student_admission_fee" min="0" step="0.01" required>
                                </div>
                            </div>
                            
                            <div class="fee-type">
                                <label for="student_agency_fee">Agency Fee</label>
                                <div class="fee-input">
                                    <span class="currency-symbol">$</span>
                                    <input type="number" id="student_agency_fee" name="student_agency_fee" min="0" step="0.01" required>
                                </div>
                            </div>
                            
                            <div class="fee-type">
                                <label for="student_visa_fee">Visa Processing Fee</label>
                                <div class="fee-input">
                                    <span class="currency-symbol">$</span>
                                    <input type="number" id="student_visa_fee" name="student_visa_fee" min="0" step="0.01" required>
                                </div>
                            </div>
                            
                            <div class="fee-type">
                                <label>Total Fee</label>
                                <div class="fee-input">
                                    <span class="currency-symbol">$</span>
                                    <input type="text" id="student_total_fee" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_student_fees" class="submit-btn">Update Student Fees</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Confirmation Dialog -->
    <div id="confirmation-dialog" class="confirmation-dialog">
        <div class="confirmation-box">
            <div class="confirmation-title">Delete Payment?</div>
            <div class="confirmation-text">Are you sure you want to delete this payment? This action cannot be undone.</div>
            <div class="confirmation-buttons">
                <button id="confirm-delete" class="confirm-btn">Delete</button>
                <button id="cancel-delete" class="cancel-btn">Cancel</button>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Fee calculation for the default settings form
            const admissionFeeInput = document.getElementById('admission_fee');
            const agencyFeeInput = document.getElementById('agency_fee');
            const visaFeeInput = document.getElementById('visa_fee');
            const totalFeeInput = document.getElementById('total_fee');
            
            function updateDefaultTotalFee() {
                const admissionFee = parseFloat(admissionFeeInput.value) || 0;
                const agencyFee = parseFloat(agencyFeeInput.value) || 0;
                const visaFee = parseFloat(visaFeeInput.value) || 0;
                
                const total = admissionFee + agencyFee + visaFee;
                totalFeeInput.value = total.toFixed(2);
            }
            
            admissionFeeInput.addEventListener('input', updateDefaultTotalFee);
            agencyFeeInput.addEventListener('input', updateDefaultTotalFee);
            visaFeeInput.addEventListener('input', updateDefaultTotalFee);
            
            // Fee calculation for individual student settings
            const studentAdmissionFeeInput = document.getElementById('student_admission_fee');
            const studentAgencyFeeInput = document.getElementById('student_agency_fee');
            const studentVisaFeeInput = document.getElementById('student_visa_fee');
            const studentTotalFeeInput = document.getElementById('student_total_fee');
            
            function updateStudentTotalFee() {
                const admissionFee = parseFloat(studentAdmissionFeeInput.value) || 0;
                const agencyFee = parseFloat(studentAgencyFeeInput.value) || 0;
                const visaFee = parseFloat(studentVisaFeeInput.value) || 0;
                
                const total = admissionFee + agencyFee + visaFee;
                studentTotalFeeInput.value = total.toFixed(2);
            }
            
            studentAdmissionFeeInput.addEventListener('input', updateStudentTotalFee);
            studentAgencyFeeInput.addEventListener('input', updateStudentTotalFee);
            studentVisaFeeInput.addEventListener('input', updateStudentTotalFee);
            
            // Pre-fill amount based on selected payment type
            const paymentTypeSelect = document.getElementById('payment_type');
            const amountInput = document.getElementById('amount');
            
            paymentTypeSelect.addEventListener('change', function() {
                const selectedType = this.value;
                
                if (selectedType === 'Admission Fee') {
                    const feeAmount = parseFloat(document.getElementById('modal-admission-fee').textContent.replace('$', ''));
                    const paidAmount = parseFloat(document.getElementById('modal-admission-paid').textContent.replace('$', ''));
                    const remainingAmount = feeAmount - paidAmount;
                    
                    if (remainingAmount > 0) {
                        amountInput.value = remainingAmount.toFixed(2);
                    }
                } else if (selectedType === 'Agency Fee') {
                    const feeAmount = parseFloat(document.getElementById('modal-agency-fee').textContent.replace('$', ''));
                    const paidAmount = parseFloat(document.getElementById('modal-agency-paid').textContent.replace('$', ''));
                    const remainingAmount = feeAmount - paidAmount;
                    
                    if (remainingAmount > 0) {
                        amountInput.value = remainingAmount.toFixed(2);
                    }
                } else if (selectedType === 'Visa Processing Fee') {
                    const feeAmount = parseFloat(document.getElementById('modal-visa-fee').textContent.replace('$', ''));
                    const paidAmount = parseFloat(document.getElementById('modal-visa-paid').textContent.replace('$', ''));
                    const remainingAmount = feeAmount - paidAmount;
                    
                    if (remainingAmount > 0) {
                        amountInput.value = remainingAmount.toFixed(2);
                    }
                } else {
                    amountInput.value = '';
                }
            });
            
            // Tab switching
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    // Remove active class from all tabs
                    tabs.forEach(t => t.classList.remove('active'));
                    // Add active class to current tab
                    this.classList.add('active');
                    
                    // Hide all tab contents
                    tabContents.forEach(content => {
                        content.style.display = 'none';
                    });
                    
                    // Show the selected tab content
                    document.getElementById(tabId + '-tab').style.display = 'block';
                });
            });
            
            // Real-time search function
            const searchInput = document.getElementById('student-search');
            
            searchInput.addEventListener('input', function() {
                const searchValue = this.value.toLowerCase();
                
                // Redirect with search query parameter
                if (searchValue) {
                    window.location.href = 'admin_finances.php?search=' + encodeURIComponent(searchValue);
                } else {
                    window.location.href = 'admin_finances.php';
                }
            });
            
            // Filter buttons
            const filterButtons = document.querySelectorAll('.filter-btn');
            const studentRows = document.querySelectorAll('.student-row');
            
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const filter = this.getAttribute('data-filter');
                    
                    // Toggle active state
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Filter student rows
                    studentRows.forEach(row => {
                        if (filter === 'all' || row.getAttribute('data-filter') === filter) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            });
            
            // Payment modal
            const modal = document.getElementById('payment-modal');
            const closeBtn = document.querySelector('.close-btn');
            const viewButtons = document.querySelectorAll('.view-payments-btn');
            
            // Open modal when view button is clicked
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const studentId = this.getAttribute('data-id');
                    const studentName = this.getAttribute('data-name');
                    const studentEmail = this.getAttribute('data-email');
                    const totalPaid = parseFloat(this.getAttribute('data-paid'));
                    const admissionPaid = parseFloat(this.getAttribute('data-admission')) || 0;
                    const agencyPaid = parseFloat(this.getAttribute('data-agency')) || 0;
                    const visaPaid = parseFloat(this.getAttribute('data-visa')) || 0;
                    const totalFee = parseFloat(this.getAttribute('data-fee'));
                    const admissionFee = parseFloat(this.getAttribute('data-admission-fee'));
                    const agencyFee = parseFloat(this.getAttribute('data-agency-fee'));
                    const visaFee = parseFloat(this.getAttribute('data-visa-fee'));
                    const remaining = parseFloat(this.getAttribute('data-remaining'));
                    
                    // Update modal content
                    document.getElementById('modal-student-name').textContent = studentName;
                    document.getElementById('modal-student-email').textContent = studentEmail;
                    document.getElementById('modal-total-fee').textContent = '$' + totalFee.toFixed(2);
                    document.getElementById('modal-amount-paid').textContent = '$' + totalPaid.toFixed(2);
                    document.getElementById('modal-remaining').textContent = '$' + remaining.toFixed(2);
                    
                    // Update fee values in modal
                    document.getElementById('modal-admission-fee').textContent = '$' + admissionFee.toFixed(2);
                    document.getElementById('modal-agency-fee').textContent = '$' + agencyFee.toFixed(2);
                    document.getElementById('modal-visa-fee').textContent = '$' + visaFee.toFixed(2);
                    
                    // Update breakdown details
                    document.getElementById('modal-admission-paid').textContent = '$' + admissionPaid.toFixed(2);
                    document.getElementById('modal-agency-paid').textContent = '$' + agencyPaid.toFixed(2);
                    document.getElementById('modal-visa-paid').textContent = '$' + visaPaid.toFixed(2);
                    
                    // Set admission status
                    const admissionStatus = document.getElementById('modal-admission-status');
                    if (admissionPaid >= admissionFee) {
                        admissionStatus.textContent = 'Paid';
                        admissionStatus.className = 'status-badge status-complete';
                    } else if (admissionPaid > 0) {
                        admissionStatus.textContent = 'Partial';
                        admissionStatus.className = 'status-badge status-pending';
                    } else {
                        admissionStatus.textContent = 'Pending';
                        admissionStatus.className = 'status-badge status-pending';
                    }
                    
                    // Set agency status
                    const agencyStatus = document.getElementById('modal-agency-status');
                    if (agencyPaid >= agencyFee) {
                        agencyStatus.textContent = 'Paid';
                        agencyStatus.className = 'status-badge status-complete';
                    } else if (agencyPaid > 0) {
                        agencyStatus.textContent = 'Partial';
                        agencyStatus.className = 'status-badge status-pending';
                    } else {
                        agencyStatus.textContent = 'Pending';
                        agencyStatus.className = 'status-badge status-pending';
                    }
                    
                    // Set visa status
                    const visaStatus = document.getElementById('modal-visa-status');
                    if (visaPaid >= visaFee) {
                        visaStatus.textContent = 'Paid';
                        visaStatus.className = 'status-badge status-complete';
                    } else if (visaPaid > 0) {
                        visaStatus.textContent = 'Partial';
                        visaStatus.className = 'status-badge status-pending';
                    } else {
                        visaStatus.textContent = 'Pending';
                        visaStatus.className = 'status-badge status-pending';
                    }
                    
                    const statusElement = document.getElementById('modal-status');
                    if (remaining <= 0) {
                        statusElement.textContent = 'Complete';
                        statusElement.classList.add('complete');
                        statusElement.classList.remove('pending');
                    } else {
                        statusElement.textContent = 'Pending';
                        statusElement.classList.add('pending');
                        statusElement.classList.remove('complete');
                    }
                    
                    // Set form values
                    document.getElementById('student_id').value = studentId;
                    document.getElementById('student_id_for_fees').value = studentId;
                    
                    // Set individual fee settings fields
                    document.getElementById('student_admission_fee').value = admissionFee.toFixed(2);
                    document.getElementById('student_agency_fee').value = agencyFee.toFixed(2);
                    document.getElementById('student_visa_fee').value = visaFee.toFixed(2);
                    document.getElementById('student_total_fee').value = totalFee.toFixed(2);
                    
                    // Load payment history
                    loadPaymentHistory(studentId);
                    
                    // Show the modal
                    modal.style.display = 'block';
                });
            });
            
            // Close modal when X is clicked
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
            
            // Close modal when clicking outside the modal content
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
            
            // Confirmation dialog
            const confirmationDialog = document.getElementById('confirmation-dialog');
            const confirmDeleteBtn = document.getElementById('confirm-delete');
            const cancelDeleteBtn = document.getElementById('cancel-delete');
            let deletePaymentId = null;
            
            // Show confirmation dialog when delete button is clicked
            function setupDeleteButtons() {
                document.querySelectorAll('.delete-payment-btn').forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        deletePaymentId = this.getAttribute('data-id');
                        confirmationDialog.style.display = 'flex';
                    });
                });
            }
            
            // Confirm delete
            confirmDeleteBtn.addEventListener('click', function() {
                if (deletePaymentId) {
                    window.location.href = 'admin_finances.php?delete_payment=' + deletePaymentId;
                }
                confirmationDialog.style.display = 'none';
            });
            
            // Cancel delete
            cancelDeleteBtn.addEventListener('click', function() {
                confirmationDialog.style.display = 'none';
                deletePaymentId = null;
            });
            
            // Function to load payment history
            function loadPaymentHistory(studentId) {
                // In a real implementation, this would make an AJAX request
                const xhr = new XMLHttpRequest();
                xhr.open('GET', 'get_payment_history.php?student_id=' + studentId, true);
                
                xhr.onload = function() {
                    if (this.status === 200) {
                        try {
                            const payments = JSON.parse(this.responseText);
                            displayPaymentHistory(payments);
                            setupDeleteButtons(); // Setup delete buttons after loading payments
                        } catch (e) {
                            document.getElementById('payment-history-list').innerHTML = `
                                <div class="history-item">
                                    <div class="history-date">Error loading payment history. Please try again.</div>
                                    <div class="history-amount"></div>
                                </div>
                            `;
                        }
                    } else {
                        document.getElementById('payment-history-list').innerHTML = `
                            <div class="history-item">
                                <div class="history-date">Error loading payment history. Please try again.</div>
                                <div class="history-amount"></div>
                            </div>
                        `;
                    }
                };
                
                xhr.onerror = function() {
                    document.getElementById('payment-history-list').innerHTML = `
                        <div class="history-item">
                            <div class="history-date">Error loading payment history. Please try again.</div>
                            <div class="history-amount"></div>
                        </div>
                    `;
                };
                
                xhr.send();
            }
            
            // Function to display payment history
            function displayPaymentHistory(payments) {
                const historyContainer = document.getElementById('payment-history-list');
                
                if (payments.length === 0) {
                    historyContainer.innerHTML = `
                        <div class="history-item">
                            <div class="history-date">No payment records found.</div>
                            <div class="history-amount"></div>
                        </div>
                    `;
                    return;
                }
                
                let html = '';
                payments.forEach(payment => {
                    const date = new Date(payment.payment_date);
                    const formattedDate = date.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric' 
                    });
                    
                    html += `
                        <div class="history-item">
                            <div class="history-info">
                                <div class="history-date">${formattedDate} - ${payment.payment_method}</div>
                                <div class="history-type">${payment.payment_type || 'General Payment'}</div>
                                <div class="history-notes">${payment.notes || ''}</div>
                            </div>
                            <div class="history-amount">$${parseFloat(payment.amount).toFixed(2)}</div>
                            <div class="history-actions">
                                <a href="#" class="delete-payment-btn" data-id="${payment.payment_id}">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    `;
                });
                
                historyContainer.innerHTML = html;
            }
            
            // Responsive sidebar toggle
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