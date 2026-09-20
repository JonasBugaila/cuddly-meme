<?php
/**
 * ATASKAITŲ PDF VARIKLIS (TCPDF pagrindu)
 *
 * KODĖL TCPDF, O NE NARŠYKLĖS SPAUSDINIMAS:
 * Naršyklių spausdinimo CSS (@media print) neturi patikimo būdo uždėti
 * SKIRTINGĄ puslapio numerį kiekvieno fizinio lapo apačioje:
 *   - position:fixed - naršyklės sujungia visas kopijas į vieną, todėl
 *     visuose puslapiuose atsiranda ta pati (paskutinio puslapio) reikšmė;
 *   - flexbox + margin-top:auto - sutrikdo "page-break-after" veikimą;
 *   - normalus srautas - numeris atsiduria iškart po lentele, o ne lapo apačioje;
 *   - CSS counter(page) - neveikia, kai turinys generuojamas atskirais blokais.
 * Visa tai buvo išbandyta ir nė vienas variantas neveikė patikimai.
 *
 * TCPDF šią problemą sprendžia natūraliai: Footer() metodas iškviečiamas
 * KIEKVIENAM fiziniam lapui automatiškai, o getAliasNumPage()/getAliasNbPages()
 * įrašo tikrus "X iš Y" skaičius. Puslapių lūžiai taip pat automatiniai -
 * nebereikia jokio eilučių skaičiavimo ar duomenų dalijimo į "porcijas".
 */

/**
 * TCPDF klasė su ataskaitoms pritaikyta porašte.
 */
class OlympiadReportPDF extends TCPDF
{
    /** Ar rodyti puslapio numerį (visada true - besąlygiškai) */
    public $report_show_page_num = true;

    /**
     * Konstruktoriuje išjungiame TCPDF reklaminę nuorodą
     * ("Powered by TCPDF"), kuri kitaip atsiranda paskutinio lapo apačioje.
     * Savybė yra "protected", todėl ją galima nustatyti tik paveldėtoje
     * klasėje - tam ir skirtas šis konstruktorius.
     */
    public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4',
                                $unicode = true, $encoding = 'UTF-8', $diskcache = false)
    {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache);
        $this->tcpdflink = false;
    }

    /**
     * Poraštė - iškviečiama AUTOMATIŠKAI kiekvienam fiziniam lapui.
     * Puslapio numeris visada dešiniajame apatiniame kampe.
     */
    public function Footer()
    {
        if (!$this->report_show_page_num) {
            return;
        }

        // 15 mm nuo lapo apačios
        $this->SetY(-15);
        $this->SetFont('dejavusans', '', 9);
        $this->SetTextColor(100, 100, 100);

        // getAliasNumPage()/getAliasNbPages() - TCPDF vietos rezervavimo žymos,
        // kurias jis pats pakeičia tikrais skaičiais baigdamas dokumentą.
        $this->Cell(
            0, 10,
            $this->getAliasNumPage() . ' iš ' . $this->getAliasNbPages(),
            0, 0, 'R'
        );
    }

    /**
     * Antraštė išjungta - ataskaitos antraštė rašoma kaip įprastas turinys
     * (taip išvengiama TCPDF viršutinės paraštės derinimo su kintamo aukščio
     * HTML antrašte, kurią administratorius laisvai redaguoja).
     */
    public function Header()
    {
        // sąmoningai tuščia
    }
}

/**
 * Sukuria naują ataskaitos PDF pagal šablono nustatymus.
 *
 * $layout_key - 'protocol' | 'evaluation' | 'codes' | 'signature'
 */
function report_pdf_create($layout_key = 'protocol')
{
    $layout = get_print_layout($layout_key);

    $margin_t = (int)($layout['margin_t'] ?? 20);
    $margin_b = (int)($layout['margin_b'] ?? 20);
    $margin_l = (int)($layout['margin_l'] ?? 20);
    $margin_r = (int)($layout['margin_r'] ?? 20);
    $font_size = (int)($layout['font_size'] ?? 12);

    $pdf = new OlympiadReportPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Olimpiadų sistema');
    $pdf->SetAuthor('Olimpiadų sistema');

    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true); // poraštė (puslapio numeris) - visada įjungta

    $pdf->SetMargins($margin_l, $margin_t, $margin_r);
    // Apatinė paraštė + 12 mm vietos puslapio numeriui.
    // Pasiekus šią ribą TCPDF PATS perkelia turinį į naują lapą.
    $pdf->SetAutoPageBreak(true, $margin_b + 12);

    $pdf->SetFont('dejavusans', '', $font_size);

    return $pdf;
}

/**
 * Prideda vieną ataskaitos skyrių (antraštė + lentelė + poraštė).
 *
 * Kiekvienas skyrius pradedamas naujame lape. Jei lentelės duomenys netelpa,
 * TCPDF automatiškai perkelia juos į kitą lapą, o lentelės antraštės eilutė
 * (<thead>) kartojama kiekviename naujame lape. Poraštės elementai
 * (parašai, data) rašomi po PASKUTINE duomenų eilute ir puslapių
 * numeracijai jokios įtakos neturi.
 */
function report_pdf_add_section($pdf, $title, $institution, $headers, $data, $layout_key = 'protocol')
{
    $layout = get_print_layout($layout_key);
    $font_size = (int)($layout['font_size'] ?? 12);

    $search  = ['{{TITLE}}', '{{INSTITUTION}}', '{{DATE}}'];
    $replace = [htmlspecialchars((string)$title), htmlspecialchars((string)$institution), date('Y-m-d')];

    $header_html = str_replace($search, $replace, $layout['header_html'] ?? '');
    $footer_html = str_replace($search, $replace, $layout['footer_html'] ?? '');

    $pdf->AddPage();

    // 1. Ataskaitos antraštė (iš šablono)
    if (trim($header_html) !== '') {
        $pdf->writeHTML($header_html, true, false, true, false, '');
    }

    // 2. Duomenų lentelė. <thead> TCPDF automatiškai kartoja kiekviename
    //    naujame lape, kai lentelė persikelia per puslapio ribą.
    $tbl = '<table border="1" cellpadding="4" cellspacing="0" style="width:100%; font-size:' . $font_size . 'pt;">';
    $tbl .= '<thead><tr style="background-color:#eeeeee; font-weight:bold;">';
    foreach ($headers as $h) {
        $tbl .= '<th>' . htmlspecialchars((string)$h) . '</th>';
    }
    $tbl .= '</tr></thead><tbody>';
    foreach ($data as $row) {
        $tbl .= '<tr>';
        foreach ($row as $cell) {
            $tbl .= '<td>' . $cell . '</td>';
        }
        $tbl .= '</tr>';
    }
    $tbl .= '</tbody></table>';

    $pdf->writeHTML($tbl, true, false, true, false, '');

    // 3. Poraštės elementai (parašai, data) - iškart po paskutine duomenų
    //    eilute. Jei netelpa, TCPDF perkelia juos į kitą lapą; puslapių
    //    numeracija dėl to nesikeičia, nes ją tvarko Footer() metodas.
    if (trim($footer_html) !== '') {
        $pdf->Ln(6);
        $pdf->writeHTML($footer_html, true, false, true, false, '');
    }

    return $pdf;
}

/**
 * Atiduoda paruoštą PDF naršyklei peržiūrai (su spausdinimo dialogu).
 */
function report_pdf_output($pdf, $filename = 'ataskaita.pdf')
{
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
    if (ob_get_length()) {
        ob_end_clean();
    }
    $pdf->Output($filename, 'I');
    exit;
}

/**
 * Patogumo funkcija paprastoms, vieno skyriaus ataskaitoms -
 * sukuria, užpildo ir iškart atiduoda PDF.
 */
function report_pdf_simple($title, $institution, $headers, $data, $layout_key = 'protocol', $filename = null)
{
    $pdf = report_pdf_create($layout_key);
    report_pdf_add_section($pdf, $title, $institution, $headers, $data, $layout_key);
    report_pdf_output($pdf, $filename ?? ($title . '.pdf'));
}
