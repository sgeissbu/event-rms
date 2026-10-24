<?php
/**
 * @var array $event  @var list<array{category:?array, entries:list<array>}> $groups  @var int $total
 */
use App\Domain\NameDisplay;

$teamSize = (int) $event['team_size'];
$mode = (string) $event['name_display'];
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-4">
    <div>
        <span class="eyebrow"><?= e($event['title']) ?></span>
        <h1 class="h2 mb-0">Startliste</h1>
        <p class="print-only small mb-0">Stand: <?= e(date('d.m.Y H:i')) ?> Uhr</p>
    </div>
    <span class="badge-soft"><?= $total ?> <?= $teamSize > 1 ? ($total === 1 ? 'Team' : 'Teams') : ($total === 1 ? 'Person' : 'Personen') ?></span>
</div>

<?php if ($groups === []): ?>
    <div class="empty-state">
        <p class="mb-0">Noch keine Anmeldungen.</p>
    </div>
<?php endif; ?>

<?php foreach ($groups as $group): ?>
    <section class="card mb-4">
        <?php if ($group['category'] !== null || count($groups) > 1): ?>
            <div class="card-header-plain">
                <h2 class="h5 mb-0"><?= e($group['category']['name'] ?? 'Ohne Kategorie') ?></h2>
                <span class="text-muted small"><?= count($group['entries']) ?></span>
            </div>
        <?php endif; ?>
        <ul class="entry-list">
            <?php foreach ($group['entries'] as $entry): ?>
                <li>
                    <span class="start-no"><?= (int) $entry['start_no'] ?></span>
                    <span>
                        <span class="fw-semibold"><?= e(NameDisplay::entry($entry, $mode, $teamSize)) ?></span>
                        <?php if ($teamSize > 1 && $mode !== 'start_no'):
                            $names = array_filter(array_map(static fn (array $m): string => NameDisplay::member($m['name'], $mode), $entry['members'])); ?>
                            <?php if ($names): ?><span class="d-block small text-muted"><?= e(implode(' · ', $names)) ?></span><?php endif; ?>
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endforeach; ?>
