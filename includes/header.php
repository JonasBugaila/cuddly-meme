<?php
/**
 * Antraštės failas
 * 
 * Šis failas įtraukiamas į visus puslapius ir atvaizduoja puslapio antraštę
 */

require_once dirname(dirname(__FILE__)) . '/config/config.php';
require_once dirname(dirname(__FILE__)) . '/config/db_connect.php';
require_once dirname(dirname(__FILE__)) . '/config/functions.php';

start_session();
?>
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
	<!-- BOOTSTRAP CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/bootstrap.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
	<script src="/assets/js/bootstrap.bundle.min.js"></script>

<?php 
// Užkrauname dinaminę temą
$system_theme = get_system_theme(); 

// Funkcija RGB konversijai (reikalinga permatomumui ir šešėliams)
function hexToRgbStr($hex) {
    $hex = str_replace('#', '', $hex);
    if(strlen($hex) == 3) {
        $r = hexdec(substr($hex,0,1).substr($hex,0,1));
        $g = hexdec(substr($hex,1,1).substr($hex,1,1));
        $b = hexdec(substr($hex,2,1).substr($hex,2,1));
    } else {
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
    }
    return "$r, $g, $b";
}
?>
<style>
:root {
    /* ========================================================= */
    /* 1. JŪSŲ ASMENINIO style.css KINTAMŲJŲ PERRAŠYMAS          */
    /* ========================================================= */
    --primary-color: <?php echo $system_theme['primary_color']; ?>;
    --secondary-color: <?php echo $system_theme['secondary_color']; ?>;
    --background-color: <?php echo $system_theme['body_bg']; ?>;
    --text-color: <?php echo $system_theme['text_color']; ?>;
    --success-color: <?php echo $system_theme['success_color']; ?>;
    --error-color: <?php echo $system_theme['danger_color']; ?>;
    --warning-color: <?php echo $system_theme['warning_color']; ?>;
    --info-color: <?php echo $system_theme['info_color']; ?>;
    /* Sukuriame šviesų akcentą kortelėms ir hover efektams iš pagrindinės spalvos */
    --accent-color: rgba(<?php echo hexToRgbStr($system_theme['primary_color']); ?>, 0.08);

    /* ========================================================= */
    /* 2. BOOTSTRAP 5 IR SISTEMOS BAZINIAI KINTAMIEJI            */
    /* ========================================================= */
    --bs-primary: <?php echo $system_theme['primary_color']; ?>;
    --bs-primary-rgb: <?php echo hexToRgbStr($system_theme['primary_color']); ?>;
    --bs-secondary: <?php echo $system_theme['secondary_color']; ?>;
    --bs-secondary-rgb: <?php echo hexToRgbStr($system_theme['secondary_color']); ?>;
    --bs-success: <?php echo $system_theme['success_color']; ?>;
    --bs-success-rgb: <?php echo hexToRgbStr($system_theme['success_color']); ?>;
    --bs-info: <?php echo $system_theme['info_color']; ?>;
    --bs-info-rgb: <?php echo hexToRgbStr($system_theme['info_color']); ?>;
    --bs-warning: <?php echo $system_theme['warning_color']; ?>;
    --bs-warning-rgb: <?php echo hexToRgbStr($system_theme['warning_color']); ?>;
    --bs-danger: <?php echo $system_theme['danger_color']; ?>;
    --bs-danger-rgb: <?php echo hexToRgbStr($system_theme['danger_color']); ?>;

    --bs-body-bg: <?php echo $system_theme['body_bg']; ?>;
    --bs-body-color: <?php echo $system_theme['text_color']; ?>;

    --theme-topbar-bg: <?php echo $system_theme['topbar_bg']; ?>;
    --theme-topbar-text: <?php echo $system_theme['topbar_text']; ?>;
    --theme-topbar-hover: <?php echo $system_theme['topbar_hover']; ?>;

    --theme-sidebar-bg: <?php echo $system_theme['sidebar_bg']; ?>;
    --theme-sidebar-text: <?php echo $system_theme['sidebar_text']; ?>;
    --theme-sidebar-hover-bg: <?php echo $system_theme['sidebar_hover_bg']; ?>;
    --theme-sidebar-active-bg: <?php echo $system_theme['sidebar_active_bg']; ?>;
    --theme-sidebar-active-text: <?php echo $system_theme['sidebar_active_text']; ?>;

    --theme-card-bg: <?php echo $system_theme['card_bg']; ?>;
    --theme-card-header: <?php echo $system_theme['card_header_bg']; ?>;
    --theme-card-border: <?php echo $system_theme['card_border']; ?>;
    --theme-table-header-bg: <?php echo $system_theme['table_header_bg']; ?>;
    --theme-table-header-text: <?php echo $system_theme['table_header_text']; ?>;

    --theme-footer-bg: <?php echo $system_theme['footer_bg']; ?>;
    --theme-footer-text: <?php echo $system_theme['footer_text']; ?>;
}

/* ================= 1. BENDRA (KŪNAS IR TEKSTAS) ================= */
body, #wrapper { background-color: var(--theme-body-bg) !important; color: var(--theme-text) !important; }
.text-gray-800, .text-gray-900, h1, h2, h3, h4, h5, h6 { color: var(--theme-text) !important; }

/* ================= 2. VIRŠUTINĖ JUOSTA (TOPBAR / HEADER / NAV) ================= */
/* Pridėta palaikymas seniesiems .header ir .nav iš style.css, naikinami seni gradientai */
nav.topbar, .navbar, .header, .nav { 
    background-color: var(--theme-topbar-bg) !important; 
    background-image: none !important; /* Naikina style.css esantį gradientą */
    border-bottom: 1px solid rgba(0,0,0,0.05) !important; 
}
nav.topbar .nav-link, nav.topbar .nav-link i, nav.topbar .nav-link span, .topbar .dropdown-toggle, .nav-link { color: var(--theme-topbar-text) !important; }
nav.topbar .nav-link:hover, nav.topbar .nav-link:hover i, nav.topbar .nav-link:hover span, .nav-link:hover { color: var(--theme-topbar-hover) !important; background-color: transparent !important; }
.nav-link.active { color: var(--theme-topbar-hover) !important; border-bottom: 2px solid var(--theme-topbar-hover) !important; }

/* ================= 3. ŠONINIS MENIU (SIDEBAR) ================= */
.sidebar, .sidebar.bg-gradient-primary { background: var(--theme-sidebar-bg) !important; background-image: none !important;}
.sidebar .nav-item .nav-link, .sidebar .nav-item .nav-link i, .sidebar-brand-text { color: var(--theme-sidebar-text) !important; }
.sidebar .nav-item .nav-link:hover, .sidebar .nav-item .nav-link:hover i { background-color: var(--theme-sidebar-hover-bg) !important; color: var(--theme-sidebar-text) !important; opacity: 1 !important;}
.sidebar .nav-item.active .nav-link { background-color: var(--theme-sidebar-active-bg) !important; color: var(--theme-sidebar-active-text) !important; }
.sidebar .nav-item.active .nav-link i { color: var(--theme-sidebar-active-text) !important; }

/* ================= 4. KORTELĖS IR ELEMENTAI ================= */
.card, .list-group-item { background-color: var(--theme-card-bg) !important; border-color: var(--theme-card-border) !important; color: var(--theme-text) !important; }
/* style.css turėjo savo card-header gradientus - juos išjungiame ir priverčiame naudoti spalvą iš nustatymų */
.card-header { 
    background-color: var(--theme-card-header) !important; 
    background-image: none !important; 
    color: var(--theme-text) !important;
    border-bottom: 1px solid var(--theme-card-border) !important; 
}
.border-left-primary, .border-primary { border-left-color: var(--bs-primary) !important; border-color: var(--bs-primary) !important; }
.border-left-success, .border-success { border-left-color: var(--bs-success) !important; border-color: var(--bs-success) !important; }
.border-left-info, .border-info { border-left-color: var(--bs-info) !important; border-color: var(--bs-info) !important; }
.border-left-warning, .border-warning { border-left-color: var(--bs-warning) !important; border-color: var(--bs-warning) !important; }

/* ================= 5. LENTELĖS (TABLES) ================= */
.table { color: var(--theme-text) !important; }
.table thead th, .table-light th, .table-light { background-color: var(--theme-table-header-bg) !important; color: var(--theme-table-header-text) !important; border-bottom: 2px solid var(--theme-card-border) !important; }
.table td, .table th { border-color: var(--theme-card-border); }
.pagination .page-item.active .page-link { background-color: var(--bs-primary) !important; border-color: var(--bs-primary) !important; color: #fff !important; }
.pagination .page-link { color: var(--bs-primary); }

/* ================= 6. MYGTUKAI ================= */
.btn-primary { background-color: var(--bs-primary) !important; border-color: var(--bs-primary) !important; color: #fff !important; }
.btn-primary:hover { filter: brightness(85%); background-color: var(--bs-primary) !important; }
.btn-primary:focus { box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), .5) !important; }

.btn-secondary { background-color: var(--bs-secondary) !important; border-color: var(--bs-secondary) !important; color: #fff !important; }
.btn-secondary:hover { filter: brightness(85%); background-color: var(--bs-secondary) !important; }

.btn-success { background-color: var(--bs-success) !important; border-color: var(--bs-success) !important; color: #fff !important; }
.btn-success:hover { filter: brightness(85%); background-color: var(--bs-success) !important; }

.btn-info { background-color: var(--bs-info) !important; border-color: var(--bs-info) !important; color: #fff !important; }
.btn-info:hover { filter: brightness(85%); background-color: var(--bs-info) !important; }

.btn-warning { background-color: var(--bs-warning) !important; border-color: var(--bs-warning) !important; color: #000 !important; }
.btn-warning:hover { filter: brightness(85%); background-color: var(--bs-warning) !important; }

.btn-danger { background-color: var(--bs-danger) !important; border-color: var(--bs-danger) !important; color: #fff !important; }
.btn-danger:hover { filter: brightness(85%); background-color: var(--bs-danger) !important; }

.btn-outline-primary { color: var(--bs-primary) !important; border-color: var(--bs-primary) !important; }
.btn-outline-primary:hover { background-color: var(--bs-primary) !important; color: #fff !important; }

/* Fono, teksto ir ženklelių (Badges) helper klasės */
.bg-primary, .badge.bg-primary { background-color: var(--bs-primary) !important; color: #fff !important; }
.bg-success, .badge.bg-success { background-color: var(--bs-success) !important; color: #fff !important; }
.bg-warning, .badge.bg-warning { background-color: var(--bs-warning) !important; color: #000 !important; }
.text-primary { color: var(--bs-primary) !important; }
.text-success { color: var(--bs-success) !important; }

/* Iššokantys pranešimai (Alerts) su permatomumu */
.alert-primary { background-color: rgba(var(--bs-primary-rgb), 0.15) !important; border-color: rgba(var(--bs-primary-rgb), 0.3) !important; color: var(--bs-primary) !important; }
.alert-success { background-color: rgba(var(--bs-success-rgb), 0.15) !important; border-color: rgba(var(--bs-success-rgb), 0.3) !important; color: var(--bs-success) !important; }
.alert-danger, .alert-error { background-color: rgba(var(--bs-danger-rgb), 0.15) !important; border-color: rgba(var(--bs-danger-rgb), 0.3) !important; color: var(--bs-danger) !important; }

/* ================= 7. PORAŠTĖ IR LOGOTIPAS ================= */
footer, .footer, .sticky-footer { background-color: var(--theme-footer-bg) !important; color: var(--theme-footer-text) !important; border-top: 1px solid var(--theme-card-border) !important;}
.system-logo { max-width: <?php echo htmlspecialchars($system_theme['logo_width']); ?> !important; height: auto; }
.system-logo-img { content: url('<?php echo SITE_URL . "/" . $system_theme['logo_path']; ?>') !important; }
</style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
				<div class="system-logo system-logo-img">
                    <img src="<?php echo SITE_URL; ?>/logotipas.jpg" alt="Olimpiadų sistema logotipas">
					</div>
                </div>
                <div class="user-info">
                    <?php if (is_logged_in()): ?>
                        <span>Sveiki, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        <a href="<?php echo SITE_URL; ?>/modules/auth/logout.php" class="btn btn-secondary">Atsijungti</a>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/modules/auth/login.php" class="btn btn-secondary">Prisijungti</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    
    <nav class="nav">
        <div class="container">
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="<?php echo SITE_URL; ?>" class="nav-link <?php echo (current_url() == SITE_URL || current_url() == SITE_URL . '/') ? 'active' : ''; ?>">Pradžia</a>
                </li>
                <?php if (is_logged_in()): ?>
                    <li class="nav-item">
                        <a href="<?php echo SITE_URL; ?>/modules/olympiads/index.php" class="nav-link <?php echo strpos(current_url(), '/modules/olympiads/') !== false ? 'active' : ''; ?>">Olimpiados</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo SITE_URL; ?>/modules/registration/index.php" class="nav-link <?php echo strpos(current_url(), '/modules/registration/') !== false ? 'active' : ''; ?>">Registracija</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo SITE_URL; ?>/modules/results/index.php" class="nav-link <?php echo strpos(current_url(), '/modules/results/') !== false ? 'active' : ''; ?>">Rezultatai</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo SITE_URL; ?>/modules/reports/index.php" class="nav-link <?php echo strpos(current_url(), '/modules/reports/') !== false ? 'active' : ''; ?>">Ataskaitos</a>
                    </li>
                    <?php if (is_admin()): ?>
                        <li class="nav-item">
                            <a href="<?php echo SITE_URL; ?>/modules/admin/index.php" class="nav-link <?php echo strpos(current_url(), '/modules/admin/') !== false ? 'active' : ''; ?>">Administravimas</a>
                        </li>
						 <li class="nav-item">
                        <a href="<?php echo SITE_URL; ?>/modules/reports/kalendorius.php" class="nav-link <?php echo strpos(current_url(), '/modules/reports/') !== false ? 'active' : ''; ?>">Kalendorius</a>
                    </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    
    <main class="main">
        <div class="container">
            <?php display_message(); ?>