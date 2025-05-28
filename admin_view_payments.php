<?php
// admin_view_payments.php
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['employee_id']) || !$_SESSION['employee_logged_in'] || $_SESSION['employee_role'] != 'Admin') {
    header("Location: employee_login.php");
    exit;
}

$admin_id = $_SESSION['employee_id'];
$admin_name = $_SESSION['employee_name'];

// Check if application ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_applications.php");
    exit;
}

$application_id = $_GET['id'];

// Get application details
$app_query = "SELECT a.*, s.full_name as student_name, s.email as student_email, s.student_id 
             FROM applications a 
             JOIN students s ON a.student_id = s.student_id 
             WHERE a.application_id = ?";
$app_stmt = $conn->prepare($app_query);
$app_stmt->bind_param("i", $application_id);
$app_stmt->execute();
$app_result = $app_stmt->get_result();

if ($app_result->num_rows === 0) {
    // Application not found
    header("Location: admin_applications.php");
    exit;
}

$application = $app_result->fetch_assoc();
$student_id = $application['student_id'];

// Handle payment form submission
$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_payment'])) {
    $amount = sanitize_input($_POST['amount']);
    $payment_type = sanitize_input($_POST['payment_type']);
    $payment_method = sanitize_input($_POST['payment_method']);
    $status = sanitize_input($_POST['status']);
    $notes = sanitize_input($_POST['notes']);
    
    // Insert payment record
    $stmt = $conn->prepare("INSERT INTO payments (student_id, application_id, amount, payment_type, payment_method, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iidsss", $student_id, $application_id, $amount, $payment_type, $payment_method, $status, $notes);
    
    if ($stmt->execute()) {
        $success_message = "Payment record added successfully!";
    } else {
        $error_message = "Error adding payment: " . $conn->error;
    }
    
    $stmt->close();
}

// Get fees for this country
$fees_query = "SELECT fee_type, amount FROM fees WHERE destination_country = ?";
$fees_stmt = $conn->prepare($fees_query);
$fees_stmt->bind_param("s", $application['destination_country']);
$fees_stmt->execute();
$fees_result = $fees_stmt->get_result();

$fees = [];
$total_fees = 0;

while ($fee = $fees_result->fetch_assoc()) {
    $fees[$fee['fee_type']] = $fee['amount'];
    $total_fees += $fee['amount'];
}

// Get payments for this application
$payments_query = "SELECT * FROM payments WHERE application_id = ? ORDER BY payment_date DESC";
$payments_stmt = $conn->prepare($payments_query);
$payments_stmt->bind_param("i", $application_id);
$payments_stmt->execute();
$payments_result = $payments_stmt->get_result();

$payments = [];
$total_paid = [
    'Admission Fee' => 0,
    'Agency Fee' => 0,
    'Visa Processing Fee' => 0,
    'Other' => 0,
    'total' => 0
];

while ($payment = $payments_result->fetch_assoc()) {
    $payments[] = $payment;
    $total_paid[$payment['payment_type']] += $payment['amount'];
    $total_paid['total'] += $payment['amount'];
}

$app_stmt->close();
$fees_stmt->close();
$payments_stmt->close();

// Get fee types for dropdown
$fee_types_query = "SELECT DISTINCT fee_type FROM fees";
$fee_types_result = $conn->query($fee_types_query);
$fee_types = [];

while ($row = $fee_types_result->fetch_assoc()) {
    $fee_types[] = $row['fee_type'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Payments - SuccessWay Admin</title>
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
        }
        
        .back-link:hover {
            text-decoration: underline;
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
        
        .application-info {
            margin-bottom: 30px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 15px;
        }
        
        .info-label {
            width: 150px;
            font-weight: 600;
            color: #555;
        }
        
        .info-value {
            flex: 1;
        }
        
        .fee-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .fee-card {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            min-width: 200px;
            flex: 1;
        }
        
        .fee-title {
            font-size: 16px;
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
        }
        
        .fee-amount {
            font-size: 24px;
            font-weight: 700;
            color: #40b3a2;
        }
        
        .payment-progress {
            height: 8px;
            background-color: #eee;
            border-radius: 4px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background-color: #40b3a2;
            border-radius: 4px;
        }
        
        .payment-status {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            font-size: 12px;
            color: #777;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th, table td {
            padding: 12px 15px;
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
        
        .status-pending {
            background-color: #ffeaa7;
            color: #d69e2e;
        }
        
        .status-completed {
            background-color: #c6f6d5;
            color: #38a169;
        }
        
        .status-failed {
            background-color: #fed7d7;
            color: #e53e3e;
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
        
        select, input[type="text"], input[type="number"], input[type="email"], textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        
        select:focus, input:focus, textarea:focus {
            border-color: #40b3a2;
            outline: none;
        }
        
        textarea {
            min-height: 100px;
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
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
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
            
            .fee-summary {
                flex-direction: column;
            }
            
            .form-row {
                flex-direction: column;
                gap: 15px;
            }
            
            .logout-link {
                position: static;
                margin: 20px;
            }
            
            .info-row {
                flex-direction: column;
            }
            
            .info-label {
                width: 100%;
                margin-bottom: 5px;
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
                <li><a href="admin_payments.php" class="active">Payments</a></li>
                <li><a href="admin_reports.php">Reports</a></li>
            </ul>
            
            <a href="employee_logout.php" class="logout-link">Logout</a>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <a href="admin_applications.php" class="back-link">← Back to Applications</a>
            
            <h1 class="page-title">Application Payments</h1>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <!-- Application Info -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Application Details</h2>
                </div>
                
                <div class="application-info">
                    <div class="info-row">
                        <div class="info-label">Student</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['student_name']); ?> (<?php echo htmlspecialchars($application['student_email']); ?>)</div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">University</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['university_name']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Program</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['program']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Country</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['destination_country']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Status</div>
                        <div class="info-value"><?php echo htmlspecialchars($application['status']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Submission Date</div>
                        <div class="info-value"><?php echo date('F d, Y', strtotime($application['submission_date'])); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Fee Summary -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Payment Summary</h2>
                </div>
                
                <div class="fee-summary">
                    <?php
                    $fee_types = ['Admission Fee', 'Agency Fee', 'Visa Processing Fee'];
                    foreach ($fee_types as $fee_type):
                        $fee_amount = isset($fees[$fee_type]) ? $fees[$fee_type] : 0;
                        $paid_amount = isset($total_paid[$fee_type]) ? $total_paid[$fee_type] : 0;
                        $progress = $fee_amount > 0 ? ($paid_amount / $fee_amount) * 100 : 0;
                    ?>
                    <div class="fee-card">
                        <div class="fee-title"><?php echo $fee_type; ?></div>
                        <div class="fee-amount">$<?php echo number_format($fee_amount, 2); ?></div>
                        <div class="payment-progress">
                            <div class="progress-bar" style="width: <?php echo min(100, $progress); ?>%;"></div>
                        </div>
                        <div class="payment-status">
                            <span>Paid: $<?php echo number_format($paid_amount, 2); ?></span>
                            <span>Remaining: $<?php echo number_format(max(0, $fee_amount - $paid_amount), 2); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Total -->
                    <div class="fee-card" style="background-color: #f0f9f7;">
                        <div class="fee-title">Total</div>
                        <div class="fee-amount">$<?php echo number_format($total_fees, 2); ?></div>
                        <div class="payment-progress">
                            <div class="progress-bar" style="width: <?php echo min(100, (($total_paid['total'] / max(1, $total_fees)) * 100)); ?>%;"></div>
                        </div>
                        <div class="payment-status">
                            <span>Paid: $<?php echo number_format($total_paid['total'], 2); ?></span>
                            <span>Remaining: $<?php echo number_format(max(0, $total_fees - $total_paid['total']), 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Add Payment -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Add Payment</h2>
                </div>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $application_id); ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="amount">Amount ($)</label>
                            <input type="number" id="amount" name="amount" step="0.01" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_type">Payment Type</label>
                            <select id="payment_type" name="payment_type" required>
                                <option value="">Select Type</option>
                                <?php foreach ($fee_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>">
                                        <?php echo htmlspecialchars($type); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_method">Payment Method</label>
                            <select id="payment_method" name="payment_method" required>
                                <option value="">Select Method</option>
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="PayPal">PayPal</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" required>
                                <option value="Completed">Completed</option>
                                <option value="Pending">Pending</option>
                                <option value="Failed">Failed</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes"></textarea>
                    </div>
                    
                    <button type="submit" name="add_payment" class="submit-btn">Add Payment</button>
                </form>
            </div>
            
            <!-- Payment History -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Payment History</h2>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($payments) > 0): ?>
                                <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_type']); ?></td>
                                    <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($payment['status']); ?>">
                                            <?php echo $payment['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['notes']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">No payment records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
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