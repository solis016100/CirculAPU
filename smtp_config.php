<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

function configureCirculApuSmtp(PHPMailer $mail): void
{
    $username = getenv('CIRCULAPU_SMTP_USERNAME');
    $password = getenv('CIRCULAPU_SMTP_PASSWORD');

    if ($username === false || $username === '' || $password === false || $password === '') {
        error_log('CirculAPU SMTP credentials are missing from the server environment.');
        throw new Exception('SMTP credentials are not configured.');
    }

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $username;
    $mail->Password = $password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->setFrom($username, 'CirculAPU');
}
