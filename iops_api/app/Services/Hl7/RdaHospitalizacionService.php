<?php

namespace App\Services\Hl7;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Servicio monolítico para generar el Bundle FHIR del RDA Hospitalización.
 * Cajas 1-4: Recursos clínicos | Caja 5: Composition + Bundle wrapper.
 */
class RdaHospitalizacionService extends RdaService
{
    /**
     * Genera el Bundle FHIR completo para RDA Hospitalización.
     *
     * @param int $ingresoId
     * @return array Bundle FHIR listo para json_encode()
     * @throws \Exception
     */
    public function getDataForRda(int $ingresoId): array
    {
        Log::info("Iniciando obtención de datos RDA Hospitalización para ingreso: {$ingresoId}");

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
            //DESCOMENTAR CUANDO SE ENTREGE
            $claseAtencionDate = $this->determinarClaseAtencionDate($ingresoId);

            // $fechaInicio = !empty($claseAtencionDate['fecha_inicio']) ? $claseAtencionDate['fecha_inicio'] : $ingreso->fecha_ingreso;
            // $fechaFin = !empty($claseAtencionDate['fecha_fin']) ? $claseAtencionDate['fecha_fin'] : $ingreso->fecha_egreso;

            //**
            //ELIMINAR CUANDO SE ENTREGUE
            $fechaInicio = $ingreso->fecha_ingreso;
            $fechaFin = $ingreso->fecha_egreso;
            //**
        } catch (\Exception $e) {
            Log::warning("RdaHospitalizacionService: fallo determinarClaseAtencionDate para ingreso {$ingresoId}. Usando fechas de fallback. Error: " . $e->getMessage());

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

        // ── RECURSO 5: OBSERVATION-0 (Ocupación) ──────────────────────────
        $resources[] = $this->buildObservationOccupationResource($patientId);

        // ═════════════════════════════════════════════
        // CAJA 3: Recursos Condition
        // (las queries ya se ejecutaron en el PRE-CÁLCULO arriba)
        // ═════════════════════════════════════════════

        // ── RECURSO 6: CONDITION-0 (Diagnóstico Ingreso - siempre presente) ──
        $resources[] = $diagnosesData['condition0'];

        // ── RECURSO 7: CONDITION-1 (Diagnóstico Egreso - SIEMPRE obligatorio) ──
        $resources[] = $diagnosesData['condition1'];

        // ═════════════════════════════════════════════
        // CAJA 3.5: PROCEDIMIENTOS REALIZADOS
        // ═════════════════════════════════════════════
        $cargosCupsEncounter = $this->getDiagnosticosCuentas($ingresoId);

        $procedimientosBd = [
            [
                'codigo_cups' => $cargosCupsEncounter['code'] ?? '890202' , //TODO
                'nombre_cups' => $cargosCupsEncounter['display'] ?? 'CONSULTA DE PRIMERA VEZ POR OTRAS ESPECIALIDADES MÉDICAS', //TODO
                'fecha_realizacion' => $periodStart, //TODO
                'finalidad_codigo' => '44', //TODO
                'finalidad_nombre' => 'OTRA' //TODO
            ]
        ];

        $procedimientosRefs = [];
        foreach ($procedimientosBd as $index => $procData) {
            $procedureResource = $this->buildProcedureResource($patientId, $practitionerId, $procData, $index);
            $resources[] = $procedureResource; // Se añade al Bundle
            $procedimientosRefs[] = ["reference" => "#" . $procedureResource['id']]; // Se guarda la ref para el Composition
        }

        // ═════════════════════════════════════════════
        // CAJA 4: Incapacidades y Anexo de Epicrisis
        // ═════════════════════════════════════════════

        // ── RECURSO 8: OBSERVATION-1 (Incapacidad SIPE) ──
        $incapacidad = $this->buildObservationIncapacidadResource($ingresoId, $patientId);
        if ($incapacidad !== null) {
            $hasObservation1 = true;
            $resources[] = $incapacidad;
        }

        // ── RECURSO 9: DOCUMENT REFERENCE-0 (Epicrisis PDF) ─────────────
        $resources[] = $this->buildDocumentReferenceEpicrisisResource($ingresoId, $patientId, $organizationId, $periodEnd);

        // ═════════════════════════════════════════════
        // CAJA 4.5: ALERGIAS, MEDICAMENTOS ADMINISTRADOS Y PRESCRITOS
        // ═════════════════════════════════════════════

        // Inicializar las variables de referencias antes de cualquier validación o ciclo.
        $alergiasRefs = [];
        $medicamentosRefs = [];
        $alergiasBd = [];

        $alergiasBd = [
            [
                'tipo_alergia_codigo' => '01', //TODO
                'tipo_alergia_nombre' => 'Medicamento', //TODO
                'descripcion_alergeno' => 'Penicilina' //TODO
            ]
        ];
        //mock de medicamentos administrados
        $medicamentosAdministradosBd = [
            [
                'codigo_ium' => '1P1008851012103', //TODO
                'nombre_medicamento' => 'PARACETAMOL 500mg/1 U TABLETAS DE LIBERACION NO MODIFICADA ORAL (FIREXIFEN) TABLETA 1 U/CAJA X 250' //TODO
            ],
            [
                'codigo_ium' => '1P1008851012103', //TODO
                'nombre_medicamento' => 'PARACETAMOL 500mg/1 U TABLETAS DE LIBERACION NO MODIFICADA ORAL (FIREXIFEN) TABLETA 1 U/CAJA X 250' //TODO
            ]
        ];
        //mock de medicamentos prescritos
        $medicamentosPrescritosBd = [
            [
                'codigo_dci' => '1540', //TODO
                'nombre_medicamento' => 'DIETILCARBAMAZINA' //TODO
            ],
            [
                'codigo_dci' => '1540', //TODO
                'nombre_medicamento' => 'DIETILCARBAMAZINA' //TODO
            ],
        ];

        // Iterar alergias
        foreach ($alergiasBd as $k => $allergyData) {
            $allergyResource = $this->buildAllergyResource($patientId, $allergyData, $k);
            $resources[] = $allergyResource;
            $alergiasRefs[] = ["reference" => "#" . $allergyResource["id"]];
        }

        // Iterar medicamentos administrados
        foreach ($medicamentosAdministradosBd as $i => $medData) {
            $medAdminResource = $this->buildMedicationAdministrationResource($patientId, $periodEnd, $medData, $i);
            $resources[] = $medAdminResource;
            $medicamentosRefs[] = ["reference" => "#" . $medAdminResource["id"]];
        }

        // Iterar medicamentos prescritos (Fórmula médica alta)
        foreach ($medicamentosPrescritosBd as $j => $medData) {
            $medReqResource = $this->buildMedicationRequestResource($patientId, $medData, $j);
            $resources[] = $medReqResource;
            $medicamentosRefs[] = ["reference" => "#" . $medReqResource["id"]];
        }
        // ═════════════════════════════════════════════
        // CAJA 5: Composition + Bundle (Ensamblaje Final)
        // ═════════════════════════════════════════════

        // Timestamp del documento = momento actual de la generación
        $bundleTimestamp = date('Y-m-d\TH:i:sP');

        $composition = $this->buildCompositionResource(
            $patientId,
            $practitionerId,
            $organizationId,
            $periodStart,
            $periodEnd,
            $hasObservation1,
            $procedimientosRefs, // <-- NUEVO PARÁMETRO
            $medicamentosRefs, // <-- NUEVO PARÁMETRO
            $alergiasRefs // <-- NUEVO PARÁMETRO DE ALERGIAS
        );

        return $this->assembleBundle($ingresoId, $uuid, $bundleTimestamp, $composition, $resources);
    }

    private function buildPatientResource(\App\Models\Persona $persona, string $patientId, array $colombianIdentifierData): array
    {
        // ═════════════════════════════════════════════
        // CAJA 1: Datos Demográficos
        // ═════════════════════════════════════════════
        $iso31661NationalityData      = $this->getIso31661Data('numeric', 'Colombia');
        $ethnicGroupData              = $this->getColombianEthnicGroupData('6');
        $disabilityClassificationData = $this->getColombianDisabilityClassificationData('08');

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
        $useOrganization = 'official';
        $organizationIdentifierTypeData = $this->getHl7CatalogItemByName('v2.0203', 170);
        $colombianOrganizationIdentifier = $this->getColombianOrganizationIdentifier('1');
        $TaxIDNumberValue = 'Desconocido';
        $healthcareProviderIdentifierNumber = $this->getHl7CatalogItemByName('v2.0203', 151);
        $healthcareProviderCodePrestador = $this->getColombianOrganizationIdentifier('2');

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

        // Diagnóstico de INGRESO
        $dxIngresoBd = DB::table('public.hc_diagnosticos_ingreso')
            ->where('ingreso', $ingresoId)
            ->orderByDesc('sw_principal')
            ->first();

        $codigoCie10Ingreso = $dxIngresoBd->tipo_diagnostico_id ?? 'Z769';
        $tipoDxId           = (string) ($dxIngresoBd->tipo_diagnostico ?? '1');
        $certezaRips = $mapCertezaRips[$tipoDxId] ?? ['code' => '01', 'display' => 'Impresión Diagnóstica'];

        try {
            $icd10Ingreso = $this->getIcd10Data($codigoCie10Ingreso);
        } catch (\Exception $e) {
            $icd10Ingreso = ['system' => 'http://hl7.org/fhir/sid/icd-10', 'code' => $codigoCie10Ingreso, 'display' => 'SIN DESCRIPCIÓN'];
        }

        // Diagnóstico de EGRESO
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
            // Fallback
            $icd10Egreso       = $icd10Ingreso;
            $certezaRipsEgreso = $certezaRips;
        }

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
        $causaExternaRipsCode    = '38'; //TODO
        $causaExternaRipsDisplay = 'ENFERMEDAD GENERAL'; //TODO

        return [
            "resourceType" => "Encounter",
            "id" => "Encounter-0",
            "meta" => [
                "profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/EncounterHospitalizationRDA"]
            ],
            "status" => "finished",
            "class" => [
                "system" => "http://terminology.hl7.org/CodeSystem/v3-ActCode",
                "code" => "IMP",
                "display" => "inpatient encounter"
            ],
            "type" => [
                ["coding" => [["system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianTechModality", "code" => "01", "display" => "Intramural"]]],
                ["coding" => [["system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/GrupoServicios", "code" => "03", "display" => "Internación"]]],
                ["coding" => [["system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/EntornoAtencion", "code" => "05", "display" => "Institucional"]]] //TODO
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
            "diagnosis" => $encounterDiagnosis,

            // ---------------------------------------------------------
            // FASE 1: BLOQUE DE HOSPITALIZACIÓN (Ingreso y Egreso)
            // ---------------------------------------------------------
            "hospitalization" => [
                "admitSource" => [
                    "coding" => [
                        [
                            "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/ViaIngreso",
                            "code" => "01", //TODO
                            "display" => "DEMANDA ESPONTANEA" // <-- CORREGIDO: Texto exacto exigido por Minsalud
                        ]
                    ]
                ],
                "dischargeDisposition" => [
                    "coding" => [
                        [
                            "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/CondicionyDestinoUsuarioEgreso",
                            "code" => "01", //TODO
                            "display" => "PACIENTE CON DESTINO A SU DOMICILIO" // <-- CORREGIDO: Texto exacto exigido por Minsalud
                        ]
                    ]
                ]
            ]
        ];
    }

    private function buildObservationOccupationResource(string $patientId): array
    {
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

    /**
     * Construye un recurso ProcedureRDA validado por Minsalud.
     */
    private function buildProcedureResource(string $patientId, string $practitionerId, array $procData, int $index): array
    {
        $procedureId = "Procedure-" . $index;

        return [
            "resourceType" => "Procedure",
            "id" => $procedureId,
            "meta" => [
                "profile" => [
                    "https://fhir.minsalud.gov.co/rda/StructureDefinition/ProcedureRDA"
                ]
            ],
            "status" => "completed",
            "category" => [
                "coding" => [
                    [
                        "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianHealthTechnologyCategory",
                        "code" => "01",
                        "display" => "Procedimiento en salud"
                    ]
                ]
            ],
            "code" => [
                "coding" => [
                    [
                        "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/CUPS",
                        "code" => $procData['codigo_cups'],
                        "display" => $procData['nombre_cups']
                    ]
                ],
                "text" => $procData['nombre_cups']
            ],
            "subject" => ["reference" => "#" . $patientId],
            "encounter" => ["reference" => "#Encounter-0"],
            "performedDateTime" => $procData['fecha_realizacion'] ?? date('Y-m-d\TH:i:sP'),
            "performer" => [
                [
                    "actor" => ["reference" => "#" . $practitionerId]
                ]
            ],
            "reasonCode" => [
                [
                    "coding" => [
                        [
                            "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/RIPSFinalidadConsultaVersion2",
                            "code" => $procData['finalidad_codigo'] ?? "44", //TODO
                            "display" => $procData['finalidad_nombre'] ?? "OTRA" //TODO
                        ]
                    ]
                ]
            ],
            // CORREGIDO: Se agregaron los IDs obligatorios para el "slicing" del perfil del Minsalud
            "reasonReference" => [
                [
                    "id" => "MainDiagnosis",
                    "reference" => "#Condition-0"
                ],
                [
                    "id" => "Comobility-1",
                    "reference" => "#Condition-1"
                ]
            ]
        ];
    }

    private function buildObservationIncapacidadResource(int $ingresoId, string $patientId): ?array
    {
        $incapacidadBd = DB::table('public.hc_incapacidades as i')
            ->join('public.hc_evoluciones as e', 'i.evolucion_id', '=', 'e.evolucion_id')
            ->where('e.ingreso', $ingresoId)
            ->select('i.dias_de_incapacidad', 'i.sw_prorroga')
            ->orderByDesc('e.fecha')
            ->first();

        if ($incapacidadBd !== null) {
            $diasIncapacidad = (int) $incapacidadBd->dias_de_incapacidad;
            $hayProrroga     = (string) $incapacidadBd->sw_prorroga === '1';

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

        return null;
    }

    private function buildDocumentReferenceEpicrisisResource(int $ingresoId, string $patientId, string $organizationId, string $fechaDocumento): array
    {
        $epicrisisPdfBase64 = '';

        try {
            Log::info("DEBUG BROWSERSHOT: 1. Iniciando obtención de PDF");

            $respuestaSiis = $this->obtenerEpicrisisSiis($ingresoId);
            if (!$respuestaSiis['success']) {
                $errorMsg = $respuestaSiis['error'] ?? 'Error desconocido del SIIS';
                throw new \Exception("Error SIIS: {$errorMsg}");
            }

            if (!isset($respuestaSiis['pdf_base64']) || empty($respuestaSiis['pdf_base64'])) {
                 throw new \Exception("El endpoint SIIS no devolvió el PDF esperado en su respuesta.");
            }

            $epicrisisPdfBase64 = $respuestaSiis['pdf_base64'];

        } catch (\Exception $e) {
            Log::error("ERROR BROWSERSHOT O SIIS: " . $e->getMessage());
            // Lanza la excepción hacia arriba para que el Controlador (getDataForRda) atrape el error,
            // detenga el flujo y le avise al front-end que falló la generación del PDF.
            throw new \Exception("Fallo crítico: No se pudo generar el PDF de la Epicrisis. " . $e->getMessage());
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

    /**
     * Construye y retorna un MedicationAdministration validado usando IUM para los consumos internos de piso.
     */
    private function buildMedicationAdministrationResource(string $patientId, string $fechaAdministracion, array $medData, int $index): array
    {
        $medAdminId = "MedicationAdministration-" . $index;

        return [
            "resourceType" => "MedicationAdministration",
            "id" => $medAdminId,
            "meta" => [
                "profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/MedicationAdministrationRDA"]
            ],
            "status" => "completed",
            "category" => [
                "coding" => [
                    [
                        "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianHealthTechnologyCategory",
                        "code" => "02", // CORREGIDO: 02 es para Medicamentos //TODO
                        "display" => "Medicamento con registro sanitario" //TODO
                    ]
                ]
            ],
            "medicationCodeableConcept" => [
                "coding" => [
                    [
                        "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/IUM",
                        "code" => $medData['codigo_ium'] ?? "NI", //TODO
                        "display" => $medData['nombre_medicamento'] ?? "Sin información" //TODO
                    ]
                ]
            ],
            "subject" => ["reference" => "#" . $patientId],
            "context" => ["reference" => "#Encounter-0"],
            "effectiveDateTime" => $fechaAdministracion,
            // NUEVO OBLIGATORIO: Referencia a la orden médica
            "request" => ["reference" => "#MedicationRequest-" . $index],
            "dosage" => [
                "route" => [
                    "coding" => [
                        [
                            "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/VAD",
                            "code" => "048", //TODO
                            "display" => "ORAL" //TODO
                        ]
                    ]
                ],
                "dose" => [
                    "value" => 1,
                    "unit" => "mg",
                    "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/UMM",
                    "code" => "173" // <-- Código Oficial: mg/ml //TODO
                ],
                "rateQuantity" => [
                    "value" => 1,
                    "unit" => "h",
                    "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/MedicationTime",
                    "code" => "2" // <-- Código Oficial: Horas //TODO
                ]
            ]
        ];
    }

    /**
     * Construye y retorna un MedicationRequest usando catálogos MipresINN para las órdenes de prescripción al alta.
     */
    private function buildMedicationRequestResource(string $patientId, array $medData, int $index): array
    {
        $medRequestId = "MedicationRequest-" . $index;

        return [
            "resourceType" => "MedicationRequest",
            "id" => $medRequestId,
            "meta" => [
                "profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/MedicationRequestRDA"]
            ],
            "status" => "active",
            "intent" => "order",
            // NUEVO OBLIGATORIO:
            "reportedBoolean" => true,
            "category" => [
                [
                    "coding" => [
                        [
                            "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianHealthTechnologyCategory",
                            "code" => "02", //TODO
                            "display" => "Medicamento con registro sanitario" //TODO
                        ]
                    ]
                ]
            ],
            "medicationCodeableConcept" => [
                "coding" => [
                    [
                        "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/MipresINN",
                        "code" => $medData['codigo_dci'] ?? "NI", //TODO
                        "display" => $medData['nombre_medicamento'] ?? "Sin información" //TODO
                    ]
                ]
            ],
            "subject" => ["reference" => "#" . $patientId],
            "encounter" => ["reference" => "#Encounter-0"],
            // NUEVO OBLIGATORIO: Fecha de autoría y Razón
            "authoredOn" => $medData['fecha'] ?? date('Y-m-d\TH:i:sP'),
            "reasonCode" => [
                [
                    "coding" => [
                        [
                            "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/RIPSFinalidadConsultaVersion2",
                            "code" => "44", //TODO
                            "display" => "OTRA" //TODO
                        ]
                    ]
                ]
            ],
            "dosageInstruction" => [
                [
                    "timing" => [
                        "repeat" => [
                            "duration" => 8.0,  //TODO
                            "durationUnit" => "h" // UCUM standard  //TODO
                        ],
                        "code" => [
                            "coding" => [
                                [
                                    "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/MedicationTime",
                                    "code" => "2", // <-- Código Oficial: Horas  //TODO
                                    "display" => "Horas"  //TODO
                                ]
                            ]
                        ]
                    ],
                    "route" => [
                        "coding" => [
                            [
                                "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/VAD",
                                "code" => "048", // <-- Código Oficial: ORAL  //TODO
                                "display" => "ORAL"  //TODO
                            ]
                        ]
                    ],
                    "doseAndRate" => [
                        [
                            "doseQuantity" => [
                                "value" => 500.0, //TODO
                                "unit" => "mg/ml", //TODO
                                "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/UMM",
                                "code" => "173" // <-- Código Oficial: mg/ml //TODO
                            ],
                            "rateQuantity" => [
                                "value" => 1.0, //TODO
                                "unit" => "Horas", //TODO
                                "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/MedicationTime",
                                "code" => "2" // <-- Código Oficial: Horas //TODO
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Construye y retorna un AllergyIntolerance validado según perfil del Minsalud para RDA.
     */
    private function buildAllergyResource(string $patientId, array $allergyData, int $index): array
    {
        $allergyId = "AllergyIntolerance-" . $index;

        return [
            "resourceType" => "AllergyIntolerance",
            "id" => $allergyId,
            "meta" => [
                "profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/AllergyIntoleranceRDA"]
            ],
            "clinicalStatus" => [
                "coding" => [
                    [
                        "system" => "http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical",
                        "code" => "active",
                        "display" => "Active"
                    ]
                ]
            ],
            "code" => [
                "coding" => [
                    [
                        "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/TipoAlergia",
                        "code" => $allergyData['tipo_alergia_codigo'] ?? "01", //TODO
                        "display" => $allergyData['tipo_alergia_nombre'] ?? "Medicamento" //TODO
                    ]
                ],
                "text" => $allergyData['descripcion_alergeno'] ?? "Alergia no especificada" //TODO
            ],
            "patient" => ["reference" => "#" . $patientId]
        ];
    }

    private function buildCompositionResource(
        string $patientId,
        string $practitionerId,
        string $organizationId,
        string $periodStart,
        string $periodEnd,
        bool $hasObservation1,
        array $procedimientosRefs = [],
        array $medicamentosRefs = [],
        array $alergiasRefs = []
    ): array {

        // 1. Definir la estructura estricta para secciones vacías (Error cmp-1 y NullFlavor resueltos)
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

        // 2. Construcción Dinámica: Sección Incapacidad
        $seccionIncapacidad = [
            "title" => "Datos incapacidad (SIPE – Sistema de Incapacidades y Prestaciones Economicas)",
            "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "105583-9", "display" => "Worker Sick leave form" ] ] ],
        ];
        if ($hasObservation1) {
            $seccionIncapacidad["entry"] = [["reference" => "#Observation-1"]];
        } else {
            $seccionIncapacidad["text"] = $emptySectionData["text"];
            $seccionIncapacidad["emptyReason"] = $emptySectionData["emptyReason"];
        }

        // 3. Construcción Dinámica: Sección Procedimientos
        $seccionProcedimientos = [
            "title" => "Historial de procedimientos",
            "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "47519-4", "display" => "History of Procedures Document" ] ] ],
        ];
        if (!empty($procedimientosRefs)) {
            $seccionProcedimientos["entry"] = $procedimientosRefs;
        } else {
            $seccionProcedimientos["text"] = $emptySectionData["text"];
            $seccionProcedimientos["emptyReason"] = $emptySectionData["emptyReason"];
        }

        // 3.5 Construcción Dinámica: Sección Medicamentos
        $seccionMedicamentos = [
            "title" => "Historial de medicamentos",
            "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "10160-0", "display" => "History of Medication use Narrative" ] ] ],
        ];
        if (!empty($medicamentosRefs)) {
            $seccionMedicamentos["entry"] = $medicamentosRefs;
        } else {
            $seccionMedicamentos["text"] = $emptySectionData["text"];
            $seccionMedicamentos["emptyReason"] = $emptySectionData["emptyReason"];
        }

        // 3.6 Construcción Dinámica: Sección Alergias
        $seccionAlergias = [
            "title" => "Historial de alergias, intolerancias y reacciones adversas",
            "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "48765-2", "display" => "Allergies and adverse reactions Document" ] ] ],
        ];
        if (!empty($alergiasRefs)) {
            $seccionAlergias["entry"] = $alergiasRefs;
        } else {
            $seccionAlergias["text"] = $emptySectionData["text"];
            $seccionAlergias["emptyReason"] = $emptySectionData["emptyReason"];
        }

        // Secciones que siempre estarán vacías por ahora (Factores de riesgo, Resultados, Órdenes)
        $seccionVaciaRiesgos = [
            "title" => "Factores de riesgo",
            "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "75492-9", "display" => "Risk assessment and screening note" ] ] ],
            "text" => $emptySectionData["text"],
            "emptyReason" => $emptySectionData["emptyReason"]
        ];

        $seccionVaciaResultados = [
            "title" => "Resultados del uso de las tecnologías en salud",
            "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "30954-2", "display" => "Relevant diagnostic tests/laboratory data note" ] ] ],
            "text" => $emptySectionData["text"],
            "emptyReason" => $emptySectionData["emptyReason"]
        ];

        $seccionVaciaOrdenes = [
            "title" => "Órdenes, prescripciones o solicitudes de servicio",
            "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "61146-1", "display" => "Orders for services Document" ] ] ],
            "text" => $emptySectionData["text"],
            "emptyReason" => $emptySectionData["emptyReason"]
        ];

        // 4. Consolidar las 11 Secciones Exactas
        $sections = [
            [
                "title" => "Entidad(es) responsable(s) por el plan de beneficios en salud (Hospitalización / Internación)",
                "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "48768-6", "display" => "Payment sources Document" ] ] ],
                "entry" => [["reference" => "#" . $organizationId]]
            ],
            [
                "title" => "Otros datos demográficos",
                "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "74208-0", "display" => "Demographic information + History of occupation Document" ] ] ],
                "entry" => [["reference" => "#Observation-0"]]
            ],
            $seccionIncapacidad,
            [
                "title" => "Historial de diagnósticos de problemas de salud",
                "code" => [ "coding" => [ [ "system" => "http://loinc.org", "code" => "11450-4", "display" => "Problem list - Reported" ] ] ],
                "entry" => [ ["reference" => "#Condition-0"], ["reference" => "#Condition-1"] ]
            ],
            $seccionAlergias,
            $seccionVaciaRiesgos,
            $seccionMedicamentos,
            $seccionProcedimientos,
            $seccionVaciaResultados,
            $seccionVaciaOrdenes,
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
                "profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/CompositionHospitalizationRDA"]
            ],
            "status" => "final",
            "type" => [
                "coding" => [
                    [
                        "system" => "http://loinc.org",
                        "code" => "34105-7",
                        "display" => "Hospital Discharge summary"
                    ]
                ]
            ],
            "subject" => ["reference" => "#" . $patientId],
            "encounter" => ["reference" => "#Encounter-0"],
            "date" => $periodEnd,
            "author" => [["reference" => "#" . $practitionerId]],
            "title" => "RDA Hospitalización",
            "confidentiality" => "N",
            "attester" => [
                [
                    "mode" => "legal",
                    "party" => ["reference" => "#" . $patientId]
                ]
            ],
            "custodian" => ["reference" => "#" . $organizationId],
            "event" => [
                [
                    "period" => [
                        "start" => $periodStart,
                        "end" => $periodEnd
                    ]
                ]
            ],
            "section" => $sections
        ];
    }

    private function assembleBundle(int $ingresoId, string $uuid, string $bundleTimestamp, array $composition, array $resources): array
    {
        $bundleEntry = [];

        // 1. Siempre primero el Composition (exigencia de FHIR para tipo 'document')
        $bundleEntry[] = ["resource" => $composition];

        // 2. Iterar todos los recursos clínicos agregados en las cajas 1 a 4
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

        Log::info("Bundle RDA Hospitalización generado exitosamente para ingreso: {$ingresoId}", [
            'total_entries' => count($bundleEntry),
        ]);

        return $bundle;
    }

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

    public function sendRdaHospitalizacion(array $rdaDataHospitalizacion, string $tokenIhce, ?int $ingresoId = null): array
    {
        $config = config('services.ihce');
        $baseUrl = rtrim($config['base_url'], '/');
        $endpoint = "{$baseUrl}/Composition/\$enviar-rda-hospitalizacion";

        Log::info("Iniciando envío de RDA Hospitalización al API IHCE. URL: {$endpoint}");

        $responseBody = [];
        $statusCode = 500;
        $success = false;

        // Nuestro escudo contra caídas del Minsalud
        $apiTimeout = $config['timeout'] ?? 120;

        try {
            $response = Http::withToken($tokenIhce)
                ->withHeaders([
                    'Ocp-Apim-Subscription-Key' => $config['subscription_key'],
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->timeout($apiTimeout)
                ->connectTimeout($apiTimeout)
                ->post($endpoint, $rdaDataHospitalizacion);

            $statusCode = $response->status();
            $success = $response->successful();

            // Laravel extrae el JSON automáticamente, sea un 200 OK o un 400 de error con el OperationOutcome
            $responseBody = $response->json();

            // Si por alguna razón el servidor del gobierno responde vacío o con HTML en vez de JSON
            if (is_null($responseBody)) {
                $responseBody = ['raw_body' => $response->body()];
                Log::warning("Respuesta no-JSON o vacía de IHCE (HTTP {$statusCode})");
            }

            if ($statusCode === 401) {
                Log::warning("Token IHCE rechazado (401) en Hospitalización. Limpiando caché.");
                \Illuminate\Support\Facades\Cache::forget('ihce_oauth_token');
            }

            Log::info("Respuesta Ministerio IHCE (Hospitalización) recibida - HTTP Status: {$statusCode}");

        } catch (\Exception $e) {
            Log::error("Fallo técnico al comunicar con Ministerio IHCE (Hospitalización): " . $e->getMessage());
            $responseBody = ['error_interno' => $e->getMessage()];
        }

        return [
            'success'     => $success,
            'status_code' => $statusCode,
            'response'    => $responseBody
        ];
    }
}
