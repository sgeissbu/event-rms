<?php
/**
 * Web-Installer. Ablauf:
 *   1. Voraussetzungen + DB-Zugang → .env schreiben (oder Inhalt zum Hochladen anzeigen)
 *   2. Verein, Farben, Admin, erster Anlass → Schema einspielen, Sperre setzen
 * Gesperrt, sobald uploads/setup.lock existiert oder ein Admin in der DB steht.
 *
 * Schutz vor fremdem Zugriff während der Einrichtung: Schritt 2 ist nur in der Sitzung
 * erlaubt, die den DB-Zugang eingegeben hat – oder nach erneuter Eingabe des DB-Passworts
 * (z.B. wenn die .env manuell hochgeladen wurde).
 */
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Session;
use App\Core\View;
use App\Http\App;
use App\Install\Installer;

Session::start();

$render = static function (string $step, array $vars = []): never {
    View::render('setup/' . $step, $vars + App::fallbackLayoutVars() + ['pageTitle' => 'Einrichtung', 'step' => $step], 'layout-plain');
    exit;
};

if (Installer::isLocked()) {
    if (isset($_SESSION['setup_done'])) {
        $done = $_SESSION['setup_done'];
        unset($_SESSION['setup_done'], $_SESSION['setup_verified'], $_SESSION['setup_env']);
        $render('done', $done + ['protection' => Installer::selfTestProtection(base_url())]);
    }
    http_response_code(404);
    exit('Nicht gefunden.');
}

$envExists = Env::isLoaded() && Database::isConfigured();
$verified = !empty($_SESSION['setup_verified']);
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) ($_POST['action'] ?? '') : '';
if ($action !== '') {
    Session::requireCsrf();
}

// --- Schritt 1: Datenbankzugang ------------------------------------------------
if ($action === 'database') {
    if ($envExists && !$verified) {
        // Eine bestehende .env darf nur überschreiben, wer sie kennt.
        $render('verify', ['error' => 'Es existiert bereits eine Konfiguration. Bitte zuerst bestätigen.']);
    }
    $cfg = [
        'host' => trim((string) ($_POST['db_host'] ?? '')),
        'port' => trim((string) ($_POST['db_port'] ?? '3306')) ?: '3306',
        'name' => trim((string) ($_POST['db_name'] ?? '')),
        'user' => trim((string) ($_POST['db_user'] ?? '')),
        'pass' => (string) ($_POST['db_pass'] ?? ''),
    ];
    $checks = Installer::requirements();
    $error = null;
    if ($cfg['host'] === '' || $cfg['name'] === '' || $cfg['user'] === '' || !ctype_digit($cfg['port'])) {
        $error = 'Bitte Host, Port, Datenbankname und Benutzer angeben.';
    } else {
        try {
            Installer::checkDatabase($cfg);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
    if ($error !== null) {
        $render('database', ['checks' => $checks, 'error' => $error, 'old' => $cfg]);
    }

    $contents = Installer::envContents($cfg, base_url()); // feste Adresse für Links in E-Mails
    Session::regenerate();
    $_SESSION['setup_verified'] = true;
    if (!Installer::writeEnv($contents)) {
        $_SESSION['setup_env'] = $contents;
    }
    redirect('setup.php');
}

// --- Bestätigung bei vorhandener .env --------------------------------------------
if ($action === 'verify') {
    $_SESSION['setup_verify_attempts'] = ($_SESSION['setup_verify_attempts'] ?? 0) + 1;
    if ($_SESSION['setup_verify_attempts'] > 5) {
        $render('verify', ['error' => 'Zu viele Versuche. Bitte Browser neu starten.', 'blocked' => true]);
    }
    if ($envExists && hash_equals((string) Env::get('DB_PASS', ''), (string) ($_POST['db_pass'] ?? ''))) {
        Session::regenerate();
        $_SESSION['setup_verified'] = true;
        unset($_SESSION['setup_verify_attempts']);
        redirect('setup.php');
    }
    $render('verify', ['error' => 'Das Passwort stimmt nicht mit der .env überein.']);
}

// --- Schritt 2: Verein, Admin, Anlass ---------------------------------------------
if ($action === 'install') {
    if (!$envExists || !$verified) {
        redirect('setup.php');
    }
    $in = array_map(static fn ($v): string => is_string($v) ? $v : '', $_POST);
    $errors = Installer::validateSite($in);
    if ($errors === []) {
        try {
            $result = Installer::install(Database::pdo(), $in);
            $_SESSION['setup_done'] = ['judgeCode' => $result['judge_code'], 'adminEmail' => trim($in['admin_email'])];
            redirect('setup.php');
        } catch (Throwable $e) {
            error_log('event-rms setup: ' . $e->getMessage());
            $errors['_'] = 'Die Einrichtung ist fehlgeschlagen: ' . $e->getMessage();
        }
    }
    unset($in['admin_password'], $in['admin_password2']);
    $render('site', ['errors' => $errors, 'old' => $in]);
}

// --- GET: passenden Schritt anzeigen ---------------------------------------------
if (isset($_SESSION['setup_env'])) {
    // .env konnte nicht geschrieben werden: anzeigen, bis die hochgeladene Datei passt.
    $expected = Env::parse($_SESSION['setup_env']);
    foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
        if (Env::get($key) !== $expected[$key]) {
            $render('env-manual', ['envContents' => $_SESSION['setup_env']]);
        }
    }
    unset($_SESSION['setup_env']);
}
if (!$envExists) {
    $render('database', ['checks' => Installer::requirements(), 'error' => null, 'old' => []]);
}

if (!$verified) {
    $render('verify', ['error' => null]);
}

try {
    Database::pdo();
} catch (RuntimeException) {
    $render('database', [
        'checks' => Installer::requirements(),
        'error' => 'Mit den Angaben in der .env ist keine Verbindung möglich. Bitte Zugang neu eingeben.',
        'old' => ['host' => Env::get('DB_HOST'), 'port' => Env::get('DB_PORT', '3306'), 'name' => Env::get('DB_NAME'), 'user' => Env::get('DB_USER')],
    ]);
}

$render('site', [
    'errors' => [],
    'old' => [
        'event_start' => (new DateTimeImmutable('+30 days'))->format('Y-m-d') . 'T13:30',
        'team_size' => '1',
        'color_primary' => '#1D4ED8',
        'color_secondary' => '#F59E0B',
    ],
]);
