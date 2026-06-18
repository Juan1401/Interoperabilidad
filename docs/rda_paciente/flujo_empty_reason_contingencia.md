# Documentación Técnica: Implementación de Contingencia EmptyReason en RDA

Este documento expone la transición arquitectónica y la refactorización de la lógica implementada en el sistema para dar cumplimiento estricto a las validaciones del estándar HL7 FHIR R4. Se detalla cómo la plataforma ahora gestiona orgánicamente la ausencia de información clínica, abandonando los valores predeterminados estáticos a favor de las directivas declarativas `EmptyReason`.

## 1. Fundamento de la Implementación (FHIR Compliance)

En versiones anteriores, ante la ausencia de un dato (por ejemplo, si el paciente no registraba alergias en su historia clínica), el sistema instanciaba forzosamente un recurso con datos simulados extraídos de los catálogos ("Sin alergias" - ID 35, "Paracetamol" - ID 626). 
Para optimizar el uso de los *Bundles* y adherirse al estándar semántico de interoperabilidad, este flujo fue reemplazado. El sistema ahora omite la creación de dichos recursos "fantasma" y, en su lugar, justifica la carencia de los mismos directamente en el índice del documento (`Composition`) a través de un código de justificación o `EmptyReason`.

---

## 2. Refactorización de Recursos Base (Constructores)

Los métodos dedicados a construir las taxonomías fueron ajustados para reaccionar ante una contingencia nula. Se afectaron tres dominios principales:
1. `buildAllergyIntoleranceResource`
2. `buildFamilyMemberHistoryResource`
3. `buildMedicationStatementResource`

### Acciones Realizadas:
- **Modificación de Firmas:** Las funciones se definieron con el tipo de retorno `?array` para poder responder legítimamente de forma nula.
- **Eliminación del Fallback ("Else"):** Se borraron todos los bloques alternos que previamente invocaban elementos comodín. 
- **Retorno Limpio:** Si el *flag* dinámico es `false` (no hubo consultas en BD o vinieron vacías), la función ahora aborta de inmediato su bloque central y ejecuta un `return null;`.

---

## 3. Orquestación Segura en el Bundle (`getDataForRda`)

Dado que ahora los constructores pueden arrojar retornos nulos, se modificó el punto donde el `Bundle` consolida su matriz de salida (`$resources[]`). 

**Comportamiento Actualizado:**
Cada llamado a un constructor es capturado localmente y verificado a través de un condicional `if ($recurso)`. 
```php
$medicationResource = $this->buildMedicationStatementResource($medicationFlag, $patientId, $medicationData);
if ($medicationResource) {
    $resources[] = $medicationResource;
}
```
Esto anula el riesgo de inyectar índices vacíos (`[null]`) en el JSON masivo, previniendo rupturas graves de validación de esquemas en Minsalud.

---

## 4. Estructuración del `Composition` (El Índice LOINC)

El mayor impacto técnico radica en la reescritura del método `buildCompositionResource`. 

### 4.1 Creación del Bloque Maestro de Contingencia
Se estableció un bloque inmutable que contiene el estándar codificado por HL7 para reportar ausencias de lista:
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
*`nilknown` significa "No consta / No conocido" en el diccionario semántico FHIR.*

### 4.2 Lógica Condicional de Secciones
Cada sección del documento (Alergias, Antecedentes, Medicamentos) es evaluada con base en su bandera:

- **Si la bandera es `true`:** La sección se construye insertando el arreglo asociativo `"entry" => [["reference" => "#NombreDelRecurso-0"]]`. Esto le dice al documento: "Ve y busca el detalle en el recurso con este ID".
- **Si la bandera es `false`:** El atributo `"entry"` queda totalmente excluido (cumpliendo con la cardinalidad FHIR que dicta que no deben mezclarse `entries` y `emptyReasons` en una misma lista). A cambio, mediante la función nativa `array_merge`, el sistema fusiona la metadata del título y el código LOINC con nuestro bloque maestro de contingencia (`$emptySectionData`), reportando oficialmente la ausencia justificada del hallazgo.

### 4.3 Excepciones del Flujo
La sección de **Historial de diagnósticos (Problem list)** no fue sometida a esta regla contingente, respetando la estructura core de que siempre debe reportarse al menos un diagnóstico principal de atención (ingreso/egreso) de acuerdo a los perfiles de la guía colombiana.
