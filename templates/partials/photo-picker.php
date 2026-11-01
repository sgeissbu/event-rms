<?php
/**
 * Auswahl eines Fotos aus den Kopfbild-Fotos («Verein & Design»), als Radio-Raster in einem aufklappbaren Bereich.
 * @var string $pickerName  Feldname  @var ?string $pickerValue  gewählter Pfad (relativ zu uploads/)
 * @var list<string> $photos  @var string $pickerLabel  @var string $pickerHelp  @var string $pickerNone  Text für «kein Foto»
 */
$pickerValue = in_array($pickerValue ?? '', $photos, true) ? $pickerValue : '';
?>
<div class="photo-picker">
    <span class="form-label d-block"><?= e($pickerLabel) ?> <span class="text-muted fw-normal">(optional)</span></span>
    <?php if ($photos === []): ?>
        <p class="form-text mb-0">Noch keine Fotos vorhanden. Lade sie unter <a href="<?= e(url('admin/einstellungen.php')) ?>">«Verein &amp; Design» → Fotos für den Kopfbereich</a> hoch – danach kannst du hier eines wählen.</p>
        <input type="hidden" name="<?= e($pickerName) ?>" value="">
    <?php else: ?>
        <details class="photo-picker-details">
            <summary>
                <?php if ($pickerValue !== ''): ?>
                    <img src="<?= e(asset('uploads/' . $pickerValue)) ?>" alt="" class="photo-picker-current">
                    <span>Foto ändern</span>
                <?php else: ?>
                    <span class="photo-picker-empty" aria-hidden="true"></span>
                    <span><?= e($pickerNone) ?> – Foto wählen</span>
                <?php endif; ?>
            </summary>
            <div class="photo-picker-grid" role="radiogroup" aria-label="<?= e($pickerLabel) ?>">
                <label class="photo-picker-item photo-picker-none">
                    <input type="radio" name="<?= e($pickerName) ?>" value=""<?= $pickerValue === '' ? ' checked' : '' ?>>
                    <span><?= e($pickerNone) ?></span>
                </label>
                <?php foreach ($photos as $i => $photo): ?>
                    <label class="photo-picker-item">
                        <input type="radio" name="<?= e($pickerName) ?>" value="<?= e($photo) ?>"<?= $pickerValue === $photo ? ' checked' : '' ?>>
                        <img src="<?= e(asset('uploads/' . $photo)) ?>" alt="Foto <?= $i + 1 ?>" loading="lazy">
                    </label>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>
    <?php if ($pickerHelp !== ''): ?><div class="form-text"><?= e($pickerHelp) ?></div><?php endif; ?>
</div>
