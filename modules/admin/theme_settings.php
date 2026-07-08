<?php
/**
 * Sistemos išvaizdos (Theme) valdymo puslapis
 */
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in() || !is_admin()) {
    set_message('Neturite teisių pasiekti šį puslapį.', 'error');
    redirect(SITE_URL);
}

$theme_file = dirname(dirname(dirname(__FILE__))) . '/config/theme.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_message('Saugos klaida (CSRF). Bandykite dar kartą.', 'error');
        redirect(current_url());
    }

    $current_theme = get_system_theme();
    $colors_to_update = [
        'primary_color', 'success_color', 'warning_color', 'info_color', 'danger_color',
        'body_bg', 'text_color', 'header_bg', 'header_text', 'sidebar_bg', 'sidebar_text', 
        'sidebar_hover', 'card_bg', 'card_header_bg', 'footer_bg', 'footer_text'
    ];

    foreach ($colors_to_update as $color_key) {
        if (isset($_POST[$color_key]) && preg_match('/^#[a-f0-9]{6}$/i', $_POST[$color_key])) {
            $current_theme[$color_key] = $_POST[$color_key];
        }
    }

    if (isset($_POST['logo_width'])) $current_theme['logo_width'] = sanitize_input($_POST['logo_width']);

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

    if (file_put_contents($theme_file, json_encode($current_theme, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
        set_message('Sistemos išvaizda sėkmingai atnaujinta!', 'success');
    }
    redirect(current_url());
}

$theme = get_system_theme();
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-paint-roller text-primary me-2"></i> Išvaizdos nustatymai</h1>
    <a href="<?php echo SITE_URL; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Atgal į pagrindinį</a>
</div>

<?php display_message(); ?>

<form action="" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

    <div class="row g-4">
        
        <div class="col-xl-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom-primary">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-heading text-primary me-2"></i> Viršutinė meniu juosta (Header)</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">Meniu juostos fonas</label>
                            <input type="color" class="form-control form-control-color w-100 shadow-sm" name="header_bg" value="<?php echo htmlspecialchars($theme['header_bg']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">Meniu punktų tekstas / Ikonos</label>
                            <input type="color" class="form-control form-control-color w-100 shadow-sm" name="header_text" value="<?php echo htmlspecialchars($theme['header_text']); ?>">
                        </div>
                        <div class="col-12 text-muted small mt-2">
                            <i class="fas fa-info-circle"></i> Užvedus pelę ant meniu punkto, jis nusidažys <strong>Pirmine sistemos spalva (Primary)</strong>.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-mouse-pointer text-success me-2"></i> Mygtukų ir akcentų spalvos</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">Pirminė spalva (Mygtukai / Aktyvūs)</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" class="form-control form-control-color shadow-sm" name="primary_color" value="<?php echo htmlspecialchars($theme['primary_color']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">Sėkmės spalva (Išsaugojimas)</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" class="form-control form-control-color shadow-sm" name="success_color" value="<?php echo htmlspecialchars($theme['success_color']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">Informacinė spalva (Peržiūra)</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" class="form-control form-control-color shadow-sm" name="info_color" value="<?php echo htmlspecialchars($theme['info_color']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">Įspėjimo spalva (Parašų lapas)</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" class="form-control form-control-color shadow-sm" name="warning_color" value="<?php echo htmlspecialchars($theme['warning_color']); ?>">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-muted mb-1">Klaidos / Trynimo spalva</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" class="form-control form-control-color shadow-sm" name="danger_color" value="<?php echo htmlspecialchars($theme['danger_color']); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-layer-group text-info me-2"></i> Šoninis meniu ir Kortelės</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1">Šoninio meniu fonas</label>
                            <input type="color" class="form-control form-control-color w-100 shadow-sm" name="sidebar_bg" value="<?php echo htmlspecialchars($theme['sidebar_bg']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1">Šoninis tekstas</label>
                            <input type="color" class="form-control form-control-color w-100 shadow-sm" name="sidebar_text" value="<?php echo htmlspecialchars($theme['sidebar_text']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1">Aktyvus meniu fonas</label>
                            <input type="color" class="form-control form-control-color w-100 shadow-sm" name="sidebar_hover" value="<?php echo htmlspecialchars($theme['sidebar_hover']); ?>">
                        </div>
                        <hr class="my-3 opacity-25">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">Kortelių fonas</label>
                            <input type="color" class="form-control form-control-color w-100 shadow-sm" name="card_bg" value="<?php echo htmlspecialchars($theme['card_bg']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">Kortelių antraštės fonas</label>
                            <input type="color" class="form-control form-control-color w-100 shadow-sm" name="card_header_bg" value="<?php echo htmlspecialchars($theme['card_header_bg']); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-desktop text-warning me-2"></i> Bendra aplinka ir Logotipas</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Puslapio fonas</label>
                            <input type="color" class="form-control form-control-color w-100 shadow-sm" name="body_bg" value="<?php echo htmlspecialchars($theme['body_bg']); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Bendras tekstas</label>
                            <input type="color" class="form-control form-control-color w-100 shadow-sm" name="text_color" value="<?php echo htmlspecialchars($theme['text_color']); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Poraštės fonas</label>
                            <input type="color" class="form-control form-control-color w-100 shadow-sm" name="footer_bg" value="<?php echo htmlspecialchars($theme['footer_bg']); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Poraštės tekstas</label>
                            <input type="color" class="form-control form-control-color w-100 shadow-sm" name="footer_text" value="<?php echo htmlspecialchars($theme['footer_text']); ?>">
                        </div>
                    </div>
                    
                    <hr class="my-4 opacity-25">
                    
                    <div class="d-flex align-items-center gap-4">
                        <div class="text-center p-3 bg-light rounded border">
                            <img src="<?php echo SITE_URL . '/' . htmlspecialchars($theme['logo_path']); ?>" alt="Logotipas" style="max-height: 80px; max-width: <?php echo htmlspecialchars($theme['logo_width']); ?>;">
                        </div>
                        <div class="flex-grow-1">
                            <label class="form-label fw-bold small">Įkelti naują logotipą (PNG, SVG, WEBP)</label>
                            <input type="file" class="form-control form-control-sm mb-2" name="logo_file" accept=".png,.jpg,.jpeg,.svg,.webp">
                            <div class="d-flex align-items-center gap-2">
                                <label class="form-label small mb-0 w-100 text-muted">Maksimalus plotis:</label>
                                <input type="text" class="form-control form-control-sm" name="logo_width" value="<?php echo htmlspecialchars($theme['logo_width']); ?>">
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