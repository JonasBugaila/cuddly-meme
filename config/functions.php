<?php
/**
 * Bendros funkcijos
 * * Šiame faile saugomos bendros funkcijos, naudojamos visoje sistemoje
 */

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

function start_session() {
    if (session_status() == PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

function is_logged_in() {
    start_session();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function is_admin() {
    start_session();
    return isset($_SESSION['user_level']) && $_SESSION['user_level'] == 'admin';
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function generate_password($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}

function format_date($date, $format = 'Y-m-d H:i:s') {
    $datetime = new DateTime($date);
    return $datetime->format($format);
}

function set_message($message, $type = 'info') {
    start_session();
    $_SESSION['message'] = [
        'text' => $message,
        'type' => $type
    ];
}

function get_message() {
    start_session();
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        unset($_SESSION['message']);
        return $message;
    }
    return null;
}

function display_message() {
    $message = get_message();
    if ($message) {
        $type_class = 'alert-' . $message['type'];
        echo "<div class='alert $type_class'>{$message['text']}</div>";
    }
}

function generate_csrf_token() {
    start_session();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    start_session();
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

function current_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    return "$protocol://$host$uri";
}

function has_all_keys($array, $keys) {
    foreach ($keys as $key) {
        if (!array_key_exists($key, $array)) {
            return false;
        }
    }
    return true;
}

function generate_printable_table($title, $institution, $headers, $data, $options = []) {
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
    $options = array_merge($default_options, $options);
    $print_id = 'print_' . uniqid();
    $html = '<div id="' . $print_id . '_container">';
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
    $html .= '<div id="' . $print_id . '_printable" style="counter-reset: page 0;">';
    $html .= '<div class="text-center mb-4">';
    $html .= '<h3>' . htmlspecialchars($institution) . '</h3>';
    $html .= '<h4>' . htmlspecialchars($title) . '</h4>';
    $html .= '<p>Spausdinimo data: ' . date($options['date_format']) . '</p>';
    $html .= '</div>';
    $html .= '<table class="' . $options['table_class'] . '">';
    $html .= '<thead><tr>';
    foreach ($headers as $header) {
        $html .= '<th>' . htmlspecialchars($header) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . $cell . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    $html .= '<div class="mt-5"><div class="row"><div class="col-6">';
    $html .= '<p>' . $options['signature_text'] . ' ___________________</p>';
    if (!empty($options['signature_name'])) {
        $html .= '<p>' . htmlspecialchars($options['signature_name']) . '</p>';
    }
    $html .= '</div></div></div></div></div>';
    $html .= '<style>
        @media print {
            body { padding: 20mm; }
            .d-print-none { display: none !important; }
            .page-number:before { content: counter(page); }
            @page { size: A4; margin: 20mm; counter-increment: page; }
            table { width: 100%; border-collapse: collapse; }
            table th, table td { border: 1px solid #000; padding: 8px; }
            table th { background-color: #f2f2f2; }
        }
    </style>';
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

function get_konkursai_events() {
    $conn = db_connect();
    $result = $conn->query("
        SELECT 
            konk_id, konkurso_pav, COALESCE(data, NULL) AS data, status, grupe
        FROM konkursai ORDER BY data ASC, konk_id ASC
    ");
    if (!$result) { return []; }
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $is_active = ($row['status'] ?? 1) == 0;
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
            $event['display'] = 'list-item';
        }
        $events[] = $event;
    }
    return $events;
}

function display_konkursai_calendar() {
    $events = get_konkursai_events();
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
            #calendar { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
            .fc-event { border-radius: 6px; font-weight: 500; font-size: 0.9em; }
        </style>
    </head>
    <body>
        <div class="container"><div id="calendar"></div></div>
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
                    initialView: 'dayGridMonth', locale: 'lt', timeZone: 'Europe/Vilnius',
                    events: <?php echo $events_json; ?>,
                    eventClick: function(info) { if (info.event.url) window.location.href = info.event.url; }
                });
                calendar.render();
            });
        </script>
    </body>
    </html>
    <?php
}

function log_action($action, $details = '') {
    start_session();
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Svečias';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Nežinomas IP';
    if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    $data = [
        'user_id' => $user_id,
        'action' => sanitize_input($action),
        'details' => sanitize_input($details),
        'ip_address' => sanitize_input($ip_address)
    ];
    db_insert('system_logs', $data);
}

/**
 * =====================================================================
 * PUSLAPIAVIMO IR RIKIAVIMO (PAGINATION & SORTING) PAGALBINĖS FUNKCIJOS
 * =====================================================================
 */

/**
 * Generuoja URL pridedant arba pakeičiant GET parametrus
 */
function build_url_with_params($new_params) {
    $query_params = $_GET;
    foreach ($new_params as $key => $value) {
        $query_params[$key] = $value;
    }
    return '?' . http_build_query($query_params);
}

/**
 * Generuoja rikiuojamą lentelės antraštę (HTML)
 */
function generate_sortable_header($column_db_name, $label, $current_sort, $current_dir) {
    // Jei paspaudžiama ant jau rikiuojamo stulpelio, keičiame kryptį. Kitaip - numatytoji ASC.
    $next_dir = ($current_sort === $column_db_name && $current_dir === 'ASC') ? 'DESC' : 'ASC';
    $url = build_url_with_params(['sort' => $column_db_name, 'dir' => $next_dir, 'page' => 1]); // Keičiant rikiavimą grąžiname į 1 puslapį
    
    $icon = '';
    if ($current_sort === $column_db_name) {
        $icon = $current_dir === 'ASC' ? '&nbsp;<i class="fas fa-sort-up"></i>' : '&nbsp;<i class="fas fa-sort-down"></i>';
    } else {
        $icon = '&nbsp;<i class="fas fa-sort text-muted" style="opacity: 0.3;"></i>';
    }

    return "<a href=\"{$url}\" class=\"text-dark text-decoration-none\">{$label}{$icon}</a>";
}

/**
 * Generuoja puslapiavimo elementus (HTML) ir limito pasirinkimą
 */
function render_pagination($total_items, $limit, $current_page) {
    $total_pages = ceil($total_items / $limit);
    if ($total_pages <= 1 && $total_items <= 10) return ''; // Nerodome jei nėra prasmės

    $limits = [10, 25, 50, 100];
    
    echo '<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-3">';
    
    // Limito pasirinkimas (puslapyje rodomų elementų skaičius)
    echo '<div class="d-flex align-items-center">';
    echo '<span class="me-2 text-muted">Rodyti:</span>';
    echo '<select class="form-select form-select-sm w-auto" onchange="window.location.href=this.value;">';
    foreach ($limits as $l) {
        $url = build_url_with_params(['limit' => $l, 'page' => 1]);
        $selected = ($l == $limit) ? 'selected' : '';
        echo "<option value=\"{$url}\" {$selected}>{$l}</option>";
    }
    echo '</select>';
    echo "<span class=\"ms-3 text-muted small\">Iš viso: <strong>{$total_items}</strong></span>";
    echo '</div>';

    // Puslapiai (1, 2, 3...)
    if ($total_pages > 1) {
        echo '<nav aria-label="Page navigation">';
        echo '<ul class="pagination pagination-sm mb-0">';
        
        // Atgal mygtukas
        $prev_disabled = ($current_page <= 1) ? 'disabled' : '';
        $prev_url = build_url_with_params(['page' => max(1, $current_page - 1)]);
        echo "<li class=\"page-item {$prev_disabled}\"><a class=\"page-link\" href=\"{$prev_url}\">&laquo;</a></li>";
        
        // Puslapių numeriai (rodome protingą rėžį, kad nebūtų 100 mygtukų)
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);
        
        if ($start_page > 1) {
            echo "<li class=\"page-item\"><a class=\"page-link\" href=\"" . build_url_with_params(['page' => 1]) . "\">1</a></li>";
            if ($start_page > 2) echo "<li class=\"page-item disabled\"><span class=\"page-link\">...</span></li>";
        }

        for ($i = $start_page; $i <= $end_page; $i++) {
            $active = ($i == $current_page) ? 'active' : '';
            $url = build_url_with_params(['page' => $i]);
            echo "<li class=\"page-item {$active}\"><a class=\"page-link\" href=\"{$url}\">{$i}</a></li>";
        }

        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) echo "<li class=\"page-item disabled\"><span class=\"page-link\">...</span></li>";
            echo "<li class=\"page-item\"><a class=\"page-link\" href=\"" . build_url_with_params(['page' => $total_pages]) . "\">{$total_pages}</a></li>";
        }

        // Pirmyn mygtukas
        $next_disabled = ($current_page >= $total_pages) ? 'disabled' : '';
        $next_url = build_url_with_params(['page' => min($total_pages, $current_page + 1)]);
        echo "<li class=\"page-item {$next_disabled}\"><a class=\"page-link\" href=\"{$next_url}\">&raquo;</a></li>";
        
        echo '</ul></nav>';
    }
    echo '</div>';
}


?>

