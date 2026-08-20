<?php
/**
 * Vizualaus diplomo šablono redagavimo puslapis su paraštėmis (WYSIWYG - Lokalus HugeRTE)
 */
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname, ar prisijungęs administratorius
if (!is_logged_in() || !is_admin()) {
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL);
    exit;
}

$config_file = dirname(dirname(dirname(__FILE__))) . '/config/diploma_layout.json';
$old_html_file = dirname(dirname(dirname(__FILE__))) . '/config/diploma_template.html';

// Standartinis išdėstymas
$default_html = '
<div style="text-align: center; padding: 20px;">
    <p style="text-align: right; color: #999999; font-size: 14px;"><strong>{DIP_NR}</strong></p>
    <p>{LOGO}</p>
    <h1 style="font-size: 48px; color: #333333; margin-top: 20px;">Diplomas</h1>
    <p style="font-size: 24px; color: #555555; font-style: italic;">Už pasiekimus respublikinėje olimpiadoje</p>
    <p style="font-size: 42px; color: #d4af37; margin-top: 30px;"><strong>{VIETA}</strong></p>
    <p style="font-size: 38px; color: #2c3e50; margin-top: 20px;"><strong>{VARDAS_PAVARDE}</strong></p>
    <p style="font-size: 28px; color: #444444; margin-top: 20px;">{MOKYKLA}</p>
    <p style="font-size: 26px; color: #666666; font-style: italic; margin-top: 20px;">„{OLIMPIADA}“</p>
    <p style="font-size: 20px; color: #888888; margin-top: 40px;">{DATA}</p>
</div>';

$default_data = [
    'content_html' => $default_html,
    'margin_t' => 0,
    'margin_b' => 0,
    'margin_l' => 0,
    'margin_r' => 0
];

// Nuskaitome dabartinį šabloną (su migracija iš seno HTML failo, jei toks yra)
$layout = [];
if (file_exists($config_file)) {
    $layout = json_decode(file_get_contents($config_file), true);
} elseif (file_exists($old_html_file)) {
    // Jei randame seną HTML failą, perkeliam jo turinį į naują formatą
    $layout = $default_data;
    $layout['content_html'] = file_get_contents($old_html_file);
}

if (!is_array($layout) || empty($layout)) {
    $layout = $default_data;
    file_put_contents($config_file, json_encode($layout, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Jei forma pateikta, išsaugome šabloną
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_message('Saugumo klaida: neteisingas CSRF žetonas.', 'error');
        redirect(SITE_URL . '/modules/admin/diploma_template.php');
        exit;
    }

    $data = [
        'content_html' => $_POST['template_content'] ?? '',
        'margin_t' => (int)($_POST['margin_t'] ?? 0),
        'margin_b' => (int)($_POST['margin_b'] ?? 0),
        'margin_l' => (int)($_POST['margin_l'] ?? 0),
        'margin_r' => (int)($_POST['margin_r'] ?? 0)
    ];
    
    if (file_put_contents($config_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false) {
        set_message('Diplomo šablonas sėkmingai išsaugotas!', 'success');
    } else {
        set_message('Klaida: Nepavyko išsaugoti failo. Patikrinkite direktorijos teises.', 'error');
    }
    redirect(SITE_URL . '/modules/admin/diploma_template.php');
    exit;
}

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<script src="<?php echo SITE_URL; ?>/assets/hugerte/hugerte.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    if (typeof hugerte !== 'undefined') {
        hugerte.init({
            selector: '#template_content',
            height: 700,
            promotion: false,
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'help', 'wordcount'
            ],
            toolbar: 'undo redo | insertvars | newline | blocks fontsize | ' +
                     'bold italic forecolor backcolor | alignleft aligncenter ' +
                     'alignright alignjustify | bullist numlist outdent indent | ' +
                     'image table | removeformat | code fullscreen preview help',
            content_style: 'body { font-family: Helvetica, Arial, sans-serif; font-size: 16px; background-color: #fff; }',
            // PRIDĖTA: paprastas Enter dabar visada įterpia eilutės lūžį (<br>), o ne
            // naują pastraipą - žr. paaiškinimą modules/admin/print_template.php faile.
            newline_behavior: 'linebreak',
            setup: function (editor) {
                editor.ui.registry.addMenuButton('insertvars', {
                    text: 'Įterpti kintamąjį',
                    icon: 'plus',
                    fetch: function (callback) {
                        var items = [
                            { type: 'menuitem', text: 'Diplomo numeris', onAction: function () { editor.insertContent(' <strong>{DIP_NR}</strong> '); } },
                            { type: 'menuitem', text: 'Sistemos Logotipas', onAction: function () { editor.insertContent(' <p>{LOGO}</p> '); } },
                            { type: 'menuitem', text: 'Užimta vieta', onAction: function () { editor.insertContent(' <strong>{VIETA}</strong> '); } },
                            { type: 'menuitem', text: 'Mokinio Vardas Pavardė', onAction: function () { editor.insertContent(' <strong>{VARDAS_PAVARDE}</strong> '); } },
                            { type: 'menuitem', text: 'Mokykla', onAction: function () { editor.insertContent(' {MOKYKLA} '); } },
                            { type: 'menuitem', text: 'Olimpiados pavadinimas', onAction: function () { editor.insertContent(' „{OLIMPIADA}“ '); } },
                            { type: 'menuitem', text: 'Data', onAction: function () { editor.insertContent(' {DATA} '); } }
                        ];
                        callback(items);
                    }
                });

                // PRIDĖTA: aiškus mygtukas naujai eilutei įterpti (žr. paaiškinimą
                // modules/admin/print_template.php faile)
                editor.ui.registry.addButton('newline', {
                    text: 'Nauja eilutė',
                    tooltip: 'Įterpti naują eilutę (line break) žymeklio vietoje',
                    onAction: function () {
                        // SAUGU: tiesioginis turinio įterpimas - patikimiausias metodas
                        editor.insertContent('<br>');
                    }
                });
            }
        });
    } else {
        alert('Klaida: HugeRTE nepavyko užkrauti.');
    }
});
</script>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
                <h2 class="mb-0 h4"><i class="fas fa-certificate"></i> Vizualus diplomo šablonas</h2>
                <a href="<?php echo SITE_URL; ?>/modules/admin/index.php" class="btn btn-light btn-sm">Grįžti</a>
            </div>
            <div class="card-body p-4">
                <?php display_message(); ?>
                
                <div class="alert alert-info">
                    <strong>Patarimas:</strong> Naudokite mygtuką <strong>„Įterpti kintamąjį“</strong> įrankių juostoje. PDF formatas yra labai jautrus šriftams, todėl stenkitės dizainą formuoti paprastai (naudojant spalvas ir dydžius).
                </div>

                <form action="" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="row">
                        <div class="col-lg-9 mb-4">
                            <textarea id="template_content" name="template_content"><?php echo htmlspecialchars($layout['content_html'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-lg-3">
                            <div class="card bg-light border-0">
                                <div class="card-body">
                                    <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="fas fa-arrows-alt"></i> Paraštės (mm)</h5>
                                    <div class="mb-3">
                                        <label class="form-label small text-muted mb-1">Viršutinė</label>
                                        <input type="number" name="margin_t" class="form-control" value="<?php echo $layout['margin_t'] ?? 0; ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small text-muted mb-1">Apatinė</label>
                                        <input type="number" name="margin_b" class="form-control" value="<?php echo $layout['margin_b'] ?? 0; ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small text-muted mb-1">Kairioji</label>
                                        <input type="number" name="margin_l" class="form-control" value="<?php echo $layout['margin_l'] ?? 0; ?>">
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label small text-muted mb-1">Dešinioji</label>
                                        <input type="number" name="margin_r" class="form-control" value="<?php echo $layout['margin_r'] ?? 0; ?>">
                                    </div>
                                    
                                    <button type="submit" name="save_template" class="btn btn-success w-100">
                                        <i class="fas fa-save"></i> Išsaugoti
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>