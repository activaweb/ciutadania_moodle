# Documentació del Codi a Mida - Projecte CiutadanIA

Data última actualització: 2026-06-19

---

## Visió General

El projecte CiutadanIA és una instal·lació Moodle personalitzada per gestionar la formació i certificació de competències. El sistema permet que els estudiants completin mòduls formatius, assolir una nota mínima per a ser aprovats, i obtenir certificats oficials vinculats a un procés de pagament.

### Components desenvolupats a mida

| Component | Ruta | Tipus |
|-----------|------|-------|
| `theme_ciutadania` | `/theme/ciutadania/` | Tema visual |
| `customcertelement_approvedmodules` | `/mod/customcert/element/approvedmodules/` | Element de certificat |
| `customcertelement_hourscalculated` | `/mod/customcert/element/hourscalculated/` | Element de certificat |
| `local_ciudadania_certs` | `/local/ciudadania_certs/` | Plugin local (nucli) |
| `availability_ciudadania` | `/availability/condition/ciutadania/` | Condició de disponibilitat |

---

## Flux General del Sistema

```
L'estudiant completa mòduls del curs
        ↓
Moodle registra la compleció a completion_info
        ↓
[local_ciudadania_certs] Observer detecta l'event de compleció
        │
        ├─ Si el mòdul completat és una URL de pagament:
        │   └─ snapshot_manager::create_snapshot()
        │       └─ Guarda els mòduls aprovats a "ciudadania_certifications"
        │
        └─ Si és un mòdul normal amb nova aprovació:
            └─ Reinicia la URL "tornada" per habilitar nou cicle de pagament
        ↓
[customcertelement_approvedmodules] al certificat:
    ├─ Mode "actual": llegeix get_current_approved_modules() (en temps real)
    └─ Mode "certificat": llegeix get_certified_modules() (snapshot del pagament)

[customcertelement_hourscalculated] al certificat:
    └─ Compta mòduls completats × 2 hores

[availability_ciudadania] als recursos del curs:
    └─ Comprova si existeix registre a "ciudadania_certifications"
        ├─ Existeix → accés concedit ("ha realitzat el pagament")
        └─ No existeix → accés bloquejat
```

---

## 1. Tema: `theme_ciutadania`

**Ruta:** `/theme/ciutadania/`
**Versió:** 2026012900 | Requereix Moodle >= 2022111800

### Propòsit

Tema visual del projecte que estén el tema pare **Boost** de Moodle amb una identitat gràfica pròpia del projecte CiutadanIA i suport bilingüe (català i anglès).

### Paleta de colors

| Color | Valor HEX | Ús |
|-------|-----------|-----|
| Morat primari | `#50488E` | Color principal, botons, capçaleres |
| Morat clar | `#8981C0` | Accents, hover |
| Morat fosc | `#312C56` | Peu de pàgina |
| Groc | `#FFFF66` | Destacats |
| Verd èxit | `#28a745` | Confirmacions |

### Fitxers principals

- **`config.php`** — Configuració del tema. Hereta de Boost, desactiva el dock lateral, defineix la funció de compilació SCSS.
- **`lib.php`** — Funció `theme_ciutadania_get_main_scss_content()` que carrega i compila tots els fitxers SCSS amb el color primari.
- **`version.php`** — Metadades del plugin.
- **`lang/ca/`** i **`lang/en/`** — Cadenes de traducció bilingüe.

### Personalitzacions SCSS per pàgina

| Fitxer SCSS | Pàgina afectada |
|-------------|-----------------|
| `custom.scss` | Base global (colors, botons, footer) |
| `custom.login.page.scss` | Pàgina d'accés (imatge de fons: `/theme/ciutadania/img/fons.jpg`) |
| `custom.policy.page.scss` | Pàgina de política |
| `custom.signup.page.scss` | Registre d'usuari |
| `custom.my.index.page.scss` | Tauler / Els meus cursos |
| `custom.inici.page.scss` | Pàgina d'inici |
| `custom.enroll.index.page.scss` | Matriculació a cursos |
| `custom.cursos.page.scss` | Llista de cursos |
| `custom.curs.page.scss` | Vista d'un curs |
| `custom.scorm.scss` | Activitats SCORM |

### Notes importants

- El menú lateral (drawer) s'oculta en determinades vistes.
- La imatge de fons de la pàgina de login cal que existeixi a `/theme/ciutadania/img/fons.jpg`.

---

## 2. Element de Certificat: `customcertelement_approvedmodules`

**Ruta:** `/mod/customcert/element/approvedmodules/`
**Versió:** 2026020901 | Requereix Moodle >= 2022111800

### Propòsit

Element personalitzat per al plugin **CustomCert** que mostra la llista de mòduls que l'estudiant ha superat (nota ≥ 35/100) en el certificat generat. Pot mostrar l'estat actual o l'estat en el moment del pagament.

### Modes de visualització

| Mode | Descripció |
|------|------------|
| **Mòduls aprovats actuals** | Llista dinàmica en temps real (`get_current_approved_modules()`) |
| **Mòduls del darrer certificat** | Snapshot del moment del pagament (`get_certified_modules()`) |

### Format de sortida al certificat

Taula de 4 columnes:

| Nom del mòdul | Hores | Data de compleció | Nota |
|---------------|-------|-------------------|------|
| Nom del mòdul | 2h | DD/MM/AAAA | X.X (dividit entre 10) |

> Les hores estan fixades a **2h per mòdul** (valor codificat directament).

### Opcions de configuració (en el disseny del certificat)

- **Mode:** "Actual" o "Del darrer certificat"
- **Mostrar notes:** Activar/desactivar la columna de notes

### Missatge especial

Si l'estudiant no té cap registre de pagament, mostra: *"You must complete the payment process..."*

### Dependències

- Depèn de `local_ciudadania_certs\snapshot_manager` per obtenir les dades.
- Requereix el plugin `mod_customcert` instal·lat.

### Fitxer principal

- **`classes/element.php`** — Tota la lògica: renderitzat PDF/HTML, formulari de configuració, obtenció i format dels mòduls.

---

## 3. Element de Certificat: `customcertelement_hourscalculated`

**Ruta:** `/mod/customcert/element/hourscalculated/`
**Versió:** 2024052001 (release 1.1)

### Propòsit

Element personalitzat per a CustomCert que calcula i mostra el total d'hores de formació basant-se en els mòduls completats pel curs.

### Lògica de càlcul

```
Hores totals = Nombre de mòduls completats × 2 hores
```

- Itera tots els mòduls del curs amb seguiment de compleció activat.
- Compta els que tenen estat `COMPLETION_COMPLETE` o `COMPLETION_COMPLETE_PASS`.
- Multiplica per 2 (valor fixat, no configurable).

### Notes importants

- No té opcions de configuració (no hi ha camps al formulari de l'element).
- Les hores per mòdul (2h) estan hardcoded a `get_calculated_hours()` a `classes/element.php`.
- No crea cap taula pròpia; usa les taules natives de Moodle (`course_modules`, `course_modules_completion`).

---

## 4. Plugin Local: `local_ciudadania_certs`

**Ruta:** `/local/ciudadania_certs/`

### Propòsit

Nucli del sistema de certificació. Gestiona els **snapshots** d'aprovació: quan un estudiant paga, es guarda l'estat dels mòduls aprovats en aquell moment. Proporciona les APIs que usen els altres components.

### Taula de base de dades: `ciudadania_certifications`

| Camp | Tipus | Descripció |
|------|-------|------------|
| `id` | INT (PK) | Identificador únic |
| `userid` | INT (FK → user) | Usuari |
| `courseid` | INT (FK → course) | Curs |
| `paymentcmid` | INT | ID del mòdul de pagament que va activar el snapshot |
| `modules_json` | TEXT | JSON amb la llista de mòduls aprovats i les seves dades |
| `total_modules` | INT | Nombre total de mòduls al snapshot |
| `total_hours` | INT | Total d'hores al snapshot |
| `timecreated` | INT | Timestamp de creació |

**Índexs:** `(userid, courseid)` i `timecreated`

### Classe `snapshot_manager` (`classes/snapshot_manager.php`)

Classe estàtica amb les funcions principals del sistema:

#### `get_current_approved_modules($userid, $courseid)`

Retorna la llista actual de mòduls aprovats per l'usuari. **Lògica:**

1. Obté tots els mòduls del curs amb compleció registrada.
2. Per cada mòdul completat, obté la nota de `grade_grades`.
3. Filtra els **mòduls elegibles**: nota **≥ 35/100**.
4. Comprova que tots els **mòduls obligatoris** (M1, M2, M3, M4, M10 pel camp `idnumber`) estan entre els elegibles. Si falta algun → retorna array buit.
5. Calcula la **mitjana** només dels mòduls elegibles (≥ 35).
6. Si la mitjana és **< 70/100** → retorna array buit.
7. Retorna els mòduls elegibles.

**Llindars clau:**
- Nota mínima per mòdul individual: **35/100**
- Mòduls obligatoris (camp `idnumber`): **M1, M2, M3, M4, M10** (tots han de ser ≥ 35)
- Mitjana mínima dels elegibles: **≥ 70/100**

#### `create_snapshot($userid, $courseid, $paymentcmid)`

Crea un snapshot en el moment del pagament:
1. Obté els mòduls aprovats actuals.
2. Elimina l'snapshot anterior de l'usuari per aquest curs.
3. Guarda el nou snapshot a `ciudadania_certifications`.
4. Retorna l'ID del nou registre, o `false` si no hi ha mòduls aprovats.

#### `get_certified_modules($userid, $courseid)`

Retorna els mòduls del darrer snapshot (el més recent). Retorna array buit si no n'hi ha.

#### `get_all_snapshots($userid, $courseid)`

Retorna tots els snapshots histórics ordenats per `timecreated`.

#### `has_certifications($userid, $courseid)`

Retorna `true`/`false` si l'usuari té algun registre de pagament al curs.

### Classe `observer` (`classes/observer.php`)

Escolta l'event `\core\event\course_module_completion_updated` de Moodle.

#### Cas 1: Compleció d'una URL de pagament

El sistema identifica una URL de pagament si el nom o la URL conté alguna d'aquestes paraules:
- `pagament`, `payment`, `pago`, `certificat oficial`

Quan es detecta la compleció d'una d'aquestes URLs:
1. Comprova si hi ha mòduls aprovats nous des de l'últim snapshot.
2. Si n'hi ha → crea un nou snapshot.
3. Registra l'acció al log de Moodle.

#### Cas 2: Compleció d'un mòdul ordinari → reinici de "tornada"

Quan es completa qualsevol mòdul, el sistema comprova si cal reiniciar la URL de "tornada" (per habilitar un nou cicle de pagament):

Condicions per reiniciar:
1. L'usuari ja té snapshots previs (ha pagat anteriorment).
2. El mòdul recentment completat **no estava** a l'últim snapshot.
3. La condició de nota global (> 50) segueix complint-se.

La URL "tornada" s'identifica si el seu nom conté: `tornada` o `retorn`.
Es reinicia a `COMPLETION_INCOMPLETE` per permetre una nova compleció.

### Fitxers addicionals

- **`db/install.xml`** — Definició de la taula `ciudadania_certifications`.
- **`db/events.php`** — Registre de l'observer a l'event de compleció.
- **`lang/ca/`** i **`lang/en/`** — Cadenes de traducció i metadades de privacitat.

---

## 5. Condició de Disponibilitat: `availability_ciudadania`

**Ruta:** `/availability/condition/ciutadania/`
**Versió:** 2026052600 | Requereix Moodle >= 2022041900

### Propòsit

Condició d'accés per restringir activitats o seccions del curs en funció de si l'estudiant ha completat el procés de pagament (té un registre a `ciudadania_certifications`).

### Funcionament

La condició és **sense paràmetres** (estàtica). Comprova únicament si existeix un registre a `ciudadania_certifications` per a l'usuari i el curs actuals.

| Configuració | Resultat |
|-------------|---------|
| "Ha realitzat el pagament" | Accés concedit si `has_certifications() = true` |
| "NO ha realitzat el pagament" | Accés concedit si `has_certifications() = false` |

### Casos d'ús típics

- Bloquejar la descàrrega del certificat oficial fins que s'hagi pagat.
- Bloquejar el recurs de "tornada al pagament" fins que hi hagi nous mòduls aprovats.
- Restringir activitats post-certificació fins al pagament.

### Fitxers principals

- **`classes/condition.php`** — Lògica de la condició. Consulta directament `ciudadania_certifications`.
- **`classes/frontend.php`** — Integració amb l'editor del curs (sense camps de configuració).
- **`amd/src/form.js`** — Formulari AMD buit (sense opcions configurables).

---

## Dependències entre Components

```
theme_ciutadania
    └─ Depèn de: boost (tema pare de Moodle)

customcertelement_approvedmodules
    └─ Depèn de: mod_customcert, local_ciudadania_certs

customcertelement_hourscalculated
    └─ Depèn de: mod_customcert (i taules natives Moodle)

local_ciudadania_certs
    └─ Depèn de: taules natives Moodle (grade_grades, course_modules_completion, url)
    └─ Usat per: customcertelement_approvedmodules, availability_ciudadania

availability_ciudadania
    └─ Depèn de: local_ciudadania_certs (taula ciudadania_certifications)
```

---

## Taules de Base de Dades

### Taula pròpia creada pel projecte

**`ciudadania_certifications`** (creada per `local_ciudadania_certs`)

### Taules natives de Moodle usades

| Taula | Usat per |
|-------|----------|
| `grade_grades`, `grade_items` | Obtenció de notes dels mòduls |
| `course_modules_completion` | Estat de compleció dels mòduls |
| `course_modules` | Estructura del curs |
| `course` | Informació del curs |
| `url` | Identificació de URLs de pagament i tornada |

---

## Suport d'Idiomes

Tots els components suporten:
- **Català (ca):** Idioma principal del projecte
- **Anglès (en):** Fallback predeterminat de Moodle

---

## Configuració i Constants a Tenir en Compte

### Llindars de notes (a `local_ciudadania_certs/classes/snapshot_manager.php`)

```php
// IDs dels mòduls obligatoris (camp "ID number" del mòdul a Moodle)
$mandatory_idnumbers = ['M1', 'M2', 'M3', 'M4', 'M10'];

// Nota mínima per a que un mòdul individual compti com a "aprovat"
$min_module_grade = 35; // sobre 100

// Mitjana dels mòduls elegibles (>= 35) necessària per generar diploma
$min_global_average = 70; // sobre 100 (>= 70)
```

### Hores per mòdul (a `customcertelement_hourscalculated/classes/element.php`)

```php
$hours_per_module = 2; // hardcoded
```

### Identificadors de URLs especials (a `local_ciudadania_certs/classes/observer.php`)

```php
// URLs que es consideren "de pagament"
['pagament', 'payment', 'pago', 'certificat oficial']

// URLs que es consideren "de tornada" (es reinicien quan hi ha nous aprovats)
['tornada', 'retorn']
```

---

## Instal·lació i Actualitzacions

Per instal·lar o actualitzar els plugins, cal accedir com a administrador a:
**Administració del lloc → Notificacions** (Moodle detectarà els nous plugins/versions i executarà els scripts d'instal·lació/actualització).

L'ordre recomanat d'instal·lació si es fa des de zero:
1. `local_ciudadania_certs` (crea la taula de BD que usen els altres)
2. `availability_ciudadania`
3. `customcertelement_approvedmodules`
4. `customcertelement_hourscalculated`
5. `theme_ciutadania`
