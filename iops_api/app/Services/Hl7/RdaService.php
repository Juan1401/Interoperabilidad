<?php

namespace App\Services\Hl7;

use App\Models\Ingresos;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Servicio base para manejar la lógica de negocio de mensajes HL7 RDA
 */
abstract class RdaService
{
    /**
     * Obtiene un ingreso por su ID.
     *
     * @param int $ingresoId
     * @return \App\Models\Ingresos|null
     */
    protected function getIngresoWithRelations(int $ingresoId): ?Ingresos
    {
        try {
            return Ingresos::find($ingresoId);
        } catch (\Exception $e) {
            Log::error('Error al obtener ingreso: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Valida que el ingreso exista.
     *
     * @param \App\Models\Ingresos|null $ingreso
     * @return bool
     */
    protected function validateIngreso(?Ingresos $ingreso): bool
    {
        if (!$ingreso) {
            return false;
        }
        return true;
    }

    /**
     * Obtiene los datos del profesional asociado a la evolución activa del ingreso.
     *
     * @param int $ingresoId
     * @return array
     * @throws \Exception
     */
    protected function getPractitionerData(int $ingresoId): array
    {
        $evolucion = \App\Models\HcEvolucion::where('ingreso', $ingresoId)
            ->where('estado', '0')
            ->with(['profesionalUsuario.profesionalEspecialidades.especialidadDetail'])
            ->first();

        if (!$evolucion) {
            throw new \Exception("No se encontró evolución activa (estado 0) para el ingreso {$ingresoId}");
        }

        $profesionalUsuario = $evolucion->profesionalUsuario;
        if (!$profesionalUsuario) {
            throw new \Exception("No se encontró usuario profesional para la evolución {$evolucion->evolucion_id}");
        }

        // Búsqueda manual del profesional para manejar la clave compuesta correctamente
        $profesional = \App\Models\Profesional::where('tercero_id', $profesionalUsuario->tercero_id)
            ->where('tipo_id_tercero', $profesionalUsuario->tipo_tercero_id)
            ->first();

        if (!$profesional) {
            Log::warning("No se encontró información detallada en profesionales para el usuario {$profesionalUsuario->usuario_id} (Tipo: {$profesionalUsuario->tipo_tercero_id}, ID: {$profesionalUsuario->tercero_id})");
        }

        // Filtrar la especialidad correcta que coincida con el tipo_tercero_id del profesional
        $especialidadProfesional = $profesionalUsuario->profesionalEspecialidades
            ->where('tipo_id_tercero', $profesionalUsuario->tipo_tercero_id)
            ->first();

        if (!$especialidadProfesional) {
            Log::warning("No se encontró especialidad coincidente para el profesional {$profesionalUsuario->usuario_id}");
        }

        $especialidadDescripcion = $especialidadProfesional && $especialidadProfesional->especialidadDetail
            ? $especialidadProfesional->especialidadDetail->descripcion
            : 'SIN ESPECIALIDAD';

        $practitionerData = [
            'evolucion_id' => $evolucion->evolucion_id,
            'usuario_id' => $profesionalUsuario->usuario_id,
            'tipo_tercero_id' => $profesionalUsuario->tipo_tercero_id,
            'tercero_id' => $profesionalUsuario->tercero_id,
            'especialidad_codigo' => $especialidadProfesional ? $especialidadProfesional->especialidad : null,
            'especialidad_descripcion' => $especialidadDescripcion,
            'primer_nombre' => $profesional ? $profesional->primer_nombre : 'PROFESIONAL',
            'segundo_nombre' => $profesional ? $profesional->segundo_nombre : '',
            'primer_apellido' => $profesional ? $profesional->primer_apellido : 'DESCONOCIDO',
            'segundo_apellido' => $profesional ? $profesional->segundo_apellido : '',
        ];

        Log::info("Datos Practitioner obtenidos para ingreso {$ingresoId}", $practitionerData);

        return $practitionerData;
    }

    /**
     * Obtiene los datos de la organización (IPS) configurada.
     *
     * @param string $organizationId El ID de la organización a consultar, por defecto '01' según requerimiento.
     * @return array
     * @throws \Exception
     */
    protected function getOrganizationData(string $organizationId = '01'): array
    {
        $organization = \App\Models\Organization::find($organizationId);

        if (!$organization) {
            throw new \Exception("Organización con ID {$organizationId} no encontrada");
        }

        // Mapear datos a un array estructurado
        $organizationData = [
            'empresa_id' => $organization->empresa_id,
            'razon_social' => $organization->razon_social,
            'tipo_id_tercero' => $organization->tipo_id_tercero,
            'id' => $organization->id,
            'dv' => $organization->digito_verificacion,
            'codigo_habilitacion' => $organization->codigo_sgsss_ips ?? $organization->codigo_sgsss, // Usar codigo_sgsss_ips si existe, sino el general
            'direccion' => $organization->direccion,
            'telefono' => $organization->telefonos,
            'email' => $organization->email,
            'municipio_id' => $organization->tipo_mpio_id,
            'departamento_id' => $organization->tipo_dpto_id,
            'pais_id' => $organization->tipo_pais_id,
            'codigo_sgsss_ips' => $organization->codigo_sgsss_ips,
        ];

        Log::info("Datos Organization obtenidos", $organizationData);

        return $organizationData;
    }

    /**
     * Obtiene los datos de un ítem del catálogo HL7.
     *
     * @param int $itemId El ID del ítem a consultar en hl7_catalog_items.
     * @return array ['system' => url, 'code' => código, 'display' => descripción]
     * @throws \Exception
     */
    protected function getHl7CatalogItemData(int $itemId): array
    {
        $result = \Illuminate\Support\Facades\DB::table('hl7_catalogs as hc')
            ->join('hl7_catalog_items as hci', 'hc.id', '=', 'hci.hl7_catalog_id')
            ->where('hci.id', $itemId)
            ->select('hc.url', 'hc.name', 'hci.code', 'hci.display')
            ->first();

        if (!$result) {
            throw new \Exception("No se encontró el ítem del catálogo HL7 con ID {$itemId}");
        }

        $catalogItemData = [
            'system'  => $result->url,
            'code'    => $result->code,
            'display' => $result->display,
        ];

        Log::info("Datos catálogo HL7 obtenidos [{$result->name}]", $catalogItemData);

        return $catalogItemData;
    }

    /**
     * Obtiene los datos de un ítem del catálogo HL7 por nombre del catálogo.
     * permite obtener los datos de esta variable, como dato obligatorio siempre debe ir el hc.name, pero el dato hci.id es opcional
     *
     * @param string $catalogName Nombre del catálogo (hl7_catalogs.name)
     * @param int|null $itemId ID del ítem (hl7_catalog_items.id), opcional
     * @return array ['system' => url, 'code' => código, 'display' => descripción]
     * @throws \Exception
     */
    protected function getHl7CatalogItemByName(string $catalogName, ?int $itemId = null): array
    {
        $query = \Illuminate\Support\Facades\DB::table('hl7_catalogs as hc')
            ->join('hl7_catalog_items as hci', 'hc.id', '=', 'hci.hl7_catalog_id')
            ->where('hc.name', $catalogName)
            ->select('hc.url', 'hc.name', 'hci.code', 'hci.display');

        if ($itemId !== null) {
            $query->where('hci.id', $itemId);
        }

        $result = $query->first();

        if (!$result) {
            $msg = "No se encontró el ítem del catálogo '{$catalogName}'";
            if ($itemId) {
                $msg .= " con ID {$itemId}";
            }
            throw new \Exception($msg);
        }

        $itemData = [
            'system'  => $result->url,
            'code'    => $result->code,
            'display' => $result->display,
        ];

        Log::info("Datos catálogo HL7 [{$catalogName}] obtenidos", $itemData);

        return $itemData;
    }

    /**
     * Obtiene los datos de alergias del paciente de manera dinámica.
     *
     * @param int $ingresoId
     * @return array|null
     */
    protected function getPatientAllergyData(int $ingresoId): ?array
    {
        $result = \Illuminate\Support\Facades\DB::table('public.ingresos as i')
            ->join('public.hc_evoluciones as e', 'i.ingreso', '=', 'e.ingreso')
            ->join('public.hc_antecedentes_personales as ap', 'e.evolucion_id', '=', 'ap.evolucion_id')
            ->join('public.hc_tipos_antecedentes_detalle_personales as tap', 'ap.hc_tipo_antecedente_detalle_personal_id', '=', 'tap.hc_tipo_antecedente_detalle_personal_id')
            ->join('ihce.cat_tipos_alergia as cta', 'tap.tipo_alergia', '=', 'cta.id')
            ->where('i.ingreso', $ingresoId)
            ->whereNotNull('tap.tipo_alergia')
            ->orderBy('e.fecha', 'desc')
            ->select('cta.codigo as code', 'cta.descripcion as display', 'ap.detalle as text')
            ->first();

        if (!$result) {
            return null;
        }

        $displayRaw = (string) $result->display;
        $display = mb_check_encoding($displayRaw, 'UTF-8') ? $displayRaw : mb_convert_encoding($displayRaw, 'UTF-8', 'ISO-8859-1');

        $textRaw = (string) $result->text;
        if (!empty($textRaw)) {
            $text = mb_check_encoding($textRaw, 'UTF-8') ? $textRaw : mb_convert_encoding($textRaw, 'UTF-8', 'ISO-8859-1');
        } else {
            $text = $display;
        }

        return [
            'code' => $result->code,
            'display' => $display,
            'text' => $text
        ];
    }

    /**
     * Obtiene los datos de antecedentes patológicos (Conditions) del paciente de manera dinámica.
     * Consulta los antecedentes personales marcados como patológicos (sw_patologico = '1').
     *
     * @param int $ingresoId
     * @return array Arreglo de antecedentes patológicos procesados, o arreglo vacío si no hay resultados.
     */
    protected function getPatientConditionData(int $ingresoId): array
    {
        $results = \Illuminate\Support\Facades\DB::table('public.hc_antecedentes_personales as ap')
            ->join('public.hc_tipos_antecedentes_personales as tp', 'tp.hc_tipo_antecedente_personal_id', '=', 'ap.hc_tipo_antecedente_personal_id')
            ->join('public.hc_evoluciones as e', 'e.evolucion_id', '=', 'ap.evolucion_id')
            ->join('public.ingresos as i', 'i.ingreso', '=', 'e.ingreso')
            ->where('tp.sw_patologico', '1')
            ->where('i.ingreso', $ingresoId)
            ->select('ap.detalle as display')
            ->get();

        if ($results->isEmpty()) {
            return [];
        }

        $processed = [];
        foreach ($results as $row) {
            $displayRaw = (string) $row->display;
            $display = mb_check_encoding($displayRaw, 'UTF-8')
                ? $displayRaw
                : mb_convert_encoding($displayRaw, 'UTF-8', 'ISO-8859-1');

            $processed[] = [
                'display' => $display
            ];
        }

        return $processed;
    }

    /**
     * Obtiene los datos de antecedentes familiares del paciente de manera dinámica.
     *
     * @param int $ingresoId
     * @return array|null
     */
    protected function getPatientFamilyHistoryData(int $ingresoId): ?array
    {
        $result = \Illuminate\Support\Facades\DB::table('public.ingresos as i')
            ->join('public.hc_evoluciones as e', 'i.ingreso', '=', 'e.ingreso')
            ->join('public.hc_antecedentes_familiares as af', 'e.evolucion_id', '=', 'af.evolucion_id')
            ->join('ihce.parentescos as ip', 'af.tipo_parentesco_id', '=', 'ip.codigo')
            ->join('public.diagnosticos as d', 'af.diagnostico_id', '=', 'd.diagnostico_id')
            ->where('i.ingreso', $ingresoId)
            ->whereNotNull('af.tipo_parentesco_id')
            ->whereNotNull('af.diagnostico_id')
            ->orderBy('e.fecha', 'desc')
            ->select(
                'af.diagnostico_id as condition_code',
                'd.diagnostico_nombre as condition_display',
                'af.tipo_parentesco_id as relationship_code',
                'ip.descripcion as relationship_display'
            )
            ->first();

        if (!$result) {
            return null;
        }

        $conditionDisplayRaw = (string) $result->condition_display;
        $conditionDisplay = mb_check_encoding($conditionDisplayRaw, 'UTF-8')
            ? $conditionDisplayRaw
            : mb_convert_encoding($conditionDisplayRaw, 'UTF-8', 'ISO-8859-1');

        $relCode = $result->relationship_code;
        $relDisplayRaw = $result->relationship_display;

        if (empty($relCode) || empty($relDisplayRaw)) {
            $relCode = '01';
            $relDisplayRaw = 'Padres';
        }

        $relationshipDisplayRaw = (string) $relDisplayRaw;
        $relationshipDisplay = mb_check_encoding($relationshipDisplayRaw, 'UTF-8')
            ? $relationshipDisplayRaw
            : mb_convert_encoding($relationshipDisplayRaw, 'UTF-8', 'ISO-8859-1');

        return [
            'condition_code' => $result->condition_code,
            'condition_display' => $conditionDisplay,
            'relationship_code' => $relCode,
            'relationship_display' => $relationshipDisplay
        ];
    }

    /**
     * Obtiene los datos de medicamentos del paciente de manera dinámica.
     *
     * @param int $ingresoId
     * @return array|null
     */
    protected function getPatientMedicationData(int $ingresoId): ?array
    {
        $result = \Illuminate\Support\Facades\DB::table('public.ingresos as i')
            ->join('public.hc_evoluciones as e', 'i.ingreso', '=', 'e.ingreso')
            ->join('public.hc_antecedentes_personales as ap', 'e.evolucion_id', '=', 'ap.evolucion_id')
            ->join('public.inv_med_cod_principios_activos as pa', 'ap.cod_principio_activo', '=', 'pa.cod_principio_activo')
            ->join('public.hc_tipos_antecedentes_detalle_personales as tap', 'ap.hc_tipo_antecedente_detalle_personal_id', '=', 'tap.hc_tipo_antecedente_detalle_personal_id')
            ->where('i.ingreso', $ingresoId)
            ->whereNotNull('ap.cod_principio_activo')
            ->whereNull('tap.tipo_alergia')
            ->orderBy('e.fecha', 'desc')
            ->select('ap.cod_principio_activo as medication_code', 'pa.descripcion as medication_display')
            ->first();

        if (!$result) {
            return null;
        }

        $medicationDisplayRaw = (string) $result->medication_display;
        $medicationDisplay = mb_check_encoding($medicationDisplayRaw, 'UTF-8')
            ? $medicationDisplayRaw
            : mb_convert_encoding($medicationDisplayRaw, 'UTF-8', 'ISO-8859-1');

        return [
            'code' => $result->medication_code,
            'display' => $medicationDisplay
        ];
    }

    /**
     * Obtiene los datos de un código ICD-10.
     *
     * @param string $code Código ICD-10 a consultar.
     * @return array ['system' => url, 'code' => código, 'display' => descripción]
     * @throws \Exception
     */
    protected function getIcd10Data(string $code): array
    {
        $result = \Illuminate\Support\Facades\DB::table('icd10co')
            ->where('code', $code)
            ->where('active', true)
            ->select('code', 'display')
            ->first();

        if (!$result) {
            throw new \Exception("No se encontró el código ICD-10 '{$code}'");
        }

        $icd10Data = [
            'system'  => 'http://hl7.org/fhir/sid/icd-10',
            'code'    => $result->code,
            'display' => $result->display,
        ];

        Log::info("Datos ICD-10 [{$code}] obtenidos", $icd10Data);

        return $icd10Data;
    }

    /**
     * Obtiene los datos de un código INN de MIPRES.
     *
     * @param string $code Código INN a consultar en la tabla mipres_inn.
     * @return array ['system' => url, 'code' => código, 'display' => descripción]
     * @throws \Exception
     */
    protected function getMipresInnData(string $code): array
    {
        $result = \Illuminate\Support\Facades\DB::table('mipres_inn')
            ->where('code', $code)
            ->where('active', true)
            ->select('code', 'display')
            ->first();

        if (!$result) {
            throw new \Exception("No se encontró el código MipresINN '{$code}'");
        }

        $mipresInnData = [
            'system'  => 'https://fhir.minsalud.gov.co/rda/CodeSystem/MipresINN',
            'code'    => $result->code,
            'display' => $result->display,
        ];

        Log::info("Datos MipresINN [{$code}] obtenidos", $mipresInnData);

        return $mipresInnData;
    }

    /**
     * Obtiene los datos de un país según ISO 3166-1.
     *
     * @param string $codeType Tipo de código: alpha2, alpha3, numeric
     * @param string $display  Nombre del país a buscar (búsqueda parcial, case-insensitive).
     * @return array ['system' => url, 'code' => código, 'display' => descripción]
     * @throws \Exception
     */
    protected function getIso31661Data(string $codeType, string $display): array
    {
        $result = \Illuminate\Support\Facades\DB::table('iso_3166_1')
            ->where('code_type', $codeType)
            ->where('display', 'ILIKE', '%' . $display . '%')
            ->where('active', true)
            ->select('code', 'display')
            ->first();

        if (!$result) {
            throw new \Exception("No se encontró el país ISO 3166-1 con code_type '{$codeType}' y display '{$display}'");
        }

        $iso31661Data = [
            'system'  => 'https://fhir.minsalud.gov.co/rda/CodeSystem/ISO31661',
            'code'    => $result->code,
            'display' => $result->display,
        ];

        Log::info("Datos ISO 3166-1 [{$codeType}/{$display}] obtenidos", $iso31661Data);

        return $iso31661Data;
    }

    /**
     * Obtiene los datos de un grupo étnico colombiano por código.
     *
     * @param string $code Código del grupo étnico. Ej: '6' (Otras etnias)
     * @return array ['system' => url, 'code' => código, 'display' => descripción]
     * @throws \Exception
     */
    protected function getColombianEthnicGroupData(string $id): array
    {
        $result = \Illuminate\Support\Facades\DB::table('colombian_ethnic_group')
            ->where('id', $id)
            ->where('active', true)
            ->select('code', 'display')
            ->first();

        if (!$result) {
            throw new \Exception("No se encontró el grupo étnico colombiano con id '{$id}'");
        }

        $ethnicGroupData = [
            'system'  => 'https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianEthnicGroup',
            'code'    => $result->code,
            'display' => $result->display,
        ];

        Log::info("Datos ColombianEthnicGroup [{$id}] obtenidos", $ethnicGroupData);

        return $ethnicGroupData;
    }

    /**
     * Obtiene los datos de una clasificación de discapacidad colombiana por código.
     *
     * @param string $code Código de la clasificación de discapacidad. Ej: '08' (Sin Discapacidad)
     * @return array ['system' => url, 'code' => código, 'display' => descripción]
     * @throws \Exception
     */
    protected function getColombianDisabilityClassificationData(string $id): array
    {
        $result = \Illuminate\Support\Facades\DB::table('colombian_disability_classification')
            ->where('id', $id)
            ->where('active', true)
            ->select('code', 'display')
            ->first();

        if (!$result) {
            throw new \Exception("No se encontró la clasificación de discapacidad colombiana con id '{$id}'");
        }

        $disabilityData = [
            'system'  => 'https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianDisabilityClassification',
            'code'    => $result->code,
            'display' => $result->display,
        ];

        Log::info("Datos ColombianDisabilityClassification [{$id}] obtenidos", $disabilityData);

        return $disabilityData;
    }

    /**
     * Obtiene los datos de una identidad de género colombiana por id.
     *
     * @param string $id ID de la identidad de género. Ej: '1' (Masculino)
     * @return array ['system' => url, 'code' => código, 'display' => descripción]
     * @throws \Exception
     */
    protected function getColombianGenderIdentityData(string $id): array
    {
        $result = \Illuminate\Support\Facades\DB::table('colombian_gender_identity')
            ->where('id', $id)
            ->where('active', true)
            ->select('code', 'display')
            ->first();

        if (!$result) {
            throw new \Exception("No se encontró la identidad de género colombiana con id '{$id}'");
        }

        $genderIdentityData = [
            'system'  => 'https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianGenderIdentity',
            'code'    => $result->code,
            'display' => $result->display,
        ];

        Log::info("Datos ColombianGenderIdentity [{$id}] obtenidos", $genderIdentityData);

        return $genderIdentityData;
    }

    /**
     * Obtiene los datos de un tipo de identificador colombiano por código.
     *
     * @param string $code Código del identificador. Ej: 'CC', 'TI'
     * @return array ['system' => url, 'code' => código, 'display' => descripción]
     * @throws \Exception
     */
    protected function getColombianPersonIdentifierData(string $code): array
    {
        $result = \Illuminate\Support\Facades\DB::table('colombian_person_identifier')
            ->where('code', $code)
            ->where('active', true)
            ->select('code', 'display')
            ->first();

        if (!$result) {
            throw new \Exception("No se encontró el identificador colombiano con código '{$code}'");
        }

        $identifierData = [
            'system'  => 'https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianPersonIdentifier',
            'code'    => $result->code,
            'display' => $result->display,
        ];

        Log::info("Datos ColombianPersonIdentifier [{$code}] obtenidos", $identifierData);

        return $identifierData;
    }

    /**
     * Obtiene los datos DIVIPOLA (municipio y departamento) por código DIVIPOLA string concatenado.
     *
     * @param string $divipolaCode (Ej: '76001')
     * @return array Datos del municipio y su departamento relacionado.
     * @throws \Exception
     */
    protected function getDivipolaDataByCode(string $divipolaCode): array
    {
        $result = \Illuminate\Support\Facades\DB::table('ihce.municipalities as m')
            ->join('ihce.departments as d', 'm.department_id', '=', 'd.id')
            ->select(
                'm.*',
                'd.id as id_d',
                'd.code as code_d',
                'd.display as display_d',
                'd.active as active_d'
            )
            ->where('m.code', $divipolaCode)
            ->first();

        if (!$result) {
            throw new \Exception("No se encontró el municipio con código DIVIPOLA {$divipolaCode}");
        }

        return (array) $result;
    }

    /**
     * Obtiene los datos DIVIPOLA (municipio y departamento) por ID de municipio.
     *
     * @param int $municipalityId ID del municipio en la tabla ihce.municipalities.
     * @return array Datos del municipio y su departamento relacionado.
     * @throws \Exception
     */
    protected function getDivipolaDataByMunicipalityId(int $municipalityId): array
    {
        $result = \Illuminate\Support\Facades\DB::table('ihce.municipalities as m')
            ->join('ihce.departments as d', 'm.department_id', '=', 'd.id')
            ->select(
                'm.*',
                'd.id as id_d',
                'd.code as code_d',
                'd.display as display_d',
                'd.active as active_d'
            )
            ->where('m.id', $municipalityId)
            ->first();

        if (!$result) {
            throw new \Exception("No se encontró el municipio con ID {$municipalityId} en ihce.municipalities");
        }

        Log::info("Datos DIVIPOLA obtenidos para municipio ID [{$municipalityId}]", (array)$result);

        return (array)$result;
    }

    /**
     * Obtiene los datos de una zona de residencia colombiana por código.
     *
     * @param string $code Código de la zona de residencia. Ej: '01' (Urbana), '02' (Rural)
     * @return array ['system' => url, 'code' => código, 'display' => descripción]
     * @throws \Exception
     */
    protected function getColombianResidenceZoneData(string $code): array
    {
        $result = \Illuminate\Support\Facades\DB::table('ihce.colombian_residence_zone')
            ->where('code', $code)
            ->where('active', true)
            ->select('code', 'display')
            ->first();

        if (!$result) {
            throw new \Exception("No se encontró la zona de residencia colombiana con código '{$code}'");
        }

        $residenceZoneData = [
            'system'  => 'https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianResidenceZone',
            'code'    => $result->code,
            'display' => $result->display,
        ];

        Log::info("Datos ColombianResidenceZone [{$code}] obtenidos", $residenceZoneData);

        return $residenceZoneData;
    }

    /**
     * Obtiene los datos de una zona de residencia colombiana por código.
     *
     * @param string $code Código de la zona de residencia. Ej: '01' (Urbana), '02' (Rural)
     * @return array ['system' => url, 'code' => código, 'display' => descripción]
     * @throws \Exception
     */
    protected function getColombianGenderGroup(string $id): array
    {
        $result = \Illuminate\Support\Facades\DB::table('ihce.colombian_gender_group')
            ->where('id', $id)
            ->where('active', true)
            ->select('code', 'display')
            ->first();

        if (!$result) {
            throw new \Exception("No se encontró el grupo de género colombiano con id '{$id}'");
        }

        $residenceZoneData = [
            'system'  => 'https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianGenderGroup',
            'code'    => $result->code,
            'display' => $result->display,
        ];

        Log::info("Datos ColombianGenderGroup [{$id}] obtenidos", $residenceZoneData);

        return $residenceZoneData;
    }
    /**
     * Obtiene los datos de un identificador de organización colombiano por código.
     *
     * @param string $id Código del identificador de organización colombiano. Ej: 'NIT', 'CodigoPrestador', 'EAPB'
     * @return array ['system' => url, 'code' => código, 'display' => descripción]
     * @throws \Exception
     */
    protected function getColombianOrganizationIdentifier(string $id): array
    {
        $result = \Illuminate\Support\Facades\DB::table('ihce.colombian_organization_identifiers')
            ->where('id', $id)
            ->where('active', true)
            ->select('code', 'display')
            ->first();

        if (!$result) {
            throw new \Exception("No se encontró el identificador de organización colombiano con id '{$id}'");
        }

        $organizationIdentifierData = [
            'system'  => 'https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianOrganizationIdentifier',
            'code'    => $result->code,
            'display' => $result->display,
        ];

        Log::info("Datos ColombianOrganizationIdentifier [{$id}] obtenidos", $organizationIdentifierData);

        return $organizationIdentifierData;
    }

    /**
     * Sanitiza una cadena para ser usada como ID en FHIR.
     * Elimina espacios, puntos, comas, tildes y caracteres especiales.
     * Solo permite letras y números.
     *
     * @param string $input
     * @return string
     */
    protected function sanitizeForId(string $input): string
    {
        // Mapa de tildes y caracteres especiales a letras base
        $unwanted_array = [
            'Š' => 'S',
            'š' => 's',
            'Ž' => 'Z',
            'ž' => 'z',
            'À' => 'A',
            'Á' => 'A',
            'Â' => 'A',
            'Ã' => 'A',
            'Ä' => 'A',
            'Å' => 'A',
            'Æ' => 'A',
            'Ç' => 'C',
            'È' => 'E',
            'É' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'Ì' => 'I',
            'Í' => 'I',
            'Î' => 'I',
            'Ï' => 'I',
            'Ñ' => 'N',
            'Ò' => 'O',
            'Ó' => 'O',
            'Ô' => 'O',
            'Õ' => 'O',
            'Ö' => 'O',
            'Ø' => 'O',
            'Ù' => 'U',
            'Ú' => 'U',
            'Û' => 'U',
            'Ü' => 'U',
            'Ý' => 'Y',
            'Þ' => 'B',
            'ß' => 'Ss',
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'ä' => 'a',
            'å' => 'a',
            'æ' => 'a',
            'ç' => 'c',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ð' => 'o',
            'ñ' => 'n',
            'ò' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ö' => 'o',
            'ø' => 'o',
            'ù' => 'u',
            'ú' => 'u',
            'û' => 'u',
            'ý' => 'y',
            'þ' => 'b',
            'ÿ' => 'y'
        ];

        $str = strtr($input, $unwanted_array);

        // Eliminar cualquier cosa que no sea letras o números
        return preg_replace('/[^A-Za-z0-9]/', '', $str);
    }

    /**
     * Registra o actualiza el Master (ihce_control_envios) y el Detalle (ihce_control_envios_logs).
     *
     * @param int $ingresoId
     * @param int $usuarioId
     * @param int $tipoRdaId   (Ej: 1 = RDA Paciente)
     * @param array $jsonEnviado
     * @param array $mensajeRespuesta
     * @param int $statusCode
     * @return void
     */
    public function logEnvioRda(
        int $ingresoId,
        int $usuarioId,
        int $tipoRdaId,
        array $jsonEnviado,
        array $mensajeRespuesta,
        int $statusCode
    ): void {
        try {
            // Determinar estado basado en código HTTP: 2=EXITOSO, 3=FALLIDO, 4=RECHAZADO
            $estadoEnvioId = 3;
            if ($statusCode >= 200 && $statusCode < 300) {
                $estadoEnvioId = 2;
            } elseif ($statusCode >= 400 && $statusCode < 500) {
                $estadoEnvioId = 4;
            }

            // Buscar registro maestro
            $master = \Illuminate\Support\Facades\DB::table('ihce.ihce_control_envios')
                ->where('ingreso_id', $ingresoId)
                ->where('tipo_rda_id', $tipoRdaId)
                ->first();

            if ($master) {
                // Actualizar registro maestro si ya existe
                \Illuminate\Support\Facades\DB::table('ihce.ihce_control_envios')
                    ->where('envio_id', $master->envio_id)
                    ->update([
                        'estado_envio_id' => $estadoEnvioId,
                        'fecha_ultimo_intento' => now(),
                        'intentos_realizados' => $master->intentos_realizados + 1,
                        'codigo_respuesta_http' => (string)$statusCode,
                        'updated_at' => now(),
                    ]);
                $envioId = $master->envio_id;
                $accionLogId = 2; // (2 = REINTENTO_AUTOMATICO/NUEVO INTENTO)
            } else {
                // Obtener evolucion activa para el ingreso para relacionarla si existe
                $evolucion = \App\Models\HcEvolucion::where('ingreso', $ingresoId)->where('estado', '0')->first();

                // Crear nuevo registro maestro
                $envioId = \Illuminate\Support\Facades\DB::table('ihce.ihce_control_envios')->insertGetId([
                    'ingreso_id' => $ingresoId,
                    'evolucion_id' => $evolucion ? $evolucion->evolucion_id : null,
                    'tipo_rda_id' => $tipoRdaId,
                    'estado_envio_id' => $estadoEnvioId,
                    'fecha_ultimo_intento' => now(),
                    'intentos_realizados' => 1,
                    'codigo_respuesta_http' => (string)$statusCode,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], 'envio_id');
                $accionLogId = 1; // (1 = ENVIO_MANUAL)
            }

            // Sanitizar los arrays antes de guardarlos para evitar que json_encode devuelva false por UTF-8
            // Se usa el flag JSON_INVALID_UTF8_SUBSTITUTE que reemplaza caracteres no válidos.
            $encodedJsonEnviado = json_encode($jsonEnviado, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            if ($encodedJsonEnviado === false) {
                $encodedJsonEnviado = json_encode(['error_local' => 'Payload original no pudo ser codificado a JSON por caracteres incompatibles.']);
            } else {
                // Prevenir error "Untranslatable character" en columnas jsonb de Postgres(LATIN1)
                // Postgres resuelve internamente secuencias como \u2013 (en-dash) en el jsonb, fallando si la BD es LATIN1.
                // Convertimos los escapes unicode no-ASCII a '?' o suprimimos antes de insertar.
                $encodedJsonEnviado = preg_replace('/\\\\u([0-9a-fA-F]{4})/i', '?', $encodedJsonEnviado);
            }

            $encodedMensajeRespuesta = json_encode($mensajeRespuesta, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            if ($encodedMensajeRespuesta === false) {
                $encodedMensajeRespuesta = json_encode(['error_local' => 'Respuesta original no pudo ser codificada a JSON por caracteres incompatibles.']);
            } else {
                $encodedMensajeRespuesta = preg_replace('/\\\\u([0-9a-fA-F]{4})/i', '?', $encodedMensajeRespuesta);
            }

            // Insertar el detalle del log
            \Illuminate\Support\Facades\DB::table('ihce.ihce_control_envios_logs')->insert([
                'envio_id' => $envioId,
                'fecha_evento' => now(),
                'usuario_id' => $usuarioId,
                'accion_log_id' => $accionLogId,
                'json_enviado' => $encodedJsonEnviado,
                'mensaje_respuesta' => $encodedMensajeRespuesta,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info("Trazabilidad RDA guardada en base de datos. EnvioID: {$envioId}, Master actualizado/creado y Log insertado.");
        } catch (\Exception $e) {
            Log::error("Fallo al registrar la persistencia del envío RDA en la DB (Master o Log): " . $e->getMessage() . " - LÍNEA: " . $e->getLine());
        }
    }

    /**
     * Método abstracto que debe implementar cada tipo de RDA.
     *
     * @param int $ingresoId
     * @return array
     */
    abstract public function getDataForRda(int $ingresoId): array;

    /**
     * Determina la clase de atención y las fechas de la misma basada en reglas de negocio
     *
     * @param int $ingresoId
     * @return array
     */
    public function determinarClaseAtencionDate(int $ingresoId): array
    {
        $ingreso = \Illuminate\Support\Facades\DB::table('public.ingresos as i')
            ->leftJoin('public.ingresos_salidas as isd', 'i.ingreso', '=', 'isd.ingreso')
            ->where('i.ingreso', $ingresoId)
            ->select('i.fecha_ingreso', 'isd.fecha_registro as fecha_egreso')
            ->first();

        // Si no hay ingreso, abortar o devolver array por default.
        if (!$ingreso) {
            return [
                'clase_atencion_fhir' => 'AMB',
                'grupo_servicio_codigo' => '01',
                'grupo_servicio_nombre' => 'Consulta Externa',
                'fecha_inicio' => null,
                'fecha_fin' => null,
                'urgencia_fecha_inicio' => null,
                'urgencia_fecha_fin' => null,
            ];
        }

        // Obtener el primer ingreso y último egreso de IMP
        $impData = \Illuminate\Support\Facades\DB::table('public.movimientos_habitacion as mh')
            ->join('public.estaciones_enfermeria as ee', 'mh.estacion_id', '=', 'ee.estacion_id')
            ->where('mh.ingreso', $ingresoId)
            ->where('ee.sw_hospitalizacion_rips', '1')
            ->selectRaw('MIN(mh.fecha_ingreso) as primer_ingreso, MAX(mh.fecha_egreso) as ultimo_egreso')
            ->first();

        // Obtener el último egreso de EMER
        $emerData = \Illuminate\Support\Facades\DB::table('public.movimientos_habitacion as mh')
            ->join('public.estaciones_enfermeria as ee', 'mh.estacion_id', '=', 'ee.estacion_id')
            ->where('mh.ingreso', $ingresoId)
            ->where('ee.sw_hospitalizacion_rips', '0')
            ->selectRaw('MAX(mh.fecha_egreso) as ultimo_egreso, COUNT(*) as conteo')
            ->first();

        $hasImp = !empty($impData->primer_ingreso);
        $hasEmer = $emerData && $emerData->conteo > 0;

        if ($hasImp) {
            $claseF = 'IMP';
            $gsc = '03';
            $gsn = 'Internación';
            $fi = $impData->primer_ingreso;
            $ff = (!empty($impData->ultimo_egreso)) ? $impData->ultimo_egreso : $ingreso->fecha_egreso;
        } elseif ($hasEmer) {
            $claseF = 'EMER';
            $gsc = '02';
            $gsn = 'Urgencias';
            $fi = $ingreso->fecha_ingreso;
            $ff = $emerData->ultimo_egreso;
        } else {
            $claseF = 'AMB';
            $gsc = '01';
            $gsn = 'Consulta Externa';
            $fi = $ingreso->fecha_ingreso;
            $ff = $ingreso->fecha_egreso;
        }

        // Calcula la fecha fin de EMER según el caso B si alguien consulta por urgencia específicamente
        $emerFi = $ingreso->fecha_ingreso;
        if ($hasEmer) {
            $emerFf = $emerData->ultimo_egreso;
        } elseif ($hasImp) {
            $emerFf = $impData->primer_ingreso; // Caso B: "Si no existen movimientos... tomar primer registro donde sw=1 para cerrar urgencia"
        } else {
            $emerFf = $ingreso->fecha_egreso;
        }

        return [
            'clase_atencion_fhir' => $claseF,
            'grupo_servicio_codigo' => $gsc,
            'grupo_servicio_nombre' => $gsn,
            'fecha_inicio' => $fi,
            'fecha_fin' => $ff,
            'urgencia_fecha_inicio' => $emerFi,
            'urgencia_fecha_fin' => $emerFf,
        ];
    }

    /**
     * Determina la clase de atención y las fechas de la misma basada en reglas de negocio
     *
     * @param int $ingresoId
     * @return array
     */
    public function getCiuo88ac($code): array
    {
        $ciuo88ac = \Illuminate\Support\Facades\DB::table('ihce.ciuo_88_ac')
            ->where('code', $code)
            ->first();

        $ciuoCode = $ciuo88ac ? $ciuo88ac->code : '9333';
        $ciuoDisplay = $ciuo88ac ? $ciuo88ac->display : 'Ocupaciones no clasificadas en otra parte';

        return [
            'code' => $ciuoCode,
            'display' => $ciuoDisplay
        ];
    }

    /**
     * Consume el endpoint interno del SIIS Legacy para obtener la Epicrisis.
     *
     * @param int $ingresoId ID del ingreso del paciente
     * @return array Respuesta con el contenido de la epicrisis o el error
     */
    public function obtenerEpicrisisSiis(int $ingresoId): array
    {
        Log::info("Consultando endpoint de reporte epicrisis SIIS para ingreso: {$ingresoId}");

        try {
            // 1. Consultar la "bandera" en la base de datos (esquema publico).
            $swEnvioEpicrisis = \Illuminate\Support\Facades\DB::table('public.system_modulos_variables')
                ->where('variable', 'IHCE_sw_envio_epicrisis')
                ->value('valor');

            // 2. Si la bandera está apagada (es distinta de '1'), se retorna el PDF quemado.
            if ($swEnvioEpicrisis !== '1') {
                Log::info("Feature Flag [IHCE_sw_envio_epicrisis] apagado. Retornando PDF Base64 por defecto para el ingreso {$ingresoId}.");

                return [
                    'success' => true,
                    'data'    => ['mensaje' => 'Generación de PDF desactivada temporalmente (Fallback)'],
                    'pdf_base64' => 'JVBERi0xLjQKJcOkw7zDtsOfCjIgMCBvYmoKPDwvTGVuZ3RoIDMgMCBSPj4Kc3RyZWFtCkJUCjAvRjEgMjQKVGYKMSAwIDAKMSAxMCAxMApUbQooRG9jdW1lbnRvIGRlIHBydWViYSkKVEoKRVQKZW5kc3RyZWFtCmVuZG9iagozIDAgb2JqCjUzCmVuZG9iago1IDAgb2JqCjw8L1R5cGUvUGFnZS9NZWRpYUJveFswIDAgNTk1IDg0Ml0vUmVzb3VyY2VzPDwvRm9udDw8L0YxIDEgMCBSPj4+Pi9Db250ZW50cyAyIDAgUi9QYXJlbnQgNCAwIFI+PgplbmRvYmoKMSAwIG9iago8PC9UeXBlL0ZvbnQvU3VidHlwZS9UeXBlMS9CYXNlRm9udC9IZWx2ZXRpY2E+PgplbmRvYmoKNCAwIG9iago8PC9UeXBlL0VwYWdlcy9Db3VudCAxL0tpZHNbNSAwIFJdPj4KZW5kc3RyZWFtCmVuZG9iago2IDAgb2JqCjw8L1R5cGUvQ2F0YWxvZy9QYWdlcyA0IDAgUj4+CmVuZG9iago3IDAgb2JqCjw8L1Byb2R1Y2VyKEdvc3RzY3JpcHQgOS41MCkvQ3JlYXRpb25EYXRlKEQ6MjAyMzA5MDExNjM4NDJaMDAnMDAnKT4+CmVuZG9iagp4cmVmCjAgOAowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAyMjUgMDAwMDAgbiAKMDAwMDAwMDAxNSAwMDAwMCBuIAowMDAwMDAwMTE3IDAwMDAwIG4gCjAwMDAwMDAzMTMgMDAwMDAgbiAKMDAwMDAwMDEzNiAwMDAwMCBuIAowMDAwMDAwMzY0IDAwMDAwIG4gCjAwMDAwMDA0MTIgMDAwMDAgbiAKdHJhaWxlcgo8PC9TaXplIDgvUm9vdCA2IDAgUi9JbmZvIDcgMCBSPj4Kc3RhcnR4cmVmCjUwNAolJUVPRgo='
                ];
            }

            // 3. Si la bandera es '1', ejecutamos el flujo original normal.
            $baseUrl = config('services.siis.base_url');
            if (!$baseUrl) {
                Log::error("SIIS_BASE_URL no parametrizada en el .env");
                return ['success' => false, 'error' => 'SIIS_BASE_URL no parametrizada en el .env'];
            }

            $baseUrl = rtrim($baseUrl, '/') . "/webservices/Ihce/apiReporteEpicrisis.php";

            // Laravel hace la petición
            $response = Http::timeout(60)->get($baseUrl, ['ingreso' => $ingresoId]);

            // Si el HTTP es 200 OK
            if ($response->successful()) {
                $jsonArray = $response->json();

                // Validamos que el JSON tenga la llave 'status' en 200
                if (isset($jsonArray['status']) && $jsonArray['status'] == 200) {

                    Log::info("DEBUG BROWSERSHOT: HTML obtenido, lanzando Google Chrome (Headless)...");

                    $htmlPuro = '';
                    if (!empty($jsonArray['html_base64'])) {
                        $htmlPuro = base64_decode($jsonArray['html_base64']);
                    } else {
                        throw new \Exception("No hay HTML codificado en base64 en la respuesta del SIIS (html_base64 vacío).");
                    }

                    // Generar PDF con Google Chrome sin guardar en disco.
                    $pdfContent = \Spatie\Browsershot\Browsershot::html($htmlPuro)
                        ->setChromePath('/usr/bin/chromium')
                        ->noSandbox()
                        ->ignoreHttpsErrors()
                        ->landscape()
                        ->format('Letter')
                        ->margins(10, 10, 10, 10)
                        ->waitUntilNetworkIdle()
                        ->pdf();

                    Log::info("DEBUG BROWSERSHOT: ¡PDF generado con éxito!");

                    $epicrisisPdfBase64 = base64_encode($pdfContent);

                    return [
                        'success' => true,
                        'data'    => $jsonArray,
                        'pdf_base64' => $epicrisisPdfBase64
                    ];
                }
            }

            return ['success' => false, 'error' => 'Respuesta no exitosa del SIIS (HTTP Status: ' . $response->status() . ')'];

        } catch (\Exception $e) {
            Log::error("Error en obtenerEpicrisisSiis (o conversión a PDF): " . $e->getMessage());

            // Sugerencia Arquitectonica: En caso de error con Browsershot o SIIS,
            // se devuelve el PDF de emergencia en lugar de fallar para NO bloquear el envío del RDA completo.
            return [
                'success' => true,
                'data'    => ['mensaje' => 'Error capturado en Epicrisis, usando Fallback. Detalles: ' . $e->getMessage()],
                'pdf_base64' => 'JVBERi0xLjQKJcOkw7zDtsOfCjIgMCBvYmoKPDwvTGVuZ3RoIDMgMCBSPj4Kc3RyZWFtCkJUCjAvRjEgMjQKVGYKMSAwIDAKMSAxMCAxMApUbQooRG9jdW1lbnRvIGRlIHBydWViYSkKVEoKRVQKZW5kc3RyZWFtCmVuZG9iagozIDAgb2JqCjUzCmVuZG9iago1IDAgb2JqCjw8L1R5cGUvUGFnZS9NZWRpYUJveFswIDAgNTk1IDg0Ml0vUmVzb3VyY2VzPDwvRm9udDw8L0YxIDEgMCBSPj4+Pi9Db250ZW50cyAyIDAgUi9QYXJlbnQgNCAwIFI+PgplbmRvYmoKMSAwIG9iago8PC9UeXBlL0ZvbnQvU3VidHlwZS9UeXBlMS9CYXNlRm9udC9IZWx2ZXRpY2E+PgplbmRvYmoKNCAwIG9iago8PC9UeXBlL0VwYWdlcy9Db3VudCAxL0tpZHNbNSAwIFJdPj4KZW5kc3RyZWFtCmVuZG9iago2IDAgb2JqCjw8L1R5cGUvQ2F0YWxvZy9QYWdlcyA0IDAgUj4+CmVuZG9iago3IDAgb2JqCjw8L1Byb2R1Y2VyKEdvc3RzY3JpcHQgOS41MCkvQ3JlYXRpb25EYXRlKEQ6MjAyMzA5MDExNjM4NDJaMDAnMDAnKT4+CmVuZG9iagp4cmVmCjAgOAowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAyMjUgMDAwMDAgbiAKMDAwMDAwMDAxNSAwMDAwMCBuIAowMDAwMDAwMTE3IDAwMDAwIG4gCjAwMDAwMDAzMTMgMDAwMDAgbiAKMDAwMDAwMDEzNiAwMDAwMCBuIAowMDAwMDAwMzY0IDAwMDAwIG4gCjAwMDAwMDA0MTIgMDAwMDAgbiAKdHJhaWxlcgo8PC9TaXplIDgvUm9vdCA2IDAgUi9JbmZvIDcgMCBSPj4Kc3RhcnR4cmVmCjUwNAolJUVPRgo='
            ];
        }
    }

    /**
     * Obtiene los diagnósticos y cargos CUPS asociados al ingreso a través de la cuenta.
     *
     * @param int $ingresoId
     * @return array
     */
    protected function getDiagnosticosCuentas(int $ingresoId): array
    {
        try {
            $diagnosticos = \Illuminate\Support\Facades\DB::table('public.cuentas_detalle as cd')
                ->join('public.cuentas as c', 'c.numerodecuenta', '=', 'cd.numerodecuenta')
                ->join('public.ingresos as i', 'i.ingreso', '=', 'c.ingreso')
                ->join('public.cups as cp', 'cp.cargo', '=', 'cd.cargo_cups')
                ->join('public.rips_parametros_tipos as rp', function ($join) {
                    $join->on('rp.grupo_tarifario_id', '=', 'cp.grupo_tarifario_id')
                         ->on('rp.subgrupo_tarifario_id', '=', 'cp.subgrupo_tarifario_id');
                })
                ->where('i.ingreso', $ingresoId)
                ->where('rp.rips_tipo_id', 'AC')
                ->select('cp.cargo', 'cp.descripcion')
                ->limit(1)
                ->get();

            // Transformar la colección a un array asociativo simple
            $diagnosticosArray = $diagnosticos->map(fn($item) => (array) $item)->toArray();
            // Validar si la descripción ya es UTF-8
            $descripcion = $diagnosticosArray[0]['descripcion'];
            if (!mb_check_encoding($descripcion, 'UTF-8')) {
                $descripcion = mb_convert_encoding($descripcion, 'UTF-8', 'ISO-8859-1');
            }

            // echo "<pre>diagnosticosArray: ";
            // print_r($diagnosticosArray);
            // echo "</pre>";

            return ["code" => $diagnosticosArray[0]['cargo'], "display" => $descripcion];
        } catch (\Exception $e) {
            Log::error("Error al consultar cuentas y cups para ingreso {$ingresoId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene los datos de la clasificación de discapacidad del paciente dinámicamente
     * desde los antecedentes de la evolución más reciente del ingreso.
     * Si no tiene una registrada, retorna "08" (Sin Discapacidad) por defecto.
     *
     * @param int $ingresoId ID del ingreso actual
     * @return array ['system' => url, 'code' => código, 'display' => descripción]
     */
    protected function getPatientDisabilityData(int $ingresoId): array
    {
        try {
            $antecedente = \Illuminate\Support\Facades\DB::table('public.ingresos as i')
                ->join('public.hc_evoluciones as e', 'i.ingreso', '=', 'e.ingreso')
                ->join('public.hc_antecedentes_personales as ap', 'e.evolucion_id', '=', 'ap.evolucion_id')
                ->join('public.hc_tipos_antecedentes_detalle_personales_adicional as ad', 'ap.tipo_discapacidad', '=', 'ad.hc_tipos_antecedentes_detalle_personales_adicional_id')
                ->where('i.ingreso', $ingresoId)
                ->whereNotNull('ap.tipo_discapacidad')
                ->select('ad.discapacidad_hl7 as code', 'ad.descripcion as display')
                ->orderByDesc('e.fecha')
                ->first();

            // Si la consulta arroja resultados y tiene el código HL7
            if ($antecedente && !empty($antecedente->code)) {

                // Validar si la descripción ya es UTF-8
                $descripcion = $antecedente->display;
                if (!mb_check_encoding($descripcion, 'UTF-8')) {
                    $descripcion = mb_convert_encoding($descripcion, 'UTF-8', 'ISO-8859-1');
                }

                $disabilityData = [
                    'system'  => 'https://fhir.minsalud.gov.co/rda/CodeSystem/ColombianDisabilityClassification',
                    'code'    => str_pad($antecedente->code, 2, "0", STR_PAD_LEFT), // Formateo a 2 dígitos exigido
                    'display' => trim($descripcion),
                ];

                Log::info("Datos de discapacidad dinámicos obtenidos para el ingreso {$ingresoId}", $disabilityData);

                return $disabilityData;
            }
        } catch (\Exception $e) {
            Log::warning("Error al consultar la discapacidad dinámica para el ingreso {$ingresoId}: " . $e->getMessage());
        }

        // Fallback: Si no hay registro o falla la consulta, retornamos '08' por defecto
        Log::info("Aplicando fallback de discapacidad '08' (Sin discapacidad) para el ingreso {$ingresoId}.");
        return $this->getColombianDisabilityClassificationData('08');
    }

}