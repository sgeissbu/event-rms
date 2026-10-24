<?php
/** @var string $step */
$order = ['database' => 1, 'env-manual' => 1, 'verify' => 1, 'site' => 2, 'done' => 3];
$current = $order[$step] ?? 1;
$labels = [1 => 'Datenbank', 2 => 'Verein & Anlass', 3 => 'Fertig'];
?>
<div class="text-center mb-4">
    <span class="eyebrow">event-rms einrichten</span>
</div>
<ol class="stepper">
    <?php foreach ($labels as $n => $label): ?>
        <li class="<?= $n < $current ? 'done' : ($n === $current ? 'current' : '') ?>"><?= $n ?>. <?= e($label) ?></li>
    <?php endforeach; ?>
</ol>
