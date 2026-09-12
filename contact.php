<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function respond(int $code, bool $success, string $message): never {
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, false, 'Please submit the enquiry form.');
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 20000) respond(413, false, 'Your enquiry is too large.');
session_start();
function field(string $key): string {
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}
if (field('website_check') !== '') respond(400, false, 'The enquiry could not be accepted.');
$name = field('name');
$email = field('email');
$service = field('service');
$space = field('space');
$message = field('message');
if (strlen($name) < 2 || strlen($name) > 100 || preg_match('/[\r\n]/', $name)) respond(422, false, 'Please enter a valid name.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || preg_match('/[\r\n]/', $email)) respond(422, false, 'Please enter a valid email address.');
if (!in_array($service, ['Household pests', 'Termite control', 'Rodent control', 'General enquiry'], true)) respond(422, false, 'Please select a service.');
if (!in_array($space, ['Home', 'Apartment', 'Commercial property', 'Other'], true)) respond(422, false, 'Please select a property type.');
if (strlen($message) < 10 || strlen($message) > 5000 || field('privacy_consent') !== '1') respond(422, false, 'Please complete your message and privacy consent.');
if (time() - (int)($_SESSION['last_enquiry'] ?? 0) < 45) respond(429, false, 'Please wait a moment before sending another enquiry.');
$configPath = __DIR__ . '/config/config.js';
if (!is_readable($configPath)) respond(503, false, 'The enquiry service is temporarily unavailable.');
$source = file_get_contents($configPath);
if ($source === false || !preg_match('/window\.SITE_CONFIG\s*=\s*(\{.*\})\s*;?\s*$/s', $source, $match)) respond(503, false, 'The enquiry service is temporarily unavailable.');
$config = json_decode($match[1], true);
if (!is_array($config)) respond(503, false, 'The enquiry service is temporarily unavailable.');
$recipient = $config['email'] ?? '';
if (!is_string($recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $recipient) || str_ends_with($recipient, '.example')) respond(503, false, 'The enquiry email is not configured yet.');
$subject = 'Website enquiry: ' . $service;
$body = "Name: $name\nEmail: $email\nService: $service\nSpace: $space\nPrivacy consent: yes\n\n$message";
$headers = ['From' => $recipient, 'Reply-To' => $email, 'Content-Type' => 'text/plain; charset=UTF-8'];
if (!function_exists('mail')) respond(503, false, 'The email service is not available.');
$sent = mail($recipient, $subject, $body, $headers);
if (!$sent) respond(502, false, 'Your message could not be sent. Please try again later.');
$_SESSION['last_enquiry'] = time();
respond(200, true, (string)($config['formSuccessMessage'] ?? 'Успешно отправлено'));
