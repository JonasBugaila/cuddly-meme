<?php
/**
 * Poraštės failas
 * 
 * Šis failas įtraukiamas į visus puslapius ir atvaizduoja puslapio poraštę
 */
?>
        </div>
    </main>
    
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Olimpiadų sistema. Visos teisės saugomos.</p>
        </div>
    </footer>
    
   
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
 <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>

</body>
</html>
<?php if (function_exists('is_logged_in') && is_logged_in()): ?>
<div class="modal fade" id="sessionTimeoutModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="sessionTimeoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="sessionTimeoutModalLabel"><i class="fas fa-exclamation-triangle"></i> Sesijos pabaigos perspėjimas</h5>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="fs-5 mb-2">Jūs buvote neaktyvus.</p>
                <p class="text-muted">Saugumo sumetimais jūsų sesija bus nutraukta po:</p>
                <div id="sessionCountdown" class="fw-bold text-danger fs-2 my-3">01:00</div>
                <p class="mb-0">Ar norite pratęsti darbą sistemoje?</p>
            </div>
            <div class="modal-footer d-flex justify-content-center border-0 pt-0">
                <button type="button" id="btnEndSession" class="btn btn-secondary px-4 me-2">Baigti sesiją</button>
                <button type="button" id="btnExtendSession" class="btn btn-success px-4">Pratęsti sesiją</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Konfigūracija (milisekundėmis) - Šiuo metu nustatyta testavimui (1 min + 1 min)
    const warnAfter = 14 * 60 * 1000;  // Rodyti langą po 14 minutės neaktyvumo
    const logoutAfter = 15 * 60 * 1000; // Visiškai atjungti po 15 minučių bendro laiko
    const siteUrl = "<?php echo defined('SITE_URL') ? SITE_URL : ''; ?>";

    let warningTimer;
    let logoutTimer;
    let countdownInterval;
    let countdownSeconds = 60; 
    let isWarningActive = false; // NAUJA: būsena, kuri blokuoja pelės judesius iššokus langui

    const modalElement = document.getElementById('sessionTimeoutModal');
    let sessionModal = (typeof bootstrap !== 'undefined' && bootstrap.Modal) 
        ? new bootstrap.Modal(modalElement) 
        : null;

    function resetTimers() {
        // Jei langas aktyvus, ignoruojame fono judesius (neleidžiame pelės judesiams uždaryti lango)
        if (isWarningActive) return;

        clearTimeout(warningTimer);
        clearTimeout(logoutTimer);
        clearInterval(countdownInterval);
        
        if (sessionModal) {
            sessionModal.hide();
        } else if (typeof $ !== 'undefined' && $('#sessionTimeoutModal').data('bs.modal')) {
            $('#sessionTimeoutModal').modal('hide');
        }

        warningTimer = setTimeout(showWarning, warnAfter);
        logoutTimer = setTimeout(logoutUser, logoutAfter);
    }

    function showWarning() {
        isWarningActive = true; // Užrakiname sistemą – dabar pelės judesiai bus ignoruojami

        if (sessionModal) {
            sessionModal.show();
        } else if (typeof $ !== 'undefined') {
            $('#sessionTimeoutModal').modal('show');
        }

        countdownSeconds = 60;
        document.getElementById('sessionCountdown').textContent = "01:00";
        
        countdownInterval = setInterval(function() {
            countdownSeconds--;
            if (countdownSeconds <= 0) {
                clearInterval(countdownInterval);
                logoutUser();
            } else {
                let secs = countdownSeconds < 10 ? "0" + countdownSeconds : countdownSeconds;
                document.getElementById('sessionCountdown').textContent = "00:" + secs;
            }
        }, 1000);
    }

    function logoutUser() {
        window.location.href = siteUrl + "/modules/auth/logout.php?reason=timeout";
    }

    function extendSession() {
        fetch(siteUrl + '/includes/ping.php')
            .then(response => response.json())
            .then(data => {
                isWarningActive = false; // Atrakiname sistemą, nes vartotojas sąmoningai pratęsė sesiją
                resetTimers();
            })
            .catch(err => {
                console.error('Nepavyko atnaujinti sesijos:', err);
                isWarningActive = false; 
                resetTimers();
            });
    }

    // Mygtukų klausytojai
    document.getElementById('btnExtendSession').addEventListener('click', extendSession);
    document.getElementById('btnEndSession').addEventListener('click', logoutUser);

    // Vartotojo aktyvumo sekimas
    const activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'];
    activityEvents.forEach(function(eventName) {
        window.addEventListener(eventName, throttle(resetTimers, 2000)); 
    });

    function throttle(func, delay) {
        let timeout = null;
        return function() {
            if (!timeout) {
                func();
                timeout = setTimeout(function() { timeout = null; }, delay);
            }
        };
    }

    // Paleidžiame pirmą kartą
    resetTimers();
});
</script>
<?php endif; ?>