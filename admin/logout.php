<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Session;
use App\Http\AdminAuth;
use App\Http\App;

App::boot();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::requireCsrf();
    AdminAuth::logout();
    Session::flash('success', 'Du bist abgemeldet.');
}
redirect('admin/login.php');
