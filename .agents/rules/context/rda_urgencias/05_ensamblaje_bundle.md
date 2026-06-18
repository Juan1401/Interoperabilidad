---
trigger: always_on
---

# Contexto: RDA Urgencias - Ensamblaje Final (Caja 5)

**Objetivo:** Crear el recurso `Composition` y envolver todos los recursos generados previamente en un `Bundle` de tipo `document`.

**Reglas del Composition:**
- Debe ser SIEMPRE el primer recurso (`entry[0]`) dentro del `Bundle`.
- `subject`, `encounter`, `author` y `custodian` deben enlazar a los IDs dinámicos que ya generamos (Paciente, Médico, IPS y Encounter).
- El arreglo `section` es el índice: cada sección tiene un código LOINC específico y en `entry` hace referencia a los recursos (Observations, Conditions, DocumentReference).

**Reglas del Bundle:**
- Debe tener `resourceType: "Bundle"`, `type: "document"`.
- `timestamp` debe ser la fecha y hora actual de la generación del documento en formato FHIR ISO 8601.
- `entry` contendrá el Composition de primero, seguido de TODOS los recursos que ya construimos en Cajas 1 a 4.

**Fragmento JSON esperado del Composition (Validado por QA):**
```json
{
  "resourceType": "Composition",
  "id": "Composition-0",
  "meta": { "profile": ["[https://fhir.minsalud.gov.co/rda/StructureDefinition/CompositionEmergencyRDA](https://fhir.minsalud.gov.co/rda/StructureDefinition/CompositionEmergencyRDA)"] },
  "status": "final",
  "type": { "coding": [ { "system": "[http://loinc.org](http://loinc.org)", "code": "59258-4", "display": "Emergency department Discharge summary" } ] },
  "subject": { "reference": "#RC-1232832630" },
  "encounter": { "reference": "#Encounter-0" },
  "date": "2020-11-20T10:45:00-05:00",
  "author": [ { "reference": "#CC-1113636857" } ],
  "title": "RDA Urgencias",
  "confidentiality": "N",
  "attester": [ { "mode": "legal", "party": { "reference": "#RC-1232832630" } } ],
  "custodian": { "reference": "#7600102541" },
  "section": [
    { "title": "Entidad(es) responsable(s)", "code": { "coding": [ { "system": "[http://loinc.org](http://loinc.org)", "code": "48768-6" } ] }, "entry": [ { "reference": "#7600102541" } ] },
    { "title": "Otros datos demográficos", "code": { "coding": [ { "system": "[http://loinc.org](http://loinc.org)", "code": "74208-0" } ] }, "entry": [ { "reference": "#Observation-0" } ] },
    { "title": "Clasificación de triaje", "code": { "coding": [ { "system": "[http://loinc.org](http://loinc.org)", "code": "54094-8" } ] }, "entry": [ { "reference": "#Observation-2" } ] },
    { "title": "Datos incapacidad", "code": { "coding": [ { "system": "[http://loinc.org](http://loinc.org)", "code": "105583-9" } ] }, "entry": [ { "reference": "#Observation-1" } ] },
    { "title": "Historial de diagnósticos", "code": { "coding": [ { "system": "[http://loinc.org](http://loinc.org)", "code": "11450-4" } ] }, "entry": [ { "reference": "#Condition-0" }, { "reference": "#Condition-1" } ] },
    { "title": "Documentos de soporte", "code": { "coding": [ { "system": "[http://loinc.org](http://loinc.org)", "code": "55107-7" } ] }, "entry": [ { "reference": "#DocumentReference-0" } ] }
  ]
}