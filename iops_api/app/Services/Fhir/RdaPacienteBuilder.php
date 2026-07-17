<?php

namespace App\Services\Fhir;

use Illuminate\Support\Str;

/**
 * Builder que construye un Bundle FHIR HL7 para el RDA de Paciente
 * a partir de un array JSON estático (payload del formulario).
 *
 * Patrón Adapter: transforma los datos del formulario Angular en la estructura
 * exacta que Minsalud espera, sin depender de la base de datos.
 */
class RdaPacienteBuilder
{
    // ─── Constantes de OIDs y Systems de Minsalud (no cambian) ───

    private const PROFILE_PATIENT    = 'https://fhir.minsalud.gov.co/rda/StructureDefinition/PatientRDA';
    private const PROFILE_ORG        = 'https://fhir.minsalud.gov.co/rda/StructureDefinition/CareDeliveryOrganizationRDA';
    private const PROFILE_PRACT      = 'https://fhir.minsalud.gov.co/rda/StructureDefinition/PractitionerRDA';
    private const PROFILE_CONDITION  = 'https://fhir.minsalud.gov.co/rda/StructureDefinition/ConditionStatementRDA';
    private const PROFILE_ALLERGY    = 'https://fhir.minsalud.gov.co/rda/StructureDefinition/AllergyIntoleranceStatementRDA';
    private const PROFILE_MEDICATION = 'https://fhir.minsalud.gov.co/rda/StructureDefinition/MedicationStatementRDA';
    private const PROFILE_FAMILY     = 'https://fhir.minsalud.gov.co/rda/StructureDefinition/FamilyMemberHistoryRDA';
    private const PROFILE_COMPOSITION = 'https://fhir.minsalud.gov.co/rda/StructureDefinition/CompositionPatientStatementRDA';

    private const SYS_PERSON_ID      = 'https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianPersonIdentifier';
    private const SYS_ORG_ID         = 'https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianOrganizationIdentifier';
    private const SYS_ISO31661       = 'https://fhir.minsalud.gov.co/rda/CodeSystem/ISO31661';
    private const SYS_ETHNIC         = 'https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianEthnicGroup';
    private const SYS_DISABILITY     = 'https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianDisabilityClassification';
    private const SYS_GENDER_ID      = 'https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianGenderIdentity';
    private const SYS_GENDER_GROUP   = 'https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianGenderGroup';
    private const SYS_RESIDENCE_ZONE = 'https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianResidenceZone';
    private const SYS_V2_0203        = 'http://terminology.hl7.org/CodeSystem/v2-0203';
    private const SYS_DIVIPOLA       = 'https://fhir.minsalud.gov.co/rda/CodeSystem/DIVIPOLA';
    private const SYS_RNEC           = 'https://fhir.minsalud.gov.co/rda/NamingSystem/RNEC';
    private const SYS_REPS           = 'https://fhir.minsalud.gov.co/rda/NamingSystem/REPS';
    private const SYS_ICD10          = 'http://hl7.org/fhir/sid/icd-10';
    private const SYS_MIPRES_INN     = 'https://fhir.minsalud.gov.co/rda/CodeSystem/MipresINN';
    private const SYS_LOINC          = 'http://loinc.org';
    private const SYS_CONDITION_CLINICAL = 'http://terminology.hl7.org/CodeSystem/condition-clinical';
    private const SYS_CONDITION_VERIF    = 'http://terminology.hl7.org/CodeSystem/condition-ver-status';
    private const SYS_CONDITION_CATEGORY = 'http://terminology.hl7.org/CodeSystem/condition-category';
    private const SYS_ALLERGY_CLINICAL   = 'http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical';
    private const SYS_ALLERGY_VERIF      = 'http://terminology.hl7.org/CodeSystem/allergyintolerance-verification';
    private const SYS_TIPO_ALERGIA       = 'https://fhir.minsalud.gov.co/rda/CodeSystem/TipoAlergia';
    private const SYS_MEDICATION_STATUS  = 'http://hl7.org/fhir/CodeSystem/medication-statement-status';
    private const SYS_FAMILY_STATUS      = 'http://hl7.org/fhir/history-status';
    private const SYS_PARENTESCO         = 'https://fhir.minsalud.gov.co/rda/CodeSystem/ParentescoAntecedente';
    private const SYS_TECH_MODALITY      = 'https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianTechModality';
    private const SYS_GRUPO_SERVICIOS    = 'https://fhir.minsalud.gov.co/rda/CodeSystem/GrupoServicios';
    private const SYS_EMPTY_REASON       = 'http://terminology.hl7.org/CodeSystem/list-empty-reason';

    // ─── Datos fijos de Colombia que no cambian entre envíos ───

    /** Código numérico ISO 3166-1 de Colombia */
    private const COUNTRY_CODE    = '170';
    private const COUNTRY_DISPLAY = 'Colombia';

    /**
     * Construye el Bundle FHIR completo a partir del payload del formulario Angular.
     *
     * @param array $payload El array con la estructura:
     *   - caja_1_demograficos: {paciente, organizacion, profesional}
     *   - caja_antecedentes: {patologicos[], alergias[], familiares[], farmacologicos[]}
     * @return array El Bundle FHIR listo para enviar a Minsalud.
     */
    public function build(array $payload): array
    {
        // ── Extraer secciones del payload ──
        $paciente     = $payload['caja_1_demograficos']['paciente'] ?? [];
        $organizacion = $payload['caja_1_demograficos']['organizacion'] ?? [];
        $profesional  = $payload['caja_1_demograficos']['profesional'] ?? [];
        $antecedentes = $payload['caja_antecedentes'] ?? [];

        // ── Generar IDs deterministas ──
        $organizationId = $organizacion['codigo_habilitacion'] ?? '000000000000';
        $patientId      = ($paciente['tipo_documento'] ?? 'CC') . '-' . ($paciente['numero_documento'] ?? '0');
        $practitionerId = ($profesional['tipo_documento'] ?? 'CC') . '-' . ($profesional['numero_documento'] ?? '0');
        $compositionId  = 'Composition-0';

        // ── Construir los recursos FHIR ──
        $resources = [];

        $resources[] = $this->buildPatientResource($paciente, $patientId);
        $resources[] = $this->buildOrganizationResource($organizacion, $organizationId);
        $resources[] = $this->buildPractitionerResource($profesional, $practitionerId);

        // Conditions (Antecedentes patológicos)
        $conditionRefs = [];
        $patologicos = $antecedentes['patologicos'] ?? [];
        $conditions = $this->buildConditionResources($patologicos, $patientId);
        foreach ($conditions as $condition) {
            $resources[] = $condition;
            $conditionRefs[] = ['reference' => '#' . $condition['id']];
        }

        // Alergias
        $alergias = $antecedentes['alergias'] ?? [];
        $allergyResources = $this->buildAllergyResources($alergias, $patientId);
        $allergyRefs = [];
        foreach ($allergyResources as $allergy) {
            $resources[] = $allergy;
            $allergyRefs[] = ['reference' => '#' . $allergy['id']];
        }

        // Antecedentes familiares
        $familiares = $antecedentes['familiares'] ?? [];
        $familyResources = $this->buildFamilyMemberHistoryResources($familiares, $patientId);
        $familyRefs = [];
        foreach ($familyResources as $family) {
            $resources[] = $family;
            $familyRefs[] = ['reference' => '#' . $family['id']];
        }

        // Medicamentos (farmacológicos)
        $farmacologicos = $antecedentes['farmacologicos'] ?? [];
        $medicationResources = $this->buildMedicationStatementResources($farmacologicos, $patientId);
        $medicationRefs = [];
        foreach ($medicationResources as $medication) {
            $resources[] = $medication;
            $medicationRefs[] = ['reference' => '#' . $medication['id']];
        }

        // Composition (documento que ensambla todo)
        $composition = $this->buildCompositionResource(
            $compositionId,
            $patientId,
            $practitionerId,
            $organizationId,
            $conditionRefs,
            $allergyRefs,
            $familyRefs,
            $medicationRefs
        );

        return $this->assembleBundle($composition, $resources);
    }

    // ─── Builders de recursos individuales ──────────────────────────────────

    /**
     * Construye el recurso Patient FHIR.
     */
    private function buildPatientResource(array $paciente, string $patientId): array
    {
        $tipoDoc   = $paciente['tipo_documento'] ?? 'CC';
        $numDoc    = $paciente['numero_documento'] ?? '';
        $nombres   = $paciente['nombres'] ?? '';
        $apellidos = $paciente['apellidos'] ?? '';
        $fechaNac  = $paciente['fecha_nacimiento'] ?? '';
        $generoBio = $paciente['genero_biologico'] ?? '01'; // 01=Hombre, 02=Mujer
        $codMpio   = empty($paciente['codigo_municipio']) ? '76001' : $paciente['codigo_municipio'];

        // Variables demográficas dinámicas
        $etnia        = $paciente['etnia'] ?? '6';
        $discapacidad = $paciente['discapacidad'] ?? '08';
        $identidad    = $paciente['identidad_genero'] ?? '04';

        // Mapeo de displays
        $etniaMap = [
            '1' => 'Indígena', '2' => 'ROM (Gitano)', '3' => 'Raizal',
            '4' => 'Palenquero de San Basilio', '5' => 'Negro(a), Mulato(a), Afrocolombiano(a)', '6' => 'Otras etnias'
        ];
        $discapacidadMap = [
            '01' => 'Discapacidad física',
            '02' => 'Discapacidad visual',
            '03' => 'Discapacidad auditiva',
            '04' => 'Discapacidad intelectual',
            '05' => 'Discapacidad sicosocial',
            '06' => 'Sordoceguera',
            '07' => 'Discapacidad múltiple',
            '08' => 'Sin discapacidad'
        ];
        $identidadMap = [
            '01' => 'Masculino', '02' => 'Femenino', '03' => 'Transgénero', '04' => 'Neutro'
        ];

        // Mapeo de género biológico colombiano → FHIR gender
        $genderMap = ['01' => 'male', '02' => 'female', '03' => 'other'];
        $gender = $genderMap[$generoBio] ?? 'unknown';

        // Mapeo de display del grupo de género colombiano
        $genderGroupDisplay = ['01' => 'Hombre', '02' => 'Mujer', '03' => 'Indeterminado o Intersexual'];

        // Mapeo de display del tipo de documento colombiano
        $docTypeDisplay = [
            'CC' => 'Cédula de ciudadanía',
            'TI' => 'Tarjeta de identidad',
            'CE' => 'Cédula de extranjería',
            'PA' => 'Pasaporte',
            'RC' => 'Registro civil',
            'MS' => 'Menor sin identificar',
            'AS' => 'Adulto sin identificar',
        ];

        return [
            'resourceType' => 'Patient',
            'id' => $patientId,
            'meta' => [
                'profile' => [self::PROFILE_PATIENT],
            ],
            'extension' => [
                [
                    'url' => 'https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionPatientNationality',
                    'valueCoding' => [
                        'system'  => self::SYS_ISO31661,
                        'code'    => self::COUNTRY_CODE,
                        'display' => self::COUNTRY_DISPLAY,
                    ],
                ],
                [
                    'url' => 'https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionPatientEthnicity',
                    'valueCoding' => [
                        'system'  => self::SYS_ETHNIC,
                        'code'    => $etnia,
                        'display' => $etniaMap[$etnia],
                    ],
                ],
                [
                    'url' => 'https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionPatientDisability',
                    'valueCoding' => [
                        'system'  => self::SYS_DISABILITY,
                        'code'    => $discapacidad,
                        'display' => $discapacidadMap[$discapacidad],
                    ],
                ],
                [
                    'url' => 'https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionPatientGenderIdentity',
                    'valueCoding' => [
                        'system'  => self::SYS_GENDER_ID,
                        'code'    => $identidad,
                        'display' => $identidadMap[$identidad],
                    ],
                ],
            ],
            'identifier' => [
                [
                    'id'  => 'NationalPersonIdentifier-0',
                    'use' => 'official',
                    'type' => [
                        'coding' => [
                            [
                                'system'  => self::SYS_V2_0203,
                                'code'    => 'PN',
                                'display' => 'Person number',
                            ],
                            [
                                'system'  => self::SYS_PERSON_ID,
                                'code'    => $tipoDoc,
                                'display' => $docTypeDisplay[$tipoDoc] ?? $tipoDoc,
                            ],
                        ],
                    ],
                    'system' => self::SYS_RNEC,
                    'value'  => $numDoc,
                ],
            ],
            'name' => [
                [
                    'use'    => 'official',
                    'given'  => array_filter(explode(' ', $nombres)),
                    'family' => $apellidos,
                    '_family' => [
                        'extension' => [
                            [
                                'url' => 'https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionFathersFamilyName',
                                'valueString' => explode(' ', $apellidos)[0] ?? $apellidos,
                            ],
                            [
                                'url' => 'https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionMothersFamilyName',
                                'valueString' => explode(' ', $apellidos)[1] ?? '',
                            ],
                        ],
                    ],
                ],
            ],
            'address' => [
                [
                    'id'   => 'HomeAddress-0',
                    'use'  => 'home',
                    'type' => 'physical',
                    'city' => $codMpio,
                    '_city' => [
                        'extension' => [
                            [
                                'url' => 'https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionDivipolaMunicipality',
                                'valueCoding' => [
                                    'code'   => $codMpio,
                                    'system' => self::SYS_DIVIPOLA,
                                ],
                            ],
                        ],
                    ],
                    'country' => self::COUNTRY_DISPLAY,
                    '_country' => [
                        'extension' => [
                            [
                                'url' => 'https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionCountryCode',
                                'valueCoding' => [
                                    'system' => self::SYS_ISO31661,
                                    'code'   => self::COUNTRY_CODE,
                                ],
                            ],
                        ],
                    ],
                    'extension' => [
                        [
                            'url' => 'https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionResidenceZone',
                            'valueCoding' => [
                                'system'  => self::SYS_RESIDENCE_ZONE,
                                'code'    => '01',
                                'display' => 'Urbana',
                            ],
                        ],
                    ],
                ],
            ],
            'active'          => true,
            'gender'          => $gender,
            '_gender' => [
                'extension' => [
                    [
                        'url' => 'https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionBiologicalGender',
                        'valueCoding' => [
                            'system'  => self::SYS_GENDER_GROUP,
                            'code'    => $generoBio,
                            'display' => $genderGroupDisplay[$generoBio] ?? 'Hombre',
                        ],
                    ],
                ],
            ],
            'birthDate'       => $fechaNac,
            'deceasedBoolean' => false,
        ];
    }

    /**
     * Construye el recurso Organization FHIR.
     */
    private function buildOrganizationResource(array $org, string $organizationId): array
    {
        $nit               = $org['nit'] ?? '';
        $nombre            = $org['nombre'] ?? '';
        $codigoHabilitacion = $org['codigo_habilitacion'] ?? '';

        return [
            'resourceType' => 'Organization',
            'id' => $organizationId,
            'meta' => [
                'profile' => [self::PROFILE_ORG],
            ],
            'identifier' => [
                [
                    'id'  => 'TaxIdentifier-0',
                    'use' => 'official',
                    'type' => [
                        'coding' => [
                            [
                                'system'  => self::SYS_V2_0203,
                                'code'    => 'TAX',
                                'display' => 'Tax ID number',
                            ],
                            [
                                'system'  => self::SYS_ORG_ID,
                                'code'    => 'NI',
                                'display' => 'NIT',
                            ],
                        ],
                    ],
                    'value' => $nit,
                ],
                [
                    'id'  => 'HealthcareProviderIdentifier-0',
                    'use' => 'official',
                    'type' => [
                        'coding' => [
                            [
                                'system'  => self::SYS_V2_0203,
                                'code'    => 'PRN',
                                'display' => 'Provider number',
                            ],
                            [
                                'system'  => self::SYS_ORG_ID,
                                'code'    => 'CodigoPrestador',
                                'display' => 'Prestador',
                            ],
                        ],
                    ],
                    'system' => self::SYS_REPS,
                    'value'  => $codigoHabilitacion,
                ],
            ],
            'name' => $nombre,
        ];
    }

    /**
     * Construye el recurso Practitioner FHIR.
     */
    private function buildPractitionerResource(array $prof, string $practitionerId): array
    {
        $tipoDoc   = $prof['tipo_documento'] ?? 'CC';
        $numDoc    = $prof['numero_documento'] ?? '';
        $nombres   = $prof['nombres'] ?? '';
        $apellidos = $prof['apellidos'] ?? '';

        // Mapeo de display del tipo de documento
        $docTypeDisplay = [
            'CC' => 'Cédula de ciudadanía',
            'CE' => 'Cédula de extranjería',
            'PA' => 'Pasaporte',
        ];

        $apellidosParts = explode(' ', $apellidos);

        return [
            'resourceType' => 'Practitioner',
            'id' => $practitionerId,
            'meta' => [
                'profile' => [self::PROFILE_PRACT],
            ],
            'identifier' => [
                [
                    'id'  => 'NationalPersonIdentifier-0',
                    'use' => 'official',
                    'type' => [
                        'coding' => [
                            [
                                'system'  => self::SYS_V2_0203,
                                'code'    => 'PN',
                                'display' => 'Person number',
                            ],
                            [
                                'system'  => self::SYS_PERSON_ID,
                                'code'    => $tipoDoc,
                                'display' => $docTypeDisplay[$tipoDoc] ?? $tipoDoc,
                            ],
                        ],
                    ],
                    'value' => $numDoc,
                ],
            ],
            'name' => [
                [
                    'use'    => 'official',
                    'family' => $apellidos,
                    '_family' => [
                        'extension' => [
                            [
                                'url' => 'https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionFathersFamilyName',
                                'valueString' => $apellidosParts[0] ?? $apellidos,
                            ],
                            [
                                'url' => 'https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionMothersFamilyName',
                                'valueString' => $apellidosParts[1] ?? '',
                            ],
                        ],
                    ],
                    'given' => array_filter(explode(' ', $nombres)),
                ],
            ],
        ];
    }

    /**
     * Construye recursos Condition (antecedentes patológicos).
     * Genera un recurso por cada antecedente patológico.
     *
     * @param array $patologicos Array de antecedentes: [{diagnostico: string, codigo_cie10: string}]
     * @param string $patientId UUID del paciente.
     * @return array Lista de recursos Condition.
     */
    private function buildConditionResources(array $patologicos, string $patientId): array
    {
        if (empty($patologicos)) {
            return [];
        }

        $conditions = [];

        foreach ($patologicos as $index => $patologia) {
            $conditionId = 'Condition-' . $index;

            $conditions[] = [
                'resourceType' => 'Condition',
                'id' => $conditionId,
                'meta' => [
                    'profile' => [self::PROFILE_CONDITION],
                ],
                'clinicalStatus' => [
                    'coding' => [
                        [
                            'system'  => self::SYS_CONDITION_CLINICAL,
                            'code'    => 'active',
                            'display' => 'Active',
                        ],
                    ],
                ],
                'verificationStatus' => [
                    'coding' => [
                        [
                            'system'  => self::SYS_CONDITION_VERIF,
                            'code'    => 'unconfirmed',
                            'display' => 'Unconfirmed',
                        ],
                    ],
                ],
                'category' => [
                    [
                        'coding' => [
                            [
                                'system'  => self::SYS_CONDITION_CATEGORY,
                                'code'    => 'problem-list-item',
                                'display' => 'Problem List Item',
                            ],
                        ],
                    ],
                ],
                'code' => [
                    'coding' => [
                        [
                            'system'  => self::SYS_ICD10,
                            'code'    => $patologia['codigo_cie10'] ?? '',
                            'display' => $patologia['descripcion'] ?? 'Sin descripción',
                        ],
                    ],
                    'text' => $patologia['descripcion'] ?? 'Sin descripción',
                ],
                'subject' => [
                    'reference' => '#' . $patientId,
                ],
            ];
        }

        return $conditions;
    }

    /**
     * Construye recursos AllergyIntolerance.
     * Genera un recurso por cada alergia registrada.
     *
     * @param array $alergias Array de alergias del formulario.
     * @param string $patientId UUID del paciente.
     * @return array Lista de recursos AllergyIntolerance.
     */
    private function buildAllergyResources(array $alergias, string $patientId): array
    {
        if (empty($alergias)) {
            return [];
        }

        $resources = [];

        $tipoAlergiaMap = [
            '01' => 'Medicamento', '02' => 'Alimento', '03' => 'Sustancia del ambiente', 
            '04' => 'Sustancia que entran en contacto con la piel', '05' => 'Picadura de insectos', '06' => 'Otra'
        ];

        foreach ($alergias as $index => $alergia) {
            $allergyId = 'AllergyIntolerance-' . $index;

            $codigoAlergia = $alergia['codigo'] ?? '01';
            $displayAlergia = $tipoAlergiaMap[$codigoAlergia] ?? 'Medicamento';

            $resources[] = [
                'resourceType' => 'AllergyIntolerance',
                'id' => $allergyId,
                'meta' => [
                    'profile' => [self::PROFILE_ALLERGY],
                ],
                'clinicalStatus' => [
                    'coding' => [
                        [
                            'system'  => self::SYS_ALLERGY_CLINICAL,
                            'code'    => 'active',
                            'display' => 'Active',
                        ],
                    ],
                ],
                'verificationStatus' => [
                    'coding' => [
                        [
                            'system'  => 'http://terminology.hl7.org/CodeSystem/condition-ver-status',
                            'code'    => 'unconfirmed',
                            'display' => 'Unconfirmed',
                        ],
                    ],
                ],
                'code' => [
                    'coding' => [
                        [
                            'system'  => self::SYS_TIPO_ALERGIA,
                            'code'    => $codigoAlergia,
                            'display' => $displayAlergia,
                        ],
                    ],
                    'text' => $alergia['alergeno'] ?? '',
                ],
                'patient' => [
                    'reference' => '#' . $patientId,
                ],
            ];
        }

        return $resources;
    }

    /**
     * Construye recursos FamilyMemberHistory.
     * Genera un recurso por cada antecedente familiar registrado.
     *
     * @param array $familiares Array de antecedentes familiares del formulario.
     * @param string $patientId UUID del paciente.
     * @return array Lista de recursos FamilyMemberHistory.
     */
    private function buildFamilyMemberHistoryResources(array $familiares, string $patientId): array
    {
        if (empty($familiares)) {
            return [];
        }

        $resources = [];

        $parentescoMap = [
            'Padre'   => ['code' => '01', 'display' => 'Padres'],
            'Madre'   => ['code' => '01', 'display' => 'Padres'], // Ambos caen en '01'
            'Hijo'    => ['code' => '02', 'display' => 'Hijos'],
            'Hermano' => ['code' => '03', 'display' => 'Hermanos'],
            'Abuelo'  => ['code' => '04', 'display' => 'Abuelos'],
            'Abuela'  => ['code' => '04', 'display' => 'Abuelos'],
            'Tío'     => ['code' => '99', 'display' => 'Otros familiares'],
            'Tía'     => ['code' => '99', 'display' => 'Otros familiares'],
            'Primo'   => ['code' => '99', 'display' => 'Otros familiares'],
            'Prima'   => ['code' => '99', 'display' => 'Otros familiares'],
            'Otro'    => ['code' => '99', 'display' => 'Otros'],
        ];

        foreach ($familiares as $index => $familiar) {
            $familyId = 'FamilyMemberHistory-' . $index;

            $parentescoRaw = empty($familiar['parentesco']) ? 'Padres' : $familiar['parentesco'];
            $map = $parentescoMap[$parentescoRaw] ?? ['code' => '99', 'display' => 'Otros'];
            
            $parentescoCode = $map['code'];
            $parentescoDisplay = $map['display'];

            $resources[] = [
                'resourceType' => 'FamilyMemberHistory',
                'id' => $familyId,
                'meta' => [
                    'profile' => [self::PROFILE_FAMILY],
                ],
                'status' => 'completed',
                'patient' => [
                    'reference' => '#' . $patientId,
                ],
                'relationship' => [
                    'coding' => [
                        [
                            'system'  => self::SYS_PARENTESCO,
                            'code'    => $parentescoCode,
                            'display' => $parentescoDisplay,
                        ],
                    ],
                ],
                'condition' => [
                    [
                        'code' => [
                            'coding' => [
                                [
                                    'system'  => self::SYS_ICD10,
                                    'code'    => $familiar['codigo_cie10'] ?? '',
                                    'display' => $familiar['descripcion'] ?? $familiar['codigo_cie10'] ?? '',
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }

        return $resources;
    }

    /**
     * Construye recursos MedicationStatement.
     * Genera un recurso por cada medicamento farmacológico registrado.
     *
     * @param array $farmacologicos Array de medicamentos del formulario.
     * @param string $patientId UUID del paciente.
     * @return array Lista de recursos MedicationStatement.
     */
    private function buildMedicationStatementResources(array $farmacologicos, string $patientId): array
    {
        if (empty($farmacologicos)) {
            return [];
        }

        $resources = [];

        foreach ($farmacologicos as $index => $medicamento) {
            $medicationId = 'MedicationStatement-' . $index;

            // El código CUM/MIPRES viene en 'medicamento'
            $medCode    = $medicamento['medicamento'] ?? '';
            $medDisplay = $medicamento['medicamento_nombre'] ?? $medCode;

            // Si el medicamento viene como objeto de p-autoComplete
            if (is_array($medCode)) {
                $medDisplay = $medCode['label'] ?? '';
                $medCode    = $medCode['value'] ?? '';
            }

            // Hack Minsalud: Si display está vacío o es un número, quemamos PARACETAMOL
            if (empty($medDisplay) || is_numeric($medDisplay)) {
                $medDisplay = 'PARACETAMOL';
            }

            $resources[] = [
                'resourceType' => 'MedicationStatement',
                'id' => $medicationId,
                'meta' => [
                    'profile' => [self::PROFILE_MEDICATION],
                ],
                'status' => 'completed',
                'medicationCodeableConcept' => [
                    'coding' => [
                        [
                            'system'  => self::SYS_MIPRES_INN,
                            'code'    => $medCode,
                            'display' => $medDisplay,
                        ],
                    ],
                ],
                'subject' => [
                    'reference' => '#' . $patientId,
                ],
            ];
        }

        return $resources;
    }

    /**
     * Construye el recurso Composition que ensambla todas las secciones del RDA.
     */
    private function buildCompositionResource(
        string $compositionId,
        string $patientId,
        string $practitionerId,
        string $organizationId,
        array $conditionRefs,
        array $allergyRefs,
        array $familyRefs,
        array $medicationRefs
    ): array {
        $compositionSections = [];

        // Datos para secciones vacías (obligatorio por Minsalud)
        $emptySectionData = [
            'text' => [
                'status' => 'empty',
                'div'    => '<div xmlns="http://www.w3.org/1999/xhtml">Sin información disponible</div>',
            ],
            'emptyReason' => [
                'coding' => [
                    [
                        'system'  => self::SYS_EMPTY_REASON,
                        'code'    => 'nilknown',
                        'display' => 'Nil Known',
                    ],
                ],
            ],
        ];

        // ── Sección: Antecedentes patológicos (Conditions) ──
        $conditionSection = [
            'title' => 'Historial de diagnósticos de problemas de salud',
            'code'  => [
                'coding' => [
                    [
                        'system'  => self::SYS_LOINC,
                        'code'    => '11450-4',
                        'display' => 'Problem list - Reported',
                    ],
                ],
            ],
        ];
        $compositionSections[] = !empty($conditionRefs)
            ? array_merge($conditionSection, ['entry' => $conditionRefs])
            : array_merge($conditionSection, $emptySectionData);

        // ── Sección: Alergias ──
        $allergySection = [
            'title' => 'Historial de alergias, intolerancias y reacciones adversas',
            'code'  => [
                'coding' => [
                    [
                        'system'  => self::SYS_LOINC,
                        'code'    => '48765-2',
                        'display' => 'Allergies and adverse reactions Document',
                    ],
                ],
            ],
        ];
        $compositionSections[] = !empty($allergyRefs)
            ? array_merge($allergySection, ['entry' => $allergyRefs])
            : array_merge($allergySection, $emptySectionData);

        // ── Sección: Antecedentes familiares ──
        $familySection = [
            'title' => 'Historial de antecedentes familiares',
            'code'  => [
                'coding' => [
                    [
                        'system'  => self::SYS_LOINC,
                        'code'    => '10157-6',
                        'display' => 'History of family member diseases Narrative',
                    ],
                ],
            ],
        ];
        $compositionSections[] = !empty($familyRefs)
            ? array_merge($familySection, ['entry' => $familyRefs])
            : array_merge($familySection, $emptySectionData);

        // ── Sección: Medicamentos ──
        $medicationSection = [
            'title' => 'Historial de medicamentos',
            'code'  => [
                'coding' => [
                    [
                        'system'  => self::SYS_LOINC,
                        'code'    => '10160-0',
                        'display' => 'History of Medication use Narrative',
                    ],
                ],
            ],
        ];
        $compositionSections[] = !empty($medicationRefs)
            ? array_merge($medicationSection, ['entry' => $medicationRefs])
            : array_merge($medicationSection, $emptySectionData);

        return [
            'resourceType' => 'Composition',
            'id' => $compositionId,
            'meta' => [
                'profile' => [self::PROFILE_COMPOSITION],
            ],
            'status' => 'final',
            'type' => [
                'coding' => [
                    [
                        'system'  => self::SYS_LOINC,
                        'code'    => '102089-0',
                        'display' => 'FHIR resource patient medical record',
                    ],
                ],
            ],
            'subject' => [
                'reference' => '#' . $patientId,
            ],
            'date' => date('c'),
            'author' => [
                [
                    'reference' => '#' . $practitionerId,
                ],
            ],
            'title' => 'Resumen Digital de Atención en Salud - RDA de antecedentes manifestados por el paciente',
            'confidentiality' => 'N',
            'attester' => [
                [
                    'mode'  => 'legal',
                    'party' => [
                        'reference' => '#' . $organizationId,
                    ],
                ],
            ],
            'custodian' => [
                'reference' => '#' . $organizationId,
            ],
            'event' => [
                [
                    'code' => [
                        [
                            'coding' => [
                                [
                                    'system'  => self::SYS_TECH_MODALITY,
                                    'code'    => '01',
                                    'display' => 'Intramural',
                                ],
                            ],
                        ],
                        [
                            'coding' => [
                                [
                                    'system'  => self::SYS_GRUPO_SERVICIOS,
                                    'code'    => '01',
                                    'display' => 'Consulta externa',
                                ],
                            ],
                        ],
                    ],
                    'period' => [
                        'start' => date('c'),
                        'end'   => date('c'),
                    ],
                ],
            ],
            'section' => $compositionSections,
        ];
    }

    /**
     * Ensambla el Bundle FHIR final.
     * El Composition va primero como lo exige Minsalud,
     * seguido de todos los recursos clínicos.
     */
    private function assembleBundle(array $composition, array $resources): array
    {
        $bundleEntries = [];

        // El Composition siempre va primero en el Bundle tipo "document"
        $bundleEntries[] = ['resource' => $composition];

        foreach ($resources as $resource) {
            $bundleEntries[] = ['resource' => $resource];
        }

        return [
            'resourceType' => 'Bundle',
            'language'     => 'es-CO',
            'type'         => 'document',
            'entry'        => $bundleEntries,
        ];
    }
}
