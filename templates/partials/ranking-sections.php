<?php
/**
 * Ranglisten-Abschnitte (auch einzeln abrufbar für die Live-Aktualisierung).
 * @var array $event  @var array{sections: list<array>, disciplines: array<int, array>, entries: array<int, array>} $ranking
 */
use App\Domain\NameDisplay;
use App\Domain\ValueFormat;

$teamSize = (int) $event['team_size'];
$mode = (string) $event['name_display'];
$value = static function (array $detail, array $d): string {
    if ($detail['penalty']) {
        return '–';
    }
    return ValueFormat::withUnit($detail['raw'], (string) $d['input_format'], (int) $d['decimals'], (string) $d['unit']);
};
$medal = static fn (int $rank): string => $rank <= 3 ? ' rank-' . $rank : '';
?>
<?php if ($ranking['sections'] === []): ?>
    <div class="empty-state"><p class="mb-0">Noch keine Anmeldungen.</p></div>
<?php endif; ?>

<?php foreach ($ranking['sections'] as $section):
    $counted = array_values(array_filter($section['counted'], static fn (int $id): bool => isset($ranking['disciplines'][$id])));
    $hasResults = $counted !== [];
?>
    <section class="ranking-section" aria-label="<?= e($section['category']['name'] ?? 'Rangliste') ?>">
        <div class="section-head">
            <h2 class="home-h"><span class="home-badge"><?= icon('medal') ?></span><?= e($section['category']['name'] ?? (count($ranking['sections']) > 1 ? 'Ohne Kategorie' : 'Gesamt')) ?></h2>
            <span class="section-count"><?= count($section['rows']) ?> <?= $teamSize > 1 ? 'Teams' : 'Teilnehmende' ?></span>
        </div>

        <?php if (!$hasResults): ?>
            <div class="card"><p class="text-muted px-4 py-3 mb-0">Noch keine Resultate erfasst.</p></div>
        <?php else: ?>
            <?php
            // Podest: Plätze 1–3 (bei Gleichstand auch mehr). Klassische Anordnung 2-1-3 nur bei genau drei Plätzen.
            $podium = array_values(array_filter($section['rows'], static fn (array $r): bool => $r['rank'] <= 3));
            $classic = count($podium) === 3 && array_column($podium, 'rank') === [1, 2, 3];
            ?>
            <?php if ($podium !== []): ?>
                <ol class="podium<?= $classic ? ' podium--classic' : '' ?>" aria-label="Podest">
                    <?php foreach ($podium as $row): $entry = $ranking['entries'][$row['entry_id']]; ?>
                        <li class="podium-place podium-<?= $row['rank'] ?>">
                            <span class="podium-rank"><?= $row['rank'] ?><span class="visually-hidden">. Rang</span></span>
                            <span class="podium-name"><?= e(NameDisplay::entry($entry, $mode, $teamSize)) ?></span>
                            <?php if ($teamSize > 1 && $mode !== 'start_no' && !empty($entry['members'])):
                                $names = array_filter(array_map(static fn (array $m): string => NameDisplay::member((string) ($m['name'] ?? ''), $mode), $entry['members'])); ?>
                                <?php if ($names): ?><span class="podium-members"><?= e(implode(' · ', $names)) ?></span><?php endif; ?>
                            <?php endif; ?>
                            <span class="podium-points"><?= $row['points'] ?> Punkte</span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
            <div class="card ranking-card">
            <!-- Desktop: Tabelle -->
            <div class="table-responsive d-none d-md-block">
                <table class="table ranking-table mb-0">
                    <thead>
                        <tr>
                            <th class="text-center">Rang</th>
                            <th><?= $teamSize > 1 ? 'Team' : 'Name' ?></th>
                            <th class="text-center col-total">Punkte</th>
                            <?php foreach ($counted as $id): ?>
                                <th class="text-center"><?= e($ranking['disciplines'][$id]['name']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($section['rows'] as $row): $entry = $ranking['entries'][$row['entry_id']]; ?>
                            <tr>
                                <td class="text-center"><span class="rank-badge<?= $medal($row['rank']) ?>"><?= $row['rank'] ?></span></td>
                                <td class="fw-semibold"><?= e(NameDisplay::entry($entry, $mode, $teamSize)) ?></td>
                                <td class="text-center col-total fw-bold"><?= $row['points'] ?></td>
                                <?php foreach ($counted as $id): $detail = $row['details'][$id]; ?>
                                    <td class="text-center">
                                        <span class="fw-semibold<?= $detail['penalty'] ? ' text-muted' : '' ?>"><?= $detail['rank'] ?></span>
                                        <span class="d-block small text-muted"><?= e($value($detail, $ranking['disciplines'][$id])) ?></span>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile: Karten mit aufklappbaren Details -->
            <ol class="ranking-list d-md-none">
                <?php foreach ($section['rows'] as $row): $entry = $ranking['entries'][$row['entry_id']]; ?>
                    <li>
                        <details>
                            <summary>
                                <span class="rank-badge<?= $medal($row['rank']) ?>"><?= $row['rank'] ?></span>
                                <span class="ranking-name"><?= e(NameDisplay::entry($entry, $mode, $teamSize)) ?></span>
                                <span class="ranking-points"><?= $row['points'] ?> <small>Pkt.</small></span>
                            </summary>
                            <dl class="ranking-details">
                                <?php foreach ($counted as $id): $detail = $row['details'][$id]; ?>
                                    <dt><?= e($ranking['disciplines'][$id]['name']) ?></dt>
                                    <dd><?= e($value($detail, $ranking['disciplines'][$id])) ?> <span class="text-muted">· Rang <?= $detail['rank'] ?></span></dd>
                                <?php endforeach; ?>
                            </dl>
                        </details>
                    </li>
                <?php endforeach; ?>
            </ol>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
