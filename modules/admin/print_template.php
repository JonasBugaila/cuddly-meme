<?php
/**
 * Universalus spausdinimo maketo redaktorius (Lokalus HugeRTE su puslapiavimu)
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

// Numatytieji duomenys (Papildyti gražiu parašų bloku iš kelių eilučių)
$default_data = [
    'header_html' => '<div style="text-align: center; margin-bottom: 20px;"><h3>{{INSTITUTION}}</h3><h4 style="color: #444;">{{TITLE}}</h4></div>',
    'footer_html' => '<div style="margin-top: 40px;"><table style="width: 100%; border: none;"><tr style="border: none;"><td style="border: none; width: 50%;"><strong>Komisijos pirmininkas:</strong> ___________________</td><td style="border: none; width: 50%;"><strong>Komisijos sekretorius:</strong> ___________________</td></tr><tr style="border: none;"><td style="border: none; padding-top: 20px;"><strong>Komisijos narys:</strong> ___________________</td><td style="border: none; padding-top: 20px;"><strong>Data:</strong> {{DATE}}</td></tr></table></div>',
    'margin_t' => 20,
    'margin_b' => 20,
    'margin_l' => 20,
    'margin_r' => 20,
    'font_size' => 12,
    'show_page_num' => 1 // Naujas nustatymas puslapių numeracijai
];

// Saugus failo nuskaitymas
$layout = [];
if (file_exists($config_file)) {
    $layout = json_decode(file_get_contents($config_file), true);
}
if (!is_array($layout) || empty($layout)) {
    $layout = $default_data;
    file_put_contents($config_file, json_encode($layout, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Apdorojame formos išsaugojimą
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_layout'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $data = [
            'header_html' => $_POST['header_html'] ?? '',
            'footer_html' => $_POST['footer_html'] ?? '',
            'margin_t' => (int)($_POST['margin_t'] ?? 20),
            'margin_b' => (int)($_POST['margin_b'] ?? 20),
            'margin_l' => (int)($_POST['margin_l'] ?? 20),
            'margin_r' => (int)($_POST['margin_r'] ?? 20),
            'font_size' => (int)($_POST['font_size'] ?? 12),
            'show_page_num' => isset($_POST['show_page_num']) ? 1 : 0
        ];
        
        file_put_contents($config_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        set_message('Spausdinimo maketas sėkmingai išsaugotas!', 'success');
        redirect(SITE_URL . '/modules/admin/print_template.php');
        exit;
    }
}

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
            toolbar: 'undo redo | insertvars | blocks fontsize | bold italic forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | table | code preview',
            content_style: 'body { font-family: Helvetica, Arial, sans-serif; font-size: 14px; background-color: #fff; }',
            
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
                            // Papildomos eilutės parašams
                            { type: 'menuitem', text: 'Eilutė: Komisijos pirmininkas', onAction: function () { editor.insertContent('<p><strong>Komisijos pirmininkas:</strong> ___________________________</p>'); } },
                            { type: 'menuitem', text: 'Eilutė: Komisijos sekretorius', onAction: function () { editor.insertContent('<p><strong>Komisijos sekretorius:</strong> ___________________________</p>'); } },
                            { type: 'menuitem', text: 'Eilutė: Komisijos narys', onAction: function () { editor.insertContent('<p><strong>Komisijos narys:</strong> ___________________________</p>'); } },
                            { type: 'menuitem', text: 'Eilutė: Tuščia parašo eilutė', onAction: function () { editor.insertContent('<p>___________________________ &nbsp;&nbsp;&nbsp;&nbsp; ___________________________</p>'); } }
                        ];
                        callback(items);
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
            <div class="card-body">
                <?php display_message(); ?>
                
                <div class="alert alert-info">
                    <strong>Patarimas parašams:</strong> Naudokite viršutinį mygtuką <strong>„Įterpti kintamąjį / eilutę“</strong>, kad akimirksniu įterptumėte naujas parašų eilutes pirmininkui, sekretoriui ar nariams. Galite sukurti kiek tik norite eilučių!
                </div>

                <form action="" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="form-group mb-4">
                                <label class="fw-bold fs-5 mb-2">Puslapio Antraštė (Viršutinė dalis)</label>
                                <textarea class="hugerte-editor" name="header_html"><?php echo htmlspecialchars($layout['header_html'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group mb-4">
                                <label class="fw-bold fs-5 mb-2">Parašai / Dokumento pabaiga (Apatinė dalis - eilučių blokas)</label>
                                <textarea class="hugerte-editor" name="footer_html"><?php echo htmlspecialchars($layout['footer_html'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card bg-light border-0 mb-4">
                                <div class="card-body">
                                    <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="fas fa-file-alt"></i> Puslapio numeracija</h5>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="show_page_num" name="show_page_num" value="1" <?php echo (isset($layout['show_page_num']) && $layout['show_page_num'] == 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-bold" for="show_page_num">Rodyti puslapių numerius</label>
                                    </div>
                                    <small class="text-muted d-block mb-3">Įjungus šį nustatymą, kiekvieno spausdinamo lapo apačioje dešinėje automatiškai atsiras užrašas „Puslapis 1“, „Puslapis 2“.</small>
                                </div>
                            </div>

                            <div class="card bg-light border-0">
                                <div class="card-body">
                                    <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="fas fa-arrows-alt"></i> Paraštės (Milimetrais)</h5>
                                    <div class="row g-2 mb-4">
                                        <div class="col-6">
                                            <label class="small text-muted">Viršutinė</label>
                                            <input type="number" name="margin_t" class="form-control" value="<?php echo $layout['margin_t'] ?? 20; ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="small text-muted">Apatinė</label>
                                            <input type="number" name="margin_b" class="form-control" value="<?php echo $layout['margin_b'] ?? 20; ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="small text-muted">Kairioji (Segtuvui)</label>
                                            <input type="number" name="margin_l" class="form-control" value="<?php echo $layout['margin_l'] ?? 20; ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="small text-muted">Dešinioji</label>
                                            <input type="number" name="margin_r" class="form-control" value="<?php echo $layout['margin_r'] ?? 20; ?>">
                                        </div>
                                    </div>
                                    
                                    <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="fas fa-font"></i> Lentelės Šriftas</h5>
                                    <div class="mb-4">
                                        <label class="small text-muted">Dydis spausdinant (pt)</label>
                                        <input type="number" name="font_size" class="form-control" value="<?php echo $layout['font_size'] ?? 12; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="save_layout" class="btn btn-success btn-lg w-100 mt-3">
                        <i class="fas fa-save"></i> Išsaugoti spausdinimo maketą visai sistemai
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>