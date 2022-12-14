<?php

declare(strict_types=1);

namespace PeskyORM\Utils;

abstract class StringUtils
{
    /**
     * A list of words which should not be inflected, reversed.
     */
    private const UNINFLECTED = [
        '',

        // data
        'atad',

        // deer
        'reed',

        // feedback
        'kcabdeef',

        // fish
        'hsif',

        // info
        'ofni',

        // moose
        'esoom',

        // series
        'seires',

        // sheep
        'peehs',

        // species
        'seiceps',
    ];

    /**
     * Map English plural to singular suffixes.
     *
     * @see http://english-zone.com/spelling/plurals.html
     * @see https://symfony.com/doc/5.4/components/string.html#inflector
     */
    private const PLURAL_MAP = [
        // First entry: plural suffix, reversed
        // Second entry: length of plural suffix
        // Third entry: Whether the suffix may succeed a vocal
        // Fourth entry: Whether the suffix may succeed a consonant
        // Fifth entry: singular suffix, normal

        // bacteria (bacterium), criteria (criterion), phenomena (phenomenon)
        ['a', 1, true, true, ['on', 'um']],

        // nebulae (nebula)
        ['ea', 2, true, true, 'a'],

        // services (service)
        ['secivres', 8, true, true, 'service'],

        // mice (mouse), lice (louse)
        ['eci', 3, false, true, 'ouse'],

        // geese (goose)
        ['esee', 4, false, true, 'oose'],

        // fungi (fungus), alumni (alumnus), syllabi (syllabus), radii (radius)
        ['i', 1, true, true, 'us'],

        // men (man), women (woman)
        ['nem', 3, true, true, 'man'],

        // children (child)
        ['nerdlihc', 8, true, true, 'child'],

        // oxen (ox)
        ['nexo', 4, false, false, 'ox'],

        // indices (index), appendices (appendix), prices (price)
        ['seci', 4, false, true, ['ex', 'ix', 'ice']],

        // selfies (selfie)
        ['seifles', 7, true, true, 'selfie'],

        // zombies (zombie)
        ['seibmoz', 7, true, true, 'zombie'],

        // movies (movie)
        ['seivom', 6, true, true, 'movie'],

        // conspectuses (conspectus), prospectuses (prospectus)
        ['sesutcep', 8, true, true, 'pectus'],

        // feet (foot)
        ['teef', 4, true, true, 'foot'],

        // geese (goose)
        ['eseeg', 5, true, true, 'goose'],

        // teeth (tooth)
        ['hteet', 5, true, true, 'tooth'],

        // news (news)
        ['swen', 4, true, true, 'news'],

        // series (series)
        ['seires', 6, true, true, 'series'],

        // cookies (cookie), rookies (rookie)
        ['seik', 4, true, true, 'kie'],

        // babies (baby)
        ['sei', 3, false, true, 'y'],

        // accesses (access), addresses (address), kisses (kiss)
        ['sess', 4, true, false, 'ss'],

        // analyses (analysis), ellipses (ellipsis), fungi (fungus),
        // neuroses (neurosis), theses (thesis), emphases (emphasis),
        // oases (oasis), crises (crisis), houses (house), bases (base),
        // atlases (atlas)
        ['ses', 3, true, true, ['s', 'se', 'sis']],

        // objectives (objective), alternative (alternatives)
        ['sevit', 5, true, true, 'tive'],

        // drives (drive)
        ['sevird', 6, false, true, 'drive'],

        // lives (life), wives (wife)
        ['sevi', 4, false, true, 'ife'],

        // moves (move)
        ['sevom', 5, true, true, 'move'],

        // hooves (hoof), dwarves (dwarf), elves (elf), leaves (leaf), caves (cave), staves (staff)
        ['sev', 3, true, true, ['f', 've', 'ff']],

        // axes (axis), axes (ax), axes (axe)
        ['sexa', 4, false, false, ['ax', 'axe', 'axis']],

        // indexes (index), matrixes (matrix)
        ['sex', 3, true, false, 'x'],

        // quizzes (quiz)
        ['sezz', 4, true, false, 'z'],

        // bureaus (bureau)
        ['suae', 4, false, true, 'eau'],

        // fees (fee), trees (tree), employees (employee)
        ['see', 3, true, true, 'ee'],

        // edges (edge)
        ['segd', 4, true, true, 'dge'],

        // bushes (bush), arches (arch)
        ['seh', 3, true, true, 'h'],

        // shoes (shoe), heroes (hero)
        ['seo', 3, true, true, 'oe'],

        // waltzes (waltz),
        ['sezt', 3, true, true, 'tz'],

        // roses (rose), garages (garage), cassettes (cassette),
        ['se', 2, true, true, 'e'],

        // tags (tag)
        ['s', 1, true, true, ''],

        // chateaux (chateau)
        ['xuae', 4, false, true, 'eau'],

        // people (person)
        ['elpoep', 6, true, true, 'person'],
    ];

    public const PASCAL_CASE_VALIDATION_REGEXP = '%^[A-Z][a-zA-Z0-9]*$%'; // PascalCase
    public const SNAKE_CASE_VALIDATION_REGEXP = '%^[a-z][a-z0-9_]*$%';    // snake_case

    /**
     * Convert snake_case, dashed-string, any#delimiter to PascalCase
     */
    public static function toPascalCase(string $underscoredString): string
    {
        return str_replace(' ', '', ucwords(preg_replace('%[^a-z\d]+%i', ' ', $underscoredString)));
    }

    public static function toSingularPascalCase(string $plural): string
    {
        return static::toSingular(static::toPascalCase($plural));
    }

    public static function toSingular(string $plural): string
    {
        $pluralRev = strrev($plural);
        $lowerPluralRev = strtolower($pluralRev);
        $pluralLength = \strlen($lowerPluralRev);

        // Check if the word is one which is not inflected, return early if so
        if (\in_array($lowerPluralRev, self::UNINFLECTED, true)) {
            return $plural;
        }

        // The outer loop iterates over the entries of the plural table
        // The inner loop $j iterates over the characters of the plural suffix
        // in the plural table to compare them with the characters of the actual
        // given plural suffix
        foreach (self::PLURAL_MAP as $map) {
            [$suffix, $suffixLength] = $map;
            $j = 0;

            // Compare characters in the plural table and of the suffix of the
            // given plural one by one
            while ($suffix[$j] === $lowerPluralRev[$j]) {
                // Let $j point to the next character
                ++$j;

                // Successfully compared the last character
                // Add an entry with the singular suffix to the singular array
                if ($j === $suffixLength) {
                    // Is there any character preceding the suffix in the plural string?
                    if ($j < $pluralLength) {
                        $nextIsVocal = str_contains('aeiou', $lowerPluralRev[$j]);

                        if (!$map[2] && $nextIsVocal) {
                            // suffix may not succeed a vocal but next char is one
                            break;
                        }

                        if (!$map[3] && !$nextIsVocal) {
                            // suffix may not succeed a consonant but next char is one
                            break;
                        }
                    }

                    $newBase = substr($plural, 0, $pluralLength - $suffixLength);
                    $newSuffix = $map[4];

                    // Check whether the first character in the plural suffix
                    // is uppercased. If yes, uppercase the first character in
                    // the singular suffix too
                    if (function_exists('ctype_upper')) {
                        $firstUpper = ctype_upper($pluralRev[$j - 1]);
                    } else {
                        $firstUpper = (bool)preg_match('%[A-Z]%', $pluralRev[$j - 1]);
                    }

                    if (\is_array($newSuffix)) {
                        return $newBase . ($firstUpper ? ucfirst($newSuffix[0]) : $newSuffix[0]);
                    }

                    return $newBase . ($firstUpper ? ucfirst($newSuffix) : $newSuffix);
                }

                // Suffix is longer than word
                if ($j === $pluralLength) {
                    break;
                }
            }
        }

        // Assume that plural and singular is identical
        return $plural;
    }

    /**
     * Convert PascalCase or camelCase to snake_case
     */
    public static function toSnakeCase(string $pascalOrSnakeCaseString): string
    {
        return strtolower(
            preg_replace(
                ['/\s+/', '/(?<=\\w)([A-Z])/', '/_+/'],
                ['_', '_\\1', '_'],
                $pascalOrSnakeCaseString
            )
        );
    }

    public static function isPascalCase(string $string): bool
    {
        return (bool)preg_match(static::PASCAL_CASE_VALIDATION_REGEXP, $string);
    }

    public static function isSnakeCase(string $string): bool
    {
        return (bool)preg_match(static::SNAKE_CASE_VALIDATION_REGEXP, $string);
    }
}