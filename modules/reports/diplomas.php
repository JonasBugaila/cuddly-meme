<?php
$root = dirname(dirname(dirname(__FILE__)));
require_once $root . '/config/config.php';
require_once $root . '/config/db_connect.php';
require_once $root . '/config/functions.php';
require_once __DIR__ . '/diploma_style.php';

if (!is_logged_in()) die('Turite prisijungti');
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) die('Neteisingas ID');

$dalyvis_id = (int)$_GET['id'];
$sql = "SELECT d.*, m.pavadinimas AS mokykla FROM dalyviai d LEFT JOIN mokyklos m ON d.var_mokykla = m.pavadinimas WHERE d.reg_id = ?";
$stmt = db_query($sql, [$dalyvis_id], 'i');
$dalyvis = db_get_row($stmt);
if (!$dalyvis) die('Dalyvis nerastas');

$year = date('Y', strtotime($dalyvis['pil_data']));
$dip_nr = sprintf("DIP-%d-%03d", $year, $dalyvis_id);
$vietos = ['I' => 'I vieta', 'II' => 'II vieta', 'III' => 'III vieta', 'laureat.' => 'Laureatas'];
$vietos_tekstas = $vietos[$dalyvis['Vieta']] ?? 'Dalyvis';
$data_tekstas = date('Y m. d d.', strtotime($dalyvis['pil_data']));
?>

<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <title>Diplomas – <?php echo htmlspecialchars($dalyvis['1_vardas'] . ' ' . $dalyvis['1_pavarde']); ?></title>
    <?php echo $style_css; ?>
</head>
<body>
    <div class="diplomas">
        <div class="bg-pattern"></div>
        <div class="dip-nr"><?php echo $dip_nr; ?></div>
        <div class="content">
            <?php echo $logo_svg; ?>
            <h1>Diplomas</h1>
            <p class="subtitle">Už pasiekimus respublikinėje olimpiadoje</p>
            <div class="laureatas"><?php echo $vietos_tekstas; ?></div>
            <div class="vardas"><?php echo htmlspecialchars($dalyvis['1_vardas'] . ' ' . $dalyvis['1_pavarde']); ?></div>
            <div class="mokykla"><?php echo htmlspecialchars($dalyvis['mokykla'] ?? $dalyvis['var_mokykla']); ?></div>
            <div class="olimpiada">„<?php echo htmlspecialchars($dalyvis['konkurso_pav']); ?>“</div>
            <div class="data"><?php echo $data_tekstas; ?></div>
        </div>
        <div class="footer">Olimpiadų valdymo sistema © <?php echo date('Y'); ?></div>
    </div>

    <div class="no-print">
        <button onclick="window.print()" class="btn btn-success btn-lg">Spausdinti</button>
        <a href="javascript:window.close()" class="btn btn-secondary btn-lg ms-2">Uždaryti</a>
    </div>
</body>
</html>