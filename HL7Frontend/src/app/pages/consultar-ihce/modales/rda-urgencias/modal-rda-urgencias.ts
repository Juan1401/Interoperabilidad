import { Component, inject, OnInit, signal, effect, ChangeDetectorRef, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule, DatePipe, TitleCasePipe } from '@angular/common';
import { DynamicDialogConfig, DynamicDialogRef } from 'primeng/dynamicdialog';
import { ButtonModule } from 'primeng/button';
import { TooltipModule } from 'primeng/tooltip';
import { PdfViewerModule } from 'ng2-pdf-viewer';
import { ConsultarIhceService, DocumentoExternoResponse, DocumentoBinarioData } from '../../consultar-ihce.service';
import { SafeDatePipe } from '../../../../shared/pipes/safe-date.pipe';
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
  PractitionerData,
} from '../../consultar-ihce.utils';
import {
  parseFhirObservation,
  parseFhirRiskAssessment,
  parseFhirMedicationRequest,
  parseFhirServiceRequest,
  parseFhirDocumentReference,
  ObservacionData,
  RiesgoData,
  SolicitudMedicamentoData,
  AdministracionMedicamentoData,
  parseFhirMedicationAdministration,
  SolicitudServicioData,
  DocumentoReferenciaData,
  parseFhirTriaje,
  TriajeData,
  ProcedimientoData,
  parseFhirProcedure,
  parseFhirDocumentoAdjunto,
} from './modal-rda-urgencias.utils';

// Códigos LOINC de las secciones de la Composition de Urgencias
const SEC_ENTIDAD_PAGADORA = '48768-6';
const SEC_DEMOGRAFICOS = '74208-0';
const SEC_INCAPACIDAD = '105583-9';
const SEC_DIAGNOSTICOS = '11450-4';
const SEC_ALERGIAS = '48765-2';
const SEC_FACTORES_RIESGO = '75492-9';
const SEC_MEDICAMENTOS = '10160-0';
const SEC_PROCEDIMIENTOS = '47519-4';
const SEC_RESULTADOS = '30954-2';
const SEC_SOLICITUDES = '61146-1';
const SEC_DOCUMENTOS = '55107-7';
const SEC_TRIAJE = '54094-8';

@Component({
  selector: 'app-modal-rda-urgencias',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    CommonModule,
    DatePipe,
    SafeDatePipe,
    TitleCasePipe,
    ButtonModule,
    TooltipModule,
    PdfViewerModule,
  ],
  templateUrl: './modal-rda-urgencias.html',
  styleUrl: './modal-rda-urgencias.scss',
})
export class ModalRdaUrgenciasComponent implements OnInit {
  private config = inject(DynamicDialogConfig);
  private ref = inject(DynamicDialogRef);
  private consultarIhceService = inject(ConsultarIhceService);
  private cdr = inject(ChangeDetectorRef);

  // Composición completa recibida del padre
  datosEncuentro = signal<any>(null);

  // ── Señales de datos parseados ────────────────────────────────────────────
  encuentroParsedData = signal<EncounterData[]>([]);
  isLoadingEncuentro = signal(false);

  pacienteParsedData = signal<PatientData[]>([]);
  isLoadingPaciente = signal(false);

  prestadoraParsedData = signal<CustodianData[]>([]);
  isLoadingPrestadora = signal(false);

  custodioParsedData = signal<CustodianData[]>([]);
  isLoadingCustodio = signal(false);

  epsParsedData = signal<CustodianData[]>([]);
  isLoadingEps = signal(false);

  profesionalParsedData = signal<PractitionerData[]>([]);
  isLoadingProfesional = signal(false);

  conditionParsedData = signal<ConditionData[]>([]);
  isLoadingConditionData = signal(false);

  allergyParsedData = signal<AllergyIntoleranceData[]>([]);
  isLoadingAllergyData = signal(false);

  incapacidadParsedData = signal<ObservacionData[]>([]);
  isLoadingIncapacidad = signal(false);

  riesgoParsedData = signal<RiesgoData[]>([]);
  isLoadingRiesgo = signal(false);

  medicamentoParsedData = signal<SolicitudMedicamentoData[]>([]);
  isLoadingMedicamento = signal(false);

  adminMedicamentoParsedData = signal<AdministracionMedicamentoData[]>([]);
  isLoadingAdminMedicamento = signal(false);

  procedimientoParsedData = signal<ProcedimientoData[]>([]);
  isLoadingProcedimiento = signal(false);

  resultadoParsedData = signal<ObservacionData[]>([]);
  isLoadingResultado = signal(false);

  demograficosParsedData = signal<ObservacionData[]>([]);
  isLoadingDemograficos = signal(false);

  solicitudParsedData = signal<SolicitudServicioData[]>([]);
  isLoadingSolicitud = signal(false);

  documentoParsedData = signal<DocumentoReferenciaData[]>([]);
  isLoadingDocumento = signal(false);

  triajeParsedData = signal<TriajeData[]>([]);
  isLoadingTriaje = signal(false);

  destinoParsedData = signal<CustodianData | null>(null);
  isLoadingDestino = signal(false);

  // ── Señales para el documento externo (proxy gateway) ────────────────────
  /** Respuesta completa del backend al consultar el documento externo. */
  documentoExternoData = signal<DocumentoExternoResponse | null>(null);
  /** true mientras la petición al endpoint /documento-externo está en vuelo. */
  isLoadingDocumentoExterno = signal(false);
  /** Mensaje de error si la consulta falla, null en caso contrario. */
  documentoExternoError = signal<string | null>(null);
  /**
   * Bytes del PDF decodificado desde Base64 para alimentar al ng2-pdf-viewer.
   * Se establece como Uint8Array para evitar re-decodificaciones en cada render.
   * null = ningún PDF cargado en pantalla.
   */
  pdfBytes = signal<Uint8Array | null>(null);
  /** Nombre descriptivo del documento activo en el visor (ej. tipo del DocumentReference). */
  pdfTitulo = signal<string>('Documento adjunto');
  
  /** Controla si el visor PDF se muestra en pantalla completa. */
  isVisorFullscreen = signal<boolean>(false);
  // ─────────────────────────────────────────────────────────────────────────

  constructor() {
    // Escuchar cambios en el encuentro para cargar la institución de destino si existe
    effect(() => {
      const encuentros = this.encuentroParsedData();
      if (encuentros.length > 0) {
        const destinoRef = encuentros[0].destinoEgreso;
        if (destinoRef && destinoRef !== '---' && !this.destinoParsedData() && !this.isLoadingDestino()) {
          this.cargarDestino(destinoRef);
        }
      }
    });
  }

  ngOnInit() {
    const fila = this.config.data?.fila;
    if (fila) {
      this.datosEncuentro.set(fila);
      this.cargarDetalle(fila);
    }
  }

  cargarDetalle(data: any) {
    // Extraer referencias de cada sección por código LOINC
    const refs: Record<string, string[]> = {
      entidadPagadora: [],
      demograficos: [],
      incapacidad: [],
      diagnosticos: [],
      alergias: [],
      factoresRiesgo: [],
      medicamentos: [],
      procedimientos: [],
      resultados: [],
      solicitudes: [],
      documentos: [],
      triaje: [],
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
      else if (code === SEC_PROCEDIMIENTOS) refs['procedimientos'] = entries;
      else if (code === SEC_RESULTADOS) refs['resultados'] = entries;
      else if (code === SEC_SOLICITUDES) refs['solicitudes'] = entries;
      else if (code === SEC_DOCUMENTOS) refs['documentos'] = entries;
      else if (code === SEC_TRIAJE) refs['triaje'] = entries;
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

    const custRef = resource?.custodian?.reference;
    if (custRef) refs['custodian'] = [custRef];

    // Helper genérico para cargar un array de referencias y parsearlas
    const cargarRefs = <T>(
      refList: string[],
      loadingSignal: ReturnType<typeof signal<boolean>>,
      dataSignal: ReturnType<typeof signal<T[]>>,
      parserFn: (raw: any) => T | null
    ) => {
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
            if (--pending === 0) { dataSignal.set(results); loadingSignal.set(false); }
          },
          error: () => {
            if (--pending === 0) { dataSignal.set(results); loadingSignal.set(false); }
          }
        });
      });
    };

    // Cargar todos los recursos
    cargarRefs(refs['encuentro'], this.isLoadingEncuentro, this.encuentroParsedData, parseFhirEncounter);
    cargarRefs(refs['paciente'], this.isLoadingPaciente, this.pacienteParsedData, parseFhirPatient);
    cargarRefs(refs['prestadora'], this.isLoadingPrestadora, this.prestadoraParsedData, parseFhirCustodian);
    cargarRefs(refs['profesional'], this.isLoadingProfesional, this.profesionalParsedData, parseFhirPractitioner);
    cargarRefs(refs['custodian'], this.isLoadingCustodio, this.custodioParsedData, parseFhirCustodian);

    cargarRefs(refs['entidadPagadora'], this.isLoadingEps, this.epsParsedData, parseFhirCustodian);

    cargarRefs(refs['demograficos'], this.isLoadingDemograficos, this.demograficosParsedData, parseFhirObservation);
    cargarRefs(refs['incapacidad'], this.isLoadingIncapacidad, this.incapacidadParsedData, parseFhirObservation);
    cargarRefs(refs['diagnosticos'], this.isLoadingConditionData, this.conditionParsedData, parseFhirCondition);
    cargarRefs(refs['alergias'], this.isLoadingAllergyData, this.allergyParsedData, parseFhirAllergyIntolerance);
    cargarRefs(refs['factoresRiesgo'], this.isLoadingRiesgo, this.riesgoParsedData, parseFhirRiskAssessment);
    cargarRefs(refs['medicamentos'], this.isLoadingMedicamento, this.medicamentoParsedData, parseFhirMedicationRequest);
    cargarRefs(refs['medicamentos'], this.isLoadingAdminMedicamento, this.adminMedicamentoParsedData, parseFhirMedicationAdministration);
    cargarRefs(refs['procedimientos'], this.isLoadingProcedimiento, this.procedimientoParsedData, parseFhirProcedure);
    cargarRefs(refs['resultados'], this.isLoadingResultado, this.resultadoParsedData, parseFhirObservation);
    cargarRefs(refs['solicitudes'], this.isLoadingSolicitud, this.solicitudParsedData, parseFhirServiceRequest);
    cargarRefs(refs['documentos'], this.isLoadingDocumento, this.documentoParsedData, parseFhirDocumentReference);
    cargarRefs(refs['triaje'], this.isLoadingTriaje, this.triajeParsedData, parseFhirTriaje);
  }

  private cargarDestino(ref: string) {
    this.isLoadingDestino.set(true);
    // console.log('[cargarDestino] Solicitando recurso destino:', ref);
    this.consultarIhceService.consultarRecurso(ref).subscribe({
      next: (response) => {
        const fhir = response?.data ?? response;
        const parsed = parseFhirCustodian(fhir);
        if (parsed) {
          this.destinoParsedData.set(parsed);
          // console.log('[cargarDestino] Destino cargado:', parsed);
        }
        this.isLoadingDestino.set(false);
        this.cdr.detectChanges();
      },
      error: () => {
        this.isLoadingDestino.set(false);
        this.cdr.detectChanges();
      }
    });
  }

  procesarDocumento(event: Event, url: string, titulo: string = 'Documento adjunto'): void {
    event.preventDefault();
    event.stopPropagation();

    // console.log('[procesarDocumento] Click en Ver adjunto. URL:', url, 'Título:', titulo);

    // Evitar peticiones duplicadas si ya hay una en vuelo
    if (this.isLoadingDocumentoExterno()) {
      // console.log('[procesarDocumento] Petición en vuelo, ignorando click.');
      return;
    }

    // Limpiar estado previo: cerrar visor anterior antes de abrir el nuevo
    this.documentoExternoData.set(null);
    this.documentoExternoError.set(null);
    this.pdfBytes.set(null);
    this.pdfTitulo.set(titulo);
    this.isLoadingDocumentoExterno.set(true);
    this.cdr.detectChanges();

    // console.log('[procesarDocumento] Estado seteado a cargando. Ejecutando petición...');

    this.consultarIhceService.consultarDocumentoExterno(url).subscribe({
      next: (response) => {
        this.documentoExternoData.set(response);


        // console.log('response::', response);

        // Usamos la utilidad de parseo para extraer el Base64 (ya sea de FHIR o binario directo)
        const base64Data = parseFhirDocumentoAdjunto(response);

        // console.log('base64Data::', base64Data);
        // return true;

        if (base64Data) {
          // Se decodifica a Uint8Array para alimentar directamente al ng2-pdf-viewer.
          const bytes = this.base64AUint8Array(base64Data);
          this.pdfBytes.set(bytes);
          // console.log('[procesarDocumento] PDF decodificado exitosamente. Tamaño en bytes:', bytes.length);
          // console.log('[procesarDocumento] Primeros bytes:', bytes.slice(0, 10));
        } else {
          // El Ministerio retornó un recurso FHIR en JSON sin contenido PDF o sin formato binario
          console.warn('[procesarDocumento] La respuesta no contiene un binario PDF válido:', response?.data);
          this.documentoExternoError.set('El documento devuelto no contiene datos válidos.');
        }

        this.isLoadingDocumentoExterno.set(false);
        this.cdr.detectChanges();
      },
      error: (err) => {
        const mensaje = err?.error?.message ?? err?.message ?? 'Error desconocido al obtener el documento.';
        this.documentoExternoError.set(mensaje);
        console.error('[procesarDocumento] Error al consultar el documento externo:', mensaje);
        this.isLoadingDocumentoExterno.set(false);
        this.cdr.detectChanges();
      }
    });
  }

  /** Cierra el visor PDF y limpia la memoria ocupada por los bytes del documento. */
  cerrarVisorPdf(): void {
    this.pdfBytes.set(null);
    this.documentoExternoData.set(null);
    this.documentoExternoError.set(null);
    this.pdfTitulo.set('Documento adjunto');
    this.isVisorFullscreen.set(false);
  }

  /** Alterna el visor PDF entre su tamaño normal y pantalla completa. */
  toggleVisorFullscreen(): void {
    this.isVisorFullscreen.update(v => !v);
  }

  onPdfError(error: any): void {
    console.error('[PDF Viewer Error] Error al renderizar el documento PDF:', error);
    this.pdfBytes.set(null); // Ocultar el visor si el PDF es inválido
    this.documentoExternoError.set('El documento PDF tiene una estructura inválida o está corrupto y no se puede visualizar.');
  }

  /**
   * Convierte una cadena Base64 (con o sin prefijo data:...) a Uint8Array.
   * Se elimina el prefijo «data:application/pdf;base64,» si existe,
   * para evitar errores de decodificación en el visor PDF.
   *
   * @param base64 Cadena Base64 recibida del backend.
   * @returns Uint8Array con los bytes binarios del PDF.
   */
  private base64AUint8Array(base64: string): Uint8Array {
    // Limpiar posible prefijo de Data URL (ej. "data:application/pdf;base64,")
    const base64Limpio = base64.includes(',') ? base64.split(',')[1] : base64;
    const binario = atob(base64Limpio);
    const bytes = new Uint8Array(binario.length);
    for (let i = 0; i < binario.length; i++) {
      bytes[i] = binario.charCodeAt(i);
    }
    return bytes;
  }

  cerrar() {
    this.ref.close();
  }
}
