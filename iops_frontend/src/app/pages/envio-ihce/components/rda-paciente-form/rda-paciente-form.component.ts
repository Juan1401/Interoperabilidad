import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, FormArray, ReactiveFormsModule } from '@angular/forms';
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
export class RdaPacienteFormComponent {
  private fb = inject(FormBuilder);
  private envioService = inject(EnvioIhceService);
  private messageService = inject(MessageService);

  enviando = false;

  // Mock data arrays
  tiposDocumento: any[] = [{ label: 'Cédula de Ciudadanía', value: 'CC' }, { label: 'Tarjeta de Identidad', value: 'TI' }];
  generosBiologicos: any[] = [{ label: 'Masculino', value: '1' }, { label: 'Femenino', value: '2' }];
  zonasResidencia: any[] = [{ label: 'Urbana', value: 'U' }, { label: 'Rural', value: 'R' }];
  paises: any[] = [{ label: 'Colombia (170)', value: '170' }];
  municipios: any[] = [{ label: 'Santiago de Cali', value: '76001' }];
  eapb: any[] = [{ label: 'EPS Sanitas', value: 'EPS001' }];

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

  // --- Sugerencias Mock para Autocompletar ---
  sugerenciasDiagnosticos: any[] = [];
  sugerenciasMedicamentos: any[] = [];

  buscarDiagnostico(event: any) {
    // Aquí luego conectaremos al servicio que consulta CIE-10 / CIE-11
    this.sugerenciasDiagnosticos = [
      { label: 'J01 - Sinusitis aguda', value: 'J01' },
      { label: 'I10 - Hipertensión esencial', value: 'I10' },
      { label: 'E11 - Diabetes mellitus tipo 2', value: 'E11' }
    ].filter(d => d.label.toLowerCase().includes(event.query.toLowerCase()));
  }

  buscarMedicamento(event: any) {
    // Aquí luego conectaremos al servicio que consulta medicamentos (MIPRES)
    this.sugerenciasMedicamentos = [
      { label: 'Paracetamol 500mg', value: '001' },
      { label: 'Amoxicilina 500mg', value: '002' },
      { label: 'Loratadina 10mg', value: '003' }
    ].filter(m => m.label.toLowerCase().includes(event.query.toLowerCase()));
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

