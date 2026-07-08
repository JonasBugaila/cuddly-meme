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
    return $r . ', ' . $g . ', ' . $b;
}
?>
<style>
:root {
    --bs-primary: <?php echo $system_theme['primary_color']; ?>;
    --bs-primary-rgb: <?php echo hexToRgbStr($system_theme['primary_color']); ?>;
    --bs-success: <?php echo $system_theme['success_color']; ?>;
    --bs-success-rgb: <?php echo hexToRgbStr($system_theme['success_color']); ?>;
    --bs-warning: <?php echo $system_theme['warning_color']; ?>;
    --bs-warning-rgb: <?php echo hexToRgbStr($system_theme['warning_color']); ?>;
    --bs-info: <?php echo $system_theme['info_color']; ?>;
    --bs-info-rgb: <?php echo hexToRgbStr($system_theme['info_color']); ?>;
    --bs-danger: <?php echo $system_theme['danger_color']; ?>;
    --bs-danger-rgb: <?php echo hexToRgbStr($system_theme['danger_color']); ?>;

    --bs-body-bg: <?php echo $system_theme['body_bg']; ?>;
    --bs-body-color: <?php echo $system_theme['text_color']; ?>;
    
    --theme-header-bg: <?php echo $system_theme['header_bg']; ?>;
    --theme-header-text: <?php echo $system_theme['header_text']; ?>;
    
    --theme-sidebar-bg: <?php echo $system_theme['sidebar_bg']; ?>;
    --theme-sidebar-text: <?php echo $system_theme['sidebar_text']; ?>;
    --theme-sidebar-hover: <?php echo $system_theme['sidebar_hover']; ?>;
    
    --theme-card-bg: <?php echo $system_theme['card_bg']; ?>;
    --theme-card-header: <?php echo $system_theme['card_header_bg']; ?>;
    
    --theme-footer-bg: <?php echo $system_theme['footer_bg']; ?>;
    --theme-footer-text: <?php echo $system_theme['footer_text']; ?>;
}

body { background-color: var(--bs-body-bg) !important; color: var(--bs-body-color) !important; }

/* ================= 1. MYGTUKAI IR JŲ UŽVEDIMO (HOVER) EFEKTAI ================= */
.btn-primary { background-color: var(--bs-primary) !important; border-color: var(--bs-primary) !important; color: #fff !important; }
.btn-primary:hover, .btn-primary:focus { filter: brightness(85%); color: #fff !important; }

.btn-success { background-color: var(--bs-success) !important; border-color: var(--bs-success) !important; color: #fff !important; }
.btn-success:hover, .btn-success:focus { filter: brightness(85%); color: #fff !important; }

.btn-warning { background-color: var(--bs-warning) !important; border-color: var(--bs-warning) !important; color: #000 !important; }
.btn-warning:hover, .btn-warning:focus { filter: brightness(85%); color: #000 !important; }

.btn-info { background-color: var(--bs-info) !important; border-color: var(--bs-info) !important; color: #fff !important; }
.btn-info:hover, .btn-info:focus { filter: brightness(85%); color: #fff !important; }

.btn-danger { background-color: var(--bs-danger) !important; border-color: var(--bs-danger) !important; color: #fff !important; }
.btn-danger:hover, .btn-danger:focus { filter: brightness(85%); color: #fff !important; }

/* Mygtukai su rėmeliu (Outline) */
.btn-outline-primary { color: var(--bs-primary) !important; border-color: var(--bs-primary) !important; }
.btn-outline-primary:hover { background-color: var(--bs-primary) !important; color: #fff !important; }
.btn-outline-success { color: var(--bs-success) !important; border-color: var(--bs-success) !important; }
.btn-outline-success:hover { background-color: var(--bs-success) !important; color: #fff !important; }
.btn-outline-info { color: var(--bs-info) !important; border-color: var(--bs-info) !important; }
.btn-outline-info:hover { background-color: var(--bs-info) !important; color: #fff !important; }

/* ================= 2. VIRŠUTINĖ MENIU JUOSTA (HEADER / TOPBAR) ================= */
/* Agresyviai perrašome fono klases, tokias kaip .bg-white */
header, .navbar, .topbar, .navbar.bg-white, .topbar.bg-white { 
    background-color: var(--theme-header-bg) !important; 
    border-bottom: 1px solid rgba(0,0,0,0.1) !important;
}

/* Viršutinės juostos tekstas, nuorodos, ikonėlės ir dropdown meniu mygtukai */
.navbar .nav-link, 
.topbar .nav-link, 
.navbar-brand, 
.topbar .nav-item .nav-link span,
.topbar .nav-link i,
.topbar .dropdown-toggle,
.topbar .text-gray-600 { 
    color: var(--theme-header-text) !important; 
}

/* Užvedus pelę ant viršutinio meniu punktų */
.navbar .nav-link:hover, 
.topbar .nav-link:hover, 
.topbar .nav-link:hover i,
.topbar .nav-link:hover span { 
    color: var(--bs-primary) !important; /* Naudoja pagrindinę (Primary) spalvą */
    opacity: 0.8;
}

/* ================= 3. ŠONINIS MENIU (SIDEBAR) ================= */
aside, .sidebar, .main-menu, .sidebar.bg-gradient-primary { 
    background-color: var(--theme-sidebar-bg) !important;
    background-image: none !important; /* Išjungiame originalų Bootstrap gradientą */
}
.sidebar .nav-item .nav-link, .sidebar .nav-item .nav-link i, .sidebar-brand-text { 
    color: var(--theme-sidebar-text) !important; 
}
.sidebar .nav-item:hover .nav-link, .sidebar .nav-item.active .nav-link { 
    background-color: var(--theme-sidebar-hover) !important;
    color: #fff !important;
}

/* ================= 4. KORTELĖS IR SĄRAŠAI ================= */
.card, .list-group-item { background-color: var(--theme-card-bg) !important; color: var(--bs-body-color) !important; }
.card-header { background-color: var(--theme-card-header) !important; border-bottom: 1px solid rgba(0,0,0,0.1); }
.list-group-item { border-color: rgba(0,0,0,0.1) !important; }

/* ================= 5. APATINĖ JUOSTA (FOOTER) ================= */
footer, .footer, .sticky-footer { 
    background-color: var(--theme-footer-bg) !important; 
    color: var(--theme-footer-text) !important; 
}
.footer .copyright span { color: var(--theme-footer-text) !important; }

/* ================= 6. BENDRI ELEMENTAI (Fonas, Tekstas, Iššokantys langai) ================= */
.bg-primary, .badge.bg-primary { background-color: var(--bs-primary) !important; color: #fff !important; }
.bg-success, .badge.bg-success { background-color: var(--bs-success) !important; color: #fff !important; }
.bg-warning, .badge.bg-warning { background-color: var(--bs-warning) !important; color: #000 !important; }
.text-primary { color: var(--bs-primary) !important; }
.text-success { color: var(--bs-success) !important; }
.border-primary { border-color: var(--bs-primary) !important; }
.border-success { border-color: var(--bs-success) !important; }

.alert-primary { background-color: rgba(var(--bs-primary-rgb), 0.15) !important; border-color: rgba(var(--bs-primary-rgb), 0.3) !important; color: var(--bs-primary) !important; }
.alert-success { background-color: rgba(var(--bs-success-rgb), 0.15) !important; border-color: rgba(var(--bs-success-rgb), 0.3) !important; color: var(--bs-success) !important; }
.alert-danger  { background-color: rgba(var(--bs-danger-rgb), 0.15) !important; border-color: rgba(var(--bs-danger-rgb), 0.3) !important; color: var(--bs-danger) !important; }

.pagination .page-item.active .page-link { background-color: var(--bs-primary) !important; border-color: var(--bs-primary) !important; color: #fff !important; }
.pagination .page-link { color: var(--bs-primary); }

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
                        <span>Sveiki, <?php echo $_SESSION['user_name']; ?></span>
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