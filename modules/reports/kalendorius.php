<?php
/**
 * Globalus Kalendoriaus Komponentas
 * Šis failas yra įkraunamas per footer.php ir veikia visuose puslapiuose kaip modalinis langas.
 */

// Jei failas bandomas atidaryti tiesiogiai per URL (o ne įtrauktas per footer.php),
// nukreipiame į pagrindinį puslapį, nes kalendorius dabar yra iššokantis langas.
if (basename($_SERVER['PHP_SELF']) === 'kalendorius.php') {
    require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
    header("Location: " . SITE_URL);
    exit;
}

// 1. DUOMENŲ PARUOŠIMAS
$global_events = [];
if (function_exists('db_query')) {
    $konkursai = db_get_results(db_query("SELECT konk_id, konkurso_pav, data, status, grupe FROM konkursai WHERE data IS NOT NULL AND data != '0000-00-00'"));
    foreach ($konkursai as $oly) {
        $is_active = ($oly['status'] == 0);
        $global_events[] = [
            'id' => $oly['konk_id'],
            'title' => $oly['konkurso_pav'] . (!empty($oly['grupe']) ? " ({$oly['grupe']})" : ''),
            'start' => $oly['data'],
            'backgroundColor' => $is_active ? '#198754' : '#6c757d',
            'borderColor' => $is_active ? '#157347' : '#5c636a',
            'textColor' => '#ffffff',
            'extendedProps' => [
                'pavadinimas' => $oly['konkurso_pav']
            ]
        ];
    }
}
$is_logged_global = function_exists('is_logged_in') && is_logged_in();
$is_admin_global = function_exists('is_admin') && is_admin();
?>

<div class="modal fade" id="globalCalendarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold"><i class="far fa-calendar-alt me-2"></i> Renginių Kalendorius</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="d-flex gap-3 mb-3 justify-content-end">
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1"><i class="fas fa-circle small me-1"></i> Būsimos / Vyksta</span>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1"><i class="fas fa-archive small me-1"></i> Įvykusios</span>
                </div>
                <div id="globalCalendarView" class="bg-white p-3 rounded shadow-sm border"></div>
            </div>
        </div>
    </div>
</div>

<?php if (!$is_logged_global): ?>
<div class="modal fade" id="globalGuestActionModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold">Informacija</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-4">
        <i class="fas fa-lock fa-3x text-muted mb-3 opacity-50"></i>
        <h5 id="globalGuestModalOlympiadName" class="mb-3 text-dark fw-bold"></h5>
        <p class="text-muted mb-0">Norėdami matyti detalesnę informaciją, registruoti mokinius arba peržiūrėti rezultatus, prašome prisijungti prie sistemos.</p>
      </div>
      <div class="modal-footer justify-content-center bg-light border-0 py-3">
        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Uždaryti</button>
        <a href="<?php echo defined('SITE_URL') ? SITE_URL : ''; ?>/modules/auth/login.php" class="btn btn-primary px-4 fw-bold">Prisijungti</a>
      </div>
    </div>
  </div>
</div>
<?php else: ?>
<div class="modal fade" id="globalLoggedInEventModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title fw-bold">Olimpiados valdymas</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-4">
        <i class="fas fa-trophy fa-3x text-success mb-3 opacity-75"></i>
        <h5 id="globalLoggedInModalTitle" class="mb-3 text-dark fw-bold"></h5>
        <p class="text-muted mb-4">Ką norėtumėte daryti su šia olimpiada?</p>
        <div class="d-grid gap-2">
            <a href="#" id="globalBtnRegister" class="btn btn-primary btn-lg fw-bold"><i class="fas fa-user-plus me-2"></i> Registruoti mokinius</a>
            <a href="#" id="globalBtnResults" class="btn btn-outline-secondary btn-lg fw-bold"><i class="fas fa-poll-h me-2"></i> Žiūrėti rezultatus</a>
            <?php if($is_admin_global): ?>
            <a href="#" id="globalBtnManage" class="btn btn-outline-danger btn-lg fw-bold"><i class="fas fa-cog me-2"></i> Administruoti olimpiadą</a>
            <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var globalCalendarEl = document.getElementById('globalCalendarView');
    var globalCalendar = null;
    var globalModalEl = document.getElementById('globalCalendarModal');

    // Išmanus visų "Kalendorius" mygtukų gaudymas bet kuriame puslapyje
    document.querySelectorAll('a, button').forEach(function(el) {
        var text = el.textContent.toLowerCase();
        var href = el.href ? el.href.toLowerCase() : '';
        if (text.includes('kalendorius') || href.includes('kalendorius')) {
            el.addEventListener('click', function(e) {
                // Jei elementas dar neturi bootstrap toggle atributo
                if (!el.hasAttribute('data-bs-toggle')) {
                    e.preventDefault();
                    var bsModal = new bootstrap.Modal(globalModalEl);
                    bsModal.show();
                }
            });
        }
    });

    if(globalModalEl) {
        globalModalEl.addEventListener('shown.bs.modal', function () {
            if (!globalCalendar) {
                var isMobile = window.innerWidth < 768;
                globalCalendar = new FullCalendar.Calendar(globalCalendarEl, {
                    initialView: isMobile ? 'listMonth' : 'dayGridMonth',
                    locale: 'lt',
                    firstDay: 1,
                    headerToolbar: {
                        left: isMobile ? 'prev,next' : 'prev,next today',
                        center: 'title',
                        right: isMobile ? '' : 'dayGridMonth,listMonth'
                    },
                    buttonText: { today: 'Šiandien', month: 'Kalendorius', list: 'Sąrašas' },
                    height: isMobile ? 'auto' : 650,
                    events: <?php echo json_encode($global_events, JSON_UNESCAPED_UNICODE); ?>,
                    eventClick: function(info) {
                        info.jsEvent.preventDefault();
                        
                        var calModalInstance = bootstrap.Modal.getInstance(globalModalEl);
                        if(calModalInstance) calModalInstance.hide();
                        
                        <?php if ($is_logged_global): ?>
                            document.getElementById('globalLoggedInModalTitle').textContent = info.event.title;
                            var baseUrl = "<?php echo defined('SITE_URL') ? SITE_URL : ''; ?>";
                            
                            document.getElementById('globalBtnRegister').href = baseUrl + '/modules/registration/index.php?konk_id=' + info.event.id;
                            document.getElementById('globalBtnResults').href = baseUrl + '/modules/results/index.php?f_olympiad=' + encodeURIComponent(info.event.extendedProps.pavadinimas);
                            
                            <?php if($is_admin_global): ?>
                            document.getElementById('globalBtnManage').href = baseUrl + '/modules/olympiads/view.php?id=' + info.event.id;
                            <?php endif; ?>
                            
                            var loggedInModal = new bootstrap.Modal(document.getElementById('globalLoggedInEventModal'));
                            loggedInModal.show();
                        <?php else: ?>
                            document.getElementById('globalGuestModalOlympiadName').textContent = info.event.title;
                            var guestModal = new bootstrap.Modal(document.getElementById('globalGuestActionModal'));
                            guestModal.show();
                        <?php endif; ?>
                    },
                    eventMouseEnter: function(info) { info.el.style.cursor = 'pointer'; }
                });
                globalCalendar.render();
            } else {
                globalCalendar.render();
            }
        });
    }
});
</script>
<style>
/* FullCalendar stilių pagrąžinimas globaliam langui */
.fc-theme-standard .fc-scrollgrid { border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; }
.fc .fc-button-primary { background-color: #0d6efd; border-color: #0d6efd; text-transform: capitalize; }
.fc .fc-button-primary:hover { background-color: #0b5ed7; border-color: #0a58ca; }
.fc-event { padding: 2px 4px; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px; border: none!important;}
</style>