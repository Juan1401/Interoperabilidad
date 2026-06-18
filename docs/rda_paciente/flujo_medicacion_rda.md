# Documentación Técnica: Flujo Dinámico de Antecedentes Farmacológicos (MedicationStatement) en RDA

Este documento expone la arquitectura y la lógica implementadas para la extracción dinámica de los antecedentes de medicamentos de un paciente hacia su correspondiente recurso HL7 FHIR (`MedicationStatement`), diferenciándolos correctamente de las alergias a medicamentos.

## 1. Arquitectura de Extracción

A fin de garantizar la escalabilidad y adherirse a los principios de desarrollo orientados a objetos, la lógica se separó en dos responsabilidades principales:
- **Clase Padre (`RdaService`)**: Se encarga del acceso a datos mediante una consulta estructurada, lo que permite a múltiples servicios utilizar la función de forma genérica.
- **Clase Hija (`RdaPacienteService`)**: Invoca la capa de extracción y orquesta la inyección JSON dentro de los lineamientos del perfil oficial de Minsalud.

---

## 2. Obtención de los Datos: `getPatientMedicationData()`

La extracción del principio activo prescrito se realiza mediante un método protegido integrado en la clase base (`RdaService`), aplicando un orden descendente sobre las evoluciones (`ORDER BY e.fecha DESC LIMIT 1`) para recabar siempre el registro histórico más fresco.

### 2.1 Regla Crítica de Separación (Query Builder)
El Query Builder cruza de forma segura las tablas base:
1. `public.ingresos` y `public.hc_evoluciones` (Atención central).
2. `public.hc_antecedentes_personales` (Tabla contenedora del antecedente).
3. `public.inv_med_cod_principios_activos` (Tabla de medicamentos para extraer la descripción oficial).
4. `public.hc_tipos_antecedentes_detalle_personales` (Tabla de detalles para el cruce taxonómico).

**El factor diferenciador vital:** Al interior de este método, se instruyó a la consulta realizar un `->whereNull('tap.tipo_alergia')`. 
Esta validación es de extrema importancia clínica, ya que previene que los medicamentos a los cuales el paciente presenta sensibilidad alérgica (almacenados bajo la misma ramificación de antecedentes) terminen inyectándose de manera incorrecta como un registro de un tratamiento activo/histórico.

### 2.2 Validación de Codificación (Blindaje UTF-8)
El campo textual correspondiente a la descripción del medicamento (`medication_display`) se evalúa antes de ser empacado:
```php
$medicationDisplay = mb_check_encoding($campo, 'UTF-8') ? $campo : mb_convert_encoding($campo, 'UTF-8', 'ISO-8859-1');
```
Esto anula el riesgo de caracteres huérfanos generados desde las aplicaciones legacy y asegura el flujo de la mensajería web.

---

## 3. Dinámica del Flag Orquestador (`$medicationFlag`)

En el ensamblador general (`getDataForRda`), se eliminó la definición estática (`$medicationFlag = false;`) reemplazándola por el resultado de la función.
```php
$medicationData = $this->getPatientMedicationData($ingresoId);
$medicationFlag = $medicationData !== null;
```
Esto habilita al servicio para saber dinámicamente si debe armar un nodo con datos concretos o activar las rutinas de seguridad de *Fallback*.

---

## 4. Estructuración del Recurso FHIR (`buildMedicationStatementResource`)

La firma del constructor ahora inyecta el `$medicationData`.

### 4.1 Escenario A: Información Farmacológica Positiva (`$medicationFlag === true`)
1. Se asigna el `status` recuperándolo del catálogo oficial (`MedicationStatementStatusCodes`, ID 44 -> Completed).
2. Para establecer el identificador semántico del catálogo sin quemar URIs (como `https://fhir.minsalud.gov.co/rda/CodeSystem/MipresINN`), el código llama a `$baseSystem = $this->getMipresInnData('626');` para traer la infraestructura y la base.
3. El arreglo `$mipresInnData` se recompone en tiempo de ejecución de la siguiente forma: 
   - `system`: Utiliza el recolectado dinámicamente en el paso anterior.
   - `code` y `display`: Emplean los datos extraídos de la BD del paciente (`$medicationData['code']` y `$medicationData['display']`).
4. **Acuerdo de Comentarios:** Todo bloque de comentarios y ayudas orientadas al mantenimiento fueron preservados en su forma original.

### 4.2 Escenario B: Información Farmacológica Ausente (`$medicationFlag === false`)
1. Las convenciones originales de relleno quedan en su lugar para impedir la negación del documento frente al Minsalud.
2. Ante una variable nula o vacía, la API construye automáticamente un antecedente basado en **Paracetamol** (`code: 626`) junto con un estatus `Completed`, de tal forma que el validador estructural de esquemas R4 no identifique la ausencia del recurso.

---

## 5. Vinculación en el Bundle

El estado dinámico del flag también se transfiere al `Composition`, que registra fielmente al `MedicationStatement-0` en su matriz LOINC de índice de documento (bajo el código `10160-0` - *History of Medication use Narrative*), sellando así un ciclo de trazabilidad HL7 inquebrantable.
