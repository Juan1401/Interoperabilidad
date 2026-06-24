<?php

namespace App\Services\Hl7;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

/**
 * Servicio para obtener datos de RDA Paciente (HL7)
 */
class RdaPacienteService extends RdaService
{
    /**
     * Obtiene los datos necesarios para generar un mensaje HL7 RDA de tipo Paciente.
     *
     * @param int $ingresoId
     * @return array
     * @throws \Exception
     */
    public function getDataForRda(int $ingresoId): array
    {
        Log::info("Iniciando obtención de datos RDA Paciente para ingreso: {$ingresoId}");

        // Obtener el ingreso con todas las relaciones necesarias
        $ingreso = $this->getIngresoWithRelations($ingresoId);

        // Validar que el ingreso exista
        if (!$this->validateIngreso($ingreso)) {
            throw new \Exception('Ingreso no encontrado');
        }

        // Obtener datos del paciente desde la relación
        $paciente = $ingreso->paciente;

        if (!$paciente) {
            throw new \Exception("Paciente no encontrado para el ingreso {$ingresoId}");
        }

        // Instanciar la clase Persona para mapear los datos
        $persona = new \App\Models\Persona($paciente);
        $persona->documento = $this->sanitizeForId($persona->documento);

        // Obtener datos del Practitioner
        $practitionerData = $this->getPractitionerData($ingresoId);
        $practitionerData['tercero_id'] = $this->sanitizeForId($practitionerData['tercero_id']);

        // obtner datos Organization
        $organizationData = $this->getOrganizationData();

        /* obtener datos ColombianTechModality
        * @param int $id = 1 Intramural
        */
        $techModalityData = $this->getHl7CatalogItemData(1);
        /* obtener datos GrupoServicios
        * @param int $id = 10 Consulta externa
        */
        $grupoServiciosData = $this->getHl7CatalogItemData(10);

        // Extracción dinámica de datos clínicos desde la base de datos
        $conditionData = $this->getPatientConditionData($ingresoId);
        $conditionFlag = !empty($conditionData);
        $allergyData = $this->getPatientAllergyData($ingresoId);
        $allergyFlag = $allergyData !== null;
        $medicationData = $this->getPatientMedicationData($ingresoId);
        $medicationFlag = $medicationData !== null;
        $familyHistoryData = $this->getPatientFamilyHistoryData($ingresoId);
        $familyHistoryFlag = $familyHistoryData !== null;

        /* obtener datos ColombianPersonIdentifier
        * @param string $code = 'CC' (Cédula ciudadanía)
        */
        $colombianIdentifierData = $this->getColombianPersonIdentifierData($persona->tipo_documento);

        /** Obtener datos ColombianPersonIdentifier
         * @param string $code = 'CC' (Cédula ciudadanía)
         */
        $colombianPractitionerIdentifierData = $this->getColombianPersonIdentifierData($practitionerData['tipo_tercero_id']);

        $patientId = $colombianIdentifierData['code'] . "-" . $persona->documento;
        $practitionerId = $practitionerData['tipo_tercero_id'] . "-" . $practitionerData['tercero_id'];
        $organizationId = $organizationData['codigo_sgsss_ips'];

        $resources = [];

        // Patient
        $resources[] = $this->buildPatientResource($persona, $patientId, $colombianIdentifierData, $ingresoId);
        // Organization
        $resources[] = $this->buildOrganizationResource($organizationData, $organizationId);
        // Practitioner
        $resources[] = $this->buildPractitionerResource($practitionerData, $practitionerId, $colombianPractitionerIdentifierData);

        // Condition
        $conditionRefs = []; // Referencias para inyectar en el Composition
        $conditions = $this->buildConditionResources($conditionFlag, $patientId, $conditionData);
        foreach ($conditions as $condition) {
            if ($condition) {
                $resources[] = $condition;
                $conditionRefs[] = ['reference' => '#' . $condition['id']];
            }
        }

        // Allergy
        $allergyResource = $this->buildAllergyIntoleranceResource($allergyFlag, $patientId, $allergyData);
        if ($allergyResource) {
            $resources[] = $allergyResource;
        }

        // Family History
        $familyHistoryResource = $this->buildFamilyMemberHistoryResource($familyHistoryFlag, $patientId, $familyHistoryData);
        if ($familyHistoryResource) {
            $resources[] = $familyHistoryResource;
        }

        // Medication
        $medicationResource = $this->buildMedicationStatementResource($medicationFlag, $patientId, $medicationData);
        if ($medicationResource) {
            $resources[] = $medicationResource;
        }

        // Composition
        $composition = $this->buildCompositionResource(
            $patientId,
            $practitionerId,
            $organizationId,
            $conditionFlag,
            $allergyFlag,
            $familyHistoryFlag,
            $medicationFlag,
            $techModalityData,
            $grupoServiciosData,
            $conditionRefs
        );

        return $this->assembleBundle($composition, $resources, $ingresoId);
    }

    private function buildPatientResource(\App\Models\Persona $persona, string $patientId, array $colombianIdentifierData, int $ingresoId): array
    {
        /**
         * Inicio Patient
         * */
        /* obtener datos nacionalidad ISO 3166-1
        * @param string $codeType = 'numeric'
        * @param string $display = 'Colombia'
        */
        $iso31661NationalityData = $this->getIso31661Data('numeric', 'Colombia');
        /* obtener datos grupo étnico colombiano
        * @param string $id = '6' (Otras etnias)
        */
        $ethnicGroupData = $this->getColombianEthnicGroupData('6');
        /* obtener datos clasificación de discapacidad colombiana
        * @param string $id = '08' (Sin Discapacidad)
        */
        $disabilityClassificationData = $this->getPatientDisabilityData($ingresoId);
        /* obtener datos identidad de género colombiana
        * @param string $id = '1' (Masculino)
        */
        $patientGenderIdentity = $this->getColombianGenderIdentityData('1');
        /* obtener datos tipo identificador HL7 v2-0203
        * @param string $catalogName = 'v2.0203'
        * @param int $id = 146 (Person number)
        */
        $hl7IdentifierTypeData = $this->getHl7CatalogItemByName('v2.0203', 146);
        /* obtener datos address ExtensionDivipolaMunicipality
        * @param int $id = 1006 (Santiago de Cali)
        */
        $divipolaMunicipality = $this->getDivipolaDataByMunicipalityId(1006);
        /* obtener datos address ExtensionCountryCode
        * @param string $codeType = 'numeric'
        * @param string $display = 'Colombia'
        */
        $addressCountryCode = $this->getIso31661Data('numeric', 'Colombia');
        /* obtener datos address ExtensionResidenceZone
        * @param string $code = '01' (Urbana)
        */
        $addressResidenceZone = $this->getColombianResidenceZoneData('01');
        /* obtener paciente esta activo o inactivo
        * @param boolean $code = true (Activo), false (Inactivo)
        */
        $active = true;
        /* obtener paciente genero 	male | female | other | unknown
        * @param string $code = 'male' (Masculino), 'female' (Femenino), 'other' (Otro), 'unknown' (Desconocido)
        */
        $gender = $persona->sexo == 'F' ? 'female' : 'male';
        /* obtener datos address ExtensionBiologicalGender
        * @param string $id = '01' (Hombre), '02' (Mujer), '03' (Indeterminado o Intersexual)
        */
        $colombianGenderGroup = $this->getColombianGenderGroup('1');
        /* obtener paciente esta deceasedBoolean
        * @param boolean $code = true (Muerto), false (Vivo)
        */
        $deceasedBoolean = false;
        /**
         * Fin Patient
         * */

        return [
            "resourceType" => "Patient",
            "id" => $patientId,
            "meta" => [
                "profile" => [
                    "https://fhir.minsalud.gov.co/rda/StructureDefinition/PatientRDA"
                ]
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
                ],
                [
                    "url" => "https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionPatientGenderIdentity",
                    "valueCoding" => [
                        "system" => $patientGenderIdentity['system'],
                        "code" => $patientGenderIdentity['code'],
                        "display" => $patientGenderIdentity['display']
                    ]
                ]
            ],
            "identifier" => [
                [
                    "type" => [
                        "coding" => [
                            [
                                "system" => $hl7IdentifierTypeData['system'],
                                "code" => $hl7IdentifierTypeData['code'],
                                "display" => $hl7IdentifierTypeData['display']
                            ],
                            [
                                "system" => $colombianIdentifierData['system'],
                                "code" => $colombianIdentifierData['code'],
                                "display" => $colombianIdentifierData['display']
                            ]
                        ]
                    ],
                    "id" => "NationalPersonIdentifier-0",
                    "use" => "official",
                    "system" => "https://fhir.minsalud.gov.co/rda/NamingSystem/RNEC",
                    "value" => $persona->documento
                ]
            ],
            "name" => [
                [
                    "given" => [
                        $persona->primer_nombre,
                        $persona->segundo_nombre
                    ],
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
                    ]
                ]
            ],
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
                                    "code" => $divipolaMunicipality['code'],
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
                                "code"   => $addressResidenceZone['code'],
                                "display" => $addressResidenceZone['display']
                            ]
                        ]
                    ]
                ]
            ],
            "active" => $active,
            "gender" => $gender,
            "_gender" => [
                "extension" => [
                    [
                        "url" => "https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionBiologicalGender",
                        "valueCoding" => [
                            "system" => $colombianGenderGroup['system'],
                            "code" => $colombianGenderGroup['code'],
                            "display" => $colombianGenderGroup['display']
                        ]
                    ]
                ]
            ],
            "birthDate" => $persona->fecha_nacimiento,
            "deceasedBoolean" => $deceasedBoolean
        ];
    }

    private function buildOrganizationResource(array $organizationData, string $organizationId): array
    {
        /**
         * Inicio Organization
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

    private function buildPractitionerResource(array $practitionerData, string $practitionerId, array $colombianPractitionerIdentifierData): array
    {
        /**
         * Inicio Practitioner
         * */

        /** identifier NationalPersonIdentifier-0
         * usual | official | temp | secondary | old (If known)
         * Binding: IdentifierUse (required): Identifies the purpose for this identifier, if known .
         * Fixed Value: official
         */
        $usePractitionerIdentifier = 'official';
        /** Obtener datos NationalPersonIdentifier-0
         * @param string $catalogName = 'v2.0203'
         * @param int $id = 146 (PN)
         */
        $practitionerIdentifierTypeData = $this->getHl7CatalogItemByName('v2.0203', 146);

        /**
         * Fin Practitioner
         * */
        return [
            "resourceType" => "Practitioner",
            "id" => $practitionerId,
            "meta" => [
                "profile" => [
                    "https://fhir.minsalud.gov.co/rda/StructureDefinition/PractitionerRDA"
                ]
            ],
            "identifier" => [
                [
                    "id" => "NationalPersonIdentifier-0",
                    "use" => $usePractitionerIdentifier,
                    "type" => [
                        "coding" => [
                            [
                                "system" => $practitionerIdentifierTypeData['system'],
                                "code" => $practitionerIdentifierTypeData['code'],
                                "display" => $practitionerIdentifierTypeData['display']
                            ],
                            [
                                "system" => $colombianPractitionerIdentifierData['system'],
                                "code" => $colombianPractitionerIdentifierData['code'],
                                "display" => $colombianPractitionerIdentifierData['display']
                            ]
                        ]
                    ],
                    "value" => $practitionerData['tercero_id']
                ]
            ],
            "name" => [
                [
                    "use" => "official",
                    "family" => trim($practitionerData['primer_apellido'] . ' ' . $practitionerData['segundo_apellido']),
                    "_family" => [
                        "extension" => [
                            [
                                "url" => "https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionFathersFamilyName",
                                "valueString" => $practitionerData['primer_apellido']
                            ],
                            [
                                "url" => "https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionMothersFamilyName",
                                "valueString" => $practitionerData['segundo_apellido']
                            ]
                        ]
                    ],
                    "given" => array_filter([
                        $practitionerData['primer_nombre'],
                        $practitionerData['segundo_nombre']
                    ])
                ]
            ]
        ];
    }

    private function buildConditionResources(bool $conditionFlag, string $patientId, ?array $conditionData = null): array
    {
        $conditions = [];

        /**
         * Inicio Condition
         * */
        if ($conditionFlag && $conditionData) {
            /*
            * Extracción dinámica de antecedentes patológicos desde la base de datos.
            * Se genera un recurso Condition por cada antecedente encontrado.
            */

            /* obtener datos ConditionClinicalStatusCodes
            * @param string $name = ConditionClinicalStatusCodes
            * @param int $id = 15 (Active)
            */
            $conditionClinicalData = $this->getHl7CatalogItemByName('ConditionClinicalStatusCodes', 15);
            /* obtener datos verificationStatus
            * @param string $name = ConditionVerificationStatus
            * @param int $id = 21 (unconfirmed)
            */
            $verificationStatusData = $this->getHl7CatalogItemByName('ConditionVerificationStatus', 21);
            /* obtener datos ConditionCategoryCodes
            * @param string $name = ConditionCategoryCodes
            * @param int $id = 28 (problem-list-item)
            */
            $conditionCategoryData = $this->getHl7CatalogItemByName('ConditionCategoryCodes', 28);

            foreach ($conditionData as $index => $patologia) {
                $conditions[] = [
                    "resourceType" => "Condition",
                    "id" => "Condition-" . $index,
                    "meta" => [
                        "profile" => [
                            "https://fhir.minsalud.gov.co/rda/StructureDefinition/ConditionStatementRDA"
                        ]
                    ],
                    "clinicalStatus" => [
                        "coding" => [
                            [
                                "system" => $conditionClinicalData['system'],
                                "code" => $conditionClinicalData['code'],
                                "display" => $conditionClinicalData['display']
                            ]
                        ]
                    ],
                    "verificationStatus" => [
                        "coding" => [
                            [
                                "code" => $verificationStatusData['code'],
                                "display" => $verificationStatusData['display']
                            ]
                        ]
                    ],
                    "category" => [
                        [
                            "coding" => [
                                [
                                    "system" => $conditionCategoryData['system'],
                                    "code" => $conditionCategoryData['code'],
                                    "display" => $conditionCategoryData['display']
                                ]
                            ]
                        ]
                    ],
                    "code" => [
                        "text" => $patologia['display']
                    ],
                    "subject" => [
                        "reference" => "#" . $patientId
                    ]
                ];
            }
        }

        /**
         * Fin Condition
         * */

        return $conditions;
    }

    private function buildAllergyIntoleranceResource(bool $allergyFlag, string $patientId, ?array $allergyData = null): ?array
    {
        /**
         * Inicio AllergyIntolerance
         * */
        if ($allergyFlag && $allergyData) {
            /*
            * Si ya se registra la AllergyIntolerance de los antesedentes
            * Falta por desarrollar
            */

            /* obtener datos clinicalStatus
            * @param string $name = AllergyIntoleranceClinicalStatusCodes
            * @param int $id = 40 (active)
            */
            $clinicalStatusAllergy = $this->getHl7CatalogItemByName('AllergyIntoleranceClinicalStatusCodes', 40);
            /* obtener datos verificationStatus
            * @param string $name = AllergyIntoleranceVerificationStatusCodes
            * @param int $id = 36 (unconfirmed)
            */
            $verificationStatusAllergy = $this->getHl7CatalogItemByName('AllergyIntoleranceVerificationStatusCodes', 36);
            /* obtener datos TipoAlergia
            * @param string $name = TipoAlergia
            * @param int $id = 30 (Medicamento)
            */
            $typeAllergy = $this->getHl7CatalogItemByName('TipoAlergia', 30);

            $typeAllergy['code'] = $allergyData['code'];
            $typeAllergy['display'] = $allergyData['display'];

            // Este dato es opcional
            $textAllergy = $allergyData['text'];

            return [
                "resourceType" => "AllergyIntolerance",
                "id" => "AllergyIntolerance-0",
                "meta" => [
                    "profile" => [
                        "https://fhir.minsalud.gov.co/rda/StructureDefinition/AllergyIntoleranceStatementRDA"
                    ]
                ],
                "clinicalStatus" => [
                    "coding" => [
                        [
                            "code" => $clinicalStatusAllergy['code'],
                            "display" => $clinicalStatusAllergy['display']
                        ]
                    ]
                ],
                "verificationStatus" => [
                    "coding" => [
                        [
                            "code" => $verificationStatusAllergy['code'],
                            "display" => $verificationStatusAllergy['display']
                        ]
                    ]
                ],
                "code" => [
                    "coding" => [
                        [
                            "system" => $typeAllergy['system'],
                            "code" => $typeAllergy['code'],
                            "display" => $typeAllergy['display']
                        ]
                    ],
                    "text" => $textAllergy
                ],
                "patient" => [
                    "reference" => "#" . $patientId
                ]
            ];
        }

        return null;
    }

    private function buildMedicationStatementResource(bool $medicationFlag, string $patientId, ?array $medicationData = null): ?array
    {
        /**
         * Inicio MedicationStatement
         * */
        if ($medicationFlag && $medicationData) {
            /*
            * Si no se registra la MedicationStatement de los antesedentes
            * Falta por desarrollar
            */

            /* obtener datos MedicationStatementStatusCodes
            * @param string $name = MedicationStatementStatusCodes
            * @param int $id = 44 (completed)
            */
            $medicationStatementData = $this->getHl7CatalogItemByName('MedicationStatementStatusCodes', 44);
            
            /* obtener datos MedicationStatement tabla de mipres_inn
            * @param string $code = 626 (PARACETAMOL)
            */
            $baseSystem = $this->getMipresInnData('626');
            $mipresInnData = [
                'system' => $baseSystem['system'],
                'code' => $medicationData['code'],
                'display' => $medicationData['display']
            ];

            return [
                "resourceType" => "MedicationStatement",
                "id" => "MedicationStatement-0",
                "meta" => [
                    "profile" => [
                        "https://fhir.minsalud.gov.co/rda/StructureDefinition/MedicationStatementRDA"
                    ]
                ],
                "status" => $medicationStatementData['code'],
                "medicationCodeableConcept" => [
                    "coding" => [
                        [
                            "system" => $mipresInnData['system'],
                            "code" => $mipresInnData['code'],
                            "display" => $mipresInnData['display']
                        ]
                    ]
                ],
                "subject" => [
                    "reference" => "#" . $patientId
                ]
            ];
        }

        return null;
    }

    private function buildFamilyMemberHistoryResource(bool $familyHistoryFlag, string $patientId, ?array $familyHistoryData = null): ?array
    {
        /**
         * Inicio FamilyMemberHistory
         * */
        if ($familyHistoryFlag && $familyHistoryData) {
            /*
            * Si no se registra la FamilyMemberHistory de los antesedentes
            * Falta por desarrollar
            */

            /* obtener datos FamilyMemberHistoryStatusCodes
            * @param string $name = FamilyHistoryStatus
            * @param int $id = 44 (completed)
            */
            $familyHistoryStatusData = $this->getHl7CatalogItemByName('FamilyHistoryStatus', 51);

            /* obtener datos FamilyMemberHistory tabla de mipres_inn
            * @param string $code = K359 (Apendicitis aguda)
            */
            $icd10DataFamilyMemberHistory = [
                'system' => 'http://hl7.org/fhir/sid/icd-10',
                'code' => $familyHistoryData['condition_code'],
                'display' => $familyHistoryData['condition_display']
            ];

            /* obtener datos ParentescoAntecedente
            * @param string $name = ParentescoAntecedente
            * @param int $id = 55 (Padres)
            */
            $parentescoAntecedenteData = $this->getHl7CatalogItemByName('ParentescoAntecedente', 55);
            $parentescoAntecedenteData['code'] = $familyHistoryData['relationship_code'];
            $parentescoAntecedenteData['display'] = $familyHistoryData['relationship_display'];

            return [
                "resourceType" => "FamilyMemberHistory",
                "id" => "FamilyMemberHistory-0",
                "meta" => [
                    "profile" => [
                        "https://fhir.minsalud.gov.co/rda/StructureDefinition/FamilyMemberHistoryRDA"
                    ]
                ],
                "status" => $familyHistoryStatusData['code'],
                "patient" => [
                    "reference" => "#" . $patientId
                ],
                "relationship" => [
                    "coding" => [
                        [
                            "system" => $parentescoAntecedenteData['system'],
                            "code" => $parentescoAntecedenteData['code'],
                            "display" => $parentescoAntecedenteData['display']
                        ]
                    ]
                ],
                "condition" => [
                    [
                        "code" => [
                            "coding" => [
                                [
                                    "system" => $icd10DataFamilyMemberHistory['system'],
                                    "code" => $icd10DataFamilyMemberHistory['code'],
                                    "display" => $icd10DataFamilyMemberHistory['display']
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        }

        return null;
    }

    private function buildCompositionResource(
        string $patientId,
        string $practitionerId,
        string $organizationId,
        bool $conditionFlag,
        bool $allergyFlag,
        bool $familyHistoryFlag,
        bool $medicationFlag,
        array $techModalityData,
        array $grupoServiciosData,
        array $conditionRefs = []
    ): array {
        // Preparar las secciones de Composition de forma condicional
        $compositionSections = [];

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

        if ($conditionFlag && !empty($conditionRefs)) {
            $compositionSections[] = [
                "title" => "Historial de diagnósticos de problemas de salud",
                "code" => [
                    "coding" => [
                        [
                            "system" => "http://loinc.org",
                            "code" => "11450-4",
                            "display" => "Problem list - Reported"
                        ]
                    ]
                ],
                "entry" => $conditionRefs
            ];
        } else {
            $compositionSections[] = array_merge([
                "title" => "Historial de diagnósticos de problemas de salud",
                "code" => [
                    "coding" => [
                        [
                            "system" => "http://loinc.org",
                            "code" => "11450-4",
                            "display" => "Problem list - Reported"
                        ]
                    ]
                ]
            ], $emptySectionData);
        }

        if ($allergyFlag) {
            $compositionSections[] = [
                "title" => "Historial de alergias, intolerancias y reacciones adversas",
                "code" => [
                    "coding" => [
                        [
                            "system" => "http://loinc.org",
                            "code" => "48765-2",
                            "display" => "Allergies and adverse reactions Document"
                        ]
                    ]
                ],
                "entry" => [
                    [
                        "reference" => "#AllergyIntolerance-0"
                    ]
                ]
            ];
        } else {
            $compositionSections[] = array_merge([
                "title" => "Historial de alergias, intolerancias y reacciones adversas",
                "code" => [
                    "coding" => [
                        [
                            "system" => "http://loinc.org",
                            "code" => "48765-2",
                            "display" => "Allergies and adverse reactions Document"
                        ]
                    ]
                ]
            ], $emptySectionData);
        }

        if ($familyHistoryFlag) {
            $compositionSections[] = [
                "title" => "Historial de antecedentes familiares",
                "code" => [
                    "coding" => [
                        [
                            "system" => "http://loinc.org",
                            "code" => "10157-6",
                            "display" => "History of family member diseases Narrative"
                        ]
                    ]
                ],
                "entry" => [
                    [
                        "reference" => "#FamilyMemberHistory-0"
                    ]
                ]
            ];
        } else {
            $compositionSections[] = array_merge([
                "title" => "Historial de antecedentes familiares",
                "code" => [
                    "coding" => [
                        [
                            "system" => "http://loinc.org",
                            "code" => "10157-6",
                            "display" => "History of family member diseases Narrative"
                        ]
                    ]
                ]
            ], $emptySectionData);
        }

        if ($medicationFlag) {
            $compositionSections[] = [
                "title" => "Historial de medicamentos",
                "code" => [
                    "coding" => [
                        [
                            "system" => "http://loinc.org",
                            "code" => "10160-0",
                            "display" => "History of Medication use Narrative"
                        ]
                    ]
                ],
                "entry" => [
                    [
                        "reference" => "#MedicationStatement-0"
                    ]
                ]
            ];
        } else {
            $compositionSections[] = array_merge([
                "title" => "Historial de medicamentos",
                "code" => [
                    "coding" => [
                        [
                            "system" => "http://loinc.org",
                            "code" => "10160-0",
                            "display" => "History of Medication use Narrative"
                        ]
                    ]
                ]
            ], $emptySectionData);
        }

        return [
            "resourceType" => "Composition",
            "meta" => [
                "profile" => [
                    "https://fhir.minsalud.gov.co/rda/StructureDefinition/CompositionPatientStatementRDA"
                ]
            ],
            "status" => "final",
            "id" => "Composition-0",
            "type" => [
                "coding" => [
                    [
                        "system" => "http://loinc.org",
                        "code" => "102089-0",
                        "display" => "FHIR resource patient medical record"
                    ]
                ]
            ],
            "subject" => [
                "reference" => "#" . $patientId
            ],
            "date" => date('c'),
            "author" => [
                [
                    "reference" => "#" . $practitionerId
                ]
            ],
            "title" => "Resumen Digital de Atención en Salud - RDA de antecedentes manifestados por el paciente",
            "confidentiality" => "N",
            "attester" => [
                [
                    "mode" => "legal",
                    "party" => [
                        "reference" => "#" . $organizationId
                    ]
                ]
            ],
            "custodian" => [
                "reference" => "#" . $organizationId
            ],
            "event" => [
                [
                    "code" => [
                        [
                            "coding" => [
                                [
                                    "system" => $techModalityData['system'],
                                    "code" => $techModalityData['code'],
                                    "display" => $techModalityData['display']
                                ]
                            ]
                        ],
                        [
                            "coding" => [
                                [
                                    "system" => $grupoServiciosData['system'],
                                    "code" => $grupoServiciosData['code'],
                                    "display" => $grupoServiciosData['display']
                                ]
                            ]
                        ]
                    ],
                    "period" => [
                        "start" => date('c'),
                        "end" => date('c', strtotime('+1 hour'))
                    ]
                ]
            ],
            "section" => $compositionSections
        ];
    }

    private function assembleBundle(array $composition, array $resources, int $ingresoId): array
    {
        $bundleEntries = [];

        $bundleEntries[] = ["resource" => $composition];

        foreach ($resources as $resource) {
            $bundleEntries[] = ["resource" => $resource];
        }

        $rdaData = [
            "resourceType" => "Bundle",
            "language" => "es-CO",
            "type" => "document",
            "entry" => $bundleEntries
        ];

        Log::info("Datos RDA Paciente obtenidos exitosamente para ingreso: {$ingresoId}");

        return $rdaData;
    }

    /**
     * Valida que los datos sean suficientes para generar un mensaje HL7 RDA Paciente.
     *
     * @param array $rdaData
     * @return bool
     */
    public function validateRdaData(array $rdaData): bool
    {
        // Validar campos obligatorios para HL7
        if (!isset($rdaData['resourceType']) || $rdaData['resourceType'] !== 'Bundle') {
            return false;
        }
        return true;
    }

    /**
     * Envía el RDA Paciente (Payload FHIR) al Ministerio utilizando el token OAuth
     * y las llaves de suscripción. Funciona como un orquestador seguro implementando persistencia.
     *
     * @param array $rdaDataPaciente
     * @param string $tokenIhce
     * @param int|null $ingresoId
     * @return array
     */
    public function sendRdaPaciente(array $rdaDataPaciente, string $tokenIhce, ?int $ingresoId = null): array
    {
        $config = config('services.ihce');
        $baseUrl = rtrim($config['base_url'], '/');
        // El endpoint exacto parametrizado
        $endpoint = "{$baseUrl}/Composition/\$enviar-rda-paciente";

        Log::info("Iniciando envío de RDA Paciente al API IHCE. URL: {$endpoint}");

        $responseBody = [];
        $statusCode = 500;
        $success = false;
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
                ->post($endpoint, $rdaDataPaciente);

            $statusCode = $response->status();
            $success = $response->successful();

            // Laravel extrae el JSON automáticamente (ideal para ver el detalle de los errores 400)
            $responseBody = $response->json();

            // Si el servidor del gobierno responde completamente vacío o con una pantalla HTML de error
            if (is_null($responseBody)) {
                $responseBody = ['raw_body' => $response->body()];
                Log::warning("Respuesta no-JSON o vacía de IHCE Paciente (HTTP {$statusCode})");
            }

            // Limpieza preventiva si el token expiró repentinamente
            if ($statusCode === 401) {
                Log::warning("Token IHCE rechazado (401) en Paciente. Limpiando caché.");
                \Illuminate\Support\Facades\Cache::forget('ihce_oauth_token');
            }

            Log::info("Respuesta Ministerio IHCE (Paciente) recibida - HTTP Status: {$statusCode}");

        } catch (\Exception $e) {
            Log::error("Fallo técnico al comunicar con Ministerio IHCE (Paciente): " . $e->getMessage());
            $responseBody = ['error_interno' => $e->getMessage()];
        }

        return [
            'success'     => $success,
            'status_code' => $statusCode,
            'response'    => $responseBody
        ];
    }
}
