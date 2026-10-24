<?php
declare(strict_types=1);

use App\Domain\CategoryValidator;

return [
    'Gültige Kategorie mit Jahrgangsbereich' => function (): void {
        $r = CategoryValidator::validate(['name' => ' U10 Mädchen ', 'gender' => 'F', 'birth_year_from' => '2018', 'birth_year_to' => '2019', 'sort' => '3'], 2027);
        assert_same([], $r['errors']);
        assert_same(['name' => 'U10 Mädchen', 'gender' => 'F', 'birth_year_from' => 2018, 'birth_year_to' => 2019, 'sort' => 3], $r['values']);
    },
    'Leere Jahrgänge = egal, Geschlecht Standard «any»' => function (): void {
        $r = CategoryValidator::validate(['name' => 'Alle'], 2027);
        assert_same([], $r['errors']);
        assert_same([null, null, 'any'], [$r['values']['birth_year_from'], $r['values']['birth_year_to'], $r['values']['gender']]);
    },
    'Fehler: Name, Geschlecht, Jahrgänge, von > bis' => function (): void {
        $r = CategoryValidator::validate(['name' => '', 'gender' => 'Q', 'birth_year_from' => '2020', 'birth_year_to' => '2015'], 2027);
        assert_same(['name', 'gender', 'birth_year_to'], array_keys($r['errors']));
        $r = CategoryValidator::validate(['name' => 'X', 'birth_year_from' => '20x0', 'birth_year_to' => '2031'], 2027);
        assert_same(['birth_year_from', 'birth_year_to'], array_keys($r['errors']));
    },
    'Lesbare Regel' => function (): void {
        $c = static fn ($g, $f, $t) => ['gender' => $g, 'birth_year_from' => $f, 'birth_year_to' => $t];
        assert_same('weiblich, Jg. 2018–2019', CategoryValidator::describe($c('F', 2018, 2019)));
        assert_same('Jg. 2015 und jünger', CategoryValidator::describe($c('any', 2015, null)));
        assert_same('männlich, Jg. 2010 und älter', CategoryValidator::describe($c('M', null, 2010)));
        assert_same('Jg. 2016', CategoryValidator::describe($c('any', 2016, 2016)));
        assert_same('alle (manuelle Wahl)', CategoryValidator::describe($c('any', null, null)));
    },
];
