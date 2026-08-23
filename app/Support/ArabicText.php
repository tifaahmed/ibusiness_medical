<?php

namespace App\Support;

/**
 * Lays Arabic text out for GD.
 *
 * GD's imagettftext() draws code points in the order it is handed them and
 * looks each one up in the font exactly as written: it does no bidirectional
 * reordering and no Arabic shaping. A right-to-left string therefore comes out
 * backwards and spelled in disconnected isolated letters. The browser does both
 * steps for the canvas preview, which is why the live render reads correctly
 * and the exported PNG does not.
 *
 * This turns a logical-order UTF-8 string into the visual-order string GD
 * needs: Arabic letters are swapped for their Arabic Presentation Forms-B
 * shapes (isolated / final / initial / medial, plus the lam-alef ligatures),
 * then the runs are reordered with the reordering half of the Unicode
 * bidirectional algorithm so embedded numbers and Latin words still read left
 * to right inside an Arabic line.
 */
final class ArabicText
{
    /**
     * Every joining letter, as [isolated, final, initial, medial]; 0 = no such
     * form. The isolated column is documentation only — shape() emits the plain
     * code point for a letter that stands alone.
     */
    private const FORMS = [
        0x0621 => [0xFE80, 0, 0, 0],
        0x0622 => [0xFE81, 0xFE82, 0, 0],
        0x0623 => [0xFE83, 0xFE84, 0, 0],
        0x0624 => [0xFE85, 0xFE86, 0, 0],
        0x0625 => [0xFE87, 0xFE88, 0, 0],
        0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C],
        0x0627 => [0xFE8D, 0xFE8E, 0, 0],
        0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92],
        0x0629 => [0xFE93, 0xFE94, 0, 0],
        0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98],
        0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C],
        0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0],
        0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4],
        0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8],
        0x062F => [0xFEA9, 0xFEAA, 0, 0],
        0x0630 => [0xFEAB, 0xFEAC, 0, 0],
        0x0631 => [0xFEAD, 0xFEAE, 0, 0],
        0x0632 => [0xFEAF, 0xFEB0, 0, 0],
        0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4],
        0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8],
        0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC],
        0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0],
        0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4],
        0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8],
        0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC],
        0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0],
        0x0640 => [0x0640, 0x0640, 0x0640, 0x0640],
        0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4],
        0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8],
        0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC],
        0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0],
        0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4],
        0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8],
        0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC],
        0x0648 => [0xFEED, 0xFEEE, 0, 0],
        0x0649 => [0xFEEF, 0xFEF0, 0, 0],
        0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4],
        0x0671 => [0xFB50, 0xFB51, 0, 0],
        0x0679 => [0xFB66, 0xFB67, 0xFB68, 0xFB69],
        0x067E => [0xFB56, 0xFB57, 0xFB58, 0xFB59],
        0x0686 => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D],
        0x0688 => [0xFB88, 0xFB89, 0, 0],
        0x0691 => [0xFB8C, 0xFB8D, 0, 0],
        0x0698 => [0xFB8A, 0xFB8B, 0, 0],
        0x06A9 => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91],
        0x06AF => [0xFB92, 0xFB93, 0xFB94, 0xFB95],
        0x06BA => [0xFB9E, 0xFB9F, 0, 0],
        0x06BE => [0xFBAA, 0xFBAB, 0xFBAC, 0xFBAD],
        0x06C0 => [0xFBA4, 0xFBA5, 0, 0],
        0x06CC => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF],
        0x06D2 => [0xFBAE, 0xFBAF, 0, 0],
    ];

    /** A lam swallows a following alef into one glyph: [isolated, final]. */
    private const LAM = 0x0644;

    private const LAM_ALEF = [
        0x0622 => [0xFEF5, 0xFEF6],
        0x0623 => [0xFEF7, 0xFEF8],
        0x0625 => [0xFEF9, 0xFEFA],
        0x0627 => [0xFEFB, 0xFEFC],
    ];

    /** Brackets and quotes swap sides when they sit inside right-to-left text. */
    private const MIRRORED = [
        '(' => ')', ')' => '(',
        '[' => ']', ']' => '[',
        '{' => '}', '}' => '{',
        '<' => '>', '>' => '<',
        '«' => '»', '»' => '«',
        '‹' => '›', '›' => '‹',
    ];

    /**
     * Rewrite a logical-order string into the visual order GD should draw.
     *
     * Text with no Arabic in it is handed straight back, so Latin-only fields
     * such as the website line are untouched.
     */
    public static function forRendering(string $text): string
    {
        if ($text === '' || ! preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text)) {
            return $text;
        }

        return self::reorder(self::shape(self::clusters($text)));
    }

    /**
     * Break the string into clusters of one base character plus any combining
     * marks riding on it, so a harakah never gets separated from its letter.
     *
     * @return array<int, array{cp: int, glyph: string, marks: string}>
     */
    private static function clusters(string $text): array
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $clusters = [];

        foreach ($chars as $char) {
            if ($clusters !== [] && preg_match('/^[\p{Mn}\p{Me}]$/u', $char)) {
                $clusters[count($clusters) - 1]['marks'] .= $char;

                continue;
            }

            $clusters[] = [
                'cp' => self::codepoint($char),
                'glyph' => $char,
                'marks' => '',
            ];
        }

        return $clusters;
    }

    /**
     * Swap each Arabic letter for the presentation form its neighbours call for.
     *
     * @param  array<int, array{cp: int, glyph: string, marks: string}>  $clusters
     * @return array<int, array{cp: int, glyph: string, marks: string}>
     */
    private static function shape(array $clusters): array
    {
        $shaped = [];
        $count = count($clusters);

        for ($i = 0; $i < $count; $i++) {
            $cluster = $clusters[$i];
            $forms = self::FORMS[$cluster['cp']] ?? null;

            if ($forms === null) {
                $shaped[] = $cluster;

                continue;
            }

            $previous = $clusters[$i - 1]['cp'] ?? null;
            $next = $clusters[$i + 1]['cp'] ?? null;

            // The letter before reaches forward only if it is dual-joining.
            $joinedBefore = $previous !== null && (self::FORMS[$previous][2] ?? 0) !== 0;

            if ($cluster['cp'] === self::LAM && $next !== null && isset(self::LAM_ALEF[$next])) {
                [$isolated, $final] = self::LAM_ALEF[$next];
                $cluster['glyph'] = self::character($joinedBefore ? $final : $isolated);
                $cluster['marks'] .= $clusters[$i + 1]['marks'];
                $cluster['cp'] = $joinedBefore ? $final : $isolated;
                $shaped[] = $cluster;
                $i++;

                continue;
            }

            // Every joining letter has a final form, so any of them accepts a join.
            $joinedAfter = $next !== null && (self::FORMS[$next][1] ?? 0) !== 0;

            $form = match (true) {
                $joinedBefore && $joinedAfter && $forms[3] !== 0 => $forms[3],
                $joinedBefore && $forms[1] !== 0 => $forms[1],
                $joinedAfter && $forms[2] !== 0 => $forms[2],
                // A letter standing alone is left as the plain code point:
                // fonts draw that as the isolated shape, and many of them —
                // Tajawal included — ship no U+FExx isolated glyph at all.
                default => 0,
            };

            if ($form !== 0) {
                $cluster['glyph'] = self::character($form);
                $cluster['cp'] = $form;
            }

            $shaped[] = $cluster;
        }

        return $shaped;
    }

    /**
     * Rule L2 of the bidirectional algorithm: give every cluster an embedding
     * level, then reverse each run from the deepest level down to the shallowest
     * right-to-left one.
     *
     * @param  array<int, array{cp: int, glyph: string, marks: string}>  $clusters
     */
    private static function reorder(array $clusters): string
    {
        $types = array_map(fn (array $cluster): string => self::directionOf($cluster['cp']), $clusters);
        $base = self::baseLevel($types);
        $levels = self::levels($types, $base);
        $count = count($levels);

        $order = range(0, $count - 1);
        $highest = max($levels);
        $lowestOdd = $highest + 1;
        foreach ($levels as $level) {
            if ($level % 2 === 1 && $level < $lowestOdd) {
                $lowestOdd = $level;
            }
        }

        for ($level = $highest; $level >= $lowestOdd; $level--) {
            $start = null;

            for ($i = 0; $i <= $count; $i++) {
                $inside = $i < $count && $levels[$i] >= $level;

                if ($inside && $start === null) {
                    $start = $i;
                } elseif (! $inside && $start !== null) {
                    array_splice($order, $start, $i - $start, array_reverse(array_slice($order, $start, $i - $start)));
                    $start = null;
                }
            }
        }

        $out = '';
        foreach ($order as $index) {
            $glyph = $clusters[$index]['glyph'];

            if ($levels[$index] % 2 === 1) {
                $glyph = self::MIRRORED[$glyph] ?? $glyph;
            }

            $out .= $glyph.$clusters[$index]['marks'];
        }

        return $out;
    }

    /**
     * The paragraph direction, taken from the first strong character — the same
     * rule the browser applies to `dir="auto"`.
     *
     * @param  array<int, string>  $types
     */
    private static function baseLevel(array $types): int
    {
        foreach ($types as $type) {
            if ($type === 'R') {
                return 1;
            }

            if ($type === 'L') {
                return 0;
            }
        }

        return 0;
    }

    /**
     * @param  array<int, string>  $types
     * @return array<int, int>
     */
    private static function levels(array $types, int $base): array
    {
        $levels = [];
        $lastStrong = $base === 1 ? 'R' : 'L';

        foreach ($types as $type) {
            $levels[] = match ($type) {
                // Numbers read left to right even in Arabic, one level deeper.
                'R' => 1,
                'L' => $base === 1 ? 2 : 0,
                'EN', 'AN' => $lastStrong === 'R' ? 2 : $base,
                default => -1,
            };

            if ($type === 'R' || $type === 'L') {
                $lastStrong = $type;
            }
        }

        return self::resolveNeutrals($types, $levels, $base);
    }

    /**
     * Rule N1: a run of spaces and punctuation joins whichever side surrounds
     * it when both sides agree, and falls back to the paragraph direction when
     * they do not. Numbers count as right-to-left for this purpose.
     *
     * @param  array<int, string>  $types
     * @param  array<int, int>  $levels
     * @return array<int, int>
     */
    private static function resolveNeutrals(array $types, array $levels, int $base): array
    {
        $count = count($levels);
        $baseSide = $base === 1 ? 'R' : 'L';

        for ($i = 0; $i < $count; $i++) {
            if ($levels[$i] !== -1) {
                continue;
            }

            $end = $i;
            while ($end < $count && $levels[$end] === -1) {
                $end++;
            }

            $before = $i > 0 ? self::neutralSide($types[$i - 1]) : $baseSide;
            $after = $end < $count ? self::neutralSide($types[$end]) : $baseSide;

            $level = match (true) {
                $before !== $after => $base,
                $before === 'R' => 1,
                default => $base === 1 ? 2 : 0,
            };

            for ($j = $i; $j < $end; $j++) {
                $levels[$j] = $level;
            }

            $i = $end - 1;
        }

        return $levels;
    }

    private static function neutralSide(string $type): string
    {
        return in_array($type, ['R', 'EN', 'AN'], true) ? 'R' : 'L';
    }

    private static function directionOf(int $cp): string
    {
        return match (true) {
            $cp >= 0x0030 && $cp <= 0x0039 => 'EN',
            ($cp >= 0x0660 && $cp <= 0x0669) || ($cp >= 0x06F0 && $cp <= 0x06F9) => 'AN',
            // The Arabic comma and decimal separators only ever sit between
            // other characters, so they behave as punctuation, not as letters.
            $cp === 0x060C || ($cp >= 0x066B && $cp <= 0x066C) => 'N',
            ($cp >= 0x0590 && $cp <= 0x08FF)
                || ($cp >= 0xFB1D && $cp <= 0xFDFF)
                || ($cp >= 0xFE70 && $cp <= 0xFEFF) => 'R',
            (bool) preg_match('/^\p{L}$/u', self::character($cp)) => 'L',
            default => 'N',
        };
    }

    private static function codepoint(string $char): int
    {
        return (int) mb_ord($char, 'UTF-8');
    }

    private static function character(int $cp): string
    {
        return (string) mb_chr($cp, 'UTF-8');
    }
}
