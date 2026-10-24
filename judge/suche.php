<?php
/**
 * JSON-Suche nach Einträgen für die Erfassung (Startnummer oder Name).
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database;
use App\Http\App;
use App\Http\JudgeAuth;
use App\Repository\EntryRepository;

App::boot();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$judge = JudgeAuth::current();
if ($judge === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Bitte neu anmelden.']);
    exit;
}

$query = is_string($_GET['q'] ?? null) ? mb_substr($_GET['q'], 0, 60) : '';
echo json_encode(
    (new EntryRepository(Database::pdo()))->search((int) $judge['event']['id'], $query),
    JSON_UNESCAPED_UNICODE
);
