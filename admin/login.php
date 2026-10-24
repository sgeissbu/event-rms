<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Session;
use App\Core\View;
use App\Http\AdminAuth;
use App\Http\App;

App::boot();
header('Cache-Control: no-store');

if (AdminAuth::current() !== null) {
    redirect('admin/index.php');
}

$error = null;
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::requireCsrf();
    $email = (string) ($_POST['email'] ?? '');
    $error = AdminAuth::login($email, (string) ($_POST['password'] ?? ''));
    if ($error === null) {
        redirect('admin/index.php');
    }
}

View::render('admin/login', App::layoutVars() + ['pageTitle' => 'Admin-Anmeldung', 'error' => $error, 'email' => $email], 'layout-plain');
