<?php

namespace App\Services\Hl7;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Servicio para obtener datos de RDA Consulta (HL7)
 *
 * @TODO: Implementar la lógica específica para consultas
 */
class RdaConsultaService extends RdaService
{
    /**
     * Obtiene los datos necesarios para generar un mensaje HL7 RDA de tipo Consulta.
     *
     * @param int $ingresoId
     * @return array
     * @throws \Exception
     */
    public function getDataForRda(int $ingresoId): array
    {
        Log::info("Iniciando obtención de datos RDA Consulta para ingreso: {$ingresoId}");

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

        // Generar UUID
        $uuid = (string) Str::uuid();

        // Esta fecha obtenida de la tabla ingresos_salidas se debe homologar
        $claseAtencionDate = $this->determinarClaseAtencionDate($ingresoId);

        // Hora fecha actual del servidor
        // $Encounter_period_start = date('Y-m-d\TH:i:sO');
        $Encounter_period_start = date('Y-m-d\TH:i:sO', strtotime($claseAtencionDate['fecha_inicio']));
        // // Se requiere a esta fecha sumarle 30 minutos
        // // $Encounter_period_end = date('Y-m-d\TH:i:sO', strtotime('+30 minutes'));
        $Encounter_period_end = date('Y-m-d\TH:i:sO', strtotime($claseAtencionDate['fecha_fin']));

        // print_r(json_encode($claseAtencionDate));
        // echo "\n" . "<b><br> claseAtencionDate[]<pre>" . "\n";
        // print_r($claseAtencionDate);
        // echo "\n" . "</pre></b>" . "\n";
        // die("\n" . "<br><b>Archivo Modificado:<br>" . __FILE__ . "</b><br>Error Desarrollo " . date("d-m-Y H:i:s") . "\n");


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
        $disabilityClassificationData = $this->getColombianDisabilityClassificationData('08');
        /* obtener datos tipo identificador HL7 v2-0203
        * @param string $catalogName = 'v2.0203'
        * @param int $id = 146 (Person number)
        */
        $hl7IdentifierTypeData = $this->getHl7CatalogItemByName('v2.0203', 146);
        /* obtener datos ColombianPersonIdentifier
        * @param string $code = tipo_documento del paciente
        */
        $colombianIdentifierData = $this->getColombianPersonIdentifierData($persona->tipo_documento);
        $tipoDocumento_numeroDocumento = $colombianIdentifierData['code'] . "-" . $persona->documento;
        /* obtener datos address ExtensionDivipolaMunicipality
        * Concatena tipo_dpto_id + tipo_mpio_id para formar código DIVIPOLA (ej: '76' + '001' = '76001')
        */
        $divipolaCode = $persona->departamento . $persona->municipio;
        $divipolaMunicipality = $this->getDivipolaDataByCode($divipolaCode);
        /* obtener datos address ExtensionCountryCode
        * @param string $codeType = 'numeric'
        * @param string $display = 'Colombia'
        */
        $addressCountryCode = $this->getIso31661Data('numeric', 'Colombia');
        /* obtener datos address ExtensionResidenceZone
        * Mapea zona_residencia: 'U' → '01' (Urbana), 'R' → '02' (Rural)
        */
        $zonaResidenciaCode = $persona->zona === 'U' ? '01' : '02';
        $addressResidenceZone = $this->getColombianResidenceZoneData($zonaResidenciaCode);
        /* obtener paciente esta activo o inactivo
        * @param boolean $active = true (Activo), false (Inactivo)
        */
        $active = true;
        /* obtener paciente genero male | female | other | unknown
        * @param string $code = 'male' (Masculino), 'female' (Femenino)
        */
        $gender = $persona->sexo === 'F' ? 'female' : 'male';
        /* obtener datos address ExtensionBiologicalGender
        * Mapea sexo_id: 'M' → '1' (Hombre), 'F' → '2' (Mujer)
        */
        $colombianGenderGroupId = $persona->sexo === 'F' ? '2' : '1';
        $colombianGenderGroup = $this->getColombianGenderGroup($colombianGenderGroupId);

        /**
         * Fin Patient
         * */


        /**
         * Inicio Practitioner
         * */

        // Obtener datos del Practitioner
        $practitionerData = $this->getPractitionerData($ingresoId);
        $practitionerData['tercero_id'] = $this->sanitizeForId($practitionerData['tercero_id']);

        /** identifier NationalPersonIdentifier-0
         * usual | official | temp | secondary | old (If known)
         * Binding: IdentifierUse (required): Identifies the purpose for this identifier, if known .
         * Fixed Value: official
         */
        $usePractitionerIdentifier = 'official';
        /** Obtener datos ColombianPersonIdentifier
         * @param string $code = 'CC' (Cédula ciudadanía)
         */
        $colombianPractitionerIdentifierData = $this->getColombianPersonIdentifierData($practitionerData['tipo_tercero_id']);

        /**
         * Fin Practitioner
         * */

        /**
         * Inicio Organization
         * */

        // Obtener datos Organization
        $organizationData = $this->getOrganizationData();

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

        /**
         * Inicio event
         * */

        /* obtener datos ColombianTechModality
        * @param int $id = 1 Intramural
        */
        $techModalityData = $this->getHl7CatalogItemData(1);
        /* obtener datos GrupoServicios
        * @param int $id = 10 Consulta externa
        */
        $grupoServiciosData = $this->getHl7CatalogItemData(10);

        /**
         * Fin event
         * */

        /**
         * Inicio Encounter
         * */

        // Obtener datos de ocupación (Cuentas y CUPS asociados al ingreso)
        $cargosCupsEncounter = $this->getDiagnosticosCuentas($ingresoId);


        // echo "\n" . "<b><br> cargosCupsEncounter[*-*]<pre>" . "\n";
        // print_r($cargosCupsEncounter);
        // echo "\n" . "</pre></b>" . "\n";
        // die("\n" . "<br><b>Archivo Modificado:<br>" . __FILE__ . "</b><br>Error Desarrollo " . date("d-m-Y H:i:s") . "\n");


        /**
         * Fin Encounter
         * */

        /**
         * Inicio Observation-0 Ocupacion
         * */
        /* obtener datos CIUO88AC*/
        $ocupacionCiuo88acData = $this->getCiuo88ac('1130');
        /**
         * Fin Observation-0 Ocupacion
         * */

        /**
         * Inicio Epicrisis SIIS
         * */

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

        /**
         * Fin Epicrisis SIIS
         * */


        $fhirBundle = [
            "resourceType" => "Bundle", //ok
            "language" => "es-CO", //ok
            "type" => "document", //ok
            // Esto es para las versiones del docuemnto tambien se debe cambiar la fecha del Encounter
            "identifier" => [
                "system" => "https://fhir.minsalud.gov.co/rda/NamingSystem/identifier-RDA", //ok
                // Se requiere para este campo generar UUID
                "value" => $ingresoId . "-" . $uuid //ok
            ],
            "timestamp" => date('Y-m-d\TH:i:sO'), //ok
            "entry" => [
                [
                    "resource" => [
                        "resourceType" => "Composition", //ok
                        "id" => "Composition-0", //ok
                        "meta" => [
                            "profile" => [
                                "https://fhir.minsalud.gov.co/rda/StructureDefinition/CompositionAmbulatoryRDA" //ok
                            ]
                        ],
                        "status" => "final", //ok
                        "type" => [
                            "coding" => [
                                [
                                    "system" => "http://loinc.org", //ok
                                    "code" => "51845-6", //ok
                                    "display" => "Outpatient Consult note" //ok
                                ]
                            ]
                        ],
                        "subject" => [
                            "reference" => "#" . $tipoDocumento_numeroDocumento . "" //ok
                        ],
                        "encounter" => [
                            "reference" => "#Encounter-0" //ok
                        ],
                        "date" => date('Y-m-d\TH:i:sO'), //ok
                        "author" => [
                            ["reference" => "#" . $practitionerData['tipo_tercero_id'] . "-" . $practitionerData['tercero_id']] //ok
                        ],
                        "title" => "RDA Consulta", //ok
                        "confidentiality" => "N", //ok
                        "attester" => [
                            [
                                "mode" => "legal", //ok
                                "party" => ["reference" => "#" . $practitionerData['tipo_tercero_id'] . "-" . $practitionerData['tercero_id']] //ok
                            ]
                        ],
                        "custodian" => ["reference" => "#" . $organizationData['codigo_sgsss_ips']], //ok
                        "event" => [
                            [
                                "code" => [
                                    [
                                        "coding" => [
                                            [
                                                "system" => $techModalityData['system'], //ok
                                                "code" => $techModalityData['code'], //ok
                                                "display" => $techModalityData['display'] //ok
                                            ]
                                        ]
                                    ],
                                    [
                                        "coding" => [
                                            [
                                                "system" => $grupoServiciosData['system'], //ok
                                                "code" => $grupoServiciosData['code'], //ok
                                                "display" => $grupoServiciosData['display'] //ok
                                            ]
                                        ]
                                    ]
                                ],
                                "period" => [
                                    "start" => $Encounter_period_start,
                                    "end" => $Encounter_period_end
                                ]
                            ]
                        ],
                        "section" => [
                            [
                                "title" => "Entidad(es) responsable(s) por el plan de beneficios en salud (consulta)", //ok
                                "code" => [
                                    "coding" => [
                                        [
                                            "system" => "http://loinc.org", //ok
                                            "code" => "48768-6", //ok
                                            "display" => "Payment sources Document" //ok
                                        ]
                                    ]
                                ],
                                "entry" => [
                                    ["reference" => "#" . $tipoDocumento_numeroDocumento] //ok
                                ]
                            ],
                            [
                                "title" => "Otros datos demográficos", //ok
                                "code" => [
                                    "coding" => [
                                        [
                                            "system" => "http://loinc.org", //ok
                                            "code" => "74208-0", //ok
                                            "display" => "Demographic information + History of occupation Document" //ok
                                        ]
                                    ]
                                ],
                                "entry" => [
                                    ["reference" => "#Observation-0"] //ok
                                ]
                            ],
                            [
                                "title" => "Datos incapacidad (SIPE – Sistema de Incapacidades y Prestaciones Economicas)", //ok
                                "code" => [
                                    "coding" => [
                                        [
                                            "system" => "http://loinc.org", //ok
                                            "code" => "105583-9", //ok
                                            "display" => "Worker Sick leave form" //ok
                                        ]
                                    ]
                                ],
                                "entry" => [
                                    ["reference" => "#Observation-1"] //ok
                                ]
                            ],
                            [
                                "title" => "Historial de diagnósticos de problemas de salud", //ok
                                "code" => [
                                    "coding" => [
                                        [
                                            "system" => "http://loinc.org", //ok
                                            "code" => "11450-4", //ok
                                            "display" => "Problem list - Reported" //ok
                                        ]
                                    ]
                                ],
                                "entry" => [
                                    ["reference" => "#Condition-0"] //ok
                                ]
                            ],
                            [
                                "title" => "Documentos de soporte", //ok
                                "code" => [
                                    "coding" => [
                                        [
                                            "system" => "http://loinc.org", //ok
                                            "code" => "55107-7", //ok
                                            "display" => "Addendum Document" //ok
                                        ]
                                    ]
                                ],
                                "entry" => [
                                    ["reference" => "#DocumentReference-0"] //ok
                                ]
                            ],
                            [
                                "title" => "Historial de medicamentos", //ok
                                "code" => [
                                    "coding" => [
                                        [
                                            "system" => "http://loinc.org", //ok
                                            "code" => "10160-0", //ok
                                            "display" => "History of Medication use Narrative" //ok
                                        ]
                                    ]
                                ],
                                "entry" => [
                                    ["reference" => "#MedicationRequest-0"] //ok
                                ]
                            ],
                            [
                                "title" => "Historial de alergias, intolerancias y reacciones adversas", //ok
                                "code" => [
                                    "coding" => [
                                        [
                                            "system" => "http://loinc.org", //ok
                                            "code" => "48765-2", //ok
                                            "display" => "Allergies and adverse reactions Document" //ok
                                        ]
                                    ]
                                ],
                                "entry" => [
                                    ["reference" => "#AllergyIntolerance-0"] //ok
                                ]
                            ],
                            [
                                "title" => "Factores de riesgo", //ok
                                "code" => [
                                    "coding" => [
                                        [
                                            "system" => "http://loinc.org", //ok
                                            "code" => "75492-9", //ok
                                            "display" => "Risk assessment and screening note" //ok
                                        ]
                                    ]
                                ],
                                "entry" => [
                                    ["reference" => "#RiskAssessment-0"] //ok
                                ]
                            ],
                            [
                                "title" => "Órdenes, prescripciones o solicitudes de servicio",
                                "code" => [
                                    "coding" => [
                                        [
                                            "system" => "http://loinc.org",
                                            "code" => "61146-1",
                                            "display" => "Orders for services Document"
                                        ]
                                    ]
                                ],
                                "entry" => [
                                    ["reference" => "#ServiceRequest-0"]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "resource" => [
                        "resourceType" => "Patient",
                        "id" => $tipoDocumento_numeroDocumento,
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
                            ]
                        ],
                        "identifier" => [
                            [
                                "id" => "NationalPersonIdentifier-0",
                                "use" => "official",
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
                                "given" => [$persona->primer_nombre, $persona->segundo_nombre]
                            ]
                        ],
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
                        "birthDate" => $persona->fecha_nacimiento ? date('Y-m-d', strtotime($persona->fecha_nacimiento)) : null,
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
                                            "code" => $addressResidenceZone['code'],
                                            "display" => $addressResidenceZone['display']
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        "active" => $active
                    ]
                ],
                [
                    "resource" => [
                        "resourceType" => "Practitioner",
                        "id" => ""  . $practitionerData['tipo_tercero_id'] . "-" . $practitionerData['tercero_id'] . "",
                        "meta" => [
                            "profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/PractitionerRDA"]
                        ],
                        "identifier" => [
                            [
                                "id" => "NationalPersonIdentifier-0",
                                "use" => $usePractitionerIdentifier,
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
                                "family" => $practitionerData['primer_apellido'] . ' ' . $practitionerData['segundo_apellido'],
                                "given" => [$practitionerData['primer_nombre']]
                            ]
                        ]
                    ]
                ],
                [
                    "resource" => [
                        "resourceType" => "Organization",
                        "id" => $organizationData['codigo_sgsss_ips'],
                        "meta" => [
                            "profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/CareDeliveryOrganizationRDA"]
                        ],
                        "identifier" => [
                            [
                                "system" => "http://co.fhir.guide/NamingSystem/REPS",
                                "value" => $organizationData['codigo_sgsss_ips']
                            ]
                        ],
                        "name" => $organizationData['razon_social']
                    ]
                ],
                [
                    "resource" => [
                        "resourceType" => "Encounter",
                        "id" => "Encounter-0",
                        "meta" => [
                            "profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/EncounterAmbulatoryRDA"]
                        ],
                        "status" => "finished",
                        "class" => [
                            "system" => "http://terminology.hl7.org/CodeSystem/v3-ActCode",
                            "code" => "AMB",
                            "display" => "ambulatory"
                        ],
                        "type" => [
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
                            ],
                            [
                                "coding" => [
                                    [
                                        "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/EntornoAtencion",
                                        "code" => "05",
                                        "display" => "Institucional"
                                    ]
                                ]
                            ]
                        ],
                        "serviceType" => [
                            "coding" => [
                                [
                                    "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/CUPS",
                                    // "code" => "890201",
                                    // "display" => "CONSULTA DE PRIMERA VEZ POR MEDICINA GENERAL",
                                    "code" => $cargosCupsEncounter['code'],
                                    "display" => $cargosCupsEncounter['display'],
                                ]
                            ]
                        ],
                        "subject" => ["reference" => "#" . $tipoDocumento_numeroDocumento],
                        "participant" => [
                            [
                                "id" => "AttenderPhysician",
                                "type" => [
                                    [
                                        "coding" => [
                                            [
                                                "system" => "http://terminology.hl7.org/CodeSystem/v3-ParticipationType",
                                                "code" => "ATND",
                                                "display" => "attender"
                                            ]
                                        ]
                                    ]
                                ],
                                "individual" => ["reference" => "#" . $practitionerData['tipo_tercero_id'] . "-" . $practitionerData['tercero_id']]
                            ]
                        ],
                        "period" => [
                            "start" => $Encounter_period_start,
                            "end" => $Encounter_period_end
                        ],
                        "reasonCode" => [
                            [
                                "coding" => [
                                    [
                                        "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/RIPSCausaExternaVersion2",
                                        "code" => "38",
                                        "display" => "ENFERMEDAD GENERAL"
                                    ]
                                ]
                            ]
                        ],
                        "diagnosis" => [
                            [
                                "id" => "MainDiagnosis",
                                "extension" => [
                                    [
                                        "url" => "https://fhir.minsalud.gov.co/rda/StructureDefinition/ExtensionDiagnosisType",
                                        "valueCoding" => [
                                            "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/RIPSTipoDiagnosticoPrincipalVersion2",
                                            "code" => "01",
                                            "display" => "Impresión Diagnóstica"
                                        ]
                                    ]
                                ],
                                "condition" => ["reference" => "#Condition-0"],
                                "use" => [
                                    "coding" => [
                                        [
                                            "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianDiagnosisRole",
                                            "code" => "8319008",
                                            "display" => "diagnóstico primario"
                                        ]
                                    ]
                                ],
                                "rank" => 1
                            ]
                        ]
                    ]
                ],
                [
                    "resource" => [
                        "resourceType" => "Observation",
                        "id" => "Observation-0",
                        "meta" => [
                            "profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/PatientOccupationAtEncounterRDA"]
                        ],
                        "status" => "final",
                        "code" => [
                            "coding" => [
                                [
                                    "system" => "http://snomed.info/sct",
                                    "code" => "184104002",
                                    "display" => "ocupación del paciente"
                                ]
                            ],
                            "text" => "Ocupación del paciente en el momento de la atención"
                        ],
                        "subject" => ["reference" => "#" . $tipoDocumento_numeroDocumento],
                        "valueCodeableConcept" => [
                            "coding" => [
                                [
                                    "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/CIUO88AC",
                                    "code" => $ocupacionCiuo88acData['code'],
                                    "display" => $ocupacionCiuo88acData['display']
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "resource" => [
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
                        "subject" => ["reference" => "#" . $tipoDocumento_numeroDocumento],
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
                                            "code" => "01",
                                            "display" => "Nueva"
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
                                    "value" => 1,
                                    "unit" => "días",
                                    "system" => "http://unitsofmeasure.org",
                                    "code" => "d"
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "resource" => [
                        "resourceType" => "Condition",
                        "id" => "Condition-0",
                        "meta" => [
                            "profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/ConditionRDA"]
                        ],
                        "clinicalStatus" => [
                            "coding" => [
                                [
                                    "system" => "http://terminology.hl7.org/CodeSystem/condition-clinical",
                                    "code" => "active",
                                    "display" => "Active"
                                ]
                            ]
                        ],
                        "category" => [
                            [
                                "coding" => [
                                    [
                                        "system" => "http://terminology.hl7.org/CodeSystem/condition-category",
                                        "code" => "encounter-diagnosis",
                                        "display" => "Encounter Diagnosis"
                                    ]
                                ]
                            ]
                        ],
                        "code" => [
                            "coding" => [
                                [
                                    "system" => "http://hl7.org/fhir/sid/icd-10",
                                    "code" => "K409",
                                    "display" => "HERNIA INGUINAL UNILATERAL O NO ESPECIFICADA, SIN OBSTRUCCION NI GANGRENA"
                                ]
                            ]
                        ],
                        "subject" => ["reference" => "#" . $tipoDocumento_numeroDocumento]
                    ]
                ],
                [
                    "resource" => [
                        "resourceType" => "MedicationRequest",
                        "id" => "MedicationRequest-0",
                        "meta" => [
                            "profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/MedicationRequestRDA"]
                        ],
                        "status" => "active",
                        "intent" => "order",
                        "category" => [
                            [
                                "coding" => [
                                    [
                                        "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianHealthTechnologyCategory",
                                        "code" => "02",
                                        "display" => "Medicamento con registro sanitario"
                                    ]
                                ]
                            ]
                        ],
                        "reportedBoolean" => true,
                        "medicationCodeableConcept" => [
                            "coding" => [
                                [
                                    "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/MipresINN",
                                    "code" => "626",
                                    "display" => "PARACETAMOL"
                                ]
                            ]
                        ],
                        "subject" => ["reference" => "#" . $tipoDocumento_numeroDocumento],
                        "authoredOn" => "2011-01-01T08:00:00-05:00",
                        "reasonCode" => [
                            [
                                "coding" => [
                                    [
                                        "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/RIPSFinalidadConsultaVersion2",
                                        "code" => "44",
                                        "display" => "OTRA"
                                    ]
                                ]
                            ]
                        ],
                        "dosageInstruction" => [
                            [
                                "timing" => [
                                    "code" => [
                                        "coding" => [
                                            [
                                                "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/MedicationTime",
                                                "code" => "2",
                                                "display" => "Horas"
                                            ]
                                        ]
                                    ],
                                    "repeat" => [
                                        "duration" => 5,
                                        "durationUnit" => "d"
                                    ]
                                ],
                                "route" => [
                                    "coding" => [
                                        [
                                            "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/VAD",
                                            "code" => "002",
                                            "display" => "BUCAL"
                                        ]
                                    ]
                                ],
                                "doseAndRate" => [
                                    [
                                        "doseQuantity" => [
                                            "value" => 1,
                                            "unit" => "Tableta",
                                            "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/UMM",
                                            "code" => "106"
                                        ],
                                        "rateQuantity" => [
                                            "value" => 8,
                                            "unit" => "Horas",
                                            "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/MedicationTime",
                                            "code" => "2"
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "resource" => [
                        "resourceType" => "AllergyIntolerance",
                        "id" => "AllergyIntolerance-0",
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
                                    "code" => "01",
                                    "display" => "Medicamento"
                                ]
                            ],
                            "text" => "Alergia a la penicilina reportada por paciente"
                        ],
                        "patient" => ["reference" => "#" . $tipoDocumento_numeroDocumento]
                    ]
                ],
                [
                    "resource" => [
                        "resourceType" => "RiskAssessment",
                        "id" => "RiskAssessment-0",
                        "meta" => [
                            "profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/RiskFactorRDA"]
                        ],
                        "status" => "registered",
                        "code" => [
                            "coding" => [
                                [
                                    "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/FactorRiesgo",
                                    "code" => "01",
                                    "display" => "Químicos"
                                ]
                            ],
                            "text" => "Químicos"
                        ],
                        "subject" => ["reference" => "#" . $tipoDocumento_numeroDocumento],
                        "encounter" => ["reference" => "#Encounter-0"]
                    ]
                ],
                [
                    "resource" => [
                        "resourceType" => "ServiceRequest",
                        "id" => "ServiceRequest-0",
                        "meta" => [
                            "profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/OtherTechnologyServiceRequestRDA"]
                        ],
                        "status" => "active",
                        "intent" => "order",
                        "category" => [
                            [
                                "coding" => [
                                    [
                                        "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianHealthTechnologyCategory",
                                        "code" => "13",
                                        "display" => "Servicio complementario"
                                    ]
                                ]
                            ]
                        ],
                        "code" => [
                            "coding" => [
                                [
                                    "system" => "http://snomed.info/sct",
                                    "code" => "103693007",
                                    "display" => "Diagnostic procedure"
                                ]
                            ],
                            "text" => "Hemograma I"
                        ],
                        "subject" => ["reference" => "#" . $tipoDocumento_numeroDocumento],
                        "encounter" => ["reference" => "#Encounter-0"],
                        "authoredOn" => "2011-01-01T08:00:00-05:00",
                        "reasonCode" => [
                            [
                                "coding" => [
                                    [
                                        "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/RIPSFinalidadConsultaVersion2",
                                        "code" => "44",
                                        "display" => "OTRA"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "resource" => [
                        "resourceType" => "DocumentReference",
                        "id" => "DocumentReference-0",
                        "meta" => [
                            "profile" => ["https://fhir.minsalud.gov.co/rda/StructureDefinition/DocumentReferenceEPIRDA"]
                        ],
                        "status" => "current",
                        "type" => [
                            "coding" => [
                                [
                                    "system" => "http://loinc.org",
                                    "code" => "18842-5",
                                    "display" => "Discharge summary"
                                ],
                                [
                                    "system" => "https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianDocumentTypes",
                                    "code" => "EPI",
                                    "display" => "Epicrisis"
                                ]
                            ]
                        ],
                        "category" => [
                            [
                                "coding" => [
                                    [
                                        "system" => "http://loinc.org",
                                        "code" => "55108-5",
                                        "display" => "Clinical presentation Document"
                                    ]
                                ]
                            ]
                        ],
                        "subject" => ["reference" => "#" . $tipoDocumento_numeroDocumento],
                        "date" => "2011-01-01T08:00:00-05:00",
                        "author" => [
                            ["reference" => "#" . $organizationData['codigo_sgsss_ips']]
                        ],
                        "custodian" => ["reference" => "Organization/MinSalud"],
                        "description" => "Epicrisis del encuentro de atención en salud - RDA",
                        "securityLabel" => [
                            [
                                "coding" => [
                                    [
                                        "system" => "http://terminology.hl7.org/CodeSystem/v3-Confidentiality",
                                        "code" => "R",
                                        "display" => "restricted"
                                    ]
                                ]
                            ]
                        ],
                        "content" => [
                            [
                                "attachment" => [
                                    "language" => "es-CO",
                                    "data" => $epicrisisPdfBase64
                                ],
                                "format" => [
                                    "system" => "urn:ietf:bcp:13",
                                    "code" => "application/pdf",
                                    "display" => "PDF"
                                ]
                            ]
                        ],
                        "context" => [
                            "encounter" => [
                                ["reference" => "#Encounter-0"]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // echo json_encode($fhirBundle, JSON_PRETTY_PRINT);
        // die();


        // echo "\n" . "<b><br> fhirBundle[]<pre>" . "\n";
        // print_r(json_encode($fhirBundle));
        // echo "\n" . "</pre></b>" . "\n";
        // die("\n" . "<br><b>Archivo Modificado:<br>" . __FILE__ . "</b><br>Error Desarrollo " . date("d-m-Y H:i:s") . "\n");

        // echo json_encode($fhirBundle);
        // die();


        Log::info("Datos RDA Consulta obtenidos exitosamente para ingreso: {$ingresoId}");

        return $fhirBundle;
    }

    /**
     * Valida que los datos sean suficientes para generar un mensaje HL7 RDA Consulta.
     *
     * @param array $rdaDataConsulta
     * @return bool
     */
    public function validateRdaData(array $rdaDataConsulta): bool
    {
        // Validar campos obligatorios para HL7
        if (!isset($rdaDataConsulta['resourceType']) || $rdaDataConsulta['resourceType'] !== 'Bundle') {
            return false;
        }
        return true;
    }

    /**
     * Envía el RDA Paciente (Payload FHIR) al Ministerio utilizando el token OAuth
     * y las llaves de suscripción. Funciona como un orquestador seguro implementando persistencia.
     *
     * @param array $rdaDataConsulta
     * @param string $tokenIhce
     * @param int|null $ingresoId
     * @return array
     */
    public function sendRdaConsulta(array $rdaDataConsulta, string $tokenIhce, ?int $ingresoId = null): array
    {
        $config = config('services.ihce');
        $baseUrl = rtrim($config['base_url'], '/');
        // El endpoint exacto parametrizado
        $endpoint = "{$baseUrl}/Composition/\$enviar-rda-consulta";

        Log::info("Iniciando envío de RDA Consulta al API IHCE. URL: {$endpoint}");

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
                ->post($endpoint, $rdaDataConsulta);

            $statusCode = $response->status();
            $success = $response->successful();

            // Laravel extrae el JSON automáticamente (ideal para ver el detalle de los errores 400)
            $responseBody = $response->json();

            // Si el servidor del gobierno responde completamente vacío o con una pantalla HTML de error
            if (is_null($responseBody)) {
                $responseBody = ['raw_body' => $response->body()];
                Log::warning("Respuesta no-JSON o vacía de IHCE Consulta (HTTP {$statusCode})");
            }

            // Limpieza preventiva si el token expiró repentinamente
            if ($statusCode === 401) {
                Log::warning("Token IHCE rechazado (401) en Consulta. Limpiando caché.");
                \Illuminate\Support\Facades\Cache::forget('ihce_oauth_token');
            }

            Log::info("Respuesta Ministerio IHCE (Consulta) recibida - HTTP Status: {$statusCode}");

        } catch (\Exception $e) {
            Log::error("Fallo técnico al comunicar con Ministerio IHCE (Consulta): " . $e->getMessage());
            $responseBody = ['error_interno' => $e->getMessage()];
        }

        return [
            'success'     => $success,
            'status_code' => $statusCode,
            'response'    => $responseBody
        ];
    }
}
