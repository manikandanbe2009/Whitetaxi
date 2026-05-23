<?php

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request payload.']);
    exit;
}

// ── Sanitise inputs ──────────────────────────────────────────────────────────
$name           = trim((string) ($data['name']           ?? ''));
$mobile         = trim((string) ($data['mobile']         ?? ''));
$email          = trim((string) ($data['email']          ?? ''));
$pickup         = trim((string) ($data['pickup']         ?? ''));
$drop           = trim((string) ($data['drop']           ?? ''));
$date           = trim((string) ($data['date']           ?? ''));
$time           = trim((string) ($data['time']           ?? ''));
$vehicle        = trim((string) ($data['vehicle']        ?? ''));
$tripType       = trim((string) ($data['trip_type']      ?? ''));
$tripDays       = trim((string) ($data['trip_days']      ?? ''));
$distanceKm     = trim((string) ($data['distance_km']    ?? ''));
$baseFare       = trim((string) ($data['base_fare']      ?? ''));
$perKm          = trim((string) ($data['per_km']         ?? ''));
$distCharge     = trim((string) ($data['dist_charge']    ?? ''));
$driverAllowance= trim((string) ($data['driver_allowance']?? '0'));
$totalFare      = trim((string) ($data['total_fare']     ?? ''));

// Basic validation
if ($name === '' || $email === '' || $mobile === '' || $vehicle === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Required booking fields are missing.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid customer email address.']);
    exit;
}

// ── Business config ──────────────────────────────────────────────────────────
$businessName  = 'White Call Taxi';
$businessEmail = env_value('BUSINESS_EMAIL', 'info@whitecalltaxi.com');
$businessPhone = env_value('BUSINESS_PHONE', '+91 12345 67890');
$smtpFrom      = env_value('SMTP_FROM', $businessEmail);

$tripTypeLabel = $tripType === 'two-way'
    ? "Round Trip ({$tripDays} day" . ($tripDays != '1' ? 's' : '') . ')'
    : 'One Way';

// ── Build HTML email ─────────────────────────────────────────────────────────
$htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Booking Confirmation – {$businessName}</title>
</head>
<body style="margin:0;padding:0;background:#0f1428;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0f1428;padding:32px 16px;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background:#161d3a;border-radius:16px;overflow:hidden;border:1px solid rgba(255,255,255,0.08);">

          <!-- Header -->
          <tr>
            <td style="background:linear-gradient(135deg,#1a2550,#0f1428);padding:32px 40px;text-align:center;border-bottom:1px solid rgba(255,193,7,0.2);">
              <p style="margin:0 0 6px;font-size:13px;letter-spacing:3px;color:#ffc107;text-transform:uppercase;font-weight:700;">White Call Taxi</p>
              <h1 style="margin:0;font-size:26px;color:#fff;font-weight:700;">Booking Confirmed! 🎉</h1>
              <p style="margin:10px 0 0;color:rgba(255,255,255,0.55);font-size:14px;">Your ride has been requested. We'll call you shortly to confirm.</p>
            </td>
          </tr>

          <!-- Greeting -->
          <tr>
            <td style="padding:28px 40px 0;">
              <p style="margin:0;color:#fff;font-size:16px;">Hello <strong style="color:#ffc107;">{$name}</strong>,</p>
              <p style="margin:8px 0 0;color:rgba(255,255,255,0.6);font-size:14px;line-height:1.6;">
                Thank you for choosing {$businessName}. Here is a complete summary of your booking request.
              </p>
            </td>
          </tr>

          <!-- Vehicle badge -->
          <tr>
            <td style="padding:24px 40px 0;">
              <table width="100%" cellpadding="0" cellspacing="0" style="background:rgba(255,193,7,0.08);border:1px solid rgba(255,193,7,0.25);border-radius:12px;padding:16px 20px;">
                <tr>
                  <td>
                    <p style="margin:0;font-size:11px;letter-spacing:2px;color:rgba(255,255,255,0.4);text-transform:uppercase;">Selected Vehicle</p>
                    <p style="margin:6px 0 0;font-size:22px;font-weight:800;color:#ffc107;">{$vehicle}</p>
                    <p style="margin:4px 0 0;font-size:13px;color:rgba(255,255,255,0.5);">{$tripTypeLabel}</p>
                  </td>
                  <td align="right">
                    <p style="margin:0;font-size:11px;letter-spacing:2px;color:rgba(255,255,255,0.4);text-transform:uppercase;">Total Fare</p>
                    <p style="margin:6px 0 0;font-size:26px;font-weight:800;color:#fff;">Rs. {$totalFare}</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Passenger Details -->
          <tr>
            <td style="padding:24px 40px 0;">
              <p style="margin:0 0 12px;font-size:11px;letter-spacing:2px;color:rgba(255,255,255,0.35);text-transform:uppercase;font-weight:700;">Passenger Details</p>
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="50%" style="padding:0 8px 10px 0;">
                    <div style="background:rgba(255,255,255,0.04);border-radius:8px;padding:12px 14px;">
                      <p style="margin:0;font-size:10px;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:1px;">Name</p>
                      <p style="margin:4px 0 0;font-size:14px;color:#fff;font-weight:600;">{$name}</p>
                    </div>
                  </td>
                  <td width="50%" style="padding:0 0 10px 8px;">
                    <div style="background:rgba(255,255,255,0.04);border-radius:8px;padding:12px 14px;">
                      <p style="margin:0;font-size:10px;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:1px;">Mobile</p>
                      <p style="margin:4px 0 0;font-size:14px;color:#fff;font-weight:600;">{$mobile}</p>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td width="50%" style="padding:0 8px 0 0;">
                    <div style="background:rgba(255,255,255,0.04);border-radius:8px;padding:12px 14px;">
                      <p style="margin:0;font-size:10px;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:1px;">Date</p>
                      <p style="margin:4px 0 0;font-size:14px;color:#fff;font-weight:600;">{$date}</p>
                    </div>
                  </td>
                  <td width="50%" style="padding:0 0 0 8px;">
                    <div style="background:rgba(255,255,255,0.04);border-radius:8px;padding:12px 14px;">
                      <p style="margin:0;font-size:10px;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:1px;">Time</p>
                      <p style="margin:4px 0 0;font-size:14px;color:#fff;font-weight:600;">{$time}</p>
                    </div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Trip Details -->
          <tr>
            <td style="padding:20px 40px 0;">
              <p style="margin:0 0 12px;font-size:11px;letter-spacing:2px;color:rgba(255,255,255,0.35);text-transform:uppercase;font-weight:700;">Trip Details</p>
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="padding:0 0 10px 0;">
                    <div style="background:rgba(255,255,255,0.04);border-radius:8px;padding:12px 14px;">
                      <p style="margin:0;font-size:10px;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:1px;">Pickup Location</p>
                      <p style="margin:4px 0 0;font-size:14px;color:#fff;font-weight:600;">{$pickup}</p>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td style="padding:0 0 10px 0;">
                    <div style="background:rgba(255,255,255,0.04);border-radius:8px;padding:12px 14px;">
                      <p style="margin:0;font-size:10px;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:1px;">Drop Location</p>
                      <p style="margin:4px 0 0;font-size:14px;color:#fff;font-weight:600;">{$drop}</p>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td width="50%" style="padding:0 8px 0 0;">
                    <div style="background:rgba(255,255,255,0.04);border-radius:8px;padding:12px 14px;">
                      <p style="margin:0;font-size:10px;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:1px;">Distance</p>
                      <p style="margin:4px 0 0;font-size:14px;color:#fff;font-weight:600;">{$distanceKm} km</p>
                    </div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Fare Breakdown -->
          <tr>
            <td style="padding:20px 40px 0;">
              <p style="margin:0 0 12px;font-size:11px;letter-spacing:2px;color:rgba(255,255,255,0.35);text-transform:uppercase;font-weight:700;">Fare Breakdown</p>
              <table width="100%" cellpadding="0" cellspacing="0" style="background:rgba(255,255,255,0.03);border-radius:10px;overflow:hidden;">
                <tr style="border-bottom:1px solid rgba(255,255,255,0.06);">
                  <td style="padding:12px 16px;color:rgba(255,255,255,0.65);font-size:13px;">Base Fare</td>
                  <td align="right" style="padding:12px 16px;color:#fff;font-size:13px;font-weight:600;">Rs. {$baseFare}</td>
                </tr>
                <tr style="border-bottom:1px solid rgba(255,255,255,0.06);">
                  <td style="padding:12px 16px;color:rgba(255,255,255,0.65);font-size:13px;">Distance ({$distanceKm} km × Rs. {$perKm}/km)</td>
                  <td align="right" style="padding:12px 16px;color:#fff;font-size:13px;font-weight:600;">Rs. {$distCharge}</td>
                </tr>
HTML;

if ((float)$driverAllowance > 0) {
    $htmlBody .= <<<HTML
                <tr style="border-bottom:1px solid rgba(255,255,255,0.06);">
                  <td style="padding:12px 16px;color:rgba(255,255,255,0.65);font-size:13px;">Driver Allowance</td>
                  <td align="right" style="padding:12px 16px;color:#fff;font-size:13px;font-weight:600;">Rs. {$driverAllowance}</td>
                </tr>
HTML;
}

$htmlBody .= <<<HTML
                <tr style="background:rgba(255,193,7,0.1);">
                  <td style="padding:14px 16px;color:#ffc107;font-size:15px;font-weight:700;">Total Estimated Fare</td>
                  <td align="right" style="padding:14px 16px;color:#ffc107;font-size:18px;font-weight:800;">Rs. {$totalFare}</td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Contact -->
          <tr>
            <td style="padding:24px 40px;">
              <table width="100%" cellpadding="0" cellspacing="0" style="background:rgba(255,255,255,0.04);border-radius:10px;padding:16px 20px;">
                <tr>
                  <td>
                    <p style="margin:0;color:rgba(255,255,255,0.5);font-size:13px;">Need help? Contact us:</p>
                    <p style="margin:6px 0 0;color:#ffc107;font-size:15px;font-weight:700;">{$businessPhone}</p>
                    <p style="margin:2px 0 0;color:rgba(255,255,255,0.4);font-size:12px;">{$businessEmail}</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="padding:20px 40px 28px;text-align:center;border-top:1px solid rgba(255,255,255,0.06);">
              <p style="margin:0;color:rgba(255,255,255,0.25);font-size:12px;">© 2026 {$businessName}. Premium Reliable Safe.</p>
              <p style="margin:6px 0 0;color:rgba(255,255,255,0.2);font-size:11px;">This is an automated booking confirmation email.</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

// ── Plain-text fallback ───────────────────────────────────────────────────────
$textBody = <<<TEXT
WHITE CALL TAXI – Booking Confirmation
=======================================

Hello {$name},

Thank you for choosing White Call Taxi. Here is your booking summary:

SELECTED VEHICLE : {$vehicle}
TRIP TYPE        : {$tripTypeLabel}

PASSENGER DETAILS
-----------------
Name   : {$name}
Mobile : {$mobile}
Email  : {$email}
Date   : {$date}
Time   : {$time}

TRIP DETAILS
------------
Pickup   : {$pickup}
Drop     : {$drop}
Distance : {$distanceKm} km

FARE BREAKDOWN
--------------
Base Fare        : Rs. {$baseFare}
Distance Charge  : Rs. {$distCharge}  ({$distanceKm} km x Rs. {$perKm}/km)
Driver Allowance : Rs. {$driverAllowance}
TOTAL FARE       : Rs. {$totalFare}

For any queries call us at {$businessPhone} or email {$businessEmail}.

© 2026 White Call Taxi. Premium Reliable Safe.
TEXT;

// ── Send to customer ─────────────────────────────────────────────────────────
$customerSubject = "Your {$businessName} Booking – {$vehicle} on {$date}";
$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: {$businessName} <{$smtpFrom}>\r\n";
$headers .= "Reply-To: {$businessEmail}\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

$customerSent = mail($email, $customerSubject, $htmlBody, $headers);

// ── Send copy to business ────────────────────────────────────────────────────
$businessSubject = "New Booking Request – {$vehicle} | {$name} | {$date} {$time}";
$businessHeaders  = "MIME-Version: 1.0\r\n";
$businessHeaders .= "Content-Type: text/html; charset=UTF-8\r\n";
$businessHeaders .= "From: {$businessName} Bookings <{$smtpFrom}>\r\n";
$businessHeaders .= "Reply-To: {$email}\r\n";
$businessHeaders .= "X-Mailer: PHP/" . phpversion();

$businessSent = mail($businessEmail, $businessSubject, $htmlBody, $businessHeaders);

if (!$customerSent) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send confirmation email to customer. Please check your server mail configuration.']);
    exit;
}

echo json_encode([
    'success'       => true,
    'customer_sent' => $customerSent,
    'business_sent' => $businessSent,
    'message'       => 'Booking confirmation sent successfully.',
]);
