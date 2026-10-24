<?php
/**
 * @var array $judge  @var list<array> $disciplines  @var int $selectedDiscipline
 * @var list<array> $recent  @var ?string $error  @var array $old
 */
use App\Domain\ValueFormat;
use App\Repository\ResultRepository;

$event = $judge['event'];
$isTeam = (int) $event['team_size'] > 1;
$current = null;
foreach ($disciplines as $d) {
    if ($d['id'] === $selectedDiscipline) {
        $current = $d;
    }
}
$current ??= $disciplines[0] ?? null;
$oldValues = is_array($old['value'] ?? null) ? array_values($old['value']) : [];
$clientDisciplines = array_map(static fn (array $d): array => [
    'id' => $d['id'], 'unit' => $d['unit'], 'format' => $d['input_format'], 'parts' => $d['parts'],
], $disciplines);
$voidLimit = time() - ResultRepository::JUDGE_VOID_MINUTES * 60;
?>
<?php if ($disciplines === []): ?>
    <div class="alert alert-warning">Für diesen Anlass sind noch keine Disziplinen erfasst. Bitte die Wettkampfleitung informieren.</div>
<?php else: ?>
    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="card capture-card mb-4" id="capture-form" autocomplete="off"
          data-search-url="<?= e(url('judge/suche.php')) ?>" data-disciplines="<?= e(json_encode($clientDisciplines, JSON_UNESCAPED_UNICODE)) ?>">
        <div class="card-body">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">

            <div class="mb-3">
                <label class="form-label" for="discipline">Disziplin</label>
                <select class="form-select form-select-lg" id="discipline" name="discipline_id">
                    <?php foreach ($disciplines as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $current !== null && $d['id'] === $current['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3 position-relative">
                <label class="form-label" for="entry-search"><?= $isTeam ? 'Team' : 'Teilnehmer/in' ?></label>
                <input class="form-control form-control-lg" id="entry-search" type="search" inputmode="search"
                       placeholder="Startnummer oder Name" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="entry-results" aria-describedby="entry-hint"
                       value="<?= e(is_string($old['entry_label'] ?? null) ? $old['entry_label'] : '') ?>" autofocus>
                <input type="hidden" name="entry_id" id="entry-id" value="<?= e(is_string($old['entry_id'] ?? null) ? $old['entry_id'] : '') ?>">
                <input type="hidden" name="entry_label" id="entry-label" value="<?= e(is_string($old['entry_label'] ?? null) ? $old['entry_label'] : '') ?>">
                <ul class="search-results" id="entry-results" role="listbox" aria-label="Treffer" hidden></ul>
                <div class="form-text" id="entry-hint">Startnummer oder Name eintippen und einen Treffer wählen.</div>
                <div class="invalid-feedback" id="entry-error" role="alert" hidden>Bitte zuerst einen Treffer aus der Liste wählen.</div>
            </div>

            <div id="value-fields">
                <?php $labels = $current['parts'] ?: ['Resultat']; ?>
                <?php foreach ($labels as $i => $label): ?>
                    <div class="mb-3">
                        <label class="form-label" for="value-<?= $i ?>"><?= e($label) ?></label>
                        <div class="input-group input-group-lg">
                            <input class="form-control value-input" id="value-<?= $i ?>" name="value[]" required
                                   inputmode="<?= $current['input_format'] === 'time' ? 'text' : 'decimal' ?>"
                                   placeholder="<?= $current['input_format'] === 'time' ? '1:23.45 oder 83.45' : 'Wert eingeben' ?>"
                                   value="<?= e(is_string($oldValues[$i] ?? null) ? $oldValues[$i] : '') ?>">
                            <?php if ($current['unit'] !== ''): ?><span class="input-group-text"><?= e($current['unit']) ?></span><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (count($current['parts']) > 1): ?>
                    <p class="form-text">Die Teilmessungen werden automatisch zusammengezählt.</p>
                <?php endif; ?>
            </div>

            <div class="capture-actions">
                <button class="btn btn-primary btn-lg w-100" type="submit" data-once>Resultat speichern</button>
            </div>
        </div>
    </form>
<?php endif; ?>

<h2 class="h6 text-muted text-uppercase mb-2">Deine letzten Einträge</h2>
<?php if ($recent === []): ?>
    <p class="text-muted small">Noch keine Einträge.</p>
<?php else: ?>
    <ul class="recent-list">
        <?php foreach ($recent as $r):
            $voided = $r['voided_at'] !== null;
            $canVoid = !$voided && strtotime((string) $r['created_at']) >= $voidLimit; ?>
            <li class="<?= $voided ? 'is-voided' : '' ?>">
                <div class="min-w-0">
                    <div class="fw-semibold text-truncate">Nr. <?= (int) $r['start_no'] ?> <?= e($r['entry_name']) ?></div>
                    <div class="small text-muted">
                        <?= e($r['discipline_name']) ?> ·
                        <strong class="text-body"><?= e(ValueFormat::withUnit((float) $r['value'], (string) $r['input_format'], (int) $r['decimals'], (string) $r['unit'])) ?></strong>
                        · <?= e(date('H:i', strtotime((string) $r['created_at']))) ?><?= $voided ? ' · annulliert' : '' ?>
                    </div>
                </div>
                <?php if ($canVoid): ?>
                    <form method="post" class="flex-shrink-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="void">
                        <input type="hidden" name="result_id" value="<?= (int) $r['id'] ?>">
                        <button class="btn btn-sm btn-outline-primary" type="submit" data-once>Annullieren</button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
