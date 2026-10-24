<?php
/**
 * @var array $event  @var list<array> $list  @var array<int,int> $counts  @var ?array $editing
 * @var array $errors  @var array $old  @var bool $readOnly  @var bool $isManual  @var list<string> $conflicts
 */
use App\Domain\CategoryValidator;
use App\Domain\MemberConfig;
use App\Http\Form;

$f = new Form($errors, $old);
$config = MemberConfig::normalize($event['member_config'], (int) $event['team_size']);
$memberLabel = $config[(int) $event['category_member'] - 1]['label'] ?? 'Mitglied ' . (int) $event['category_member'];
?>
<span class="eyebrow"><?= e($event['title']) ?></span>
<h1 class="h2 mb-2">Kategorien</h1>
<p class="text-muted mb-4">
    Jede Kategorie hat ihre eigene Rangliste. Ohne Kategorien gibt es eine Gesamtrangliste.
    Haben Kategorien Regeln, wird bei der Anmeldung automatisch die erste passende gewählt
    (geprüft wird <strong><?= e($memberLabel) ?></strong>). Ohne Regeln wählen die Anmeldenden selbst.
</p>

<?php foreach ($conflicts as $conflict): ?>
    <div class="alert alert-warning"><?= e($conflict) ?></div>
<?php endforeach; ?>

<section class="card mb-4">
    <?php if ($list === []): ?>
        <p class="text-muted p-4 mb-0">Noch keine Kategorien – alle Anmeldungen kommen in eine Gesamtrangliste.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table admin-table mb-0">
                <thead><tr><th>Reihenfolge</th><th>Name</th><th>Regel</th><th class="text-end">Anmeldungen</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($list as $c): $n = $counts[$c['id']] ?? 0; ?>
                        <tr class="<?= $editing !== null && $editing['id'] === $c['id'] ? 'is-editing' : '' ?>">
                            <td><?= (int) $c['sort'] ?></td>
                            <td class="fw-semibold"><?= e($c['name']) ?></td>
                            <td class="text-muted"><?= e(CategoryValidator::describe($c)) ?></td>
                            <td class="text-end"><?= $n ?></td>
                            <td class="text-end text-nowrap">
                                <?php if (!$readOnly): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/kategorien.php', ['edit' => $c['id']])) ?>">Bearbeiten</a>
                                    <?php if ($n === 0): ?>
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button class="btn btn-sm btn-outline-primary" type="submit" data-once>Löschen</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($readOnly): ?>
    <div class="alert alert-info">Dieser Anlass ist anonymisiert – Kategorien können nicht mehr geändert werden.</div>
<?php else: ?>
    <div class="row g-4">
        <div class="col-lg-7">
            <form method="post" class="card" novalidate>
                <div class="card-body">
                    <h2 class="h5 mb-3"><?= $editing ? 'Kategorie bearbeiten' : 'Neue Kategorie' ?></h2>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <?php if ($editing): ?><input type="hidden" name="id" value="<?= $editing['id'] ?>"><?php endif; ?>

                    <div class="row g-3">
                        <div class="col-8">
                            <label class="form-label" for="name">Name</label>
                            <input class="form-control<?= $f->invalid('name') ?>" id="name" name="name" required maxlength="100" value="<?= $f->value('name') ?>" placeholder="z.B. U10 Mädchen">
                            <?= $f->feedback('name') ?>
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="sort">Reihenfolge</label>
                            <input class="form-control" id="sort" name="sort" type="number" value="<?= $f->value('sort', '0') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="gender">Geschlecht</label>
                            <select class="form-select<?= $f->invalid('gender') ?>" id="gender" name="gender">
                                <?php foreach (CategoryValidator::GENDERS as $value => $label): ?>
                                    <?php if ($value === 'mixed' && (int) $event['team_size'] === 1) { continue; } ?>
                                    <option value="<?= e($value) ?>"<?= $f->selected('gender', $value) ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= $f->feedback('gender') ?>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="birth_year_from">Jahrgang von</label>
                            <input class="form-control<?= $f->invalid('birth_year_from') ?>" id="birth_year_from" name="birth_year_from" inputmode="numeric" value="<?= $f->value('birth_year_from') ?>" placeholder="egal">
                            <?= $f->feedback('birth_year_from') ?>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="birth_year_to">bis</label>
                            <input class="form-control<?= $f->invalid('birth_year_to') ?>" id="birth_year_to" name="birth_year_to" inputmode="numeric" value="<?= $f->value('birth_year_to') ?>" placeholder="egal">
                            <?= $f->feedback('birth_year_to') ?>
                        </div>
                    </div>
                    <p class="form-text mt-2">Beispiel U10 Mädchen beim Anlass <?= (int) substr((string) $event['starts_at'], 0, 4) ?>: weiblich, Jahrgang <?= (int) substr((string) $event['starts_at'], 0, 4) - 9 ?> bis <?= (int) substr((string) $event['starts_at'], 0, 4) ?>.
                        Leere Felder heissen «egal». Eine Kategorie ganz ohne Regel fängt alle übrigen auf.</p>

                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-primary" type="submit" data-once><?= $editing ? 'Speichern' : 'Anlegen' ?></button>
                        <?php if ($editing): ?><a class="btn btn-outline-primary" href="<?= e(url('admin/kategorien.php')) ?>">Abbrechen</a><?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        <?php if ($list !== [] && !$isManual): ?>
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h5">Regeln geändert?</h2>
                        <p class="text-muted small">Bestehende Anmeldungen behalten ihre Kategorie, bis du sie neu zuteilst. Dabei werden alle Anmeldungen nach den aktuellen Regeln eingeteilt.</p>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reassign">
                            <button class="btn btn-outline-primary" type="submit" data-once>Alle neu zuteilen</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
