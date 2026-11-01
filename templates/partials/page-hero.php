<?php
/**
 * Seitenkopf der Unterseiten (Rangliste, Startliste, Anmeldung) ab 0.11: Band in der Vereinsfarbe, rechts ein
 * leicht gekipptes Foto als Karte (vollständig sichtbar – ein breites Fotoband schnitt zufällige Fotos ab).
 * @var array $event  @var string $heroTitle  @var string $heroIcon  @var ?string $heroImage  URL
 * @var string $heroMeta  Zeile unter dem Titel (bereits escaped)  @var string $heroActions  HTML (Knöpfe), optional
 */
?>
<section class="page-hero print-hide">
    <div class="container">
        <div class="page-hero-text">
            <a class="page-hero-event" href="<?= e(url('index.php', \App\Http\App::eventQuery($event))) ?>"><?= e($event['title']) ?></a>
            <h1><span class="home-badge"><?= icon($heroIcon) ?></span><?= e($heroTitle) ?></h1>
            <?php if (($heroMeta ?? '') !== ''): ?><p class="page-hero-meta"><?= $heroMeta ?></p><?php endif; ?>
            <?php if (($heroActions ?? '') !== ''): ?><div class="page-hero-actions"><?= $heroActions ?></div><?php endif; ?>
        </div>
        <?php if (!empty($heroImage)): ?>
            <figure class="page-hero-snap"><img src="<?= e($heroImage) ?>" alt=""></figure>
        <?php endif; ?>
    </div>
</section>
