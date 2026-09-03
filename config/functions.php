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

        // SAUGU: sesijos gyvavimo trukmės apribojimas. Bendro naudojimo
        // kompiuteriuose (pvz. mokyklos raštinėje) sesija neturėtų likti
        // aktyvi neribotai ilgai po to, kai vartotojas nustoja ją naudoti.
        $session_lifetime = 15 * 60; // 15 minučių neaktyvumo (suderinta su footer.php JS logout laikmačiu)
        ini_set('session.gc_maxlifetime', $session_lifetime);
        session_set_cookie_params($session_lifetime);

        session_start();

        // Tikriname, ar sesija neviršijo leistino neaktyvumo laiko
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $session_lifetime) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            session_start();
        }
        $_SESSION['last_activity'] = time();
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
/**
 * NAUJA: iškelta iš generate_printable_table() į atskirą funkciją, kad tą patį
 * maketą (paraštes, šrifto dydį ir kt.) galėtų nuskaityti ir kviečiantis failas
 * PRIEŠ duomenų skaidymą į puslapius (žr. calculate_rows_per_page() žemiau) -
 * anksčiau ši informacija buvo žinoma tik generate_printable_table() viduje,
 * todėl kviečiantys failai naudodavo fiksuotą, niekaip nesusietą su realiu
 * šablonu eilučių skaičių (pvz. visada 15 ar 20).
 */
function get_print_layout($layout_key = 'protocol') {
    $layout_file = dirname(dirname(__FILE__)) . '/config/print_layout.json';
    $all_layouts = [];

    if (file_exists($layout_file)) {
        $all_layouts = json_decode(file_get_contents($layout_file), true) ?: [];
    }

    if (isset($all_layouts['header_html']) && !isset($all_layouts['protocol'])) {
        $old_layout = $all_layouts;
        $all_layouts = ['protocol' => $old_layout, 'evaluation' => $old_layout, 'codes' => $old_layout, 'signature' => $old_layout];
    }

    return $all_layouts[$layout_key] ?? ($all_layouts['protocol'] ?? [
        'header_html' => '<div style="text-align: center; margin-bottom: 20px;"><h3>{{INSTITUTION}}</h3><h4 style="color: #444;">{{TITLE}}</h4></div>',
        'footer_html' => '',
        'margin_t' => 20, 'margin_b' => 20, 'margin_l' => 20, 'margin_r' => 20, 'font_size' => 12
    ]);
}

/**
 * NAUJA: apskaičiuoja, kiek lentelės eilučių įvertinama telpa į vieną A4 puslapį,
 * atsižvelgiant į šablone sukonfigūruotas paraštes ir šrifto dydį.
 *
 * SVARBU: tai YRA ĮVERTIS, ne tikslus skaičiavimas - naršyklė PHP negrąžina jokios
 * informacijos apie realų atvaizdavimą (kiek vietos realiai užima antraštė/poraštė,
 * kaip naršyklė interpretuoja eilučių aukštį ir pan.). Skaičiavimas apima saugų
 * rezervą, kad greičiau liktų nepanaudotos vietos puslapio apačioje, nei turinys
 * persilietų į kitą fizinį lapą be savo puslapio numerio.
 *
 * Jei administratorius per print_template.php nustato rankinį 'rows_per_page'
 * (didesnį už 0), jis visada turi pirmenybę prieš automatinį skaičiavimą - tai
 * leidžia pačiam pakoreguoti, jei automatinis įvertis konkrečiu atveju netikslus.
 */
/**
 * NAUJA: apskaičiuoja, kiek lentelės eilučių įvertinama telpa į vieną A4 puslapį,
 * atsižvelgiant į šablone sukonfigūruotas paraštes ir šrifto dydį.
 *
 * PATAISYTA: dabar priima $reserve_footer parametrą - poraštė (footer_html, parašai,
 * data) rodoma TIK PASKUTINIAME dokumento puslapyje, o ne kiekviename atskirai, todėl
 * tarpiniai puslapiai (be poraštės) gali talpinti daugiau eilučių nei paskutinis
 * (kuriame reikia palikti vietos porašei). Žr. paginate_with_footer_reserve() žemiau,
 * kuri naudoja abu variantus kartu, kad sudarytų protingą puslapių skaidymą.
 *
 * SVARBU: tai YRA ĮVERTIS, ne tikslus skaičiavimas - naršyklė PHP negrąžina jokios
 * informacijos apie realų atvaizdavimą. Skaičiavimas apima saugų rezervą, kad greičiau
 * liktų nepanaudotos vietos puslapio apačioje, nei turinys persilietų į kitą fizinį
 * lapą be savo puslapio numerio.
 *
 * Jei administratorius per print_template.php nustato rankinį 'rows_per_page'
 * (didesnį už 0), jis visada turi pirmenybę prieš automatinį skaičiavimą - tokiu
 * atveju $reserve_footer neturi įtakos (naudojama ta pati reikšmė visiems puslapiams).
 */
function calculate_rows_per_page($layout, $reserve_footer = false) {
    if (isset($layout['rows_per_page']) && (int)$layout['rows_per_page'] > 0) {
        return (int)$layout['rows_per_page'];
    }

    $margin_t = (int)($layout['margin_t'] ?? 20);
    $margin_b = (int)($layout['margin_b'] ?? 20);
    $font_size = (int)($layout['font_size'] ?? 12);

    // NAUJA (STRUKTŪRINIS SPRENDIMAS): juosta puslapio numeriui rezervuojama
    // VISADA, nepriklausomai nuo šablono "show_page_num" nustatymo - taip pagrindinio
    // turinio kiekis (eilučių skaičius puslapyje) NESIKEIČIA vien dėl to, kad kas nors
    // įjungė/išjungė numeraciją, o pati juosta faktiškai rezervuojama padidinant realią
    // @page apatinę paraštę (žr. print_document_head()), ne šio skaičiavimo viduje -
    // ŠIS skaičiavimas tiesiog turi žinoti apie tą patį fiksuotą dydį, kad eilučių
    // kiekio įvertis atitiktų realiai turimą (sumažintą) turinio plotą.
    // PATAISYTA: 15mm -> 25mm - puslapio numeris dabar rodomas NORMALIAME turinio
    // sraute (position:fixed neveikia patikimai kelioms skirtingoms reikšmėms
    // spausdinant - naršyklė jas sujungia į vieną pasikartojantį elementą), todėl
    // jam REIKIA realios vietos sraute (skirtingai nuo position:fixed varianto, kuris
    // nereikalaudavo jokios papildomos vietos, bet rodydavo neteisingą tekstą). Kad
    // saugiai tilptų numerio tekstas + linija virš jo + tarpai, rezervas padidintas.
    $page_number_reserve_mm = 25;

    // Turimas turinio aukštis (A4 = 297mm) atėmus paraštes IR fiksuotą puslapio
    // numerio juostą (žr. aukščiau - visada rezervuojama, net jei numeracija išjungta)
    $available_mm = 297 - $margin_t - $margin_b - $page_number_reserve_mm;

    // Rezervas antraštei (header_html, rodoma kiekviename puslapyje) - šis turinys
    // kintamas (administratorius jį laisvai redaguoja per HugeRTE), todėl tikslus
    // aukštis nežinomas iš anksto - naudojamas pagrįstas įvertis.
    $header_buffer_mm = 25;

    // Papildomas rezervas porašei (footer_html - parašai, data) - pridedamas TIK
    // paskutiniam puslapiui, nes tik jame poraštė realiai rodoma.
    $footer_buffer_mm = $reserve_footer ? 35 : 0;

    // Vienos eilutės aukščio įvertis: šrifto dydis (pt -> mm) * eilutės aukščio
    // koeficientas + langelio vidinis tarpas (6px viršuje ir apačioje, žr. CSS
    // "table.print-table td { padding: 6px 8px; }") + rėmelio storis.
    $pt_to_mm = 0.3528;
    $line_height_factor = 1.3;
    $cell_padding_mm = 12 * 0.2646; // 6px + 6px, 1px ≈ 0.2646mm (96dpi)
    $row_height_mm = ($font_size * $pt_to_mm * $line_height_factor) + $cell_padding_mm + 0.6;

    // Lentelės antraštės eilutė (thead) - panašaus aukščio kaip paprasta eilutė
    $table_header_mm = $row_height_mm;

    $usable_mm = $available_mm - $header_buffer_mm - $footer_buffer_mm - $table_header_mm;

    if ($row_height_mm <= 0 || $usable_mm <= 0) {
        return $reserve_footer ? 8 : 15; // saugus atsarginis variantas, jei nustatymai nerealūs
    }

    $rows = (int) floor($usable_mm / $row_height_mm);

    // Saugus rezervas - kelios eilutės mažiau, kad įvertinimo netikslumas
    // (naršyklių atvaizdavimo skirtumai) nesukeltų persiliejimo į kitą lapą.
    // PATAISYTA: 1 -> 2 eilutės, papildoma atsarga (žr. page_number_reserve_mm
    // padidinimą aukščiau - abu pakeitimai kartu turėtų realiai išspręsti
    // persiliejimo į tuščią naują lapą problemą).
    $rows -= 2;

    return max(3, min(60, $rows));
}

/**
 * NAUJA: skaido įrašų sąrašą į puslapius taip, kad poraštei (footer_html - parašai,
 * data) liktų vietos TIK paskutiniame puslapyje - tarpiniai puslapiai pilnai
 * užpildomi duomenimis (be poraštės rezervo), o paskutinis puslapis talpina mažiau
 * eilučių, kad porašė tikrai tilptų po jomis.
 *
 * $rows_per_page - kiek eilučių telpa PAPRASTAME (ne paskutiniame) puslapyje
 * $rows_last_page - kiek eilučių telpa PASKUTINIAME puslapyje (su porašte)
 *
 * PATAISYTA: pridėtas "žvilgsnis į priekį" (look-ahead) - anksčiau, kai likusių
 * įrašų kiekis viršydavo $rows_last_page, bet buvo mažesnis už $rows_per_page,
 * funkcija paimdavo VISUS likusius įrašus į einamą puslapį (nes jie visi tilpo į
 * $rows_per_page), palikdama paskutiniam puslapiui TUŠČIĄ masyvą - dėl to
 * atsirasdavo "fantomas" tuščias paskutinis puslapis su vien tik antrašte ir
 * porašte, be jokių duomenų eilučių. Dabar, prieš imant pilną $rows_per_page
 * kiekį, patikrinama, ar po to liks pakankamai įrašų kitiems puslapiams - jei ne,
 * likę įrašai padalinami maždaug per pusę, kad nė vienas puslapis neliktų
 * tuščias ar nepagrįstai mažas.
 */
function paginate_with_footer_reserve($items, $rows_per_page, $rows_last_page) {
    $total = count($items);
    if ($total === 0) {
        return [[]];
    }

    $chunks = [];
    $remaining = $items;

    while (true) {
        $remaining_count = count($remaining);

        // Jei likę įrašai telpa į paskutinį (su porašte) puslapį - čia ir baigiame.
        if ($remaining_count <= $rows_last_page) {
            $chunks[] = $remaining;
            break;
        }

        // Patikriname: jei paimtume PILNĄ $rows_per_page kiekį, ar liks pakankamai
        // įrašų, kad kitas puslapis (paskutinis arba dar vienas tarpinis) NEBŪTŲ
        // tuščias ar nepagrįstai mažas?
        if ($remaining_count - $rows_per_page > $rows_last_page) {
            // Saugu - po šio puslapio liks daugiau nei telpa į paskutinį, vadinasi
            // bus dar bent vienas normalaus dydžio puslapis.
            $chunks[] = array_slice($remaining, 0, $rows_per_page);
            $remaining = array_slice($remaining, $rows_per_page);
        } else {
            // Tai priešpaskutinis ir paskutinis puslapiai kartu - padaliname likusius
            // įrašus maždaug per pusę, kad abu puslapiai gautų protingą, nesutuštėjusį
            // kiekį (užuot vienam atitekus viskam, o kitam - nieko).
            $first_part_size = (int) ceil($remaining_count / 2);
            $first_part_size = min($first_part_size, $rows_per_page);
            $first_part_size = max($first_part_size, $remaining_count - $rows_last_page);

            $chunks[] = array_slice($remaining, 0, $first_part_size);
            $remaining = array_slice($remaining, $first_part_size);
        }
    }

    return $chunks;
}

/**
 * NAUJA (STRUKTŪRINIS PATAISYMAS): spausdinimo dokumento PRADŽIA - grąžina tinkamą
 * HTML5 dokumento pradžią (<!DOCTYPE>, <html>, <head> su VIENU konsoliduotu <style>
 * bloku, </head>, <body>) PLIUS spausdinimo skriptą ir kraunimo animaciją.
 *
 * SVARBU: anksčiau visas šis turinys (skriptas, kraunimo animacija, <style> blokas)
 * būdavo įterpiamas PER KIEKVIENĄ generate_printable_table() iškvietimą atskirai (per
 * "static $base_injected" apsaugą, kuri turėjo veikti tik VIENĄ kartą), o galutinis
 * spausdinimo turinys apskritai NETURĖJO <!DOCTYPE html><html><head></head><body>
 * apvalkalo - buvo tiesiog eilė sujungtų HTML fragmentų. Toks "be galvos" (be <head>)
 * dokumentas naršyklei yra netaisyklingas HTML, todėl <style> blokų (kurie kartodavosi
 * kiekviename puslapyje) taikymo tvarka ir spausdinimo puslapių skaičiavimas tapdavo
 * nenuspėjamas - tai buvo tikroji priežastis, kodėl kai kurie puslapiai spausdinime
 * atrodydavo tušti arba su netinkamai išdėstytu turiniu.
 *
 * Naudojimas kviečiančiame faile (kelių puslapių atveju):
 *   echo print_document_head($layout_key);
 *   foreach ($chunks as ...) { echo generate_printable_page(...); }
 *   echo print_document_foot();
 */
function print_document_head($layout_key = 'protocol') {
    $layout = get_print_layout($layout_key);

    // NAUJA (STRUKTŪRINIS SPRENDIMAS): fiksuota, nuo šablono NEPRIKLAUSANTI juosta
    // fizinio lapo apačioje, kurioje rodomas puslapio numeris (arba lieka tuščia,
    // jei numeracija šablone išjungta). Ši juosta VISADA rezervuojama (didinant
    // realią @page apatinę paraštę) - taip pagrindinis turinys FIZIŠKAI negali į ją
    // patekti (naršyklė spausdina tik iki @page paraščių ribos), todėl puslapio
    // numeris visada telpa toje pačioje vietoje kiekviename lape, nepriklausomai nuo
    // turinio kiekio ar eilučių skaičiaus įverčio tikslumo.
    $page_number_reserve_mm = 25;
    $effective_margin_b = (int)($layout['margin_b'] ?? 20) + $page_number_reserve_mm;

    $html = '<!DOCTYPE html><html lang="lt"><head><meta charset="UTF-8"><title>Spausdinimas</title>';

    $html .= '<style>
        body { margin: 0; padding: 0; }
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
                position: relative; left: auto; top: auto; visibility: visible;
                /* PATAISYTA #7: flexbox GALUTINAI atmestas - dokumentuota naršyklių
                   spausdinimo variklių problema, kad "page-break-after: always"
                   nepatikimai veikia, kai puslapio turinio konteineris yra flex
                   konteineris (display:flex ant .print-wrapper). Realiuose testuose
                   tai pasireiškė kaip puslapio numerio rodymas TIK kas kelias
                   duomenų "porcijas", o ne kiekviename fiziniame puslapyje - pats
                   priverstinis lūžis tarp .olympiad-section/.evaluation-section
                   blokų tapdavo nepatikimas. Grąžinta prie paprasto blokinio
                   išdėstymo (be flex) - su šiuo variantu page-break-after veikė
                   patikimai (patvirtinta ankstesniuose testuose), tik numeris
                   nebūtinai lygiuojasi lygiai su fizine lapo apačia (žr. .page-number
                   žemiau - tai sąmoningas kompromisas, žr. calculate_rows_per_page()
                   dėl rezervuotos vietos skaičiavimo). */
                box-sizing: border-box;
            }
            .page-number {
                margin-top: 15px;
                padding-top: 8px;
                border-top: 1px solid #ccc;
                text-align: center;
                font-size: 10pt;
                color: #666;
            }
            body {
                background: #fff !important; padding: 0 !important;
                font-family: "Times New Roman", Times, serif;
                font-size: ' . (int)($layout['font_size'] ?? 12) . 'pt !important;
            }
            @page {
                margin: ' . (int)($layout['margin_t'] ?? 20) . 'mm ' . (int)($layout['margin_r'] ?? 20) . 'mm ' . $effective_margin_b . 'mm ' . (int)($layout['margin_l'] ?? 20) . 'mm;
            }
        }
    </style>';

    $html .= '</head><body>';

    $html .= '<script>
        document.addEventListener("DOMContentLoaded", function() {
            setTimeout(function() {
                window.print();
            }, 400);
            window.onafterprint = function() {
                setTimeout(function() {
                    if (window.history.length > 1) {
                        window.history.back();
                    } else {
                        window.close();
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

    return $html;
}

/**
 * NAUJA: spausdinimo dokumento PABAIGA - uždaro </body></html>.
 */
function print_document_foot() {
    return '</body></html>';
}

/**
 * NAUJA: grąžina VIENO puslapio turinį (.print-wrapper su antrašte, lentele,
 * porašte ir puslapio numeriu) - BE jokio <style>/<script>/kraunimo animacijos.
 * Naudoti kartu su print_document_head()/print_document_foot() kelių puslapių atveju.
 */
function generate_printable_page($title, $institution, $headers, $data, $options = [], $layout_key = 'protocol') {
    $layout = get_print_layout($layout_key);

    $search = ['{{TITLE}}', '{{INSTITUTION}}', '{{DATE}}'];
    $replace = [htmlspecialchars($title), htmlspecialchars($institution), date('Y-m-d')];

    $header_html = str_replace($search, $replace, $layout['header_html'] ?? '');
    $footer_html = str_replace($search, $replace, $layout['footer_html'] ?? '');

    $print_id = 'print_' . uniqid();

    $html = '<div id="' . $print_id . '_printable" class="print-wrapper">';
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

    // PATAISYTA: puslapio numeris dabar rodomas BESĄLYGIŠKAI kiekviename puslapyje,
    // kai tik žinomi page_num/total_pages (t.y. visada daugiapuslapiuose dokumentuose) -
    // šablono "show_page_num" nustatymas daugiau NEBETIKRINAMAS.
    $page_num_html = '';
    if (isset($options['page_num']) && isset($options['total_pages'])) {
        $page_num_html = '<div class="page-number">Puslapis ' . (int)$options['page_num'] . ' iš ' . (int)$options['total_pages'] . '</div>';
    }

    $show_footer = !isset($options['is_last_page']) || $options['is_last_page'] === true;
    $footer_html_output = $show_footer ? $footer_html : '';

    $html .= '<div class="print-footer mt-4 pt-2">' . $footer_html_output . '</div>';
    $html .= $page_num_html;
    $html .= '</div>';

    return $html;
}

/**
 * PATAISYTA (STRUKTŪRINIS): dabar tiesiog sudeda print_document_head() +
 * generate_printable_page() + print_document_foot() į VIENĄ savarankišką dokumentą -
 * tinka vienpuslapiam naudojimui vienu iškvietimu (participant_id.php,
 * signature_sheets.php, school_olympiad_report_V2.php). Kelių puslapių atveju
 * (protocols.php, evaluation_sheets.php, results/view.php) naudokite tris atskiras
 * funkcijas tiesiogiai - žr. print_document_head() komentarą aukščiau.
 */
function generate_printable_table($title, $institution, $headers, $data, $options = [], $layout_key = 'protocol') {
    $html = print_document_head($layout_key);
    $html .= generate_printable_page($title, $institution, $headers, $data, $options, $layout_key);
    $html .= print_document_foot();
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

/**
 * NAUJA: atskiras mokytojų (paprastų vartotojų) veiklos žurnalas.
 * Fiksuoja tik mokytojo (ne admin) atliekamus veiksmus: dalyvių registravimą
 * ir redagavimą. Saugoma atskiroje lentelėje 'teacher_activity_log',
 * nesumaišant su bendru admin/sistemos žurnalu 'system_logs'.
 */
function log_teacher_action($action, $details = '', $reg_id = null) {
    start_session();
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return;
    }

    $vart_id = $_SESSION['user_id'];

    // Mokyklos pavadinimą kešuojame sesijoje, kad nereikėtų kaskart klausti DB
    if (!isset($_SESSION['user_school']) && function_exists('db_query')) {
        $row = db_get_row(db_query("SELECT var_mokykla FROM vartotojas WHERE vart_id = ?", [$vart_id], 's'));
        $_SESSION['user_school'] = $row['var_mokykla'] ?? '';
    }
    $var_mokykla = $_SESSION['user_school'] ?? '';

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Nežinomas IP';
    if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }

    $data = [
        'vart_id' => sanitize_input($vart_id),
        'var_mokykla' => sanitize_input($var_mokykla),
        'action' => sanitize_input($action),
        'details' => sanitize_input($details),
        'ip_address' => sanitize_input($ip_address)
    ];
    if ($reg_id !== null) {
        $data['reg_id'] = (int)$reg_id;
    }

    if (function_exists('db_insert')) {
        db_insert('teacher_activity_log', $data);
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

function get_system_theme() {
    $theme_file = dirname(dirname(__FILE__)) . '/config/theme.json';
    // PATAISYTA (modernizavimas): atnaujinta numatytoji paletė - Indigo/Slate,
    // atitinkanti šiuolaikinių administravimo sistemų dizaino standartus.
    // Administratorius bet kada gali pakeisti per admin/theme_settings.php.
    $default_theme = [
        'primary_color'       => '#4f46e5', 'secondary_color'     => '#64748b', 'success_color'       => '#16a34a',
        'info_color'          => '#0891b2', 'warning_color'       => '#f59e0b', 'danger_color'        => '#dc2626',
        'body_bg'             => '#f8fafc', 'text_color'          => '#1e293b', 'topbar_bg'           => '#ffffff',
        'topbar_text'         => '#64748b', 'topbar_hover'        => '#4f46e5', 'sidebar_bg'          => '#4f46e5',
        'sidebar_text'        => '#ffffff', 'sidebar_hover_bg'    => '#4338ca', 'sidebar_active_bg'   => '#ffffff',
        'sidebar_active_text' => '#4f46e5', 'card_bg'             => '#ffffff', 'card_header_bg'      => '#fbfcfe',
        'card_border'         => '#e2e8f0', 'table_header_bg'     => '#f8fafc', 'table_header_text'   => '#64748b',
        'footer_bg'           => '#ffffff', 'footer_text'         => '#64748b', 'logo_path'           => 'assets/img/logo.png',
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