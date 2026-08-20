<?php
/**
 * Universalus spausdinimo maketo redaktorius (Lokalus HugeRTE su puslapiavimu ir skirtingais šablonais)
 */
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Leidžiame prieiti tik administratoriams
if (!is_logged_in() || !is_admin()) {
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/index.php');
    exit;
}

$config_file = dirname(dirname(dirname(__FILE__))) . '/config/print_layout.json';

// Galimi šablonai ir jų pavadinimai
$templates = [
    'protocol'   => 'Rezultatų protokolai',
    'evaluation' => 'Vertinimo lapai',
    'codes'      => 'Kodų priskyrimo lapai',
    'signature'  => 'Parašų lapas'
];

// Nustatome, kuris šablonas dabar atidarytas (pagal nutylėjimą - protokolas)
$current_key = isset($_GET['type']) && array_key_exists($_GET['type'], $templates) ? $_GET['type'] : 'protocol';

// Numatytieji duomenys kiekvienam šablonui, jei jie tušti
$default_data = [
    'header_html' => '<div style="text-align: center; margin-bottom: 20px;"><h3>{{INSTITUTION}}</h3><h4 style="color: #444;">{{TITLE}}</h4></div>',
    'footer_html' => '<div style="margin-top: 40px;"><table style="width: 100%; border: none;"><tr style="border: none;"><td style="border: none; width: 50%;"><strong>Komisijos pirmininkas:</strong> ___________________</td><td style="border: none; width: 50%;"><strong>Komisijos sekretorius:</strong> ___________________</td></tr><tr style="border: none;"><td style="border: none; padding-top: 20px;"><strong>Komisijos narys:</strong> ___________________</td><td style="border: none; padding-top: 20px;"><strong>Data:</strong> {{DATE}}</td></tr></table></div>',
    'margin_t' => 20,
    'margin_b' => 20,
    'margin_l' => 20,
    'margin_r' => 20,
    'font_size' => 12,
    'show_page_num' => 1
];

// Saugus failo nuskaitymas ir struktūros atnaujinimas
$all_layouts = [];
if (file_exists($config_file)) {
    $all_layouts = json_decode(file_get_contents($config_file), true);
    
    // ATGALINIS SUDERINAMUMAS: Jei senas failas turi tik vieną pagrindinį šabloną
    if (isset($all_layouts['header_html']) && !isset($all_layouts['protocol'])) {
        $old_layout = $all_layouts;
        $all_layouts = [
            'protocol'   => $old_layout,
            'evaluation' => $old_layout,
            'codes'      => $old_layout,
            'signature'  => $old_layout
        ];
        file_put_contents($config_file, json_encode($all_layouts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

// Apdorojame formos išsaugojimą konkrečiam šablonui
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_layout'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $type = sanitize_input($_POST['template_type']);
        if (array_key_exists($type, $templates)) {
            $all_layouts[$type] = [
                'header_html' => $_POST['header_html'] ?? '',
                'footer_html' => $_POST['footer_html'] ?? '',
                'margin_t' => (int)($_POST['margin_t'] ?? 20),
                'margin_b' => (int)($_POST['margin_b'] ?? 20),
                'margin_l' => (int)($_POST['margin_l'] ?? 20),
                'margin_r' => (int)($_POST['margin_r'] ?? 20),
                'font_size' => (int)($_POST['font_size'] ?? 12),
                'show_page_num' => isset($_POST['show_page_num']) ? 1 : 0
            ];
            
            file_put_contents($config_file, json_encode($all_layouts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            set_message("Šablonas <strong>\"{$templates[$type]}\"</strong> sėkmingai išsaugotas!", 'success');
            redirect(SITE_URL . '/modules/admin/print_template.php?type=' . $type);
            exit;
        }
    }
}

// Paimame pasirinkto šablono duomenis atvaizdavimui arba užpildome numatytaisiais
$current_layout = $all_layouts[$current_key] ?? $default_data;

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<script src="<?php echo SITE_URL; ?>/assets/hugerte/hugerte.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    if (typeof hugerte !== 'undefined') {
        hugerte.init({
            selector: '.hugerte-editor',
            height: 280,
            promotion: false, 
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'help', 'wordcount'
            ],
            toolbar: 'undo redo | insertvars | newline | blocks fontsize | bold italic forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | table | code preview',
            content_style: 'body { font-family: Helvetica, Arial, sans-serif; font-size: 14px; background-color: #fff; }',
            // PRIDĖTA: paprastas Enter klavišas dabar visada įterpia eilutės lūžį (<br>),
            // o ne naują pastraipą (<p>) - trumpiems formatuotiems fragmentams (antraštės,
            // parašų laukai lentelėse) tai atitinka intuityvų "paspaudžiu Enter - gaunu
            // naują eilutę" lūkestį ir išsprendžia painiavą su lentelių langeliais, kur
            // nauja pastraipa dažnai nerodydavo jokio matomo pokyčio.
            newline_behavior: 'linebreak',

            setup: function (editor) {
                editor.ui.registry.addMenuButton('insertvars', {
                    text: 'Įterpti kintamąjį / eilutę',
                    icon: 'plus',
                    fetch: function (callback) {
                        var items = [
                            { type: 'menuitem', text: 'Institucijos Pavadinimas', onAction: function () { editor.insertContent(' <strong>{{INSTITUTION}}</strong> '); } },
                            { type: 'menuitem', text: 'Dokumento Pavadinimas', onAction: function () { editor.insertContent(' <strong>{{TITLE}}</strong> '); } },
                            { type: 'menuitem', text: 'Šiandienos Data', onAction: function () { editor.insertContent(' {{DATE}} '); } },
                            { type: 'separator' },
                            { type: 'menuitem', text: 'Eilutė: Komisijos pirmininkas', onAction: function () { editor.insertContent('<p><strong>Komisijos pirmininkas:</strong> ___________________________</p>'); } },
                            { type: 'menuitem', text: 'Eilutė: Komisijos sekretorius', onAction: function () { editor.insertContent('<p><strong>Komisijos sekretorius:</strong> ___________________________</p>'); } },
                            { type: 'menuitem', text: 'Eilutė: Komisijos narys', onAction: function () { editor.insertContent('<p><strong>Komisijos narys:</strong> ___________________________</p>'); } },
                            { type: 'menuitem', text: 'Eilutė: Tuščia parašo eilutė', onAction: function () { editor.insertContent('<p>___________________________ &nbsp;&nbsp;&nbsp;&nbsp; ___________________________</p>'); } }
                        ];
                        callback(items);
                    }
                });

                // PRIDĖTA: aiškus, visada matomas mygtukas naujai eilutei įterpti -
                // nepriklauso nuo klaviatūros klavišų elgesio ir veikia bet kurioje
                // vietoje (įskaitant lentelių langelius, kur Enter klavišas dažnai
                // "neveikdavo" arba neduodavo jokio matomo rezultato).
                editor.ui.registry.addButton('newline', {
                    text: 'Nauja eilutė',
                    tooltip: 'Įterpti naują eilutę (line break) žymeklio vietoje',
                    onAction: function () {
                        // SAUGU: tiesioginis turinio įterpimas per insertContent() yra
                        // patikimiausias metodas - neveikia priklausomai nuo to, ar
                        // konkretus komandos pavadinimas (execCommand) atpažįstamas
                        // šioje HugeRTE versijoje.
                        editor.insertContent('<br>');
                    }
                });
            }
        });
    }
});
</script>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
                <h2 class="mb-0 h4"><i class="fas fa-print"></i> Universalus Spausdinimo Maketas</h2>
                <a href="<?php echo SITE_URL; ?>/modules/admin/index.php" class="btn btn-light btn-sm">Grįžti</a>
            </div>
            <div class="card-body bg-light">
                <?php display_message(); ?>
                
                <!-- ŠABLONO PASIRINKIMO FORMA -->
                <form method="GET" action="" class="mb-4 bg-white p-3 border rounded shadow-sm">
                    <label for="templateSelector" class="form-label fw-bold text-primary">
                        <i class="fas fa-hand-pointer"></i> Pasirinkite, kurį šabloną norite redaguoti:
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fas fa-file-alt"></i></span>
                        <select name="type" id="templateSelector" class="form-select form-select-lg border-primary fw-bold" onchange="this.form.submit()">
                            <?php foreach ($templates as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $current_key === $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <div class="alert alert-info">
                    <strong>Patarimas parašams:</strong> Naudokite viršutinį mygtuką <strong>„Įterpti kintamąjį / eilutę“</strong> redaktoriuje, kad akimirksniu įterptumėte naujas parašų eilutes pirmininkui, sekretoriui ar nariams.
                </div>

                <!-- REDAGAVIMO FORMA -->
                <form action="" method="post" class="bg-white p-4 border rounded shadow-sm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="template_type" value="<?php echo $current_key; ?>">
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="form-group mb-4">
                                <label class="fw-bold fs-5 mb-2 text-dark">Puslapio Antraštė (Viršutinė dalis)</label>
                                <textarea class="hugerte-editor" name="header_html"><?php echo htmlspecialchars($current_layout['header_html'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group mb-4">
                                <label class="fw-bold fs-5 mb-2 text-dark">Parašai / Dokumento pabaiga (Apatinė dalis)</label>
                                <textarea class="hugerte-editor" name="footer_html"><?php echo htmlspecialchars($current_layout['footer_html'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card bg-light border-0 mb-4 shadow-sm">
                                <div class="card-body">
                                    <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="fas fa-file-alt"></i> Puslapio numeracija</h5>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="show_page_num" name="show_page_num" value="1" <?php echo (isset($current_layout['show_page_num']) && $current_layout['show_page_num'] == 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-bold" for="show_page_num">Rodyti puslapių numerius</label>
                                    </div>
                                    <small class="text-muted d-block mb-3">Įjungus šį nustatymą, kiekvieno spausdinamo lapo apačioje atsiras užrašas „Puslapis 1“.</small>
                                </div>
                            </div>

                            <div class="card bg-light border-0 shadow-sm">
                                <div class="card-body">
                                    <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="fas fa-arrows-alt"></i> Paraštės (mm) ir Šriftas</h5>
                                    <div class="row g-2 mb-4">
                                        <div class="col-6">
                                            <label class="small text-muted fw-bold">Viršutinė</label>
                                            <input type="number" name="margin_t" class="form-control" value="<?php echo $current_layout['margin_t'] ?? 20; ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="small text-muted fw-bold">Apatinė</label>
                                            <input type="number" name="margin_b" class="form-control" value="<?php echo $current_layout['margin_b'] ?? 20; ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="small text-muted fw-bold">Kairioji</label>
                                            <input type="number" name="margin_l" class="form-control" value="<?php echo $current_layout['margin_l'] ?? 20; ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="small text-muted fw-bold">Dešinioji</label>
                                            <input type="number" name="margin_r" class="form-control" value="<?php echo $current_layout['margin_r'] ?? 20; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <label class="small text-muted fw-bold">Šrifto dydis (pt)</label>
                                        <input type="number" name="font_size" class="form-control" value="<?php echo $current_layout['font_size'] ?? 12; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="save_layout" class="btn btn-success btn-lg w-100 mt-4 shadow-sm">
                        <i class="fas fa-save"></i> Išsaugoti šį maketą (<?php echo $templates[$current_key]; ?>)
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>