<?php

/*
 * EN LOCAL (extension PHP intl) : écrit resources/data/countries.php, la liste des pays (code ISO, nom en
 * français, indicatif téléphonique), relue par App\Support\Countries. Le serveur n'a pas besoin d'intl.
 * Usage : php tools/countries/build-countries.php
 */

// Indicatifs téléphoniques (plan de numérotation de l'UIT). Les pays du plan nord-américain partagent « 1 ».
$dial = [
    'AF' => 93, 'AL' => 355, 'DZ' => 213, 'AD' => 376, 'AO' => 244, 'AG' => 1, 'AR' => 54, 'AM' => 374, 'AU' => 61,
    'AT' => 43, 'AZ' => 994, 'BS' => 1, 'BH' => 973, 'BD' => 880, 'BB' => 1, 'BY' => 375, 'BE' => 32, 'BZ' => 501,
    'BJ' => 229, 'BT' => 975, 'BO' => 591, 'BA' => 387, 'BW' => 267, 'BR' => 55, 'BN' => 673, 'BG' => 359, 'BF' => 226,
    'BI' => 257, 'KH' => 855, 'CM' => 237, 'CA' => 1, 'CV' => 238, 'CF' => 236, 'TD' => 235, 'CL' => 56, 'CN' => 86,
    'CO' => 57, 'KM' => 269, 'CG' => 242, 'CD' => 243, 'CR' => 506, 'CI' => 225, 'HR' => 385, 'CU' => 53, 'CY' => 357,
    'CZ' => 420, 'DK' => 45, 'DJ' => 253, 'DM' => 1, 'DO' => 1, 'EC' => 593, 'EG' => 20, 'SV' => 503, 'GQ' => 240,
    'ER' => 291, 'EE' => 372, 'SZ' => 268, 'ET' => 251, 'FJ' => 679, 'FI' => 358, 'FR' => 33, 'GF' => 594, 'PF' => 689,
    'GA' => 241, 'GM' => 220, 'GE' => 995, 'DE' => 49, 'GH' => 233, 'GR' => 30, 'GD' => 1, 'GP' => 590, 'GT' => 502,
    'GN' => 224, 'GW' => 245, 'GY' => 592, 'HT' => 509, 'HN' => 504, 'HK' => 852, 'HU' => 36, 'IS' => 354, 'IN' => 91,
    'ID' => 62, 'IR' => 98, 'IQ' => 964, 'IE' => 353, 'IL' => 972, 'IT' => 39, 'JM' => 1, 'JP' => 81, 'JO' => 962,
    'KZ' => 7, 'KE' => 254, 'KI' => 686, 'KP' => 850, 'KR' => 82, 'KW' => 965, 'KG' => 996, 'LA' => 856, 'LV' => 371,
    'LB' => 961, 'LS' => 266, 'LR' => 231, 'LY' => 218, 'LI' => 423, 'LT' => 370, 'LU' => 352, 'MO' => 853, 'MG' => 261,
    'MW' => 265, 'MY' => 60, 'MV' => 960, 'ML' => 223, 'MT' => 356, 'MH' => 692, 'MQ' => 596, 'MR' => 222, 'MU' => 230,
    'YT' => 262, 'MX' => 52, 'FM' => 691, 'MD' => 373, 'MC' => 377, 'MN' => 976, 'ME' => 382, 'MA' => 212, 'MZ' => 258,
    'MM' => 95, 'NA' => 264, 'NR' => 674, 'NP' => 977, 'NL' => 31, 'NC' => 687, 'NZ' => 64, 'NI' => 505, 'NE' => 227,
    'NG' => 234, 'MK' => 389, 'NO' => 47, 'OM' => 968, 'PK' => 92, 'PW' => 680, 'PS' => 970, 'PA' => 507, 'PG' => 675,
    'PY' => 595, 'PE' => 51, 'PH' => 63, 'PL' => 48, 'PT' => 351, 'PR' => 1, 'QA' => 974, 'RE' => 262, 'RO' => 40,
    'RU' => 7, 'RW' => 250, 'KN' => 1, 'LC' => 1, 'PM' => 508, 'VC' => 1, 'WS' => 685, 'SM' => 378, 'ST' => 239,
    'SA' => 966, 'SN' => 221, 'RS' => 381, 'SC' => 248, 'SL' => 232, 'SG' => 65, 'SK' => 421, 'SI' => 386, 'SB' => 677,
    'SO' => 252, 'ZA' => 27, 'SS' => 211, 'ES' => 34, 'LK' => 94, 'SD' => 249, 'SR' => 597, 'SE' => 46, 'CH' => 41,
    'SY' => 963, 'TW' => 886, 'TJ' => 992, 'TZ' => 255, 'TH' => 66, 'TL' => 670, 'TG' => 228, 'TO' => 676, 'TT' => 1,
    'TN' => 216, 'TR' => 90, 'TM' => 993, 'TV' => 688, 'UG' => 256, 'UA' => 380, 'AE' => 971, 'GB' => 44, 'US' => 1,
    'UY' => 598, 'UZ' => 998, 'VU' => 678, 'VA' => 39, 'VE' => 58, 'VN' => 84, 'WF' => 681, 'YE' => 967, 'ZM' => 260,
    'ZW' => 263, 'XK' => 383,
];

// Pays où le français est langue officielle ou très répandue (membres de la Francophonie), et la France d'outre-mer.
$francophone = [
    'TG', 'BJ', 'BF', 'CI', 'DZ', 'BE', 'BI', 'CM', 'CA', 'CF', 'KM', 'CG', 'CD', 'DJ', 'FR', 'GA', 'GN', 'GQ', 'HT',
    'LB', 'LU', 'MG', 'ML', 'MA', 'MU', 'MR', 'MC', 'NE', 'RW', 'SN', 'SC', 'CH', 'TD', 'TN', 'VU',
    'GF', 'PF', 'GP', 'MQ', 'YT', 'NC', 'RE', 'PM', 'WF',
];

$collator = new Collator('fr_FR');
$countries = [];

foreach ($dial as $code => $number) {
    $name = Locale::getDisplayRegion('-'.$code, 'fr');

    if ($name === '' || $name === $code) {
        fwrite(STDERR, "Nom introuvable : {$code}\n");
        exit(1);
    }

    $countries[$code] = ['name' => $name, 'dial' => (string) $number, 'francophone' => in_array($code, $francophone, true)];
}

uasort($countries, fn ($a, $b) => $collator->compare($a['name'], $b['name']));

$lines = ["<?php\n", '/*', ' * FICHIER GÉNÉRÉ par tools/countries/build-countries.php (EN LOCAL, extension intl) : ne pas modifier à la main.', ' * Pays (code ISO 3166-1), nom en français, indicatif téléphonique, pays francophone. Lu par App\Support\Countries.', ' */', '', 'return ['];

foreach ($countries as $code => $country) {
    $lines[] = sprintf("    '%s' => ['name' => %s, 'dial' => '%s', 'francophone' => %s],", $code, var_export($country['name'], true), $country['dial'], $country['francophone'] ? 'true' : 'false');
}

$lines[] = '];';
file_put_contents(__DIR__.'/../../resources/data/countries.php', implode("\n", $lines)."\n");
echo count($countries)." pays écrits.\n";
