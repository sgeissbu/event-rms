<?php
/**
 * JSON-Endpunkt für die Farb-Vorschau in «Verein & Design»: liefert die berechneten Theme-Variablen
 * (hell und dunkel) und Lesbarkeits-Hinweise für zwei Farben – dieselbe Logik wie die echte Seite.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Http\AdminAuth;
use App\Http\App;
use App\Service\Theme;

App::boot();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (AdminAuth::current() === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Bitte neu anmelden.']);
    exit;
}

$primary = Theme::normalizeHex(is_string($_GET['primary'] ?? null) ? $_GET['primary'] : '');
$secondary = Theme::normalizeHex(is_string($_GET['secondary'] ?? null) ? $_GET['secondary'] : '');
if ($primary === null || $secondary === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige Farbe.']);
    exit;
}

echo json_encode(Theme::preview($primary, $secondary), JSON_UNESCAPED_UNICODE);
