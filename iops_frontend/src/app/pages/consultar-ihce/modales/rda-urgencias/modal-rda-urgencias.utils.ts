// ============================================================
// Utilidades de Parseo FHIR - Modal RDA Urgencias
// Extiende modal-rda-consulta.utils.ts con los tipos específicos
// de la Composition de Urgencias (Emergency RDA).
// ============================================================

// Re-exportamos todo lo que ya existe en el módulo de consulta
// para no duplicar código. Urgencias comparte la misma estructura
// para Observation, RiskAssessment, MedicationRequest y ServiceRequest.
export type { ObservacionData, ObservacionComponentData, RiesgoData, SolicitudMedicamentoData, AdministracionMedicamentoData, SolicitudServicioData, DocumentoReferenciaData } from '../rda-consulta/modal-rda-consulta.utils';

export { parseFhirObservation, parseFhirRiskAssessment, parseFhirMedicationRequest, parseFhirMedicationAdministration, parseFhirServiceRequest, parseFhirDocumentReference } from '../rda-consulta/modal-rda-consulta.utils';

// ─── Triaje ──────────────────────────────────────────────────────────────────
export interface TriajeData {
    id: string;
    status: string;
    codigoCode: string;
    codigoDisplay: string;
    codigoText: string;
    /** Nivel de Triaje (I, II, III, IV, V) como valueCodeableConcept o componente */
    nivelTriaje: string;
    nivelTriajeDisplay: string;
    /** Color asociado al nivel (para badge visual) */
    colorBadge: string;
    efectivaFecha: string;
    patientRef: string;
}

/** Devuelve color de badge según el nivel de triaje colombiano */
function colorPorNivel(nivel: string): string {
    const map: Record<string, string> = {
        I: '#dc2626',
        '1': '#dc2626',
        '01': '#dc2626', // Rojo
        II: '#ea580c',
        '2': '#ea580c',
        '02': '#ea580c', // Naranja
        III: '#ca8a04',
        '3': '#ca8a04',
        '03': '#ca8a04', // Amarillo
        IV: '#16a34a',
        '4': '#16a34a',
        '04': '#16a34a', // Verde
        V: '#2563eb',
        '5': '#2563eb',
        '05': '#2563eb' // Azul
    };
    return map[nivel?.toUpperCase()] ?? '#6b7280';
}

/**
 * Parsea un recurso FHIR Observation de tipo "Triage note" a un objeto plano.
 * El nivel de triaje puede venir como:
 *   - valueCodeableConcept → coding[0].display
 *   - component con code que incluya "triaje" o "triage"
 */
export function parseFhirTriaje(rawResource: any): TriajeData | null {
    if (!rawResource) return null;
    const resource = rawResource?.data ? rawResource.data : rawResource;
    if (resource?.resourceType !== 'Observation') return null;

    const codigo = resource?.code?.coding?.[0];

    // Intentar extraer nivel desde valueCodeableConcept
    const valCoding = resource?.valueCodeableConcept?.coding?.[0];
    let nivelCode = valCoding?.code || '---';
    let nivelDisplay = valCoding?.display || resource?.valueCodeableConcept?.text || '---';

    // Si no viene en el valor, buscar en componentes
    if (nivelCode === '---' && Array.isArray(resource?.component)) {
        const triageComp = resource.component.find((c: any) => c?.code?.coding?.[0]?.display?.toLowerCase().includes('triai') || c?.code?.text?.toLowerCase().includes('triai'));
        if (triageComp) {
            const tc = triageComp?.valueCodeableConcept?.coding?.[0];
            nivelCode = tc?.code || triageComp?.valueString || '---';
            nivelDisplay = tc?.display || triageComp?.valueCodeableConcept?.text || nivelCode;
        }
    }

    return {
        id: resource?.id || '---',
        status: resource?.status || '---',
        codigoCode: codigo?.code || '---',
        codigoDisplay: codigo?.display || resource?.code?.text || '---',
        codigoText: resource?.code?.text || '---',
        nivelTriaje: nivelCode,
        nivelTriajeDisplay: nivelDisplay,
        colorBadge: colorPorNivel(nivelCode),
        efectivaFecha: resource?.effectiveDateTime || resource?.meta?.lastUpdated || '---',
        patientRef: resource?.subject?.reference || '---'
    };
}

// ─── Procedimientos ──────────────────────────────────────────────────────────
export interface ProcedimientoData {
    id: string;
    status: string;
    codigoCode: string;
    codigoDisplay: string;
    categoriaCode: string;
    categoriaDisplay: string;
    fechaSolicitud: string;
    fechaRealizacion: string;
    finalidadCode: string;
    finalidadDisplay: string;
    pacienteRef: string;
}

export function parseFhirProcedure(rawResource: any): ProcedimientoData | null {
    if (!rawResource) return null;
    const resource = rawResource?.data ? rawResource.data : rawResource;
    if (resource?.resourceType !== 'Procedure') return null;

    const codigo = resource?.code?.coding?.[0];
    const category = resource?.category?.coding?.[0] || resource?.category?.[0]?.coding?.[0];

    const reqDateExt = resource?.extension?.find((e: any) => e.url?.includes('ExtensionRequestDate'));
    const fechaSolicitud = reqDateExt?.valueDate || '---';

    const reason = resource?.reasonCode?.[0]?.coding?.[0];

    return {
        id: resource?.id || '---',
        status: resource?.status || '---',
        codigoCode: codigo?.code || '---',
        codigoDisplay: codigo?.display || resource?.code?.text || '---',
        categoriaCode: category?.code || '---',
        categoriaDisplay: category?.display || '---',
        fechaSolicitud,
        fechaRealizacion: resource?.performedDateTime || resource?.performedPeriod?.start || resource?.meta?.lastUpdated || '---',
        finalidadCode: reason?.code || '---',
        finalidadDisplay: reason?.display || '---',
        pacienteRef: resource?.subject?.reference || '---'
    };
}

// ─── Documentos Adjuntos (PDF Base64) ────────────────────────────────────────
/**
 * Extrae la cadena Base64 del documento adjunto desde la respuesta del servidor.
 * Parsea el caso FHIR DocumentReference o el caso de binario directo.
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
