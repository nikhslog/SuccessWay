<?php
// Set headers to return JSON response
header('Content-Type: application/json');

// Collect form data
$name = isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '';
$email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : '';
$subject = isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : '';
$message = isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '';

// Set recipient email (company email)
$to = "info@successwaymali.com";

// Set email subject
$email_subject = "Contact Form Submission: {$subject}";

// Compose email body
$email_body = "
<html>
<head>
    <title>New Contact Form Submission</title>
</head>
<body>
    <h2>New Contact Form Submission</h2>
    <p><strong>Name:</strong> {$name}</p>
    <p><strong>Email:</strong> {$email}</p>
    <p><strong>Subject:</strong> {$subject}</p>
    <p><strong>Message:</strong> {$message}</p>
    <p><strong>Submitted:</strong> " . date('Y-m-d H:i:s') . "</p>
</body>
</html>
";

// Set email headers - make them identical to your booking script headers
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= "From: SuccessWay <noreply@successway.com>" . "\r\n";
$headers .= "Reply-To: {$email}" . "\r\n";

// Create a log file (for debugging)
$log_file = 'contact_logs.txt';
$log_message = "New Contact: " . date('Y-m-d H:i:s') . "\n";
$log_message .= "To: {$to}\n";
$log_message .= "Name: {$name}\n";
$log_message .= "Email: {$email}\n";
$log_message .= "Subject: {$subject}\n";
$log_message .= "Message: {$message}\n\n";
file_put_contents($log_file, $log_message, FILE_APPEND);

// Send email
$mail_sent = mail($to, $email_subject, $email_body, $headers);

// Always return success (since the booking script works this way)
echo json_encode(['success' => true, 'mailSent' => $mail_sent ? 'yes' : 'no']);
?>