<?php

/**
 * Static province → municipality → barangay list for Occidental Mindoro and
 * Oriental Mindoro, sourced from the PSA PSGC (via philatlas.com, Wikipedia
 * cross-checked for San Jose/Ilin/Ambulong/Lubang/Looc) as of 2026-10-22.
 * Deliberately NOT a runtime API call — this app only serves these two
 * provinces, so the whole dataset is small enough to ship as a plain file
 * and works offline / on Railway with no network dependency.
 *
 * Service-area eligibility (which of these are actually orderable) is a
 * SEPARATE concern — see database/data/excluded_barangays.php and
 * App\Services\ServiceArea, which filter this list rather than duplicate it.
 */

return [
    'Occidental Mindoro' => [
        'Abra de Ilog' => [
            'Armado', 'Balao', 'Cabacao', 'Lumangbayan', 'Poblacion',
            'San Vicente', 'Santa Maria', 'Tibag', 'Udalo', 'Wawa',
        ],
        'Calintaan' => [
            'Concepcion', 'Iriron', 'Malpalon', 'New Dagupan', 'Poblacion',
            'Poypoy', 'Tanyag',
        ],
        'Looc' => [
            'Agkawayan', 'Ambil', 'Balikyas', 'Bonbon', 'Bulacan', 'Burol',
            'Guitna', 'Kanluran', 'Talaotao',
        ],
        'Lubang' => [
            'Araw at Bituin', 'Bagong Sikat', 'Banaag at Pag-asa', 'Binakas',
            'Cabra', 'Likas ng Silangan', 'Maginhawa', 'Maligaya', 'Maliig',
            'Ninikat ng Pag-asa', 'Paraiso', 'Surville', 'Tagbac', 'Tangal',
            'Tilik', 'Vigo',
        ],
        'Magsaysay' => [
            'Alibog', 'Caguray', 'Calawag', 'Gapasan', 'Laste', 'Lourdes',
            'Nicolas', 'Paclolo', 'Poblacion', 'Purnaga', 'Santa Teresa',
            'Sibalat',
        ],
        'Mamburao' => [
            'Balansay', 'Fatima', 'Payompon', 'Poblacion 1', 'Poblacion 2',
            'Poblacion 3', 'Poblacion 4', 'Poblacion 5', 'Poblacion 6',
            'Poblacion 7', 'Poblacion 8', 'San Luis', 'Talabaan',
            'Tangkalan', 'Tayamaan',
        ],
        'Paluan' => [
            'Alipaoy', 'Bagong Silang Poblacion', 'Handang Tumulong Poblacion',
            'Harrison', 'Lumangbayan', 'Mananao', 'Mapalad Poblacion',
            'Marikit', 'Pag-asa ng Bayan Poblacion', 'San Jose Poblacion',
            'Silahis ng Pag-asa Poblacion', 'Tubili',
        ],
        'Rizal' => [
            'Adela', 'Aguas', 'Magsikap', 'Malawaan', 'Manoot', 'Pitogo',
            'Rizal', 'Rumbang', 'Salvacion', 'San Pedro', 'Santo Niño',
        ],
        'Sablayan' => [
            'Batong Buhay', 'Buenavista', 'Burgos', 'Claudio Salgado',
            'General Emilio Aguinaldo', 'Ibud', 'Ilvita', 'Lagnas', 'Ligaya',
            'Malisbong', 'Paetan', 'Pag-asa', 'Poblacion', 'San Agustin',
            'San Francisco', 'San Nicolas', 'San Vicente', 'Santa Lucia',
            'Santo Niño', 'Tagumpay', 'Tuban', 'Victoria',
        ],
        'San Jose' => [
            'Ambulong', 'Ansiray', 'Bagong Sikat', 'Bangkal', 'Barangay 1',
            'Barangay 2', 'Barangay 3', 'Barangay 4', 'Barangay 5',
            'Barangay 6', 'Barangay 7', 'Barangay 8', 'Batasan', 'Bayotbot',
            'Bubog', 'Buri', 'Camburay', 'Caminawit', 'Catayungan', 'Central',
            'Iling Proper', 'Inasakan', 'Ipil', 'La Curva', 'Labangan Iling',
            'Labangan Poblacion', 'Mabini', 'Magbay', 'Mangarin', 'Mapaya',
            'Monte Claro', 'Murtha', 'Naibuan', 'Natandol', 'Pag-asa',
            'Pawican', 'San Agustin', 'San Isidro', 'San Roque',
        ],
        'Santa Cruz' => [
            'Alacaak', 'Barahan', 'Casague', 'Dayap', 'Kurtinganan',
            'Lumangbayan', 'Mulawin', 'Pinagturilan', 'Poblacion I',
            'Poblacion II', 'San Vicente',
        ],
    ],

    'Oriental Mindoro' => [
        'Baco' => [
            'Alag', 'Bangkatan', 'Baras', 'Bayanan', 'Burbuli', 'Catwiran I',
            'Catwiran II', 'Dulangan I', 'Dulangan II', 'Lantuyang',
            'Lumang Bayan', 'Malapad', 'Mangangan I', 'Mangangan II',
            'Mayabig', 'Pambisan', 'Poblacion', 'Pulang-Tubig',
            'Putican-Cabulo', 'San Andres', 'San Ignacio', 'Santa Cruz',
            'Santa Rosa I', 'Santa Rosa II', 'Tabon-tabon', 'Tagumpay',
            'Water',
        ],
        'Bansud' => [
            'Alcadesma', 'Bato', 'Conrazon', 'Malo', 'Manihala', 'Pag-asa',
            'Poblacion', 'Proper Bansud', 'Proper Tiguisan', 'Rosacara',
            'Salcedo', 'Sumagui', 'Villa Pag-asa',
        ],
        'Bongabong' => [
            'Anilao', 'Aplaya', 'Bagumbayan I', 'Bagumbayan II', 'Batangan',
            'Bukal', 'Camantigue', 'Carmundo', 'Cawayan', 'Dayhagan',
            'Formon', 'Hagan', 'Hagupit', 'Ipil', 'Kaligtasan', 'Labasan',
            'Labonan', 'Libertad', 'Lisap', 'Luna', 'Malitbog', 'Mapang',
            'Masaguisi', 'Mina de Oro', 'Morente', 'Ogbot', 'Orconuma',
            'Poblacion', 'Polusahi', 'Sagana', 'San Isidro', 'San Jose',
            'San Juan', 'Santa Cruz', 'Sigange', 'Tawas',
        ],
        'Bulalacao' => [
            'Bagong Sikat', 'Balatasan', 'Benli', 'Cabugao', 'Cambunang',
            'Campaasan', 'Maasin', 'Maujao', 'Milagrosa', 'Nasukob',
            'Poblacion', 'San Francisco', 'San Isidro', 'San Juan',
            'San Roque',
        ],
        'Calapan City' => [
            'Balingayan', 'Balite', 'Baruyan', 'Batino', 'Bayanan I',
            'Bayanan II', 'Biga', 'Bondoc', 'Bucayao', 'Buhuan', 'Bulusan',
            'Calero', 'Camansihan', 'Camilmil', 'Canubing I', 'Canubing II',
            'Comunal', 'Guinobatan', 'Gulod', 'Gutad', 'Ibaba East',
            'Ibaba West', 'Ilaya', 'Lalud', 'Lazareto', 'Libis',
            'Lumang Bayan', 'Mahal na Pangalan', 'Maidlang', 'Malad',
            'Malamig', 'Managpi', 'Masipit', 'Nag-iba I', 'Nag-iba II',
            'Navotas', 'Pachoca', 'Palhi', 'Panggalaan', 'Parang', 'Patas',
            'Personas', 'Putingtubig', 'Salong', 'San Antonio',
            'San Vicente Central', 'San Vicente East', 'San Vicente North',
            'San Vicente South', 'San Vicente West', 'Santa Cruz',
            'Santa Isabel', 'Santa Maria Village', 'Santa Rita', 'Santo Niño',
            'Sapul', 'Silonay', 'Suqui', 'Tawagan', 'Tawiran', 'Tibag',
            'Wawa',
        ],
        'Gloria' => [
            'Agos', 'Agsalin', 'Alma Villa', 'Andres Bonifacio', 'Balete',
            'Banus', 'Banutan', 'Bulaklakan', 'Buong Lupa',
            'Gaudencio Antonino', 'Guimbonan', 'Kawit', 'Lucio Laurel',
            'Macario Adriatico', 'Malamig', 'Malayong', 'Maligaya',
            'Malubay', 'Manguyang', 'Maragooc', 'Mirayan', 'Narra',
            'Papandungin', 'San Antonio', 'Santa Maria', 'Santa Theresa',
            'Tambong',
        ],
        'Mansalay' => [
            'B. del Mundo', 'Balugo', 'Bonbon', 'Budburan', 'Cabalwa',
            'Don Pedro', 'Maliwanag', 'Manaul', 'Panaytayan', 'Poblacion',
            'Roma', 'Santa Brigida', 'Santa Maria', 'Santa Teresita',
            'Villa Celestial', 'Wasig', 'Waygan',
        ],
        'Naujan' => [
            'Adrialuna', 'Andres Ilagan', 'Antipolo', 'Apitong', 'Arangin',
            'Aurora', 'Bacungan', 'Bagong Buhay', 'Balite', 'Bancuro',
            'Banuton', 'Barcenaga', 'Bayani', 'Buhangin', 'Caburo',
            'Concepcion', 'Dao', 'Del Pilar', 'Estrella', 'Evangelista',
            'Gamao', 'General Esco', 'Herrera', 'Inarawan', 'Kalinisan',
            'Laguna', 'Mabini', 'Magtibay', 'Mahabang Parang', 'Malaya',
            'Malinao', 'Malvar', 'Masagana', 'Masaguing', 'Melgar A',
            'Melgar B', 'Metolza', 'Montelago', 'Montemayor', 'Motoderazo',
            'Mulawin', 'Nag-iba I', 'Nag-iba II', 'Pagkakaisa', 'Paitan',
            'Paniquian', 'Pinagsabangan I', 'Pinagsabangan II', 'Piñahan',
            'Poblacion I', 'Poblacion II', 'Poblacion III', 'Sampaguita',
            'San Agustin I', 'San Agustin II', 'San Andres', 'San Antonio',
            'San Carlos', 'San Isidro', 'San Jose', 'San Luis', 'San Nicolas',
            'San Pedro', 'Santa Cruz', 'Santa Isabel', 'Santa Maria',
            'Santiago', 'Santo Niño', 'Tagumpay', 'Tigkan',
        ],
        'Pinamalayan' => [
            'Anoling', 'Bacungan', 'Bangbang', 'Banilad', 'Buli', 'Cacawan',
            'Calingag', 'Del Razon', 'Guinhawa', 'Inclanay', 'Lumangbayan',
            'Malaya', 'Maliangcog', 'Maningcol', 'Marayos', 'Marfrancisco',
            'Nabuslot', 'Pagalagala', 'Palayan', 'Pambisan Malaki',
            'Pambisan Munti', 'Panggulayan', 'Papandayan', 'Pili',
            'Quinabigan', 'Ranzo', 'Rosario', 'Sabang', 'Santa Isabel',
            'Santa Maria', 'Santa Rita', 'Santo Niño', 'Wawa', 'Zone I',
            'Zone II', 'Zone III', 'Zone IV',
        ],
        'Pola' => [
            'Bacawan', 'Bacungan', 'Batuhan', 'Bayanan', 'Biga',
            'Buhay na Tubig', 'Calima', 'Calubasanhon', 'Campamento',
            'Casiligan', 'Malibago', 'Maluanluan', 'Matulatula', 'Misong',
            'Pahilahan', 'Panikihan', 'Pula', 'Puting Cacao', 'Tagbakin',
            'Tagumpay', 'Tiguihan', 'Zone I', 'Zone II',
        ],
        'Puerto Galera' => [
            'Aninuan', 'Baclayan', 'Balatero', 'Dulangan', 'Palangan',
            'Poblacion', 'Sabang', 'San Antonio', 'San Isidro', 'Santo Niño',
            'Sinandigan', 'Tabinay', 'Villaflor',
        ],
        'Roxas' => [
            'Bagumbayan', 'Cantil', 'Dangay', 'Happy Valley', 'Libertad',
            'Libtong', 'Little Tanauan', 'Mabuhay', 'Maraska', 'Odiong',
            'Paclasan', 'San Aquilino', 'San Isidro', 'San Jose',
            'San Mariano', 'San Miguel', 'San Rafael', 'San Vicente', 'Uyao',
            'Victoria',
        ],
        'San Teodoro' => [
            'Bigaan', 'Caagutayan', 'Calangatan', 'Calsapa', 'Ilag',
            'Lumangbayan', 'Poblacion', 'Tacligan',
        ],
        'Socorro' => [
            'Bagsok', 'Batong Dalig', 'Bayuin', 'Bugtong na Tuog', 'Calocmoy',
            'Calubayan', 'Catiningan', 'Fortuna', 'Happy Valley',
            'Leuteboro I', 'Leuteboro II', 'Ma. Concepcion', 'Mabuhay I',
            'Mabuhay II', 'Malugay', 'Matungao', 'Monteverde', 'Pasi I',
            'Pasi II', 'Santo Domingo', 'Subaan', 'Villareal', 'Zone I',
            'Zone II', 'Zone III', 'Zone IV',
        ],
        'Victoria' => [
            'Alcate', 'Antonino', 'Babangonan', 'Bagong Buhay',
            'Bagong Silang', 'Bambanin', 'Bethel', 'Canaan', 'Concepcion',
            'Duongan', 'Jose Leido Jr.', 'Loyal', 'Mabini', 'Macatoc',
            'Malabo', 'Merit', 'Ordovilla', 'Pakyas', 'Poblacion I',
            'Poblacion II', 'Poblacion III', 'Poblacion IV', 'Sampaguita',
            'San Antonio', 'San Cristobal', 'San Gabriel', 'San Gelacio',
            'San Isidro', 'San Juan', 'San Narciso', 'Urdaneta',
            'Villa Cerveza',
        ],
    ],
];
