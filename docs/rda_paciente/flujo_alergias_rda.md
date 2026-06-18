# Documentación Técnica: Flujo Dinámico de Alergias (AllergyIntolerance) en RDA

Este documento detalla el funcionamiento del flujo de extracción y ensamblaje de alergias en la generación de documentos HL7 FHIR (RDA), asegurando el correcto cumplimiento del estándar y la interacción dinámica con la base de datos de la IPS.

## 1. Arquitectura y Reutilización

El flujo de las alergias se dividió en dos responsabilidades claramente delimitadas, implementando buenas prácticas de herencia orientada a objetos:
- **`RdaService` (Clase Padre)**: Centraliza la consulta a la base de datos a través del método `getPatientAllergyData()`. Esto permite que otras clases hijas (como `RdaPacienteService`, `RdaUrgenciasService` o `RdaHospitalizacionService`) puedan invocar el método sin tener que duplicar el código de acceso a datos y las validaciones de caracteres.
- **`RdaPacienteService` (Clase Hija)**: Orquesta la construcción estructural del recurso final FHIR a través del patrón Builder usando el método `buildAllergyIntoleranceResource()`.

---

## 2. Paso 1: Extracción de Datos (`getPatientAllergyData`)

El método `getPatientAllergyData($ingresoId)` extrae la alergia documentada más reciente asociada a las evoluciones del paciente, ejecutando las siguientes tareas de fondo:

### 2.1 Consulta a la Base de Datos
La consulta en formato *Query Builder* replica de forma segura el siguiente cruce de datos:
1. Parte desde los `ingresos` (`public.ingresos`).
2. Busca la evolución clínica activa o relacionada (`public.hc_evoluciones`).
3. Cruza con los antecedentes personales registrados en dicha evolución (`public.hc_antecedentes_personales`).
4. Obtiene el detalle de dichos antecedentes (`public.hc_tipos_antecedentes_detalle_personales`).
5. Finalmente cruza con el catálogo centralizado que homologa los códigos hacia MinSalud (`ihce.cat_tipos_alergia`) usando un `INNER JOIN` para asegurar que el registro corresponde a una alergia validada.
Se ordena por fecha de evolución (`ORDER BY e.fecha DESC`) de tal manera que `first()` entregue siempre la última alergia informada por el profesional.

### 2.2 Blindaje UTF-8 y Fallback
Para prevenir problemas de codificación (caracteres extraños o errores al armar el JSON final), se procesan los campos de texto devueltos:
- Se evalúa la descripción oficial (`display`) mediante `mb_check_encoding(..., 'UTF-8')`. Si la codificación original difiere, se fuerza su conversión a UTF-8 usando `mb_convert_encoding(..., 'UTF-8', 'ISO-8859-1')`.
- Se evalúa el texto libre que ingresó el médico en el sistema (`text`). 
- **Lógica de Fallback:** Si el médico no digitó ningún detalle adicional (es decir, el campo de texto viene vacío o nulo), el sistema automáticamente clona la descripción oficial del catálogo (`display`) y la inyecta como texto para evitar enviar un nodo vacío o inválido a la plataforma Minsalud.

---

## 3. Paso 2: Evaluación del Flag Dinámico (`$allergyFlag`)

Dentro de la función central de recolección de información (ej. `getDataForRda()`), se dispara la lógica principal:

```php
$allergyData = $this->getPatientAllergyData($ingresoId);
$allergyFlag = $allergyData !== null;
```

Se interroga a la base de datos a través del servicio padre. Si se obtienen registros, la bandera `$allergyFlag` pasa a ser `true` y el array con los datos (`$allergyData`) se almacena en memoria. En caso contrario, si no existen registros, `$allergyFlag` se evalúa como `false`.

---

## 4. Paso 3: Construcción del Recurso FHIR (`buildAllergyIntoleranceResource`)

Este método ensambla los nodos JSON del recurso `AllergyIntolerance`. Recibe por parámetros el comportamiento deseado (`$allergyFlag`) y los datos recuperados (`$allergyData`). 

### Escenario A: El paciente SÍ tiene alergias registradas (`$allergyFlag === true`)
1. Se consultan de manera local los catálogos del sistema para establecer los identificadores base: `clinicalStatus` (generalmente Activa - ID 40) y `verificationStatus` (generalmente No confirmada - ID 36).
2. Para el tipo de alergia, se consume el catálogo HL7 `TipoAlergia` mediante la función genérica `$this->getHl7CatalogItemByName()`. El objetivo primordial de este llamado es **extraer el identificador oficial del catálogo (la URL del atributo `system`)** sin dejar URLs estáticas ("quemadas") en el código.
3. El `code` y el `display` por defecto que devuelve la llamada al catálogo son reemplazados (sobrescritos) dinámicamente por los datos que extrajimos desde nuestra base de datos de atención (`$allergyData['code']`, `$allergyData['display']`).
4. Finalmente, se mapea el `$textAllergy` tomando el texto blindado (o su fallback).

### Escenario B: El paciente NO tiene alergias registradas (`$allergyFlag === false`)
1. Si el sistema corrobora que no hay datos en la BD, arma un recurso genérico de "salida por defecto".
2. Se consume el catálogo `TipoAlergia` solicitando de manera explícita el **ID 35**, que en la nomenclatura homologada corresponde a la respuesta **"Sin Alergias / No reporta"**.
3. El texto explicativo se asigna a `"Sin alergias"`. Esto previene que la conformación del documento falle y responde satisfactoriamente a la exigencia obligatoria de MinSalud para la cardinalidad del recurso (inclusive cuando no hay enfermedades).

---

## 5. Impacto en el Bundle Final (`Composition`)

Dentro del ensamblaje definitivo del documento:
- El recurso `Composition` evalúa este mismo `$allergyFlag` y sin importar el resultado, añade el componente a su índice maestro de información.
- La sección `Historial de alergias, intolerancias y reacciones adversas` (LOINC `48765-2`) se enlazará siempre apuntando de forma interna al recurso autogenerado (`reference: "#AllergyIntolerance-0"`).
- Todo este flujo cumple íntegramente con las validaciones del perfil semántico `AllergyIntoleranceStatementRDA` que Minsalud solicita para sus servicios web.
