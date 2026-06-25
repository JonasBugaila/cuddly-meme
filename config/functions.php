<?php
/**
 * Bendros funkcijos
 * * Šiame faile saugomos bendros funkcijos, naudojamos visoje sistemoje
 */

/**
 * Saugus įvesties filtravimas
 * * @param string $data Įvesties duomenys
 * @return string Išvalyti duomenys
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Slaptažodžio šifravimas
 * * @param string $password Slaptažodis
 * @return string Šifruotas slaptažodis
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Slaptažodžio tikrinimas
 * * @param string $password Slaptažodis
 * @param string $hash Užšifruotas slaptažodis
 * @return bool Grąžina true jei slaptažodis teisingas
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Sesijos inicijavimas
 */
function start_session() {
    if (session_status() == PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

/**
 * Patikrinti ar vartotojas prisijungęs
 * * @return bool Grąžina true jei vartotojas prisijungęs
 */
function is_logged_in() {
    start_session();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Patikrinti ar vartotojas turi administratoriaus teises
 * * @return bool Grąžina true jei vartotojas turi administratoriaus teises
 */
function is_admin() {
    start_session();
    return isset($_SESSION['user_level']) && $_SESSION['user_level'] == 'admin';
}

/**
 * Nukreipti vartotoją į kitą puslapį
 * * @param string $url URL adresas
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Generuoti atsitiktinį slaptažodį
 * * @param int $length Slaptažodžio ilgis
 * @return string Sugeneruotas slaptažodis
 */
function generate_password($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    
    return $password;
}

/**
 * Formatuoti datą lietuvišku formatu
 * * @param string $date Data
 * @param string $format Formatas
 * @return string Suformatuota data
 */
function format_date($date, $format = 'Y-m-d H:i:s') {
    $datetime = new DateTime($date);
    return $datetime->format($format);
}

/**
 * Generuoti pranešimą
 * * @param string $message Pranešimo tekstas
 * @param string $type Pranešimo tipas (success, error, warning, info)
 */
function set_message($message, $type = 'info') {
    start_session();
    $_SESSION['message'] = [
        'text' => $message,
        'type' => $type
    ];
}

/**
 * Gauti pranešimą
 * * @return array|null Pranešimas arba null jei nėra
 */
function get_message() {
    start_session();
    
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        unset($_SESSION['message']);
        return $message;
    }
    
    return null;
}

/**
 * Rodyti pranešimą
 */
function display_message() {
    $message = get_message();
    
    if ($message) {
        $type_class = 'alert-' . $message['type'];
        echo "<div class='alert $type_class'>{$message['text']}</div>";
    }
}

/**
 * Generuoti CSRF žetoną
 * * @return string CSRF žetonas
 */
function generate_csrf_token() {
    start_session();
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Tikrinti CSRF žetoną
 * * @param string $token CSRF žetonas
 * @return bool Grąžina true jei žetonas teisingas
 */
function verify_csrf_token($token) {
    start_session();
    
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    
    return true;
}

/**
 * Gauti dabartinį URL
 * * @return string Dabartinis URL
 */
function current_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    
    return "$protocol://$host$uri";
}

/**
 * Patikrinti ar masyvas turi visus reikiamus raktus
 * * @param array $array Masyvas
 * @param array $keys Raktai
 * @return bool Grąžina true jei turi visus raktus, kitaip false
 */
function has_all_keys($array, $keys) {
    foreach ($keys as $key) {
        if (!array_key_exists($key, $array)) {
            return false;
        }
    }
    return true;
}

/**
 * Lentelės spausdinimo funkcija
 * * Ši funkcija generuoja spausdinamą lentelę su antrašte, parašo vieta ir puslapių numeracija
 * * @param string $title Dokumento pavadinimas
 * @param string $institution Įstaigos pavadinimas
 * @param array $headers Lentelės antraštės
 * @param array $data Lentelės duomenys
 * @param array $options Papildomos parinktys (neprivaloma)
 * @return string HTML kodas spausdinimui
 */
function generate_printable_table($title, $institution, $headers, $data, $options = []) {
    // Nustatome numatytąsias parinktis
    $default_options = [
        'date_format' => 'Y-m-d',
        'signature_text' => 'Parašas',
        'signature_name' => '',
        'page_text' => 'Puslapis',
        'print_button_text' => 'Spausdinti',
        'back_button_text' => 'Grįžti',
        'table_class' => 'table table-bordered',
        'include_print_button' => true,
        'include_back_button' => true
    ];
    
    // Sujungiame numatytąsias parinktis su vartotojo pateiktomis
    $options = array_merge($default_options, $options);
    
    // Generuojame unikalų ID spausdinimo mygtukui
    $print_id = 'print_' . uniqid();
    
    // Pradedame HTML kodą
    $html = '<div id="' . $print_id . '_container">';
    
    // Pridedame mygtukus
    if ($options['include_print_button'] || $options['include_back_button']) {
        $html .= '<div class="d-print-none mb-3">';
        
        if ($options['include_print_button']) {
            $html .= '<button onclick="window.print();" class="btn btn-primary me-2">' . $options['print_button_text'] . '</button>';
        }
        
        if ($options['include_back_button']) {
            $html .= '<button onclick="window.history.back();" class="btn btn-secondary">' . $options['back_button_text'] . '</button>';
        }
        
        $html .= '</div>';
    }
    
    // Pradedame spausdinamą dalį
    $html .= '<div id="' . $print_id . '_printable" style="counter-reset: page 0;">';
    
    // Pridedame antraštę
    $html .= '<div class="text-center mb-4">';
    $html .= '<h3>' . htmlspecialchars($institution) . '</h3>';
    $html .= '<h4>' . htmlspecialchars($title) . '</h4>';
    $html .= '<p>Spausdinimo data: ' . date($options['date_format']) . '</p>';
    $html .= '</div>';
    
    // Pridedame lentelę
    $html .= '<table class="' . $options['table_class'] . '">';
    
    // Lentelės antraštė
    $html .= '<thead><tr>';
    foreach ($headers as $header) {
        $html .= '<th>' . htmlspecialchars($header) . '</th>';
    }
    $html .= '</tr></thead>';
    
    // Lentelės duomenys
    $html .= '<tbody>';
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . $cell . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody>';
    
    $html .= '</table>';
    
    // Pridedame parašo vietą
    $html .= '<div class="mt-5">';
    $html .= '<div class="row">';
    $html .= '<div class="col-6">';
    $html .= '<p>' . $options['signature_text'] . ' ___________________</p>';
    if (!empty($options['signature_name'])) {
        $html .= '<p>' . htmlspecialchars($options['signature_name']) . '</p>';
    }
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Baigiame spausdinamą dalį
    $html .= '</div>';
    
    // Baigiame konteinerį
    $html .= '</div>';
    
    // Pridedame CSS stilius
    $html .= '<style>
        @media print {
            body {
                padding: 20mm;
            }
            .d-print-none {
                display: none !important;
            }
            .page-number:before {
                content: counter(page);
            }
            @page {
                size: A4;
                margin: 20mm;
                counter-increment: page;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            table th, table td {
                border: 1px solid #000;
                padding: 8px;
            }
            table th {
                background-color: #f2f2f2;
            }
        }
    </style>';
    
    // Pridedame JavaScript, kad užtikrintume puslapio numerį bent 1
    $html .= '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var pageNumberSpans = document.getElementsByClassName("page-number");
            for (var i = 0; i < pageNumberSpans.length; i++) {
                if (!pageNumberSpans[i].innerText || pageNumberSpans[i].innerText === "0") {
                    pageNumberSpans[i].innerText = "1";
                }
            }
        });
    </script>';
    
    return $html;
}

/**
 * === KALENDORIUS – JŪSŲ KONKURSAI (be warning'ų) ===
 * konk_id | konkurso_pav | status (0=aktyvus, 1=neaktyvus) | data
 */

function get_konkursai_events() {
    $conn = db_connect();
    
    $result = $conn->query("
        SELECT 
            konk_id,
            konkurso_pav,
            COALESCE(data, NULL) AS data,
            status,
            grupe
        FROM konkursai 
        ORDER BY data ASC, konk_id ASC
    ");

    if (!$result) {
        error_log("Kalendoriaus klaida: " . $conn->error);
        return [];
    }

    $events = [];
    while ($row = $result->fetch_assoc()) {
        $is_active = ($row['status'] ?? 1) == 0; // 0 = aktyvus
        $has_date = !empty($row['data']) && $row['data'] !== '0000-00-00';

        $event = [
            'id' => $row['konk_id'],
            'title' => ($row['konkurso_pav'] ?? 'Be pavadinimo') . ' (' . ($row['grupe'] ?? 'Nėra grupės') . ')',
            'backgroundColor' => $is_active ? '#28a745' : '#6c757d',
            'borderColor' => $is_active ? '#20c997' : '#495057',
            'textColor' => 'white',
            'url' => SITE_URL . '/modules/olympiads/view.php?id=' . $row['konk_id']
        ];

        if ($has_date) {
            $event['start'] = $row['data'];
        } else {
            $event['start'] = null;
            $event['display'] = 'list-item'; // rodo sąraše
        }

        $events[] = $event;
    }

    return $events;
}

function display_konkursai_calendar() {
    $events = get_konkursai_events();
    $active_count = count(array_filter($events, fn($e) => $e['backgroundColor'] == '#28a745'));
    $inactive_count = count($events) - $active_count;
    $events_with_date = array_filter($events, fn($e) => $e['start'] !== null);
    $events_json = json_encode($events_with_date, JSON_UNESCAPED_UNICODE);
    ?>
    <!DOCTYPE html>
    <html lang="lt">
    <head>
        <meta charset="UTF-8">
        <title>Konkursų kalendorius</title>
        <link rel="stylesheet" href="../assets/css/index.global.min.css">
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; background: #f8f9fa; }
            .container { max-width: 1200px; margin: 20px auto; padding: 0 15px; }
            .header { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; margin-bottom: 20px; }
            .stats { display: flex; justify-content: center; gap: 25px; margin-top: 15px; flex-wrap: wrap; }
            .stat { background: white; padding: 15px 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); min-width: 100px; }
            .stat.active { border-left: 5px solid #28a745; }
            .stat.inactive { border-left: 5px solid #6c757d; }
            .stat-number { font-size: 2em; font-weight: bold; margin: 5px 0; }
            .stat-label { color: #666; font-size: 0.85em; }
            #calendar { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
            .fc-event { border-radius: 6px; font-weight: 500; font-size: 0.9em; }
            .fc-list-event-title { font-weight: 500; }
        </style>
    </head>
    <body>
        <div class="container">
            <div id="calendar"></div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var calendarEl = document.getElementById('calendar');
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    locale: 'lt',
                    timeZone: 'Europe/Vilnius',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,listMonth'
                    },
                    buttonText: {
                        today: 'Šiandien',
                        month: 'Mėnuo',
                        week: 'Savaitė',
                        list: 'Sąrašas'
                    },
                    events: <?php echo $events_json; ?>,
                    eventClick: function(info) {
                        if (info.event.url) {
                            window.location.href = info.event.url;
                        }
                    },
                    eventDidMount: function(info) {
                        if (!info.event.start) {
                            info.el.style.opacity = '0.8';
                        }
                    }
                });
                calendar.render();
            });
        </script>
    </body>
    </html>
    <?php
}
/**
 * Įrašo įvykį į sistemos žurnalą
 * * @param string $action Veiksmo pavadinimas (pvz., 'Prisijungimas', 'Klaida', 'Vartotojo trynimas')
 * @param string $details Išsami informacija (neprivaloma)
 */
function log_action($action, $details = '') {
    start_session();
    
    // Gauname vartotojo ID, jei jis prisijungęs
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Svečias';
    
    // Gauname IP adresą, atsižvelgiant į galimus proxy (Cloudflare ir pan.)
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Nežinomas IP';
    if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    
    $data = [
        'user_id' => $user_id,
        'action' => sanitize_input($action),
        'details' => sanitize_input($details),
        'ip_address' => sanitize_input($ip_address),
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Naudojame db_insert įrašymui į duomenų bazę
    db_insert('system_logs', $data);
}
?>
