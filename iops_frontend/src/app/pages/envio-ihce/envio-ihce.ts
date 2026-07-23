import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, NonNullableFormBuilder, Validators, FormGroup, AbstractControl, ValidationErrors, ValidatorFn } from '@angular/forms';
import { DatePickerModule } from 'primeng/datepicker';
import { SelectModule } from 'primeng/select';
import { InputTextModule } from 'primeng/inputtext';
import { ButtonModule } from 'primeng/button';
import { TooltipModule } from 'primeng/tooltip';
import { forkJoin, of, from } from 'rxjs';
import { catchError, finalize, concatMap, toArray } from 'rxjs/operators';
import { EnvioIhceService } from './envio-ihce.service';
import { SessionService } from '../../services/session.service';

import { LayoutService } from '../../layout/service/layout.service';
import { FormsModule } from '@angular/forms';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { Dialog, DialogModule } from 'primeng/dialog';
import { FileUploadModule } from 'primeng/fileupload';
import { IconFieldModule } from 'primeng/iconfield';
import { InputIconModule } from 'primeng/inputicon';
import { InputNumberModule } from 'primeng/inputnumber';
import { RadioButtonModule } from 'primeng/radiobutton';
import { RatingModule } from 'primeng/rating';
import { Table, TableModule, TableLazyLoadEvent } from 'primeng/table';
import { TagModule } from 'primeng/tag';
import { ToastModule } from 'primeng/toast';
import { ToolbarModule } from 'primeng/toolbar';
import { MessageService, ConfirmationService } from 'primeng/api';
import { CheckboxModule } from 'primeng/checkbox';
import { ProgressBarModule } from 'primeng/progressbar';
import { AppJsonViewerComponent } from '../../shared/components/json-viewer/app-json-viewer';

const dateRangeValidator: ValidatorFn = (control: AbstractControl): ValidationErrors | null => {
    const fechaInicio = control.get('fechaInicio')?.value;
    const fechaFin = control.get('fechaFin')?.value;

    if ((fechaInicio && !fechaFin) || (!fechaInicio && fechaFin)) {
        return { dateRangeIncomplete: true };
    }
    return null;
};

const documentPairValidator: ValidatorFn = (control: AbstractControl): ValidationErrors | null => {
    const tipoDocumento = control.get('tipoDocumento')?.value;
    const documento = control.get('documento')?.value;

    if ((tipoDocumento && !documento) || (!tipoDocumento && documento)) {
        return { documentPairIncomplete: true };
    }
    return null;
};

@Component({
    selector: 'app-envio-ihce',
    standalone: true,
    imports: [
        CommonModule,
        ReactiveFormsModule,
        DatePickerModule,
        SelectModule,
        InputTextModule,
        ButtonModule,
        TooltipModule,
        ConfirmDialogModule,
        CheckboxModule,
        DialogModule,
        FileUploadModule,
        IconFieldModule,
        InputIconModule,
        InputNumberModule,
        RadioButtonModule,
        RatingModule,
        TableModule,
        TagModule,
        TagModule,
        ToastModule,
        ToolbarModule,
        InputTextModule,
        FormsModule,
        ProgressBarModule,
        AppJsonViewerComponent
    ],
    providers: [MessageService, ConfirmationService],
    templateUrl: './envio-ihce.html',
    styleUrl: './envio-ihce.scss'
})
export class EnvioIhce implements OnInit {
    private fb = inject(NonNullableFormBuilder);
    private envioIhceService = inject(EnvioIhceService);
    private sessionService = inject(SessionService);
    private messageService = inject(MessageService);
    private confirmationService = inject(ConfirmationService);
    private layoutService = inject(LayoutService);

    // Signals para catálogos
    estados = signal<any[]>([]);
    tiposDocumento = signal<any[]>([]);
    atencionesMedicas = signal<any[]>([]);

    // Opciones estáticas para Clase de Atención FHIR
    clasesAtencionFhir = signal<any[]>([
        { label: '-- SELECCIONE --', value: null },
        { label: 'Urgencias (EMER)', value: 'EMER' },
        { label: 'Internación (IMP)', value: 'IMP' },
        { label: 'Consulta Externa (AMB)', value: 'AMB' }
    ]);

    // Signals de estado
    isLoading = signal<boolean>(true);
    filtrosAplicados = signal<any>(null);
    resultadosBusqueda = signal<any>(null);
    totalRecords = signal<number>(0);
    ingresosSeleccionados = signal<any[]>([]);

    // Signals para Envío Masivo
    procesadosMasivo = signal<number>(0);
    totalEnvioMasivo = signal<number>(0);
    resultadosMasivo = signal<{ ingreso: string; estado: string; mensaje: string }[]>([]);
    mostrarResumenMasivo = signal<boolean>(false);

    // ── Signals para el visor de JSON (modal integrado) ─────────────────────
    jsonViewerVisible = signal<boolean>(false);
    jsonViewerTitulo = signal<string>('Log JSON');
    jsonViewerData = signal<any>(null);
    jsonViewerCargando = signal<boolean>(false);
    jsonViewerExpandAll = signal<boolean>(false);
    jsonViewerMaximized = signal<boolean>(true);
    // ────────────────────────────────────────────────────────────────────────

    maxDateValue = new Date();

    form: FormGroup = this.fb.group(
        {
            fechaInicio: [null, Validators.required],
            fechaFin: [null, Validators.required],
            estado: [null],
            tipoDocumento: [null],
            documento: [''],
            noIngreso: [''],
            atencionMedica: [null],
            claseAtencionFhir: [null]
        },
        { validators: [dateRangeValidator, documentPairValidator] }
    );

    get f() {
        return this.form.controls;
    }

    ngOnInit() {
        this.cargarCatalogos();
    }

    cargarCatalogos() {
        this.isLoading.set(true);

        forkJoin({
            tiposDocId: this.envioIhceService.getTiposIdPacientes().pipe(
                catchError((error) => {
                    console.error('Error cargando Tipos ID Paciente', error);
                    return of([]);
                })
            ),
            estadosEnvio: this.envioIhceService.getIhceCatEstadosEnvio().pipe(
                catchError((error) => {
                    console.error('Error cargando Estados', error);
                    return of([]);
                })
            ),
            tiposRda: this.envioIhceService.getIhceCatTiposRda().pipe(
                catchError((error) => {
                    console.error('Error cargando Tipos RDA', error);
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

                const estadosMapped = (responses.estadosEnvio?.data || responses.estadosEnvio || []).map((item: any) => ({
                    label: item.label || item.nombre,
                    value: item.value || item.id
                }));

                const rdaMapped = (responses.tiposRda?.data || responses.tiposRda || []).map((item: any) => ({
                    label: item.nombre || item.label,
                    value: item.id || item.value
                }));

                this.tiposDocumento.set([defaultOption, ...tiposDocMapped]);
                this.estados.set([defaultOption, ...estadosMapped]);
                this.atencionesMedicas.set([defaultOption, ...rdaMapped]);

                this.isLoading.set(false);
            },
            error: (err) => {
                console.error('Error general en forkJoin:', err);
                this.isLoading.set(false);
            }
        });
    }

    private formatFecha(fecha: any): string | null {
        if (!fecha) return null;
        if (fecha instanceof Date) {
            const year = fecha.getFullYear();
            const month = String(fecha.getMonth() + 1).padStart(2, '0');
            const day = String(fecha.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
        return fecha;
    }

    buscar(page: number = 1) {
        if (this.form.valid && !this.isFormEmpty()) {
            // Ocultar el menú lateral al realizar la búsqueda para dar más espacio a la tabla
            if (this.layoutService.isDesktop()) {
                this.layoutService.layoutState.update((prev) => ({ ...prev, staticMenuDesktopInactive: true }));
            } else {
                this.layoutService.layoutState.update((prev) => ({ ...prev, mobileMenuActive: false }));
            }

            const rawValue = this.form.getRawValue();

            // Mapear el payload según lo solicitado: vacíos en null y fechas en formato YYYY-MM-DD
            const payload = {
                fechaInicio: this.formatFecha(rawValue.fechaInicio),
                fechaFin: this.formatFecha(rawValue.fechaFin),
                tipoDocumento: rawValue.tipoDocumento || null,
                documento: rawValue.documento || null,
                estado: rawValue.estado || null,
                noIngreso: rawValue.noIngreso ? Number(rawValue.noIngreso) : null,
                atencionMedica: rawValue.atencionMedica || null,
                claseAtencionFhir: rawValue.claseAtencionFhir || null
            };

            this.filtrosAplicados.set(payload);

            // Enviar datos al Service
            this.envioIhceService.buscarIngresos(payload, page).subscribe({
                next: (response) => {
                    // Limpiar selección previa en cada búsqueda nueva
                    this.ingresosSeleccionados.set([]);

                    if (response?.success || response?.data) {
                        this.resultadosBusqueda.set(response.data || response);
                        this.totalRecords.set(response.meta?.total || 0);
                        console.log('Búsqueda exitosa:', response);
                    } else {
                        console.log('Respuesta finalizada (sin datos correctos):', response);
                        this.resultadosBusqueda.set(null);
                        this.totalRecords.set(0);
                    }
                },
                error: (error) => {
                    console.error('Error durante la búsqueda:', error);
                    this.resultadosBusqueda.set(null);
                    this.totalRecords.set(0);
                }
            });
        } else {
            this.form.markAllAsTouched();
        }
    }

    loadIngresosLazy(event: TableLazyLoadEvent) {
        if (!this.filtrosAplicados()) return; // Prevenir peticiones tempranas antes de buscar manual
        const page = event.first !== undefined && event.rows ? event.first / event.rows + 1 : 1;
        this.buscar(page);
    }

    borrarCasillas() {
        this.form.reset();
        this.filtrosAplicados.set(null);
        this.resultadosBusqueda.set(null);
        this.totalRecords.set(0);
        this.ingresosSeleccionados.set([]);
    }

    // --- Lógica de Selección Manual para Lazy Loading ---

    allAllowedSelected(): boolean {
        const records = this.resultadosBusqueda();
        if (!records || records.length === 0) return false;

        // Filtramos los permitidos en la página actual
        const allowedRecords = records.filter((r: any) => r.estadoEnvioId !== 2);
        if (allowedRecords.length === 0) return false;

        // Comprobamos si todos los permitidos están en el signal
        const selected = this.ingresosSeleccionados();
        return allowedRecords.every((allowed: any) => selected.some((s) => s.ingreso === allowed.ingreso));
    }

    toggleAllAllowed(event: any) {
        const isChecked = event.checked;
        const records = this.resultadosBusqueda() || [];
        const allowedRecords = records.filter((r: any) => r.estadoEnvioId !== 2);

        let currentSelected = [...this.ingresosSeleccionados()];

        if (isChecked) {
            // Agregar los permitidos que no estén ya en el array
            allowedRecords.forEach((allowed: any) => {
                if (!currentSelected.some((s) => s.ingreso === allowed.ingreso)) {
                    currentSelected.push(allowed);
                }
            });
        } else {
            // Remover de la selección los permitidos de ESTA página
            const allowedIngresosIds = allowedRecords.map((r: any) => r.ingreso);
            currentSelected = currentSelected.filter((s) => !allowedIngresosIds.includes(s.ingreso));
        }

        this.ingresosSeleccionados.set(currentSelected);
    }

    enviarSeleccionados() {
        const seleccionados = this.ingresosSeleccionados();
        if (!seleccionados || seleccionados.length === 0) return;

        this.confirmationService.confirm({
            message: `¿Está seguro que desea enviar a procesar los ${seleccionados.length} ingresos seleccionados?`,
            header: 'Confirmar Envío Masivo',
            icon: 'pi pi-exclamation-triangle',
            acceptLabel: 'Sí, enviar todos',
            rejectLabel: 'Cancelar',
            acceptButtonStyleClass: 'p-button-primary',
            rejectButtonStyleClass: 'p-button-secondary p-button-outlined',
            accept: () => {
                // Inicializar Estado Visual
                this.totalEnvioMasivo.set(seleccionados.length);
                this.procesadosMasivo.set(0);
                this.resultadosMasivo.set([]);

                // Bloquear UI con el Headless Toast
                this.messageService.add({
                    key: 'envioMasivo',
                    sticky: true,
                    severity: 'custom',
                    summary: 'Procesando Envíos...',
                    styleClass: 'backdrop-blur-lg rounded-2xl'
                });

                // Obtener el ID del usuario desde la Signal reactiva de sesión
                const usuarioId = this.sessionService.sessionData()?.id ?? 0;

                // Un solo flujo secuencial: concatMap + tap para progreso + toArray para resumen final
                from(seleccionados)
                    .pipe(
                        concatMap((ingreso: any) => {
                            const payload = {
                                ingreso: ingreso.ingreso,
                                usuario_id: String(usuarioId)
                            };

                            return this.envioIhceService.enviarRdaPaciente(payload).pipe(
                                // Mapear éxito al formato de resultado esperado
                                // concatMap(res => of({ ingreso: ingreso.ingreso, estado: 'Éxito *-*', mensaje: res?.message || 'Proceso Exitoso' })),
                                concatMap((res) => {
                                    // console.log('Formateando éxito para ingreso:', ingreso);
                                    console.log('Formateando éxito para res:', res);
                                    return of({ ingreso: ingreso.ingreso, estado: res?.success, mensaje: res?.message || 'Proceso Exitoso' });
                                }),
                                catchError((err) => {
                                    // Capturar errores individuales sin romper el pipeline
                                    return of({
                                        ingreso: ingreso.ingreso,
                                        estado: 'Error',
                                        mensaje: err?.error?.message || 'Ocurrió un error inesperado al procesar.'
                                    });
                                }),
                                // Actualizar barra de progreso por cada elemento completado
                                finalize(() => {
                                    this.procesadosMasivo.update((count) => count + 1);
                                })
                            );
                        }),
                        toArray()
                    )
                    .subscribe({
                        next: (detalleTabla) => {
                            this.resultadosMasivo.set(detalleTabla);
                            this.messageService.clear('envioMasivo');
                            this.mostrarResumenMasivo.set(true);
                        }
                    });
            }
        });
    }

    cerrarResumen() {
        this.mostrarResumenMasivo.set(false);
        this.buscar();
    }

    enviarFila(ingreso: any) {
        this.confirmationService.confirm({
            message: `¿Está seguro que desea enviar a procesar el Ingreso No. ${ingreso.ingreso}?`,
            header: 'Confirmar Envío',
            icon: 'pi pi-exclamation-triangle',
            acceptLabel: 'Sí, enviar',
            rejectLabel: 'Cerrar',
            acceptButtonStyleClass: 'p-button-primary',
            rejectButtonStyleClass: 'p-button-secondary p-button-outlined',
            accept: () => {
                // Obtener el ID del usuario desde la Signal reactiva de sesión
                const usuarioId = this.sessionService.sessionData()?.id ?? 0;

                // json que contiene la informacion a enviar para el endpoint RDA
                const payload = {
                    ingreso: ingreso.ingreso,
                    usuario_id: String(usuarioId)
                };

                // console.log('Enviando Payload RDA (Objeto):', payload);

                this.envioIhceService
                    .enviarRdaPaciente(payload)
                    .pipe(
                        // El LoadingService se maneja globalmente por LoadingInterceptor
                        finalize(() => {
                            // Lógica de finalización si es necesaria independientemente del interceptor
                        })
                    )
                    .subscribe({
                        next: (response) => {
                            console.log('Respuesta RDA Paciente:', response);

                            // Refrescar tabla con los filtros actuales
                            this.buscar();

                            /*
              
              */

                            if (response?.success) {
                                this.messageService.add({
                                    key: 'confirmacionCentral', // Debe coincidir con el key del HTML
                                    severity: 'success',
                                    summary: 'Proceso Exitoso',
                                    detail: response?.message, // Aquí inyectamos la descripción dinámica
                                    life: 10000
                                });
                            } else {
                                this.messageService.add({
                                    key: 'confirmacionCentral', // Debe coincidir con el key del HTML
                                    severity: 'error',
                                    summary: 'Error',
                                    detail: response?.message, // Aquí inyectamos la descripción dinámica
                                    life: 10000
                                });
                            }
                        },
                        error: (error) => {
                            console.error('Error al enviar RDA Paciente:', error);
                            this.messageService.add({
                                key: 'confirmacionCentral',
                                severity: 'error',
                                summary: 'Error',
                                detail: error?.error?.message || 'Ocurrió un error inesperado al procesar el envío.',
                                life: 10000
                            });
                        }
                    });
            }
        });
    }

    getSeverityStatus(estadoId: number | null): 'success' | 'secondary' | 'info' | 'warn' | 'danger' | 'contrast' {
        if (!estadoId) return 'info'; // Sin enviar o nulo

        // Asumiendo IDs por convención: 1=Procesado/Exitoso, 2=Pendiente, 3=Error
        switch (Number(estadoId)) {
            case 1:
                return 'warn'; // Pendiente
            case 2:
                return 'success'; // Exitoso
            case 3:
                return 'contrast'; // FALLIDO
            case 4:
                return 'danger'; // RECHAZADO
            default:
                return 'info';
        }
    }

    isFormEmpty(): boolean {
        const rawValue = this.form.getRawValue();
        return Object.values(rawValue).every((val) => val === null || val === '');
    }

    abrirJsonLog(ingresoId: number, tipoEndpoint: string, tipoRdaId?: number): void {
        this.isLoading.set(true);

        this.envioIhceService
            .obtenerJsonLog(ingresoId, tipoEndpoint, tipoRdaId)
            .pipe(finalize(() => this.isLoading.set(false)))
            .subscribe({
                next: (response) => {
                    const jsonString = JSON.stringify(response, null, 2);
                    const blob = new Blob([jsonString], { type: 'application/json' });
                    const url = window.URL.createObjectURL(blob);
                    window.open(url, '_blank');
                },
                error: (error) => {
                    this.messageService.add({
                        key: 'confirmacionCentral',
                        severity: 'warn',
                        summary: 'Sin información',
                        detail: error?.error?.message || 'No se encontró información para este ingreso.',
                        life: 8000
                    });
                }
            });
    }

    /**
     * Abre el visor JSON integrado en un Dialog de PrimeNG.
     * Realiza la misma petición que abrirJsonLog() pero grafica el resultado
     * en un modal con AppJsonViewerComponent en lugar de abrir una nueva pestaña.
     *
     * @param ingresoId   Número de ingreso del paciente.
     * @param tipoEndpoint Endpoint del log: 'ihce-ultimo-json-enviado' | 'ihce-ultima-respuesta-envio'
     * @param titulo      Etiqueta que se mostrará como header del modal.
     * @param tipoRdaId   (Opcional) ID del tipo de RDA.
     */
    abrirJsonLogModal(ingresoId: number, tipoEndpoint: string, titulo: string, tipoRdaId?: number): void {
        // Limpiar estado anterior y abrir el dialog con spinner
        this.jsonViewerData.set(null);
        this.jsonViewerExpandAll.set(true); // <-- Auto expandir por defecto al cargar
        this.jsonViewerMaximized.set(true); // <-- Abrir maximizado por defecto
        this.jsonViewerTitulo.set(`${titulo} — Ingreso #${ingresoId}`);
        this.jsonViewerCargando.set(true);
        this.jsonViewerVisible.set(true);

        this.envioIhceService
            .obtenerJsonLog(ingresoId, tipoEndpoint, tipoRdaId)
            .pipe(finalize(() => this.jsonViewerCargando.set(false)))
            .subscribe({
                next: (response) => {
                    // Normalizar: si la respuesta trae una capa 'data', usarla directamente
                    this.jsonViewerData.set(response?.data ?? response);
                },
                error: (error) => {
                    this.jsonViewerVisible.set(false);
                    this.messageService.add({
                        key: 'confirmacionCentral',
                        severity: 'warn',
                        summary: 'Sin información',
                        detail: error?.error?.message || 'No se encontró información de log para este ingreso.',
                        life: 8000
                    });
                }
            });
    }
}
