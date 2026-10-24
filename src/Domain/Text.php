<?php
declare(strict_types=1);

namespace App\Domain;

final class Text
{
    /** URL-tauglicher Kurzname: «Oldies & Kids Cup 2026» → «oldies-kids-cup-2026». */
    public static function slug(string $text, int $maxLength = 60): string
    {
        $map = ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue', 'ß' => 'ss',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'â' => 'a', 'ç' => 'c', 'ô' => 'o', 'î' => 'i', 'ï' => 'i'];
        $text = strtr($text, $map);
        $text = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $text));
        $text = trim($text, '-');
        return rtrim(substr($text, 0, $maxLength), '-');
    }

    /** Initialen für das Platzhalter-Logo: «Jugi Langenthal» → «JL». */
    public static function initials(string $name, int $max = 2): string
    {
        $initials = '';
        foreach (preg_split('/[\s\-]+/u', trim($name)) ?: [] as $word) {
            if ($word !== '' && preg_match('/^\p{L}|\d/u', $word, $m)) {
                $initials .= mb_strtoupper($m[0]);
            }
            if (mb_strlen($initials) >= $max) {
                break;
            }
        }
        return $initials !== '' ? $initials : '?';
    }
}
