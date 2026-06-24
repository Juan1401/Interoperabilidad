// ============================================================
// Utilidades de Parseo FHIR - Modal RDA Consulta Externa
// Complementa a consultar-ihce.utils.ts con los tipos de recursos
// específicos de la Composition de Consulta Ambulatoria.
// ============================================================

export interface ObservacionComponentData {
    codigoCode: string;
    codigoDisplay: string;
    valor: string;
}

export interface ObservacionData {
    id: string;
    status: string;
    categoriaCode: string;
    categoriaDisplay: string;
    codigoCode: string;
    codigoDisplay: string;
    codigoText: string;
    valorString: string;
    valorCode: string;
    valorDisplay: string;
    dispositivo: string;
    efectivaFecha: string;
    patientRef: string;
    componentes: ObservacionComponentData[];
}

export interface RiesgoData {
    id: string;
    status: string;
    codigoCode: string;
    codigoDisplay: string;
    codigoText: string;
    probabilidad: string;
    riesgoDisplay: string;
    fechaRegistro: string;
    patientRef: string;
}

export interface SolicitudMedicamentoData {
    id: string;
    status: string;
    intent: string;
    medicacionCode: string;
    medicacionDisplay: string;
    medicacionSistema: string;
    cantidadValor: string;
    cantidadUnidad: string;
    cantidadCode: string;
    patientRef: string;
    encountRef: string;
    fechaSolicitud: string;
    categoriaCode: string;
    categoriaDisplay: string;
    motivoCode: string;
    motivoDisplay: string;
    viaCode: string;
    viaDisplay: string;
    frecuencia: string;
    duracion: string;
    reportedBoolean: boolean;
}

export interface AdministracionMedicamentoData {
    id: string;
    status: string;
    medicacionCode: string;
    medicacionDisplay: string;
    medicacionSistema: string;
    categoriaCode: string;
    categoriaDisplay: string;
    fechaAdministracion: string;
    dosisValor: string;
    dosisUnidad: string;
    viaCode: string;
    viaDisplay: string;
    frecuencia: string;
}

export interface SolicitudServicioData {
    id: string;
    status: string;
    intent: string;
    codigoCode: string;
    codigoDisplay: string;
    categoriaCode: string;
    categoriaDisplay: string;
    finalidadCode: string;
    finalidadDisplay: string;
    prioridad: string;
    patientRef: string;
    encountRef: string;
    fechaSolicitud: string;
}

export interface DocumentoReferenciaData {
    id: string;
    status: string;
    tipoCode: string;
    tipoDisplay: string;
    patientRef: string;
    fechaCreacion: string;
    contentType: string;
    url: string;
}

/**
 * Parsea un recurso FHIR Observation a un objeto plano.
 * Usado para: Datos demográficos adicionales e Incapacidad (SIPE)
 */
export function parseFhirObservation(rawResource: any): ObservacionData | null {
    if (!rawResource) return null;
    const resource = rawResource?.data ? rawResource.data : rawResource;
    if (resource?.resourceType !== 'Observation') return null;

    const categoria = resource?.category?.[0]?.coding?.[0];
    const codigo = resource?.code?.coding?.[0];

    // El valor puede venir en varios formatos FHIR
    const valorCode = resource?.valueCodeableConcept?.coding?.[0]?.code || '---';
    const valorDisplay = resource?.valueCodeableConcept?.coding?.[0]?.display || resource?.valueCodeableConcept?.text || '---';
    const valorString = resource?.valueString || resource?.valueQuantity?.value?.toString() || '---';

    const componentes: ObservacionComponentData[] = [];
    if (Array.isArray(resource?.component)) {
        resource.component.forEach((comp: any) => {
            const compCodigo = comp.code?.coding?.[0];
            let compValor = '---';
            if (comp.valueCodeableConcept?.coding?.[0]?.display) {
                compValor = comp.valueCodeableConcept.coding[0].display;
            } else if (comp.valueQuantity) {
                compValor = `${comp.valueQuantity.value} ${comp.valueQuantity.unit || ''}`.trim();
            } else if (comp.valueString) {
                compValor = comp.valueString;
            }

            componentes.push({
                codigoCode: compCodigo?.code || '---',
                codigoDisplay: compCodigo?.display || comp.code?.text || '---',
                valor: compValor
            });
        });
    }

    return {
        id: resource?.id || '---',
        status: resource?.status || '---',
        categoriaCode: categoria?.code || '---',
        categoriaDisplay: categoria?.display || '---',
        codigoCode: codigo?.code || '---',
        codigoDisplay: codigo?.display || resource?.code?.text || '---',
        codigoText: resource?.code?.text || '---',
        valorString,
        valorCode,
        valorDisplay,
        dispositivo: resource?.device?.identifier?.value || resource?.device?.display || '---',
        efectivaFecha: resource?.effectiveDateTime || resource?.meta?.lastUpdated || '---',
        patientRef: resource?.subject?.reference || '---',
        componentes
    };
}

/**
 * Parsea un recurso FHIR RiskAssessment a un objeto plano.
 * Usado para: Factores de riesgo
 */
export function parseFhirRiskAssessment(rawResource: any): RiesgoData | null {
    if (!rawResource) return null;
    const resource = rawResource?.data ? rawResource.data : rawResource;
    if (resource?.resourceType !== 'RiskAssessment') return null;

    const codigo = resource?.code?.coding?.[0];
    const prediction = resource?.prediction?.[0];

    return {
        id: resource?.id || '---',
        status: resource?.status || '---',
        codigoCode: codigo?.code || '---',
        codigoDisplay: codigo?.display || '---',
        codigoText: resource?.code?.text || '---',
        probabilidad: prediction?.probabilityDecimal?.toString() || prediction?.probabilityRange?.low?.value?.toString() || '---',
        riesgoDisplay: prediction?.outcome?.coding?.[0]?.display || prediction?.outcome?.text || '---',
        fechaRegistro: resource?.meta?.lastUpdated || '---',
        patientRef: resource?.subject?.reference || '---'
    };
}

/**
 * Parsea un recurso FHIR MedicationRequest a un objeto plano.
 * Usado para: Historial de medicamentos en Consulta Externa
 */
export function parseFhirMedicationRequest(rawResource: any): SolicitudMedicamentoData | null {
    if (!rawResource) return null;
    const resource = rawResource?.data ? rawResource.data : rawResource;
    if (resource?.resourceType !== 'MedicationRequest') return null;

    const medCoding = resource?.medicationCodeableConcept?.coding?.[0];
    const doseInst = resource?.dosageInstruction?.[0];
    const cantidad = doseInst?.doseAndRate?.[0]?.doseQuantity;

    const categoria = resource?.category?.[0]?.coding?.[0];
    const motivo = resource?.reasonCode?.[0]?.coding?.[0];
    const via = doseInst?.route?.coding?.[0];

    const rateQuantity = doseInst?.doseAndRate?.[0]?.rateQuantity;
    const frecuencia = rateQuantity?.value ? `Cada ${rateQuantity.value} ${rateQuantity.unit || ''}` : '---';

    const timing = doseInst?.timing;
    const duracionVal = timing?.repeat?.duration;
    const duracionStr = duracionVal ? `${duracionVal} días (${timing?.code?.coding?.[0]?.display || timing?.repeat?.durationUnit || ''})` : '---';

    return {
        id: resource?.id || '---',
        status: resource?.status || '---',
        intent: resource?.intent || '---',
        medicacionCode: medCoding?.code || '---',
        medicacionDisplay: medCoding?.display || resource?.medicationCodeableConcept?.text || '---',
        medicacionSistema: medCoding?.system || '---',
        cantidadValor: cantidad?.value?.toString() || '---',
        cantidadUnidad: cantidad?.unit || cantidad?.code || '---',
        cantidadCode: cantidad?.code || '---',
        patientRef: resource?.subject?.reference || '---',
        encountRef: resource?.encounter?.reference || '---',
        fechaSolicitud: resource?.authoredOn || '---',
        categoriaCode: categoria?.code || '---',
        categoriaDisplay: categoria?.display || '---',
        motivoCode: motivo?.code || '---',
        motivoDisplay: motivo?.display || '---',
        viaCode: via?.code || '---',
        viaDisplay: via?.display || '---',
        frecuencia,
        duracion: duracionStr,
        reportedBoolean: resource?.reportedBoolean || false
    };
}

/**
 * Parsea un recurso FHIR MedicationAdministration a un objeto plano.
 * Usado para: Administración de Medicamentos
 */
export function parseFhirMedicationAdministration(rawResource: any): AdministracionMedicamentoData | null {
    if (!rawResource) return null;
    const resource = rawResource?.data ? rawResource.data : rawResource;
    if (resource?.resourceType !== 'MedicationAdministration') return null;

    const medCoding = resource?.medicationCodeableConcept?.coding?.[0];
    const categoria = resource?.category?.coding?.[0] || resource?.category?.[0]?.coding?.[0]; // Puede ser objeto o array en FHIR

    const dosage = resource?.dosage;
    const route = dosage?.route?.coding?.[0];
    const dose = dosage?.dose;
    const rate = dosage?.rateQuantity;

    const frecuencia = rate?.value ? `Cada ${rate.value} ${rate.unit || ''}` : '---';

    return {
        id: resource?.id || '---',
        status: resource?.status || '---',
        medicacionCode: medCoding?.code || '---',
        medicacionDisplay: medCoding?.display || resource?.medicationCodeableConcept?.text || '---',
        medicacionSistema: medCoding?.system || '---',
        categoriaCode: categoria?.code || '---',
        categoriaDisplay: categoria?.display || '---',
        fechaAdministracion: resource?.effectiveDateTime || '---',
        dosisValor: dose?.value?.toString() || '---',
        dosisUnidad: dose?.unit || '---',
        viaCode: route?.code || '---',
        viaDisplay: route?.display || '---',
        frecuencia
    };
}

/**
 * Parsea un recurso FHIR ServiceRequest a un objeto plano.
 * Usado para: Órdenes, prescripciones o solicitudes de servicio
 */
export function parseFhirServiceRequest(rawResource: any): SolicitudServicioData | null {
    if (!rawResource) return null;
    const resource = rawResource?.data ? rawResource.data : rawResource;
    if (resource?.resourceType !== 'ServiceRequest') return null;

    const codigo = resource?.code?.coding?.[0];
    const categoria = resource?.category?.[0]?.coding?.[0];
    const finalidad = resource?.reasonCode?.[0]?.coding?.[0];

    return {
        id: resource?.id || '---',
        status: resource?.status || '---',
        intent: resource?.intent || '---',
        codigoCode: codigo?.code || '---',
        codigoDisplay: codigo?.display || resource?.code?.text || '---',
        categoriaCode: categoria?.code || '---',
        categoriaDisplay: categoria?.display || '---',
        finalidadCode: finalidad?.code || '---',
        finalidadDisplay: finalidad?.display || '---',
        prioridad: resource?.priority || '---',
        patientRef: resource?.subject?.reference || '---',
        encountRef: resource?.encounter?.reference || '---',
        fechaSolicitud: resource?.authoredOn || '---'
    };
}

/**
 * Parsea un recurso FHIR DocumentReference a un objeto plano.
 * Usado para: Documentos de soporte
 */
export function parseFhirDocumentReference(rawResource: any): DocumentoReferenciaData | null {
    if (!rawResource) return null;
    const resource = rawResource?.data ? rawResource.data : rawResource;
    if (resource?.resourceType !== 'DocumentReference') return null;

    const tipo = resource?.type?.coding?.[0];
    const content = resource?.content?.[0]?.attachment;
    const contentFormat = resource?.content?.[0]?.format;

    // console.log('resource:', resource);
    // console.log('content:', content);
    // console.log('contentFormat:', contentFormat);

    return {
        id: resource?.id || '---',
        status: resource?.status || '---',
        tipoCode: tipo?.code || '---',
        tipoDisplay: tipo?.display || resource?.type?.text || contentFormat?.display || '---',
        patientRef: resource?.subject?.reference || '---',
        fechaCreacion: resource?.date || resource?.meta?.lastUpdated || '---',
        contentType: content?.contentType || contentFormat?.display || contentFormat?.code || '---',
        url: content?.url || '---'
    };
}

// ─── Documentos Adjuntos (PDF Base64) ────────────────────────────────────────
/**
 * Extrae la cadena Base64 del documento adjunto desde la respuesta del servidor.
 * Parsea el caso FHIR DocumentReference o el caso de binario directo.
 * Compartido entre RDA Consulta, Hospitalización y Urgencias.
 */
export function parseFhirDocumentoAdjunto(rawResource: any): string | null {
    if (!rawResource) return null;
    const resource = rawResource?.data ? rawResource.data : rawResource;

    const base64 = resource?.resourceType === 'DocumentReference' ? resource?.content?.[0]?.attachment?.data : resource?.raw_body && resource?.encoding === 'base64' ? resource.raw_body : null;

    if (!base64) return null;

    // Validación de seguridad:
    // 1. Evitar el dummy del estándar "U01BTExfUERG" (SMALL_PDF)
    // 2. Verificar que empiece con la firma %PDF- (en base64 es "JVBERi0")
    if (base64 === 'U01BTExfUERG' || !base64.startsWith('JVBERi0')) {
        return null;
    }

    return base64;
}
