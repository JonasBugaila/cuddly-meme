-- Migracija: duomenų vientisumo sugriežtinimas
-- SVARBU: prieš paleidžiant UNIQUE apribojimus, PIRMA patikrink, ar nėra dublikatų
-- (žr. patikros užklausas žemiau) - jei dublikatų yra, ALTER TABLE su UNIQUE mes klaidą.

-- ============================================================
-- 0. PATIKRA DĖL DUBLIKATŲ (paleisk PIRMA, prieš žemiau esančias ALTER TABLE eilutes)
-- ============================================================
-- SELECT pavadinimas, COUNT(*) FROM mokyklos GROUP BY pavadinimas HAVING COUNT(*) > 1;
-- SELECT konkurso_pav, COUNT(*) FROM konkursai GROUP BY konkurso_pav HAVING COUNT(*) > 1;
-- SELECT el_pastas, COUNT(*) FROM vartotojas WHERE el_pastas IS NOT NULL AND el_pastas != '' GROUP BY el_pastas HAVING COUNT(*) > 1;
-- SELECT 1_vardas, 1_pavarde, var_mokykla, konkurso_pav, COUNT(*) FROM dalyviai
--     GROUP BY 1_vardas, 1_pavarde, var_mokykla, konkurso_pav HAVING COUNT(*) > 1;
--
-- Jei kuri nors užklausa grąžina eilučių - PIRMA rankiniu būdu suvienodink/ištrink
-- dublikatus (arba pervadink vieną iš jų), o tik tada paleisk žemiau esančias komandas.
-- Paskutinioji (dalyviai) užklausa svarbi #4 punktui - jei ji grąžina eilučių, tai
-- reiškia, kad tas pats mokinys jau šiuo metu yra dukart registruotas į tą pačią
-- olimpiadą - prieš pridedant UNIQUE apribojimą (žr. #4), tokius dublikatus reikia
-- rankiniu būdu peržiūrėti ir vieną iš jų ištrinti arba sujungti duomenis.


-- ============================================================
-- 1. UNIKALUMO APRIBOJIMAI
-- Sistema visur nurodo mokyklą/olimpiadą/el.paštą PER PAVADINIMĄ (ne per ID),
-- todėl DB lygyje būtina užtikrinti, kad pavadinimai/el.paštas būtų unikalūs -
-- priešingu atveju "WHERE konkurso_pav = ?" galėtų dviprasmiškai atitikti
-- kelias eilutes vienu metu.
-- ============================================================
ALTER TABLE mokyklos ADD UNIQUE KEY uniq_pavadinimas (pavadinimas);
ALTER TABLE konkursai ADD UNIQUE KEY uniq_konkurso_pav (konkurso_pav);
ALTER TABLE vartotojas ADD UNIQUE KEY uniq_el_pastas (el_pastas);


-- ============================================================
-- 2. TRŪKSTAMI INDEKSAI
-- dalyviai.var_mokykla ir dalyviai.konkurso_pav filtruojami beveik
-- kiekviename užklausime ("Mano dalyviai", olimpiados peržiūra, ataskaitos),
-- bet neturi indekso - augant lentelei, kiekviena tokia užklausa taps pilnu
-- lentelės skenavimu.
-- ============================================================
ALTER TABLE dalyviai ADD INDEX idx_var_mokykla (var_mokykla);
ALTER TABLE dalyviai ADD INDEX idx_konkurso_pav (konkurso_pav);


-- ============================================================
-- 3. COLLATION NEATITIKIMAS
-- teacher_activity_log sukurta su utf8mb4_general_ci, o visos kitos lentelės -
-- su utf8mb4_unicode_ci. Suvienodinama, kad ateityje nekiltų "Illegal mix of
-- collations" klaidos jungiant (JOIN) su kitomis lentelėmis, ir kad lietuviškų
-- raidžių rikiavimas būtų nuoseklus visoje sistemoje.
-- ============================================================
ALTER TABLE teacher_activity_log CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


-- ============================================================
-- 4. DUBLIKATŲ APSAUGA - TAS PATS MOKINYS TOJE PAČIOJE OLIMPIADOJE
-- Kadangi DB neturi atskiro "mokinio" ID, tas pats mokinys atpažįstamas per
-- (vardas, pavardė, mokykla) derinį. Šis apribojimas neleidžia DB lygyje
-- įrašyti dviejų eilučių su tuo pačiu (vardas, pavardė, mokykla, olimpiada)
-- deriniu - t.y. neleidžia tam pačiam mokiniui būti dukart registruotam į
-- TĄ PAČIĄ olimpiadą. Registruoti tą patį mokinį į SKIRTINGAS olimpiadas
-- (kas yra normalu ir laukiama) šis apribojimas NETRUKDO.
--
-- SVARBU: PHP kodo pusėje (modules/registration/register.php, save.php,
-- edit_participant.php) jau pridėta patikra PRIEŠ įrašant/atnaujinant, kuri
-- parodo draugišką pranešimą vartotojui. Šis DB apribojimas veikia kaip
-- paskutinė apsaugos linija (pvz. lenktynių sąlygos atveju, kai du mokytojai
-- vienu metu registruoja tą patį mokinį).
-- ============================================================
ALTER TABLE dalyviai ADD UNIQUE KEY uniq_dalyvis_olimpiadoje (`1_vardas`, `1_pavarde`, var_mokykla, konkurso_pav);
