<?php
/**
 * @var string $mode  list|new|edit|copy  @var ?array $target  @var array $errors  @var array $old  @var bool $readOnly
 * @var ?int $lockedTeamSize  @var array<int,int> $entryCounts  @var int $maxTeamSize  @var int $activeEventId
 * Nur Liste: @var callable $phaseOf  @var list<array> $allEvents  @var array<int,int> $categoryCount  @var array<int,int> $disciplineCount
 */
use App\Domain\EventPhase;
use App\Domain\EventValidator;
use App\Domain\HomeContent;
use App\Http\Form;

$f = new Form($errors, $old);
?>
<?php if ($mode === 'list'): ?>
    <?php $phaseLabel = [EventPhase::UPCOMING => 'Bevorstehend', EventPhase::LIVE => 'Läuft', EventPhase::PAST => 'Vorbei']; ?>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <h1 class="h2 mb-0">Anlässe</h1>
        <a class="btn btn-primary" href="<?= e(url('admin/anlaesse.php', ['new' => 1])) ?>">Neuer Anlass</a>
    </div>

    <?php if ($allEvents === []): ?>
        <div class="empty-state"><p>Noch kein Anlass angelegt.</p></div>
    <?php else: ?>
        <section class="card">
            <div class="table-responsive">
                <table class="table admin-table mb-0">
                    <thead><tr><th>Anlass</th><th>Datum</th><th>Status</th><th class="text-end">Anmeldungen</th><th class="text-end">Disziplinen</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($allEvents as $e): $id = (int) $e['id']; ?>
                            <tr>
                                <td>
                                    <a class="fw-semibold" href="<?= e(url('admin/index.php', ['event_id' => $id])) ?>"><?= e($e['title']) ?></a>
                                    <?php if ($id === $activeEventId): ?><span class="badge-soft ms-2">aktiv</span><?php endif; ?>
                                </td>
                                <td class="text-nowrap text-muted"><?= e((new DateTimeImmutable((string) $e['starts_at']))->format('d.m.Y H:i')) ?></td>
                                <td class="text-muted">
                                    <?= $e['anonymized_at'] ? 'anonymisiert' : ($e['archived_at'] ? 'archiviert' : e($phaseLabel[$phaseOf($e)])) ?>
                                </td>
                                <td class="text-end"><?= $entryCounts[$id] ?? 0 ?></td>
                                <td class="text-end"><?= $disciplineCount[$id] ?? 0 ?></td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/anlaesse.php', ['edit' => $id])) ?>">Bearbeiten</a>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/anlaesse.php', ['copy' => $id])) ?>">Als Vorlage kopieren</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

<?php elseif ($mode === 'copy'): ?>
    <a class="small" href="<?= e(url('admin/anlaesse.php')) ?>">← Alle Anlässe</a>
    <h1 class="h2 mt-2 mb-2">«<?= e($target['title']) ?>» als Vorlage kopieren</h1>
    <p class="text-muted mb-4">Übernommen werden Einstellungen, Texte, Kategorien und Disziplinen. Anmeldungen und Resultate werden <strong>nicht</strong> kopiert; der Kampfrichter-Code ist neu.</p>
    <form method="post" class="card" novalidate style="max-width: 36rem">
        <div class="card-body">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="duplicate">
            <input type="hidden" name="id" value="<?= (int) $target['id'] ?>">
            <div class="mb-3">
                <label class="form-label" for="title">Name des neuen Anlasses</label>
                <input class="form-control<?= $f->invalid('title') ?>" id="title" name="title" required maxlength="150" value="<?= $f->value('title') ?>">
                <?= $f->feedback('title') ?>
            </div>
            <div class="mb-4">
                <label class="form-label" for="starts_at">Beginn</label>
                <input class="form-control<?= $f->invalid('starts_at') ?>" id="starts_at" name="starts_at" type="datetime-local" required value="<?= $f->value('starts_at') ?>">
                <?= $f->feedback('starts_at') ?>
                <div class="form-text">Die Dauer des Originals wird übernommen.</div>
            </div>
            <button class="btn btn-primary" type="submit" data-once>Kopie anlegen</button>
            <a class="btn btn-outline-primary" href="<?= e(url('admin/anlaesse.php')) ?>">Abbrechen</a>
        </div>
    </form>

<?php else: /* new | edit */ ?>
    <?php $isEdit = $mode === 'edit'; $dis = $readOnly ? ' disabled' : ''; ?>
    <a class="small" href="<?= e(url('admin/anlaesse.php')) ?>">← Alle Anlässe</a>
    <h1 class="h2 mt-2 mb-4"><?= $isEdit ? e($target['title']) : 'Neuer Anlass' ?></h1>

    <?php if ($readOnly): ?>
        <div class="alert alert-info">Dieser Anlass ist anonymisiert und kann nicht mehr geändert werden.</div>
    <?php elseif ($errors): ?>
        <div class="alert alert-danger" role="alert">Bitte die markierten Felder prüfen.</div>
    <?php endif; ?>

    <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $target['id'] ?>"><?php endif; ?>
        <fieldset<?= $dis ?>>

        <section class="card mb-4"><div class="card-body">
            <h2 class="h5 mb-3">Allgemein</h2>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="title">Name</label>
                    <input class="form-control<?= $f->invalid('title') ?>" id="title" name="title" required maxlength="150" value="<?= $f->value('title') ?>" placeholder="z.B. Jugi-Cup 2027">
                    <?= $f->feedback('title') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="tagline">Untertitel <span class="text-muted fw-normal">(optional)</span></label>
                    <input class="form-control<?= $f->invalid('tagline') ?>" id="tagline" name="tagline" maxlength="255" value="<?= $f->value('tagline') ?>">
                    <?= $f->feedback('tagline') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="location">Ort <span class="text-muted fw-normal">(optional)</span></label>
                    <input class="form-control<?= $f->invalid('location') ?>" id="location" name="location" maxlength="255" value="<?= $f->value('location') ?>">
                    <?= $f->feedback('location') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="starts_at">Beginn</label>
                    <input class="form-control<?= $f->invalid('starts_at') ?>" id="starts_at" name="starts_at" type="datetime-local" required value="<?= $f->value('starts_at') ?>">
                    <?= $f->feedback('starts_at') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="ends_at">Ende</label>
                    <input class="form-control<?= $f->invalid('ends_at') ?>" id="ends_at" name="ends_at" type="datetime-local" required value="<?= $f->value('ends_at') ?>">
                    <?= $f->feedback('ends_at') ?>
                    <div class="form-text">Zwischen Beginn und Ende gilt der Anlass als «läuft» (Live-Rangliste).</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="badge">Sticker <span class="text-muted fw-normal">(optional)</span></label>
                    <input class="form-control<?= $f->invalid('badge') ?>" id="badge" name="badge" maxlength="80" value="<?= $f->value('badge') ?>" placeholder="z.B. 175 Jahre STV" aria-describedby="badge_help">
                    <?= $f->feedback('badge') ?>
                    <div class="form-text" id="badge_help">Kleines, leicht gekipptes Schild über dem Titel – für Jubiläen oder Besonderes.</div>
                </div>
            </div>
        </div></section>

        <section class="card mb-4"><div class="card-body">
            <h2 class="h5 mb-1">Texte &amp; Startseite</h2>
            <p class="text-muted small">Leerzeile = neuer Absatz, Zeilen mit «- » = Aufzählung, <code>**fett**</code>.</p>
            <div class="mb-3">
                <label class="form-label" for="description">Beschreibung</label>
                <textarea class="form-control<?= $f->invalid('description') ?>" id="description" name="description" rows="5" aria-describedby="description_help"><?= $f->value('description') ?></textarea>
                <?= $f->feedback('description') ?>
                <div class="form-text" id="description_help">Erscheint unter «Worum geht’s?». Der erste Absatz wird als kurzer Einstieg hervorgehoben – am besten ein einziger Satz.</div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-7">
                    <span class="form-label d-block">Kurzpunkte <span class="text-muted fw-normal">(optional, bis <?= HomeContent::MAX_KEY_POINTS ?>)</span></span>
                    <p class="form-text mt-0">Das Wichtigste auf einen Blick – z.B. «Kind + Erwachsene/r», «5 Plauschdisziplinen». Symbol anklicken, Text darunter. Leere Zeilen erscheinen nicht.</p>
                    <?php $points = is_array($old['key_points'] ?? null) ? array_values($old['key_points']) : []; ?>
                    <?php for ($i = 0; $i < HomeContent::MAX_KEY_POINTS; $i++): $point = is_array($points[$i] ?? null) ? $points[$i] : []; ?>
                        <fieldset class="key-point-row">
                            <legend class="visually-hidden">Kurzpunkt <?= $i + 1 ?></legend>
                            <div class="icon-choice" role="radiogroup" aria-label="Symbol für Kurzpunkt <?= $i + 1 ?>">
                                <?php $chosen = $point['icon'] ?? array_keys(HomeContent::KEY_POINT_ICONS)[$i]; ?>
                                <?php foreach (HomeContent::KEY_POINT_ICONS as $icon => $iconLabel): ?>
                                    <label title="<?= e($iconLabel) ?>">
                                        <input type="radio" name="key_points[<?= $i ?>][icon]" value="<?= e($icon) ?>"<?= $chosen === $icon ? ' checked' : '' ?>>
                                        <?= icon($icon) ?><span class="visually-hidden"><?= e($iconLabel) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <label class="visually-hidden" for="kp<?= $i ?>-text">Text für Kurzpunkt <?= $i + 1 ?></label>
                            <input class="form-control<?= $f->invalid('key_points.' . ($i + 1)) ?>" id="kp<?= $i ?>-text" name="key_points[<?= $i ?>][text]" maxlength="<?= HomeContent::MAX_KEY_POINT_LENGTH ?>" value="<?= e((string) ($point['text'] ?? '')) ?>" placeholder="Kurzpunkt <?= $i + 1 ?>">
                            <?= $f->feedback('key_points.' . ($i + 1)) ?>
                        </fieldset>
                    <?php endfor; ?>
                </div>
                <div class="col-lg-5">
                    <?php
                    $pickerName = 'intro_image';
                    $pickerValue = (string) ($old['intro_image'] ?? '');
                    $pickerLabel = 'Foto zur Beschreibung';
                    $pickerNone = 'Zufällig';
                    $pickerHelp = 'Steht neben «Worum geht’s?». «Zufällig» nimmt bei jedem Aufruf eines der Kopfbild-Fotos.';
                    require dirname(__DIR__) . '/partials/photo-picker.php';
                    ?>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="schedule_text">Zeitplan</label>
                    <textarea class="form-control<?= $f->invalid('schedule_text') ?>" id="schedule_text" name="schedule_text" rows="6" placeholder="- 13:00 Türöffnung&#10;- **13:30 Start**" aria-describedby="schedule_help"><?= $f->value('schedule_text') ?></textarea>
                    <?= $f->feedback('schedule_text') ?>
                    <div class="form-text" id="schedule_help">Beginnt jede Zeile mit einer Uhrzeit, erscheint der Zeitplan als Zeitleiste. <code>**fett**</code> hebt einen Punkt hervor.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="bring_text">Mitnehmen</label>
                    <textarea class="form-control<?= $f->invalid('bring_text') ?>" id="bring_text" name="bring_text" rows="6" placeholder="- Turnschuhe&#10;- Trinkflasche" aria-describedby="bring_help"><?= $f->value('bring_text') ?></textarea>
                    <?= $f->feedback('bring_text') ?>
                    <div class="form-text" id="bring_help">Jeder «- »-Punkt bekommt ein Symbol: Kommt ein bekanntes Stichwort vor (Schuhe, Kleider/Shirt, Trinkflasche, Sonnenschutz/Hut, Znüni/Verpflegung, Rucksack/Tasche, Ausweis), passt es dazu – sonst erscheint ein Häkchen. Eine Zeile ohne «- » erscheint als Hinweis darunter.</div>
                </div>
            </div>
        </div></section>

        <section class="card mb-4"><div class="card-body">
            <h2 class="h5 mb-1">Fotos der Unterseiten</h2>
            <p class="text-muted small">Foto rechts im Kopf von Rangliste, Startliste und Anmeldung – z.B. die Siegerehrung für die Rangliste. «Zufällig» nimmt bei jedem Aufruf eines der Kopfbild-Fotos.</p>
            <div class="row g-4">
                <?php foreach (['ranking_image' => 'Rangliste', 'startlist_image' => 'Startliste', 'registration_image' => 'Anmeldung'] as $field => $label): ?>
                    <div class="col-lg-4">
                        <?php
                        $pickerName = $field;
                        $pickerValue = (string) ($old[$field] ?? '');
                        $pickerLabel = $label;
                        $pickerNone = 'Zufällig';
                        $pickerHelp = '';
                        require dirname(__DIR__) . '/partials/photo-picker.php';
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div></section>

        <section class="card mb-4"><div class="card-body">
            <h2 class="h5 mb-3">Anmeldung &amp; Sichtbarkeit</h2>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="registration_enabled" name="registration_enabled" value="1"<?= $f->checked('registration_enabled') ?>>
                <label class="form-check-label" for="registration_enabled">Online-Anmeldung aktiv</label>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="registration_closes_at">Anmeldeschluss <span class="text-muted fw-normal">(optional)</span></label>
                    <input class="form-control<?= $f->invalid('registration_closes_at') ?>" id="registration_closes_at" name="registration_closes_at" type="datetime-local" value="<?= $f->value('registration_closes_at') ?>">
                    <?= $f->feedback('registration_closes_at') ?>
                    <div class="form-text">Leer = die Anmeldung schliesst automatisch zum Beginn des Anlasses.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="ranking_visibility">Rangliste öffentlich</label>
                    <select class="form-select<?= $f->invalid('ranking_visibility') ?>" id="ranking_visibility" name="ranking_visibility">
                        <?php foreach (EventValidator::VISIBILITY as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= $f->selected('ranking_visibility', $value) ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $f->feedback('ranking_visibility') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="name_display">Namen auf Start- und Rangliste</label>
                    <select class="form-select<?= $f->invalid('name_display') ?>" id="name_display" name="name_display">
                        <?php foreach (EventValidator::NAME_DISPLAY as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= $f->selected('name_display', $value) ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $f->feedback('name_display') ?>
                    <div class="form-text">Datenschutz: Bei Kindern empfiehlt sich «gekürzt» oder «nur Startnummer». Jahrgang und E-Mail sind nie öffentlich.</div>
                </div>
            </div>
        </div></section>

        <section class="card mb-4"><div class="card-body">
            <h2 class="h5 mb-3">Format</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-5">
                    <label class="form-label" for="team_size">Teilnahme</label>
                    <?php if ($lockedTeamSize !== null): ?>
                        <input type="hidden" name="team_size" value="<?= $lockedTeamSize ?>">
                    <?php endif; ?>
                    <select class="form-select<?= $f->invalid('team_size') ?>" id="team_size" name="team_size" data-team-size <?= $lockedTeamSize !== null ? 'disabled' : '' ?>>
                        <?php for ($n = 1; $n <= $maxTeamSize; $n++): ?>
                            <option value="<?= $n ?>"<?= (string) ($old['team_size'] ?? 1) === (string) $n ? ' selected' : '' ?>><?= $n === 1 ? 'Einzelwettkampf' : 'Teams à ' . $n . ' Personen' ?></option>
                        <?php endfor; ?>
                    </select>
                    <?= $f->feedback('team_size') ?>
                    <?php if ($lockedTeamSize !== null): ?>
                        <div class="form-text">Nicht mehr änderbar, weil es bereits Anmeldungen gibt.</div>
                    <?php endif; ?>
                </div>
            </div>

            <h3 class="h6 mt-4">Angaben pro <span data-member-noun>Person</span></h3>
            <p class="text-muted small">Welche Daten sollen bei der Anmeldung abgefragt werden? Geschlecht und Jahrgang braucht es nur, wenn Kategorien oder Handicap darauf beruhen.</p>
            <div class="member-config">
                <?php for ($pos = 1; $pos <= $maxTeamSize; $pos++):
                    $m = is_array($old['members'][$pos] ?? null) ? $old['members'][$pos] : []; ?>
                    <div class="member-row" data-member-row="<?= $pos ?>">
                        <div class="member-no"><?= $pos ?></div>
                        <div class="row g-2 flex-grow-1">
                            <div class="col-md-5">
                                <label class="form-label small" for="m<?= $pos ?>-label">Bezeichnung</label>
                                <input class="form-control<?= $f->invalid("members.{$pos}.label") ?>" id="m<?= $pos ?>-label" name="members[<?= $pos ?>][label]" maxlength="40"
                                       value="<?= e($m['label'] ?? '') ?>" placeholder="<?= $pos === 1 ? 'z.B. Kind' : 'Mitglied ' . $pos ?>">
                                <?= $f->feedback("members.{$pos}.label") ?>
                            </div>
                            <?php foreach (['gender' => 'Geschlecht', 'birth_year' => 'Jahrgang'] as $field => $label): ?>
                                <div class="col-6 col-md-3">
                                    <label class="form-label small" for="m<?= $pos ?>-<?= $field ?>"><?= $label ?></label>
                                    <select class="form-select" id="m<?= $pos ?>-<?= $field ?>" name="members[<?= $pos ?>][<?= $field ?>]">
                                        <?php foreach (EventValidator::MODES as $value => $modeLabel): ?>
                                            <option value="<?= e($value) ?>"<?= ($m[$field] ?? 'optional') === $value ? ' selected' : '' ?>><?= e($modeLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div></section>

        <section class="card mb-4"><div class="card-body">
            <h2 class="h5 mb-3">Kategorien &amp; Handicap</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="category_member">Kategorie richtet sich nach</label>
                    <select class="form-select<?= $f->invalid('category_member') ?>" id="category_member" name="category_member" data-member-select>
                        <?php for ($pos = 1; $pos <= $maxTeamSize; $pos++): ?>
                            <option value="<?= $pos ?>" data-pos="<?= $pos ?>"<?= (string) ($old['category_member'] ?? 1) === (string) $pos ? ' selected' : '' ?>>Mitglied <?= $pos ?></option>
                        <?php endfor; ?>
                    </select>
                    <?= $f->feedback('category_member') ?>
                    <div class="form-text">Geschlecht und Jahrgang dieses Mitglieds entscheiden über die Kategorie (Kategorien legst du unter «Kategorien» an).</div>
                </div>
            </div>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="handicap_enabled" name="handicap_enabled" value="1"<?= $f->checked('handicap_enabled') ?> data-toggle-target="#handicap-fields">
                <label class="form-check-label" for="handicap_enabled">Handicap aktivieren <span class="text-muted">(Ausgleich nach Alter und Geschlecht)</span></label>
            </div>
            <div id="handicap-fields" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="handicap_gender_member">Frauenbonus gilt für</label>
                    <select class="form-select<?= $f->invalid('handicap_gender_member') ?>" id="handicap_gender_member" name="handicap_gender_member" data-member-select>
                        <?php for ($pos = 1; $pos <= $maxTeamSize; $pos++): ?>
                            <option value="<?= $pos ?>" data-pos="<?= $pos ?>"<?= (string) ($old['handicap_gender_member'] ?? 1) === (string) $pos ? ' selected' : '' ?>>Mitglied <?= $pos ?></option>
                        <?php endfor; ?>
                    </select>
                    <?= $f->feedback('handicap_gender_member') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="handicap_age_member">Altersbonus gilt für</label>
                    <select class="form-select<?= $f->invalid('handicap_age_member') ?>" id="handicap_age_member" name="handicap_age_member" data-member-select>
                        <?php for ($pos = 1; $pos <= $maxTeamSize; $pos++): ?>
                            <option value="<?= $pos ?>" data-pos="<?= $pos ?>"<?= (string) ($old['handicap_age_member'] ?? 1) === (string) $pos ? ' selected' : '' ?>>Mitglied <?= $pos ?></option>
                        <?php endfor; ?>
                    </select>
                    <?= $f->feedback('handicap_age_member') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="handicap_reference_age">Referenzalter <span class="text-muted fw-normal">(optional)</span></label>
                    <input class="form-control<?= $f->invalid('handicap_reference_age') ?>" id="handicap_reference_age" name="handicap_reference_age" type="number" min="1" max="99" value="<?= $f->value('handicap_reference_age') ?>" placeholder="z.B. 16">
                    <?= $f->feedback('handicap_reference_age') ?>
                </div>
                <div class="col-12 form-text">
                    Der Altersbonus wirkt für jedes Lebensjahr unter dem Referenzalter. Ohne Referenzalter gibt es keinen Altersbonus.
                    Die Faktoren (immer ≥ 1) stellst du pro Disziplin ein.
                </div>
            </div>
        </div></section>

        <?php if (!$readOnly): ?>
        <section class="card mb-4"><div class="card-body">
            <h2 class="h5 mb-1">Nach dem Anlass</h2>
            <p class="text-muted small">Sobald der Anlass vorbei ist, stellt sich die Startseite selbst um (Rangliste statt Anmeldung, ohne Zeitplan und Mitnehmen). Hier legst du fest, was unter «Worum geht’s?» stehen soll.</p>
            <div class="mb-3">
                <label class="form-label" for="recap">Rückblick-Text <span class="text-muted fw-normal">(optional)</span></label>
                <textarea class="form-control<?= $f->invalid('recap') ?>" id="recap" name="recap" rows="4" aria-describedby="recap_help"<?= $recapDisabled ?? '' ?>><?= $f->value('recap') ?></textarea>
                <?= $f->feedback('recap') ?>
                <div class="form-text" id="recap_help">Ersetzt nach dem Anlass die Beschreibung unter «Rückblick» – z.B. «Am 28. März traten 40 Teams an … Danke allen!». Leer = die Beschreibung bleibt stehen. Der erste Absatz wird hervorgehoben.</div>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="recap_auto_points" name="recap_auto_points" value="1"<?= $f->checked('recap_auto_points') ?>>
                <label class="form-check-label" for="recap_auto_points">Nach dem Anlass Fakten statt Kurzpunkte zeigen <span class="text-muted">– Anzahl Teams, Disziplinen, Ort und «Danke fürs Mitmachen!»</span></label>
            </div>
        </div></section>
        <?php endif; ?>

        </fieldset>
        <?php if (!$readOnly): ?>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit" data-once><?= $isEdit ? 'Speichern' : 'Anlass anlegen' ?></button>
                <a class="btn btn-outline-primary" href="<?= e(url('admin/anlaesse.php')) ?>">Abbrechen</a>
            </div>
        <?php endif; ?>
    </form>

    <?php if ($readOnly): /* Anonymisiert: nur der Rückblick bleibt bearbeitbar (keine Personendaten) */ ?>
        <form method="post" class="mt-4" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_recap">
            <input type="hidden" name="id" value="<?= (int) $target['id'] ?>">
            <section class="card mb-4"><div class="card-body">
            <h2 class="h5 mb-1">Nach dem Anlass</h2>
            <p class="text-muted small">Sobald der Anlass vorbei ist, stellt sich die Startseite selbst um (Rangliste statt Anmeldung, ohne Zeitplan und Mitnehmen). Der Anlass ist anonymisiert – der Rückblick lässt sich trotzdem anpassen. Hier legst du fest, was unter «Worum geht’s?» stehen soll.</p>
            <div class="mb-3">
                <label class="form-label" for="recap">Rückblick-Text <span class="text-muted fw-normal">(optional)</span></label>
                <textarea class="form-control<?= $f->invalid('recap') ?>" id="recap" name="recap" rows="4" aria-describedby="recap_help"<?= $recapDisabled ?? '' ?>><?= $f->value('recap') ?></textarea>
                <?= $f->feedback('recap') ?>
                <div class="form-text" id="recap_help">Ersetzt nach dem Anlass die Beschreibung unter «Rückblick» – z.B. «Am 28. März traten 40 Teams an … Danke allen!». Leer = die Beschreibung bleibt stehen. Der erste Absatz wird hervorgehoben.</div>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="recap_auto_points" name="recap_auto_points" value="1"<?= $f->checked('recap_auto_points') ?>>
                <label class="form-check-label" for="recap_auto_points">Nach dem Anlass Fakten statt Kurzpunkte zeigen <span class="text-muted">– Anzahl Teams, Disziplinen, Ort und «Danke fürs Mitmachen!»</span></label>
            </div>
                <button class="btn btn-primary mt-3" type="submit" data-once>Rückblick speichern</button>
            </div></section>
        </form>
    <?php endif; ?>
<?php endif; ?>
