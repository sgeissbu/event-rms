<?php
/**
 * @var string $mode  list|new|edit|copy  @var ?array $target  @var array $errors  @var array $old  @var bool $readOnly
 * @var ?int $lockedTeamSize  @var array<int,int> $entryCounts  @var int $maxTeamSize  @var int $activeEventId
 * Nur Liste: @var callable $phaseOf  @var list<array> $allEvents  @var array<int,int> $categoryCount  @var array<int,int> $disciplineCount
 */
use App\Domain\EventPhase;
use App\Domain\EventValidator;
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
            </div>
        </div></section>

        <section class="card mb-4"><div class="card-body">
            <h2 class="h5 mb-1">Texte</h2>
            <p class="text-muted small">Leerzeile = neuer Absatz, Zeilen mit «- » = Aufzählung, <code>**fett**</code>.</p>
            <div class="mb-3">
                <label class="form-label" for="description">Beschreibung</label>
                <textarea class="form-control<?= $f->invalid('description') ?>" id="description" name="description" rows="4"><?= $f->value('description') ?></textarea>
                <?= $f->feedback('description') ?>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="schedule_text">Zeitplan</label>
                    <textarea class="form-control<?= $f->invalid('schedule_text') ?>" id="schedule_text" name="schedule_text" rows="6" placeholder="- 13:00 Türöffnung&#10;- 13:30 Start"><?= $f->value('schedule_text') ?></textarea>
                    <?= $f->feedback('schedule_text') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="bring_text">Mitnehmen</label>
                    <textarea class="form-control<?= $f->invalid('bring_text') ?>" id="bring_text" name="bring_text" rows="6" placeholder="- Turnschuhe&#10;- Trinkflasche"><?= $f->value('bring_text') ?></textarea>
                    <?= $f->feedback('bring_text') ?>
                </div>
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

        </fieldset>
        <?php if (!$readOnly): ?>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit" data-once><?= $isEdit ? 'Speichern' : 'Anlass anlegen' ?></button>
                <a class="btn btn-outline-primary" href="<?= e(url('admin/anlaesse.php')) ?>">Abbrechen</a>
            </div>
        <?php endif; ?>
    </form>
<?php endif; ?>
