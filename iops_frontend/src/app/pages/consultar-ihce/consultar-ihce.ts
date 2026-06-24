import { Component, inject, signal, computed, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormsModule, NonNullableFormBuilder, FormGroup, AbstractControl, ValidationErrors, ValidatorFn } from '@angular/forms';
import { forkJoin, of } from 'rxjs';
import { catchError, switchMap } from 'rxjs/operators';
import { DatePickerModule } from 'primeng/datepicker';
import { SelectModule } from 'primeng/select';
import { InputTextModule } from 'primeng/inputtext';
import { ButtonModule } from 'primeng/button';
import { PanelModule } from 'primeng/panel';
import { SkeletonModule } from 'primeng/skeleton';
import { TagModule } from 'primeng/tag';
import { TableModule } from 'primeng/table';
import { BadgeModule } from 'primeng/badge';
import { JsonPipe } from '@angular/common';
import { SessionService } from '../../services/session.service';
import { EnvioIhceService } from '../envio-ihce/envio-ihce.service';
import { LayoutService } from '../../layout/service/layout.service';
import { ConsultarIhceService, RdaAuditPayload } from './consultar-ihce.service';
import { DialogService } from 'primeng/dynamicdialog';
import { ModalRdaPacienteComponent } from './modales/rda-paciente/modal-rda-paciente';
import { ModalRdaConsultaComponent } from './modales/rda-consulta/modal-rda-consulta';
import { ModalRdaUrgenciasComponent } from './modales/rda-urgencias/modal-rda-urgencias';
import { ModalRdaHospitalizacionComponent } from './modales/rda-hospitalizacion/modal-rda-hospitalizacion';

// Validador: ambas fechas deben ir juntas o ninguna
const dateRangeValidator: ValidatorFn = (control: AbstractControl): ValidationErrors | null => {
    const fechaInicio = control.get('fechaInicio')?.value;
    const fechaFin = control.get('fechaFin')?.value;
    if ((fechaInicio && !fechaFin) || (!fechaInicio && fechaFin)) {
        return { dateRangeIncomplete: true };
    }
    return null;
};

// Validador: tipo de documento y número deben ir juntos
const documentPairValidator: ValidatorFn = (control: AbstractControl): ValidationErrors | null => {
    const tipoDocumento = control.get('tipoDocumento')?.value;
    const documento = control.get('documento')?.value;
    if ((tipoDocumento && !documento) || (!tipoDocumento && documento)) {
        return { documentPairIncomplete: true };
    }
    return null;
};

@Component({
    selector: 'app-consultar-ihce',
    standalone: true,
    imports: [CommonModule, ReactiveFormsModule, FormsModule, DatePickerModule, SelectModule, InputTextModule, ButtonModule, PanelModule, SkeletonModule, TagModule, TableModule, BadgeModule, JsonPipe],
    providers: [DialogService],
    templateUrl: './consultar-ihce.html',
    styleUrl: './consultar-ihce.scss'
})
export class ConsultarIhce implements OnInit {
    private fb = inject(NonNullableFormBuilder);
    private sessionService = inject(SessionService);
    private envioIhceService = inject(EnvioIhceService);
    private layoutService = inject(LayoutService);
    private consultarIhceService = inject(ConsultarIhceService);
    private dialogService = inject(DialogService);

    // Datos de sesión disponibles en la vista
    public sessionData = this.sessionService.sessionData;

    // Catálogos
    tiposDocumento = signal<any[]>([]);

    // Estado de validación del usuario (credenciales propias)
    usuarioValidoParaConsultar = signal<boolean>(true);
    mensajeErrorUsuario = signal<string>('');

    // Estado general
    isLoading = signal<boolean>(false);
    busquedaRealizada = signal<boolean>(false);

    // Estado individual por sección
    isLoadingPaciente = signal<boolean>(false);
    isLoadingVacunacion = signal<boolean>(false);
    isLoadingEncuentros = signal<boolean>(false);
    isLoadingAntecedentes = signal<boolean>(false);

    // Datos por sección (null = no buscado, [] = sin resultados, [...] = con datos)
    datosPaciente = signal<any[] | null>(null);
    datosVacunacion = signal<any[] | null>(null);
    datosEncuentros = signal<any[] | null>(null);
    datosAntecedentes = signal<any[] | null>(null);

    // Filtro para la tabla de Encuentros Clínicos
    filtroEncuentros = signal<string>('Todos');
    opcionesFiltroEncuentros = [
        { label: 'Todos', value: 'Todos', icon: 'pi pi-list' },
        { label: 'Consulta externa', value: 'Consulta', icon: 'pi pi-user' },
        { label: 'Hospitalización', value: 'Hospitalización', icon: 'pi pi-building' },
        { label: 'Urgencias', value: 'Urgencias', icon: 'pi pi-exclamation-circle' }
    ];

    // Lista de encuentros filtrada reactivamente
    datosEncuentrosFiltrados = computed(() => {
        const todos = this.datosEncuentros() ?? [];
        const filtro = this.filtroEncuentros();
        // console.log('Encuentros filtrados:', todos);
        // console.log('Filtro:', filtro);
        if (filtro === 'Todos') return todos;
        return todos.filter((e: any) => {
            const titulo: string = (e.resource?.title || '').toLowerCase();
            return titulo.includes(filtro.toLowerCase());
        });
    });

    // Modales movidos a componentes independientes
    maxDateValue = new Date();

    // Design tokens para la cabecera azul del p-panel principal (buscador)
    panelTokens = {
        header: {
            background: '#2563ab',
            color: '#ffffff',
            borderColor: '#2563ab'
        }
    };

    form: FormGroup = this.fb.group(
        {
            tipoDocumento: [null],
            documento: ['']
        },
        { validators: [documentPairValidator] }
    );

    get f() {
        return this.form.controls;
    }

    ngOnInit() {
        this.cargarCatalogos();
        this.asignarValoresSesion();

        // Hay que quitarlo

        // PT - 5727972
        // RC - 1232832630
        // TI - 1108337161  --> todos

        // RDA HOSPITALIZACION: TI-1150686833
        // RDA CONSULTA: TI-1111699362
        // RDA Urgencias: PT-7581315

        // this.form.patchValue({
        //   tipoDocumento: 'TI',
        //   documento: '1108337161'
        // });
    }

    asignarValoresSesion() {
        const data = this.sessionData();
        // console.log('data::', data);

        // Validación de credenciales del usuario logueado
        if (!data?.tipo_documento_us || !data?.documento_us) {
            this.usuarioValidoParaConsultar.set(false);
            this.mensajeErrorUsuario.set('El usuario no cuenta con los tipo de documento y el documento para poder consultar las IHCE');
            return; // Detener flujo si no hay credenciales de usuario
        } else {
            this.usuarioValidoParaConsultar.set(true);
            this.mensajeErrorUsuario.set('');
        }

        // Lógica para asignar datos del PACIENTE a consultar
        if (data && data.TIPO_ID && data.ID) {
            this.form.patchValue({
                tipoDocumento: data.TIPO_ID,
                documento: data.ID
            });
            // Búsqueda automática si los datos del paciente están completos
            this.buscar();
        } else if (data) {
            if (data.TIPO_ID) {
                this.form.patchValue({ tipoDocumento: data.TIPO_ID });
            }
            if (data.ID) {
                this.form.patchValue({ documento: data.ID });
            }
        }
    }

    cargarCatalogos() {
        this.isLoading.set(true);

        forkJoin({
            tiposDocId: this.envioIhceService.getTiposIdPacientes().pipe(
                catchError((error) => {
                    console.error('Error cargando Tipos ID Paciente', error);
                    return of([]);
                })
            )
        }).subscribe({
            next: (responses: any) => {
                const defaultOption = { label: '-- SELECCIONE --', value: null };

                const tiposDocMapped = (responses.tiposDocId?.data || responses.tiposDocId || []).map((item: any) => ({
                    label: item.descripcion || item.label,
                    value: item.tipoId || item.value
                }));

                this.tiposDocumento.set([defaultOption, ...tiposDocMapped]);
                this.isLoading.set(false);
            },
            error: (err) => {
                console.error('Error general en forkJoin:', err);
                this.isLoading.set(false);
            }
        });
    }

    buscar() {
        if (this.form.valid && !this.isFormEmpty()) {
            const rawValue = this.form.getRawValue();
            const payload = {
                tipoDocumento: rawValue.tipoDocumento || null,
                documento: rawValue.documento || null
            };
            // console.log('Buscando con payload:', payload);

            // Activar sección de resultados y simular estado de carga
            this.busquedaRealizada.set(true);
            this.layoutService.cerrarMenu(); // Colapsar el menú al mostrar resultados
            this.iniciarCargaSecciones();

            // Conectar paciente con su endpoint correspondiente
            this.cargarInfoPaciente(payload);
            this.cargarEncuentros(payload);
            this.cargarAntecedentes(payload);
        } else {
            this.form.markAllAsTouched();
        }
    }

    /** Activa el estado de carga en todas las secciones al iniciar una búsqueda */
    private iniciarCargaSecciones() {
        this.isLoadingPaciente.set(true);
        this.isLoadingVacunacion.set(true);
        this.isLoadingEncuentros.set(true);
        this.isLoadingAntecedentes.set(true);

        // Simulación temporal para Vacunación (aún sin endpoint)
        setTimeout(() => {
            this.datosVacunacion.set([]);
            this.isLoadingVacunacion.set(false);
        }, 1000);
    }

    private cargarInfoPaciente(payload: any) {
        const sessionData = this.sessionData();
        const requestPayload = {
            tipo_documento: payload.tipoDocumento,
            numero_documento: payload.documento,
            tipo_doc_usuario: sessionData?.tipo_documento_us || '',
            numero_doc_usuario: sessionData?.documento_us || ''
        };

        this.consultarIhceService
            .consultarPacienteExacto(requestPayload)
            .pipe(
                switchMap((response: any) => {
                    // El backend Laravel siempre envuelve en { status, success, message, data }
                    // El Ministerio devuelve un Patient individual o un Bundle en response.data
                    const fhirPayload = response?.data ?? response;
                    const patients = this.extractPatients(fhirPayload);

                    if (patients.length === 0) {
                        console.info('[IHCE] paciente-exacto sin resultados. Consultando paciente-similar...');
                        return this.consultarIhceService.consultarPacienteSimilar(requestPayload);
                    }

                    // Ya tiene datos: emitir con el marcador interno para saltarse la normalización
                    return of({ _patients: patients });
                }),
                catchError((error) => {
                    console.warn('[IHCE] Error en paciente-exacto, intentando paciente-similar...', error);
                    return this.consultarIhceService.consultarPacienteSimilar(requestPayload).pipe(
                        catchError((err2) => {
                            console.error('[IHCE] Error también en paciente-similar:', err2);
                            return of(null);
                        })
                    );
                })
            )
            .subscribe({
                next: (response: any) => {
                    if (response?._patients) {
                        // Vienen del shortcut de paciente-exacto
                        this.datosPaciente.set(response._patients);
                        this.isLoadingPaciente.set(false);
                        return;
                    }

                    // Normalizar respuesta de paciente-similar (mismo wrapper Laravel)
                    const fhirPayload = response?.data ?? response;
                    const patients = this.extractPatients(fhirPayload);
                    this.datosPaciente.set(patients);
                    this.isLoadingPaciente.set(false);
                },
                error: (error) => {
                    console.error('[IHCE] Error definitivo al cargar info de paciente', error);
                    this.datosPaciente.set([]);
                    this.isLoadingPaciente.set(false);
                }
            });
    }

    /**
     * Normaliza cualquier estructura FHIR que pueda contener recursos Patient:
     *  - Bundle  → extrae entry[*].resource donde resourceType === 'Patient'
     *  - Patient → lo envuelve directamente en array
     *  - Array   → lo filtra para Patient resources
     */
    private extractPatients(fhirPayload: any): any[] {
        if (!fhirPayload) return [];

        // Caso: Bundle (exacto o similar devuelven Bundle searchset)
        if (fhirPayload.resourceType === 'Bundle' && Array.isArray(fhirPayload.entry)) {
            return fhirPayload.entry.map((e: any) => e?.resource).filter((r: any) => r?.resourceType === 'Patient' || r != null);
        }

        // Caso: recurso Patient directo
        if (fhirPayload.resourceType === 'Patient') {
            return [fhirPayload];
        }

        // Caso: ya es un array (edge case)
        if (Array.isArray(fhirPayload)) {
            return fhirPayload.filter(Boolean);
        }

        return [];
    }

    private cargarAntecedentes(payload: any) {
        const sessionData = this.sessionData();
        const requestPayload = {
            tipo_documento: payload.tipoDocumento,
            numero_documento: payload.documento,
            tipo_doc_usuario: sessionData?.tipo_documento_us || '',
            numero_doc_usuario: sessionData?.documento_us || ''
        };

        // console.log('Realizando consulta a rda-paciente con payload:', requestPayload);

        this.consultarIhceService.consultarRdaPaciente(requestPayload).subscribe({
            next: (response: any) => {
                // console.log('Respuesta cruda rda-paciente:', response);
                let entries: any[] = [];

                // Función recursiva para buscar "entry" en el objeto sin crashear
                const findEntry = (obj: any): any[] | null => {
                    if (!obj || typeof obj !== 'object') return null;
                    if (Array.isArray(obj.entry)) return obj.entry;
                    if (obj.data && Array.isArray(obj.data.entry)) return obj.data.entry;

                    for (let key in obj) {
                        if (obj[key] && typeof obj[key] === 'object') {
                            if (Array.isArray(obj[key].entry)) return obj[key].entry;
                        }
                    }
                    return null;
                };

                let parsedData = response;
                if (typeof response === 'string') {
                    try {
                        parsedData = JSON.parse(response);
                    } catch (e) {}
                }

                let extractedEntries = findEntry(parsedData);

                if (extractedEntries) {
                    entries = extractedEntries;
                } else if (Array.isArray(parsedData)) {
                    entries = parsedData;
                } else if (parsedData?.data && Array.isArray(parsedData.data)) {
                    entries = parsedData.data;
                }

                // console.log('Entries extraídos:', entries);

                this.datosAntecedentes.set(entries);
                this.isLoadingAntecedentes.set(false);
            },
            error: (error) => {
                console.error('Error al cargar antecedentes (rda-paciente)', error);
                this.datosAntecedentes.set([]);
                this.isLoadingAntecedentes.set(false);
            }
        });
    }

    private cargarEncuentros(payload: any) {
        this.filtroEncuentros.set('Todos');
        const sessionData = this.sessionData();
        const requestPayload = {
            tipo_documento: payload.tipoDocumento,
            numero_documento: payload.documento,
            tipo_doc_usuario: sessionData?.tipo_documento_us || '',
            numero_doc_usuario: sessionData?.documento_us || ''
        };

        this.consultarIhceService.consultarRdaEncuentros(requestPayload).subscribe({
            next: (response: any) => {
                // console.log('Respuesta cruda rda-encuentros-clinicos-fechas:', response);
                let entries: any[] = [];

                const findEntry = (obj: any): any[] | null => {
                    if (!obj || typeof obj !== 'object') return null;
                    if (Array.isArray(obj.entry)) return obj.entry;
                    if (obj.data && Array.isArray(obj.data.entry)) return obj.data.entry;
                    for (let key in obj) {
                        if (obj[key] && typeof obj[key] === 'object') {
                            if (Array.isArray(obj[key].entry)) return obj[key].entry;
                        }
                    }
                    return null;
                };

                let parsedData = response;
                if (typeof response === 'string') {
                    try {
                        parsedData = JSON.parse(response);
                    } catch (e) {}
                }

                const extracted = findEntry(parsedData);
                if (extracted) {
                    entries = extracted;
                } else if (Array.isArray(parsedData)) {
                    entries = parsedData;
                } else if (parsedData?.data && Array.isArray(parsedData.data)) {
                    entries = parsedData.data;
                }

                // console.log('Encuentros extraídos:', entries);
                this.datosEncuentros.set(entries);
                this.isLoadingEncuentros.set(false);
            },
            error: (error) => {
                console.error('Error al cargar encuentros clínicos', error);
                this.datosEncuentros.set([]);
                this.isLoadingEncuentros.set(false);
            }
        });
    }

    seleccionarEncuentro(fila: any) {
        const title = (fila?.resource?.title || '').toLowerCase();

        // Determinar tipo_rda_id según la tabla ihce.ihce_cat_tipos_rda
        // 1=RDA_PACIENTE | 2=RDA_URGENCIAS | 3=RDA_CONSULTA_EXTERNA | 4=RDA_HOSPITALIZACION
        let tipoRdaId = 3; // Por defecto: Consulta Externa
        if (title.includes('urgencia')) tipoRdaId = 2;
        else if (title.includes('hospitalizaci')) tipoRdaId = 4;

        this.dispararAuditoriaRda(fila, tipoRdaId);

        if (title.includes('urgencia')) {
            this.dialogService.open(ModalRdaUrgenciasComponent, { header: 'Detalle de Urgencias', width: '100vw', height: '100vh', modal: true, maximizable: true, closable: true, closeOnEscape: true, styleClass: 'rda-modal', data: { fila: fila } });
        } else if (title.includes('hospitalizaci')) {
            this.dialogService.open(ModalRdaHospitalizacionComponent, {
                header: 'Detalle de Hospitalización',
                width: '100vw',
                height: '100vh',
                modal: true,
                maximizable: true,
                closable: true,
                closeOnEscape: true,
                styleClass: 'rda-modal',
                data: { fila: fila }
            });
        } else {
            this.dialogService.open(ModalRdaConsultaComponent, {
                header: 'Detalle de Consulta Externa',
                width: '100vw',
                height: '100vh',
                modal: true,
                maximizable: true,
                closable: true,
                closeOnEscape: true,
                styleClass: 'rda-modal',
                data: { fila: fila }
            });
        }
    }

    seleccionarRda(item: any) {
        this.dispararAuditoriaRda(item, 1); // 1 = RDA_PACIENTE
        this.dialogService.open(ModalRdaPacienteComponent, {
            header: 'Detalle del Resumen de Atención en Salud',
            width: '100vw',
            height: '100vh',
            modal: true,
            maximizable: true,
            closable: true,
            closeOnEscape: true,
            styleClass: 'rda-modal',
            data: { fila: item }
        });
    }

    /**
     * Construye y envía el registro de auditoría de visualización de un documento RDA.
     * Método reutilizable por seleccionarEncuentro() y seleccionarRda().
     *
     * @param fila   Entrada del Bundle FHIR que contiene el recurso Composition.
     * @param tipoRdaId  ID del tipo de RDA según ihce.ihce_cat_tipos_rda.
     */
    private dispararAuditoriaRda(fila: any, tipoRdaId: number): void {
        try {
            const session = this.sessionData();

            // console.log('Session:', session);

            // Extraer user_id desde la sesión
            const userId: number = session?.usuario_id ?? session?.user_id ?? session?.id ?? session?.US ?? 0;

            // Extraer el ID del Composition desde la entrada del Bundle FHIR
            // Estructura: fila.resource.id (si ya es el recurso) o buscar en fila.entry[]
            let rdaId = fila?.resource?.id || '';
            if (!rdaId && Array.isArray(fila?.entry)) {
                const composition = fila.entry.find((e: any) => e?.resource?.resourceType === 'Composition');
                rdaId = composition?.resource?.id || '';
            }

            // Datos del paciente consultado (vienen del formulario activo)
            const formVal = this.form.getRawValue();

            const payload: RdaAuditPayload = {
                user_id: userId,
                patient_document_type: formVal.tipoDocumento || '',
                patient_document_number: formVal.documento || '',
                tipo_rda_id: tipoRdaId,
                rda_id: 'Composition/' + rdaId
            };

            this.consultarIhceService.registrarAuditoriaRda(payload);
        } catch (e) {
            // La auditoría nunca debe interrumpir el flujo del médico
            console.warn('[Auditoría RDA] Error al construir el payload:', e);
        }
    }

    borrarCasillas() {
        this.form.reset();
        this.busquedaRealizada.set(false);
        this.datosPaciente.set(null);
        this.datosVacunacion.set(null);
        this.datosEncuentros.set(null);
        this.datosAntecedentes.set(null);
    }

    isFormEmpty(): boolean {
        const rawValue = this.form.getRawValue();
        return Object.values(rawValue).every((val) => val === null || val === '');
    }
}
