---
trigger: always_on
---

# Contexto: RDA Urgencias - Diagnósticos (Caja 3)

**Objetivo:** Agregar los recursos `Condition` (Diagnósticos CIE10) al array del servicio monolítico `RdaUrgenciasService.php`.

**Reglas:**
- Se deben crear dos recursos `Condition`: uno para el diagnóstico de ingreso (`Condition-0`) y otro para el de egreso (`Condition-1`).
- Ambos deben usar el perfil `ConditionRDA` y estar en estado `active`.
- El código (`code.coding`) debe mapear con los diagnósticos de CIE10 reales de nuestra base de datos asociados al Ingreso.
- Los IDs generados (`Condition-0`, `Condition-1`) deben coincidir con las referencias que pusimos en el recurso `Encounter` de la Caja 2.
- El sujeto (`subject.reference`) debe apuntar dinámicamente al ID del Paciente.

**Fragmento JSON esperado:**
```json
[
  {
    "resource": {
      "resourceType": "Condition",
      "id": "Condition-0",
      "meta": { "profile": ["[https://fhir.minsalud.gov.co/rda/StructureDefinition/ConditionRDA](https://fhir.minsalud.gov.co/rda/StructureDefinition/ConditionRDA)"] },
      "clinicalStatus": { "coding": [ { "system": "[http://terminology.hl7.org/CodeSystem/condition-clinical](http://terminology.hl7.org/CodeSystem/condition-clinical)", "code": "active", "display": "Active" } ] },
      "category": [ { "coding": [ { "system": "[http://terminology.hl7.org/CodeSystem/condition-category](http://terminology.hl7.org/CodeSystem/condition-category)", "code": "encounter-diagnosis", "display": "Encounter Diagnosis" } ] } ],
      "code": { "coding": [ { "system": "[http://hl7.org/fhir/sid/icd-10](http://hl7.org/fhir/sid/icd-10)", "code": "K359", "display": "APENDICITIS AGUDA, NO ESPECIFICADA" } ], "text": "Apendicitis Aguda" },
      "subject": { "reference": "#RC-1232832630" }
    }
  },
  {
    "resource": {
      "resourceType": "Condition",
      "id": "Condition-1",
      "meta": { "profile": ["[https://fhir.minsalud.gov.co/rda/StructureDefinition/ConditionRDA](https://fhir.minsalud.gov.co/rda/StructureDefinition/ConditionRDA)"] },
      "clinicalStatus": { "coding": [ { "system": "[http://terminology.hl7.org/CodeSystem/condition-clinical](http://terminology.hl7.org/CodeSystem/condition-clinical)", "code": "active", "display": "Active" } ] },
      "category": [ { "coding": [ { "system": "[http://terminology.hl7.org/CodeSystem/condition-category](http://terminology.hl7.org/CodeSystem/condition-category)", "code": "encounter-diagnosis", "display": "Encounter Diagnosis" } ] } ],
      "code": { "coding": [ { "system": "[http://hl7.org/fhir/sid/icd-10](http://hl7.org/fhir/sid/icd-10)", "code": "K352", "display": "APENDICITIS AGUDA, NO ESPECIFICADA" } ], "text": "Apendicitis Aguda con Peritonitis" },
      "subject": { "reference": "#RC-1232832630" }
    }
  }
]