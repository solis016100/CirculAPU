<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/smtp_config.php';

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email address format.']);
        exit();
    }

     
    $otp = rand(100000, 999999);
    $_SESSION['generated_otp'] = $otp;
    $_SESSION['otp_email'] = $email;

    $mail = new PHPMailer(true);

    try {
         
        configureCirculApuSmtp($mail);
        $mail->addAddress($email);

         
        $mail->isHTML(true);
        $mail->Subject = 'Your CirculAPU Sign-Up OTP Code';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; max-width: 500px;'>
                <h2>CirculAPU Verification Code</h2>
                <p>Use the following OTP code to complete your registration:</p>
                <h1 style='color: #2e7d32; letter-spacing: 4px;'>$otp</h1>
                <p>This code is valid for single use during registration.</p>
            </div>
        ";

        $mail->send();
        echo json_encode(['status' => 'success', 'message' => 'OTP sent to ' . $email]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Email could not be sent. Please try again later.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Email required.']);
}
?>
