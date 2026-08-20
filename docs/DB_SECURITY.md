# Duomenų bazės prisijungimo duomenų saugumas

Ši sistema TIK skaito DB duomenis iš `.env` failo (žr. `.env.example` šablonuose). Slaptažodis
NIEKADA nelaikomas `config/config.php` faile ar git repo.

## 1. Kaip nustatyti `.env` serveryje

1. Nukopijuok `.env.example` kaip `.env` — palik jį **projekto šaknyje** (šalia `index.php`).
   Failą saugo `.htaccess` taisyklė, blokuojanti tiesioginę prieigą prie `.env` plėtinio.
2. Įrašyk tikras reikšmes (žr. #2 žemiau dėl slaptažodžio generavimo).
3. Nustatyk failo teises taip, kad jį skaitytų tik web serverio procesas:
   ```bash
   chmod 600 .env
   ```

## 2. Kaip sugeneruoti maksimaliai saugų DB slaptažodį

Minimalūs reikalavimai: **20+ simbolių**, atsitiktinis, be žodyninių žodžių.

```bash
# Linux/Mac terminale arba per SSH į serverį:
openssl rand -base64 24
```
arba naudojant PHP (jei turi CLI prieigą):
```bash
php -r "echo bin2hex(random_bytes(16));"
```
Niekada nenaudok slaptažodžio, kuris buvo panaudotas kitur, ir nesaugok jo paprastame tekstiniame faile jokiur kitur, tik `.env`.

## 3. DB vartotojo (ne tik slaptažodžio) sugriežtinimas

Vien stiprus slaptažodis apsaugo tik iš dalies. Rekomenduojama papildomai:

### a) Riboti prisijungimą tik iš `localhost`
Jei MySQL/DB serveris tame pačiame serveryje kaip PHP (dažniausias atvejis shared hostinge),
DB vartotojas turėtų būti leidžiamas tik iš `localhost`, o ne `%` (bet kokio IP):
```sql
-- Patikrink esamas teises:
SELECT user, host FROM mysql.user WHERE user = 'tavo_db_vartotojas';

-- Jei matai '%' vietoj 'localhost', susisiek su hostingo palaikymu arba:
RENAME USER 'tavo_db_vartotojas'@'%' TO 'tavo_db_vartotojas'@'localhost';
```

### b) Mažiausių privilegijų principas
DB vartotojui, kurį naudoja pati aplikacija, reikalingos TIK šios teisės — jam
NEREIKIA `GRANT ALL PRIVILEGES` ar `DROP`/`ALTER` teisių kasdieniam veikimui:
```sql
GRANT SELECT, INSERT, UPDATE, DELETE ON testlt_olimpidos.* TO 'tavo_db_vartotojas'@'localhost';
FLUSH PRIVILEGES;
```
`CREATE`/`ALTER`/`DROP` reikalingos tik retkarčiais atliekant migracijas (pvz. šio
projekto `sql_migrations/` failus) — tokiems veiksmams gali laikinai prisijungti
per atskirą administratoriaus DB vartotoją (pvz. phpMyAdmin root/admin paskyra),
o ne per aplikacijos naudojamą vartotoją.

### c) Periodinė rotacija
Slaptažodį verta keisti bent kartą per metus, arba nedelsiant, jei kyla įtarimas
dėl nutekėjimo (kaip buvo šiame projekte — žr. #4).

## 4. Jau kompromituotas slaptažodis

Šio projekto `config/config.php.bak` failas praeityje buvo viešai pasiekiamas su
realiu slaptažodžiu. Jei to dar nepadarei:
1. **Nedelsiant pakeisk DB slaptažodį** hostingo valdymo skydelyje.
2. Atnaujink `.env` serveryje su nauju slaptažodžiu.
3. Patikrink DB prisijungimų žurnalą (jei hostingas tokį teikia) dėl neįprastos veiklos.
