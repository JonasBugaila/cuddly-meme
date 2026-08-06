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
        $current_theme['logo_width'] = sanitize_input($_POST['logo_width']);
    }

    // Logotipo įkėlimas
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'];
        $file_type = mime_content_type($_FILES['logo_file']['tmp_name']);
        
        if (in_array($file_type, $allowed_types)) {
            $ext = pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION);
            $new_filename = 'logo_' . time() . '.' . $ext;
            $upload_dir = dirname(dirname(dirname(__FILE__))) . '/assets/img/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $destination = $upload_dir . $new_filename;
            if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $destination)) {
                $old_logo_path = dirname(dirname(dirname(__FILE__))) . '/' . $current_theme['logo_path'];
                if (file_exists($old_logo_path) && strpos($current_theme['logo_path'], 'logo_' ) !== false) {
                    unlink($old_logo_path);
                }
                $current_theme['logo_path'] = 'assets/img/' . $new_filename;
            }
        } else {
            set_message('Netinkamas logotipo formatas.', 'error');
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
    echo "
    <div class='{$col}'>
        <label class='form-label small fw-bold text-muted mb-1'>{$label}</label>
        <div class='d-flex align-items-center gap-2'>
            <input type='color' class='form-control form-control-color shadow-sm border-0' name='{$name}' value='" . htmlspecialchars($current_val) . "'>
            <code class='text-dark bg-light px-2 py-1 rounded border'>" . htmlspecialchars($current_val) . "</code>
        </div>
    </div>";
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-paint-roller text-primary me-2"></i> Sistemos dizaino valdymas</h1>
    <a href="<?php echo SITE_URL; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Grįžti atgal</a>
</div>

<?php display_message(); ?>

<form action="" method="post" enctype="multipart/form-data">
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
                                <label class="form-label fw-bold">Įkelti naują logotipą (PNG, SVG, WEBP)</label>
                                <input type="file" class="form-control" name="logo_file" accept=".png,.jpg,.jpeg,.svg,.webp">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Logotipo plotis (pvz. 150px)</label>
                                <input type="text" class="form-control" name="logo_width" value="<?php echo htmlspecialchars($theme['logo_width']); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 text-end mb-5">
            <button type="submit" class="btn btn-primary btn-lg shadow-sm px-5 fw-bold">
                <i class="fas fa-save me-2"></i> Išsaugoti visus nustatymus
            </button>
        </div>
    </div>
</form>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>