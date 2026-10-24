<?php
/**
 * @var array $event  @var list<array> $list  @var array<int,int> $resultCounts  @var ?array $editing  @var bool $locked
 * @var array $errors  @var array $old  @var bool $readOnly  @var list<string> $preview
 */
use App\Domain\ValueFormat;
use App\Http\Form;

$f = new Form($errors, $old);
$num = static fn (float $v): string => rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');
?>
<span class="eyebrow"><?= e($event['title']) ?></span>
<h1 class="h2 mb-2">Disziplinen</h1>
<p class="text-muted mb-4">
    Jede Disziplin hat eine Messart (Zahl oder Zeit) und eine Richtung (höher oder tiefer ist besser).
    Mit dem Handicap-Faktor lassen sich Unterschiede ausgleichen – er ist immer ein <strong>Vorteil ab 1</strong>.
</p>

<section class="card mb-4">
    <?php if ($list === []): ?>
        <p class="text-muted p-4 mb-0">Noch keine Disziplinen – Kampfrichter können erst erfassen, wenn mindestens eine existiert.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table admin-table mb-0">
                <thead><tr><th>#</th><th>Name</th><th>Messung</th><th>Handicap</th><th class="text-end">Resultate</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($list as $d): $n = $resultCounts[$d['id']] ?? 0; ?>
                        <tr class="<?= $editing !== null && $editing['id'] === $d['id'] ? 'is-editing' : '' ?><?= $d['active'] ? '' : ' text-muted' ?>">
                            <td><?= (int) $d['sort'] ?></td>
                            <td>
                                <span class="fw-semibold"><?= e($d['name']) ?></span>
                                <?php if (!$d['active']): ?><span class="badge-soft ms-1">inaktiv</span><?php endif; ?>
                            </td>
                            <td class="text-muted">
                                <?= $d['input_format'] === 'time' ? 'Zeit' : 'Zahl' ?><?= $d['unit'] !== '' ? ' (' . e($d['unit']) . ')' : '' ?>,
                                <?= $d['higher_is_better'] ? 'höher ist besser' : 'tiefer ist besser' ?><?= $d['parts'] ? ', ' . count($d['parts']) . ' Teilmessungen' : '' ?>
                            </td>
                            <td class="text-muted text-nowrap">
                                <?php if ($d['factor_female'] > 1 || $d['factor_age'] > 1): ?>
                                    <?= $d['factor_female'] > 1 ? 'Frau ×' . $num($d['factor_female']) : '' ?>
                                    <?= $d['factor_age'] > 1 ? '· Alter ×' . $num($d['factor_age']) . '/Jahr' : '' ?>
                                <?php else: ?>–<?php endif; ?>
                            </td>
                            <td class="text-end"><?= $n ?></td>
                            <td class="text-end text-nowrap">
                                <?php if (!$readOnly): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/disziplinen.php', ['edit' => $d['id']])) ?>">Bearbeiten</a>
                                    <?php if ($n === 0): ?>
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $d['id'] ?>">
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
    <div class="alert alert-info">Dieser Anlass ist anonymisiert – Disziplinen können nicht mehr geändert werden.</div>
<?php else: ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <form method="post" class="card" novalidate>
                <div class="card-body">
                    <h2 class="h5 mb-3"><?= $editing ? 'Disziplin bearbeiten' : 'Neue Disziplin' ?></h2>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <?php if ($editing): ?><input type="hidden" name="id" value="<?= $editing['id'] ?>"><?php endif; ?>

                    <?php if ($errors): ?><div class="alert alert-danger" role="alert">Bitte die markierten Felder prüfen.</div><?php endif; ?>

                    <div class="row g-3">
                        <div class="col-8">
                            <label class="form-label" for="name">Name</label>
                            <input class="form-control<?= $f->invalid('name') ?>" id="name" name="name" required maxlength="100" value="<?= $f->value('name') ?>" placeholder="z.B. Weitsprung">
                            <?= $f->feedback('name') ?>
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="sort">Reihenfolge</label>
                            <input class="form-control" id="sort" name="sort" type="number" value="<?= $f->value('sort', '0') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Beschreibung <span class="text-muted fw-normal">(erscheint auf der Startseite)</span></label>
                            <textarea class="form-control<?= $f->invalid('description') ?>" id="description" name="description" rows="2" maxlength="1000"><?= $f->value('description') ?></textarea>
                            <?= $f->feedback('description') ?>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="input_format">Messart</label>
                            <?php if ($locked): ?><input type="hidden" name="input_format" value="<?= e($editing['input_format']) ?>"><?php endif; ?>
                            <select class="form-select<?= $f->invalid('input_format') ?>" id="input_format" name="input_format" <?= $locked ? 'disabled' : '' ?>>
                                <option value="number"<?= $f->selected('input_format', 'number') ?>>Zahl (Weite, Punkte, …)</option>
                                <option value="time"<?= $f->selected('input_format', 'time') ?>>Zeit (1:23.45 oder 83.45)</option>
                            </select>
                            <?= $f->feedback('input_format') ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="higher_is_better">Wer gewinnt?</label>
                            <?php if ($locked): ?><input type="hidden" name="higher_is_better" value="<?= (int) $editing['higher_is_better'] ?>"><?php endif; ?>
                            <select class="form-select<?= $f->invalid('higher_is_better') ?>" id="higher_is_better" name="higher_is_better" <?= $locked ? 'disabled' : '' ?>>
                                <option value="1"<?= $f->selected('higher_is_better', '1') ?>>Höherer Wert (Weite, Punkte)</option>
                                <option value="0"<?= $f->selected('higher_is_better', '0') ?>>Tieferer Wert (Zeit)</option>
                            </select>
                            <?= $f->feedback('higher_is_better') ?>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label" for="unit">Einheit</label>
                            <input class="form-control<?= $f->invalid('unit') ?>" id="unit" name="unit" maxlength="20" value="<?= $f->value('unit') ?>" placeholder="m, s, Pkt.">
                            <?= $f->feedback('unit') ?>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label" for="decimals">Dezimalstellen</label>
                            <input class="form-control<?= $f->invalid('decimals') ?>" id="decimals" name="decimals" type="number" min="0" max="4" value="<?= $f->value('decimals', '2') ?>">
                            <?= $f->feedback('decimals') ?>
                        </div>
                        <?php if ($locked): ?>
                            <div class="col-12 form-text">Messart und Richtung sind gesperrt, weil schon Resultate erfasst wurden.</div>
                        <?php endif; ?>

                        <div class="col-md-6">
                            <label class="form-label" for="attempt_mode">Mehrere Versuche</label>
                            <select class="form-select<?= $f->invalid('attempt_mode') ?>" id="attempt_mode" name="attempt_mode">
                                <option value="best"<?= $f->selected('attempt_mode', 'best') ?>>Bester Versuch zählt</option>
                                <option value="last"<?= $f->selected('attempt_mode', 'last') ?>>Letzter Versuch zählt</option>
                            </select>
                            <?= $f->feedback('attempt_mode') ?>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="allow_negative" name="allow_negative" value="1"<?= $f->checked('allow_negative') ?>>
                                <label class="form-check-label" for="allow_negative">Negative Werte erlauben</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="parts">Teilmessungen <span class="text-muted fw-normal">(optional, eine pro Zeile)</span></label>
                            <?php if ($locked): ?><input type="hidden" name="parts" value="<?= e(implode("\n", $editing['parts'])) ?>"><?php endif; ?>
                            <textarea class="form-control<?= $f->invalid('parts') ?>" id="parts" name="parts" rows="3" placeholder="Schleuderball&#10;Frisbee" <?= $locked ? 'disabled' : '' ?>><?= $f->value('parts') ?></textarea>
                            <?= $f->feedback('parts') ?>
                            <div class="form-text">Bei mehreren Zeilen erfassen die Kampfrichter jede Teilmessung einzeln; gewertet wird die Summe.</div>
                        </div>

                        <div class="col-12"><hr class="my-1"></div>
                        <div class="col-12">
                            <h3 class="h6 mb-1">Handicap-Faktoren <span class="text-muted fw-normal">(nur wirksam, wenn beim Anlass das Handicap aktiv ist)</span></h3>
                            <p class="text-muted small mb-0">Ein Faktor von 1.2 bedeutet 20 % Vorteil: Bei «höher ist besser» wird der Wert mit 1.2 multipliziert, bei «tiefer ist besser» durch 1.2 geteilt. 1 = kein Bonus.</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="factor_female">Frauenbonus</label>
                            <input class="form-control<?= $f->invalid('factor_female') ?>" id="factor_female" name="factor_female" inputmode="decimal" value="<?= $f->value('factor_female', '1') ?>">
                            <?= $f->feedback('factor_female') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="factor_age">Altersbonus pro Jahr unter dem Referenzalter</label>
                            <input class="form-control<?= $f->invalid('factor_age') ?>" id="factor_age" name="factor_age" inputmode="decimal" value="<?= $f->value('factor_age', '1') ?>">
                            <?= $f->feedback('factor_age') ?>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="active" name="active" value="1"<?= $f->checked('active') ?>>
                                <label class="form-check-label" for="active">Aktiv <span class="text-muted">– für Kampfrichter wählbar und auf der Startseite sichtbar. Bestehende Resultate zählen auch bei inaktiven Disziplinen.</span></label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary" type="submit" data-once><?= $editing ? 'Speichern' : 'Anlegen' ?></button>
                        <?php if ($editing): ?><a class="btn btn-outline-primary" href="<?= e(url('admin/disziplinen.php')) ?>">Abbrechen</a><?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($editing): ?>
            <div class="col-lg-4">
                <section class="card">
                    <div class="card-body">
                        <h2 class="h5">Beispielrechnung</h2>
                        <p class="text-muted small">Mit den gespeicherten Werten:</p>
                        <ul class="small mb-0">
                            <?php foreach ($preview as $line): ?><li><?= e($line) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                </section>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
