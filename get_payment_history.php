<?php
// get_payment_history.php
require_once 'config.php';

// Check if employee is logged in
if (!isset($_SESSION['employee_id']) || !$_SESSION['employee_logged_in']) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Check if student ID is provided
if (!isset($_GET['student_id']) || !is_numeric($_GET['student_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid student ID']);
    exit;
}

$student_id = intval($_GET['student_id']);

// Get payment history for the student
$query = "SELECT payment_id, amount, payment_date, payment_method, payment_type, notes, status 
          FROM payments 
          WHERE student_id = ? 
          ORDER BY payment_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$payments = [];
while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
}

$stmt->close();
$conn->close();

// Return payment history as JSON
header('Content-Type: application/json');
echo json_encode($payments);