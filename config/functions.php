<?php
/**
 * Bendros funkcijos
 * Šiame faile saugomos bendros funkcijos, naudojamos visoje sistemoje
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
        if (defined('SESSION_NAME')) {
            session_name(SESSION_NAME);
        }
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
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
        $type_class = 'alert-' . sanitize_input($message['type']);
        $safe_text = htmlspecialchars($message['text'], ENT_QUOTES, 'UTF-8');
        echo "<div class='alert {$type_class}'>{$safe_text}</div>";
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

/**
 * =====================================================================
 * SPAUSDINIMAS
 * =====================================================================
 */
function generate_printable_table($title, $institution, $headers, $data, $options = [], $layout_key = 'protocol') {
    $layout_file = dirname(dirname(__FILE__)) . '/config/print_layout.json';
    $all_layouts = [];
    
    if (file_exists($layout_file)) {
        $all_layouts = json_decode(file_get_contents($layout_file), true) ?: [];
    }
    
    if (isset($all_layouts['header_html']) && !isset($all_layouts['protocol'])) {
        $old_layout = $all_layouts;
        $all_layouts = ['protocol' => $old_layout, 'evaluation' => $old_layout, 'codes' => $old_layout, 'signature' => $old_layout];
    }

    $layout = $all_layouts[$layout_key] ?? ($all_layouts['protocol'] ?? [
        'header_html' => '<div style="text-align: center; margin-bottom: 20px;"><h3>{{INSTITUTION}}</h3><h4 style="color: #444;">{{TITLE}}</h4></div>', 
        'footer_html' => '', 
        'margin_t' => 20, 'margin_b' => 20, 'margin_l' => 20, 'margin_r' => 20, 'font_size' => 12
    ]);
    
    $search = ['{{TITLE}}', '{{INSTITUTION}}', '{{DATE}}'];
    $replace = [htmlspecialchars($title), htmlspecialchars($institution), date('Y-m-d')];
    
    $header_html = str_replace($search, $replace, $layout['header_html'] ?? '');
    $footer_html = str_replace($search, $replace, $layout['footer_html'] ?? '');
    
    $html = '';
    
    static $base_injected = false;
    if (!$base_injected) {
        $html .= '<script>
            document.addEventListener("DOMContentLoaded", function() {
                // Truputis laiko dokumentui užsikrauti
                setTimeout(function() {
                    window.print();
                }, 400);
                
                // Atšaukus ar atspausdinus visada grįšime atgal (toje pačioje kortelėje)
                window.onafterprint = function() {
                    setTimeout(function() {
                        if (window.history.length > 1) {
                            window.history.back();
                        } else {
                            window.close(); // Atsarginis variantas
                        }
                    }, 100);
                };
            });
        </script>';

        $html .= '<div class="screen-loader" style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; background: #f8f9fc; position: fixed; top: 0; left: 0; width: 100%; z-index: 9999; font-family: sans-serif;">';
        $html .= '<div style="margin-bottom: 20px;"><svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#4e73df" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg></div>';
        $html .= '<h2 style="color: #333; margin: 0; font-size: 24px;">Ruošiamas spausdinimo langas...</h2>';
        $html .= '<p style="color: #6c757d; margin-top: 10px;">Tuoj atsidarys peržiūra.</p>';
        $html .= '<div style="margin-top: 25px; display: flex; gap: 10px;">';
        $html .= '<button onclick="window.history.back()" style="padding: 10px 20px; background: #e74a3b; color: white; border: none; border-radius: 4px; cursor: pointer;">Atšaukti ir grįžti</button>';
        $html .= '</div>';
        $html .= '</div>';
        
        $base_injected = true;
    }
    
    $print_id = 'print_' . uniqid();
    
    $html .= '<div id="' . $print_id . '_printable" class="print-wrapper" style="counter-reset: page 0;">';
    $html .= '<div class="print-header">' . $header_html . '</div>';
    
    $html .= '<table class="table print-table w-100">';
    $html .= '<thead class="table-light"><tr>';
    foreach ($headers as $header_text) {
        $html .= '<th>' . htmlspecialchars($header_text) . '</th>';
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
    
    $footer_extra = '';
    if (isset($layout['show_page_num']) && $layout['show_page_num'] == 1) {
        $footer_extra = '<div class="page-number" style="text-align: right; margin-top: 15px; font-size: 10pt; color: #666;"></div>';
    }
    
    $html .= '<div class="print-footer mt-4 pt-2">' . $footer_html . $footer_extra . '</div>';
    $html .= '</div>'; 
    
    $html .= '<style>
        table.print-table { border-collapse: collapse; margin-bottom: 20px; width: 100%; }
        table.print-table th, table.print-table td { border: 1px solid #222; padding: 6px 8px; }
        
        @media screen {
            .print-wrapper { 
                position: absolute; left: -9999px; top: -9999px; visibility: hidden;
            }
        }
        
        @media print {
            .screen-loader { display: none !important; }
            .print-wrapper {
                position: static; left: auto; top: auto; visibility: visible; display: block;
            }
            body { 
                background: #fff !important; padding: 0 !important;
                font-family: "Times New Roman", Times, serif;
                font-size: ' . (int)($layout['font_size'] ?? 12) . 'pt !important; 
            }
            @page { 
                margin: ' . (int)($layout['margin_t'] ?? 20) . 'mm ' . (int)($layout['margin_r'] ?? 20) . 'mm ' . (int)($layout['margin_b'] ?? 20) . 'mm ' . (int)($layout['margin_l'] ?? 20) . 'mm; 
            }
            .print-wrapper { counter-reset: page; }
            .page-number:after { content: "Puslapis " counter(page); counter-increment: page; }
        }
    </style>';
    
    return $html;
}

function get_konkursai_events() {
    if (!function_exists('db_connect')) { return []; }
    $conn = db_connect();
    $result = $conn->query("
        SELECT konk_id, konkurso_pav, COALESCE(data, NULL) AS data, status, grupe
        FROM konkursai ORDER BY data ASC, konk_id ASC
    ");
    if (!$result) { return []; }
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $is_active = ($row['status'] ?? 1) == 0;
        $has_date = !empty($row['data']) && $row['data'] !== '0000-00-00';
        $site_url = defined('SITE_URL') ? SITE_URL : '';
        
        $event = [
            'id' => $row['konk_id'],
            'title' => ($row['konkurso_pav'] ?? 'Be pavadinimo') . ' (' . ($row['grupe'] ?? 'Nėra grupės') . ')',
            'backgroundColor' => $is_active ? '#28a745' : '#6c757d',
            'borderColor' => $is_active ? '#20c997' : '#495057',
            'textColor' => 'white',
            'url' => $site_url . '/modules/olympiads/view.php?id=' . $row['konk_id']
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
    if (function_exists('db_insert')) {
        db_insert('system_logs', $data);
    }
}

function build_url_with_params($new_params) {
    $query_params = $_GET;
    foreach ($new_params as $key => $value) {
        $query_params[$key] = $value;
    }
    return '?' . http_build_query($query_params);
}

function generate_sortable_header($column_db_name, $label, $current_sort, $current_dir) {
    $next_dir = ($current_sort === $column_db_name && $current_dir === 'ASC') ? 'DESC' : 'ASC';
    $url = build_url_with_params(['sort' => $column_db_name, 'dir' => $next_dir, 'page' => 1]);
    
    $icon = '';
    if ($current_sort === $column_db_name) {
        $icon = $current_dir === 'ASC' ? '&nbsp;<i class="fas fa-sort-up"></i>' : '&nbsp;<i class="fas fa-sort-down"></i>';
    } else {
        $icon = '&nbsp;<i class="fas fa-sort text-muted" style="opacity: 0.3;"></i>';
    }

    return "<a href=\"{$url}\" class=\"text-dark text-decoration-none\">{$label}{$icon}</a>";
}

function render_pagination($total_items, $limit, $current_page) {
    $total_pages = ceil($total_items / $limit);
    if ($total_pages <= 1 && $total_items <= 10) return ''; 

    $limits = [10, 25, 50, 100];
    
    echo '<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-3">';
    
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

    if ($total_pages > 1) {
        echo '<nav aria-label="Page navigation">';
        echo '<ul class="pagination pagination-sm mb-0">';
        
        $prev_disabled = ($current_page <= 1) ? 'disabled' : '';
        $prev_url = build_url_with_params(['page' => max(1, $current_page - 1)]);
        echo "<li class=\"page-item {$prev_disabled}\"><a class=\"page-link\" href=\"{$prev_url}\">&laquo;</a></li>";
        
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

        $next_disabled = ($current_page >= $total_pages) ? 'disabled' : '';
        $next_url = build_url_with_params(['page' => min($total_pages, $current_page + 1)]);
        echo "<li class=\"page-item {$next_disabled}\"><a class=\"page-link\" href=\"{$next_url}\">&raquo;</a></li>";
        
        echo '</ul></nav>';
    }
    echo '</div>';
}

function get_print_layout() {
    $file = dirname(dirname(dirname(__FILE__))) .'/config/print_layout.json';
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return [
        'header_html' => '<div style="text-align: center; margin-bottom: 20px;"><h3 style="margin-bottom: 5px;">{{INSTITUTION}}</h3><h4 style="color: #444;">{{TITLE}}</h4></div>',
        'footer_html' => '<div style="margin-top: 50px; display: flex; justify-content: space-between;"><p><strong>Komisijos pirmininkas:</strong> ___________________</p><p><strong>Data:</strong> ' . date('Y-m-d') . '</p></div>',
        'margin_t' => 20, 'margin_b' => 20, 'margin_l' => 20, 'margin_r' => 20, 'font_size' => 12
    ];
}

function get_system_theme() {
    $theme_file = dirname(dirname(__FILE__)) . '/config/theme.json';
    $default_theme = [
        'primary_color'       => '#4e73df', 'secondary_color'     => '#858796', 'success_color'       => '#1cc88a',
        'info_color'          => '#36b9cc', 'warning_color'       => '#f6c23e', 'danger_color'        => '#e74a3b',
        'body_bg'             => '#f8f9fc', 'text_color'          => '#5a5c69', 'topbar_bg'           => '#ffffff',
        'topbar_text'         => '#858796', 'topbar_hover'        => '#4e73df', 'sidebar_bg'          => '#4e73df',
        'sidebar_text'        => '#ffffff', 'sidebar_hover_bg'    => '#2e59d9', 'sidebar_active_bg'   => '#ffffff',
        'sidebar_active_text' => '#4e73df', 'card_bg'             => '#ffffff', 'card_header_bg'      => '#f8f9fc',
        'card_border'         => '#e3e6f0', 'table_header_bg'     => '#f8f9fc', 'table_header_text'   => '#5a5c69',
        'footer_bg'           => '#ffffff', 'footer_text'         => '#858796', 'logo_path'           => 'assets/img/logo.png',
        'logo_width'          => '150px'
    ];

    if (file_exists($theme_file)) {
        $custom_theme = json_decode(file_get_contents($theme_file), true);
        if (is_array($custom_theme)) {
            return array_merge($default_theme, $custom_theme);
        }
    }
    return $default_theme;
}

if (!function_exists('esc')) {
    function esc($string) {
        if (empty($string)) return '';
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}
?>