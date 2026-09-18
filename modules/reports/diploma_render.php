<?php
/**
 * BENDRA DIPLOMŲ/PADĖKŲ ŠABLONO LOGIKA
 *
 * Šis failas sukurtas todėl, kad ta pati šablono užkrovimo ir kintamųjų
 * pakeitimo logika anksčiau buvo NUKOPIJUOTA dviejuose failuose
 * (modules/reports/diplomas.php ir modules/reports/export_diplomas.php).
 * Dėl to jie laikui bėgant išsiskyrė: vienas buvo pataisytas, kitas ne,
 * ir masinis eksportas generuodavo diplomus pagal seną šabloną be linksnių.
 *
 * Dabar abu failai naudoja TAS PAČIAS funkcijas - pataisymas vienoje vietoje
 * automatiškai galioja ir pavieniam, ir masiniam generavimui.
 */

/**
 * Užkrauna administratoriaus šabloną.
 *
 * Pirmenybė teikiama config/diploma_layout.json (tam failui, kurį realiai
 * redaguoja modules/admin/diploma_template.php). Senas
 * config/diploma_template.html naudojamas tik kaip atsarginis variantas.
 *
 * Grąžina masyvą: ['html' => ..., 'margins' => [...], 'bg' => kelias|null]
 */
function diploma_load_template($root) {
    $layout_file   = $root . '/config/diploma_layout.json';
    $template_file = $root . '/config/diploma_template.html';

    $result = [
        'html'    => '<div style="font-family:\'{FONT_NAME}\'; text-align:center;"><h2>{VARDAS_PAVARDE}</h2><p>{VIETA}</p></div>',
        'margins' => ['t' => 0, 'b' => 0, 'l' => 0, 'r' => 0],
        'bg'      => null,
    ];

    if (file_exists($layout_file)) {
        $layout = json_decode(file_get_contents($layout_file), true);
        if (is_array($layout) && !empty($layout['content_html'])) {
            $result['html'] = $layout['content_html'];
            $result['margins'] = [
                't' => (int)($layout['margin_t'] ?? 0),
                'b' => (int)($layout['margin_b'] ?? 0),
                'l' => (int)($layout['margin_l'] ?? 0),
                'r' => (int)($layout['margin_r'] ?? 0),
            ];
        }
    } elseif (file_exists($template_file)) {
        $result['html'] = file_get_contents($template_file);
    }

    // Firminis fonas per visą lapą (jei įkeltas į assets/img/)
    foreach (['diploma_bg.jpg', 'diploma_bg.jpeg', 'diploma_bg.png'] as $candidate) {
        if (file_exists($root . '/assets/img/' . $candidate)) {
            $result['bg'] = $root . '/assets/img/' . $candidate;
            break;
        }
    }

    return $result;
}

/**
 * Užpildo šabloną vieno dalyvio duomenimis - įskaitant automatiškai
 * sulinksniuotus kintamuosius (žr. config/functions.php lt_* funkcijas).
 *
 * $d - dalyvio eilutė iš DB (dalyviai lentelė, gali turėti ir "mokykla" lauką)
 */
function diploma_fill_template($html_template, $d, $font_name = 'dejavusans', $logo_svg = '') {
    $vardas_raw  = $d['1_vardas'] ?? '';
    $pavarde_raw = $d['1_pavarde'] ?? '';
    $mokykla_raw = $d['mokykla'] ?? $d['var_mokykla'] ?? '';
    $olimpiada   = $d['konkurso_pav'] ?? '';

    $gender = lt_guess_gender($vardas_raw, $pavarde_raw);

    $year   = date('Y', strtotime($d['pil_data'] ?? date('Y-m-d')));
    $dip_nr = sprintf("DIP-%d-%03d", $year, (int)($d['reg_id'] ?? 0));
    $data   = date('Y-m-d', strtotime($d['pil_data'] ?? date('Y-m-d')));

    $vietos    = ['I' => 'I vieta', 'II' => 'II vieta', 'III' => 'III vieta', 'laureat.' => 'Laureatas'];
    $vietos_ka = ['I' => 'I vietą', 'II' => 'II vietą', 'III' => 'III vietą', 'laureat.' => 'laureato vietą'];
    $vieta     = $vietos[$d['Vieta'] ?? ''] ?? 'Dalyvis';
    $vieta_ka  = $vietos_ka[$d['Vieta'] ?? ''] ?? 'dalyvio vietą';

    return str_replace(
        [
            '{FONT_NAME}', '{DIP_NR}', '{LOGO}', '{VIETA}',
            '{VARDAS_PAVARDE}', '{MOKYKLA}', '{OLIMPIADA}', '{DATA}',
            // Sulinksniuoti kintamieji:
            '{VARDAS_PAVARDE_KAM}', '{MOKYKLOS}', '{MOKINIUI}', '{MOKINYS}',
            '{UZEMUSIAM}', '{KLASE}', '{MOKYTOJAS}', '{VIETA_KA}', '{OLIMPIADOJE}'
        ],
        [
            $font_name,
            $dip_nr,
            $logo_svg,
            $vieta,
            htmlspecialchars(trim($vardas_raw . ' ' . $pavarde_raw)),
            htmlspecialchars($mokykla_raw),
            htmlspecialchars($olimpiada),
            $data,
            htmlspecialchars(lt_name_to_dative($vardas_raw, $pavarde_raw)),
            htmlspecialchars(lt_school_to_genitive($mokykla_raw)),
            htmlspecialchars(lt_student_word($gender, 'n')),
            htmlspecialchars(lt_student_word($gender, 'v')),
            htmlspecialchars(lt_participle_uzemus($gender)),
            htmlspecialchars($d['1_klase'] ?? ''),
            htmlspecialchars($d['1_mok'] ?? ''),
            $vieta_ka,
            htmlspecialchars(lt_event_to_locative($olimpiada))
        ],
        $html_template
    );
}

/**
 * Sukuria vieno diplomo TCPDF objektą (su firminiu fonu ir paraštėmis).
 * Grąžina paruoštą TCPDF objektą - kviečiantis kodas pats nusprendžia,
 * ar jį atiduoti naršyklei ('I'), ar į ZIP ('S').
 */
function diploma_build_pdf($d, $template, $font_name = 'dejavusans', $logo_svg = '') {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Olimpiadų sistema');
    $pdf->SetTitle('Diplomas - ' . trim(($d['1_vardas'] ?? '') . ' ' . ($d['1_pavarde'] ?? '')));
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins($template['margins']['l'], $template['margins']['t'], $template['margins']['r']);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();

    // Firminis fonas piešiamas PIRMAS, kad tekstas atsidurtų virš jo
    if (!empty($template['bg'])) {
        $pdf->Image($template['bg'], 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
        $pdf->setPageMark();
    }

    $html = diploma_fill_template($template['html'], $d, $font_name, $logo_svg);
    $pdf->writeHTML($html, true, false, true, false, '');

    return $pdf;
}
