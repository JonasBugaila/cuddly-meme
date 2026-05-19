<?php
/**
 * Diplomo spausdinimas
 * Naudojimas: diplomas.php?id=123
 */

$root = dirname(dirname(dirname(__FILE__)));
require_once $root . '/config/config.php';
require_once $root . '/config/db_connect.php';
require_once $root . '/config/functions.php';

if (!is_logged_in()) {
    die('Turite prisijungti');
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Neteisingas ID');
}

$dalyvis_id = (int)$_GET['id'];

// === TEISINGAS SQL – NAUDOJAME konkurso_pav TEKSTĄ ===
$sql = "SELECT 
            d.*, 
            d.konkurso_pav,
            m.pavadinimas AS mokykla
        FROM dalyviai d 
        LEFT JOIN mokyklos m ON d.var_mokykla = m.pavadinimas 
        WHERE d.reg_id = ?";

$stmt = db_query($sql, [$dalyvis_id], 'i');
$dalyvis = db_get_row($stmt);

if (!$dalyvis) {
    die('Dalyvis nerastas');
}

// === Vietos formatavimas ===
$vietos = [
    'I' => 'I vieta',
    'II' => 'II vieta',
    'III' => 'III vieta',
    'laureat.' => 'Laureatas'
];
$vietos_tekstas = $vietos[$dalyvis['Vieta']] ?? 'Dalyvis';

// === Data ===
$data = $dalyvis['pil_data'] ?? date('Y-m-d');
$data_tekstas = date('Y', strtotime($data)) . ' m. ' . date('m', strtotime($data)) . ' mėn. ' . date('d', strtotime($data)) . ' d.';
?>

<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <title>Diplomas – <?php echo htmlspecialchars($dalyvis['1_vardas'] . ' ' . $dalyvis['1_pavarde']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        @page { margin: 0; size: A4; }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 0;
            background: #f4f4f4;
            font-family: 'Times New Roman', serif;
        }
        .diplomas {
            width: 210mm;
            height: 297mm;
            margin: 20mm auto;
            background: white;
            box-shadow: 0 0 25px rgba(0,0,0,0.15);
            position: relative;
            overflow: hidden;
            page-break-after: always;
        }
        .bg-pattern {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect width="100" height="100" fill="%23ffffff"/><path d="M0,50 Q25,30 50,50 T100,50" stroke="%23f0f0f0" stroke-width="2" fill="none"/><path d="M0,70 Q25,50 50,70 T100,70" stroke="%23e9ecef" stroke-width="1" fill="none"/></svg>') repeat;
            opacity: 0.4;
        }
        .content {
            position: relative;
            z-index: 2;
            padding: 60px 80px;
            text-align: center;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .logo {
            width: 90px; height: 90px;
            margin: 0 auto 25px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 28px;
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
        }
        h1 {
            font-size: 38px;
            color: #1a1a1a;
            margin: 25px 0;
            font-weight: 300;
            letter-spacing: 1px;
        }
        .subtitle {
            font-size: 21px;
            color: #555;
            margin-bottom: 45px;
            font-style: italic;
        }
        .laureatas {
            font-size: 52px;
            font-weight: bold;
            color: #d4af37;
            margin: 35px 0;
            text-shadow: 2px 2px 5px rgba(0,0,0,0.1);
            background: linear-gradient(45deg, #d4af37, #ffd700);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .vardas {
            font-size: 44px;
            color: #2c3e50;
            margin: 25px 0;
            font-weight: bold;
        }
        .mokykla {
            font-size: 26px;
            color: #444;
            margin: 18px 0;
        }
        .olimpiada {
            font-size: 24px;
            color: #666;
            margin: 35px 0;
            font-style: italic;
        }
        .data {
            font-size: 19px;
            color: #888;
            margin-top: 60px;
        }
        .footer {
            position: absolute;
            bottom: 35px;
            left: 0; right: 0;
            text-align: center;
            font-size: 14px;
            color: #bbb;
        }
        .no-print { text-align: center; margin: 20px; }
        @media print {
            body, .diplomas { background: white !important; margin: 0 !important; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="diplomas">
        <div class="bg-pattern"></div>
        <div class="content">
            <div class="logo">OL</div>
            <h1>Diplomas</h1>
            <p class="subtitle">Už pasiekimus respublikinėje olimpiadoje</p>
            
            <div class="laureatas"><?php echo $vietos_tekstas; ?></div>
            
            <div class="vardas">
                <?php echo htmlspecialchars($dalyvis['1_vardas'] . ' ' . $dalyvis['1_pavarde']); ?>
            </div>
            
            <div class="mokykla">
                <?php echo htmlspecialchars($dalyvis['mokykla'] ?? 'Mokykla nenurodyta'); ?>
            </div>
            
            <div class="olimpiada">
                „<?php echo htmlspecialchars($dalyvis['konkurso_pav'] ?? 'Olimpiada'); ?>“
            </div>
            
            <div class="data">
                <?php echo $data_tekstas; ?>
            </div>
        </div>
        
    </div>

    <div class="no-print">
        <button onclick="window.print()" class="btn btn-success btn-lg">Spausdinti</button>
        <a href="javascript:window.close()" class="btn btn-secondary btn-lg ms-2">Uždaryti</a>
    </div>
</body>
</html>