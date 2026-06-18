# Documentación Técnica: Flujo Dinámico de Antecedentes Familiares (FamilyMemberHistory) en RDA

Este documento detalla el procedimiento de refactorización aplicado al proceso de interoperabilidad para integrar dinámicamente los antecedentes familiares de un paciente directamente desde la base de datos de la IPS al estándar HL7 FHIR.

## 1. Arquitectura y Ubicación de la Lógica

Para asegurar que la extracción de datos se realice de manera escalable y que no deba repetirse el código en cada modalidad (Urgencias, Hospitalización, etc.), la lógica se ha dividido utilizando herencia orientada a objetos:
- **`RdaService` (Clase Padre)**: Aloja ahora la función de consulta a la base de datos (`getPatientFamilyHistoryData`).
- **`RdaPacienteService` (Clase Hija)**: Contiene el orquestador principal (`getDataForRda`) y el constructor específico del recurso FHIR (`buildFamilyMemberHistoryResource`).

---

## 2. Extracción de Datos: `getPatientFamilyHistoryData()`

El método protegido en `RdaService` realiza la consulta y devuelve los datos del antecedente familiar reportado más recientemente, aplicando un formato seguro.

### 2.1 Mapeo del SQL a Query Builder
La consulta se implementó uniendo nativamente las tablas relevantes del sistema:
1. Parte de la tabla `public.ingresos`.
2. Busca la evolución clínica a través de `public.hc_evoluciones`.
3. Extrae los antecedentes médicos vinculados cruzando con `public.hc_antecedentes_familiares`.
4. Relaciona el catálogo oficial de IHCE para obtener el **Parentesco** (`tipos_parentescos`).
5. Trae la información descriptiva de la **Enfermedad** (`public.diagnosticos`).
La consulta es ordenada con `ORDER BY e.fecha DESC` y usa `first()` para obtener el antecedente familiar más reciente documentado.

### 2.2 Blindaje UTF-8 y Manejo de Caracteres
Se ha aplicado el estándar de codificación de la plataforma. Para cada texto susceptible de presentar tildes o caracteres especiales (como la descripción del parentesco o la descripción del diagnóstico), se efectúa una validación:
```php
mb_check_encoding($campoRaw, 'UTF-8') ? $campoRaw : mb_convert_encoding($campoRaw, 'UTF-8', 'ISO-8859-1');
```
Esto certifica que el JSON FHIR no genere errores al intentar ser decodificado y enviado vía API a MinSalud.

---

## 3. Orquestador: Evaluación del Flag Dinámico

En el flujo principal (`getDataForRda`), se eliminó el hardcodeo previo (`$familyHistoryFlag = false;`) reemplazándolo por una evaluación de existencia real.
```php
$familyHistoryData = $this->getPatientFamilyHistoryData($ingresoId);
$familyHistoryFlag = $familyHistoryData !== null;
```
Esto habilita al orquestador para inyectar este flag de decisión y este arreglo de datos dinámicos hacia el método constructor.

---

## 4. Ensamblaje FHIR: `buildFamilyMemberHistoryResource()`

Se ajustó la firma del método constructor para que reciba la información pre-procesada de la base de datos `(?array $familyHistoryData = null)`.

### 4.1 Escenario A: Existen antecedentes en BD (`$familyHistoryFlag === true`)
1. **Status**: Se mantiene el catálogo por defecto con ID 51 (`completed`), asegurando consistencia con las reglas de negocio de finalización de evento.
2. **Diagnóstico (Enfermedad)**: A diferencia de los otros elementos, los diagnósticos CIE-10 no necesitan consultar la base de datos de catálogos HL7 interna, ya que provienen del diccionario estándar de la OMS (`http://hl7.org/fhir/sid/icd-10`). El arreglo del `condition` se arma insertando manualmente esta URL base junto al `code` y el `display` provenientes de la consulta de base de datos.
3. **Parentesco (`relationship`)**: Se evita "quemar" la URL del catálogo. Para esto se llama a `$this->getHl7CatalogItemByName('ParentescoAntecedente', 55)` y **solamente** se toma de allí la URL (`system`), mientras que el código y el display devueltos se reemplazan por los correspondientes al familiar de la base de datos (por ejemplo, si en la BD se documentó una tía, se reemplaza la base que traía el catálogo con los valores de la tía).

### 4.2 Escenario B: No existen antecedentes (`$familyHistoryFlag === false`)
1. Se preservó **completamente intacta** la lógica original de "fallback".
2. Ante la ausencia de un antecedente para informar, la API arma automáticamente un JSON genérico compuesto por:
   - Diagnóstico: `Z769` (Persona en contacto con servicios de salud).
   - Relación familiar: Padre/Madre (ID 55).
   - Estatus: Completado (ID 51).

**Regla Cumplida de Preservación de Comentarios**: Todas las notas y documentaciones insertadas originalmente que describen los IDs de Minsalud permanecieron inalteradas para futuras auditorías o mantenimientos.

---

## 5. Integración Final

Una vez generado, el recurso resultante se empaqueta de forma ordenada dentro del arreglo maestro `$resources`. 
De manera simultánea, la bandera `$familyHistoryFlag` viaja como parámetro hacia la función constructora del recurso `Composition`, indicando si el índice HL7 debe documentar el hallazgo orgánico, logrando así que la interoperabilidad del Bundle quede completa y exenta de fallos de lógica de referencias cruzadas.
