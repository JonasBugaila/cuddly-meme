<div id="cookieConsentBanner" class="fixed-bottom p-3 bg-dark text-white shadow-lg d-none transition-all" style="z-index: 9999; transform: translateY(100%);">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
            <div>
                <strong><i class="fas fa-cookie-bite text-warning me-2 fa-lg"></i> Slapukų ir duomenų naudojimas:</strong>
                Šioje sistemoje naudojami būtiniausi techniniai slapukai bei renkami standartiniai saugumo duomenys (pvz., IP adresas), reikalingi saugiam svetainės veikimui užtikrinti.
            </div>
            <div class="d-flex gap-2 flex-shrink-0">
                <button class="btn btn-outline-light btn-sm px-3" type="button" data-bs-toggle="collapse" data-bs-target="#cookieDetailsCollapse" aria-expanded="false" aria-controls="cookieDetailsCollapse">
                    <i class="fas fa-info-circle me-1"></i> Detalesnė informacija
                </button>
                <button id="btnAcceptCookies" class="btn btn-warning fw-bold px-4 shadow-sm">Supratau ir sutinku</button>
            </div>
        </div>

        <div class="collapse" id="cookieDetailsCollapse">
            <div class="card card-body text-white border-0 mt-3 p-3 table-responsive custom-cookie-card">
                <h6 class="fw-bold border-bottom border-secondary pb-2 mb-3"><i class="fas fa-shield-alt text-warning me-2"></i> Sistemoje naudojami duomenų kaupimo įrankiai</h6>
                <p class="small text-muted mb-3">Siekdami užtikrinti visapusišką duomenų apsaugą, informuojame, kad jokių trečiųjų šalių ar sekimo/reklaminių slapukų (pvz., Google Analytics) nenaudojame. Kaupiami tik šie techniniai ir saugumo duomenys:</p>
                
                <table class="table table-sm table-dark table-striped align-middle mb-0 small" style="min-width: 600px;">
                    <thead>
                        <tr class="text-muted border-bottom border-secondary">
                            <th style="width: 20%;">Pavadinimas / Raktas</th>
                            <th style="width: 15%;">Tipas</th>
                            <th style="width: 45%;">Tikslas</th>
                            <th style="width: 20%;">Saugojimo trukmė</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-warning fw-bold"><?php echo defined('SESSION_NAME') ? SESSION_NAME : 'PHPSESSID'; ?></td>
                            <td>Būtinasis (Slapukas)</td>
                            <td>Naudojamas atpažinti prisijungusį vartotoją (mokytoją arba administratorių), saugoti klaidų / sėkmės pranešimus ir užtikrinti apsaugą nuo CSRF atakų.</td>
                            <td>Iki naršyklės uždarymo arba po 15 min. neaktyvumo</td>
                        </tr>
                        <tr>
                            <td class="text-warning fw-bold">cookiesAccepted</td>
                            <td>Funkcinis (Local Storage)</td>
                            <td>Prisimena jūsų sutikimą su duomenų politika, kad šis informacinis pranešimas nebūtų rodomas iš naujo kiekviename puslapyje.</td>
                            <td>Nuolatinis (kol neišvalysite naršyklės nustatymų)</td>
                        </tr>
                        <tr>
                            <td class="text-info fw-bold">Sistemos žurnalas (IP)</td>
                            <td>Saugumo įrašas (Server-side)</td>
                            <td>Apsaugos sumetimais sistema registruoja prisijungimus bei atliktus pakeitimus (veiksmas, vartotojo ID, IP adresas). Tai būtina norint užkirsti kelią neteisėtai prieigai ir užtikrinti atskaitomybę.</td>
                            <td>Saugoma sistemos duomenų bazėje saugumo audito tikslais</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var cookieBanner = document.getElementById("cookieConsentBanner");
    var btnAccept = document.getElementById("btnAcceptCookies");

    if (cookieBanner && btnAccept) {
        // Patikriname, ar naršyklėje jau yra išsaugotas vartotojo sutikimas
        if (!localStorage.getItem("cookiesAccepted")) {
            setTimeout(function() {
                cookieBanner.classList.remove("d-none");
                setTimeout(function() {
                    cookieBanner.style.transform = "translateY(0)";
                }, 50);
            }, 500);
        }

        // Vartotojui paspaudus mygtuką "Sutinku"
        btnAccept.addEventListener("click", function() {
            localStorage.setItem("cookiesAccepted", "true");
            cookieBanner.style.transform = "translateY(100%)";
            setTimeout(function() {
                cookieBanner.classList.add("d-none");
            }, 300);
        });
    }
});
</script>

<style>
#cookieConsentBanner {
    transition: transform 0.4s ease-in-out;
}
.custom-cookie-card {
    background-color: #212529 !important; /* Tamsi elegantiška kortelės spalva */
    border: 1px solid #343a40 !important;
}
.custom-cookie-card table {
    margin-top: 5px;
}
</style>