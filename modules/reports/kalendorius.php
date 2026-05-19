<?php
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

start_session();
if (!is_logged_in()) {
    redirect(SITE_URL . '/modules/auth/login.php');
}

$events = [];
$sql = "SELECT konk_id, konkurso_pav, data, status FROM konkursai WHERE data IS NOT NULL AND data != '0000-00-00' ORDER BY data ASC";
$stmt = db_query($sql);
$rows = $stmt ? db_get_results($stmt) : [];

foreach ($rows as $row) {
    $raw_date = trim($row['data']);
    if (empty($raw_date) || $raw_date === '0000-00-00') continue;

    $date = date('Y-m-d', strtotime($raw_date));
    if ($date === '1970-01-01') continue;

    $events[] = [
        'title' => htmlspecialchars($row['konkurso_pav'] ?? 'Be pavadinimo', ENT_QUOTES, 'UTF-8'),
        'start' => $date,
        'url'   => SITE_URL . '/modules/olympiads/view.php?id=' . $row['konk_id'],
        'color' => ($row['status'] ?? 1) == 0 ? '#0d6efd' : '#6c757d'
    ];
}

$events_json = json_encode($events, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (json_last_error() !== JSON_ERROR_NONE) {
    $events_json = '[]';
}
?>

<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <title>Kalendorius</title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/index.global.min.css">
    <style>
        body, html { margin:0; padding:0; height:100%; font-family: sans-serif; background:#f8f9fa; }
        #calendar { 
            width: 100%; 
            height: 100%; 
            min-height: 380px; 
            background: white; 
            padding: 6px; 
            box-sizing: border-box;
        }
        .fc { font-size: 0.78rem; }
        .fc-toolbar-title { font-size: 1rem !important; }
        .fc-button { 
            font-size: 0.68rem !important; 
            padding: 1px 5px !important; 
            margin: 0 1px !important;
        }
        .fc-event-title { 
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; 
            font-size: 0.74rem !important; padding: 1px 3px !important; 
        }
        .fc-event { cursor: pointer; }
        .fc-event:hover { opacity: 0.9; }
        .fc-list-day-text, .fc-list-day-side-text { font-size: 0.75rem !important; }
    </style>
</head>
<body>
    <div id="calendar"></div>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            if (!calendarEl) return;

            const events = <?= $events_json ?>;

            if (events.length === 0) {
                calendarEl.innerHTML = '<p style="text-align:center; color:#666; padding:20px;">Nėra artėjančių olimpiadų.</p>';
                return;
            }

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'lt',
                timeZone: 'Europe/Vilnius',
                height: '100%',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listMonth' // VISI RODINIAI ATGAL
                },
                buttonText: {
                    today: 'Šiandien',
                    month: 'Mėnuo',
                    week: 'Savaitė',
                    list: 'Sąrašas'
                },
                views: {
                    timeGridWeek: {
                        type: 'timeGrid',
                        duration: { weeks: 1 },
                        buttonText: 'Savaitė'
                    },
                    listMonth: {
                        buttonText: 'Sąrašas'
                    }
                },
                events: events,
                eventDidMount: function(info) {
                    const full = info.event.title;
                    const short = full.length > 22 ? full.substring(0,20)+'...' : full;
                    const el = info.el.querySelector('.fc-event-title');
                    if (el) el.textContent = short;
                    info.el.title = full;
                },
                eventClick: function(info) {
                    if (info.event.url) {
                        info.jsEvent.preventDefault();
                        window.parent.location.href = info.event.url;
                    }
                }
            });

            calendar.render();
        });
    </script>
</body>
</html>