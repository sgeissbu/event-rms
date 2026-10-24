<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Session;
use App\Http\App;
use App\Http\JudgeAuth;

App::boot();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::requireCsrf();
    JudgeAuth::logout();
}
redirect('judge/index.php');
