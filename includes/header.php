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
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <img src="<?php echo SITE_URL; ?>/logotipas.jpg" alt="Olimpiadų sistema logotipas" width="120" height="160">
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