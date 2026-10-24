<?php
/**
 * Admin-Benutzer: anlegen, entfernen, eigenes Passwort ändern, Passwort einer anderen Person neu setzen (?reset=ID).
 * POST action=create | delete | change_own | set_password
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Domain\PasswordPolicy;
use App\Domain\UserValidator;
use App\Http\Admin;
use App\Http\AdminAuth;
use App\Repository\UserRepository;

$ctx = Admin::boot();
$me = $ctx['admin'];
$users = new UserRepository(Database::pdo());
$errors = [];
$old = [];
$resetTarget = null;

if (isset($_GET['reset']) && ctype_digit((string) $_GET['reset']) && (int) $_GET['reset'] !== $me['id']) {
    $resetTarget = $users->find((int) $_GET['reset']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::requireCsrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $result = UserValidator::validateNew($_POST);
        if ($result['errors'] === []) {
            try {
                $users->create($result['values']['email'], $result['values']['name'], (string) $_POST['password']);
                Session::flash('success', 'Admin «' . $result['values']['name'] . '» angelegt. Das Passwort bitte persönlich weitergeben.');
                redirect('admin/benutzer.php');
            } catch (DomainException $e) {
                $result['errors']['email'] = $e->getMessage();
            }
        }
        $errors = $result['errors'];
        $old = ['name' => $result['values']['name'], 'email' => $result['values']['email']];
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $victim = $users->find($id);
        if ($id === $me['id']) {
            Session::flash('warning', 'Du kannst dein eigenes Konto nicht entfernen. Bitte eine andere Admin-Person darum bitten.');
        } elseif ($victim !== null && $users->delete($id)) {
            Session::flash('success', 'Admin «' . $victim['name'] . '» entfernt.');
        } else {
            Session::flash('warning', 'Der Admin konnte nicht entfernt werden (der letzte Admin bleibt immer bestehen).');
        }
        redirect('admin/benutzer.php');
    }

    if ($action === 'change_own') {
        $error = AdminAuth::changeOwnPassword($me['id'], (string) ($_POST['current'] ?? ''), (string) ($_POST['password'] ?? ''), (string) ($_POST['password2'] ?? ''));
        if ($error === null) {
            Session::flash('success', 'Dein Passwort wurde geändert. Auf anderen Geräten musst du dich neu anmelden.');
            redirect('admin/benutzer.php');
        }
        $errors['change_own'] = $error;
    }

    if ($action === 'set_password') {
        $targetId = (int) ($_POST['id'] ?? 0);
        $resetTarget = $targetId !== $me['id'] ? $users->find($targetId) : null;
        $policy = PasswordPolicy::check((string) ($_POST['password'] ?? ''), (string) ($_POST['password2'] ?? ''));
        if ($resetTarget === null) {
            Session::flash('warning', 'Diesen Admin gibt es nicht.');
            redirect('admin/benutzer.php');
        } elseif ($policy !== null) {
            $errors['set_password'] = $policy;
        } else {
            $users->setPassword($resetTarget['id'], (string) $_POST['password']);
            Session::flash('success', 'Neues Passwort für «' . $resetTarget['name'] . '» gesetzt. Bitte persönlich weitergeben; bestehende Sitzungen sind beendet.');
            redirect('admin/benutzer.php');
        }
    }
}

View::render('admin/users', Admin::layoutVars($ctx, 'users', 'Admins') + [
    'list' => $users->all(),
    'meId' => $me['id'],
    'errors' => $errors,
    'old' => $old,
    'resetTarget' => $resetTarget,
], 'layout-admin');
