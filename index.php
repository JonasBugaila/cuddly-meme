<?php
/**
 * Olimpiadų Sistemos Pagrindinis Puslapis
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/config/functions.php';

$is_logged_in = is_logged_in();
$is_admin = is_admin();

// Gauname bendrą statistiką apžvalgos kortelėms (tik prisijungusiems)
$stats = [];
if ($is_logged_in) {
    $stats = [
        'olympiads' => db_get_row(db_query("SELECT COUNT(*) as cnt FROM konkursai"))['cnt'] ?? 0,
        'active' => db_get_row(db_query("SELECT COUNT(*) as cnt FROM konkursai WHERE status = 0"))['cnt'] ?? 0,
        'participants' => db_get_row(db_query("SELECT COUNT(*) as cnt FROM dalyviai"))['cnt'] ?? 0,
        'schools' => db_get_row(db_query("SELECT COUNT(*) as cnt FROM mokyklos"))['cnt'] ?? 0
    ];
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if (!$is_logged_in): ?>
    <div class="d-flex justify-content-center align-items-center" style="min-height: 70vh;">
        <div class="text-center p-5 bg-white shadow-sm rounded-4 border w-100" style="max-width: 600px;">
            <i class="fas fa-award fa-5x text-primary mb-4"></i>
            <h1 class="mb-3 fw-bold text-dark">Olimpiadų Sistema</h1>
            <p class="lead mb-5 text-muted">Prisijunkite prie sistemos arba peržiūrėkite viešą renginių kalendorių.</p>
            <div class="d-grid gap-3">
                <a href="<?php echo SITE_URL; ?>/modules/auth/login.php" class="btn btn-primary btn-lg fw-bold"><i class="fas fa-sign-in-alt me-2"></i> Prisijungti</a>
                <button type="button" class="btn btn-outline-success btn-lg fw-bold" data-bs-toggle="modal" data-bs-target="#globalCalendarModal"><i class="far fa-calendar-alt me-2"></i> Atidaryti kalendorių</button>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Sistemos apžvalga</h1>
        <span class="text-muted d-none d-md-inline">Prisijungta kaip: <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Vartotojau'); ?></strong></span>
    </div>

    <?php display_message(); ?>

    <div class="row mb-4 g-3">
        <div class="col-xl-3 col-md-6">
            <div class="stat-card">
                <div class="stat-card-icon is-primary"><i class="fas fa-trophy"></i></div>
                <div>
                    <div class="stat-card-label">Visos Olimpiados</div>
                    <div class="stat-card-value"><?php echo $stats['olympiads']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card">
                <div class="stat-card-icon is-success"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="stat-card-label">Aktyvios Olimpiados</div>
                    <div class="stat-card-value"><?php echo $stats['active']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card">
                <div class="stat-card-icon is-info"><i class="fas fa-users"></i></div>
                <div>
                    <div class="stat-card-label">Registruoti Dalyviai</div>
                    <div class="stat-card-value"><?php echo $stats['participants']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card">
                <div class="stat-card-icon is-warning"><i class="fas fa-school"></i></div>
                <div>
                    <div class="stat-card-label">Sistemos Mokyklos</div>
                    <div class="stat-card-value"><?php echo $stats['schools']; ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6 mb-4 mb-md-0">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h3 class="h5 mb-0"><i class="fas fa-play-circle text-success"></i> Šiuo metu vyksta</h3>
                    <span class="badge bg-success rounded-pill"><?php echo $stats['active']; ?></span>
                </div>
                <div class="card-body p-0" style="max-height: 350px; overflow-y: auto;">
                    <?php
                    $active_olympiads = db_get_results(db_query("SELECT * FROM konkursai WHERE status = 0 ORDER BY konk_id DESC"));
                    if (!empty($active_olympiads)):
                    ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($active_olympiads as $oly): ?>
                                <li class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <a href="<?php echo SITE_URL; ?>/modules/olympiads/view.php?id=<?php echo $oly['konk_id']; ?>" class="text-decoration-none text-dark fw-bold w-100">
                                        <?php echo htmlspecialchars($oly['konkurso_pav']); ?>
                                    </a>
                                    <a href="<?php echo SITE_URL; ?>/modules/olympiads/view.php?id=<?php echo $oly['konk_id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill">Valdyti</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-box-open fa-3x mb-3 text-light"></i>
                            <p class="mb-0">Šiuo metu nėra aktyvių olimpiadų.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white text-center border-top-0 pt-3">
                    <a href="<?php echo SITE_URL; ?>/modules/olympiads/active.php" class="btn btn-outline-success btn-sm w-100 fw-bold">
                        <i class="fas fa-list"></i> Visų aktyvių olimpiadų lentelė
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="row g-3">
                <div class="col-12">
                    <button type="button" class="btn btn-outline-success w-100 py-4 d-flex align-items-center text-start border-2 shadow-sm bg-white" data-bs-toggle="modal" data-bs-target="#globalCalendarModal">
                        <i class="far fa-calendar-alt fa-3x me-4 ms-3"></i> 
                        <div>
                            <h4 class="mb-1 fw-bold">Renginių kalendorius</h4>
                            <small class="text-dark opacity-75">Peržiūrėkite būsimas ir pasibaigusias olimpiadas laike</small>
                        </div>
                    </button>
                </div>
                <div class="col-12">
                    <a href="<?php echo SITE_URL; ?>/modules/olympiads/index.php" class="btn btn-outline-primary w-100 py-4 d-flex align-items-center text-start border-2 shadow-sm bg-white">
                        <i class="fas fa-archive fa-3x me-4 ms-3"></i> 
                        <div>
                            <h4 class="mb-1 fw-bold">Visų olimpiadų archyvas</h4>
                            <small class="text-dark opacity-75">Peržiūrėkite tiek aktyvias, tiek pasibaigusias olimpiadas sąraše</small>
                        </div>
                    </a>
                </div>
                <div class="col-12">
                    <a href="<?php echo SITE_URL; ?>/modules/reports/participants.php" class="btn btn-outline-info w-100 py-4 d-flex align-items-center text-start border-2 shadow-sm bg-white">
                        <i class="fas fa-users fa-3x me-4 ms-3"></i> 
                        <div>
                            <h4 class="mb-1 fw-bold">Visi sistemos dalyviai</h4>
                            <small class="text-dark opacity-75">Visų olimpiadų ir mokyklų dalyvių paieška bei filtravimas</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
.card-body::-webkit-scrollbar { width: 6px; }
.card-body::-webkit-scrollbar-track { background: #f8f9fa; }
.card-body::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
.card-body::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }
.border-start { border-left-width: 4px !important; }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>