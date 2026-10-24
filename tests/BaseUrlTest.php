<?php
declare(strict_types=1);

use App\Core\Env;
use App\Install\Installer;

/** Führt $fn mit gesetzten Server-/Env-Werten aus und stellt danach alles zurück. */
$with = static function (array $server, ?string $appUrl, callable $fn) {
    $backup = $_SERVER;
    $_SERVER = array_merge($_SERVER, ['SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => APP_ROOT . '/index.php', 'HTTPS' => 'off', 'SERVER_PORT' => '80'], $server);
    Env::set('APP_URL', $appUrl);
    try {
        return $fn();
    } finally {
        $_SERVER = $backup;
        Env::set('APP_URL', null);
    }
};

return [
    'APP_URL hat Vorrang vor dem Host der Anfrage (Schutz vor Host-Header-Fälschung)' => function () use ($with): void {
        $url = $with(['HTTP_HOST' => 'boese.example'], 'https://anlass.meinverein.ch/', static fn () => base_url());
        assert_same('https://anlass.meinverein.ch', $url);
        $sub = $with(['HTTP_HOST' => 'boese.example'], 'https://meinverein.ch/events', static fn () => base_url());
        assert_same('https://meinverein.ch/events', $sub);
    },
    'Ohne APP_URL: gültiger Host wird übernommen, Schema folgt HTTPS' => function () use ($with): void {
        assert_same('http://localhost:8765', $with(['HTTP_HOST' => 'localhost:8765'], null, static fn () => rtrim(base_url(), '/')));
        assert_same('https://anlass.ch', $with(['HTTP_HOST' => 'anlass.ch', 'HTTPS' => 'on'], null, static fn () => rtrim(base_url(), '/')));
    },
    'Ohne APP_URL: Host-Header mit Sonderzeichen wird verworfen' => function () use ($with): void {
        foreach (['boese.example/pfad', 'a b', "x\r\nLocation: y", 'evil.example@good.example', '<script>', ''] as $host) {
            $url = $with(['HTTP_HOST' => $host], null, static fn () => base_url());
            assert_true(str_starts_with($url, 'http://localhost'), "Host «{$host}» → {$url}");
        }
    },
    'Ungültige APP_URL wird ignoriert' => function () use ($with): void {
        foreach (['javascript:alert(1)', 'ftp://x.ch', 'https://', 'https://a b.ch', 'anlass.ch'] as $bad) {
            $url = $with(['HTTP_HOST' => 'anlass.ch'], $bad, static fn () => base_url());
            assert_same('http://anlass.ch', rtrim($url, '/'), "APP_URL «{$bad}»");
        }
    },
    'Installer schreibt APP_URL in die .env' => function (): void {
        $env = Env::parse(Installer::envContents(['host' => 'localhost', 'port' => '3306', 'name' => 'x', 'user' => 'y', 'pass' => 'z'], 'https://anlass.ch'));
        assert_same('https://anlass.ch', $env['APP_URL']);
    },
];
