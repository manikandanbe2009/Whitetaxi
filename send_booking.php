<?php
// Buffer ALL output — catches any PHP warnings/notices from config.php or elsewhere
// so they never corrupt the JSON response
ob_start();

require_once __DIR__ . '/config.php';

// Discard any output (warnings/notices) produced by require_once above
ob_clean();

// From this point, ONLY our JSON will be sent
header('Content-Type: application/json');

// Suppress PHP warnings from appearing in output — log them instead
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request payload.']);
    exit;
}

// ── Sanitise inputs ──────────────────────────────────────────────────────────
$name            = trim((string) ($data['name']             ?? ''));
$mobile          = trim((string) ($data['mobile']           ?? ''));
$email           = trim((string) ($data['email']            ?? ''));
$pickup          = trim((string) ($data['pickup']           ?? ''));
$drop            = trim((string) ($data['drop']             ?? ''));
$date            = trim((string) ($data['date']             ?? ''));
$time            = trim((string) ($data['time']             ?? ''));
$vehicle         = trim((string) ($data['vehicle']          ?? ''));
$tripType        = trim((string) ($data['trip_type']        ?? ''));
$tripDays        = trim((string) ($data['trip_days']        ?? ''));
$distanceKm      = trim((string) ($data['distance_km']      ?? ''));
$baseFare        = trim((string) ($data['base_fare']        ?? ''));
$perKm           = trim((string) ($data['per_km']           ?? ''));
$distCharge      = trim((string) ($data['dist_charge']      ?? ''));
$driverAllowance = trim((string) ($data['driver_allowance'] ?? '0'));
$totalFare       = trim((string) ($data['total_fare']       ?? ''));

// ── Validate required fields ─────────────────────────────────────────────────
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

// ── HTML email body ──────────────────────────────────────────────────────────
$htmlBody = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Booking Confirmation</title>
</head>
<body style="margin:0;padding:0;background:#0f1428;font-family:Segoe UI,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0f1428;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#161d3a;border-radius:16px;overflow:hidden;border:1px solid rgba(255,255,255,0.08);">

  <tr>
    <td style="background:linear-gradient(135deg,#1a2550,#0f1428);padding:32px 40px;text-align:center;border-bottom:1px solid rgba(255,193,7,0.2);">
      <p style="margin:0 0 6px;font-size:13px;letter-spacing:3px;color:#ffc107;text-transform:uppercase;font-weight:700;">White Call Taxi</p>
      <h1 style="margin:0;font-size:26px;color:#fff;font-weight:700;">Booking Confirmed!</h1>
      <p style="margin:10px 0 0;color:rgba(255,255,255,0.55);font-size:14px;">Your ride has been requested. We will call you shortly to confirm.</p>
    </td>
  </tr>

  <tr>
    <td style="padding:28px 40px 0;">
      <p style="margin:0;color:#fff;font-size:16px;">Hello <strong style="color:#ffc107;">' . htmlspecialchars($name, ENT_QUOTES) . '</strong>,</p>
      <p style="margin:8px 0 0;color:rgba(255,255,255,0.6);font-size:14px;line-height:1.6;">Thank you for choosing White Call Taxi. Here is your booking summary.</p>
    </td>
  </tr>

  <tr>
    <td style="padding:24px 40px 0;">
      <table width="100%" cellpadding="16" cellspacing="0" style="background:rgba(255,193,7,0.08);border:1px solid rgba(255,193,7,0.25);border-radius:12px;">
        <tr>
          <td>
            <p style="margin:0;font-size:11px;letter-spacing:2px;color:rgba(255,255,255,0.4);text-transform:uppercase;">Selected Vehicle</p>
            <p style="margin:6px 0 0;font-size:22px;font-weight:800;color:#ffc107;">' . htmlspecialchars($vehicle, ENT_QUOTES) . '</p>
            <p style="margin:4px 0 0;font-size:13px;color:rgba(255,255,255,0.5);">' . htmlspecialchars($tripTypeLabel, ENT_QUOTES) . '</p>
          </td>
          <td align="right">
            <p style="margin:0;font-size:11px;letter-spacing:2px;color:rgba(255,255,255,0.4);text-transform:uppercase;">Total Fare</p>
            <p style="margin:6px 0 0;font-size:26px;font-weight:800;color:#fff;">Rs. ' . htmlspecialchars($totalFare, ENT_QUOTES) . '</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <tr>
    <td style="padding:24px 40px 0;">
      <p style="margin:0 0 12px;font-size:11px;letter-spacing:2px;color:rgba(255,255,255,0.35);text-transform:uppercase;font-weight:700;">Passenger Details</p>
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td width="50%" style="padding:0 6px 10px 0;">
            <div style="background:rgba(255,255,255,0.04);border-radius:8px;padding:12px 14px;">
              <p style="margin:0;font-size:10px;color:rgba(255,255,255,0.4);text-transform:uppercase;">Name</p>
              <p style="margin:4px 0 0;font-size:14px;color:#fff;font-weight:600;">' . htmlspecialchars($name, ENT_QUOTES) . '</p>
            </div>
          </td>
          <td width="50%" style="padding:0 0 10px 6px;">
            <div style="background:rgba(255,255,255,0.04);border-radius:8px;padding:12px 14px;">
              <p style="margin:0;font-size:10px;color:rgba(255,255,255,0.4);text-transform:uppercase;">Mobile</p>
              <p style="margin:4px 0 0;font-size:14px;color:#fff;font-weight:600;">' . htmlspecialchars($mobile, ENT_QUOTES) . '</p>
            </div>
          </td>
        </tr>
        <tr>
          <td width="50%" style="padding:0 6px 0 0;">
            <div style="background:rgba(255,255,255,0.04);border-radius:8px;padding:12px 14px;">
              <p style="margin:0;font-size:10px;color:rgba(255,255,255,0.4);text-transform:uppercase;">Date</p>
              <p style="margin:4px 0 0;font-size:14px;color:#fff;font-weight:600;">' . htmlspecialchars($date, ENT_QUOTES) . '</p>
            </div>
          </td>
          <td width="50%" style="padding:0 0 0 6px;">
            <div style="background:rgba(255,255,255,0.04);border-radius:8px;padding:12px 14px;">
              <p style="margin:0;font-size:10px;color:rgba(255,255,255,0.4);text-transform:uppercase;">Time</p>
              <p style="margin:4px 0 0;font-size:14px;color:#fff;font-weight:600;">' . htmlspecialchars($time, ENT_QUOTES) . '</p>
            </div>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <tr>
    <td style="padding:20px 40px 0;">
      <p style="margin:0 0 12px;font-size:11px;letter-spacing:2px;color:rgba(255,255,255,0.35);text-transform:uppercase;font-weight:700;">Trip Details</p>
      <div style="background:rgba(255,255,255,0.04);border-radius:8px;padding:12px 14px;margin-bottom:8px;">
        <p style="margin:0;font-size:10px;color:rgba(255,255,255,0.4);text-transform:uppercase;">Pickup Location</p>
        <p style="margin:4px 0 0;font-size:14px;color:#fff;font-weight:600;">' . htmlspecialchars($pickup, ENT_QUOTES) . '</p>
      </div>
      <div style="background:rgba(255,255,255,0.04);border-radius:8px;padding:12px 14px;margin-bottom:8px;">
        <p style="margin:0;font-size:10px;color:rgba(255,255,255,0.4);text-transform:uppercase;">Drop Location</p>
        <p style="margin:4px 0 0;font-size:14px;color:#fff;font-weight:600;">' . htmlspecialchars($drop, ENT_QUOTES) . '</p>
      </div>
      <div style="background:rgba(255,255,255,0.04);border-radius:8px;padding:12px 14px;display:inline-block;">
        <p style="margin:0;font-size:10px;color:rgba(255,255,255,0.4);text-transform:uppercase;">Distance</p>
        <p style="margin:4px 0 0;font-size:14px;color:#fff;font-weight:600;">' . htmlspecialchars($distanceKm, ENT_QUOTES) . ' km</p>
      </div>
    </td>
  </tr>

  <tr>
    <td style="padding:20px 40px 0;">
      <p style="margin:0 0 12px;font-size:11px;letter-spacing:2px;color:rgba(255,255,255,0.35);text-transform:uppercase;font-weight:700;">Fare Breakdown</p>
      <table width="100%" cellpadding="0" cellspacing="0" style="background:rgba(255,255,255,0.03);border-radius:10px;overflow:hidden;">
        <tr>
          <td style="padding:12px 16px;color:rgba(255,255,255,0.65);font-size:13px;border-bottom:1px solid rgba(255,255,255,0.06);">Base Fare</td>
          <td align="right" style="padding:12px 16px;color:#fff;font-size:13px;font-weight:600;border-bottom:1px solid rgba(255,255,255,0.06);">Rs. ' . htmlspecialchars($baseFare, ENT_QUOTES) . '</td>
        </tr>
        <tr>
          <td style="padding:12px 16px;color:rgba(255,255,255,0.65);font-size:13px;border-bottom:1px solid rgba(255,255,255,0.06);">Distance (' . htmlspecialchars($distanceKm, ENT_QUOTES) . ' km x Rs.' . htmlspecialchars($perKm, ENT_QUOTES) . '/km)</td>
          <td align="right" style="padding:12px 16px;color:#fff;font-size:13px;font-weight:600;border-bottom:1px solid rgba(255,255,255,0.06);">Rs. ' . htmlspecialchars($distCharge, ENT_QUOTES) . '</td>
        </tr>';

if ((float) $driverAllowance > 0) {
    $htmlBody .= '
        <tr>
          <td style="padding:12px 16px;color:rgba(255,255,255,0.65);font-size:13px;border-bottom:1px solid rgba(255,255,255,0.06);">Driver Allowance</td>
          <td align="right" style="padding:12px 16px;color:#fff;font-size:13px;font-weight:600;border-bottom:1px solid rgba(255,255,255,0.06);">Rs. ' . htmlspecialchars($driverAllowance, ENT_QUOTES) . '</td>
        </tr>';
}

$htmlBody .= '
        <tr style="background:rgba(255,193,7,0.1);">
          <td style="padding:14px 16px;color:#ffc107;font-size:15px;font-weight:700;">Total Estimated Fare</td>
          <td align="right" style="padding:14px 16px;color:#ffc107;font-size:18px;font-weight:800;">Rs. ' . htmlspecialchars($totalFare, ENT_QUOTES) . '</td>
        </tr>
      </table>
    </td>
  </tr>

  <tr>
    <td style="padding:24px 40px;">
      <table width="100%" cellpadding="16" cellspacing="0" style="background:rgba(255,255,255,0.04);border-radius:10px;">
        <tr>
          <td>
            <p style="margin:0;color:rgba(255,255,255,0.5);font-size:13px;">Need help? Contact us:</p>
            <p style="margin:6px 0 0;color:#ffc107;font-size:15px;font-weight:700;">' . htmlspecialchars($businessPhone, ENT_QUOTES) . '</p>
            <p style="margin:2px 0 0;color:rgba(255,255,255,0.4);font-size:12px;">' . htmlspecialchars($businessEmail, ENT_QUOTES) . '</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <tr>
    <td style="padding:20px 40px 28px;text-align:center;border-top:1px solid rgba(255,255,255,0.06);">
      <p style="margin:0;color:rgba(255,255,255,0.25);font-size:12px;">2026 ' . htmlspecialchars($businessName, ENT_QUOTES) . '. Premium Reliable Safe.</p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';

// ── Send to customer ─────────────────────────────────────────────────────────
$subject = "Your {$businessName} Booking - {$vehicle} on {$date}";
$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: {$businessName} <{$smtpFrom}>\r\n";
$headers .= "Reply-To: {$businessEmail}\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

$customerSent = @mail($email, $subject, $htmlBody, $headers);

// ── Send copy to business ────────────────────────────────────────────────────
$bizSubject  = "New Booking - {$vehicle} | {$name} | {$date} {$time}";
$bizHeaders  = "MIME-Version: 1.0\r\n";
$bizHeaders .= "Content-Type: text/html; charset=UTF-8\r\n";
$bizHeaders .= "From: {$businessName} Bookings <{$smtpFrom}>\r\n";
$bizHeaders .= "Reply-To: {$email}\r\n";
$bizHeaders .= "X-Mailer: PHP/" . phpversion() . "\r\n";

$businessSent = @mail($businessEmail, $bizSubject, $htmlBody, $bizHeaders);

// ── Always return clean JSON ─────────────────────────────────────────────────
ob_clean(); // discard any stray output before final response
echo json_encode([
    'success'       => true,
    'customer_sent' => (bool) $customerSent,
    'business_sent' => (bool) $businessSent,
    'message'       => 'Booking confirmation sent successfully.',
]);