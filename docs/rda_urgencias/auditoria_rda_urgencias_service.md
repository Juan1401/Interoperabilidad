# Informe de Auditoría y Estado Actual: RdaUrgenciasService

> **Fecha:** 27 de abril de 2026  
> **Archivo:** `iops_api/app/Services/Hl7/RdaUrgenciasService.php`  
> **Líneas totales:** 1050 | **Peso:** ~51 KB  
> **Perfil FHIR objetivo:** `CompositionEmergencyRDA` (Minsalud Colombia)

---

## 1. Arquitectura y Composición Actual de la Clase

### 1.1 Patrón de Diseño
La clase `RdaUrgenciasService` extiende de `RdaService` (clase base que centraliza el acceso a datos y catálogos HL7). Internamente aplica un **patrón Builder monolítico**: un método orquestador (`getDataForRda`) invoca secuencialmente a métodos privados constructores, cada uno responsable de armar un recurso FHIR específico. Al final, un método ensamblador (`assembleBundle`) envuelve todo en la estructura `Bundle` de tipo `document`.

### 1.2 Inventario de Métodos

| # | Método | Responsabilidad | Líneas |
|---|--------|----------------|--------|
| 1 | `getDataForRda(int $ingresoId)` | **Orquestador principal.** Extrae datos, calcula flags, invoca constructores y ensambla el Bundle. | L23–L153 |
| 2 | `buildPatientResource(...)` | Construye el recurso `Patient` (Caja 1 - Demográficos). | L155–L307 |
| 3 | `buildPractitionerResource(...)` | Construye el recurso `Practitioner` (Caja 1). | L309–L341 |
| 4 | `buildOrganizationResource(...)` | Construye el recurso `Organization` (Caja 1). | L343–L427 |
| 5 | `getEncounterDiagnoses(...)` | **Pre-cálculo.** Consulta BD para diagnósticos de ingreso/egreso, genera `Condition-0`, `Condition-1` y el array `encounter.diagnosis`. | L429–L553 |
| 6 | `buildEncounterResource(...)` | Construye el recurso `Encounter` (Caja 2). | L555–L604 |
| 7 | `buildObservationTriageResource(...)` | Construye `Observation-2` (Triaje). | L606–L627 |
| 8 | `buildObservationOccupationResource(...)` | Construye `Observation-0` (Ocupación CIUO88AC). | L629–L649 |
| 9 | `buildObservationIncapacidadResource(...)` | Construye `Observation-1` (Incapacidad SIPE) – **con consulta dinámica a BD.** | L651–L733 |
| 10 | `buildDocumentReferenceEpicrisisResource(...)` | Construye `DocumentReference-0` (Epicrisis PDF en Base64). | L735–L787 |
| 11 | `buildCompositionResource(...)` | Construye el `Composition` con todas las secciones LOINC (Caja 5). | L789–L922 |
| 12 | `assembleBundle(...)` | Ensambla `Bundle` tipo `document`: Composition primero, luego recursos. | L924–L959 |
| 13 | `validateRdaData(array $rdaData)` | Valida estructura mínima del Bundle antes de envío. | L967–L982 |
| 14 | `sendRdaUrgencias(...)` | Envío HTTP al API de IHCE/Minsalud con manejo de timeout y token OAuth. | L993–L1050 |

---

## 2. Cumplimiento FHIR y Manejo de Contingencias (EmptyReason)

### 2.1 Problema Original
Las versiones anteriores del Composition contenían secciones con atributos `"entry"` que referenciaban recursos inexistentes (ej. `#Condition-0` en la sección de Alergias, cuando no existía un recurso `AllergyIntolerance`). Esto provocaba:

- **Error `cmp-1`:** El validador FHIR exige que si una sección no tiene `entry`, debe tener un nodo `text` con contenido narrativo.
- **Errores de catálogo (`1008`, `6007`):** Minsalud rechazaba secciones con referencias huérfanas que no apuntaban a ningún recurso real dentro del Bundle.

### 2.2 Solución Implementada: `$emptySectionData`
Se definió en `buildCompositionResource` (línea 793) una estructura maestra reutilizable:

```php
$emptySectionData = [
    "text" => [
        "status" => "empty",
        "div" => "<div xmlns=\"http://www.w3.org/1999/xhtml\">Sin información disponible</div>"
    ],
    "emptyReason" => [
        "coding" => [
            [
                "system" => "http://terminology.hl7.org/CodeSystem/list-empty-reason",
                "code" => "nilknown",
                "display" => "Nil Known"
            ]
        ]
    ]
];
```

**Principios FHIR que cumple:**
- Satisface la restricción `cmp-1` al proveer un nodo `text` con `status: "empty"` y contenido narrativo en `div`.
- Utiliza el código `nilknown` del CodeSystem oficial `list-empty-reason` en lugar del anterior `NI` de `v3-NullFlavor`, que era incorrecto para este contexto.
- Al eliminar el atributo `"entry"`, se evita que el validador busque un recurso referenciado que no existe.

### 2.3 Sección de Incapacidad (Caso Dinámico)
La sección de incapacidad SIPE (LOINC `105583-9`) es el **único caso actualmente dinámico** en el Composition. Utiliza la bandera `$hasObservation1`:
- Si `true` → La sección incluye `"entry" => [["reference" => "#Observation-1"]]`.
- Si `false` → Se inyectan los campos `text` y `emptyReason` desde `$emptySectionData`.

---

## 3. Estado de Dinamización de las Secciones (Composition)

### 3.1 Clasificación de Secciones

| # | Sección (Título LOINC) | Código LOINC | Estado | Detalle |
|---|------------------------|-------------|--------|---------|
| 1 | Entidad(es) responsable(s) | `48768-6` | ✅ **Dinámica** | Siempre presente. Referencia `#Organization` (siempre existe). |
| 2 | Otros datos demográficos | `74208-0` | ⚠️ **Semi-estática** | Referencia `#Observation-0` (Ocupación). El recurso existe pero con datos **quemados** (CIUO88AC `9333`). |
| 3 | Clasificación de triaje | `54094-8` | ⚠️ **Semi-estática** | Referencia `#Observation-2` (Triaje). El recurso existe pero con código de triaje **quemado** (`03` - Triage III). |
| 4 | Datos incapacidad (SIPE) | `105583-9` | ✅ **100% Dinámica** | Controlada por `$hasObservation1`. Consulta real a `hc_incapacidades`. Aplica `emptyReason` correctamente si no hay datos. |
| 5 | Historial de diagnósticos | `11450-4` | ✅ **Dinámica** | Referencias `#Condition-0` y `#Condition-1`. Construidos dinámicamente por `getEncounterDiagnoses()` desde `hc_diagnosticos_ingreso` y `hc_diagnosticos_egreso`. |
| 6 | Historial de alergias | `48765-2` | 🔴 **Contingencia estática** | Forzada a `$emptySectionData`. No existe constructor ni flag. |
| 7 | Factores de riesgo | `75492-9` | 🔴 **Contingencia estática** | Forzada a `$emptySectionData`. No existe constructor ni flag. |
| 8 | Historial de medicamentos | `10160-0` | 🔴 **Contingencia estática** | Forzada a `$emptySectionData`. No existe constructor ni flag. |
| 9 | Historial de procedimientos | `47519-4` | 🔴 **Contingencia estática** | Forzada a `$emptySectionData`. No existe constructor ni flag. |
| 10 | Resultados de tecnologías | `30954-2` | 🔴 **Contingencia estática** | Forzada a `$emptySectionData`. No existe constructor ni flag. |
| 11 | Órdenes y prescripciones | `61146-1` | 🔴 **Contingencia estática** | Forzada a `$emptySectionData`. No existe constructor ni flag. |
| 12 | Documentos de soporte | `55107-7` | ✅ **Dinámica** | Siempre presente. Referencia `#DocumentReference-0` (Epicrisis PDF generada por Browsershot/SIIS). |

### 3.2 Resumen Visual

```
 Secciones del Composition (12 totales)
 ├── ✅ 100% Dinámicas ........... 4/12  (Organization, Incapacidad, Diagnósticos, Documentos)
 ├── ⚠️ Semi-estáticas ........... 2/12  (Ocupación, Triaje → recursos quemados)
 └── 🔴 Contingencia estática .... 6/12  (Alergias, Riesgos, Medicamentos, Procedimientos, Resultados, Órdenes)
```

---

## 4. Deuda Técnica y Hoja de Ruta (Next Steps)

### 4.1 Datos Quemados en Recursos Existentes (Prioridad Alta)

Estos recursos **ya se generan e inyectan** en el Bundle, pero con valores hardcodeados que deben migrar a consultas de BD:

| Recurso | Variable Quemada | Valor Actual | Acción Requerida |
|---------|-----------------|--------------|------------------|
| `Encounter` (L557-559) | `$causaExternaRipsCode` | `'38'` (Enfermedad General) | Crear query a tabla de causas externas RIPS del ingreso. |
| `Observation-2` Triaje (L608-609) | `$triageCode`, `$triageDisplay` | `'03'`, `'Triage III'` | Crear `getTriageData($ingresoId)` en `RdaService` consultando la tabla de clasificación de triaje. |
| `Observation-0` Ocupación (L632-633) | `$ciuo88acCode`, `$ciuo88acDisplay` | `'9333'`, `'Obreros de carga'` | Crear `getPatientOccupationData($ingresoId)` en `RdaService` consultando la ocupación real del paciente. |

### 4.2 Secciones Sin Implementar (Prioridad Media-Alta)

Para cada una de las **6 secciones en contingencia estática**, se necesita replicar el patrón ya establecido en `RdaPacienteService`:

| Sección | Query Builder a Crear | Flag a Implementar | Método Constructor a Crear | Recurso FHIR |
|---------|----------------------|--------------------|-----------------------------|-------------|
| Alergias (`48765-2`) | Reutilizar `getPatientAllergyData($ingresoId)` de `RdaService` | `$allergyFlag` | `buildAllergyIntoleranceResource(...)` | `AllergyIntolerance` |
| Factores de Riesgo (`75492-9`) | Crear `getPatientRiskFactorsData($ingresoId)` | `$riskFactorFlag` | `buildRiskFactorObservationResource(...)` | `Observation` (perfil de riesgo) |
| Medicamentos (`10160-0`) | Reutilizar `getPatientMedicationData($ingresoId)` de `RdaService` | `$medicationFlag` | `buildMedicationStatementResource(...)` | `MedicationStatement` |
| Procedimientos (`47519-4`) | Crear `getEncounterProceduresData($ingresoId)` | `$procedureFlag` | `buildProcedureResource(...)` | `Procedure` |
| Resultados (`30954-2`) | Crear `getEncounterResultsData($ingresoId)` | `$resultsFlag` | `buildDiagnosticReportResource(...)` | `DiagnosticReport` / `Observation` |
| Órdenes (`61146-1`) | Crear `getEncounterOrdersData($ingresoId)` | `$ordersFlag` | `buildServiceRequestResource(...)` | `ServiceRequest` |

### 4.3 Consultas SQL Potenciales (Pendientes de Validación con Analistas)

```sql
-- 1. Triaje (Observation-2)
SELECT ct.clase_triage, ct.descripcion
FROM hc_triage t
JOIN clases_triage ct ON t.clase_triage_id = ct.clase_triage_id
JOIN hc_evoluciones e ON e.evolucion_id = t.evolucion_id
WHERE e.ingreso = ?;

-- 2. Ocupación (Observation-0)
SELECT o.codigo_ciuo, o.descripcion
FROM ocupaciones o
JOIN personas p ON p.ocupacion_id = o.ocupacion_id
JOIN pacientes pa ON pa.persona_id = p.persona_id
JOIN ingresos i ON i.paciente = pa.paciente_id
WHERE i.ingreso = ?;

-- 3. Causa Externa RIPS (Encounter.reasonCode)
SELECT ce.codigo, ce.descripcion
FROM causas_externas ce
JOIN ingresos i ON i.causa_externa_id = ce.causa_externa_id
WHERE i.ingreso = ?;

-- 4. Procedimientos (Procedure)
SELECT p.cups_id, c.descripcion
FROM hc_procedimientos p
JOIN cups c ON c.cups_id = p.cups_id
JOIN hc_evoluciones e ON e.evolucion_id = p.evolucion_id
WHERE e.ingreso = ?;
```

> [!WARNING]
> Estas consultas son **propuestas iniciales**. Deben ser validadas contra el esquema real de PostgreSQL (especialmente el esquema `ihce` y `public`) antes de implementarse, siguiendo la regla de negocio de verificar siempre la estructura de BD.

### 4.4 Flags Faltantes en el Orquestador

Actualmente el orquestador (L80-L85) solo define **2 flags**:
```php
$hasCondition1   = false;  // No se usa activamente (siempre false)
$hasObservation1 = false;  // ✅ Se evalúa dinámicamente
```

Se necesitan agregar como mínimo **6 flags adicionales**:
```php
$allergyFlag     = false;  // Alergias
$riskFactorFlag  = false;  // Factores de riesgo
$medicationFlag  = false;  // Medicamentos
$procedureFlag   = false;  // Procedimientos
$resultsFlag     = false;  // Resultados diagnósticos
$ordersFlag      = false;  // Órdenes/prescripciones
```

### 4.5 Observaciones Adicionales

1. **Flag `$hasCondition1` sin uso:** Se declara en L84 pero **nunca se modifica**. Siempre llega como `false` al `assembleBundle`. Debería eliminarse o vincularse a la lógica real de `getEncounterDiagnoses`.

2. **Firma de `buildCompositionResource`:** Cuando se agreguen los flags faltantes, la firma del método crecerá significativamente. Se recomienda considerar el uso de un **DTO (Data Transfer Object)** o un arreglo asociativo `$sectionFlags` para mantener la firma limpia.

3. **Reutilización desde `RdaService`:** Los métodos `getPatientAllergyData()` y `getPatientMedicationData()` ya existen en la clase padre `RdaService` y pueden invocarse directamente desde `RdaUrgenciasService` sin duplicar código. Solo habrá que crear los métodos que aún no existen (Procedimientos, Resultados, Órdenes, Triaje, Ocupación, Causa Externa).

4. **Consistencia con `RdaPacienteService`:** El patrón de contingencia (`$emptySectionData` + `array_merge`) ya fue probado y validado exitosamente en el RDA de Paciente. Se recomienda replicar exactamente la misma mecánica de `if/else` por sección al momento de dinamizar las 6 secciones pendientes.
