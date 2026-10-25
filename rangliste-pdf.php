<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
// Der Autoloader im Bootstrap kennt nur App\ und PHPMailer\ – Dompdf binden wir selbst ein.
require __DIR__ . '/vendor/dompdf/autoload.inc.php';

use App\Core\Database;
use App\Core\Settings;
use App\Domain\EventPhase;
use App\Http\AdminAuth;
use App\Http\App;
use App\Http\JudgeAuth;
use App\Service\RankingPdf;
use App\Service\RankingService;

App::boot();

$event = App::currentEvent() ?? App::notFound('Zurzeit ist kein Anlass ausgeschrieben.');

// Zugang wie auf der Ranglistenseite: öffentlich, wenn die Rangliste publiziert ist,
// sonst für Kampfrichter dieses Events – und zusätzlich für angemeldete Admins.
$judge = JudgeAuth::current();
$isJudge = $judge !== null && (int) $judge['event']['id'] === (int) $event['id'];
if (!EventPhase::rankingPublic($event) && !$isJudge && AdminAuth::current() === null) {
    App::notFound('Die Rangliste wird zum Wettkampfstart veröffentlicht.');
}

$now = new DateTimeImmutable();
$pdf = RankingPdf::build($event, (new RankingService(Database::pdo()))->forEvent($event), [
    'club' => (string) Settings::get('club_name'),
    'primary' => (string) Settings::get('color_primary'),
    'logo' => (string) Settings::get('logo_file'), // immer das Hellmodus-Logo – das PDF geht auch auf den Drucker
], $now);

// ?inline=1 liefert dasselbe PDF zur Ansicht im Browser (Drucken-Knopf), sonst zum Herunterladen.
$inline = (string) ($_GET['inline'] ?? '') === '1';

header('Content-Type: application/pdf');
header(RankingPdf::contentDisposition($event, $inline, $now));
header('Cache-Control: no-store');

echo $pdf;