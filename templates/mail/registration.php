<?php
/**
 * HTML-Bestätigungsmail (ab 0.12.1 im Stil der Website): Kopfbalken in der Vereinsfarbe, Startnummer gross.
 * Tabellen und Inline-Stile, keine Bilder und keine Webfonts – so sieht sie in Outlook, Gmail und Apple Mail gleich aus.
 * @var array $event  @var array $data  @var int $startNo  @var string $clubName  @var ?string $brandHex  @var ?string $brandText  @var ?string $accentHex  @var ?string $accentText
 */
$start = new DateTimeImmutable((string) $event['starts_at']);
$isTeam = (int) $event['team_size'] > 1;
$brand = $brandHex ?? '#1D284C';
$brandInk = $brandText ?? '#FFFFFF';
$accent = $accentHex ?? '#FEC028';
$accentInk = $accentText ?? '#111827';
$font = "system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif";
$h3 = 'margin: 22px 0 6px; font-size: 16px; color: #111827;';
?>
<div style="background: #F3F4F6; padding: 24px 12px; font-family: <?= $font ?>;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px; margin: 0 auto; background: #FFFFFF; border-radius: 12px; overflow: hidden; border-collapse: separate;">
    <tr>
        <td style="background: <?= e($brand) ?>; color: <?= e($brandInk) ?>; padding: 20px 24px;">
            <div style="font-size: 13px; font-weight: 600; opacity: 0.9;"><?= e($clubName) ?></div>
            <div style="font-size: 22px; font-weight: 800; line-height: 1.25; margin-top: 2px;"><?= e($event['title']) ?></div>
        </td>
    </tr>
    <tr>
        <td style="padding: 24px; font-size: 15px; line-height: 1.55; color: #111827;">
            <h2 style="margin: 0 0 8px; font-size: 20px;">Anmeldung bestätigt</h2>
            <p style="margin: 0 0 18px;">Vielen Dank für die Anmeldung – wir freuen uns auf <?= $isTeam ? 'euch' : 'dich' ?>!</p>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #F6F7F9; border-radius: 10px;">
                <tr>
                    <td style="padding: 14px 16px; vertical-align: middle;">
                        <div style="font-size: 13px; color: #5B6472;"><?= $isTeam ? 'Team' : 'Name' ?></div>
                        <div style="font-size: 17px; font-weight: 700;"><?= e($data['name']) ?></div>
                        <?php if ($isTeam): ?>
                            <div style="font-size: 14px; color: #5B6472; margin-top: 2px;"><?= e(implode(' · ', array_filter(array_map(static fn (array $m): string => (string) ($m['name'] ?? ''), $data['members'])))) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 14px 16px; text-align: right; vertical-align: middle; width: 1%; white-space: nowrap;">
                        <div style="font-size: 12px; color: #5B6472;">Startnummer</div>
                        <div style="display: inline-block; margin-top: 2px; padding: 6px 14px; border-radius: 10px; background: <?= e($accent) ?>; color: <?= e($accentInk) ?>; font-size: 26px; font-weight: 800; line-height: 1;"><?= (int) $startNo ?></div>
                    </td>
                </tr>
            </table>

            <p style="margin: 18px 0 0;"><strong>Wann:</strong> <?= e(format_event_date($start)) ?>
                <?php if (!empty($event['location'])): ?><br><strong>Wo:</strong> <?= e($event['location']) ?><?php endif; ?></p>
            <?php if (!empty($event['schedule_text'])): ?>
                <h3 style="<?= $h3 ?>">Zeitplan</h3><?= format_text((string) $event['schedule_text']) ?>
            <?php endif; ?>
            <?php if (!empty($event['bring_text'])): ?>
                <h3 style="<?= $h3 ?>">Mitnehmen</h3><?= format_text((string) $event['bring_text']) ?>
            <?php endif; ?>
            <p style="margin: 24px 0 0;">Sportliche Grüsse<br><?= e($clubName) ?></p>
        </td>
    </tr>
</table>
</div>
