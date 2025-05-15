<?php
ob_start();
// Set headers to return JSON response
header('Content-Type: application/json');

// Collect form data
$name = isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '';
$email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : '';
$phone = isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '';
$message = isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '';
$date = isset($_POST['date']) ? htmlspecialchars($_POST['date']) : '';
$time = isset($_POST['time']) ? htmlspecialchars($_POST['time']) : '';

// Validate form data
if (empty($name) || empty($email) || empty($phone) || empty($date) || empty($time)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email format']);
    exit;
}

// Set recipient email (you can change this later)
$to = "info@successwaymali.com"; // Leave blank for now, add company email later

// Set email subject
$subject = "New Booking: {$name} on {$date} at {$time}";

// Compose email message
$email_message = "
<html>
<head>
    <title>New Booking Notification</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        h2 { color: #40b3a2; }
        .booking-details { background-color: #f9f9f9; padding: 15px; border-radius: 5px; }
        .detail-row { margin-bottom: 10px; }
        .label { font-weight: bold; }
    </style>
</head>
<body>
    <div class='container'>
        <h2>New Booking Notification</h2>
        <p>A new booking has been made through the SuccessWay website.</p>
        
        <div class='booking-details'>
            <div class='detail-row'><span class='label'>Name:</span> {$name}</div>
            <div class='detail-row'><span class='label'>Email:</span> {$email}</div>
            <div class='detail-row'><span class='label'>Phone:</span> {$phone}</div>
            <div class='detail-row'><span class='label'>Date:</span> {$date}</div>
            <div class='detail-row'><span class='label'>Time:</span> {$time}</div>
            <div class='detail-row'><span class='label'>Message:</span> {$message}</div>
        </div>
        
        <p>Please contact the client to confirm their appointment.</p>
    </div>
</body>
</html>
";

// Set email headers
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= "From: SuccessWay Booking <noreply@successway.com>" . "\r\n";
$headers .= "Reply-To: {$email}" . "\r\n";

// For testing/development: log instead of sending email when recipient is empty
if (empty($to)) {
    // Create a log file with the booking information
    $log_file = 'booking_logs.txt';
    $log_message = "New Booking: {$date} at {$time}\n";
    $log_message .= "Name: {$name}\n";
    $log_message .= "Email: {$email}\n";
    $log_message .= "Phone: {$phone}\n";
    $log_message .= "Message: {$message}\n";
    $log_message .= "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
    
    file_put_contents($log_file, $log_message, FILE_APPEND);
    
    echo json_encode(['success' => true]);
    exit;
}

// Send email
$mail_sent = mail($to, $subject, $email_message, $headers);

if ($mail_sent) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to send email']);
}
?>