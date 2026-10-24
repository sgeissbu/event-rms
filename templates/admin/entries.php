<?php
/**
 * @var array $event  @var string $mode  list|new|edit|delete  @var ?array $target  @var array $errors  @var array $old
 * @var bool $readOnly  @var list<array> $config  @var list<array> $categories  @var array<int,string> $categoryNames  @var bool $isTeam
 * Liste: @var list<array> $list  @var int $total  @var string $q  @var array<int,int> $resultCounts
 * Löschen: @var int $resultCount
 */
use App\Domain\RegistrationValidator;
use App\Http\Form;

$f = new Form($errors, $old);
$noun = $isTeam ? 'Team' : 'Person';
$memberValue = static fn (int $pos, string $field): string => e(is_scalar($old['members'][$pos][$field] ?? null) ? (string) $old['members'][$pos][$field] : '');
?>
<span class="eyebrow"><?= e($event['title']) ?></span>

<?php if ($mode === 'list'): ?>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <h1 class="h2 mb-0">Teilnehmende <span class="text-muted fs-5 fw-normal"><?= $total ?></span></h1>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary" href="<?= e(url('admin/teilnehmer.php', ['export' => 'csv'])) ?>">CSV-Export</a>
            <?php if (!$readOnly): ?><a class="btn btn-primary" href="<?= e(url('admin/teilnehmer.php', ['new' => 1])) ?>"><?= $isTeam ? 'Team' : 'Person' ?> nachmelden</a><?php endif; ?>
        </div>
    </div>

    <form method="get" class="mb-3" role="search">
        <label class="visually-hidden" for="q">Suchen</label>
        <div class="input-group" style="max-width: 28rem">
            <input class="form-control" id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="Startnummer, Name oder E-Mail">
            <button class="btn btn-outline-primary" type="submit">Suchen</button>
            <?php if ($q !== ''): ?><a class="btn btn-outline-primary" href="<?= e(url('admin/teilnehmer.php')) ?>">Alle</a><?php endif; ?>
        </div>
    </form>

    <?php if ($readOnly): ?><div class="alert alert-info">Dieser Anlass ist anonymisiert – Personendaten sind gelöscht.</div><?php endif; ?>

    <section class="card">
        <?php if ($list === []): ?>
            <p class="text-muted p-4 mb-0"><?= $q !== '' ? 'Nichts gefunden.' : 'Noch keine Anmeldungen.' ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table admin-table mb-0">
                    <thead><tr><th>Nr.</th><th><?= $isTeam ? 'Team' : 'Name' ?></th><th>Kategorie</th><th>E-Mail</th><th class="text-end">Resultate</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($list as $entry): $n = $resultCounts[$entry['id']] ?? 0; ?>
                            <tr>
                                <td><span class="start-no"><?= (int) $entry['start_no'] ?></span></td>
                                <td>
                                    <span class="fw-semibold"><?= e($entry['name']) ?></span>
                                    <?php if ($isTeam):
                                        $names = array_filter(array_column($entry['members'], 'name')); ?>
                                        <?php if ($names): ?><span class="d-block small text-muted"><?= e(implode(' · ', $names)) ?></span><?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= e($categoryNames[$entry['category_id']] ?? '–') ?></td>
                                <td class="text-muted small"><?= e($entry['email'] ?? '–') ?></td>
                                <td class="text-end"><?= $n ?></td>
                                <td class="text-end text-nowrap">
                                    <?php if (!$readOnly): ?>
                                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/teilnehmer.php', ['edit' => $entry['id']])) ?>">Bearbeiten</a>
                                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/teilnehmer.php', ['delete' => $entry['id']])) ?>">Löschen</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

<?php elseif ($mode === 'delete'): ?>
    <a class="small" href="<?= e(url('admin/teilnehmer.php')) ?>">← Alle Teilnehmenden</a>
    <h1 class="h2 mt-2 mb-4">«<?= e($target['name']) ?>» löschen?</h1>
    <section class="card danger-zone" style="max-width: 40rem">
        <div class="card-body">
            <p>Startnummer <strong><?= (int) $target['start_no'] ?></strong> wird mit allen Angaben gelöscht.
                <?php if ($resultCount > 0): ?><strong><?= $resultCount ?> Resultat(e)</strong> dieses Eintrags werden ebenfalls gelöscht und fliessen nicht mehr in die Rangliste ein.<?php endif; ?></p>
            <p class="text-muted small">Das kann nicht rückgängig gemacht werden. Einzelne Resultate lassen sich unter «Resultate» annullieren, ohne den Eintrag zu löschen.</p>
            <form method="post" class="d-flex gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $target['id'] ?>">
                <button class="btn btn-danger" type="submit" data-once>Endgültig löschen</button>
                <a class="btn btn-outline-primary" href="<?= e(url('admin/teilnehmer.php')) ?>">Abbrechen</a>
            </form>
        </div>
    </section>

<?php else: /* new | edit */ ?>
    <a class="small" href="<?= e(url('admin/teilnehmer.php')) ?>">← Alle Teilnehmenden</a>
    <h1 class="h2 mt-2 mb-4"><?= $mode === 'edit' ? e($target['name']) . ' bearbeiten' : $noun . ' nachmelden' ?></h1>
    <?php if ($errors): ?><div class="alert alert-danger" role="alert">Bitte die markierten Felder prüfen.</div><?php endif; ?>

    <form method="post" novalidate style="max-width: 44rem">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <?php if ($mode === 'edit'): ?><input type="hidden" name="id" value="<?= (int) $target['id'] ?>"><?php endif; ?>

        <?php if ($isTeam): ?>
            <div class="card mb-3"><div class="card-body">
                <label class="form-label" for="name">Teamname</label>
                <input class="form-control<?= $f->invalid('name') ?>" id="name" name="name" required maxlength="100" value="<?= $f->value('name') ?>">
                <?= $f->feedback('name') ?>
            </div></div>
        <?php endif; ?>

        <?php foreach ($config as $i => $field): $pos = $i + 1; ?>
            <div class="card mb-3"><div class="card-body">
                <h2 class="h6 text-muted mb-3"><?= e($field['label']) ?></h2>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="m<?= $pos ?>-name">Vorname und Name</label>
                        <input class="form-control<?= $f->invalid("members.{$pos}.name") ?>" id="m<?= $pos ?>-name" name="members[<?= $pos ?>][name]" required maxlength="100" value="<?= $memberValue($pos, 'name') ?>">
                        <?= $f->feedback("members.{$pos}.name") ?>
                        <?php if (!$isTeam && isset($errors['name'])): ?><div class="invalid-feedback d-block"><?= e($errors['name']) ?></div><?php endif; ?>
                    </div>
                    <?php if ($field['birth_year'] !== 'hidden'): ?>
                        <div class="col-6">
                            <label class="form-label" for="m<?= $pos ?>-year">Jahrgang</label>
                            <input class="form-control<?= $f->invalid("members.{$pos}.birth_year") ?>" id="m<?= $pos ?>-year" name="members[<?= $pos ?>][birth_year]" type="number" inputmode="numeric" value="<?= $memberValue($pos, 'birth_year') ?>">
                            <?= $f->feedback("members.{$pos}.birth_year") ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($field['gender'] !== 'hidden'): ?>
                        <div class="col-6">
                            <label class="form-label" for="m<?= $pos ?>-gender">Geschlecht</label>
                            <select class="form-select<?= $f->invalid("members.{$pos}.gender") ?>" id="m<?= $pos ?>-gender" name="members[<?= $pos ?>][gender]">
                                <option value="">–</option>
                                <?php foreach (RegistrationValidator::GENDERS as $value => $label): ?>
                                    <option value="<?= $value ?>"<?= $memberValue($pos, 'gender') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= $f->feedback("members.{$pos}.gender") ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div></div>
        <?php endforeach; ?>

        <div class="card mb-3"><div class="card-body">
            <div class="row g-3">
                <?php if ($categories !== []): ?>
                    <div class="col-md-6">
                        <label class="form-label" for="category_id">Kategorie</label>
                        <select class="form-select<?= $f->invalid('category_id') ?>" id="category_id" name="category_id">
                            <option value="">Keine</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"<?= (string) ($old['category_id'] ?? '') === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $f->feedback('category_id') ?>
                        <div class="form-text">Bei einer Änderung von Jahrgang oder Geschlecht wird die Kategorie nicht automatisch angepasst – hier von Hand wählen oder unter «Kategorien» alle neu zuteilen.</div>
                    </div>
                <?php endif; ?>
                <div class="col-md-6">
                    <label class="form-label" for="email">E-Mail <span class="text-muted fw-normal">(optional)</span></label>
                    <input class="form-control<?= $f->invalid('email') ?>" id="email" name="email" type="email" maxlength="191" value="<?= $f->value('email') ?>">
                    <?= $f->feedback('email') ?>
                </div>
            </div>
        </div></div>

        <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit" data-once><?= $mode === 'edit' ? 'Speichern' : 'Anmelden' ?></button>
            <a class="btn btn-outline-primary" href="<?= e(url('admin/teilnehmer.php')) ?>">Abbrechen</a>
        </div>
    </form>
<?php endif; ?>
