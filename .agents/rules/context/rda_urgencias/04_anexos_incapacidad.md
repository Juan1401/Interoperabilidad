---
trigger: always_on
---

# Contexto: RDA Urgencias - Incapacidades y Anexos (Caja 4)

**Objetivo:** Agregar los recursos `Observation` (Datos de Incapacidad SIPE) y `DocumentReference` (PDF de la Epicrisis) al array del servicio monolítico `RdaUrgenciasService.php`.

**Reglas:**
- `Observation-1` (Incapacidad): Debe referenciar al Paciente. Los valores de días de incapacidad (`valueQuantity.value`) deben venir de la BD si el médico dio incapacidad. Si no, **no se debe generar este recurso** o se debe manejar con precaución.
- `DocumentReference-0` (Epicrisis PDF): Es el documento vital. El campo `content[0].attachment.data` DEBE contener el PDF de la epicrisis convertido a Base64. 
- Referencias locales: `DocumentReference-0` debe enlazar a `#RC-1232832630` (subject), `#7600102541` (author/Organization) y `#Encounter-0` (context.encounter).

**Fragmento JSON esperado (Validado por QA):**
```json
[
  {
    "resource": {
      "resourceType": "Observation",
      "id": "Observation-1",
      "meta": { "profile": ["[https://fhir.minsalud.gov.co/rda/StructureDefinition/AttendanceAllowanceRDA](https://fhir.minsalud.gov.co/rda/StructureDefinition/AttendanceAllowanceRDA)"] },
      "status": "final",
      "code": { "coding": [ { "system": "[http://snomed.info/sct](http://snomed.info/sct)", "code": "160983005", "display": "permiso de concurrencia" } ], "text": "Datos incapacidad (SIPE)" },
      "subject": { "reference": "#RC-1232832630" },
      "component": [
        { "id": "LicenseScope", "code": { "coding": [ { "system": "[http://snomed.info/sct](http://snomed.info/sct)", "code": "255590007", "display": "alcance" } ] }, "valueCodeableConcept": { "coding": [ { "system": "[https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianLicenseScope](https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianLicenseScope)", "code": "01", "display": "Nueva" } ] } },
        { "id": "MaternityLicenceTime", "code": { "coding": [ { "system": "[http://snomed.info/sct](http://snomed.info/sct)", "code": "410670007", "display": "tiempo" } ] }, "valueQuantity": { "value": 126, "unit": "días", "system": "[http://unitsofmeasure.org](http://unitsofmeasure.org)", "code": "d" } }
      ]
    }
  },
  {
    "resource": {
      "resourceType": "DocumentReference",
      "id": "DocumentReference-0",
      "meta": { "profile": ["[https://fhir.minsalud.gov.co/rda/StructureDefinition/DocumentReferenceEPIRDA](https://fhir.minsalud.gov.co/rda/StructureDefinition/DocumentReferenceEPIRDA)"] },
      "status": "current",
      "type": { "coding": [ { "system": "[http://loinc.org](http://loinc.org)", "code": "18842-5", "display": "Discharge summary" }, { "system": "[https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianDocumentTypes](https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianDocumentTypes)", "code": "EPI", "display": "Epicrisis" } ] },
      "category": [ { "coding": [ { "system": "[http://loinc.org](http://loinc.org)", "code": "55108-5", "display": "Clinical presentation Document" } ] } ],
      "subject": { "reference": "#RC-1232832630" },
      "date": "2020-11-20T10:45:00-05:00",
      "author": [ { "reference": "#7600102541" } ],
      "custodian": { "reference": "Organization/MinSalud" },
      "description": "Epicrisis del encuentro de atención en salud - RDA",
      "securityLabel": [ { "coding": [ { "system": "[http://terminology.hl7.org/CodeSystem/v3-Confidentiality](http://terminology.hl7.org/CodeSystem/v3-Confidentiality)", "code": "R", "display": "restricted" } ] } ],
      "content": [ { "attachment": { "language": "es-CO", "data": "U01BTExfUERG" }, "format": { "system": "urn:ietf:bcp:13", "code": "application/pdf", "display": "PDF" } } ],
      "context": { "encounter": [ { "reference": "#Encounter-0" } ] }
    }
  }
]