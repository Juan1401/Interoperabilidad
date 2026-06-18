# Contexto: RDA Urgencias - Datos Demográficos (Caja 1)

**Objetivo:** Adaptar/Crear los recursos Patient, Practitioner y Organization en Laravel (FHIR v4) para el proyecto RDA Urgencias (Colombia), basándonos en el sistema que ya genera el RDA Paciente.

**Reglas:**
- Respetar estrictamente las URLs de los perfiles (StructureDefinition) de Minsalud.
- Mantener las extensiones y sistemas de codificación locales (DIVIPOLA, RNEC, etc.).

**Fragmento JSON esperado (Validado por QA):**
```json
[
  {
    "resource": {
      "resourceType": "Patient",
      "id": "RC-1232832630",
      "meta": { "profile": ["[https://fhir.minsalud.gov.co/rda/StructureDefinition/PatientRDA](https://fhir.minsalud.gov.co/rda/StructureDefinition/PatientRDA)"] },
      "extension": [
        { "url": "[https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionPatientNationality](https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionPatientNationality)", "valueCoding": { "system": "[https://fhir.minsalud.gov.co/rda/CodeSystem/ISO31661](https://fhir.minsalud.gov.co/rda/CodeSystem/ISO31661)", "code": "170", "display": "Colombia" } },
        { "url": "[https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionPatientEthnicity](https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionPatientEthnicity)", "valueCoding": { "system": "[https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianEthnicGroup](https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianEthnicGroup)", "code": "6", "display": "Otras etnias" } },
        { "url": "[https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionPatientDisability](https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionPatientDisability)", "valueCoding": { "system": "[https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianDisabilityClassification](https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianDisabilityClassification)", "code": "08", "display": "Sin discapacidad" } }
      ],
      "identifier": [
        {
          "id": "NationalPersonIdentifier-0",
          "use": "official",
          "type": { "coding": [ { "system": "[http://terminology.hl7.org/CodeSystem/v2-0203](http://terminology.hl7.org/CodeSystem/v2-0203)", "code": "PN", "display": "Person number" }, { "system": "[https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianPersonIdentifier](https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianPersonIdentifier)", "code": "RC", "display": "Registro Civil" } ] },
          "system": "[https://fhir.minsalud.gov.co/rda/NamingSystem/RNEC](https://fhir.minsalud.gov.co/rda/NamingSystem/RNEC)",
          "value": "1232832630"
        }
      ],
      "name": [
        {
          "use": "official",
          "family": "GONZALEZ PINILLA",
          "_family": { "extension": [ { "url": "[https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionFathersFamilyName](https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionFathersFamilyName)", "valueString": "GONZALEZ" }, { "url": "[https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionMothersFamilyName](https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionMothersFamilyName)", "valueString": "PINILLA" } ] },
          "given": ["MARIANA"]
        }
      ],
      "gender": "female",
      "_gender": { "extension": [ { "url": "[https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionBiologicalGender](https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionBiologicalGender)", "valueCoding": { "system": "[https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianGenderGroup](https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianGenderGroup)", "code": "02", "display": "Mujer" } } ] },
      "birthDate": "2024-07-02",
      "address": [
        {
          "id": "HomeAddress-0",
          "use": "home",
          "type": "physical",
          "city": "Bogotá D.C.",
          "_city": { "extension": [ { "url": "[https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionDivipolaMunicipality](https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionDivipolaMunicipality)", "valueCoding": { "code": "11001", "system": "[https://fhir.minsalud.gov.co/rda/CodeSystem/DIVIPOLA](https://fhir.minsalud.gov.co/rda/CodeSystem/DIVIPOLA)" } } ] },
          "country": "Colombia",
          "_country": { "extension": [ { "url": "[https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionCountryCode](https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionCountryCode)", "valueCoding": { "system": "[https://fhir.minsalud.gov.co/rda/CodeSystem/ISO31661](https://fhir.minsalud.gov.co/rda/CodeSystem/ISO31661)", "code": "170" } } ] },
          "extension": [ { "url": "[https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionResidenceZone](https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionResidenceZone)", "valueCoding": { "system": "[https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianResidenceZone](https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianResidenceZone)", "code": "01", "display": "Urbana" } } ]
        }
      ]
    }
  },
  {
    "resource": {
      "resourceType": "Practitioner",
      "id": "CC-1113636857",
      "meta": { "profile": ["[https://fhir.minsalud.gov.co/rda/StructureDefinition/PractitionerRDA](https://fhir.minsalud.gov.co/rda/StructureDefinition/PractitionerRDA)"] },
      "identifier": [ { "id": "NationalPersonIdentifier-0", "use": "official", "type": { "coding": [ { "system": "[https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianPersonIdentifier](https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianPersonIdentifier)", "code": "CC", "display": "Cédula de ciudadanía" } ] }, "system": "[https://fhir.minsalud.gov.co/rda/NamingSystem/RNEC](https://fhir.minsalud.gov.co/rda/NamingSystem/RNEC)", "value": "1113636857" } ],
      "name": [ { "family": "FEIJOO HIDALGO", "given": ["JAVIER"] } ]
    }
  },
  {
    "resource": {
      "resourceType": "Organization",
      "id": "7600102541",
      "meta": { "profile": ["[https://fhir.minsalud.gov.co/rda/StructureDefinition/CareDeliveryOrganizationRDA](https://fhir.minsalud.gov.co/rda/StructureDefinition/CareDeliveryOrganizationRDA)"] },
      "identifier": [ { "system": "[http://co.fhir.guide/NamingSystem/REPS](http://co.fhir.guide/NamingSystem/REPS)", "value": "7600102541" } ],
      "name": "IPS CLUB NOEL"
    }
  }
]