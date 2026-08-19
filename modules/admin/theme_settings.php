<?php
/**
 * Sistemos išvaizdos (Theme / White-labeling) valdymo puslapis
 */
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in() || !is_admin()) {
    set_message('Neturite teisių pasiekti šį puslapį.', 'error');
    redirect(SITE_URL);
}

$theme_file = dirname(dirname(dirname(__FILE__))) . '/config/theme.json';

// Saugus duomenų apdorojimas po Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_message('Saugos klaida (CSRF). Bandykite dar kartą.', 'error');
        redirect(current_url());
    }

    $current_theme = get_system_theme();

    // Grąžinimas į numatytuosius nustatymus (atskiras submit mygtukas - apdorojame pirmiausia)
    if (isset($_POST['reset_theme'])) {
        if (file_exists($theme_file)) {
            unlink($theme_file);
        }
        set_message('Sistemos išvaizda atkurta į numatytuosius nustatymus.', 'success');
        redirect(current_url());
        exit;
    }
    
    // Visų kintamųjų sąrašas
    $colors_to_update = [
        'primary_color', 'secondary_color', 'success_color', 'info_color', 'warning_color', 'danger_color',
        'body_bg', 'text_color', 
        'topbar_bg', 'topbar_text', 'topbar_hover',
        'sidebar_bg', 'sidebar_text', 'sidebar_hover_bg', 'sidebar_active_bg', 'sidebar_active_text',
        'card_bg', 'card_header_bg', 'card_border', 
        'table_header_bg', 'table_header_text',
        'footer_bg', 'footer_text'
    ];

    foreach ($colors_to_update as $color_key) {
        if (isset($_POST[$color_key]) && preg_match('/^#[a-f0-9]{6}$/i', $_POST[$color_key])) {
            $current_theme[$color_key] = $_POST[$color_key];
        }
    }

    if (isset($_POST['logo_width'])) {
        // SAUGU: leidžiame tik skaičių + px arba % (pvz. "150px", "80%"), apsaugo nuo CSS injekcijos į <style> bloką
        $logo_width_raw = trim($_POST['logo_width']);
        if (preg_match('/^\d{1,4}(px|%)$/', $logo_width_raw)) {
            $current_theme['logo_width'] = $logo_width_raw;
        } else {
            set_message('Netinkamas logotipo pločio formatas (naudokite pvz. "150px" arba "80%"). Plotis nepakeistas.', 'warning');
        }
    }

    // Logotipo įkėlimas
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        // SAUGU: griežtas ryšys tarp aptikto MIME tipo ir leidžiamo failo plėtinio.
        // Plėtinys NIEKADA neimamas iš vartotojo pateikto failo pavadinimo -
        // priešingu atveju "polyglot" failas (tikras vaizdas su pridėtu PHP kodu),
        // pavadintas pvz. "logo.php", būtų aptiktas kaip "image/jpeg" pagal MIME
        // ir įrašytas su .php plėtiniu į viešai pasiekiamą assets/img/ katalogą,
        // kur serveris jį vykdytų kaip PHP skriptą (nuotolinio kodo vykdymo spraga).
        $mime_to_ext = [
            'image/jpeg'    => 'jpg',
            'image/png'     => 'png',
            'image/webp'    => 'webp',
            'image/svg+xml' => 'svg',
        ];

        $max_file_size = 2 * 1024 * 1024; // 2 MB
        if ($_FILES['logo_file']['size'] > $max_file_size) {
            set_message('Logotipo failas per didelis (maks. 2 MB).', 'error');
        } else {
            $tmp_path = $_FILES['logo_file']['tmp_name'];
            $file_type = mime_content_type($tmp_path);
            $is_valid = false;

            if ($file_type === 'image/svg+xml') {
                // SVG nėra rastrinis vaizdas, todėl getimagesize() jo nepatikrina -
                // patikriname turinį rankiniu būdu ir pašaliname galimai pavojingus elementus.
                $svg_content = file_get_contents($tmp_path);
                if ($svg_content !== false && (stripos($svg_content, '<svg') !== false)) {
                    // Pašaliname <script> blokus ir on* įvykių atributus (apsauga nuo XSS per SVG)
                    $svg_content = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svg_content);
                    $svg_content = preg_replace('/\son\w+\s*=\s*(["\']).*?\1/is', '', $svg_content);
                    $svg_content = preg_replace('/xlink:href\s*=\s*(["\'])\s*javascript:.*?\1/is', '', $svg_content);
                    file_put_contents($tmp_path, $svg_content);
                    $is_valid = true;
                }
            } elseif (isset($mime_to_ext[$file_type])) {
                // Rastriniams formatams papildomai patikriname per getimagesize() -
                // apsaugo nuo failų, kurių pirmieji baitai apsimeta vaizdu, bet turinys nėra validus paveikslėlis.
                $image_info = @getimagesize($tmp_path);
                $is_valid = ($image_info !== false);
            }

            if ($is_valid && isset($mime_to_ext[$file_type])) {
                $ext = $mime_to_ext[$file_type];
                $new_filename = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $upload_dir = dirname(dirname(dirname(__FILE__))) . '/assets/img/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                $destination = $upload_dir . $new_filename;
                if (move_uploaded_file($tmp_path, $destination)) {
                    $old_logo_path = dirname(dirname(dirname(__FILE__))) . '/' . $current_theme['logo_path'];
                    if (file_exists($old_logo_path) && strpos($current_theme['logo_path'], 'logo_') !== false) {
                        unlink($old_logo_path);
                    }
                    $current_theme['logo_path'] = 'assets/img/' . $new_filename;
                } else {
                    set_message('Nepavyko įrašyti logotipo failo į serverį.', 'error');
                }
            } else {
                set_message('Netinkamas arba sugadintas logotipo failas. Leidžiami formatai: JPG, PNG, WEBP, SVG.', 'error');
            }
        }
    }

    // Išsaugome į JSON
    if (file_put_contents($theme_file, json_encode($current_theme, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
        set_message('Sistemos išvaizda sėkmingai atnaujinta!', 'success');
    } else {
        set_message('Klaida. Patikrinkite config aplanko rašymo teises.', 'error');
    }
    redirect(current_url());
}

$theme = get_system_theme();
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';

// Pagalbinė funkcija formos laukeliams piešti
function render_color_input($name, $label, $current_val, $col = 'col-md-6') {
    $safe_val = htmlspecialchars($current_val);
    echo "
    <div class='{$col}'>
        <label class='form-label small fw-bold text-muted mb-1'>{$label}</label>
        <div class='d-flex align-items-center gap-2'>
            <input type='color' class='form-control form-control-color shadow-sm border-0 theme-color-input' name='{$name}' id='{$name}' value='{$safe_val}' data-hex-target='{$name}_hex'>
            <input type='text' class='form-control form-control-sm theme-hex-input' id='{$name}_hex' value='{$safe_val}' maxlength='7' pattern='^#[0-9a-fA-F]{6}$' data-color-target='{$name}' style='max-width: 100px;'>
        </div>
    </div>";
}
?>
<script>
// Sinchronizuojame spalvos parinkiklį (color picker) su tekstiniu HEX lauku abiem kryptimis
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.theme-color-input').forEach(function(colorInput) {
        var hexInput = document.getElementById(colorInput.dataset.hexTarget);
        if (!hexInput) return;
        colorInput.addEventListener('input', function() {
            hexInput.value = colorInput.value;
        });
    });
    document.querySelectorAll('.theme-hex-input').forEach(function(hexInput) {
        var colorInput = document.getElementById(hexInput.dataset.colorTarget);
        if (!colorInput) return;
        hexInput.addEventListener('input', function() {
            if (/^#[0-9a-fA-F]{6}$/.test(hexInput.value)) {
                colorInput.value = hexInput.value;
            }
        });
    });
});
</script>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-paint-roller text-primary me-2"></i> Sistemos dizaino valdymas</h1>
    <a href="<?php echo SITE_URL; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Grįžti atgal</a>
</div>

<?php display_message(); ?>

<form action="" method="post" enctype="multipart/form-data" id="theme-settings-form">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

    <div class="row g-4">
        
        <div class="col-xl-6">
            <div class="card shadow-sm border-0 h-100 border-left-primary">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-palette text-primary me-2"></i> Bazinės sistemos spalvos</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php 
                        render_color_input('primary_color', 'Pagrindinė (Primary) - Mygtukai, Akcentai', $theme['primary_color']);
                        render_color_input('secondary_color', 'Antrinė (Secondary) - Pilki mygtukai', $theme['secondary_color']);
                        render_color_input('success_color', 'Sėkmė (Success) - Pranešimai, Išsaugojimas', $theme['success_color']);
                        render_color_input('info_color', 'Informacija (Info) - Peržiūra, Informacija', $theme['info_color']);
                        render_color_input('warning_color', 'Įspėjimas (Warning) - Įspėjimai, Ataskaitos', $theme['warning_color']);
                        render_color_input('danger_color', 'Klaida (Danger) - Trynimas, Klaidos', $theme['danger_color']);
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card shadow-sm border-0 h-100 border-left-info">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-columns text-info me-2"></i> Šoninis meniu (Sidebar)</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php 
                        render_color_input('sidebar_bg', 'Meniu Fonas', $theme['sidebar_bg']);
                        render_color_input('sidebar_text', 'Meniu Tekstas / Ikonos', $theme['sidebar_text']);
                        render_color_input('sidebar_hover_bg', 'Fonas užvedus pelę', $theme['sidebar_hover_bg']);
                        ?>
                        <hr class="my-3 opacity-25">
                        <div class="col-12"><strong class="small text-primary uppercase">Aktyvus meniu punktas</strong></div>
                        <?php
                        render_color_input('sidebar_active_bg', 'Aktyvaus punkto fonas', $theme['sidebar_active_bg']);
                        render_color_input('sidebar_active_text', 'Aktyvaus punkto tekstas', $theme['sidebar_active_text']);
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card shadow-sm border-0 h-100 border-left-success">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-window-maximize text-success me-2"></i> Viršutinė juosta (Topbar) ir Puslapis</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12"><strong class="small text-success uppercase">Viršutinė juosta</strong></div>
                        <?php 
                        render_color_input('topbar_bg', 'Juostos Fonas', $theme['topbar_bg'], 'col-md-4');
                        render_color_input('topbar_text', 'Tekstas / Ikonos', $theme['topbar_text'], 'col-md-4');
                        render_color_input('topbar_hover', 'Spalva užvedus pelę', $theme['topbar_hover'], 'col-md-4');
                        ?>
                        <hr class="my-3 opacity-25">
                        <div class="col-12"><strong class="small text-success uppercase">Puslapio fonas ir bendras tekstas</strong></div>
                        <?php 
                        render_color_input('body_bg', 'Viso puslapio Fonas', $theme['body_bg']);
                        render_color_input('text_color', 'Bendras Tekstas (Antraštės, Turinis)', $theme['text_color']);
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card shadow-sm border-0 h-100 border-left-warning">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-table text-warning me-2"></i> Kortelės, Lentelės ir Poraštė</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12"><strong class="small text-warning uppercase">Kortelės ir Sąrašai</strong></div>
                        <?php 
                        render_color_input('card_bg', 'Kortelių Fonas', $theme['card_bg'], 'col-md-4');
                        render_color_input('card_header_bg', 'Kortelių Antraštės Fonas', $theme['card_header_bg'], 'col-md-4');
                        render_color_input('card_border', 'Kortelių ir Sąrašų Rėmelis (Border)', $theme['card_border'], 'col-md-4');
                        ?>
                        <hr class="my-3 opacity-25">
                        <div class="col-12"><strong class="small text-warning uppercase">Lentelės ir Poraštė</strong></div>
                        <?php 
                        render_color_input('table_header_bg', 'Lentelių Antraštės (Th) Fonas', $theme['table_header_bg'], 'col-md-6');
                        render_color_input('table_header_text', 'Lentelių Antraštės (Th) Tekstas', $theme['table_header_text'], 'col-md-6');
                        
                        render_color_input('footer_bg', 'Poraštės Fonas (Apačioje)', $theme['footer_bg'], 'col-md-6');
                        render_color_input('footer_text', 'Poraštės Tekstas', $theme['footer_text'], 'col-md-6');
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-12">
            <div class="card shadow-sm border-0 border-left-dark">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-image text-dark me-2"></i> Logotipas</h5>
                </div>
                <div class="card-body d-flex flex-column flex-md-row align-items-center gap-4">
                    <div class="text-center p-4 bg-light rounded border" style="min-width: 250px;">
                        <img src="<?php echo SITE_URL . '/' . htmlspecialchars($theme['logo_path']); ?>" alt="Logotipas" style="max-height: 80px; max-width: <?php echo htmlspecialchars($theme['logo_width']); ?>;">
                    </div>
                    
                    <div class="flex-grow-1 w-100">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-bold">Įkelti naują logotipą (PNG, JPG, SVG, WEBP)</label>
                                <input type="file" class="form-control" name="logo_file" accept=".png,.jpg,.jpeg,.svg,.webp">
                                <small class="text-muted d-block mt-1">Maksimalus failo dydis: 2 MB.</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Logotipo plotis</label>
                                <input type="text" class="form-control" name="logo_width" value="<?php echo htmlspecialchars($theme['logo_width']); ?>" placeholder="pvz. 150px" pattern="^\d{1,4}(px|%)$">
                                <small class="text-muted d-block mt-1">Formatas: skaičius + "px" arba "%".</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</form>

<div class="d-flex justify-content-between align-items-center mb-5">
    <form action="" method="post" onsubmit="return confirm('Ar tikrai norite atkurti numatytuosius sistemos dizaino nustatymus? Šis veiksmas negrąžinamas.');" class="m-0">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="reset_theme" value="1">
        <button type="submit" class="btn btn-outline-danger">
            <i class="fas fa-undo me-2"></i> Atkurti numatytuosius
        </button>
    </form>
    <button type="submit" form="theme-settings-form" class="btn btn-primary btn-lg shadow-sm px-5 fw-bold">
        <i class="fas fa-save me-2"></i> Išsaugoti visus nustatymus
    </button>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>