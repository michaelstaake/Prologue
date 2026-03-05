<?php

class TimezoneCatalog {
    private const OFFSET_ORDER = [
        'UTC-12:00','UTC-11:00','UTC-10:00','UTC-9:30','UTC-9:00',
        'UTC-8:00','UTC-7:00','UTC-6:00','UTC-5:00','UTC-4:30',
        'UTC-4:00','UTC-3:30','UTC-3:00','UTC-2:00','UTC-1:00',
        'UTC+0','UTC+1:00','UTC+2:00','UTC+3:00','UTC+3:30',
        'UTC+4:00','UTC+4:30','UTC+5:00','UTC+5:30','UTC+5:45',
        'UTC+6:00','UTC+6:30','UTC+7:00','UTC+8:00','UTC+8:30',
        'UTC+8:45','UTC+9:00','UTC+9:30','UTC+10:00','UTC+10:30',
        'UTC+11:00','UTC+12:00','UTC+12:45','UTC+13:00','UTC+14:00'
    ];

    private const PRIMARY_ZONE_BY_OFFSET = [
        'UTC-12:00' => 'Etc/GMT+12',
        'UTC-11:00' => 'Pacific/Pago_Pago',
        'UTC-10:00' => 'Pacific/Honolulu',
        'UTC-9:30' => 'Pacific/Marquesas',
        'UTC-9:00' => 'America/Anchorage',
        'UTC-8:00' => 'America/Los_Angeles',
        'UTC-7:00' => 'America/Denver',
        'UTC-6:00' => 'America/Chicago',
        'UTC-5:00' => 'America/New_York',
        'UTC-4:30' => 'America/Caracas',
        'UTC-4:00' => 'America/Halifax',
        'UTC-3:30' => 'America/St_Johns',
        'UTC-3:00' => 'America/Argentina/Buenos_Aires',
        'UTC-2:00' => 'America/Noronha',
        'UTC-1:00' => 'Atlantic/Azores',
        'UTC+0' => 'Europe/London',
        'UTC+1:00' => 'Europe/Berlin',
        'UTC+2:00' => 'Europe/Helsinki',
        'UTC+3:00' => 'Europe/Moscow',
        'UTC+3:30' => 'Asia/Tehran',
        'UTC+4:00' => 'Asia/Dubai',
        'UTC+4:30' => 'Asia/Kabul',
        'UTC+5:00' => 'Asia/Karachi',
        'UTC+5:30' => 'Asia/Kolkata',
        'UTC+5:45' => 'Asia/Kathmandu',
        'UTC+6:00' => 'Asia/Dhaka',
        'UTC+6:30' => 'Asia/Yangon',
        'UTC+7:00' => 'Asia/Bangkok',
        'UTC+8:00' => 'Asia/Singapore',
        'UTC+8:30' => 'Asia/Pyongyang',
        'UTC+8:45' => 'Australia/Eucla',
        'UTC+9:00' => 'Asia/Tokyo',
        'UTC+9:30' => 'Australia/Darwin',
        'UTC+10:00' => 'Australia/Brisbane',
        'UTC+10:30' => 'Australia/Lord_Howe',
        'UTC+11:00' => 'Pacific/Noumea',
        'UTC+12:00' => 'Pacific/Auckland',
        'UTC+12:45' => 'Pacific/Chatham',
        'UTC+13:00' => 'Pacific/Apia',
        'UTC+14:00' => 'Pacific/Kiritimati'
    ];

    private const SEARCH_TAGS_BY_OFFSET = [
        'UTC-12:00' => 'international date line west baker island',
        'UTC-11:00' => 'american samoa midway',
        'UTC-10:00' => 'hawaii',
        'UTC-9:30' => 'marquesas',
        'UTC-9:00' => 'alaska',
        'UTC-8:00' => 'pacific us canada los angeles',
        'UTC-7:00' => 'mountain us canada denver',
        'UTC-6:00' => 'central us canada chicago',
        'UTC-5:00' => 'eastern us canada new york',
        'UTC-4:30' => 'caracas',
        'UTC-4:00' => 'atlantic halifax',
        'UTC-3:30' => 'newfoundland st johns',
        'UTC-3:00' => 'argentina buenos aires brasilia',
        'UTC-2:00' => 'fernando de noronha',
        'UTC-1:00' => 'azores',
        'UTC+0' => 'london dublin lisbon greenwich',
        'UTC+1:00' => 'berlin paris madrid rome',
        'UTC+2:00' => 'helsinki athens cairo',
        'UTC+3:00' => 'moscow riyadh nairobi',
        'UTC+3:30' => 'tehran',
        'UTC+4:00' => 'dubai abu dhabi',
        'UTC+4:30' => 'kabul',
        'UTC+5:00' => 'karachi tashkent',
        'UTC+5:30' => 'india kolkata mumbai delhi sri lanka',
        'UTC+5:45' => 'kathmandu nepal',
        'UTC+6:00' => 'dhaka almaty',
        'UTC+6:30' => 'yangon myanmar',
        'UTC+7:00' => 'bangkok jakarta ho chi minh',
        'UTC+8:00' => 'singapore beijing hong kong perth',
        'UTC+8:30' => 'pyongyang',
        'UTC+8:45' => 'eucla',
        'UTC+9:00' => 'tokyo seoul',
        'UTC+9:30' => 'darwin',
        'UTC+10:00' => 'brisbane guam',
        'UTC+10:30' => 'lord howe',
        'UTC+11:00' => 'noumea solomon islands',
        'UTC+12:00' => 'auckland fiji',
        'UTC+12:45' => 'chatham islands',
        'UTC+13:00' => 'apia tonga',
        'UTC+14:00' => 'kiritimati line islands'
    ];

    public static function getAllowedOffsets(): array {
        return self::OFFSET_ORDER;
    }

    public static function getEntries(): array {
        $entries = [];

        foreach (self::OFFSET_ORDER as $offset) {
            $zoneId = self::PRIMARY_ZONE_BY_OFFSET[$offset] ?? $offset;
            $search = trim(strtolower(
                $zoneId . ' ' .
                str_replace(['/', '_'], ' ', $zoneId) . ' ' .
                $offset . ' ' .
                (self::SEARCH_TAGS_BY_OFFSET[$offset] ?? '')
            ));

            $entries[] = [
                'offset' => $offset,
                'id' => $zoneId,
                'label' => $zoneId . ' (' . $offset . ')',
                'search' => $search,
            ];
        }

        return $entries;
    }

    public static function findEntryByOffset(string $offset): ?array {
        foreach (self::getEntries() as $entry) {
            if ((string)($entry['offset'] ?? '') === $offset) {
                return $entry;
            }
        }

        return null;
    }
}
