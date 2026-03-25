<?php

namespace App\Support;

/**
 * Realtime Register contact API expects voice/fax in "E164a" form:
 * {@see https://dm.realtimeregister.com/docs/api/contacts/create}
 * Regex: \+[0-9]{1,3}\.[0-9]{1,14} (e.g. +31.384530759, not +31384530759).
 */
final class RealtimeRegisterPhone
{
    /**
     * ISO 3166-1 alpha-2 => ITU-T calling code (digits only, no +).
     *
     * @var array<string, string>
     */
    private const COUNTRY_TO_CALLING_CODE = [
        'AF' => '93', 'AL' => '355', 'DZ' => '213', 'AD' => '376', 'AO' => '244',
        'AG' => '1268', 'AR' => '54', 'AM' => '374', 'AU' => '61', 'AT' => '43',
        'AZ' => '994', 'BS' => '1242', 'BH' => '973', 'BD' => '880', 'BB' => '1246',
        'BY' => '375', 'BE' => '32', 'BZ' => '501', 'BJ' => '229', 'BT' => '975',
        'BO' => '591', 'BA' => '387', 'BW' => '267', 'BR' => '55', 'BN' => '673',
        'BG' => '359', 'BF' => '226', 'BI' => '257', 'KH' => '855', 'CM' => '237',
        'CA' => '1', 'CV' => '238', 'CF' => '236', 'TD' => '235', 'CL' => '56',
        'CN' => '86', 'CO' => '57', 'KM' => '269', 'CG' => '242', 'CD' => '243',
        'CR' => '506', 'CI' => '225', 'HR' => '385', 'CU' => '53', 'CY' => '357',
        'CZ' => '420', 'DK' => '45', 'DJ' => '253', 'DM' => '1767', 'DO' => '1809',
        'EC' => '593', 'EG' => '20', 'SV' => '503', 'GQ' => '240', 'ER' => '291',
        'EE' => '372', 'ET' => '251', 'FJ' => '679', 'FI' => '358', 'FR' => '33',
        'GA' => '241', 'GM' => '220', 'GE' => '995', 'DE' => '49', 'GH' => '233',
        'GR' => '30', 'GD' => '1473', 'GT' => '502', 'GN' => '224', 'GW' => '245',
        'GY' => '592', 'HT' => '509', 'HN' => '504', 'HK' => '852', 'HU' => '36',
        'IS' => '354', 'IN' => '91', 'ID' => '62', 'IR' => '98', 'IQ' => '964',
        'IE' => '353', 'IL' => '972', 'IT' => '39', 'JM' => '1876', 'JP' => '81',
        'JO' => '962', 'KZ' => '7', 'KE' => '254', 'KI' => '686', 'KP' => '850',
        'KR' => '82', 'KW' => '965', 'KG' => '996', 'LA' => '856', 'LV' => '371',
        'LB' => '961', 'LS' => '266', 'LR' => '231', 'LY' => '218', 'LI' => '423',
        'LT' => '370', 'LU' => '352', 'MO' => '853', 'MK' => '389', 'MG' => '261',
        'MW' => '265', 'MY' => '60', 'MV' => '960', 'ML' => '223', 'MT' => '356',
        'MH' => '692', 'MR' => '222', 'MU' => '230', 'MX' => '52', 'FM' => '691',
        'MD' => '373', 'MC' => '377', 'MN' => '976', 'ME' => '382', 'MA' => '212',
        'MZ' => '258', 'MM' => '95', 'NA' => '264', 'NR' => '674', 'NP' => '977',
        'NL' => '31', 'NZ' => '64', 'NI' => '505', 'NE' => '227', 'NG' => '234',
        'NO' => '47', 'OM' => '968', 'PK' => '92', 'PW' => '680', 'PS' => '970',
        'PA' => '507', 'PG' => '675', 'PY' => '595', 'PE' => '51', 'PH' => '63',
        'PL' => '48', 'PT' => '351', 'QA' => '974', 'RO' => '40', 'RU' => '7',
        'RW' => '250', 'KN' => '1869', 'LC' => '1758', 'VC' => '1784', 'WS' => '685',
        'SM' => '378', 'ST' => '239', 'SA' => '966', 'SN' => '221', 'RS' => '381',
        'SC' => '248', 'SL' => '232', 'SG' => '65', 'SK' => '421', 'SI' => '386',
        'SB' => '677', 'SO' => '252', 'ZA' => '27', 'SS' => '211', 'ES' => '34',
        'LK' => '94', 'SD' => '249', 'SR' => '597', 'SZ' => '268', 'SE' => '46',
        'CH' => '41', 'SY' => '963', 'TW' => '886', 'TJ' => '992', 'TZ' => '255',
        'TH' => '66', 'TL' => '670', 'TG' => '228', 'TO' => '676', 'TT' => '1868',
        'TN' => '216', 'TR' => '90', 'TM' => '993', 'TV' => '688', 'UG' => '256',
        'UA' => '380', 'AE' => '971', 'GB' => '44', 'US' => '1', 'UY' => '598',
        'UZ' => '998', 'VU' => '678', 'VA' => '39', 'VE' => '58', 'VN' => '84',
        'YE' => '967', 'ZM' => '260', 'ZW' => '263', 'XK' => '383',
    ];

    public static function toVoice(string $phone, string $countryIso3166Alpha2): string
    {
        $trimmed = trim($phone);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('Telefonnummer fehlt.');
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if ($digits === '') {
            throw new \InvalidArgumentException('Telefonnummer enthält keine Ziffern.');
        }

        $country = strtoupper(substr(trim($countryIso3166Alpha2), 0, 2));
        $cc = self::COUNTRY_TO_CALLING_CODE[$country] ?? null;

        if ($cc !== null) {
            if (str_starts_with($digits, $cc)) {
                $nsn = substr($digits, strlen($cc));
            } else {
                $nsn = $digits;
            }
            $nsn = ltrim($nsn, '0');
            if ($nsn !== '' && strlen($nsn) <= 14) {
                return '+'.$cc.'.'.$nsn;
            }
        }

        [$peeledCc, $peeledNsn] = self::splitInternationalDigits($digits);

        return '+'.$peeledCc.'.'.$peeledNsn;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitInternationalDigits(string $digits): array
    {
        foreach (self::callingPrefixesLongestFirst() as $prefix) {
            if (! str_starts_with($digits, $prefix)) {
                continue;
            }
            $nsn = substr($digits, strlen($prefix));
            if ($nsn === '') {
                continue;
            }
            $nsn = ltrim($nsn, '0');
            if ($nsn === '') {
                continue;
            }
            $len = strlen($nsn);
            if ($len >= 1 && $len <= 14) {
                return [$prefix, $nsn];
            }
        }

        throw new \InvalidArgumentException(
            'Telefonnummer konnte nicht ins Realtime-Register-Format (+CC.NSN) umgewandelt werden. Bitte internationale Schreibweise mit Ländervorwahl prüfen.'
        );
    }

    /**
     * @return list<string>
     */
    private static function callingPrefixesLongestFirst(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $unique = array_values(array_unique(array_values(self::COUNTRY_TO_CALLING_CODE)));
        usort($unique, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $cache = $unique;
    }
}
