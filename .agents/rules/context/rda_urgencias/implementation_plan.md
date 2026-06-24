# RDA Urgencias – Caja 1: Datos Demográficos (Gap Analysis + Implementación)

## Contexto y problema

El `RdaUrgenciasService` es actualmente un stub vacío. Necesitamos generar un Bundle FHIR con tres recursos (`Patient`, `Practitioner`, `Organization`) que cumpla el JSON validado por QA de Minsalud.

Al comparar el JSON objetivo con el código actual del `RdaPacienteService`, encontramos:

### Brechas identificadas (Gap Analysis)

| Aspecto | RDA Paciente (actual) | RDA Urgencias (objetivo) | Brecha |
|---|---|---|---|
| **Profile Patient** | `PatientRDA` ✅ | `PatientRDA` ✅ | Igual |
| **Extensiones paciente** | Nationality, Ethnicity, Disability, **GenderIdentity** | Nationality, Ethnicity, Disability (**sin GenderIdentity**) | RDA Urgencias NO lleva `ExtensionPatientGenderIdentity` |
| **Identifier id** | Sin `id` en el identifier | `"id": "NationalPersonIdentifier-0"` | Falta el campo `id` dentro del objeto identifier |
| **`_gender`** | Presente ✅ | Presente ✅ | Igual |
| **`birthDate`** | Presente ✅ | Presente ✅ | Igual |
| **`active`** | Presente (`true`) | **Ausente** | RDA Urgencias NO lleva campo `active` |
| **`deceasedBoolean`** | Presente (`false`) | **Ausente** | RDA Urgencias NO lleva campo `deceasedBoolean` |
| **Practitioner – identifier** | tipo incluye v2-0203 (PN) + ColombianPersonIdentifier | Solo ColombianPersonIdentifier | RDA Urgencias usa identifier simplificado (sin PN) |
| **Practitioner – `_family`** | Tiene la extensión `_family` | **Sin `_family`** | RDA Urgencias Practitioner es más simple |
| **Practitioner – `use` en name** | `"use": "official"` | **Sin `use`** | Diferencia menor |
| **Organization – identifier** | Dos identificadores (TAX + REPS) | **Solo REPS** simplificado | RDA Urgencias usa un solo identifier sin `id` ni `type` |
| **DIVIPOLA – origen del dato** | Hardcodeado al ID 1006 (Cali) | Debe derivarse del municipio real del paciente | **BRECHA MAYOR**: falta mapear municipio del HIS al código DIVIPOLA |
| **Etnia / Discapacidad** | Hardcodeados a valores fijos ('6', '08') | Deben venir del paciente | **BRECHA MAYOR**: faltan campos en el modelo/BD |
| **Género biológico** | Hardcodeado al ID '1' (siempre Hombre) | Debe derivarse del sexo del paciente | **Bug actual**: ignora el sexo real del paciente |

### Campos de BD faltantes (campos a agregar en el modelo `pacientes`/`personas`)

| Campo Nuevo | Tipo | Tabla | Propósito |
|---|---|---|---|
| `codigo_etnia` | `varchar(5)` | `pacientes` / `personas` | Código colombian ethnic group ('1'–'6') |
| `codigo_discapacidad` | `varchar(5)` | `pacientes` / `personas` | Código disability classification ('01'–'09') |
| `municipio_residencia_divipola_id` | `integer` | `pacientes` / `personas` | FK a `ihce.municipalities.id` |
| `zona_residencia` | `varchar(2)` | `pacientes` / `personas` | '01' Urbana, '02' Rural |

> [!IMPORTANT]
> Hasta que estos campos existan en BD, el servicio usará valores por defecto configurables (misma estrategia que hoy pero explícita). Los campos se agregan mediante una nueva migración.

---

## Decisión de arquitectura: Patrón Builder

En lugar de modificar el `RdaPacienteService` con condicionales `if ($tipo === 'urgencias')`, crearemos tres **Builders** independientes y reutilizables (Principio DRY):

```
app/Builders/Fhir/
  ├── FhirPatientBuilder.php        [NUEVO]
  ├── FhirPractitionerBuilder.php   [NUEVO]
  └── FhirOrganizationBuilder.php   [NUEVO]
```

Cada builder recibe los datos ya resueltos (de BD / catálogos) y devuelve el array PHP que corresponde al recurso FHIR, **sin tocar la BD**. Los servicios (`RdaPacienteService`, `RdaUrgenciasService`) son los responsables de recolectar los datos y llamar a los builders.

---

## Proposed Changes

### Component 1: Builders FHIR (nuevos)

#### [NEW] [FhirPatientBuilder.php](file:///c:/Docker_Tests/hl7interoperabilidad/iops_api/app/Builders/Fhir/FhirPatientBuilder.php)

Recibe DTO con datos del paciente y opciones de configuración. Genera el recurso `Patient` FHIR.

**Opciones configurables:**
- `includeGenderIdentity` (bool) → false para Urgencias, true para RDA Paciente
- `includeActive` (bool) → false para Urgencias
- `includeDeceased` (bool) → false para Urgencias

#### [NEW] [FhirPractitionerBuilder.php](file:///c:/Docker_Tests/hl7interoperabilidad/iops_api/app/Builders/Fhir/FhirPractitionerBuilder.php)

Genera el recurso `Practitioner` FHIR.

**Opciones configurables:**
- `includeHl7PN` (bool) → true = incluye el coding PN de v2-0203 (RDA Paciente), false = solo ColombianPersonIdentifier (Urgencias)
- `includeFamilyExtension` (bool) → true para RDA Paciente, false para Urgencias

#### [NEW] [FhirOrganizationBuilder.php](file:///c:/Docker_Tests/hl7interoperabilidad/iops_api/app/Builders/Fhir/FhirOrganizationBuilder.php)

Genera el recurso `Organization` FHIR.

**Opciones configurables:**
- `mode` (`'full'` | `'simple'`) → `'full'` = RDA Paciente (TAX + REPS), `'simple'` = RDA Urgencias (solo REPS)

---

### Component 2: Servicio RDA Urgencias

#### [MODIFY] [RdaUrgenciasService.php](file:///c:/Docker_Tests/hl7interoperabilidad/iops_api/app/Services/Hl7/RdaUrgenciasService.php)

Implementar completamente el método `getDataForRda()`:
1. Obtener ingreso → paciente → persona
2. Resolver catálogos (igual que `RdaPacienteService` pero sin flags de condición/alergias/medicamentos)
3. **Corregir bug de género**: mapear `sexo = 'F'` → id '2' ColombianGenderGroup, `'M'` → id '1'
4. Usar `FhirPatientBuilder` con `includeGenderIdentity=false`, `includeActive=false`, `includeDeceased=false`
5. Usar `FhirPractitionerBuilder` con `includeHl7PN=false`, `includeFamilyExtension=false`
6. Usar `FhirOrganizationBuilder` con `mode='simple'`
7. Retornar el array del Bundle con solo los tres recursos

---

### Component 3: Refactorización RdaPacienteService (DRY)

#### [MODIFY] [RdaPacienteService.php](file:///c:/Docker_Tests/hl7interoperabilidad/iops_api/app/Services/Hl7/RdaPacienteService.php)

Reemplazar los bloques inline de construcción de Patient, Practitioner y Organization por llamadas a los mismos Builders, con las opciones correspondientes (`includeGenderIdentity=true`, `includeActive=true`, `mode='full'`).

> [!NOTE]
> Este cambio es transparente: el JSON resultante del RDA Paciente debe ser **idéntico** al actual. La verificación cubrirá esto.

---

### Component 4: Migración de BD

#### [NEW] Migración `add_fhir_fields_to_personas_table.php`

```sql
ALTER TABLE personas ADD COLUMN codigo_etnia VARCHAR(5) NULL DEFAULT '6';
ALTER TABLE personas ADD COLUMN codigo_discapacidad VARCHAR(5) NULL DEFAULT '08';
ALTER TABLE personas ADD COLUMN municipio_residencia_divipola_id INTEGER NULL;
ALTER TABLE personas ADD COLUMN zona_residencia VARCHAR(2) NULL DEFAULT '01';
```

> [!WARNING]
> Esta migración requiere revisión para asegurarse de que la tabla `personas` es la correcta. Si los campos ya existen en otra tabla del HIS (sistema externo), no se necesita la migración; solo ajustar el modelo Eloquent.

---

## Verification Plan

### Revisión de estructura JSON

Guardar en `iops_api/cache/test_rda_urgencias_demograficos.php` un script que:
1. Instancie `RdaUrgenciasService` con un `ingresoId` de prueba
2. Llame a `getDataForRda($ingresoId)`
3. Serialize a JSON y haga `echo` del resultado

**Pasos:**
```bash
# Dentro del contenedor Docker
docker exec -it hl7_api php artisan tinker
# En tinker:
$svc = app(App\Services\Hl7\RdaUrgenciasService::class);
$data = $svc->getDataForRda(<INGRESO_ID>);
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
```

Comparar salida con el JSON del `01_demograficos.md`.

### Verificación de no-regresión RDA Paciente

```bash
# En tinker:
$svc = app(App\Services\Hl7\RdaPacienteService::class);
$data = $svc->getDataForRda(<INGRESO_ID>);
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
```

Verificar que la estructura del Bundle (Composition, Patient, Organization, Practitioner + recursos opcionales) sigue siendo generada correctamente.
