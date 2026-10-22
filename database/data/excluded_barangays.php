<?php

/**
 * Areas carved out of database/data/psgc.php because they're only reachable
 * by boat — confirmed with the user, source cited per entry, 2026-10-22.
 * Enforced server-side by App\Services\ServiceArea for BOTH regular menu
 * orders and catering bookings (never trust the browser/dropdown alone).
 *
 * Two shapes:
 *   - 'municipalities': the ENTIRE municipality is excluded (used when every
 *     barangay in it is boat-only, so there's no partial/mainland portion
 *     worth tracking barangay-by-barangay).
 *   - 'barangays': only specific barangays within an otherwise-included
 *     municipality are excluded (the municipality itself has real mainland
 *     coverage too).
 */

return [
    'municipalities' => [
        [
            'province' => 'Occidental Mindoro',
            'municipality' => 'Lubang',
            'reason' => 'boat access only',
            'source' => 'https://en.wikipedia.org/wiki/Lubang_Island — the whole municipality sits on Lubang Island, reached by ferry from Batangas, no road connection to mainland Mindoro',
        ],
        [
            'province' => 'Occidental Mindoro',
            'municipality' => 'Looc',
            'reason' => 'boat access only',
            'source' => 'https://en.wikipedia.org/wiki/Lubang_Island — covers the rest of Lubang Island plus Ambil, Golo and minor islets; no mainland Mindoro territory',
        ],
    ],

    'barangays' => [
        // Ilin Island, part of San Jose
        ['province' => 'Occidental Mindoro', 'municipality' => 'San Jose', 'barangay' => 'Ansiray', 'reason' => 'boat access only', 'source' => 'https://en.wikipedia.org/wiki/Ilin_Island'],
        ['province' => 'Occidental Mindoro', 'municipality' => 'San Jose', 'barangay' => 'Bangkal', 'reason' => 'boat access only', 'source' => 'https://en.wikipedia.org/wiki/Ilin_Island'],
        ['province' => 'Occidental Mindoro', 'municipality' => 'San Jose', 'barangay' => 'Buri', 'reason' => 'boat access only', 'source' => 'https://en.wikipedia.org/wiki/Ilin_Island'],
        ['province' => 'Occidental Mindoro', 'municipality' => 'San Jose', 'barangay' => 'Catayungan', 'reason' => 'boat access only', 'source' => 'https://en.wikipedia.org/wiki/Ilin_Island'],
        ['province' => 'Occidental Mindoro', 'municipality' => 'San Jose', 'barangay' => 'Iling Proper', 'reason' => 'boat access only', 'source' => 'https://en.wikipedia.org/wiki/Ilin_Island'],
        ['province' => 'Occidental Mindoro', 'municipality' => 'San Jose', 'barangay' => 'Inasakan', 'reason' => 'boat access only', 'source' => 'https://en.wikipedia.org/wiki/Ilin_Island'],
        ['province' => 'Occidental Mindoro', 'municipality' => 'San Jose', 'barangay' => 'Ipil', 'reason' => 'boat access only', 'source' => 'https://en.wikipedia.org/wiki/Ilin_Island'],
        ['province' => 'Occidental Mindoro', 'municipality' => 'San Jose', 'barangay' => 'Labangan Iling', 'reason' => 'boat access only', 'source' => 'https://en.wikipedia.org/wiki/Ilin_Island'],
        ['province' => 'Occidental Mindoro', 'municipality' => 'San Jose', 'barangay' => 'Natandol', 'reason' => 'boat access only', 'source' => 'https://en.wikipedia.org/wiki/Ilin_Island'],
        ['province' => 'Occidental Mindoro', 'municipality' => 'San Jose', 'barangay' => 'Pawican', 'reason' => 'boat access only', 'source' => 'https://en.wikipedia.org/wiki/Ilin_Island'],

        // Ambulong Island, part of San Jose — confirmed with the user
        ['province' => 'Occidental Mindoro', 'municipality' => 'San Jose', 'barangay' => 'Ambulong', 'reason' => 'boat access only', 'source' => 'https://en.wikipedia.org/wiki/Ambulong'],
    ],
];
