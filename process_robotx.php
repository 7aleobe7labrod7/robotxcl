<?php
// ==============================================================================
// ROBOTX - LEAD PROCESSOR V2.0 (PHPMailer MANUAL — Puerto 465 SSL)
// ==============================================================================

require_once '/home/hrbusine/public_html/robotx.cl/config/database.php';

// PHPMailer cargado manualmente (sin Composer)
require_once '/home/hrbusine/public_html/robotx.cl/vendor/phpmailer/phpmailer/src/Exception.php';
require_once '/home/hrbusine/public_html/robotx.cl/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once '/home/hrbusine/public_html/robotx.cl/vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.html");
    exit();
}

// ==============================================================================
// SANITIZACIÓN COMPATIBLE PHP 8.x
// ==============================================================================
function sanitize(string $value): string {
    return htmlspecialchars(strip_tags(trim($value)));
}

$nombre   = sanitize($_POST['nombre_contacto'] ?? '');
$email    = filter_var(trim($_POST['email']    ?? ''), FILTER_SANITIZE_EMAIL);
$telefono = sanitize($_POST['telefono']        ?? '');
$empresa  = sanitize($_POST['empresa']         ?? '');
$servicio = sanitize($_POST['servicio_interes']?? '');
$mensaje  = sanitize($_POST['mensaje']         ?? '');

// Validaciones mínimas
if (empty($nombre) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($servicio)) {
    die("Datos inválidos. Por favor completa el formulario correctamente.");
}

$origen = 'RobotX';
$ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

try {
    // ==========================================================================
    // 1. GUARDAR LEAD EN BASE DE DATOS
    // ==========================================================================
    $sql = "INSERT INTO robotx_leads 
            (nombre_contacto, email, telefono, empresa, servicio_interes, mensaje, origen, ip_address) 
            VALUES (:nombre, :email, :telefono, :empresa, :servicio, :mensaje, :origen, :ip)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nombre'   => $nombre,
        ':email'    => $email,
        ':telefono' => $telefono,
        ':empresa'  => $empresa,
        ':servicio' => $servicio,
        ':mensaje'  => $mensaje,
        ':origen'   => $origen,
        ':ip'       => $ip
    ]);

    // ==========================================================================
    // 2. ENVÍO VÍA PHPMAILER + SMTP SSL (Puerto 465)
    // ==========================================================================
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = 'mail.robotx.cl';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'contacto@robotx.cl';
    $mail->Password   = 'WxsN{4}_DOc#';       // ← CAMBIA ESTO
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8';
    $mail->SMTPDebug  = SMTP::DEBUG_OFF;           // Cambiar a DEBUG_SERVER para diagnóstico

    // Remitente y destinatario
    $mail->setFrom('contacto@robotx.cl', 'RobotX Sistema');
    $mail->addAddress('ps.alabbe@gmail.com', 'RobotX Admin');
    $mail->addReplyTo($email, $nombre);

    // Asunto y cuerpo HTML
    $mail->isHTML(true);
    $mail->Subject = 'NUEVO REQUERIMIENTO ROBOTX: ' . $nombre;

    $mail->Body = '
        <div style="font-family:Courier New,monospace;background:#05070f;color:#f1f5f9;padding:30px;border-radius:10px;border:1px solid #39ff14;">
            <h2 style="color:#39ff14;margin-top:0;">[ ROBOTX ] — NUEVO LEAD RECIBIDO</h2>
            <hr style="border-color:#39ff14;opacity:0.3;">
            <table style="width:100%;font-size:15px;line-height:2;">
                <tr><td style="color:#94a3b8;width:140px;">CLIENTE</td>  <td style="color:#fff;"><strong>' . $nombre             . '</strong></td></tr>
                <tr><td style="color:#94a3b8;">EMAIL</td>    <td style="color:#fff;">'  . $email                    . '</td></tr>
                <tr><td style="color:#94a3b8;">TELÉFONO</td> <td style="color:#fff;">'  . $telefono                 . '</td></tr>
                <tr><td style="color:#94a3b8;">EMPRESA</td>  <td style="color:#fff;">'  . ($empresa ?: '—')         . '</td></tr>
                <tr><td style="color:#94a3b8;">SERVICIO</td> <td style="color:#39ff14;"><strong>' . $servicio . '</strong></td></tr>
            </table>
            <hr style="border-color:#39ff14;opacity:0.3;">
            <p style="color:#94a3b8;margin-bottom:6px;">MENSAJE / PAYLOAD:</p>
            <p style="color:#fff;background:rgba(255,255,255,0.05);padding:15px;border-radius:6px;border-left:3px solid #39ff14;">'
                . nl2br($mensaje ?: '(sin mensaje)') .
            '</p>
            <p style="color:#475569;font-size:12px;margin-top:20px;">IP: ' . $ip . ' | Origen: ' . $origen . ' | ' . date('d/m/Y H:i:s') . '</p>
        </div>';

    // Texto plano como fallback
    $mail->AltBody =
        "ROBOTX — NUEVO LEAD\n\n"              .
        "Cliente:  $nombre\n"                   .
        "Email:    $email\n"                    .
        "Teléfono: $telefono\n"                 .
        "Empresa:  " . ($empresa ?: '—') . "\n" .
        "Servicio: $servicio\n\n"               .
        "Mensaje:\n$mensaje\n\n"                .
        "IP: $ip | $origen | " . date('d/m/Y H:i:s');

    $mail->send();

    // ==========================================================================
    // 3. REDIRECCIÓN AL ÉXITO
    // ==========================================================================
    header("Location: gracias.html");
    exit();

} catch (Exception $e) {
    // PHPMailer falló — el lead YA está en BD, no se pierde
    error_log("RobotX SMTP ERROR [" . date('Y-m-d H:i:s') . "]: " . $mail->ErrorInfo);
    header("Location: gracias.html");
    exit();

} catch (PDOException $e) {
    error_log("RobotX BD ERROR: " . $e->getMessage());
    die("Error de sistema. Intente más tarde.");
}
?>