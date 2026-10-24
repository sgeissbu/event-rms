<?php
/** HTML-Bestätigungsmail. @var array $event  @var array $data  @var int $startNo  @var string $clubName */
$start = new DateTimeImmutable((string) $event['starts_at']);
$isTeam = (int) $event['team_size'] > 1;
?>
<div style="font-family: system-ui, -apple-system, 'Segoe UI', Arial, sans-serif; font-size: 15px; line-height: 1.5; color: #111827; max-width: 560px;">
    <h2 style="margin: 0 0 12px;">Anmeldung bestätigt</h2>
    <p>Vielen Dank für die Anmeldung zum <strong><?= e($event['title']) ?></strong>. Wir freuen uns!</p>
    <div style="background: #F3F4F6; border-radius: 8px; padding: 14px 16px; margin: 16px 0;">
        <?= $isTeam ? 'Team' : 'Name' ?>: <strong><?= e($data['name']) ?></strong><br>
        Startnummer: <strong><?= (int) $startNo ?></strong>
        <?php if ($isTeam): ?>
            <br><?php foreach ($data['members'] as $m): ?><?= e($m['name']) ?><br><?php endforeach; ?>
        <?php endif; ?>
    </div>
    <p><strong>Wann:</strong> <?= e(format_event_date($start)) ?>
        <?php if (!empty($event['location'])): ?><br><strong>Wo:</strong> <?= e($event['location']) ?><?php endif; ?></p>
    <?php if (!empty($event['schedule_text'])): ?>
        <h3 style="margin: 20px 0 6px; font-size: 16px;">Zeitplan</h3><?= format_text((string) $event['schedule_text']) ?>
    <?php endif; ?>
    <?php if (!empty($event['bring_text'])): ?>
        <h3 style="margin: 20px 0 6px; font-size: 16px;">Mitnehmen</h3><?= format_text((string) $event['bring_text']) ?>
    <?php endif; ?>
    <p style="margin-top: 24px;">Sportliche Grüsse<br><?= e($clubName) ?></p>
</div>
