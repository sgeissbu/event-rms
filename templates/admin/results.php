<?php
/**
 * @var array $event  @var array $filter  @var int $page  @var bool $readOnly  @var bool $adding  @var list<array> $disciplines
 * @var array $returnQuery  @var ?string $error  @var array $old
 * Liste: @var list<array> $rows  @var int $total  @var int $pages  @var list<string> $judges
 * Nachtragen: @var int $selectedDiscipline
 */
use App\Domain\ValueFormat;
use App\Repository\ResultRepository;

$isTeam = (int) $event['team_size'] > 1;
?>
<span class="eyebrow"><?= e($event['title']) ?></span>

<?php if ($adding): ?>
    <a class="small" href="<?= e(url('admin/resultate.php')) ?>">← Alle Resultate</a>
    <h1 class="h2 mt-2 mb-2">Resultat nachtragen</h1>
    <p class="text-muted mb-4">Für Resultate, die nicht über die Kampfrichter-Seite erfasst wurden (z.B. von einem Zettel). Der Eintrag erscheint mit deinem Namen als «(Admin)».</p>

    <?php if ($disciplines === []): ?>
        <div class="alert alert-warning">Es gibt noch keine Disziplinen. Bitte zuerst unter «Disziplinen» welche anlegen.</div>
    <?php else: ?>
        <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="card" novalidate style="max-width: 36rem">
            <div class="card-body">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="row g-3">
                    <div class="col-5">
                        <label class="form-label" for="start_no">Startnummer</label>
                        <input class="form-control" id="start_no" name="start_no" type="number" min="1" inputmode="numeric" required autofocus
                               value="<?= e(is_scalar($old['start_no'] ?? null) ? (string) $old['start_no'] : '') ?>">
                    </div>
                    <div class="col-7">
                        <label class="form-label" for="discipline_id">Disziplin</label>
                        <select class="form-select" id="discipline_id" name="discipline_id" data-discipline-select>
                            <?php foreach ($disciplines as $d): ?>
                                <option value="<?= $d['id'] ?>"<?= $d['id'] === $selectedDiscipline ? ' selected' : '' ?>><?= e($d['name']) ?><?= $d['active'] ? '' : ' (inaktiv)' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php foreach ($disciplines as $d):
                        $labels = $d['parts'] ?: ['Resultat'];
                        $oldValues = is_array($old['value'][$d['id']] ?? null) ? array_values($old['value'][$d['id']]) : []; ?>
                        <div class="col-12" data-discipline-fields="<?= $d['id'] ?>">
                            <div class="row g-3">
                                <?php foreach ($labels as $i => $label): ?>
                                    <div class="col-<?= count($labels) > 1 ? '6' : '12' ?>">
                                        <label class="form-label" for="v<?= $d['id'] ?>-<?= $i ?>"><?= e($label) ?></label>
                                        <div class="input-group">
                                            <input class="form-control" id="v<?= $d['id'] ?>-<?= $i ?>" name="value[<?= $d['id'] ?>][]" inputmode="<?= $d['input_format'] === 'time' ? 'text' : 'decimal' ?>"
                                                   placeholder="<?= $d['input_format'] === 'time' ? '1:23.45 oder 83.45' : '0.00' ?>" value="<?= e(is_string($oldValues[$i] ?? null) ? $oldValues[$i] : '') ?>">
                                            <?php if ($d['unit'] !== ''): ?><span class="input-group-text"><?= e($d['unit']) ?></span><?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="d-flex gap-2 mt-4">
                    <button class="btn btn-primary" type="submit" data-once>Speichern</button>
                    <a class="btn btn-outline-primary" href="<?= e(url('admin/resultate.php')) ?>">Fertig</a>
                </div>
            </div>
        </form>
    <?php endif; ?>

<?php else: ?>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <h1 class="h2 mb-0">Resultate <span class="text-muted fs-5 fw-normal"><?= $total ?></span></h1>
        <?php if (!$readOnly): ?><a class="btn btn-primary" href="<?= e(url('admin/resultate.php', ['add' => 1])) ?>">Resultat nachtragen</a><?php endif; ?>
    </div>

    <form method="get" class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-3">
                    <label class="form-label small" for="d">Disziplin</label>
                    <select class="form-select" id="d" name="d">
                        <option value="">Alle</option>
                        <?php foreach ($disciplines as $d): ?>
                            <option value="<?= $d['id'] ?>"<?= $filter['discipline'] === $d['id'] ? ' selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small" for="j">Kampfrichter</label>
                    <select class="form-select" id="j" name="j">
                        <option value="">Alle</option>
                        <?php foreach ($judges as $j): ?>
                            <option value="<?= e($j) ?>"<?= $filter['judge'] === $j ? ' selected' : '' ?>><?= e($j) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small" for="s">Status</label>
                    <select class="form-select" id="s" name="s">
                        <option value="all"<?= $filter['status'] === 'all' ? ' selected' : '' ?>>Alle</option>
                        <option value="valid"<?= $filter['status'] === 'valid' ? ' selected' : '' ?>>Gültig</option>
                        <option value="void"<?= $filter['status'] === 'void' ? ' selected' : '' ?>>Annulliert</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small" for="q">Nr. / Name</label>
                    <input class="form-control" id="q" name="q" value="<?= e($filter['q']) ?>">
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button class="btn btn-outline-primary flex-grow-1" type="submit">Filtern</button>
                    <?php if ($returnQuery): ?><a class="btn btn-outline-primary" href="<?= e(url('admin/resultate.php')) ?>" title="Filter zurücksetzen">✕</a><?php endif; ?>
                </div>
            </div>
        </div>
    </form>

    <section class="card">
        <?php if ($rows === []): ?>
            <p class="text-muted p-4 mb-0"><?= $returnQuery ? 'Keine Resultate für diese Filter.' : 'Noch keine Resultate erfasst.' ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table admin-table mb-0">
                    <thead><tr><th>Zeit</th><th><?= $isTeam ? 'Team' : 'Name' ?></th><th>Disziplin</th><th class="text-end">Resultat</th><th>Kampfrichter</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($rows as $r):
                            $voided = $r['voided_at'] !== null;
                            $parts = $r['parts'] !== null ? (json_decode((string) $r['parts'], true) ?: []) : []; ?>
                            <tr class="<?= $voided ? 'is-voided' : '' ?>">
                                <td class="text-nowrap text-muted small"><?= e((new DateTimeImmutable((string) $r['created_at']))->format('d.m. H:i')) ?></td>
                                <td><span class="start-no"><?= (int) $r['start_no'] ?></span> <span class="fw-semibold"><?= e($r['entry_name']) ?></span></td>
                                <td><?= e($r['discipline_name']) ?></td>
                                <td class="text-end text-nowrap">
                                    <strong><?= e(ValueFormat::withUnit((float) $r['value'], (string) $r['input_format'], (int) $r['decimals'], (string) $r['unit'])) ?></strong>
                                    <?php if ($parts): ?><span class="d-block small text-muted"><?= e(implode(' + ', array_map(fn ($p) => ValueFormat::format((float) $p, (string) $r['input_format'], (int) $r['decimals']), $parts))) ?></span><?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= e($r['judge_name']) ?><?= $voided ? '<span class="d-block">annulliert' . ($r['voided_by'] ? ' von ' . e($r['voided_by']) : '') . '</span>' : '' ?></td>
                                <td class="text-end text-nowrap">
                                    <?php if (!$readOnly): ?>
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="<?= $voided ? 'restore' : 'void' ?>">
                                            <input type="hidden" name="result_id" value="<?= (int) $r['id'] ?>">
                                            <button class="btn btn-sm btn-outline-primary" type="submit" data-once><?= $voided ? 'Wiederherstellen' : 'Annullieren' ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pages > 1): ?>
                <nav class="d-flex justify-content-between align-items-center p-3 border-top" aria-label="Seiten">
                    <?php $link = static fn (int $p): string => e(url('admin/resultate.php', array_filter(['d' => $filter['discipline'] ?: null, 'j' => $filter['judge'] ?: null,
                        's' => $filter['status'] !== 'all' ? $filter['status'] : null, 'q' => $filter['q'] ?: null, 'page' => $p > 1 ? $p : null]))); ?>
                    <?php if ($page > 1): ?><a class="btn btn-sm btn-outline-primary" href="<?= $link($page - 1) ?>">← Neuere</a><?php else: ?><span></span><?php endif; ?>
                    <span class="small text-muted">Seite <?= $page ?> von <?= $pages ?></span>
                    <?php if ($page < $pages): ?><a class="btn btn-sm btn-outline-primary" href="<?= $link($page + 1) ?>">Ältere →</a><?php else: ?><span></span><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <p class="small text-muted mt-3">Annullierte Resultate zählen nicht für die Rangliste, bleiben aber gespeichert und lassen sich wiederherstellen.</p>
<?php endif; ?>
