<?php
/**
 * Anmeldeformular.
 * @var array $event  @var list<array> $memberConfig  @var list<array> $categories  @var bool $manualCategory
 * @var bool $open  @var array<string,string> $errors  @var array $old  @var ?array $done
 */
use App\Core\Settings;
use App\Domain\RegistrationValidator;

$isTeam = (int) $event['team_size'] > 1;
$invalid = static fn (string $key): string => isset($errors[$key]) ? ' is-invalid' : '';
$feedback = static fn (string $key): string => isset($errors[$key]) ? '<div class="invalid-feedback">' . e($errors[$key]) . '</div>' : '';
$oldMember = static fn (int $pos, string $field): string => is_string($old['members'][$pos][$field] ?? null) ? $old['members'][$pos][$field] : '';
$eventYear = (int) substr((string) $event['starts_at'], 0, 4);
$privacyContact = (string) Settings::get('privacy_contact');
?>
<div class="container-narrow mx-auto">
    <span class="eyebrow"><?= e($event['title']) ?></span>
    <h1 class="h2 mb-4">Anmeldung</h1>

    <?php if ($done !== null): ?>
        <div class="card mb-4">
            <div class="card-body text-center py-5">
                <div class="success-mark" aria-hidden="true">✓</div>
                <h2 class="h4">Danke, <?= e($done['name']) ?> ist angemeldet!</h2>
                <p class="mb-1">Startnummer <strong><?= (int) $done['start_no'] ?></strong></p>
                <p class="text-muted mb-4"><?= $done['mail'] ? 'Eine Bestätigung ist per E-Mail unterwegs.' : 'Bitte notiere dir die Startnummer.' ?></p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a class="btn btn-outline-primary" href="<?= e(url('startliste.php', \App\Http\App::eventQuery($event))) ?>">Zur Startliste</a>
                    <a class="btn btn-primary" href="<?= e(url('anmeldung.php', \App\Http\App::eventQuery($event))) ?>"><?= $isTeam ? 'Weiteres Team anmelden' : 'Weitere Person anmelden' ?></a>
                </div>
            </div>
        </div>
    <?php elseif (!$open): ?>
        <div class="card">
            <div class="card-body text-center py-5 text-muted">
                <p class="mb-0">Die Online-Anmeldung für <strong class="text-body"><?= e($event['title']) ?></strong> ist geschlossen.</p>
            </div>
        </div>
    <?php else: ?>
        <?php if (isset($errors['_'])): ?>
            <div class="alert alert-danger"><?= e($errors['_']) ?></div>
        <?php elseif ($errors): ?>
            <div class="alert alert-danger" role="alert">Bitte die markierten Angaben prüfen.</div>
        <?php endif; ?>

        <form method="post" novalidate class="registration-form">
            <?= csrf_field() ?>
            <div class="hp-field" aria-hidden="true">
                <label for="website">Website</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <?php if ($isTeam): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <label class="form-label" for="team-name">Teamname</label>
                        <input class="form-control form-control-lg<?= $invalid('name') ?>" id="team-name" name="name" required maxlength="100"
                               value="<?= e(is_string($old['name'] ?? null) ? $old['name'] : '') ?>" placeholder="z.B. Die schnellen Füchse">
                        <?= $feedback('name') ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach ($memberConfig as $i => $field): $pos = $i + 1; $prefix = "members[{$pos}]"; $id = "m{$pos}"; ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="h6 text-muted mb-3"><?= e($field['label']) ?></h2>
                        <div class="mb-3">
                            <label class="form-label" for="<?= $id ?>-name">Vorname und Name</label>
                            <input class="form-control<?= $invalid("members.{$pos}.name") ?>" id="<?= $id ?>-name" name="<?= $prefix ?>[name]" required maxlength="100"
                                   autocomplete="<?= $pos === 1 ? 'name' : 'off' ?>" value="<?= e($oldMember($pos, 'name')) ?>">
                            <?= $feedback("members.{$pos}.name") ?>
                            <?php if (!$isTeam && isset($errors['name'])): ?><div class="invalid-feedback d-block"><?= e($errors['name']) ?></div><?php endif; ?>
                        </div>
                        <div class="row g-3">
                            <?php if ($field['birth_year'] !== 'hidden'): ?>
                                <div class="col-6">
                                    <label class="form-label" for="<?= $id ?>-year">Jahrgang<?= $field['birth_year'] === 'optional' ? ' <span class="text-muted fw-normal">(optional)</span>' : '' ?></label>
                                    <input class="form-control<?= $invalid("members.{$pos}.birth_year") ?>" id="<?= $id ?>-year" name="<?= $prefix ?>[birth_year]"
                                           type="number" inputmode="numeric" min="<?= $eventYear - 110 ?>" max="<?= $eventYear ?>" placeholder="z.B. <?= $eventYear - 10 ?>"
                                           <?= $field['birth_year'] === 'required' ? 'required' : '' ?> value="<?= e($oldMember($pos, 'birth_year')) ?>">
                                    <?= $feedback("members.{$pos}.birth_year") ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($field['gender'] !== 'hidden'): ?>
                                <div class="col-6">
                                    <label class="form-label" for="<?= $id ?>-gender">Geschlecht<?= $field['gender'] === 'optional' ? ' <span class="text-muted fw-normal">(optional)</span>' : '' ?></label>
                                    <select class="form-select<?= $invalid("members.{$pos}.gender") ?>" id="<?= $id ?>-gender" name="<?= $prefix ?>[gender]" <?= $field['gender'] === 'required' ? 'required' : '' ?>>
                                        <option value="">Bitte wählen</option>
                                        <?php foreach (RegistrationValidator::GENDERS as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= $oldMember($pos, 'gender') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?= $feedback("members.{$pos}.gender") ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="card mb-3">
                <div class="card-body">
                    <?php if ($manualCategory): ?>
                        <div class="mb-3">
                            <label class="form-label" for="category">Kategorie</label>
                            <select class="form-select<?= $invalid('category_id') ?>" id="category" name="category_id" required>
                                <option value="">Bitte wählen</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>" <?= (string) ($old['category_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= $feedback('category_id') ?>
                        </div>
                    <?php elseif (isset($errors['category_id'])): ?>
                        <div class="alert alert-warning"><?= e($errors['category_id']) ?></div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label" for="email">E-Mail <span class="text-muted fw-normal">(optional, für die Bestätigung)</span></label>
                        <input class="form-control<?= $invalid('email') ?>" id="email" name="email" type="email" autocomplete="email" maxlength="191"
                               value="<?= e(is_string($old['email'] ?? null) ? $old['email'] : '') ?>" placeholder="name@beispiel.ch">
                        <?= $feedback('email') ?>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input<?= $invalid('consent') ?>" type="checkbox" id="consent" name="consent" value="1" required <?= !empty($old['consent']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="consent">
                            Ich bin einverstanden, dass die Angaben für die Durchführung des Anlasses gespeichert werden.
                            Auf Start- und Rangliste erscheint <?= $isTeam ? 'der Teamname' : 'der Name' ?><?= $event['name_display'] === 'short' ? ' (gekürzt)' : '' ?><?= $event['name_display'] === 'start_no' ? ' nur als Startnummer' : '' ?>,
                            Jahrgang und E-Mail werden nie veröffentlicht. Nach dem Anlass werden die Personendaten gelöscht oder anonymisiert.
                            <?php if ($privacyContact !== ''): ?>Fragen: <?= e($privacyContact) ?>.<?php endif; ?>
                            Kinder werden von einer erziehungsberechtigten Person angemeldet.
                        </label>
                        <?= $feedback('consent') ?>
                    </div>
                </div>
            </div>

            <button class="btn btn-primary btn-lg w-100" type="submit" data-once>Anmelden</button>
        </form>
    <?php endif; ?>
</div>
