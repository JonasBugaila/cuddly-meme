<?php
/**
 * Prizininkų ataskaitos puslapis su spausdinimo funkcija
 * * Šis failas atvaizduoja prizininkų ataskaitas pagal olimpiadas ir mokyklas
 * ir leidžia eksportuoti diplomus į PDF (po vieną arba masiškai)
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname ar vartotojas prisijungęs ir turi administratoriaus teises
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
} elseif (!is_admin()) {
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL);
}

// Nustatome filtrus
$olympiad = isset($_GET['olympiad']) ? sanitize_input($_GET['olympiad']) : '';
$school = isset($_GET['school']) ? sanitize_input($_GET['school']) : '';
$print_mode = isset($_GET['print']) && $_GET['print'] == '1';

// Gauname olimpiadų sąrašą
$sql = "SELECT DISTINCT konkurso_pav FROM konkursai ORDER BY konkurso_pav ASC";
$stmt = db_query($sql);
$olympiads = $stmt ? db_get_results($stmt) : [];

// Gauname mokyklų sąrašą
$sql = "SELECT DISTINCT pavadinimas FROM mokyklos ORDER BY pavadinimas ASC";
$stmt = db_query($sql);
$schools = $stmt ? db_get_results($stmt) : [];

// Gauname prizininkų sąrašą
$where = [];
$params = [];
$types = '';

if (!empty($olympiad)) {
    $where[] = "d.konkurso_pav = ?";
    $params[] = $olympiad;
    $types .= 's';
}

if (!empty($school)) {
    $where[] = "d.var_mokykla = ?";
    $params[] = $school;
    $types .= 's';
}

$where[] = "d.Vieta IN ('I', 'II', 'III', 'laureat.')";

$where_clause = 'WHERE ' . implode(' AND ', $where);

$sql = "SELECT d.*, m.pavadinimas AS mokykla_pilna 
        FROM dalyviai d 
        LEFT JOIN mokyklos m ON d.var_mokykla = m.pavadinimas 
        $where_clause 
        ORDER BY d.konkurso_pav, FIELD(d.Vieta, 'I','II','III','laureat.'), CAST(d.Balai AS UNSIGNED) DESC";

$stmt = db_query($sql, $params, $types);
$winners = $stmt ? db_get_results($stmt) : [];

// Formatuojame vietą
function formatPlace($place) {
    $places = [
        'I' => 'I vieta',
        'II' => 'II vieta',
        'III' => 'III vieta',
        'laureat.' => 'Laureatas'
    ];
    return $places[$place] ?? htmlspecialchars($place);
}
?>

<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prizininkai | Olimpiadų sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print, .btn, .card-header { display: none !important; }
            body { background: white; }
            .table { font-size: 12px; }
            .table th, .table td { padding: 4px 6px; }
        }
        .table th, .table td { vertical-align: middle; }
        .btn-sm i { margin-right: 4px; }
        .card { box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
    </style>
</head>
<body class="<?php echo $print_mode ? 'bg-white' : 'bg-light'; ?>">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center no-print">
                        <h5 class="mb-0">
                            <i class="fas fa-trophy"></i> Prizininkų ataskaita
                        </h5>
                        <div>
                            <a href="?print=1&olympiad=<?php echo urlencode($olympiad); ?>&school=<?php echo urlencode($school); ?>" 
                               class="btn btn-light btn-sm" title="Spausdinti">
                                <i class="fas fa-print"></i> Spausdinti
                            </a>
                            <a href="<?php echo SITE_URL; ?>" class="btn btn-light btn-sm">
                                <i class="fas fa-times"></i> Uždaryti
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-3 mb-4 no-print">
                            <div class="col-md-4">
                                <label class="form-label">Olimpiada</label>
                                <select name="olympiad" class="form-select">
                                    <option value="">Visos olimpiados</option>
                                    <?php foreach ($olympiads as $o): ?>
                                        <option value="<?php echo htmlspecialchars($o['konkurso_pav']); ?>" 
                                            <?php echo $olympiad === $o['konkurso_pav'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($o['konkurso_pav']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mokykla</label>
                                <select name="school" class="form-select">
                                    <option value="">Visos mokyklos</option>
                                    <?php foreach ($schools as $s): ?>
                                        <option value="<?php echo htmlspecialchars($s['pavadinimas']); ?>" 
                                            <?php echo $school === $s['pavadinimas'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($s['pavadinimas']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-filter"></i> Filtruoti
                                </button>
                                <a href="?" class="btn btn-secondary">
                                    <i class="fas fa-undo"></i> Atstatyti
                                </a>
                            </div>
                        </form>

                        <?php if (!empty($winners) && !$print_mode): ?>
                            <div class="mb-3 text-end no-print">
                                <a href="<?php echo SITE_URL; ?>/modules/reports/export_diplomas.php?konkursas=<?php echo urlencode($olympiad ?: 'Visos'); ?>"
                                   class="btn btn-success">
                                    <i class="fas fa-download"></i> Eksportuoti visus diplomus (ZIP)
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($winners)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Olimpiada</th>
                                            <th>Vardas</th>
                                            <th>Pavardė</th>
                                            <th>Klasė</th>
                                            <th>Mokykla</th>
                                            <th>Mokytojas</th>
                                            <th>Balai</th>
                                            <th>Vieta</th>
                                            <th class="text-center no-print">Diplomas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($winners as $winner): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($winner['konkurso_pav']); ?></td>
                                                <td><?php echo htmlspecialchars($winner['1_vardas']); ?></td>
                                                <td><?php echo htmlspecialchars($winner['1_pavarde']); ?></td>
                                                <td><?php echo htmlspecialchars($winner['1_klase']); ?></td>
                                                <td><?php echo htmlspecialchars($winner['mokykla_pilna'] ?? $winner['var_mokykla']); ?></td>
                                                <td><?php echo htmlspecialchars($winner['1_mok'] ?? '-'); ?></td>
                                                <td><strong><?php echo htmlspecialchars($winner['Balai']); ?></strong></td>
                                                <td>
                                                    <span class="badge bg-warning text-dark">
                                                        <?php echo formatPlace($winner['Vieta']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-center no-print">
                                                    <a href="<?php echo SITE_URL; ?>/modules/reports/diplomas.php?id=<?php echo $winner['reg_id']; ?>"
                                                       target="_blank"
                                                       class="btn btn-sm btn-warning"
                                                       title="Atsisiųsti diplomą PDF formatu">
                                                        <i class="fas fa-file-pdf"></i> PDF
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle"></i>
                                <p class="mb-0">Nėra prizininkų, atitinkančių pasirinktus filtrus.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($print_mode): ?>
        <script>
            window.onload = function() {
                window.print();
            };
        </script>
    <?php else: ?>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php endif; ?>
</body>
</html>