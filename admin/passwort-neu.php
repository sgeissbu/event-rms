<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Session;
use App\Core\View;
use App\Http\AdminAuth;
use App\Http\App;

App::boot();
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer'); // Token steht in der URL

$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::requireCsrf();
    $password = (string) ($_POST['password'] ?? '');
    if (mb_strlen($password) < AdminAuth::MIN_PASSWORD) {
        $error = 'Das Passwort muss mindestens ' . AdminAuth::MIN_PASSWORD . ' Zeichen lang sein.';
    } elseif ($password !== (string) ($_POST['password2'] ?? '')) {
        $error = 'Die Passwörter stimmen nicht überein.';
    } elseif (AdminAuth::resetPassword($token, $password)) {
        Session::flash('success', 'Passwort geändert. Du kannst dich jetzt anmelden.');
        redirect('admin/login.php');
    } else {
        $error = 'Der Link ist ungültig oder abgelaufen. Bitte einen neuen anfordern.';
    }
}

View::render('admin/reset', App::layoutVars() + [
    'pageTitle' => 'Neues Passwort',
    'token' => $token,
    'valid' => AdminAuth::isValidResetToken($token),
    'error' => $error,
], 'layout-plain');
