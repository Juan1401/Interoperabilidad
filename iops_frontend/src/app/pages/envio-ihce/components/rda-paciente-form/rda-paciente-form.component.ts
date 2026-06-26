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

  // Catálogos — se poblan desde Laravel en ngOnInit()
  tiposDocumento: any[]    = [];
  generosBiologicos: any[] = [];
  zonasResidencia: any[]   = [];
  municipios: any[]        = [];

  // País fijo Colombia; EAPB opcional (sin catálogo de API por ahora)
  paises: any[] = [{ label: 'Colombia (170)', value: '170' }];
  eapb:   any[] = [];

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
    forkJoin({
      tiposDocumento:   this.envioService.getCatalogoTiposDocumento(),
      generos:          this.envioService.getCatalogoGeneros(),
      zonas:            this.envioService.getCatalogoZonas(),
      municipios:       this.envioService.getCatalogoMunicipios()
    }).subscribe({
      next: (data) => {
        this.tiposDocumento    = data.tiposDocumento;
        this.generosBiologicos = data.generos;
        this.zonasResidencia   = data.zonas;
        this.municipios        = data.municipios;
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
      next:  (resultados) => { this.sugerenciasDiagnosticos = resultados; },
      error: ()           => { this.sugerenciasDiagnosticos = []; }
    });
  }

  /** Delega la búsqueda de medicamentos (CUM/INN) al CatalogController de Laravel */
  buscarMedicamento(event: any): void {
    if (!event.query || event.query.trim().length < 2) {
      this.sugerenciasMedicamentos = [];
      return;
    }
    this.envioService.searchMedicamentos(event.query).subscribe({
      next:  (resultados) => { this.sugerenciasMedicamentos = resultados; },
      error: ()           => { this.sugerenciasMedicamentos = []; }
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
      dosis: [''],
      frecuencia: ['']
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

    const payload = this.rdaPacienteForm.getRawValue();
    console.log('📋 Payload RDA Paciente listo para enviar:', JSON.stringify(payload, null, 2));

    this.enviando = true;
    this.envioService.postRdaPaciente(payload).subscribe({
      next: (response) => {
        this.enviando = false;
        this.messageService.add({
          severity: 'success',
          summary: 'RDA Enviado',
          detail: response?.message || 'El Registro de Datos Asistenciales fue generado y enviado exitosamente.',
          life: 8000
        });
      },
      error: (error) => {
        this.enviando = false;
        this.messageService.add({
          severity: 'error',
          summary: 'Error en el envío',
          detail: error?.error?.message || 'Ocurrió un error inesperado al enviar el RDA. Intente de nuevo.',
          life: 8000
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
}

