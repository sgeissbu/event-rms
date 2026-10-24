<?php
/**
 * Kategorien des bearbeiteten Anlasses.
 * Muster für Admin-Verwaltungsseiten: Liste + Formular auf einer Seite, ?edit=ID zum Bearbeiten,
 * POST action=save|delete|reassign, Validierung in einer Domain-Klasse, danach PRG-Redirect.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Domain\CategoryMatcher;
use App\Domain\CategoryValidator;
use App\Domain\MemberConfig;
use App\Http\Admin;
use App\Repository\CategoryRepository;
use App\Repository\EntryRepository;

$ctx = Admin::boot();
$event = Admin::requireEvent($ctx['event']);
$eventId = (int) $event['id'];
$pdo = Database::pdo();
$categories = new CategoryRepository($pdo);
$list = $categories->forEvent($eventId);
$readOnly = !empty($event['anonymized_at']);

$editId = (int) ($_GET['edit'] ?? $_POST['id'] ?? 0);
$editing = null;
foreach ($list as $c) {
    if ($c['id'] === $editId) {
        $editing = $c;
    }
}
$errors = [];
$old = $editing ?? ['sort' => (string) ((int) max([0, ...array_column($list, 'sort')]) + 1), 'gender' => 'any'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$readOnly) {
    Session::requireCsrf();
    switch ((string) ($_POST['action'] ?? '')) {
        case 'save':
            $result = CategoryValidator::validate($_POST, (int) substr((string) $event['starts_at'], 0, 4));
            if ($result['errors'] === []) {
                if ($editing !== null) {
                    $categories->update($editing['id'], $eventId, $result['values']);
                    Session::flash('success', 'Kategorie «' . $result['values']['name'] . '» gespeichert.');
                } else {
                    $categories->create($eventId, $result['values']);
                    Session::flash('success', 'Kategorie «' . $result['values']['name'] . '» angelegt.');
                }
                redirect('admin/kategorien.php');
            }
            $errors = $result['errors'];
            $old = $_POST;
            break;

        case 'delete':
            $ok = $categories->deleteIfUnused((int) ($_POST['id'] ?? 0), $eventId);
            Session::flash($ok ? 'success' : 'warning', $ok
                ? 'Kategorie gelöscht.'
                : 'Die Kategorie hat noch Anmeldungen. Bitte zuerst die Regeln anpassen und «Alle neu zuteilen» ausführen.');
            redirect('admin/kategorien.php');

        case 'reassign':
            // Wendet die aktuellen Regeln auf alle Anmeldungen an (z.B. nach geänderten Jahrgängen).
            $entries = new EntryRepository($pdo);
            $changed = 0;
            $unmatched = 0;
            if (!CategoryMatcher::isManual($list)) {
                foreach ($entries->forEvent($eventId) as $entry) {
                    $new = CategoryMatcher::match($list, $entry['members'], (int) $event['category_member']);
                    if ($new === null) {
                        $unmatched++;
                    } elseif ($new !== $entry['category_id']) {
                        $entries->setCategory($entry['id'], $eventId, $new);
                        $changed++;
                    }
                }
            }
            Session::flash('success', $changed . ' Anmeldung(en) neu zugeteilt.');
            if ($unmatched > 0) {
                Session::flash('warning', $unmatched . ' Anmeldung(en) passen zu keiner Kategorie und wurden nicht verändert.');
            }
            redirect('admin/kategorien.php');
    }
}

View::render('admin/categories', Admin::layoutVars($ctx, 'categories', 'Kategorien') + [
    'list' => $list,
    'counts' => $categories->entryCounts($eventId),
    'editing' => $editing,
    'errors' => $errors,
    'old' => $old,
    'readOnly' => $readOnly,
    'isManual' => CategoryMatcher::isManual($list),
    'conflicts' => MemberConfig::conflicts(MemberConfig::normalize($event['member_config'], (int) $event['team_size']), $event, $list),
], 'layout-admin');
