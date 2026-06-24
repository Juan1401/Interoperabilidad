import { Component, inject, OnInit, signal, ChangeDetectorRef } from '@angular/core';
import { CommonModule, DatePipe, TitleCasePipe } from '@angular/common';
import { DynamicDialogConfig, DynamicDialogRef } from 'primeng/dynamicdialog';
import { ButtonModule } from 'primeng/button';
import { TooltipModule } from 'primeng/tooltip';
import { PdfViewerModule } from 'ng2-pdf-viewer';
import { ConsultarIhceService, DocumentoExternoResponse } from '../../consultar-ihce.service';
import {
    parseFhirCondition,
    parseFhirAllergyIntolerance,
    parseFhirEncounter,
    parseFhirPatient,
    parseFhirCustodian,
    parseFhirPractitioner,
    ConditionData,
    AllergyIntoleranceData,
    EncounterData,
    PatientData,
    CustodianData,
    PractitionerData
} from '../../consultar-ihce.utils';
import {
    parseFhirObservation,
    parseFhirRiskAssessment,
    parseFhirMedicationRequest,
    parseFhirServiceRequest,
    parseFhirDocumentReference,
    parseFhirDocumentoAdjunto,
    ObservacionData,
    RiesgoData,
    SolicitudMedicamentoData,
    SolicitudServicioData,
    DocumentoReferenciaData
} from './modal-rda-consulta.utils';

// Códigos LOINC de las secciones de la Composition Ambulatoria
const SEC_ENTIDAD_PAGADORA = '48768-6';
const SEC_DEMOGRAFICOS = '74208-0';
const SEC_INCAPACIDAD = '105583-9';
const SEC_DIAGNOSTICOS = '11450-4';
const SEC_ALERGIAS = '48765-2';
const SEC_FACTORES_RIESGO = '75492-9';
const SEC_MEDICAMENTOS = '10160-0';
const SEC_SOLICITUDES = '61146-1';
const SEC_DOCUMENTOS = '55107-7';

@Component({
    selector: 'app-modal-rda-consulta',
    standalone: true,
    imports: [CommonModule, DatePipe, TitleCasePipe, ButtonModule, TooltipModule, PdfViewerModule],
    templateUrl: './modal-rda-consulta.html',
    styleUrl: './modal-rda-consulta.scss'
})
export class ModalRdaConsultaComponent implements OnInit {
    private config = inject(DynamicDialogConfig);
    private ref = inject(DynamicDialogRef);
    private consultarIhceService = inject(ConsultarIhceService);
    private cdr = inject(ChangeDetectorRef);

    // Composición completa recibida del padre
    datosEncuentro = signal<any>(null);

    // ── Señales de datos parseados ────────────────────────────────────────────
    conditionParsedData = signal<ConditionData[]>([]);
    isLoadingConditionData = signal(false);

    allergyParsedData = signal<AllergyIntoleranceData[]>([]);
    isLoadingAllergyData = signal(false);

    demograficosParsedData = signal<ObservacionData[]>([]);
    isLoadingDemograficos = signal(false);

    incapacidadParsedData = signal<ObservacionData[]>([]);
    isLoadingIncapacidad = signal(false);

    encuentroParsedData = signal<EncounterData[]>([]);
    isLoadingEncuentro = signal(false);

    pacienteParsedData = signal<PatientData[]>([]);
    isLoadingPaciente = signal(false);

    prestadoraParsedData = signal<CustodianData[]>([]);
    isLoadingPrestadora = signal(false);

    epsParsedData = signal<CustodianData[]>([]);
    isLoadingEps = signal(false);

    profesionalParsedData = signal<PractitionerData[]>([]);
    isLoadingProfesional = signal(false);

    riesgoParsedData = signal<RiesgoData[]>([]);
    isLoadingRiesgo = signal(false);

    medicamentoParsedData = signal<SolicitudMedicamentoData[]>([]);
    isLoadingMedicamento = signal(false);

    solicitudParsedData = signal<SolicitudServicioData[]>([]);
    isLoadingSolicitud = signal(false);

    documentoParsedData = signal<DocumentoReferenciaData[]>([]);
    isLoadingDocumento = signal(false);

    // ── Señales para el documento externo (proxy gateway) ────────────────────
    documentoExternoData = signal<DocumentoExternoResponse | null>(null);
    isLoadingDocumentoExterno = signal(false);
    documentoExternoError = signal<string | null>(null);
    pdfBytes = signal<Uint8Array | null>(null);
    pdfTitulo = signal<string>('Documento adjunto');
    isVisorFullscreen = signal<boolean>(false);
    // ─────────────────────────────────────────────────────────────────────────

    ngOnInit() {
        const fila = this.config.data?.fila;
        if (fila) {
            this.datosEncuentro.set(fila);
            this.cargarDetalle(fila);
        }
    }

    cargarDetalle(data: any) {
        // Extraer las referencias de cada sección por código LOINC
        const refs: Record<string, string[]> = {
            entidadPagadora: [],
            demograficos: [],
            incapacidad: [],
            diagnosticos: [],
            alergias: [],
            factoresRiesgo: [],
            medicamentos: [],
            solicitudes: [],
            documentos: []
        };

        const sections: any[] = data.resource?.section || [];
        sections.forEach((sec: any) => {
            const code = sec.code?.coding?.[0]?.code;
            const entries: string[] = (sec.entry || []).map((e: any) => e?.reference).filter(Boolean);

            if (code === SEC_ENTIDAD_PAGADORA) refs['entidadPagadora'] = entries;
            else if (code === SEC_DEMOGRAFICOS) refs['demograficos'] = entries;
            else if (code === SEC_INCAPACIDAD) refs['incapacidad'] = entries;
            else if (code === SEC_DIAGNOSTICOS) refs['diagnosticos'] = entries;
            else if (code === SEC_ALERGIAS) refs['alergias'] = entries;
            else if (code === SEC_FACTORES_RIESGO) refs['factoresRiesgo'] = entries;
            else if (code === SEC_MEDICAMENTOS) refs['medicamentos'] = entries;
            else if (code === SEC_SOLICITUDES) refs['solicitudes'] = entries;
            else if (code === SEC_DOCUMENTOS) refs['documentos'] = entries;
        });

        const resource = data.resource || data;

        const encRef = resource?.encounter?.reference;
        if (encRef) refs['encuentro'] = [encRef];

        const patRef = resource?.subject?.reference;
        if (patRef) refs['paciente'] = [patRef];

        const orgRef = resource?.author?.[0]?.reference;
        if (orgRef) refs['prestadora'] = [orgRef];

        const pracRef = resource?.attester?.[0]?.party?.reference;
        if (pracRef) refs['profesional'] = [pracRef];

        // Helper genérico para cargar un array de referencias y parsearlas
        const cargarRefs = <T>(refList: string[], loadingSignal: ReturnType<typeof signal<boolean>>, dataSignal: ReturnType<typeof signal<T[]>>, parserFn: (raw: any) => T | null) => {
            if (!refList || !refList.length) return;
            loadingSignal.set(true);
            const results: T[] = [];
            let pending = refList.length;

            refList.forEach((ref) => {
                this.consultarIhceService.consultarRecurso(ref).subscribe({
                    next: (response) => {
                        const fhir = response?.data ?? response;
                        const parsed = parserFn(fhir);
                        if (parsed) results.push(parsed);
                        if (--pending === 0) {
                            dataSignal.set(results);
                            loadingSignal.set(false);
                        }
                    },
                    error: () => {
                        if (--pending === 0) {
                            dataSignal.set(results);
                            loadingSignal.set(false);
                        }
                    }
                });
            });
        };

        // Encuentro Clínico
        cargarRefs(refs['encuentro'], this.isLoadingEncuentro, this.encuentroParsedData, parseFhirEncounter);

        // Datos del Paciente
        cargarRefs(refs['paciente'], this.isLoadingPaciente, this.pacienteParsedData, parseFhirPatient);

        // Organización prestadora de salud (author)
        cargarRefs(refs['prestadora'], this.isLoadingPrestadora, this.prestadoraParsedData, parseFhirCustodian);

        // EPS (Entidad pagadora)
        cargarRefs(refs['entidadPagadora'], this.isLoadingEps, this.epsParsedData, parseFhirCustodian);

        // Profesional de la Salud (attester)
        cargarRefs(refs['profesional'], this.isLoadingProfesional, this.profesionalParsedData, parseFhirPractitioner);

        // Diagnósticos / Condiciones
        cargarRefs(refs['diagnosticos'], this.isLoadingConditionData, this.conditionParsedData, parseFhirCondition);

        // Alergias
        cargarRefs(refs['alergias'], this.isLoadingAllergyData, this.allergyParsedData, parseFhirAllergyIntolerance);

        // Datos demográficos adicionales
        cargarRefs(refs['demograficos'], this.isLoadingDemograficos, this.demograficosParsedData, parseFhirObservation);

        // Incapacidad (SIPE)
        cargarRefs(refs['incapacidad'], this.isLoadingIncapacidad, this.incapacidadParsedData, parseFhirObservation);

        // Factores de riesgo
        cargarRefs(refs['factoresRiesgo'], this.isLoadingRiesgo, this.riesgoParsedData, parseFhirRiskAssessment);

        // Medicamentos solicitados
        cargarRefs(refs['medicamentos'], this.isLoadingMedicamento, this.medicamentoParsedData, parseFhirMedicationRequest);

        // Órdenes / Solicitudes de servicio
        cargarRefs(refs['solicitudes'], this.isLoadingSolicitud, this.solicitudParsedData, parseFhirServiceRequest);

        // Documentos de soporte
        cargarRefs(refs['documentos'], this.isLoadingDocumento, this.documentoParsedData, parseFhirDocumentReference);
    }

    cerrar() {
        this.ref.close();
    }

    procesarDocumento(event: Event, url: string, titulo: string = 'Documento adjunto'): void {
        event.preventDefault();
        event.stopPropagation();
        if (this.isLoadingDocumentoExterno()) return;

        this.documentoExternoData.set(null);
        this.documentoExternoError.set(null);
        this.pdfBytes.set(null);
        this.pdfTitulo.set(titulo);
        this.isLoadingDocumentoExterno.set(true);
        this.cdr.detectChanges();

        this.consultarIhceService.consultarDocumentoExterno(url).subscribe({
            next: (response) => {
                this.documentoExternoData.set(response);
                const base64Data = parseFhirDocumentoAdjunto(response);
                if (base64Data) {
                    this.pdfBytes.set(this.base64AUint8Array(base64Data));
                } else {
                    this.documentoExternoError.set('El documento devuelto no contiene datos válidos.');
                }
                this.isLoadingDocumentoExterno.set(false);
                this.cdr.detectChanges();
            },
            error: (err) => {
                const mensaje = err?.error?.message ?? err?.message ?? 'Error desconocido al obtener el documento.';
                this.documentoExternoError.set(mensaje);
                this.isLoadingDocumentoExterno.set(false);
                this.cdr.detectChanges();
            }
        });
    }

    cerrarVisorPdf(): void {
        this.pdfBytes.set(null);
        this.documentoExternoData.set(null);
        this.documentoExternoError.set(null);
        this.pdfTitulo.set('Documento adjunto');
        this.isVisorFullscreen.set(false);
    }

    toggleVisorFullscreen(): void {
        this.isVisorFullscreen.update((v) => !v);
    }

    onPdfError(error: any): void {
        console.error('[PDF Viewer Error]', error);
        this.pdfBytes.set(null); // Ocultar el visor si el PDF es inválido
        this.documentoExternoError.set('El documento PDF tiene una estructura inválida o está corrupto y no se puede visualizar.');
    }

    private base64AUint8Array(base64: string): Uint8Array {
        const base64Limpio = base64.includes(',') ? base64.split(',')[1] : base64;
        const binario = atob(base64Limpio);
        const bytes = new Uint8Array(binario.length);
        for (let i = 0; i < binario.length; i++) {
            bytes[i] = binario.charCodeAt(i);
        }
        return bytes;
    }
}
