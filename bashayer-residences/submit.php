<?php
/**
 * Realtor Landing — lead handler for "Bashayer Residences".
 * Drop this file in the same folder as index.html on your web host (PHP required).
 *
 * It does three things when the contact form is submitted:
 *   1. Emails your team via Resend (server-side, key stays private).
 *   2. Sends the visitor an autoresponder.
 *   3. Appends the lead to leads.csv  AND  (optionally) posts it to your CRM hub.
 *
 * Configure the four constants below, then you are live.
 */

const RESEND_API_KEY = "re_24gytzuC_G2aH7n3X9Sq6JsPbHHs8Z1Kd";                 // your Resend API key
const FROM_EMAIL     = "leads@dubaioffplanguide.com";                 // Resend-verified sender
const NOTIFY_EMAILS  = "interworkingdesign@gmail.com";              // comma-separated recipients
const CRM_WEBHOOK    = "";                // optional: https://your-hub/api/lead  ("" to disable)
const PROJECT_NAME   = "Bashayer Residences";

header('Content-Type: application/json');
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) { $data = $_POST; }

$name    = trim($data['name']    ?? '');
$email   = trim($data['email']   ?? '');
$phone   = trim($data['phone']   ?? '');
$message = trim($data['message'] ?? '');

if ($name === '' || $email === '') {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Name and email are required.']);
  exit;
}

// --- 1. append to local CSV backup ---
$row = [date('c'), PROJECT_NAME, $name, $email, $phone, str_replace(["\n","\r"], ' ', $message)];
$fh = fopen(__DIR__ . '/leads.csv', 'a');
if ($fh) { fputcsv($fh, $row); fclose($fh); }

// --- helper: send an email through Resend ---
function resend_send($to, $subject, $text) {
  if (RESEND_API_KEY === '' ) return;
  $payload = json_encode([
    'from' => 'Realtor Landing <' . FROM_EMAIL . '>',
    'to'   => is_array($to) ? $to : [$to],
    'subject' => $subject,
    'text' => $text,
  ]);
  $ch = curl_init('https://api.resend.com/emails');
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . RESEND_API_KEY, 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 15,
  ]);
  curl_exec($ch); curl_close($ch);
}

// --- 2. notify the team ---
if (NOTIFY_EMAILS !== '') {
  $to = array_map('trim', explode(',', NOTIFY_EMAILS));
  $body = "New lead for " . PROJECT_NAME . "\n\nName: $name\nEmail: $email\nPhone: $phone\nMessage: $message\n";
  resend_send($to, "New lead: " . PROJECT_NAME . " — $name", $body);
}

// --- 3. autoresponder to the visitor ---
resend_send($email, "Thanks for your interest in " . PROJECT_NAME,
  "Hi $name,\n\nThank you for your interest in " . PROJECT_NAME .
  ". One of our advisors will contact you shortly with full details, pricing and availability.\n\nBest regards");

// --- 4. optional: forward to central CRM hub ---
if (CRM_WEBHOOK !== '') {
  $ch = curl_init(CRM_WEBHOOK);
  curl_setopt_array($ch, [
    CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($data + ['project_name' => PROJECT_NAME]),
  ]);
  curl_exec($ch); curl_close($ch);
}

echo json_encode(['ok' => true]);
