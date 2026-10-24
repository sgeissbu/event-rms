<?php
/**
 * Kampfrichter-Login: Event-Code + Name. Mit ?code=… (QR-Code) ist der Code vorausgefüllt.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Session;
use App\Core\View;
use App\Http\App;
use App\Http\JudgeAuth;

App::boot();

if (JudgeAuth::current() !== null) {
    redirect('judge/erfassen.php');
}

$event = App::currentEvent();
$error = null;
$code = is_string($_GET['code'] ?? null) ? $_GET['code'] : '';
$name = (string) ($_COOKIE['judge_name'] ?? '');

if ($event !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::requireCsrf();
    $code = (string) ($_POST['code'] ?? '');
    $name = (string) ($_POST['name'] ?? '');
    $error = JudgeAuth::login($event, $code, $name);
    if ($error === null) {
        // Name fürs nächste Mal merken (nur Komfort, kein Login-Merkmal).
        setcookie('judge_name', trim($name), [
            'expires' => time() + 60 * 60 * 24 * 60, 'path' => base_path() . '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
        ]);
        redirect('judge/erfassen.php');
    }
}

View::render('judge/login', App::layoutVars('', $event) + [
    'pageTitle' => 'Kampfrichter',
    'judge' => null,
    'loginEvent' => $event,
    'error' => $error,
    'code' => $code,
    'name' => $name,
], 'layout-judge');
