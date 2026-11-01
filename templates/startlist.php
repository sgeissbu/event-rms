<?php
/**
 * Startliste (ab 0.11): Kacheln mit Startnummer als Sticker, nach Kategorien gruppiert.
 * @var array $event  @var list<array{category:?array, entries:list<array>}> $groups  @var int $total
 */
use App\Domain\NameDisplay;

$teamSize = (int) $event['team_size'];
$mode = (string) $event['name_display'];
?>
<div class="print-only mb-3">
    <span class="eyebrow"><?= e($event['title']) ?></span>
    <h1 class="h2 mb-0">Startliste</h1>
    <p class="small mb-0">Stand: <?= e(date('d.m.Y H:i')) ?> Uhr · <?= $total ?> <?= $teamSize > 1 ? 'Teams' : 'Personen' ?></p>
</div>

<?php if ($groups === []): ?>
    <div class="empty-state">
        <p class="mb-0">Noch keine Anmeldungen.</p>
    </div>
<?php endif; ?>

<div class="startlist">
    <?php foreach ($groups as $group): ?>
        <section aria-label="<?= e($group['category']['name'] ?? 'Startliste') ?>">
            <?php if ($group['category'] !== null || count($groups) > 1): ?>
                <div class="section-head">
                    <h2 class="home-h"><span class="home-badge"><?= icon('users') ?></span><?= e($group['category']['name'] ?? 'Ohne Kategorie') ?></h2>
                    <span class="section-count"><?= count($group['entries']) ?> <?= $teamSize > 1 ? (count($group['entries']) === 1 ? 'Team' : 'Teams') : (count($group['entries']) === 1 ? 'Person' : 'Personen') ?></span>
                </div>
            <?php endif; ?>
            <ul class="entry-tiles">
                <?php foreach ($group['entries'] as $entry): ?>
                    <li>
                        <span class="entry-no"><span class="visually-hidden">Startnummer </span><?= (int) $entry['start_no'] ?></span>
                        <span class="entry-text">
                            <span class="entry-name"><?= e(NameDisplay::entry($entry, $mode, $teamSize)) ?></span>
                            <?php if ($teamSize > 1 && $mode !== 'start_no'):
                                $names = array_filter(array_map(static fn (array $m): string => NameDisplay::member($m['name'], $mode), $entry['members'])); ?>
                                <?php if ($names): ?><span class="entry-members"><?= e(implode(' · ', $names)) ?></span><?php endif; ?>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
</div>
