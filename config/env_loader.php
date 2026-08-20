<?php
/**
 * Minimalus .env failo skaitytuvas - be jokių išorinių priklausomybių (composer nereikalingas).
 *
 * VARIANTAS B: .env failas laikomas projekto šaknyje, apsaugotas per .htaccess
 * (žr. FilesMatch taisyklę, blokuojančią .env plėtinį). Paprasčiausias ir
 * universaliausias sprendimas, veikiantis bet kuriame PHP hostinge be
 * papildomos serverio konfigūracijos.
 *
 * Naudojimas: load_env(__DIR__ . '/..');  // arba bet koks kelias iki .env
 */
function load_env($project_root) {
    $env_file = $project_root . '/.env';

    if (!is_readable($env_file)) {
        return false;
    }

    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue; // tuščios eilutės ir komentarai
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // Pašaliname supančias kabutes, jei jos yra ("reikšmė" arba 'reikšmė')
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        if ($name !== '' && !array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
            putenv("{$name}={$value}");
        }
    }

    return $env_file;
}

/**
 * Patogus getter su privaloma reikšme - jei kintamasis nerastas, sistema
 * iškart sustabdoma su aiškiu klaidos pranešimu (fail-fast), o ne tyliai
 * naudoja tuščią/numatytąją reikšmę, kuri galėtų nutylėti konfigūracijos klaidą.
 */
function env_required($name) {
    $value = $_ENV[$name] ?? getenv($name);
    if ($value === false || $value === null || $value === '') {
        http_response_code(500);
        error_log("KRITINĖ KLAIDA: trūksta privalomo aplinkos kintamojo '{$name}'. Patikrinkite .env failą.");
        die("Sistemos konfigūracijos klaida. Susisiekite su administratoriumi. (Trūksta: {$name})");
    }
    return $value;
}

/**
 * Getter su numatytąja reikšme (neprivaloma) - naudoti tik nejautriems nustatymams.
 */
function env_optional($name, $default = null) {
    $value = $_ENV[$name] ?? getenv($name);
    return ($value === false || $value === null || $value === '') ? $default : $value;
}
