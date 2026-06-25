<?php
/**
 * Vizualaus diplomo šablono redagavimo puslapis (WYSIWYG)
 */
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname, ar prisijungęs administratorius
if (!is_logged_in() || !is_admin()) {
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL);
}

$template_file = dirname(dirname(dirname(__FILE__))) . '/config/diploma_template.html';

// Jei forma pateikta, išsaugome šabloną
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['template_content'])) {
    $content = $_POST['template_content'];
    
    // Išsaugome į failą
    if (file_put_contents($template_file, $content) !== false) {
        set_message('Diplomo šablonas sėkmingai išsaugotas!', 'success');
    } else {
        set_message('Klaida: Nepavyko išsaugoti failo. Patikrinkite direktorijos teises.', 'error');
    }
    redirect(SITE_URL . '/modules/admin/diploma_template.php');
}

// Nuskaitome dabartinį šabloną arba sukuriame standartinį vizualiam redaktoriui
if (file_exists($template_file)) {
    $html_template = file_get_contents($template_file);
} else {
    // Standartinis, paprastesnis išdėstymas, kuris puikiai veikia WYSIWYG redaktoriuose ir TCPDF
    $html_template = '
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
    @file_put_contents($template_file, $html_template);
}

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<script src="http://olimpiada.sprendimas.eu/assets/js/tinymce.min.js" referrerpolicy="origin"></script>
<script>
  tinymce.init({
    selector: '#template_content',
    height: 700,
    language: 'lt', // Nustatome lietuvių kalbą, jei naršyklė palaiko
    plugins: [
      'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
      'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
      'insertdatetime', 'media', 'table', 'help', 'wordcount'
    ],
    // Sukonfigūruojame įrankių juostą (pašaliname font-family, kad PDF nesulūžtų lietuviškos raidės)
    toolbar: 'undo redo | insertvars | blocks fontsize | ' +
    'bold italic forecolor backcolor | alignleft aligncenter ' +
    'alignright alignjustify | bullist numlist outdent indent | ' +
    'image table | removeformat | code fullscreen preview help',
    
    content_style: 'body { font-family: Helvetica, Arial, sans-serif; font-size: 16px; background-color: #fff; }',
    
    // Pridedame savo unikalų mygtuką sistemos kintamiesiems
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
    }
  });
</script>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
                <h2 class="mb-0 h4"><i class="fas fa-certificate"></i> Vizualus diplomo šablonas</h2>
                <a href="<?php echo SITE_URL; ?>/modules/admin/index.php" class="btn btn-light btn-sm">Grįžti</a>
            </div>
            <div class="card-body">
                <?php display_message(); ?>
                
                <div class="alert alert-info">
                    <strong>Patarimas:</strong> Naudokite mygtuką <strong>„Įterpti kintamąjį“</strong> įrankių juostoje, kad įdėtumėte vietas, kur sistema automatiškai įrašys realius duomenis (pvz. mokinio vardą).
                    <br><small><em>Pastaba:</em> Šriftų stilių keitimas apribotas tik dydžiu ir spalvomis tam, kad PDF dokumente teisingai veiktų visos lietuviškos (ąčęėįšųūž) raidės.</small>
                </div>

                <form action="" method="post">
                    <div class="form-group mb-4">
                        <textarea id="template_content" name="template_content"><?php echo htmlspecialchars($html_template); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-success btn-lg w-100">
                        <i class="fas fa-save"></i> Išsaugoti šabloną
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>