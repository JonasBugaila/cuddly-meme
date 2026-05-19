<?php
/**
 * Dalyvių sąrašo atvaizdavimas
 * 
 * Šis failas rodo klasių sąrašą, o pasirinkus klasę – jos mokinius, su filtravimu pagal vardą ir pavardę
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname, ar vartotojas prisijungęs ir turi administratoriaus teises
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
} elseif (!is_admin()) {
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL);
}

// Puslapiavimo nustatymai
$perPage = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

// Filtrų ir klasės gavimas
$selectedClass = isset($_GET['class']) ? sanitize_input($_GET['class']) : '';
$filterName = isset($_GET['filterName']) ? sanitize_input($_GET['filterName']) : '';
$filterSurname = isset($_GET['filterSurname']) ? sanitize_input($_GET['filterSurname']) : '';

// Gauname unikalias klases
$sqlClasses = "SELECT DISTINCT 1_klase FROM dalyviai ORDER BY 1_klase";
$stmtClasses = db_query($sqlClasses);
$classes = db_get_results($stmtClasses);

// Gauname bendrą filtruotų mokinių skaičių, jei pasirinkta klasė
$totalParticipants = 0;
if ($selectedClass) {
    $sqlCount = "SELECT COUNT(*) as total FROM dalyviai WHERE 1_klase = ?";
    $paramsCount = [$selectedClass];
    if (!empty($filterName) || !empty($filterSurname)) {
        $sqlCount .= " AND 1=1";
        if (!empty($filterName)) {
            $sqlCount .= " AND LOWER(1_vardas) LIKE ?";
            $paramsCount[] = "%" . strtolower($filterName) . "%";
        }
        if (!empty($filterSurname)) {
            $sqlCount .= " AND LOWER(1_pavarde) LIKE ?";
            $paramsCount[] = "%" . strtolower($filterSurname) . "%";
        }
    }
    $stmtCount = db_query($sqlCount, $paramsCount);
    $totalParticipants = db_get_row($stmtCount)['total'];
    $totalPages = ceil($totalParticipants / $perPage);
} else {
    $totalPages = 0;
}

// Gauname filtruotus mokinius su ribojimu, jei pasirinkta klasė
$participants = [];
if ($selectedClass) {
    $sql = "SELECT reg_id, konkurso_pav, var_mokykla, 1_vardas, 1_pavarde, 1_klase, 1_mok, 1_mok_kvali, 2_mok, 2_mok_kvali, Balai, Vieta 
            FROM dalyviai 
            WHERE 1_klase = ?";
    $params = [$selectedClass];
    if (!empty($filterName) || !empty($filterSurname)) {
        $sql .= " AND 1=1";
        if (!empty($filterName)) {
            $sql .= " AND LOWER(1_vardas) LIKE ?";
            $params[] = "%" . strtolower($filterName) . "%";
        }
        if (!empty($filterSurname)) {
            $sql .= " AND LOWER(1_pavarde) LIKE ?";
            $params[] = "%" . strtolower($filterSurname) . "%";
        }
    }
    $sql .= " ORDER BY 1_vardas, 1_pavarde LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;

    $stmt = db_query($sql, $params, str_repeat('s', count($params) - 2) . 'ii');
    $participants = db_get_results($stmt);
}

// Grupuojame mokinius pagal klases (jei yra)
$grouped_participants = [];
if ($participants) {
    foreach ($participants as $participant) {
        $grouped_participants[$participant['1_klase']][] = $participant;
    }
}

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h1>Dalyvių sąrašas</h1>
                </div>
                <div class="card-body">
                    <?php display_message(); ?>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="classSelect" class="form-label">Pasirinkite klasę</label>
                                <select class="form-control" id="classSelect" name="class">
                                    <option value="">-- Pasirinkite klasę --</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo htmlspecialchars($class['1_klase']); ?>" 
                                                <?php echo ($selectedClass === $class['1_klase']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($class['1_klase']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="filterName" class="form-label">Filtruoti pagal vardą</label>
                                <input type="text" class="form-control" id="filterName" name="filterName" value="<?php echo htmlspecialchars($filterName); ?>" placeholder="Įveskite vardą...">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="filterSurname" class="form-label">Filtruoti pagal pavardę</label>
                                <input type="text" class="form-control" id="filterSurname" name="filterSurname" value="<?php echo htmlspecialchars($filterSurname); ?>" placeholder="Įveskite pavardę...">
                            </div>
                        </div>
                    </div>
                    <form method="get" id="filterForm" style="display: none;">
                        <input type="text" name="class" value="<?php echo htmlspecialchars($selectedClass); ?>">
                        <input type="text" name="filterName" value="<?php echo htmlspecialchars($filterName); ?>">
                        <input type="text" name="filterSurname" value="<?php echo htmlspecialchars($filterSurname); ?>">
                        <input type="text" name="page" value="<?php echo $page; ?>">
                    </form>
                    <?php if ($selectedClass && $grouped_participants): ?>
                        <?php foreach ($grouped_participants as $klase => $participants_group): ?>
                            <h3 class="mt-4"><?php echo htmlspecialchars($klase); ?></h3>
                            <table class="table table-striped participant-table" data-klase="<?php echo htmlspecialchars($klase); ?>">
                                <thead>
                                    <tr>
                                        <th>Vardas</th>
                                        <th>Pavardė</th>
                                        <th>Konkurso pavadinimas</th>
                                        <th>Mokykla</th>
                                        <th>Pirmo mokytojo vardas, pavardė</th>
                                        <th>Pirmo mokytojo kvalifikacija</th>
                                        <th>Antro mokytojo vardas, pavardė</th>
                                        <th>Antro mokytojo kvalifikacija</th>
                                        <th>Balai</th>
                                        <th>Vieta</th>
                                        <th>Veiksmai</th>
                                    </tr>
                                </thead>
                                <tbody class="participant-rows">
                                    <?php foreach ($participants_group as $participant): ?>
                                        <tr data-vardas="<?php echo strtolower(htmlspecialchars($participant['1_vardas'])); ?>" data-pavarde="<?php echo strtolower(htmlspecialchars($participant['1_pavarde'])); ?>">
                                            <td><?php echo htmlspecialchars($participant['1_vardas']); ?></td>
                                            <td><?php echo htmlspecialchars($participant['1_pavarde']); ?></td>
                                            <td><?php echo htmlspecialchars($participant['konkurso_pav']); ?></td>
                                            <td><?php echo htmlspecialchars($participant['var_mokykla']); ?></td>
                                            <td><?php echo htmlspecialchars($participant['1_mok'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($participant['1_mok_kvali'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($participant['2_mok'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($participant['2_mok_kvali'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($participant['Balai'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($participant['Vieta'] ?? ''); ?></td>
                                            <td>
                                                <a href="participant_edit.php?id=<?php echo htmlspecialchars($participant['reg_id']); ?>" class="btn btn-primary btn-sm">Redaguoti</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endforeach; ?>
                        <!-- Puslapių navigacija -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Puslapių navigacija">
                                <ul class="pagination justify-content-center mt-3">
                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&class=<?php echo urlencode($selectedClass); ?>&filterName=<?php echo urlencode($filterName); ?>&filterSurname=<?php echo urlencode($filterSurname); ?>" tabindex="-1">Ankstesnis</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo ($page === $i) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&class=<?php echo urlencode($selectedClass); ?>&filterName=<?php echo urlencode($filterName); ?>&filterSurname=<?php echo urlencode($filterSurname); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&class=<?php echo urlencode($selectedClass); ?>&filterName=<?php echo urlencode($filterName); ?>&filterSurname=<?php echo urlencode($filterSurname); ?>">Kitas</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php elseif ($selectedClass): ?>
                        <p class="text-warning">Nėra mokinių šioje klasėje pagal nurodytus filtrus.</p>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>/modules/admin/" class="btn btn-secondary mt-3">Grįžti</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const classSelect = document.getElementById('classSelect');
    const filterName = document.getElementById('filterName');
    const filterSurname = document.getElementById('filterSurname');
    const filterForm = document.getElementById('filterForm');

    function submitFilter() {
        filterForm.querySelector('input[name="class"]').value = classSelect.value;
        filterForm.querySelector('input[name="filterName"]').value = filterName.value;
        filterForm.querySelector('input[name="filterSurname"]').value = filterSurname.value;
        filterForm.querySelector('input[name="page"]').value = 1; // Grįžti į pirmą puslapį
        filterForm.submit();
    }

    classSelect.addEventListener('change', submitFilter);
    filterName.addEventListener('input', submitFilter);
    filterSurname.addEventListener('input', submitFilter);
});
</script>

<?php
require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
?>