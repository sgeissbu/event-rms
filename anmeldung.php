<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\Session;
use App\Core\Settings;
use App\Core\View;
use App\Domain\CategoryMatcher;
use App\Domain\EventPhase;
use App\Domain\MemberConfig;
use App\Domain\RegistrationValidator;
use App\Http\App;
use App\Repository\CategoryRepository;
use App\Repository\EntryRepository;
use App\Service\Mailer;
use App\Service\RateLimiter;

App::boot();

$event = App::currentEvent() ?? App::notFound('Zurzeit ist kein Anlass ausgeschrieben.');
$pdo = Database::pdo();
$categories = (new CategoryRepository($pdo))->forEvent((int) $event['id']);
$query = App::eventQuery($event);

$vars = App::layoutVars('register', $event) + [
    'pageTitle' => 'Anmeldung',
    'memberConfig' => MemberConfig::normalize($event['member_config'], (int) $event['team_size']),
    'categories' => $categories,
    'manualCategory' => CategoryMatcher::isManual($categories),
    'open' => EventPhase::registrationOpen($event),
    'errors' => [],
    'old' => [],
    'done' => $_SESSION['registered'] ?? null,
];
unset($_SESSION['registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $vars['open']) {
    Session::requireCsrf();

    // Honeypot: Bots füllen das versteckte Feld «website» aus – so tun, als hätte es geklappt.
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        redirect('anmeldung.php', $query + ['ok' => 1]);
    }

    $result = RegistrationValidator::validate($event, $categories, $_POST);
    if ($result['errors'] === [] && !(new RateLimiter($pdo))->hit(RateLimiter::clientKey('register'), 20, 3600)) {
        $result['errors']['_'] = 'Zu viele Anmeldungen von diesem Anschluss. Bitte später nochmals versuchen.';
    }

    if ($result['errors'] === []) {
        try {
            $created = (new EntryRepository($pdo))->register((int) $event['id'], $result['data']);
            $data = $result['data'];
            $mailSent = $data['email'] !== null && Mailer::send(
                $data['email'],
                'Anmeldebestätigung: ' . $event['title'],
                View::capture('mail/registration', ['event' => $event, 'data' => $data, 'startNo' => $created['start_no'], 'clubName' => Settings::get('club_name')]),
                View::capture('mail/registration-text', ['event' => $event, 'data' => $data, 'startNo' => $created['start_no'], 'clubName' => Settings::get('club_name')])
            );
            $_SESSION['registered'] = ['name' => $data['name'], 'start_no' => $created['start_no'], 'mail' => $mailSent];
            redirect('anmeldung.php', $query + ['ok' => 1]);
        } catch (DomainException $e) {
            $result['errors']['name'] = $e->getMessage();
        }
    }

    $vars['errors'] = $result['errors'];
    $vars['old'] = $_POST;
}

View::render('registration', $vars);
