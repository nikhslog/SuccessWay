<?php
// student_payments.php
require_once 'config.php';

// Check if student is logged in
if (!isset($_SESSION['student_id']) || !$_SESSION['student_logged_in']) {
    header("Location: student_login.php");
    exit;
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

// Get default fee settings
$default_query = "SELECT * FROM fee_settings";
$default_result = $conn->query($default_query);
$default_fee_settings = [];

if ($default_result->num_rows > 0) {
    while ($fee = $default_result->fetch_assoc()) {
        $default_fee_settings[$fee['fee_type']] = $fee['amount'];
    }
}

// Get student's individual fee settings
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

// Calculate total fee
$total_fee = array_sum($student_fees);

// Get student's payment details
$payment_query = "SELECT 
                    (SELECT SUM(amount) FROM payments WHERE student_id = ? AND status = 'Completed') as total_paid,
                    (SELECT SUM(amount) FROM payments WHERE student_id = ? AND status = 'Completed' AND payment_type = 'Admission Fee') as admission_paid,
                    (SELECT SUM(amount) FROM payments WHERE student_id = ? AND status = 'Completed' AND payment_type = 'Agency Fee') as agency_paid,
                    (SELECT SUM(amount) FROM payments WHERE student_id = ? AND status = 'Completed' AND payment_type = 'Visa Processing Fee') as visa_paid";

$pay_stmt = $conn->prepare($payment_query);
$pay_stmt->bind_param("iiii", $student_id, $student_id, $student_id, $student_id);
$pay_stmt->execute();
$payment_result = $pay_stmt->get_result();
$payment_data = $payment_result->fetch_assoc();

$total_paid = $payment_data['total_paid'] ?: 0;
$admission_paid = $payment_data['admission_paid'] ?: 0;
$agency_paid = $payment_data['agency_paid'] ?: 0;
$visa_paid = $payment_data['visa_paid'] ?: 0;
$remaining = $total_fee - $total_paid;

// Get payment history
$history_query = "SELECT payment_id, amount, payment_date, payment_method, payment_type, notes, status 
                 FROM payments 
                 WHERE student_id = ? 
                 ORDER BY payment_date DESC";

$history_stmt = $conn->prepare($history_query);
$history_stmt->bind_param("i", $student_id);
$history_stmt->execute();
$payment_history = $history_stmt->get_result();

$pay_stmt->close();
$history_stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Payments - SuccessWay</title>
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
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .card-title {
            font-size: 20px;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
        }
        
        .payment-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-item {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 10px;
            flex: 1;
            min-width: 200px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .summary-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }
        
        .summary-value {
            font-size: 24px;
            font-weight: 600;
            color: #333;
        }
        
        .summary-value.paid {
            color: #38a169;
        }
        
        .summary-value.pending {
            color: #dd6b20;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 8px;
        }
        
        .status-complete {
            background-color: #c6f6d5;
            color: #38a169;
        }
        
        .status-pending {
            background-color: #ffeaa7;
            color: #d69e2e;
        }
        
        .status-partial {
            background-color: #fed7b2;
            color: #dd6b20;
        }
        
        .breakdown-container {
            margin-bottom: 30px;
        }
        
        .breakdown-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }
        
        .breakdown-item {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .breakdown-item:last-child {
            border-bottom: none;
        }
        
        .breakdown-label {
            display: flex;
            align-items: center;
            font-weight: 500;
        }
        
        .breakdown-amount {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .breakdown-paid {
            font-weight: 600;
        }
        
        .history-container {
            margin-top: 30px;
        }
        
        .history-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }
        
        .history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .history-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .history-date {
            font-weight: 500;
            color: #333;
        }
        
        .history-details {
            color: #666;
            font-size: 14px;
        }
        
        .history-amount {
            font-weight: 600;
            color: #38a169;
        }
        
        .payment-progress {
            margin: 30px 0;
        }
        
        .progress-container {
            width: 100%;
            height: 20px;
            background-color: #edf2f7;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .progress-bar {
            height: 100%;
            background-color: #40b3a2;
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        
        .progress-labels {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #666;
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
            .card {
                padding: 20px;
            }
            
            .payment-summary {
                flex-direction: column;
            }
            
            .summary-item {
                min-width: 100%;
            }
            
            .breakdown-item {
                flex-direction: column;
                gap: 10px;
            }
            
            .history-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .history-amount {
                align-self: flex-end;
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
            
            .progress-labels {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 5px;
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
                Bienvenue, 
                <span><?php echo htmlspecialchars($student_name); ?></span>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="student_dashboard.php" >Tableau de bord</a></li>
                <li><a href="student_new_application.php">Nouvelle candidature</a></li>
                <li><a href="student_payments.php" class="active">Mes paiements</a></li>
                <li><a href="student_profile.php">Mon profil</a></li>
            </ul>
            
            <div class="sidebar-footer">
                <a href="student_logout.php" class="logout-link">Logout</a>
            </div>
        </div>
        
        <!-- Main Content -->
<div class="main-content">
            <h1 class="page-title">Mes paiements</h1>
            
            <div class="payment-summary">
                <div class="summary-item">
                    <div class="summary-label"><i class="fas fa-money-bill-wave"></i> Frais totaux</div>
                    <div class="summary-value">$<?php echo number_format($total_fee, 2); ?></div>
                </div>
                
                <div class="summary-item">
                    <div class="summary-label"><i class="fas fa-check-circle"></i> Montant payé</div>
                    <div class="summary-value paid">$<?php echo number_format($total_paid, 2); ?></div>
                </div>
                
                <div class="summary-item">
                    <div class="summary-label"><i class="fas fa-hourglass-half"></i> Restant</div>
                    <div class="summary-value pending">$<?php echo number_format($remaining, 2); ?></div>
                </div>
                
                <div class="summary-item">
                    <div class="summary-label"><i class="fas fa-info-circle"></i> Statut du paiement</div>
                    <div class="summary-value">
                        <?php if ($remaining <= 0): ?>
                            <span>Complet</span>
                            <span class="status-badge status-complete">Payé</span>
                        <?php elseif ($total_paid > 0): ?>
                            <span>Partiel</span>
                            <span class="status-badge status-partial">Partiel</span>
                        <?php else: ?>
                            <span>En attente</span>
                            <span class="status-badge status-pending">En attente</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <h3 class="card-title"><i class="fas fa-chart-line"></i> Progression du paiement</h3>
                
                <div class="payment-progress">
                    <?php 
                    $progress_percentage = ($total_fee > 0) ? min(100, ($total_paid / $total_fee) * 100) : 0;
                    ?>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?php echo $progress_percentage; ?>%"></div>
                    </div>
                    <div class="progress-labels">
                        <span>$0</span>
                        <span><?php echo number_format($progress_percentage, 0); ?>% Complété</span>
                        <span>$<?php echo number_format($total_fee, 2); ?></span>
                    </div>
                </div>
                
                <div class="breakdown-container">
                    <h4 class="breakdown-title"><i class="fas fa-list-ul"></i> Détail des frais</h4>
                    
                    <div class="breakdown-item">
                        <div class="breakdown-label">
                            <i class="fas fa-graduation-cap"></i>&nbsp;Frais d'admission
                        </div>
                        <div class="breakdown-amount">
                            <div class="breakdown-paid">
                                $<?php echo number_format($admission_paid, 2); ?> / $<?php echo number_format(isset($student_fees['Admission Fee']) ? $student_fees['Admission Fee'] : 0, 2); ?>
                            </div>
                            <?php if ($admission_paid >= $student_fees['Admission Fee']): ?>
                                <span class="status-badge status-complete">Payé</span>
                            <?php elseif ($admission_paid > 0): ?>
                                <span class="status-badge status-partial">Partiel</span>
                            <?php else: ?>
                                <span class="status-badge status-pending">En attente</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="breakdown-item">
                        <div class="breakdown-label">
                            <i class="fas fa-building"></i>&nbsp;&nbsp;Frais d'agence
                        </div>
                        <div class="breakdown-amount">
                            <div class="breakdown-paid">
                                $<?php echo number_format($agency_paid, 2); ?> / $<?php echo number_format(isset($student_fees['Agency Fee']) ? $student_fees['Agency Fee'] : 0, 2); ?>
                            </div>
                            <?php if ($agency_paid >= $student_fees['Agency Fee']): ?>
                                <span class="status-badge status-complete">Payé</span>
                            <?php elseif ($agency_paid > 0): ?>
                                <span class="status-badge status-partial">Partiel</span>
                            <?php else: ?>
                                <span class="status-badge status-pending">En attente</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="breakdown-item">
                        <div class="breakdown-label">
                            <i class="fas fa-passport"></i>&nbsp;&nbsp;Frais de traitement de visa
                        </div>
                        <div class="breakdown-amount">
                            <div class="breakdown-paid">
                                $<?php echo number_format($visa_paid, 2); ?> / $<?php echo number_format(isset($student_fees['Visa Processing Fee']) ? $student_fees['Visa Processing Fee'] : 0, 2); ?>
                            </div>   
                            <?php if ($visa_paid >= $student_fees['Visa Processing Fee']): ?>
                                <span class="status-badge status-complete">Payé</span>
                            <?php elseif ($visa_paid > 0): ?>
                                <span class="status-badge status-partial">Partiel</span>
                            <?php else: ?>
                                <span class="status-badge status-pending">En attente</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <h3 class="card-title"><i class="fas fa-history"></i> Historique des paiements</h3>
                
                <div class="history-container">
                    <?php if ($payment_history->num_rows > 0): ?>
                        <?php while ($payment = $payment_history->fetch_assoc()): ?>
                            <div class="history-item">
                                <div class="history-info">
                                    <div class="history-date">
                                        <i class="far fa-calendar-alt"></i> <?php echo date('F j, Y', strtotime($payment['payment_date'])); ?>
                                    </div>
                                    <div class="history-details">
                                        <?php echo htmlspecialchars($payment['payment_type'] ?: 'Paiement général'); ?> - 
                                        <?php echo htmlspecialchars($payment['payment_method']); ?>
                                        <?php if ($payment['notes']): ?>
                                            <br><small><?php echo htmlspecialchars($payment['notes']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="history-amount">
                                    $<?php echo number_format($payment['amount'], 2); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>Aucun historique de paiement trouvé.</p>
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
        <div class="label">Traduire en anglais</div>
    </div>
    <script>
        document.getElementById('translationToggle').addEventListener('click', function() {
            window.location.href = 'student_payments_en.php';
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