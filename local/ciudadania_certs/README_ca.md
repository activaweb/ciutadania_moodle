# Plugin de Certificacions CiutadanIA

Un plugin local de Moodle que crea instantànies de certificació quan els usuaris completen activitats de pagament, permetent la certificació progressiva de mòduls del curs.

## Què fa?

Aquest plugin permet oferir **certificacions incrementals** en un curs:

- Els usuaris completen mòduls progressivament
- Cada vegada que paguen, es crea una **instantània** amb els mòduls aprovats actualment
- El diploma gratuït sempre mostra **tots els mòduls aprovats actuals** (dinàmic)
- El certificat oficial mostra **només els mòduls de l'últim pagament** (instantània congelada)

## Com funciona

### 1. Creació automàtica d'instantànies

Quan un usuari completa una **activitat de pagament (URL)**, el plugin automàticament:

1. Detecta la completació via event observer
2. Obté tots els mòduls amb nota ≥70/100 aprovats actualment
3. Guarda una instantània a la base de dades amb:
   - ID d'usuari
   - ID del curs
   - ID de l'activitat de pagament
   - JSON amb els mòduls certificats
   - Total d'hores (mòduls × 2)
   - Marca de temps de creació

### 2. Integració amb customcert

El plugin s'integra amb l'element **approvedmodules** de customcert, que ara té dos modes:

- **Mode actual**: Mostra tots els mòduls aprovats actualment (per al diploma gratuït)
- **Mode certificat**: Mostra només els mòduls de l'última instantània de pagament (per al certificat oficial)

## Instal·lació

El plugin s'instal·la automàticament via el sistema d'actualització de Moodle:

```bash
php admin/cli/upgrade.php --non-interactive
```

Això crea la taula `mdlu8_ciudadania_certifications` amb l'estructura següent:

```sql
CREATE TABLE mdlu8_ciudadania_certifications (
  id BIGINT(10) PRIMARY KEY AUTO_INCREMENT,
  userid BIGINT(10) NOT NULL,
  courseid BIGINT(10) NOT NULL,
  paymentcmid BIGINT(10) NOT NULL,
  modules_json LONGTEXT NOT NULL,
  total_hours BIGINT(10) DEFAULT 0,
  total_modules BIGINT(10) DEFAULT 0,
  timecreated BIGINT(10) NOT NULL
);
```

## Configuració del curs

### Activitat 1: Diploma gratuït (dinàmic)

1. Crea una activitat **customcert**
2. Afegeix restricció: Completar 4 mòduls obligatoris
3. Afegeix l'element **"Mòduls aprovats"**
   - Mode: **"Mòduls aprovats actuals (dinàmic)"**
   - Mostrar notes: A la teva elecció

### Activitat 2: URL de pagament (única, reutilitzable)

1. Crea una activitat **URL**
2. Apunta a la teva pasarel·la de pagament
3. Afegeix restriccions:
   - Completar 4 mòduls obligatoris
   - 30 dies des de la inscripció
   - Camp DNI/NIE no buit
4. **Important**: El nom o URL ha de contenir una de:
   - "pagament", "payment", "pago"
   - "certificat oficial"

### Activitat 3: Certificat oficial (únic)

1. Crea una activitat **customcert**
2. Afegeix restricció: Pagament completat
3. Afegeix l'element **"Mòduls aprovats"**
   - Mode: **"Últims mòduls certificats (de l'últim pagament)"** ⭐
   - Mostrar notes: A la teva elecció

## Exemple de flux d'usuari

```
Dia 1: L'usuari completa 4 mòduls obligatoris
   ↓
   Diploma gratuït disponible (mostra 4 mòduls, 8h)

Dia 35: L'usuari completa 2 mòduls més (total 6)
   ↓
   Diploma gratuït s'actualitza automàticament (mostra 6 mòduls, 12h)
   URL de pagament es fa disponible
   ↓
   L'usuari paga (1a vegada)
   ↓
   El plugin crea instantània: 6 mòduls, 12h
   Certificat oficial disponible (mostra 6 mòduls, 12h)

Dia 50: L'usuari completa 3 mòduls més (total 9)
   ↓
   Diploma gratuït s'actualitza (mostra 9 mòduls, 18h)
   Certificat oficial encara mostra 6 mòduls (congelat)
   ↓
   L'usuari paga (2a vegada)
   ↓
   El plugin crea nova instantània: 9 mòduls, 18h
   Certificat oficial ara mostra 9 mòduls, 18h
```

## Consultes SQL útils

### Veure totes les certificacions

```sql
SELECT
    u.firstname, u.lastname, u.email,
    c.fullname as course,
    cc.total_modules, cc.total_hours,
    FROM_UNIXTIME(cc.timecreated) as data_pagament,
    cc.modules_json
FROM mdlu8_ciudadania_certifications cc
JOIN mdlu8_user u ON cc.userid = u.id
JOIN mdlu8_course c ON cc.courseid = c.id
ORDER BY cc.timecreated DESC;
```

### Veure historial de certificacions per a un usuari específic

```sql
SELECT
    total_modules, total_hours,
    FROM_UNIXTIME(timecreated) as data_pagament,
    modules_json
FROM mdlu8_ciudadania_certifications
WHERE userid = ? AND courseid = ?
ORDER BY timecreated DESC;
```

### Comptar certificacions per curs

```sql
SELECT
    c.fullname,
    COUNT(*) as total_certificacions,
    COUNT(DISTINCT cc.userid) as usuaris_unics
FROM mdlu8_ciudadania_certifications cc
JOIN mdlu8_course c ON cc.courseid = c.id
GROUP BY c.id, c.fullname;
```

## Detalls tècnics

### Detecció de pagaments

L'observer (`classes/observer.php`) escolta l'event:
- `\core\event\course_module_completion_updated`

I crea una instantània quan:
1. L'estat de completació és COMPLETE o COMPLETE_PASS
2. El mòdul és de tipus URL
3. El nom o adreça de l'URL conté paraules clau de pagament

### API del Snapshot Manager

```php
// Crear instantània
$snapshotid = \local_ciudadania_certs\snapshot_manager::create_snapshot(
    $userid,
    $courseid,
    $paymentcmid
);

// Obtenir últims mòduls certificats
$modules = \local_ciudadania_certs\snapshot_manager::get_certified_modules(
    $userid,
    $courseid
);

// Obtenir totes les instantànies per a un usuari
$snapshots = \local_ciudadania_certs\snapshot_manager::get_all_snapshots(
    $userid,
    $courseid
);

// Comprovar si l'usuari té certificacions
$has = \local_ciudadania_certs\snapshot_manager::has_certifications(
    $userid,
    $courseid
);
```

## Resolució de problemes

### L'instantània no es crea després del pagament

1. Comprova si l'activitat URL conté paraules clau de pagament
2. Verifica que la completació s'està seguint correctament
3. Revisa els registres de Moodle: `Administració del lloc → Informes → Registres`
4. Busca: "Certification snapshot created for user X"

### El certificat no mostra mòduls

1. Verifica que l'usuari ha completat almenys un pagament
2. Comprova que l'instantània existeix a la base de dades
3. Assegura't que l'element customcert està en mode "Certificat"

### Vols crear una instantània manualment

```php
// Executa en CLI de Moodle o tasca programada
require_once(__DIR__.'/config.php');
$userid = 123;    // ID d'usuari
$courseid = 5;    // ID del curs
$paymentcmid = 54; // ID CM de l'activitat de pagament

$result = \local_ciudadania_certs\snapshot_manager::create_snapshot(
    $userid,
    $courseid,
    $paymentcmid
);

echo "Instantània creada: " . $result;
```

## Versió

- **Versió:** 1.0
- **Requereix:** Moodle 4.1+
- **Maduresa:** ESTABLE

## Autor

Creat per al projecte CiutadanIA.

## Llicència

GPL v3 o posterior
