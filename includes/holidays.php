<?php
/**
 * Official Government Public Holidays & Day-Off Engine
 * Provides accurate official government public holidays with Cambodia (KH) default,
 * United States (US), International (GLOBAL), and custom user-defined days off.
 */

if (!defined('MINDRIFT_HOLIDAYS_LOADED')) {
    define('MINDRIFT_HOLIDAYS_LOADED', true);

    /**
     * Get official government public holidays for a given year and country.
     *
     * @param int $year
     * @param string $countryCode ('KH' | 'US' | 'GLOBAL')
     * @return array Array of holidays keyed by 'YYYY-MM-DD'
     */
    function getOfficialGovernmentHolidays(int $year, string $countryCode = 'KH'): array {
        $countryCode = strtoupper(trim($countryCode));
        $holidays = [];

        if ($countryCode === 'KH') {
            // ==========================================
            // CAMBODIA OFFICIAL PUBLIC HOLIDAYS (Prakas)
            // ==========================================
            
            // Fixed Solar Holidays
            $holidays[sprintf('%04d-01-01', $year)] = [
                'name' => "International New Year's Day",
                'local_name' => "ទិវាចូលឆ្នាំសកល",
                'country' => 'KH',
                'flag' => '🇰🇭',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Official Cambodia public holiday celebrating the international new calendar year.'
            ];

            $holidays[sprintf('%04d-01-07', $year)] = [
                'name' => 'Victory over Genocide Day',
                'local_name' => 'ទិវាជ័យជម្នះលើរបបប្រល័យពូជសាសន៍',
                'country' => 'KH',
                'flag' => '🇰🇭',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Commemorates the fall of the Khmer Rouge regime in 1979.'
            ];

            $holidays[sprintf('%04d-03-08', $year)] = [
                'name' => "International Women's Day",
                'local_name' => 'ទិវានារីអន្តរជាតិ',
                'country' => 'KH',
                'flag' => '🇰🇭',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Celebration of the social, economic, cultural, and political achievements of women.'
            ];

            // Khmer New Year (Chaul Chnam Thmey) - 4 official days
            $knyDays = [
                13 => 'Moha Sangkranta (Day 1)',
                14 => 'Veareak Vanabat (Day 2)',
                15 => 'Veareak Loeng Sak (Day 3)',
                16 => 'Khmer New Year (Day 4)'
            ];
            foreach ($knyDays as $day => $title) {
                $holidays[sprintf('%04d-04-%02d', $year, $day)] = [
                    'name' => "Khmer New Year - {$title}",
                    'local_name' => 'ពិធីបុណ្យចូលឆ្នាំថ្មី ប្រពៃណីជាតិខ្មែរ',
                    'country' => 'KH',
                    'flag' => '🇰🇭',
                    'type' => 'government',
                    'is_day_off' => true,
                    'description' => 'Major national traditional holiday celebrating the traditional solar new year in Cambodia.'
                ];
            }

            $holidays[sprintf('%04d-05-01', $year)] = [
                'name' => 'International Labor Day',
                'local_name' => 'ទិវាពលកម្មអន្តរជាតិ',
                'country' => 'KH',
                'flag' => '🇰🇭',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Celebration of workers and labor rights.'
            ];

            $holidays[sprintf('%04d-05-14', $year)] = [
                'name' => "King Norodom Sihamoni's Birthday",
                'local_name' => 'ព្រះរាជពិធីបុណ្យចម្រើនព្រះជន្ម ព្រះករុណា ព្រះបាទសម្តេច ព្រះបរមនាថ នរោត្តម សីហមុនី',
                'country' => 'KH',
                'flag' => '🇰🇭',
                'type' => 'government',
                'is_day_off' => true,
                'description' => "Official birthday celebration of His Majesty King Norodom Sihamoni."
            ];

            $holidays[sprintf('%04d-05-20', $year)] = [
                'name' => 'National Day of Remembrance',
                'local_name' => 'ទិវាជាតិនៃការចងចាំ',
                'country' => 'KH',
                'flag' => '🇰🇭',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'National day commemorating the victims of the Democratic Kampuchea regime.'
            ];

            $holidays[sprintf('%04d-06-18', $year)] = [
                'name' => "Queen Mother Norodom Monineath's Birthday",
                'local_name' => 'ព្រះរាជពិធីបុណ្យចម្រើនព្រះជន្ម សម្តេចព្រះមហាក្សត្រី នរោត្តម មុនិនាថ សីហនុ',
                'country' => 'KH',
                'flag' => '🇰🇭',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Official celebration of the Queen Mother of Cambodia.'
            ];

            $holidays[sprintf('%04d-09-24', $year)] = [
                'name' => 'Constitutional Day',
                'local_name' => 'ទិវាប្រកាសរដ្ឋធម្មនុញ្ញ',
                'country' => 'KH',
                'flag' => '🇰🇭',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Commemorates the promulgation of the Constitution of the Kingdom of Cambodia in 1993.'
            ];

            $holidays[sprintf('%04d-10-15', $year)] = [
                'name' => 'Commemoration of King Father Norodom Sihanouk',
                'local_name' => 'ទិវាប្រារព្ធពិធីគោរពព្រះវិញ្ញាណក្ខន្ធ ព្រះបរមរតនកោដ្ឋ',
                'country' => 'KH',
                'flag' => '🇰🇭',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Commemorating the late King Father Norodom Sihanouk.'
            ];

            $holidays[sprintf('%04d-10-29', $year)] = [
                'name' => "King's Coronation Day",
                'local_name' => 'ព្រះរាជពិធីរំលឹកខួបនៃការគ្រងរាជសម្បត្តិ ព្រះករុណា ព្រះបាទសម្តេច ព្រះបរមនាថ នរោត្តម សីហមុនី',
                'country' => 'KH',
                'flag' => '🇰🇭',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Commemorates the coronation anniversary of King Norodom Sihamoni in 2004.'
            ];

            $holidays[sprintf('%04d-11-09', $year)] = [
                'name' => 'National Independence Day',
                'local_name' => 'ពិធីបុណ្យឯករាជ្យជាតិ',
                'country' => 'KH',
                'flag' => '🇰🇭',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Celebrates the independence of Cambodia from France in 1953.'
            ];

            // Lunar-based Cambodian Public Holidays (Visak Bochea, Royal Ploughing, Pchum Ben, Water Festival)
            // Precise dates from Government Prakas
            $lunarByYear = [
                2024 => [
                    'visak' => '2024-05-22',
                    'ploughing' => '2024-05-26',
                    'pchum_ben' => ['2024-10-01', '2024-10-02', '2024-10-03'],
                    'water_fest' => ['2024-11-14', '2024-11-15', '2024-11-16']
                ],
                2025 => [
                    'visak' => '2025-05-11',
                    'ploughing' => '2025-05-15',
                    'pchum_ben' => ['2025-09-21', '2025-09-22', '2025-09-23'],
                    'water_fest' => ['2025-11-04', '2025-11-05', '2025-11-06']
                ],
                2026 => [
                    'visak' => '2026-05-30',
                    'ploughing' => '2026-06-03',
                    'pchum_ben' => ['2026-10-09', '2026-10-10', '2026-10-11'],
                    'water_fest' => ['2026-11-23', '2026-11-24', '2026-11-25']
                ],
                2027 => [
                    'visak' => '2027-05-19',
                    'ploughing' => '2027-05-23',
                    'pchum_ben' => ['2027-09-28', '2027-09-29', '2027-09-30'],
                    'water_fest' => ['2027-11-12', '2027-11-13', '2027-11-14']
                ],
                2028 => [
                    'visak' => '2028-05-08',
                    'ploughing' => '2028-05-12',
                    'pchum_ben' => ['2028-10-17', '2028-10-18', '2028-10-19'],
                    'water_fest' => ['2028-10-31', '2028-11-01', '2028-11-02']
                ]
            ];

            $lunarData = $lunarByYear[$year] ?? [
                'visak' => sprintf('%04d-05-20', $year),
                'ploughing' => sprintf('%04d-05-24', $year),
                'pchum_ben' => [sprintf('%04d-10-01', $year), sprintf('%04d-10-02', $year), sprintf('%04d-10-03', $year)],
                'water_fest' => [sprintf('%04d-11-15', $year), sprintf('%04d-11-16', $year), sprintf('%04d-11-17', $year)]
            ];

            if (!empty($lunarData['visak'])) {
                $holidays[$lunarData['visak']] = [
                    'name' => 'Visak Bochea Day (Buddha Day)',
                    'local_name' => 'ពិធីបុណ្យវិសាខបូជា',
                    'country' => 'KH',
                    'flag' => '🇰🇭',
                    'type' => 'government',
                    'is_day_off' => true,
                    'description' => "Sacred day commemorating the birth, enlightenment, and passing away of the Buddha."
                ];
            }

            if (!empty($lunarData['ploughing'])) {
                $holidays[$lunarData['ploughing']] = [
                    'name' => 'Royal Ploughing Ceremony',
                    'local_name' => 'ព្រះរាជពិធីច្រត់ព្រះនង្គ័ល',
                    'country' => 'KH',
                    'flag' => '🇰🇭',
                    'type' => 'government',
                    'is_day_off' => true,
                    'description' => "Ancient royal rite forecasting the harvest and agricultural fortunes of the coming season."
                ];
            }

            if (!empty($lunarData['pchum_ben'])) {
                $pbIndex = 1;
                foreach ($lunarData['pchum_ben'] as $pbDate) {
                    $holidays[$pbDate] = [
                        'name' => "Pchum Ben Festival (Day {$pbIndex})",
                        'local_name' => "ពិធីបុណ្យភ្ជុំបិណ្ឌ (ថ្ងៃទី{$pbIndex})",
                        'country' => 'KH',
                        'flag' => '🇰🇭',
                        'type' => 'government',
                        'is_day_off' => true,
                        'description' => "Ancestors' Day festival where Cambodians pay deep respect to deceased ancestors and visit pagodas."
                    ];
                    $pbIndex++;
                }
            }

            if (!empty($lunarData['water_fest'])) {
                $wfTitles = [
                    1 => 'First Day of Boat Races',
                    2 => 'Loy Pratip & Sampeas Preah Khe',
                    3 => 'Final Day & Awards Ceremony'
                ];
                $wfIndex = 1;
                foreach ($lunarData['water_fest'] as $wfDate) {
                    $sub = $wfTitles[$wfIndex] ?? "Day {$wfIndex}";
                    $holidays[$wfDate] = [
                        'name' => "Water and Moon Festival - {$sub}",
                        'local_name' => 'ព្រះរាជពិធីបុណ្យអុំទូក បណ្តែតប្រទីប និងសំពះព្រះខែ អកអំបុក',
                        'country' => 'KH',
                        'flag' => '🇰🇭',
                        'type' => 'government',
                        'is_day_off' => true,
                        'description' => "Grand water festival marking the reversing flow of the Tonle Sap River with boat races and lantern floats."
                    ];
                    $wfIndex++;
                }
            }

        } elseif ($countryCode === 'US') {
            // ==========================================
            // UNITED STATES FEDERAL PUBLIC HOLIDAYS
            // ==========================================

            // New Year's Day (Jan 1)
            $holidays[sprintf('%04d-01-01', $year)] = [
                'name' => "New Year's Day",
                'local_name' => "New Year's Day",
                'country' => 'US',
                'flag' => '🇺🇸',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Federal holiday marking the beginning of the Gregorian new year.'
            ];

            // Martin Luther King Jr. Day (3rd Monday of January)
            $mlkDate = date('Y-m-d', strtotime("third monday of january {$year}"));
            $holidays[$mlkDate] = [
                'name' => 'Martin Luther King Jr. Day',
                'local_name' => 'MLK Day',
                'country' => 'US',
                'flag' => '🇺🇸',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Honoring the civil rights leader Dr. Martin Luther King Jr.'
            ];

            // Washington's Birthday / Presidents' Day (3rd Monday of February)
            $presDate = date('Y-m-d', strtotime("third monday of february {$year}"));
            $holidays[$presDate] = [
                'name' => "Presidents' Day (Washington's Birthday)",
                'local_name' => "Presidents' Day",
                'country' => 'US',
                'flag' => '🇺🇸',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Federal holiday honoring past US presidents.'
            ];

            // Memorial Day (Last Monday of May)
            $memDate = date('Y-m-d', strtotime("last monday of may {$year}"));
            $holidays[$memDate] = [
                'name' => 'Memorial Day',
                'local_name' => 'Memorial Day',
                'country' => 'US',
                'flag' => '🇺🇸',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Honoring the men and women who died while serving in the U.S. military.'
            ];

            // Juneteenth (June 19)
            $holidays[sprintf('%04d-06-19', $year)] = [
                'name' => 'Juneteenth National Independence Day',
                'local_name' => 'Juneteenth',
                'country' => 'US',
                'flag' => '🇺🇸',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Commemorating the emancipation of enslaved African Americans.'
            ];

            // Independence Day (July 4)
            $holidays[sprintf('%04d-07-04', $year)] = [
                'name' => 'Independence Day (4th of July)',
                'local_name' => 'Independence Day',
                'country' => 'US',
                'flag' => '🇺🇸',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Celebrating the Declaration of Independence in 1776.'
            ];

            // Labor Day (1st Monday of September)
            $laborDate = date('Y-m-d', strtotime("first monday of september {$year}"));
            $holidays[$laborDate] = [
                'name' => 'Labor Day',
                'local_name' => 'Labor Day',
                'country' => 'US',
                'flag' => '🇺🇸',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Honoring the American labor movement and workers.'
            ];

            // Columbus / Indigenous Peoples' Day (2nd Monday of October)
            $colDate = date('Y-m-d', strtotime("second monday of october {$year}"));
            $holidays[$colDate] = [
                'name' => "Columbus Day / Indigenous Peoples' Day",
                'local_name' => "Columbus Day",
                'country' => 'US',
                'flag' => '🇺🇸',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Federal holiday recognized on the second Monday of October.'
            ];

            // Veterans Day (Nov 11)
            $holidays[sprintf('%04d-11-11', $year)] = [
                'name' => 'Veterans Day',
                'local_name' => 'Veterans Day',
                'country' => 'US',
                'flag' => '🇺🇸',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Honoring military veterans of the United States Armed Forces.'
            ];

            // Thanksgiving (4th Thursday of November)
            $tgDate = date('Y-m-d', strtotime("fourth thursday of november {$year}"));
            $holidays[$tgDate] = [
                'name' => 'Thanksgiving Day',
                'local_name' => 'Thanksgiving',
                'country' => 'US',
                'flag' => '🇺🇸',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'National day of thanksgiving and gratitude.'
            ];

            // Christmas Day (Dec 25)
            $holidays[sprintf('%04d-12-25', $year)] = [
                'name' => 'Christmas Day',
                'local_name' => 'Christmas Day',
                'country' => 'US',
                'flag' => '🇺🇸',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Federal holiday commemorating the birth of Jesus Christ.'
            ];

        } else {
            // ==========================================
            // GLOBAL / INTERNATIONAL HOLIDAYS
            // ==========================================
            $holidays[sprintf('%04d-01-01', $year)] = [
                'name' => "New Year's Day",
                'local_name' => "New Year's Day",
                'country' => 'GLOBAL',
                'flag' => '🌐',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Celebration of the new calendar year worldwide.'
            ];
            $holidays[sprintf('%04d-03-08', $year)] = [
                'name' => "International Women's Day",
                'local_name' => "Women's Day",
                'country' => 'GLOBAL',
                'flag' => '🌐',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Global recognition of women rights and equality.'
            ];
            $holidays[sprintf('%04d-05-01', $year)] = [
                'name' => 'International Workers Day',
                'local_name' => 'May Day',
                'country' => 'GLOBAL',
                'flag' => '🌐',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Global celebration of workers and labor solidarity.'
            ];
            $holidays[sprintf('%04d-12-25', $year)] = [
                'name' => 'Christmas Day',
                'local_name' => 'Christmas',
                'country' => 'GLOBAL',
                'flag' => '🌐',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Worldwide holiday celebrated across many cultures and nations.'
            ];
            $holidays[sprintf('%04d-12-31', $year)] = [
                'name' => "New Year's Eve",
                'local_name' => "New Year's Eve",
                'country' => 'GLOBAL',
                'flag' => '🌐',
                'type' => 'government',
                'is_day_off' => true,
                'description' => 'Closing day of the calendar year.'
            ];
        }

        return $holidays;
    }

    /**
     * Get user-defined custom days off from database for a specified month/year.
     */
    function getUserCustomDaysOff(PDO $db, int $userId, int $year, int $month): array {
        $customs = [];
        try {
            $monthPrefix = sprintf('-%02d-%04d', $month, $year);
            $monthPrefixIso = sprintf('%04d-%02d-', $year, $month);
            
            $stmt = $db->prepare("SELECT id, holiday_name, holiday_date, holiday_type, country_code, is_day_off, notes 
                                  FROM user_holidays 
                                  WHERE user_id = :uid 
                                  AND (holiday_date LIKE :p1 OR holiday_date LIKE :p2)");
            $stmt->execute([
                'uid' => $userId,
                'p1' => '%' . $monthPrefix,
                'p2' => $monthPrefixIso . '%'
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $r) {
                // Normalize date to YYYY-MM-DD
                $raw = $r['holiday_date'];
                $ts = strtotime(str_replace('/', '-', $raw));
                if ($ts) {
                    $iso = date('Y-m-d', $ts);
                    $customs[$iso] = [
                        'id' => (int)$r['id'],
                        'name' => $r['holiday_name'],
                        'local_name' => $r['holiday_name'],
                        'country' => $r['country_code'] ?? 'CUSTOM',
                        'flag' => '🏖️',
                        'type' => $r['holiday_type'] ?? 'custom',
                        'is_day_off' => (bool)($r['is_day_off'] ?? true),
                        'description' => $r['notes'] ?? 'Custom day off configured by user.'
                    ];
                }
            }
        } catch (Throwable $e) {
            error_log("Error fetching user custom holidays: " . $e->getMessage());
        }

        return $customs;
    }

    /**
     * Get all active calendar days off (both government official and user custom)
     * indexed by day number (1..31) for the requested month.
     *
     * @param PDO $db
     * @param int $userId
     * @param int $year
     * @param int $month
     * @param string $countryCode
     * @return array [ dayNumber => [ holiday_item, ... ] ]
     */
    function getMonthHolidaysByDay(PDO $db, int $userId, int $year, int $month, string $countryCode = 'KH'): array {
        $gov = getOfficialGovernmentHolidays($year, $countryCode);
        $userCustom = getUserCustomDaysOff($db, $userId, $year, $month);

        $merged = array_merge($gov, $userCustom);
        $byDay = [];

        foreach ($merged as $isoDate => $info) {
            $ts = strtotime($isoDate);
            if ($ts && (int)date('n', $ts) === $month && (int)date('Y', $ts) === $year) {
                $day = (int)date('j', $ts);
                $info['iso_date'] = $isoDate;
                $info['day'] = $day;
                $info['day_name'] = date('l', $ts);
                $byDay[$day][] = $info;
            }
        }

        return $byDay;
    }
}
