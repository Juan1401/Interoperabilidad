<?php

namespace App\Services\Hl7;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Servicio monolítico para generar el Bundle FHIR del RDA Urgencias.
 * Cajas 1-4: Recursos clínicos | Caja 5: Composition + Bundle wrapper.
 */
class RdaUrgenciasService extends RdaService
{
    /**
     * Genera el Bundle FHIR completo para RDA Urgencias.
     *
     * @param int $ingresoId
     * @return array Bundle FHIR listo para json_encode()
     * @throws \Exception
     */
    public function getDataForRda(int $ingresoId): array
    {
        Log::info("Iniciando obtención de datos RDA Urgencias para ingreso: {$ingresoId}");

        $ingreso = $this->getIngresoWithRelations($ingresoId);
        if (!$this->validateIngreso($ingreso)) {
            throw new \Exception('Ingreso no encontrado');
        }

        $paciente = $ingreso->paciente;
        if (!$paciente) {
            throw new \Exception("Paciente no encontrado para el ingreso {$ingresoId}");
        }

        $persona = new \App\Models\Persona($paciente);
        $persona->documento = $this->sanitizeForId($persona->documento);

        // Generar UUID
        $uuid = (string) Str::uuid();

        $practitionerData = $this->getPractitionerData($ingresoId);
        $practitionerData['tercero_id'] = $this->sanitizeForId($practitionerData['tercero_id']);

        $colombianIdentifierData             = $this->getColombianPersonIdentifierData($persona->tipo_documento);
        $colombianPractitionerIdentifierData = $this->getColombianPersonIdentifierData($practitionerData['tipo_tercero_id']);

        // IDs dinámicos reutilizados en TODAS las referencias cruzadas
        $patientId      = $colombianIdentifierData['code'] . "-" . $persona->documento;
        $practitionerId = $colombianPractitionerIdentifierData['code'] . "-" . $practitionerData['tercero_id'];

        $organizationData = $this->getOrganizationData();
        $organizationId = $organizationData['codigo_sgsss_ips'];

        // Esta fecha obtenida de la tabla ingresos_salidas se debe homologar
        try {
            $claseAtencionDate = $this->determinarClaseAtencionDate($ingresoId);

            $fechaInicio = !empty($claseAtencionDate['urgencia_fecha_inicio']) ? $claseAtencionDate['urgencia_fecha_inicio'] : $ingreso->fecha_ingreso;
            $fechaFin = !empty($claseAtencionDate['urgencia_fecha_fin']) ? $claseAtencionDate['urgencia_fecha_fin'] : $ingreso->fecha_egreso;

        } catch (\Exception $e) {
            Log::warning("RdaUrgenciasService: fallo determinarClaseAtencionDate para ingreso {$ingresoId}. Usando fechas de fallback. Error: " . $e->getMessage());

            // Fallback: fechas directas del objeto ingreso
            $fechaInicio = $ingreso->fecha_ingreso;
            $fechaFin = $ingreso->fecha_egreso;
        }
        $periodStart = $fechaInicio
            ? (new \DateTime($fechaInicio))->format('Y-m-d\TH:i:sP')
            : date('Y-m-d\TH:i:sP');

        $periodEnd = $fechaFin
            ? (new \DateTime($fechaFin))->format('Y-m-d\TH:i:sP')
            : date('Y-m-d\TH:i:sP');

        $effectiveDateTimeTriage = $periodStart;

        // ─────────────────────────────────────────────
        // Flags para rastrear recursos condicionales
        // (necesarios para Encounter.diagnosis y Composition Caja 5)
        // ─────────────────────────────────────────────
        $hasCondition1   = false; // Diagnóstico de egreso
        $hasObservation1 = false;  // Incapacidad SIPE (se evalúa dinámicamente)

        // ═════════════════════════════════════════════
        // RECURSOS CAJAS 1 a 4 (array plano)
        // ═════════════════════════════════════════════

        $resources = [];

        // ── RECURSO 1: PATIENT ─────────────────────────────────────────────
        $resources[] = $this->buildPatientResource($persona, $patientId, $colombianIdentifierData);

        // ── RECURSO 2: PRACTITIONER ────────────────────────────────────────
        $resources[] = $this->buildPractitionerResource($practitionerData, $practitionerId, $colombianPractitionerIdentifierData);

        // ── RECURSO 3: ORGANIZATION ────────────────────────────────────────
        $resources[] = $this->buildOrganizationResource($organizationData, $organizationId);

        // ═════════════════════════════════════════════
        // PRE-CÁLCULO: Diagnósticos RIPS (Caja 3)
        // Se ejecuta ANTES del Encounter para poder construir
        // el array diagnosis dinámicamente y evitar BUNDLE-005.
        // ═════════════════════════════════════════════
        $diagnosesData = $this->getEncounterDiagnoses($ingresoId, $patientId);

        // ── RECURSO 4: ENCOUNTER ───────────────────────────────────────────
        $resources[] = $this->buildEncounterResource($patientId, $practitionerId, $periodStart, $periodEnd, $diagnosesData['encounterDiagnosis']);

        // ── RECURSO 5: OBSERVATION-2 (Triaje) ─────────────────────────────
        $resources[] = $this->buildObservationTriageResource($patientId, $effectiveDateTimeTriage);

        // ── RECURSO 6: OBSERVATION-0 (Ocupación) ──────────────────────────
        $resources[] = $this->buildObservationOccupationResource($patientId);

        // ═════════════════════════════════════════════
        // CAJA 3: Recursos Condition
        // (las queries ya se ejecutaron en el PRE-CÁLCULO arriba)
        // ═════════════════════════════════════════════

        // ── RECURSO 7: CONDITION-0 (Diagnóstico Ingreso - siempre presente) ──
        $resources[] = $diagnosesData['condition0'];

        // ── RECURSO 8: CONDITION-1 (Diagnóstico Egreso - SIEMPRE obligatorio) ──
        $resources[] = $diagnosesData['condition1'];

        // ═════════════════════════════════════════════
        // CAJA 4: Incapacidades y Anexo de Epicrisis
        // ═════════════════════════════════════════════

        // ── RECURSO 9: OBSERVATION-1 (Incapacidad SIPE) ──
        $incapacidad = $this->buildObservationIncapacidadResource($ingresoId, $patientId);
        if ($incapacidad !== null) {
            $hasObservation1 = true;
            $resources[] = $incapacidad;
        }

        // ── RECURSO 10: DOCUMENT REFERENCE-0 (Epicrisis PDF) ─────────────
        $resources[] = $this->buildDocumentReferenceEpicrisisResource($ingresoId, $patientId, $organizationId, $periodEnd);

        // ═════════════════════════════════════════════
        // CAJA 5: Composition + Bundle (Ensamblaje Final)
        // ═════════════════════════════════════════════

        // Timestamp del documento = momento actual de la generación
        $bundleTimestamp = date('Y-m-d\TH:i:sP');

        $composition = $this->buildCompositionResource($patientId, $practitionerId, $organizationId, $periodEnd, $hasObservation1);

        return $this->assembleBundle($ingresoId, $uuid, $bundleTimestamp, $composition, $resources, $hasCondition1, $hasObservation1);
    }

    private function buildPatientResource(\App\Models\Persona $persona, string $patientId, array $colombianIdentifierData): array
    {
        // ═════════════════════════════════════════════
        // CAJA 1: Datos Demográficos
        // ═════════════════════════════════════════════
        $iso31661NationalityData      = $this->getIso31661Data('numeric', 'Colombia');
        $ethnicGroupData              = $this->getColombianEthnicGroupData('6');
        $disabilityClassificationData = $this->getColombianDisabilityClassificationData('08');

        /* obtener datos address ExtensionDivipolaMunicipality
        * Concatena tipo_dpto_id + tipo_mpio_id para formar código DIVIPOLA (ej: '76' + '001' = '76001')
        */
        $divipolaCode = $persona->departamento . $persona->municipio;
        $divipolaMunicipality = $this->getDivipolaDataByCode($divipolaCode);

        $addressCountryCode   = $this->getIso31661Data('numeric', 'Colombia');
        $addressResidenceZone = $this->getColombianResidenceZoneData('01');

        $gender          = $persona->sexo == 'F' ? 'female' : 'male';
        $bioGenderId     = $persona->sexo == 'F' ? '02' : '01';
        $colombianGenderGroup = $this->getColombianGenderGroup($bioGenderId);

        return [
            "resourceType" => "Patient",
            "id" => $patientId,
            "meta" => [
                "profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/PatientRDA"]
            ],
            "extension" => [
                [
                    "url" => "https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionPatientNationality",
                    "valueCoding" => [
                        "system" => $iso31661NationalityData['system'],
                        "code" => $iso31661NationalityData['code'],
                        "display" => $iso31661NationalityData['display']
                    ]
                ],
                [
                    "url" => "https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionPatientEthnicity",
                    "valueCoding" => [
                        "system" => $ethnicGroupData['system'],
                        "code" => $ethnicGroupData['code'],
                        "display" => $ethnicGroupData['display']
                    ]
                ],
                [
                    "url" => "https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionPatientDisability",
                    "valueCoding" => [
                        "system" => $disabilityClassificationData['system'],
                        "code" => $disabilityClassificationData['code'],
                        "display" => $disabilityClassificationData['display']
                    ]
                ]
            ],
            "identifier" => [
                [
                    "id" => "NationalPersonIdentifier-0",
                    "use" => "official",
                    "type" => [
                        "coding" => [
                            [
                                "system" => "http://terminology.hl7.org/CodeSystem/v2-0203",
                                "code" => "PN",
                                "display" => "Person number"
                            ],
                            [
                                "system" => $colombianIdentifierData['system'],
                                "code" => $colombianIdentifierData['code'],
                                "display" => $colombianIdentifierData['display']
                            ]
                        ]
                    ],
                    "system" => "https://fhir.minsalud.gov.co/rda/NamingSystem/RNEC",
                    "value" => $persona->documento
                ]
            ],
            "name" => [
                [
                    "use" => "official",
                    "family" => trim($persona->primer_apellido . ' ' . $persona->segundo_apellido),
                    "_family" => [
                        "extension" => [
                            [
                                "url" => "https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionFathersFamilyName",
                                "valueString" => $persona->primer_apellido
                            ],
                            [
                                "url" => "https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionMothersFamilyName",
                                "valueString" => $persona->segundo_apellido
                            ]
                        ]
                    ],
                    "given" => array_values(array_filter([$persona->primer_nombre, $persona->segundo_nombre]))
                ]
            ],
            "gender" => $gender,
            "_gender" => [
                "extension" => [
                    [
                        "url" => "https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionBiologicalGender",
                        "valueCoding" => [
                            "system" => $colombianGenderGroup['system'],
                            "code" => str_pad($colombianGenderGroup['code'], 2, "0", STR_PAD_LEFT),
                            "display" => $colombianGenderGroup['display']
                        ]
                    ]
                ]
            ],
            "birthDate" => $persona->fecha_nacimiento,
            "address" => [
                [
                    "id" => "HomeAddress-0",
                    "use" => "home",
                    "type" => "physical",
                    "city" => $divipolaMunicipality['display'],
                    "_city" => [
                        "extension" => [
                            [
                                "url" => "https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionDivipolaMunicipality",
                                "valueCoding" => [
                                    "code" => str_pad($divipolaMunicipality['code'], 5, "0", STR_PAD_LEFT),
                                    "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/DIVIPOLA"
                                ]
                            ]
                        ]
                    ],
                    "country" => $addressCountryCode['display'],
                    "_country" => [
                        "extension" => [
                            [
                                "url" => "https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionCountryCode",
                                "valueCoding" => [
                                    "system" => $addressCountryCode['system'],
                                    "code" => $addressCountryCode['code']
                                ]
                            ]
                        ]
                    ],
                    "extension" => [
                        [
                            "url" => "https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionResidenceZone",
                            "valueCoding" => [
                                "system" => $addressResidenceZone['system'],
                                "code" => $addressResidenceZone['code'],
                                "display" => $addressResidenceZone['display']
                            ]
                        ]
                    ]
                ]
            ],
            "active" => true
        ];
    }

    private function buildPractitionerResource(array $practitionerData, string $practitionerId, array $colombianPractitionerIdentifierData): array
    {
        return [
            "resourceType" => "Practitioner",
            "id" => $practitionerId,
            "meta" => [
                "profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/PractitionerRDA"]
            ],
            "identifier" => [
                [
                    "id" => "NationalPersonIdentifier-0",
                    "use" => "official",
                    "type" => [
                        "coding" => [
                            [
                                "system" => $colombianPractitionerIdentifierData['system'],
                                "code" => $colombianPractitionerIdentifierData['code'],
                                "display" => $colombianPractitionerIdentifierData['display']
                            ]
                        ]
                    ],
                    "system" => "https://fhir.minsalud.gov.co/rda/NamingSystem/RNEC",
                    "value" => $practitionerData['tercero_id']
                ]
            ],
            "name" => [
                [
                    "family" => trim($practitionerData['primer_apellido'] . ' ' . $practitionerData['segundo_apellido']),
                    "given" => array_values(array_filter([$practitionerData['primer_nombre'], $practitionerData['segundo_nombre']]))
                ]
            ]
        ];
    }

    private function buildOrganizationResource(array $organizationData, string $organizationId): array
    {
        /* Inicio Organization
         * */
        /** Obtener datos use
         * @param string $code = usual | official | temp | secondary | old (If known)
         */
        $useOrganization = 'official';
        /** Obtener datos Organization Identifier
         * @param string $catalogName = 'v2.0203'
         * @param int $id = 170 (TAX)
         */
        $organizationIdentifierTypeData = $this->getHl7CatalogItemByName('v2.0203', 170);
        /** Obtener datos ColombianOrganizationIdentifier
         * @param string $id = 1 (NIT)
         */
        $colombianOrganizationIdentifier = $this->getColombianOrganizationIdentifier('1');
        /** Obtener datos use
         * @param string $value = Tax ID number value Example General: 123456
         */
        $TaxIDNumberValue = 'Desconocido';
        /** Obtener datos HealthcareProviderIdentifier
         * @param string $catalogName = 'v2.0203'
         * @param int $id = 151 (PRN)
         */
        $healthcareProviderIdentifierNumber = $this->getHl7CatalogItemByName('v2.0203', 151);
        /** Obtener datos ColombianOrganizationIdentifier
         * @param string $id = 2 (Prestador)
         */
        $healthcareProviderCodePrestador = $this->getColombianOrganizationIdentifier('2');
        /**
         * Fin Organization
         * */

        return [
            "resourceType" => "Organization",
            "id" => $organizationId,
            "meta" => [
                "profile" => [
                    "https://fhir.minsalud.gov.co/rda/StructureDefinition/CareDeliveryOrganizationRDA"
                ]
            ],
            "identifier" => [
                [
                    "id" => "TaxIdentifier-0",
                    "use" => $useOrganization,
                    "type" => [
                        "coding" => [
                            [
                                "system" => $organizationIdentifierTypeData['system'],
                                "code" => $organizationIdentifierTypeData['code'],
                                "display" => $organizationIdentifierTypeData['display']
                            ],
                            [
                                "system" => $colombianOrganizationIdentifier['system'],
                                "code" => $colombianOrganizationIdentifier['code'],
                                "display" => $colombianOrganizationIdentifier['display']
                            ]
                        ]
                    ],
                    "value" => $TaxIDNumberValue
                ],
                [
                    "id" => "HealthcareProviderIdentifier-0",
                    "use" => "official",
                    "type" => [
                        "coding" => [
                            [
                                "system" => $healthcareProviderIdentifierNumber['system'],
                                "code" => $healthcareProviderIdentifierNumber['code'],
                                "display" => $healthcareProviderIdentifierNumber['display']
                            ],
                            [
                                "system" => $healthcareProviderCodePrestador['system'],
                                "code" => $healthcareProviderCodePrestador['code'],
                                "display" => $healthcareProviderCodePrestador['display']
                            ]
                        ]
                    ],
                    "system" => "https://fhir.minsalud.gov.co/rda/NamingSystem/REPS",
                    "value" => $organizationId
                ]
            ]
        ];
    }

    private function getEncounterDiagnoses(int $ingresoId, string $patientId): array
    {
        $mapCertezaRips = [
            '1' => ['code' => '01', 'display' => 'Impresión Diagnóstica'],
            '2' => ['code' => '02', 'display' => 'Confirmado Nuevo'],
            '3' => ['code' => '03', 'display' => 'Confirmado Repetido'],
        ];

        // Diagnóstico de INGRESO (siempre presente)
        $dxIngresoBd = DB::table('public.hc_diagnosticos_ingreso')
            ->where('ingreso', $ingresoId)
            ->orderByDesc('sw_principal') // Prioriza sw_principal = 1 antes que 0
            ->first();

        // El usuario indicó: código CIE10 -> 'tipo_diagnostico_id' y tipo de certeza (1, 2, 3) -> 'tipo_diagnostico'
        $codigoCie10Ingreso = $dxIngresoBd->tipo_diagnostico_id ?? 'Z769';
        $tipoDxId           = (string) ($dxIngresoBd->tipo_diagnostico ?? '1');

        $certezaRips = $mapCertezaRips[$tipoDxId] ?? ['code' => '01', 'display' => 'Impresión Diagnóstica'];

        try {
            $icd10Ingreso = $this->getIcd10Data($codigoCie10Ingreso);
        } catch (\Exception $e) {
            $icd10Ingreso = ['system' => 'http://hl7.org/fhir/sid/icd-10', 'code' => $codigoCie10Ingreso, 'display' => 'SIN DESCRIPCIÓN'];
        }

        // Diagnóstico de EGRESO (OBLIGATORIO - Minsalud lo exige siempre)
        $dxEgresoBd = DB::table('public.hc_diagnosticos_egreso')
            ->where('ingreso', $ingresoId)
            ->orderByDesc('sw_principal')
            ->first();

        if ($dxEgresoBd !== null) {
            $codigoCie10Egreso = $dxEgresoBd->tipo_diagnostico_id ?? 'Z769';
            $tipoDxEgresoId    = (string) ($dxEgresoBd->tipo_diagnostico ?? '1');

            $certezaRipsEgreso = $mapCertezaRips[$tipoDxEgresoId] ?? ['code' => '01', 'display' => 'Impresión Diagnóstica'];

            try {
                $icd10Egreso = $this->getIcd10Data($codigoCie10Egreso);
            } catch (\Exception $e) {
                $icd10Egreso = ['system' => 'http://hl7.org/fhir/sid/icd-10', 'code' => $codigoCie10Egreso, 'display' => 'SIN DESCRIPCIÓN'];
            }
        } else {
            // Fallback: usar el mismo diagnóstico de ingreso como egreso
            $icd10Egreso       = $icd10Ingreso;
            $certezaRipsEgreso = $certezaRips;
        }

        // Encounter.diagnosis: SIEMPRE incluye ambos (Condition-0 y Condition-1)
        $encounterDiagnosis = [
            [
                "id" => "AdmissionDiagnosis",
                "extension" => [
                    [
                        "url" => "https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionDiagnosisType",
                        "valueCoding" => [
                            "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/RIPSTipoDiagnosticoPrincipalVersion2",
                            "code" => $certezaRips['code'],
                            "display" => $certezaRips['display']
                        ]
                    ]
                ],
                "condition" => ["reference" => "#Condition-0"],
                "use" => ["coding" => [["system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianDiagnosisRole", "code" => "52870002", "display" => "diagnóstico de ingreso"]]],
                "rank" => 1
            ],
            [
                "id" => "DischargeDiagnosis",
                "extension" => [
                    [
                        "url" => "https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionDiagnosisType",
                        "valueCoding" => [
                            "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/RIPSTipoDiagnosticoPrincipalVersion2",
                            "code" => $certezaRipsEgreso['code'],
                            "display" => $certezaRipsEgreso['display']
                        ]
                    ]
                ],
                "condition" => ["reference" => "#Condition-1"],
                "use" => ["coding" => [["system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianDiagnosisRole", "code" => "89100005", "display" => "diagnóstico final (alta)"]]],
                "rank" => 2
            ]
        ];

        $condition0 = [
            "resourceType" => "Condition",
            "id" => "Condition-0",
            "meta" => ["profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/ConditionRDA"]],
            "extension" => [
                [
                    "url" => "https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionDiagnosisType",
                    "valueCoding" => [
                        "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/RIPSTipoDiagnosticoPrincipalVersion2",
                        "code" => $certezaRips['code'],
                        "display" => $certezaRips['display']
                    ]
                ]
            ],
            "clinicalStatus" => ["coding" => [["system" => "http://terminology.hl7.org/CodeSystem/condition-clinical", "code" => "active", "display" => "Active"]]],
            "category" => [["coding" => [["system" => "http://terminology.hl7.org/CodeSystem/condition-category", "code" => "encounter-diagnosis", "display" => "Encounter Diagnosis"]]]],
            "code" => [
                "coding" => [["system" => $icd10Ingreso['system'], "code" => $icd10Ingreso['code'], "display" => $icd10Ingreso['display']]],
                "text" => $icd10Ingreso['display']
            ],
            "subject" => ["reference" => "#" . $patientId]
        ];

        $condition1 = [
            "resourceType" => "Condition",
            "id" => "Condition-1",
            "meta" => ["profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/ConditionRDA"]],
            "extension" => [["url" => "https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionDiagnosisType", "valueCoding" => ["system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/RIPSTipoDiagnosticoPrincipalVersion2", "code" => $certezaRipsEgreso['code'], "display" => $certezaRipsEgreso['display']]]],
            "clinicalStatus" => ["coding" => [["system" => "http://terminology.hl7.org/CodeSystem/condition-clinical", "code" => "active", "display" => "Active"]]],
            "category" => [["coding" => [["system" => "http://terminology.hl7.org/CodeSystem/condition-category", "code" => "encounter-diagnosis", "display" => "Encounter Diagnosis"]]]],
            "code" => ["coding" => [["system" => $icd10Egreso['system'], "code" => $icd10Egreso['code'], "display" => $icd10Egreso['display']]], "text" => $icd10Egreso['display']],
            "subject" => ["reference" => "#" . $patientId]
        ];

        return [
            'encounterDiagnosis' => $encounterDiagnosis,
            'condition0' => $condition0,
            'condition1' => $condition1
        ];
    }

    private function buildEncounterResource(string $patientId, string $practitionerId, string $periodStart, string $periodEnd, array $encounterDiagnosis): array
    {
        // HARDCODEADOS Caja 2
        $causaExternaRipsCode    = '38';
        $causaExternaRipsDisplay = 'ENFERMEDAD GENERAL';

        return [
            "resourceType" => "Encounter",
            "id" => "Encounter-0",
            "meta" => [
                "profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/EncounterEmergencyRDA"]
            ],
            "status" => "finished",
            "class" => [
                "system" => "http://terminology.hl7.org/CodeSystem/v3-ActCode",
                "code" => "EMER",
                "display" => "emergency"
            ],
            "type" => [
                ["coding" => [["system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianTechModality", "code" => "01", "display" => "Intramural"]]],
                ["coding" => [["system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/GrupoServicios", "code" => "05", "display" => "Atención inmediata"]]],
                ["coding" => [["system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/EntornoAtencion", "code" => "05", "display" => "Institucional"]]]
            ],
            "subject" => ["reference" => "#" . $patientId],
            "participant" => [
                [
                    "id" => "DischargePhysician",
                    "type" => [
                        "coding" => [
                            [
                                "system" => "http://terminology.hl7.org/CodeSystem/v3-ParticipationType",
                                "code" => "DIS",
                                "display" => "discharger"
                            ]
                        ]
                    ],
                    "individual" => ["reference" => "#" . $practitionerId]
                ]
            ],
            "period" => [
                "start" => $periodStart,
                "end"   => $periodEnd
            ],
            "reasonCode" => [
                ["coding" => [["system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/RIPSCausaExternaVersion2", "code" => $causaExternaRipsCode, "display" => $causaExternaRipsDisplay]]]
            ],
            // diagnosis construido dinámicamente ANTES del Encounter (fix BUNDLE-005)
            "diagnosis" => $encounterDiagnosis
        ];
    }

    private function buildObservationTriageResource(string $patientId, string $effectiveDateTimeTriage): array
    {
        $triageCode              = '03';
        $triageDisplay           = 'Triage III';

        return [
            "resourceType" => "Observation",
            "id" => "Observation-2",
            "meta" => ["profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/ObservationTriageRDA"]],
            "status" => "final",
            "code" => [
                "coding" => [["system" => "http://snomed.info/sct", "code" => "225390008", "display" => "triaje"]],
                "text" => "Triage"
            ],
            "subject" => ["reference" => "#" . $patientId],
            "encounter" => ["reference" => "#Encounter-0"],
            "effectiveDateTime" => $effectiveDateTimeTriage,
            "valueCodeableConcept" => [
                "coding" => [["system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/ClaseTriage", "code" => $triageCode, "display" => $triageDisplay]]
            ]
        ];
    }

    private function buildObservationOccupationResource(string $patientId): array
    {
        //QUEMADO
        $ciuo88acCode            = '9333';
        $ciuo88acDisplay         = 'Obreros de carga';

        return [
            "resourceType" => "Observation",
            "id" => "Observation-0",
            "meta" => ["profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/PatientOccupationAtEncounterRDA"]],
            "status" => "final",
            "code" => [
                "coding" => [["system" => "http://snomed.info/sct", "code" => "184104002", "display" => "ocupación del paciente"]],
                "text" => "Ocupación del paciente en el momento de la atención"
            ],
            "subject" => ["reference" => "#" . $patientId],
            "valueCodeableConcept" => [
                "coding" => [["system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/CIUO88AC", "code" => $ciuo88acCode, "display" => $ciuo88acDisplay]]
            ]
        ];
    }

    private function buildObservationIncapacidadResource(int $ingresoId, string $patientId): ?array
    {
        $incapacidadBd = DB::table('public.hc_incapacidades as i')
            ->join('public.hc_evoluciones as e', 'i.evolucion_id', '=', 'e.evolucion_id')
            ->where('e.ingreso', $ingresoId)
            ->select('i.dias_de_incapacidad', 'i.sw_prorroga')
            ->orderByDesc('e.fecha') // Priorizar la más reciente frente a múltiples registros
            ->first();

        if ($incapacidadBd !== null) {
            $diasIncapacidad = (int) $incapacidadBd->dias_de_incapacidad;
            $hayProrroga     = (string) $incapacidadBd->sw_prorroga === '1';

            // Homologación Minsalud (LicenseScope): 01 = Nueva, 02 = Prórroga
            $licenseScopeCode    = $hayProrroga ? '02' : '01';
            $licenseScopeDisplay = $hayProrroga ? 'Prórroga' : 'Nueva';

            return [
                "resourceType" => "Observation",
                "id" => "Observation-1",
                "meta" => [
                    "profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/AttendanceAllowanceRDA"]
                ],
                "status" => "final",
                "code" => [
                    "coding" => [
                        [
                            "system" => "http://snomed.info/sct",
                            "code" => "160983005",
                            "display" => "permiso de concurrencia"
                        ]
                    ],
                    "text" => "Datos incapacidad (SIPE – Sistema de Incapacidades y Prestaciones Economicas)"
                ],
                "subject" => ["reference" => "#" . $patientId],
                "component" => [
                    [
                        "id" => "LicenseScope",
                        "code" => [
                            "coding" => [
                                [
                                    "system" => "http://snomed.info/sct",
                                    "code" => "255590007",
                                    "display" => "alcance"
                                ]
                            ],
                            "text" => "Incapacidad - Alcance de la incapacidad"
                        ],
                        "valueCodeableConcept" => [
                            "coding" => [
                                [
                                    "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianLicenseScope",
                                    "code" => $licenseScopeCode,
                                    "display" => $licenseScopeDisplay
                                ]
                            ]
                        ]
                    ],
                    [
                        "id" => "LicenseTime",
                        "code" => [
                            "coding" => [
                                [
                                    "system" => "http://snomed.info/sct",
                                    "code" => "410670007",
                                    "display" => "tiempo"
                                ]
                            ],
                            "text" => "Días de incapacidad"
                        ],
                        "valueQuantity" => [
                            "value" => $diasIncapacidad,
                            "unit" => "días",
                            "system" => "http://unitsofmeasure.org",
                            "code" => "d"
                        ]
                    ]
                ]
            ];
        }

        return null; // Return null if observation isn't valid
    }

    private function buildDocumentReferenceEpicrisisResource(int $ingresoId, string $patientId, string $organizationId, string $fechaDocumento): array
    {
        $epicrisisPdfBase64 = '';

        try {
            Log::info("DEBUG BROWSERSHOT: 1. Iniciando obtención de PDF");

            // 1. Obtener Epicrisis del SIIS (ahora esto hace la petición del HTML puro y genera el PDF en Base64 en un solo paso)
            $respuestaSiis = $this->obtenerEpicrisisSiis($ingresoId);
            if (!$respuestaSiis['success']) {
                $errorMsg = $respuestaSiis['error'] ?? 'Error desconocido del SIIS';
                throw new \Exception("Error SIIS: {$errorMsg}");
            }

            // 2. Extraemos el base64 del PDF generado, el cual es necesario para el Bundle
            if (!isset($respuestaSiis['pdf_base64']) || empty($respuestaSiis['pdf_base64'])) {
                 throw new \Exception("El endpoint SIIS no devolvió el PDF esperado en su respuesta.");
            }

            $epicrisisPdfBase64 = $respuestaSiis['pdf_base64'];

        } catch (\Exception $e) {
            Log::error("ERROR BROWSERSHOT O SIIS: " . $e->getMessage());
            $epicrisisPdfBase64 = base64_encode("Error recuperando/generando el PDF de Epicrisis: " . $e->getMessage());
        }

        return [
            "resourceType" => "DocumentReference",
            "id" => "DocumentReference-0",
            "meta" => ["profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/DocumentReferenceEPIRDA"]],
            "status" => "current",
            "type" => [
                "coding" => [
                    ["system" => "http://loinc.org", "code" => "18842-5", "display" => "Discharge summary"],
                    ["system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianDocumentTypes", "code" => "EPI", "display" => "Epicrisis"]
                ]
            ],
            "category" => [["coding" => [["system" => "http://loinc.org", "code" => "55108-5", "display" => "Clinical presentation Document"]]]],
            "subject" => ["reference" => "#" . $patientId],
            "date" => $fechaDocumento,
            "author" => [["reference" => "#" . $organizationId]],
            "custodian" => ["reference" => "Organization/MinSalud"],
            "description" => "Epicrisis del encuentro de atención en salud - RDA",
            "securityLabel" => [["coding" => [["system" => "http://terminology.hl7.org/CodeSystem/v3-Confidentiality", "code" => "R", "display" => "restricted"]]]],
            "content" => [
                [
                    "attachment" => ["language" => "es-CO", "data" => $epicrisisPdfBase64],
                    "format" => ["system" => "urn:ietf:bcp:13", "code" => "application/pdf", "display" => "PDF"]
                ]
            ],
            "context" => ["encounter" => [["reference" => "#Encounter-0"]]]
        ];
    }

    private function buildCompositionResource(string $patientId, string $practitionerId, string $organizationId, string $periodEnd, bool $hasObservation1): array
    {
        // ── 1. DEFINIR ESTRUCTURA MAESTRA PARA SECCIONES VACÍAS ──
        // Esto soluciona el error cmp-1 (requiere text) y los errores de catálogo (requiere nilknown)
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

        // ── 2. CONSTRUIR SECCIÓN DE INCAPACIDAD ──
        $seccionIncapacidad = $hasObservation1
            ? [
                "title" => "Datos incapacidad (SIPE – Sistema de Incapacidades y Prestaciones Economicas)",
                "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "105583-9", "display" => "Worker Sick leave form" ] ] ],
                "entry" => [["reference" => "#Observation-1"]]
            ]
            : [
                "title" => "Datos incapacidad (SIPE – Sistema de Incapacidades y Prestaciones Economicas)",
                "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "105583-9", "display" => "Worker Sick leave form" ] ] ],
                "text" => $emptySectionData["text"],
                "emptyReason" => $emptySectionData["emptyReason"]
            ];

        // ── 3. ENSAMBLAR TODAS LAS SECCIONES ──
        $sections = [
            [
                "title" => "Entidad(es) responsable(s) por el plan de beneficios en salud (urgencias)",
                "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "48768-6", "display" => "Payment sources Document" ] ] ],
                "entry" => [["reference" => "#" . $organizationId]]
            ],
            [
                "title" => "Otros datos demográficos",
                "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "74208-0", "display" => "Demographic information + History of occupation Document" ] ] ],
                "entry" => [["reference" => "#Observation-0"]]
            ],
            [
                "title" => "Clasificación de triaje",
                "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "54094-8", "display" => "Emergency department Triage note" ] ] ],
                "entry" => [["reference" => "#Observation-2"]]
            ],
            $seccionIncapacidad,
            [
                "title" => "Historial de diagnósticos de problemas de salud",
                "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "11450-4", "display" => "Problem list - Reported" ] ] ],
                "entry" => [ ["reference" => "#Condition-0"], ["reference" => "#Condition-1"] ]
            ],
            // ── SECCIONES VACÍAS (LIMPIEZA DE REFERENCIAS FALSAS) ──
            [
                "title" => "Historial de alergias, intolerancias y reacciones adversas",
                "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "48765-2", "display" => "Allergies and adverse reactions Document" ] ] ],
                "text" => $emptySectionData["text"],
                "emptyReason" => $emptySectionData["emptyReason"]
            ],
            [
                "title" => "Factores de riesgo",
                "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "75492-9", "display" => "Risk assessment and screening note" ] ] ],
                "text" => $emptySectionData["text"],
                "emptyReason" => $emptySectionData["emptyReason"]
            ],
            [
                "title" => "Historial de medicamentos",
                "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "10160-0", "display" => "History of Medication use Narrative" ] ] ],
                "text" => $emptySectionData["text"],
                "emptyReason" => $emptySectionData["emptyReason"]
            ],
            [
                "title" => "Historial de procedimientos",
                "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "47519-4", "display" => "History of Procedures Document" ] ] ],
                "text" => $emptySectionData["text"],
                "emptyReason" => $emptySectionData["emptyReason"]
            ],
            [
                "title" => "Resultados del uso de las tecnologías en salud",
                "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "30954-2", "display" => "Relevant diagnostic tests/laboratory data note" ] ] ],
                "text" => $emptySectionData["text"],
                "emptyReason" => $emptySectionData["emptyReason"]
            ],
            [
                "title" => "Órdenes, prescripciones o solicitudes de servicio",
                "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "61146-1", "display" => "Orders for services Document" ] ] ],
                "text" => $emptySectionData["text"],
                "emptyReason" => $emptySectionData["emptyReason"]
            ],
            // ── FIN SECCIONES VACÍAS ──
            [
                "title" => "Documentos de soporte",
                "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "55107-7", "display" => "Addendum Document" ] ] ],
                "entry" => [["reference" => "#DocumentReference-0"]]
            ]
        ];

        return [
            "resourceType" => "Composition",
            "id" => "Composition-0",
            "meta" => [
                "profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/CompositionEmergencyRDA"]
            ],
            "status" => "final",
            "type" => [
                "coding" => [
                    [
                        "system" => "http://loinc.org",
                        "code" => "59258-4",
                        "display" => "Emergency department Discharge summary"
                    ]
                ]
            ],
            "subject" => ["reference" => "#" . $patientId],
            "encounter" => ["reference" => "#Encounter-0"],
            "date" => $periodEnd,
            "author" => [["reference" => "#" . $practitionerId]],
            "title" => "RDA Urgencias",
            "confidentiality" => "N",
            "attester" => [
                [
                    "mode" => "legal",
                    "party" => ["reference" => "#" . $patientId]
                ]
            ],
            "custodian" => ["reference" => "#" . $organizationId],
            "section" => $sections
        ];
    }

    private function assembleBundle(int $ingresoId, string $uuid, string $bundleTimestamp, array $composition, array $resources, bool $hasCondition1, bool $hasObservation1): array
    {
        // ── Ensamblar Bundle final ─────────────────────────────────────────
        // entry[0] = Composition (obligatorio para Bundle tipo document)
        // entry[1..N] = todos los recursos clínicos de las Cajas 1-4
        $bundleEntry = [];

        // Primero el Composition
        $bundleEntry[] = ["resource" => $composition];

        // Luego todos los recursos de las Cajas 1-4
        foreach ($resources as $resource) {
            $bundleEntry[] = ["resource" => $resource];
        }

        $bundle = [
            "resourceType" => "Bundle",
            "language" => "es-CO",
            "type" => "document",
            "identifier" => [
                "system" => "https://fhir.minsalud.gov.co/rda/NamingSystem/identifier-RDA",
                "value" => $ingresoId . "-" . $uuid
            ],

            "timestamp" => $bundleTimestamp,
            "entry" => $bundleEntry
        ];

        Log::info("Bundle RDA Urgencias generado exitosamente para ingreso: {$ingresoId}", [
            'total_entries' => count($bundleEntry),
            'has_condition1' => $hasCondition1,
            'has_observation1' => $hasObservation1,
        ]);

        return $bundle;
    }

    /**
     * Valida que los datos sean suficientes para generar un mensaje HL7 RDA Urgencias.
     *
     * @param array $rdaData
     * @return bool
     */
    public function validateRdaData(array $rdaData): bool
    {
        if (!isset($rdaData['resourceType']) || $rdaData['resourceType'] !== 'Bundle') {
            return false;
        }

        if (!isset($rdaData['type']) || $rdaData['type'] !== 'document') {
            return false;
        }

        if (empty($rdaData['entry'])) {
            return false;
        }

        return true;
    }

    /**
     * Envía el RDA Urgencias (Payload FHIR) al Ministerio utilizando el token OAuth
     * y las llaves de suscripción.
     *
     * @param array $rdaDataUrgencias
     * @param string $tokenIhce
     * @param int|null $ingresoId
     * @return array
     */
    public function sendRdaUrgencias(array $rdaDataUrgencias, string $tokenIhce, ?int $ingresoId = null): array
    {
        $config = config('services.ihce');
        $baseUrl = rtrim($config['base_url'], '/');
        $endpoint = "{$baseUrl}/Composition/\$enviar-rda-urgencias";

        Log::info("Iniciando envío de RDA Urgencias al API IHCE. URL: {$endpoint}");

        $responseBody = [];
        $statusCode = 500;
        $success = false;

        // Nuestro escudo contra caídas y latencias del Minsalud
        $apiTimeout = $config['timeout'] ?? 120;

        try {
            $response = Http::withToken($tokenIhce)
                ->withHeaders([
                    'Ocp-Apim-Subscription-Key' => $config['subscription_key'],
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->connectTimeout($apiTimeout) // Paciencia para conectar (TCP Handshake)
                ->timeout($apiTimeout)        // Paciencia para recibir la respuesta completa
                ->post($endpoint, $rdaDataUrgencias);

            $statusCode = $response->status();
            $success = $response->successful();

            // Laravel extrae el JSON automáticamente (ideal para ver el detalle de los errores 400)
            $responseBody = $response->json();

            // Si el servidor del gobierno responde completamente vacío o con una pantalla HTML de error
            if (is_null($responseBody)) {
                $responseBody = ['raw_body' => $response->body()];
                Log::warning("Respuesta no-JSON o vacía de IHCE Urgencias (HTTP {$statusCode})");
            }

            // Limpieza preventiva si el token expiró repentinamente
            if ($statusCode === 401) {
                Log::warning("Token IHCE rechazado (401) en Urgencias. Limpiando caché.");
                \Illuminate\Support\Facades\Cache::forget('ihce_oauth_token');
            }

            Log::info("Respuesta Ministerio IHCE (Urgencias) recibida - HTTP Status: {$statusCode}");

        } catch (\Exception $e) {
            Log::error("Fallo técnico al comunicar con Ministerio IHCE (Urgencias): " . $e->getMessage());
            $responseBody = ['error_interno' => $e->getMessage()];
        }

        return [
            'success'     => $success,
            'status_code' => $statusCode,
            'response'    => $responseBody
        ];
    }
}