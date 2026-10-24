<?php
/** Text-Variante der Bestätigungsmail (ohne HTML). @var array $event  @var array $data  @var int $startNo  @var string $clubName */
$start = new DateTimeImmutable((string) $event['starts_at']);
$isTeam = (int) $event['team_size'] > 1;
echo "Anmeldung bestätigt\n\n";
echo 'Vielen Dank für die Anmeldung zum ' . $event['title'] . ". Wir freuen uns!\n\n";
echo ($isTeam ? 'Team: ' : 'Name: ') . $data['name'] . "\nStartnummer: " . (int) $startNo . "\n";
if ($isTeam) {
    foreach ($data['members'] as $m) {
        echo '- ' . $m['name'] . "\n";
    }
}
echo "\nWann: " . format_event_date($start) . "\n";
if (!empty($event['location'])) {
    echo 'Wo: ' . $event['location'] . "\n";
}
if (!empty($event['schedule_text'])) {
    echo "\nZeitplan:\n" . $event['schedule_text'] . "\n";
}
if (!empty($event['bring_text'])) {
    echo "\nMitnehmen:\n" . $event['bring_text'] . "\n";
}
echo "\nSportliche Grüsse\n" . $clubName . "\n";
