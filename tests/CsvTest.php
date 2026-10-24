<?php
declare(strict_types=1);

use App\Domain\Csv;

return [
    'Zellen mit Trennzeichen, Anführungszeichen und Zeilenumbruch werden gequotet' => function (): void {
        assert_same('plain', Csv::cell('plain'));
        assert_same('"a;b"', Csv::cell('a;b'));
        assert_same('"sie sagte ""hi"""', Csv::cell('sie sagte "hi"'));
        assert_same("\"zwei\nzeilen\"", Csv::cell("zwei\nzeilen"));
        assert_same('', Csv::cell(null));
        assert_same('0', Csv::cell(0));
    },
    'Formel-Injektion wird entschärft, Zahlen bleiben Zahlen' => function (): void {
        assert_same("'=SUMME(A1)", Csv::cell('=SUMME(A1)'));
        assert_same("'+41 79 123", Csv::cell('+41 79 123'));
        assert_same("'@cmd", Csv::cell('@cmd'));
        assert_same("'-2+3", Csv::cell('-2+3'));
        assert_same('"\'=HYPERLINK(""x"";""y"")"', Csv::cell('=HYPERLINK("x";"y")'));
        assert_same('-5', Csv::cell('-5'));
        assert_same('-5.5', Csv::cell(-5.5));
    },
    'Zeile endet mit CRLF' => function (): void {
        assert_same("a;b;\"c;d\"\r\n", Csv::row(['a', 'b', 'c;d']));
    },
];
