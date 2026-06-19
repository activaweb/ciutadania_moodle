# Lògica de Negoci - Projecte CiutadanIA

Data última actualització: 2026-06-19

---

## Estructura del curs

El curs **CiutadanIA 2.0** conté **14 mòduls formatius**, cadascun implementat com una activitat SCORM (arxiu ZIP extern). Cada SCORM té exercicis que puntuen de **0 a 100**.

### Regles bàsiques dels mòduls

- **Ordre lliure**: l'alumne pot completar els mòduls en qualsevol seqüència i moment.
- **Nota mínima d'aptitud: 35/100**. Per sota d'aquest llindar, el mòdul no compta per a cap càlcul (ni nota, ni diploma, ni hores al certificat).
- **Cada mòdul aprovat (≥35) = 2 hores de formació** al certificat final.
- Els mòduls s'identifiquen a Moodle mitjançant el camp **ID number** (valors: `M1`, `M2`, ... `M14`).

---

## Condicions per obtenir el diploma

### Mòduls obligatoris

Els cinc mòduls següents **sempre han d'estar completats amb nota ≥ 35**:

| ID number | Mòdul |
|-----------|-------|
| M1 | Mòdul 1 |
| M2 | Mòdul 2 |
| M3 | Mòdul 3 |
| M4 | Mòdul 4 |
| M10 | Mòdul 10 |

Si qualsevol d'aquests té nota < 35 o no està completat → **impossible obtenir diploma**, independentment de la resta.

### Càlcul de la nota mitja

La nota mitja es calcula **únicament** amb els mòduls que tinguin nota **≥ 35** (obligatoris + optatius). Els mòduls amb nota < 35 no entren al càlcul.

**Llindar de la mitja: ≥ 70/100**

### Exemples

| Situació | Resultat |
|----------|----------|
| M1=80, M2=70, M3=75, M4=90, M10=80 → mitja=79 | ✅ Diploma possible |
| M1=80, M2=70, M3=75, M4=90, M10=80, M5=60 → mitja=75,8 | ✅ Diploma possible |
| M1=80, M2=70, M3=75, M4=90, M10=80, M5=36, M6=38 → mitja=67 | ❌ Mitja < 70 |
| M1=80, M2=70, M3=75, M4=34, M10=80 | ❌ M4 < 35, obligatori no apte |

> **Important**: els mòduls optatius amb nota baixa (però ≥ 35) poden fer baixar la mitja per sota de 70 i bloquejar el diploma. Completar un mòdul optatiu és sempre voluntari però **sempre impacta la mitja**.

---

## Sistema de certificació i pagament

### Visió general

Quan l'alumne compleix les condicions per al diploma, pot iniciar un **procés de pagament** que genera un **snapshot** (foto fixa) de la seva formació aprovada en aquell moment. Aquesta foto és el contingut del diploma/certificat oficial.

### Les dues URLs de pagament

Al curs hi ha dues activitats de tipus URL que gestionen el cicle de pagament:

| URL | Nom conté | Funció |
|-----|-----------|--------|
| **URL de crida** | `pagament`, `payment`, etc. | Inicia el pagament (porta a la passarel·la) |
| **URL de tornada** | `tornada`, `retorn` | Rep el retorn de la passarel·la un cop pagat |

### Mecanisme de bloqueig/desbloqueig

La **URL de crida** té dues condicions de disponibilitat de Moodle:

1. **La URL de tornada NO ha d'estar marcada com a completada** → si la tornada ja és completa, no es pot tornar a pagar.
2. **Les condicions del diploma han de complir-se en temps real** (`get_current_approved_modules()` retorna resultats) → si en el moment d'accedir la mitja ha baixat de 70, la crida segueix bloquejada encara que la tornada estigui incompleta.

Aquesta doble condició garanteix que el pagament no es pugui fer si les condicions no es compleixen en el moment exacte d'accedir.

---

## Flux complet per itinerari

### Itinerari 1: Primera certificació

```
1. Alumne completa mòduls (ordre lliure)
        ↓
2. Quan tots els obligatoris (M1,M2,M3,M4,M10) ≥35
   i la mitja de tots els elegibles ≥70:
   → URL de crida es desbloqueja (condicions de disponibilitat Moodle)
        ↓
3. Alumne accede a URL de crida → passarel·la de pagament
        ↓
4. Pagament completat → passarel·la redirigeix a URL de tornada
   → Moodle marca la tornada com a completada
        ↓
5. Observer detecta compleció de la tornada (URL de pagament)
   → snapshot_manager::create_snapshot()
   → Es guarda a "ciudadania_certifications":
      - Llista de mòduls aprovats (≥35) en aquell moment
      - Total mòduls i hores
   → URL de crida es torna a bloquejar (tornada ja és completa)
        ↓
6. Certificat disponible per descarregar
   (availability_ciudadania detecta registre de pagament → accés concedit)
```

### Itinerari 2: Re-certificació (certificat actualitzat)

```
1. L'alumne ja té un certificat (snapshot existent)
        ↓
2. L'alumne completa un nou mòdul amb nota ≥35
   que NO figurava al darrer snapshot
        ↓
3. Observer detecta la compleció del mòdul:
   → Mòdul no és al snapshot anterior ✓
   → Nota del mòdul ≥35 ✓
   → Reinicia la URL de tornada a "incompleta"
   → Això desbloqueja parcialment la URL de crida
        ↓
4. La URL de crida comprova en temps real si les condicions
   del diploma es compleixen (mitja dels elegibles ≥70):
   → SI ≥70: URL de crida accessible
   → SI <70: URL de crida segueix bloquejada (fins que la mitja pugi)
        ↓
5. Si accede a la crida → pagament → tornada completada
        ↓
6. Observer crea NOU snapshot:
   → Esborra el snapshot anterior
   → Guarda el nou amb els mòduls aprovats actuals
        ↓
7. Certificat actualitzat disponible.
   El certificat anterior queda substituït (no existeix).
```

### Itinerari 3: Certificat sempre accessible

```
L'alumne té un certificat (snapshot)
        ↓
Completa més mòduls → la mitja baixa per sota de 70
        ↓
→ NO pot tornar a pagar (URL de crida bloquejada per mitja < 70)
→ PERÒ el certificat existent SEMPRE és descarregable
  (availability_ciudadania comprova només si hi ha registre de pagament)
→ El contingut del certificat NO canvia (mostra el snapshot del pagament)
```

### Itinerari 4: Mòdul completat que no aporta nova elegibilitat

```
L'alumne té un certificat. Completa un mòdul amb nota 34 (< 35)
        ↓
Observer detecta compleció:
→ Nota < 35 → NO reinicia la tornada
→ El cicle de pagament no es veu afectat
```

---

## Lògica de `get_current_approved_modules()`

Aquesta funció és el nucli de les validacions. Retorna la llista de mòduls aptes per al diploma o array buit si les condicions no es compleixen.

```
Entrada: userid, courseid

1. Obté tots els mòduls del curs amb seguiment de compleció
2. Filtra els completats (COMPLETION_COMPLETE o COMPLETION_COMPLETE_PASS)
3. Obté la nota de grade_grades per cada mòdul completat
4. Calcula els mòduls "elegibles": nota ≥ 35/100

5. Comprova mòduls obligatoris:
   → M1, M2, M3, M4, M10 han d'estar TOTS entre els elegibles
   → Si falta algun → retorna []

6. Calcula la mitja NOMÉS dels elegibles (≥35):
   → Si mitja < 70 → retorna []

7. Retorna la llista d'elegibles
```

---

## Lògica de l'Observer

L'observer escolta l'event `course_module_completion_updated`.

### Quan es completa la URL de tornada (URL de pagament)

```
is_payment_url() = true (la URL conté "pagament", "payment", etc.)
        ↓
handle_payment():
  → Si és el primer pagament (no hi ha snapshot previ):
     → create_snapshot() directament
  → Si ja hi ha snapshot previ:
     → Comprova si hi ha mòduls nous (cmids no al snapshot anterior)
     → Si no n'hi ha → no fa res (pagament sense nous mòduls)
     → Si n'hi ha → create_snapshot() (esborra l'anterior, crea el nou)
```

### Quan es completa qualsevol altre mòdul

```
is_payment_url() = false
        ↓
maybe_reset_tornada():
  → Si no hi ha snapshot previ → no fa res (primer cicle, tornada mai completada)
  → Si el mòdul ja és al snapshot → no fa res (no aporta res nou)
  → Si la nota del mòdul < 35 → no fa res (no és un mòdul elegible)
  → Si tot l'anterior passa → reinicia la URL de tornada a INCOMPLETA
     (desbloqueja parcialment la URL de crida)
```

> **Nota**: la comprovació de la mitja (≥70) i dels obligatoris NO la fa l'observer. La fa la condició de disponibilitat en temps real a la URL de crida en el moment d'accés.

---

## Contingut del certificat

El certificat generat amb el plugin CustomCert té dos elements a mida:

### Element `approvedmodules`

Mostra una taula amb els mòduls aprovats. Té dos modes:

| Mode | Dades mostrades |
|------|-----------------|
| **Actual** (`current`) | Mòduls aprovats ara mateix (dinàmic, via `get_current_approved_modules()`) |
| **Certificat** (`certified`) | Mòduls del snapshot del darrer pagament (congelat, via `get_certified_modules()`) |

Format de la taula al PDF:

| Nom del mòdul | Hores | Data compleció | Nota |
|---------------|-------|----------------|------|
| Nom | 2h | DD.MM.AAAA | X.X/10 |

### Element `hourscalculated`

Mostra el total d'hores: **nombre de mòduls completats i elegibles × 2 hores**.

---

## Condicions de disponibilitat de Moodle al curs

| Recurs | Condicions |
|--------|------------|
| **URL de crida** (pagament) | 1. URL de tornada NO completada<br>2. `get_current_approved_modules()` ≠ buit (via condició a mida) |
| **URL de tornada** | (sense restriccions especials, accessible sempre) |
| **Certificat oficial** | `availability_ciudadania`: l'usuari ha completat el pagament (té registre a `ciudadania_certifications`) |

---

## Taula de base de dades: `ciudadania_certifications`

| Camp | Descripció |
|------|------------|
| `userid` | Usuari |
| `courseid` | Curs |
| `paymentcmid` | ID del mòdul de tornada que va activar el snapshot |
| `modules_json` | JSON amb la llista de mòduls aprovats (nom, nota, data, cmid, idnumber) |
| `total_modules` | Nombre de mòduls al snapshot |
| `total_hours` | Total hores (total_modules × 2) |
| `timecreated` | Timestamp del pagament |

> Per usuari i curs **sempre hi ha com a màxim 1 registre**. El re-pagament esborra l'anterior.

---

## Llindars i constants clau

| Constant | Valor | On canviar-ho |
|----------|-------|---------------|
| Nota mínima per mòdul | **35/100** | `snapshot_manager.php` |
| Mitja mínima elegibles | **70/100** | `snapshot_manager.php` |
| Mòduls obligatoris | **M1, M2, M3, M4, M10** | `snapshot_manager.php` (`$mandatory_idnumbers`) |
| Hores per mòdul | **2h** | `element/hourscalculated/classes/element.php` |
| Paraules clau URL pagament | `pagament`, `payment`, `pago`, `certificat oficial` | `observer.php` (`is_payment_url()`) |
| Paraules clau URL tornada | `tornada`, `retorn` | `observer.php` (`reset_tornada_completion()`) |

---

## Guia de configuració a Moodle

Aquesta secció explica com configurar cada recurs del curs perquè el sistema funcioni correctament. Cal fer-ho com a professor editor o administrador.

> **Prerequisit**: instal·lar els plugins anant a **Administració del lloc → Notificacions** després de posar els fitxers al servidor.

---

### 1. Mòduls SCORM (M1–M14)

Per a cadascun dels 14 mòduls SCORM:

1. Entra a **Edita la configuració** del mòdul
2. A l'apartat **General**, emplena el camp **Número d'identificació** (ID number) amb el valor corresponent: `M1`, `M2`, ..., `M14`
3. A l'apartat **Seguiment de l'activitat**, activa la compleció i configura-la com: *L'estudiant ha d'obtenir una qualificació aprovada*

> El camp ID number és el que el codi utilitza per identificar els mòduls obligatoris (M1, M2, M3, M4, M10).

---

### 2. URL de crida al pagament

És el botó que l'estudiant clica per anar a la passarel·la de pagament.

**Configuració general:**
- **Nom**: qualsevol nom descriptiu que **no contingui** les paraules `pagament`, `payment`, `pago` ni `certificat oficial` (si el nom les conté, l'observer el tractaria com a URL de tornada i crearia un snapshot incorrecte)
- **URL externa**: l'adreça de la passarel·la de pagament
- **Seguiment de l'activitat**: desactivat (no cal marcar-la com a completada)

**Condicions de disponibilitat** (afegir totes dues en mode "TOTES han de complir-se"):

| # | Condició | Configuració |
|---|----------|-------------|
| 1 | **Compleció d'activitat** | Selecciona la *URL de tornada* → condició: **ha de NO estar marcada** com a completada |
| 2 | **Compleix condicions del diploma** | Condició `availability_ciutadania_diploma` → mode normal (no invertit) |

Amb aquesta configuració:
- Si la tornada ja s'ha completat (usuari ja ha pagat) → botó **ocult**
- Si la mitja actual és < 70 o falten obligatoris → botó **ocult**
- Si tot es compleix → botó **visible i accessible**

---

### 3. URL de tornada del pagament

És l'adreça a la qual redirigeix la passarel·la un cop el pagament és correcte. Quan Moodle la marca com a completada, el codi crea el snapshot i genera el certificat.

**Configuració general:**
- **Nom**: ha de contenir `tornada` o `retorn` **i** una paraula de pagament. Exemples vàlids:
  - `Tornada de pagament` ✅
  - `Retorn pagament` ✅
- **URL externa**: l'adreça de retorn que li dones a la passarel·la (ha de contenir `pagament` o `payment` a la URL, o bé el nom ja ho cobreix)
- **Seguiment de l'activitat**: activat → *L'estudiant ha de visualitzar aquesta activitat*

**Condicions de disponibilitat**: cap (ha de ser sempre accessible perquè la passarel·la hi pugui redirigir).

> **Com funciona**: quan la passarel·la redirigeix l'usuari a aquesta URL, Moodle la marca automàticament com a "visualitzada" → l'observer detecta la compleció → crea el snapshot → el certificat queda disponible → la URL de crida queda bloquejada (tornada = completa).

---

### 4. Certificat oficial (CustomCert)

És l'activitat que genera el PDF del diploma. Només és accessible després del pagament.

**Condicions de disponibilitat:**

| Condició | Configuració |
|----------|-------------|
| **Ha realitzat pagament** | Condició `availability_ciudadania` → mode normal (no invertit) |

**Configuració dels elements del certificat** (des de *Edita el contingut del certificat*):

Afegeix els elements estàndard que necessitis (nom, data, etc.) i a més:

#### Element: Mòduls aprovats (`approvedmodules`)

- **Mode**: *Mòduls del darrer certificat* (`certified`) — mostra sempre el contingut del snapshot del pagament, no l'estat actual
- **Mostrar notes**: segons preferència

> Usa el mode `certified` i no `current` perquè el contingut del certificat ha de reflectir la formació del moment del pagament, no l'estat present.

#### Element: Hores calculades (`hourscalculated`)

- Sense configuració addicional. Calcula automàticament: mòduls del snapshot × 2 hores.

---

### 5. Resum de la configuració de disponibilitat

| Recurs | Visible quan... |
|--------|-----------------|
| **URL de crida** | Tornada NO completada **I** condicions diploma complides (mitja ≥70 + obligatoris ≥35) |
| **URL de tornada** | Sempre accessible |
| **Certificat oficial** | Usuari té algun registre de pagament (ara i sempre) |
| **Altres recursos post-certificació** | Opcional: usar `availability_ciudadania` per restringir-los al pagament previ |

---

### 6. Verificació del funcionament

Llista de comprovació per confirmar que tot funciona:

- [ ] Tots els mòduls SCORM tenen el camp **ID number** emplenat (M1–M14)
- [ ] La URL de tornada té un nom que conté `tornada` o `retorn` i una paraula de pagament
- [ ] La URL de crida **no** té cap paraula de pagament al nom
- [ ] La URL de crida té les dues condicions de disponibilitat configurades
- [ ] El certificat té la condició `availability_ciudadania` configurada
- [ ] L'element `approvedmodules` del certificat està en mode `certified`
- [ ] El plugin `availability_ciutadania_diploma` apareix a **Administració → Plugins → Condicions de disponibilitat**

---

## Decisions d'arquitectura importants

### Per què la crida URL té doble condició de disponibilitat?

Sense la segona condició (comprovació de mitja en temps real), podria passar:
1. M5 completat → mitja=72 → tornada reiniciada → crida desblocada
2. M6 completat → mitja=69.9 → tornada ja estava incompleta, no canvia
3. L'usuari accedeix a la crida → **paga amb mitja < 70** → error

La condició de Moodle en temps real sobre `get_current_approved_modules()` evita aquest cas.

### Per què el snapshot esborra l'anterior en lloc d'acumular?

El certificat sempre representa **l'últim pagament**. No té sentit tenir múltiples certificats actius del mateix curs per al mateix usuari. Si es vol un certificat actualitzat, cal pagar de nou i el nou substitueix l'anterior.

### Per què l'observer no re-bloqueja la tornada quan la mitja baixa?

Perquè la condició de disponibilitat en temps real a la URL de crida ja ho gestiona. L'observer té una única responsabilitat: **desbloquejar la possibilitat de re-pagar quan apareixen mòduls nous elegibles**. La validació de si les condicions es compleixen en el moment del pagament és responsabilitat de la condició de disponibilitat.
