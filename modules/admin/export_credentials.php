<?php
/**
 * Prisijungimo duomenų eksportavimas į PDF
 */
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';
require_once dirname(dirname(dirname(__FILE__))) . '/vendor/tcpdf/tcpdf.php';

start_session();

if (!is_logged_in() || !is_admin()) {
    die('Neturite teisių pasiekti šį failą.');
}

if (!isset($_SESSION['flash_credentials'])) {
    die('Klaida: Nėra duomenų spausdinimui arba jie jau buvo atspausdinti. Generuoti galima tik vieną kartą iškart po išsaugojimo.');
}

// Paimame duomenis ir išvalome iš sesijos (saugumo sumetimais slaptažodis nesaugomas!)
$cred = $_SESSION['flash_credentials'];
unset($_SESSION['flash_credentials']);

// Kuriame PDF (P - portretas, mm, A5 formatas, nes nereikia viso didelio A4 lapo)
$pdf = new TCPDF('P', 'mm', 'A5', true, 'UTF-8', false);
$pdf->SetCreator('Olimpiadų sistema');
$pdf->SetTitle('Prisijungimo duomenys - ' . $cred['vardas_pavarde']);

// Išjungiame standartines antraštes ir paraštes
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 20, 15);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->AddPage();

// Naudojame dejavusans šriftą (jis palaiko lietuviškas raides ir jau yra TCPDF bibliotekoje)
$pdf->SetFont('dejavusans', '', 11);

// HTML turinys PDF dokumentui
$html = '
<div style="text-align: center; font-family: dejavusans;">
    <h2 style="color: #0d6efd; margin-bottom: 5px;">Olimpiadų Sistema</h2>
    <h3 style="color: #333; margin-top: 0;">Prisijungimo Duomenys</h3>
    <hr style="border: 0; border-top: 1px solid #ccc; margin-bottom: 20px;">
    
    <p style="text-align: left; font-size: 12px;">Sveiki, <strong>' . htmlspecialchars($cred['vardas_pavarde']) . '</strong>,</p>
    <p style="text-align: left; font-size: 12px;">Jums sukurta (arba atnaujinta) paskyra Olimpiadų sistemoje. Prisijungimui naudokite šiuos duomenis:</p>
    
    <br>
    <table border="1" cellpadding="10" cellspacing="0" style="margin: 0 auto; width: 100%; border-color: #ddd;">
        <tr>
            <td style="width: 45%; background-color: #f8f9fa; color: #555;"><strong>Vartotojo ID:</strong><br><span style="font-size: 10px;">(Prisijungimo vardas)</span></td>
            <td style="width: 55%; font-family: monospace; font-size: 15px; font-weight: bold; text-align: center; vertical-align: middle;">' . htmlspecialchars($cred['vart_id']) . '</td>
        </tr>
        <tr>
            <td style="background-color: #f8f9fa; color: #555;"><strong>Laikinas Slaptažodis:</strong></td>
            <td style="font-family: monospace; font-size: 15px; font-weight: bold; color: #d63384; text-align: center; vertical-align: middle;">' . htmlspecialchars($cred['password']) . '</td>
        </tr>
    </table>
    <br>
    
    <div style="background-color: #fff3cd; color: #856404; padding: 10px; border: 1px solid #ffeeba; border-radius: 5px; text-align: left; font-size: 11px;">
        <strong><span style="font-family: dejavusans;">!</span> SVARBU:</strong> Prisijungę pirmą kartą, <u>privalėsite pasikeisti</u> šį laikiną slaptažodį į savo asmeninį.
    </div>
    
    <br><br>
    <p style="font-size: 11px;">Prisijungti galite adresu:<br><strong>' . SITE_URL . '</strong></p>
</div>
';

$pdf->writeHTML($html, true, false, true, false, '');

$filename = "Prisijungimo_Duomenys_" . preg_replace('/[^a-zA-Z0-9]/', '_', $cred['vart_id']) . ".pdf";

// 'I' atidaro PDF tiesiogiai naršyklėje (kur galima atspausdinti). Jei norite iškart siųsti kaip failą, naudokite 'D'
$pdf->Output($filename, 'I');
exit;