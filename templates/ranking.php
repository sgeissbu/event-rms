<?php
/**
 * @var array $event  @var array $ranking  @var bool $live  @var bool $previewOnly
 */
use App\Http\App;

$partialUrl = url('rangliste.php', App::eventQuery($event) + ['partial' => 1]);
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-4">
    <div>
        <span class="eyebrow"><?= e($event['title']) ?></span>
        <h1 class="h2 mb-0">Rangliste</h1>
    </div>
    <?php if ($live): ?>
        <span class="live-badge" aria-live="polite"><span class="live-dot"></span>Live · aktualisiert sich automatisch</span>
    <?php endif; ?>
</div>

<?php if ($previewOnly): ?>
    <div class="alert alert-info">Vorschau für Kampfrichter – öffentlich noch nicht sichtbar.</div>
<?php endif; ?>

<div id="ranking-sections" <?= $live ? 'data-refresh-url="' . e($partialUrl) . '" data-refresh-seconds="30"' : '' ?>>
    <?php require __DIR__ . '/partials/ranking-sections.php'; ?>
</div>

<?php if (!empty($event['handicap_enabled'])): ?>
    <div class="card mt-4">
        <div class="card-body small text-muted">
            <h2 class="h6 text-body">So wird gewertet</h2>
            <p>Pro Disziplin gibt es Rangpunkte (1. Rang = 1 Punkt). Wer in einer Disziplin kein Resultat hat, erhält die Anzahl
                Teilnehmenden mit Resultat plus 1. Es gewinnt, wer am wenigsten Punkte hat.</p>
            <p class="mb-0">Für die Rangierung werden die Messwerte mit Bonusfaktoren ausgeglichen
                (je Disziplin z.B. für Alter oder Geschlecht). Angezeigt werden die tatsächlich gemessenen Werte.</p>
        </div>
    </div>
<?php else: ?>
    <p class="small text-muted mt-4">Pro Disziplin gibt es Rangpunkte (1. Rang = 1 Punkt; ohne Resultat: Anzahl mit Resultat + 1). Es gewinnt, wer am wenigsten Punkte hat.</p>
<?php endif; ?>
