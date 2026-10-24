<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Session;
use App\Core\Settings;
use App\Core\View;
use App\Http\AdminAuth;
use App\Http\App;
use App\Service\Mailer;

App::boot();
header('Cache-Control: no-store');

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::requireCsrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $token = filter_var($email, FILTER_VALIDATE_EMAIL) ? AdminAuth::createResetToken($email) : null;
    if ($token !== null) {
        $link = base_url() . '/admin/passwort-neu.php?token=' . $token;
        $club = (string) Settings::get('club_name');
        Mailer::send(
            $email,
            'Passwort zurücksetzen · ' . $club,
            '<p>Hallo</p><p>Für den Admin-Bereich von ' . e($club) . ' wurde ein neues Passwort angefordert.</p>'
                . '<p><a href="' . e($link) . '">Neues Passwort festlegen</a> (gültig 1 Stunde)</p>'
                . '<p>Falls du das nicht warst, kannst du diese Mail ignorieren.</p>',
            "Für den Admin-Bereich von {$club} wurde ein neues Passwort angefordert.\n\nNeues Passwort festlegen (gültig 1 Stunde):\n{$link}\n\nFalls du das nicht warst, kannst du diese Mail ignorieren.\n"
        );
    }
    // Immer dieselbe Antwort – verrät nicht, ob die Adresse existiert.
    $sent = true;
}

View::render('admin/forgot', App::layoutVars() + [
    'pageTitle' => 'Passwort vergessen',
    'sent' => $sent,
    'mailConfigured' => Mailer::isConfigured(),
], 'layout-plain');
