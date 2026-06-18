---
trigger: always_on
---

# Contexto: RDA Urgencias - Atención y Triaje (Caja 2)

**Objetivo:** Agregar los recursos `Encounter` (Atención en Urgencias) y `Observation` (Triaje y Ocupación) al servicio `RdaUrgenciasService.php`.

**Reglas:**
- El `Encounter` debe enlazar al Paciente (`#RC-1232832630`) y al Médico (`#CC-1113636857`) mediante referencias locales.
- Respetar los códigos de modalidad intramural, entorno institucional y grupo de servicios.
- Las fechas (`period.start` y `period.end`) deben venir de nuestra base de datos (Ingreso/Egreso).
- Si faltan datos en la BD para Triaje o la Ocupación CIUO88AC, se deben hardcodear temporalmente.

**Fragmento JSON esperado (Validado por QA):**
```json
[
  {
    "resource": {
      "resourceType": "Encounter",
      "id": "Encounter-0",
      "meta": { "profile": ["[https://fhir.minsalud.gov.co/rda/StructureDefinition/EncounterEmergencyRDA](https://fhir.minsalud.gov.co/rda/StructureDefinition/EncounterEmergencyRDA)"] },
      "status": "finished",
      "class": { "system": "[http://terminology.hl7.org/CodeSystem/v3-ActCode](http://terminology.hl7.org/CodeSystem/v3-ActCode)", "code": "EMER", "display": "emergency" },
      "type": [
        { "coding": [ { "system": "[https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianTechModality](https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianTechModality)", "code": "01", "display": "Intramural" } ] },
        { "coding": [ { "system": "[https://fhir.minsalud.gov.co/rda/CodeSystem/GrupoServicios](https://fhir.minsalud.gov.co/rda/CodeSystem/GrupoServicios)", "code": "05", "display": "Atención inmediata" } ] },
        { "coding": [ { "system": "[https://fhir.minsalud.gov.co/rda/CodeSystem/EntornoAtencion](https://fhir.minsalud.gov.co/rda/CodeSystem/EntornoAtencion)", "code": "05", "display": "Institucional" } ] }
      ],
      "subject": { "reference": "#RC-1232832630" },
      "participant": [ { "id": "DischargePhysician", "type": { "coding": [ { "system": "[http://terminology.hl7.org/CodeSystem/v3-ParticipationType](http://terminology.hl7.org/CodeSystem/v3-ParticipationType)", "code": "DIS", "display": "discharger" } ] }, "individual": { "reference": "#CC-1113636857" } } ],
      "period": { "start": "2021-11-20T06:11:00-05:00", "end": "2021-11-20T14:45:00-05:00" },
      "reasonCode": [ { "coding": [ { "system": "[https://fhir.minsalud.gov.co/rda/CodeSystem/RIPSCausaExternaVersion2](https://fhir.minsalud.gov.co/rda/CodeSystem/RIPSCausaExternaVersion2)", "code": "38", "display": "ENFERMEDAD GENERAL" } ] } ],
      "diagnosis": [
        { "id": "AdmissionDiagnosis", "extension": [ { "url": "[https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionDiagnosisType](https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionDiagnosisType)", "valueCoding": { "system": "[https://fhir.minsalud.gov.co/rda/CodeSystem/RIPSTipoDiagnosticoPrincipalVersion2](https://fhir.minsalud.gov.co/rda/CodeSystem/RIPSTipoDiagnosticoPrincipalVersion2)", "code": "01", "display": "Impresión Diagnóstica" } } ], "condition": { "reference": "#Condition-0" }, "use": { "coding": [ { "system": "[https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianDiagnosisRole](https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianDiagnosisRole)", "code": "52870002", "display": "diagnóstico de ingreso" } ] }, "rank": 1 },
        { "id": "DischargeDiagnosis", "extension": [ { "url": "[https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionDiagnosisType](https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionDiagnosisType)", "valueCoding": { "system": "[https://fhir.minsalud.gov.co/rda/CodeSystem/RIPSTipoDiagnosticoPrincipalVersion2](https://fhir.minsalud.gov.co/rda/CodeSystem/RIPSTipoDiagnosticoPrincipalVersion2)", "code": "01", "display": "Impresión Diagnóstica" } } ], "condition": { "reference": "#Condition-1" }, "use": { "coding": [ { "system": "[https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianDiagnosisRole](https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianDiagnosisRole)", "code": "89100005", "display": "diagnóstico final (alta)" } ] }, "rank": 2 }
      ]
    }
  },
  {
    "resource": {
      "resourceType": "Observation",
      "id": "Observation-2",
      "meta": { "profile": ["[https://fhir.minsalud.gov.co/rda/StructureDefinition/ObservationTriageRDA](https://fhir.minsalud.gov.co/rda/StructureDefinition/ObservationTriageRDA)"] },
      "status": "final",
      "code": { "coding": [ { "system": "[http://snomed.info/sct](http://snomed.info/sct)", "code": "225390008", "display": "triaje" } ], "text": "Triage" },
      "subject": { "reference": "#RC-1232832630" },
      "encounter": { "reference": "#Encounter-0" },
      "effectiveDateTime": "2020-11-20T06:20:00-05:00",
      "valueCodeableConcept": { "coding": [ { "system": "[https://fhir.minsalud.gov.co/rda/CodeSystem/ClaseTriage](https://fhir.minsalud.gov.co/rda/CodeSystem/ClaseTriage)", "code": "03", "display": "Triage III" } ] }
    }
  },
  {
    "resource": {
      "resourceType": "Observation",
      "id": "Observation-0",
      "meta": { "profile": ["[https://fhir.minsalud.gov.co/rda/StructureDefinition/PatientOccupationAtEncounterRDA](https://fhir.minsalud.gov.co/rda/StructureDefinition/PatientOccupationAtEncounterRDA)"] },
      "status": "final",
      "code": { "coding": [ { "system": "[http://snomed.info/sct](http://snomed.info/sct)", "code": "184104002", "display": "ocupación del paciente" } ], "text": "Ocupación del paciente en el momento de la atención" },
      "subject": { "reference": "#RC-1232832630" },
      "valueCodeableConcept": { "coding": [ { "system": "[https://fhir.minsalud.gov.co/rda/CodeSystem/CIUO88AC](https://fhir.minsalud.gov.co/rda/CodeSystem/CIUO88AC)", "code": "9333", "display": "Obreros de carga" } ] }
    }
  }
]