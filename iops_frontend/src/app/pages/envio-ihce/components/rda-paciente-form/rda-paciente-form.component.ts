import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, FormArray, ReactiveFormsModule } from '@angular/forms';
import { forkJoin } from 'rxjs';
import { MenuItem, MessageService } from 'primeng/api';
import { StepsModule } from 'primeng/steps';
import { ButtonModule } from 'primeng/button';
import { SelectModule } from 'primeng/select';
import { DatePickerModule } from 'primeng/datepicker';
import { InputTextModule } from 'primeng/inputtext';
import { FieldsetModule } from 'primeng/fieldset';
import { AutoCompleteModule } from 'primeng/autocomplete';
import { ToastModule } from 'primeng/toast';
import { MessageModule } from 'primeng/message';
import { EnvioIhceService } from '../../envio-ihce.service';

@Component({
  selector: 'app-rda-paciente-form',
  standalone: true,
  imports: [CommonModule, StepsModule, ButtonModule, ReactiveFormsModule, SelectModule, DatePickerModule, InputTextModule, FieldsetModule, AutoCompleteModule, ToastModule, MessageModule],
  providers: [MessageService],
  templateUrl: './rda-paciente-form.component.html'
})
export class RdaPacienteFormComponent implements OnInit {
  private fb = inject(FormBuilder);
  private envioService = inject(EnvioIhceService);
  private messageService = inject(MessageService);

  enviando = false;
  enviandoAlMinisterio = false;

  // UUID del documento generado en Fase 1, necesario para la Fase 2
  documentIdGenerado: string | null = null;

  // Estado del documento para feedback visual en el Paso 3
  statusDocumento: 'idle' | 'DRAFT' | 'READY' | 'ACCEPTED' | 'REJECTED' | 'ERROR_AUTH' | 'ERROR_SERVER' | 'ERROR' = 'idle';

  // Errores específicos devueltos por el Ministerio
  erroresMinisterio: string[] = [];

  // Catálogos — se poblan desde Laravel en ngOnInit()
  tiposDocumento: any[] = [];
  generosBiologicos: any[] = [];
  zonasResidencia: any[] = [];
  municipios: any[] = [];
  unidadesMedida: any[] = [];
  viasAdministracion: any[] = [];
  tiposAlergia: any[] = [];
  parentescos: any[] = [];
  severidadesAlergia: any[] = [];
  etnias: any[] = [];
  discapacidades: any[] = [];
  identidadesGenero: any[] = [];
  estadosClinicos: any[] = [];
  estadosVerificacion: any[] = [];
  unidadesTiempo = [
    { label: 'Horas (h)', value: 'h' },
    { label: 'Días (d)', value: 'd' },
    { label: 'Semanas (wk)', value: 'wk' },
    { label: 'Meses (mo)', value: 'mo' }
  ];
  // País fijo Colombia; EAPB opcional (sin catálogo de API por ahora)
  paises: any[] = [{ label: 'Colombia (170)', value: '170' }];
  eapb: any[] = [];

  items: MenuItem[] = [
    { label: 'Datos Demográficos' },
    { label: 'Antecedentes Clínicos' },
    { label: 'Resumen y Envío' }
  ];

  activeIndex = 0;

  rdaPacienteForm: FormGroup = this.fb.group({
    tipo_rda: ['paciente'],
    caja_1_demograficos: this.fb.group({
      paciente: this.fb.group({
        tipo_documento: ['', Validators.required],
        numero_documento: ['', Validators.required],
        nombres: ['', Validators.required],
        apellidos: ['', Validators.required],
        fecha_nacimiento: ['', Validators.required],
        genero_biologico: ['', Validators.required],
        zona_residencia: ['', Validators.required],
        codigo_pais: ['170', Validators.required],
        codigo_municipio: ['', Validators.required],
        etnia: ['', Validators.required],
        discapacidad: ['', Validators.required],
        identidad_genero: ['', Validators.required],
        eapb_codigo: ['']
      })
    }),
    caja_antecedentes: this.fb.group({
      patologicos: this.fb.array([]),
      farmacologicos: this.fb.array([]),
      alergias: this.fb.array([]),
      familiares: this.fb.array([])
    })
  });

  // Getters para acceder fácilmente a los FormArrays
  get patologicos(): FormArray {
    return this.rdaPacienteForm.get('caja_antecedentes.patologicos') as FormArray;
  }

  get farmacologicos(): FormArray {
    return this.rdaPacienteForm.get('caja_antecedentes.farmacologicos') as FormArray;
  }

  get alergias(): FormArray {
    return this.rdaPacienteForm.get('caja_antecedentes.alergias') as FormArray;
  }

  get familiares(): FormArray {
    return this.rdaPacienteForm.get('caja_antecedentes.familiares') as FormArray;
  }

  /** Ciclo de vida: carga todos los catálogos en paralelo al montar el componente */
  ngOnInit(): void {
    // Catálogos que no requieren API (datos oficiales fijos del Minsalud)
    this.cargarCatalogosEstaticos();

    forkJoin({
      tiposDocumento: this.envioService.getCatalogoTiposDocumento(),
      generos: this.envioService.getCatalogoGeneros(),
      zonas: this.envioService.getCatalogoZonas(),
      municipios: this.envioService.getCatalogoMunicipios(),
      unidadesMedida: this.envioService.getUnidadesMedida(),
      viasAdmin: this.envioService.getViasAdministracion(),
      tiposAlergia: this.envioService.getTiposAlergia(),
      parentescos: this.envioService.getParentescos(),
      severidades: this.envioService.getSeveridades()
    }).subscribe({
      next: (data) => {
        this.tiposDocumento = data.tiposDocumento;
        this.generosBiologicos = data.generos;
        this.zonasResidencia = data.zonas;
        this.municipios = data.municipios;
        this.unidadesMedida = data.unidadesMedida;
        this.viasAdministracion = data.viasAdmin;
        this.tiposAlergia = data.tiposAlergia;
        this.parentescos = data.parentescos;
        this.severidadesAlergia = data.severidades;
      },
      error: (err) => {
        // Si falla la carga de catálogos, notificamos al usuario sin bloquear el formulario
        console.error('Error cargando catálogos:', err);
        this.messageService.add({
          severity: 'warn',
          summary: 'Catálogos no disponibles',
          detail: 'No se pudieron cargar las listas desplegables. Verifique su conexión.',
          life: 6000
        });
      }
    });
  }

  // --- Sugerencias para Autocompletar — conectadas al servicio real ---
  sugerenciasDiagnosticos: any[] = [];
  sugerenciasMedicamentos: any[] = [];

  /** Delega la búsqueda de diagnósticos CIE-10 al CatalogController de Laravel */
  buscarDiagnostico(event: any): void {
    if (!event.query || event.query.trim().length < 2) {
      this.sugerenciasDiagnosticos = [];
      return;
    }
    this.envioService.searchDiagnosticos(event.query).subscribe({
      next: (resultados) => { this.sugerenciasDiagnosticos = resultados; },
      error: () => { this.sugerenciasDiagnosticos = []; }
    });
  }

  /** Delega la búsqueda de medicamentos DCI al CatalogController de Laravel */
  buscarMedicamento(event: any): void {
    if (!event.query || event.query.trim().length < 2) {
      this.sugerenciasMedicamentos = [];
      return;
    }
    this.envioService.searchMedicamentosDci(event.query).subscribe({
      next: (resultados) => { this.sugerenciasMedicamentos = resultados; },
      error: () => { this.sugerenciasMedicamentos = []; }
    });
  }

  // --- Métodos para gestionar los arreglos dinámicos ---
  addPatologico() {
    this.patologicos.push(this.fb.group({
      codigo_cie10: ['', Validators.required],
      descripcion: [''],
      estado: ['Activo']
    }));
  }
  removePatologico(index: number) {
    this.patologicos.removeAt(index);
  }

  addFarmacologico() {
    this.farmacologicos.push(this.fb.group({
      medicamento: ['', Validators.required],
      dosis_valor: ['', [Validators.required, Validators.min(0.1)]],
      dosis_unidad: ['', Validators.required],
      frecuencia_valor: ['', Validators.required],
      frecuencia_unidad: ['', Validators.required],
      via_administracion: ['', Validators.required]
    }));
  }
  removeFarmacologico(index: number) {
    this.farmacologicos.removeAt(index);
  }

  addAlergia() {
    this.alergias.push(this.fb.group({
      alergeno: ['', Validators.required],
      reaccion: [''],
      severidad: ['Leve']
    }));
  }
  removeAlergia(index: number) {
    this.alergias.removeAt(index);
  }

  addFamiliar() {
    this.familiares.push(this.fb.group({
      parentesco: ['', Validators.required],
      codigo_cie10: ['', Validators.required],
      descripcion: ['']
    }));
  }
  removeFamiliar(index: number) {
    this.familiares.removeAt(index);
  }

  next() {
    if (this.activeIndex < this.items.length - 1) {
      this.activeIndex++;
    }
  }

  prev() {
    if (this.activeIndex > 0) {
      this.activeIndex--;
    }
  }

  enviarRda() {
    if (this.rdaPacienteForm.invalid) {
      this.rdaPacienteForm.markAllAsTouched();
      this.messageService.add({
        severity: 'warn',
        summary: 'Formulario incompleto',
        detail: 'Por favor, revise y complete todos los campos obligatorios antes de continuar.',
        life: 5000
      });
      return;
    }

    let payload = this.rdaPacienteForm.getRawValue();

    // 🚀 Limpieza del Payload: Extraemos solo el string (value) de los autocompletados
    if (payload.caja_antecedentes?.patologicos) {
      payload.caja_antecedentes.patologicos = payload.caja_antecedentes.patologicos.map((p: any) => ({
        ...p,
        codigo_cie10: (p.codigo_cie10 && typeof p.codigo_cie10 === 'object') ? p.codigo_cie10.value : p.codigo_cie10
      }));
    }

    if (payload.caja_antecedentes?.farmacologicos) {
      payload.caja_antecedentes.farmacologicos = payload.caja_antecedentes.farmacologicos.map((f: any) => ({
        ...f,
        medicamento: (f.medicamento && typeof f.medicamento === 'object') ? f.medicamento.value : f.medicamento
      }));
    }

    if (payload.caja_antecedentes?.familiares) {
      payload.caja_antecedentes.familiares = payload.caja_antecedentes.familiares.map((fam: any) => ({
        ...fam,
        codigo_cie10: (fam.codigo_cie10 && typeof fam.codigo_cie10 === 'object') ? fam.codigo_cie10.value : fam.codigo_cie10
      }));
    }

    // ── FASE 1: Guardar en BD y generar Bundle FHIR ─────────────────────────
    this.enviando = true;
    this.statusDocumento = 'DRAFT';
    this.erroresMinisterio = []; // Limpiamos errores previos

    this.envioService.postRdaPaciente(payload).subscribe({
      next: (response) => {
        this.enviando = false;
        this.documentIdGenerado = response?.data?.document_id ?? null;
        this.statusDocumento = 'READY';

        this.messageService.add({
          severity: 'info',
          summary: 'Bundle FHIR generado',
          detail: response?.message || 'El bundle FHIR fue generado correctamente. Presione "Enviar al Ministerio" para completar el proceso.',
          life: 6000
        });
      },
      error: (error) => {
        this.enviando = false;
        this.statusDocumento = 'ERROR';
        this.messageService.add({
          severity: 'error',
          summary: 'Error al generar el Bundle FHIR',
          detail: error?.error?.message || 'Ocurrió un error inesperado al guardar el formulario.',
          life: 8000
        });
      }
    });
  }

  /** FASE 2: Envía al Ministerio el bundle FHIR ya almacenado */
  enviarAlMinisterio() {
    if (!this.documentIdGenerado) {
      this.messageService.add({
        severity: 'warn',
        summary: 'Acción requerida',
        detail: 'Primero debe generar el Bundle FHIR presionando "Generar RDA".',
        life: 5000
      });
      return;
    }

    this.enviandoAlMinisterio = true;
    this.envioService.sendRdaAlMinisterio(this.documentIdGenerado).subscribe({
      next: (response) => {
        this.enviandoAlMinisterio = false;
        this.statusDocumento = response?.data?.status ?? 'ERROR';

        // El backend retorna severity: 'success' | 'warn' | 'error'
        const severity = response?.severity ?? (response?.success ? 'success' : 'error');
        const summary = severity === 'success'
          ? '✅ RDA Aceptado por el Ministerio'
          : severity === 'warn'
          ? '⚠️ RDA Rechazado — Revisar errores'
          : '❌ Error de comunicación';

        this.messageService.add({
          severity,
          summary,
          detail: response?.message ?? 'Sin detalles disponibles.',
          life: 10000,
          sticky: severity !== 'success'
        });

        // Log de errores FHIR del Ministerio para depuración
        if (!response?.success && response?.data?.errors?.issue) {
          console.warn('❌ Errores FHIR del Ministerio:', response.data.errors);
          // Extraer el texto de los errores
          this.erroresMinisterio = response.data.errors.issue.map((i: any) => 
            i.details?.text || i.diagnostics || 'Error de validación desconocido'
          );
        } else {
          this.erroresMinisterio = [];
        }
      },
      error: (httpError) => {
        this.enviandoAlMinisterio = false;
        this.statusDocumento = 'ERROR';
        this.messageService.add({
          severity: 'error',
          summary: '❌ Error de red',
          detail: httpError?.error?.message || 'No se pudo conectar con el servidor. Verifique su conexión.',
          life: 10000,
          sticky: true
        });
      }
    });
  }

  // --- Getters de formato para el Paso 3 (Resumen) ---

  /** Devuelve la fecha de nacimiento como DD/MM/AAAA en lugar del objeto Date completo */
  get fechaNacimientoFormateada(): string {
    const val = this.rdaPacienteForm.get('caja_1_demograficos.paciente.fecha_nacimiento')?.value;
    if (!val) return '—';
    try {
      const d = val instanceof Date ? val : new Date(val);
      if (isNaN(d.getTime())) return String(val);
      return d.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
    } catch {
      return String(val);
    }
  }

  /** Devuelve la etiqueta del tipo de documento (ej. "Cédula de Ciudadanía") en lugar del código */
  get tipoDocFormateado(): string {
    const val = this.rdaPacienteForm.get('caja_1_demograficos.paciente.tipo_documento')?.value;
    return this.tiposDocumento.find(t => t.value === val)?.label || '—';
  }

  /** Devuelve la etiqueta del género biológico (ej. "Masculino") en lugar del código numérico */
  get generoFormateado(): string {
    const val = this.rdaPacienteForm.get('caja_1_demograficos.paciente.genero_biologico')?.value;
    return this.generosBiologicos.find(g => g.value === val)?.label || '—';
  }

  /** Devuelve la etiqueta de la zona de residencia (ej. "Urbana") en lugar del código */
  get zonaFormateada(): string {
    const val = this.rdaPacienteForm.get('caja_1_demograficos.paciente.zona_residencia')?.value;
    return this.zonasResidencia.find(z => z.value === val)?.label || '—';
  }

  /** Devuelve el nombre del municipio (ej. "Santiago de Cali") en lugar del código */
  get municipioFormateado(): string {
    const val = this.rdaPacienteForm.get('caja_1_demograficos.paciente.codigo_municipio')?.value;
    return this.municipios.find(m => m.value === val)?.label || val || '—';
  }

  /** Devuelve la etiqueta de la etnia seleccionada */
  get etniaFormateada(): string {
    const val = this.rdaPacienteForm.get('caja_1_demograficos.paciente.etnia')?.value;
    return this.etnias.find(e => e.value === val)?.label || '—';
  }

  /** Devuelve la etiqueta de la discapacidad seleccionada */
  get discapacidadFormateada(): string {
    const val = this.rdaPacienteForm.get('caja_1_demograficos.paciente.discapacidad')?.value;
    return this.discapacidades.find(d => d.value === val)?.label || '—';
  }

  /** Devuelve la etiqueta de la identidad de género seleccionada */
  get identidadGeneroFormateada(): string {
    const val = this.rdaPacienteForm.get('caja_1_demograficos.paciente.identidad_genero')?.value;
    return this.identidadesGenero.find(i => i.value === val)?.label || '—';
  }

  /**
   * Pobla los arreglos de catálogos que son fijos del Minsalud y no requieren consulta a la API.
   * Se llama una sola vez desde ngOnInit().
   */
  private cargarCatalogosEstaticos(): void {
    this.etnias = [
      { label: 'Indígena', value: '1' },
      { label: 'ROM (Gitano)', value: '2' },
      { label: 'Raizal', value: '3' },
      { label: 'Palenquero de San Basilio', value: '4' },
      { label: 'Negro(a), Mulato(a), Afrocolombiano(a)', value: '5' },
      { label: 'Otras etnias', value: '6' }
    ];
    this.discapacidades = [
      { label: 'Discapacidad física', value: '01' },
      { label: 'Discapacidad visual', value: '02' },
      { label: 'Discapacidad auditiva', value: '03' },
      { label: 'Discapacidad intelectual', value: '04' },
      { label: 'Discapacidad sicosocial', value: '05' },
      { label: 'Sordoceguera', value: '06' },
      { label: 'Discapacidad múltiple', value: '07' },
      { label: 'Sin discapacidad', value: '08' }
    ];
    this.identidadesGenero = [
      { label: 'Masculino', value: '01' },
      { label: 'Femenino', value: '02' },
      { label: 'Transgénero', value: '03' },
      { label: 'Ninguno de los anteriores', value: '04' }
    ];
    this.estadosClinicos = [
      { label: 'Activo', value: 'active' },
      { label: 'Recurrencia', value: 'recurrence' },
      { label: 'Inactivo', value: 'inactive' },
      { label: 'Resuelto', value: 'resolved' }
    ];
    this.estadosVerificacion = [
      { label: 'No confirmado', value: 'unconfirmed' },
      { label: 'Provisional', value: 'provisional' },
      { label: 'Confirmado', value: 'confirmed' },
      { label: 'Refutado', value: 'refuted' }
    ];
  }
}

