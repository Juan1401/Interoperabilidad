import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { DynamicDialogConfig, DynamicDialogRef } from 'primeng/dynamicdialog';
import { ConsultarIhceService } from '../../consultar-ihce.service';
import { parseFhirPatient, parseFhirCustodian, parseFhirPractitioner, parseFhirCondition, parseFhirAllergyIntolerance, parseFhirFamilyMemberHistory, parseFhirMedicationStatement } from '../../consultar-ihce.utils';
import { TagModule } from 'primeng/tag';
import { ButtonModule } from 'primeng/button';

@Component({
    selector: 'app-modal-rda-paciente',
    standalone: true,
    imports: [CommonModule, TagModule, ButtonModule],
    templateUrl: './modal-rda-paciente.html',
    styleUrl: './modal-rda-paciente.scss',
    providers: []
})
export class ModalRdaPacienteComponent implements OnInit {
    private config = inject(DynamicDialogConfig);
    private ref = inject(DynamicDialogRef);
    private consultarIhceService = inject(ConsultarIhceService);

    // Recurso recibido (el RDA)
    selectedRda = signal<any>(null);

    // Señales de Carga
    isLoadingPatientData = signal<boolean>(false);
    isLoadingCustodianData = signal<boolean>(false);
    isLoadingPractitionerData = signal<boolean>(false);
    isLoadingConditionData = signal<boolean>(false);
    isLoadingAllergyData = signal<boolean>(false);
    isLoadingFamilyHistoryData = signal<boolean>(false);
    isLoadingMedicationData = signal<boolean>(false);

    // Data cruda
    patientData = signal<any>(null);
    custodianData = signal<any>(null);
    practitionerData = signal<any>(null);
    conditionData = signal<any[]>([]);
    allergyData = signal<any[]>([]);
    familyHistoryData = signal<any[]>([]);
    medicationData = signal<any[]>([]);

    // Data mapeada/parseada
    patientParsedData = signal<any | null>(null);
    custodianParsedData = signal<any | null>(null);
    practitionerParsedData = signal<any | null>(null);
    conditionParsedData = signal<any[]>([]);
    allergyParsedData = signal<any[]>([]);
    familyHistoryParsedData = signal<any[]>([]);
    medicationParsedData = signal<any[]>([]);

    ngOnInit() {
        const fila = this.config.data?.fila;
        if (fila) {
            this.selectedRda.set(fila);
            this.cargarDetalle(fila);
        }
    }

    cargarDetalle(data: any) {
        // --- Variables de Referencia Principales ---
        let referencePatient = data.resource?.subject?.reference;
        let referenceCustodian = data.resource?.custodian?.reference;
        let referencePractitioner = data.resource?.author?.[0]?.reference;
        let referenceAttester = data.resource?.attester?.[0]?.party?.reference;

        // --- Variables de Referencia de las Secciones Clínicas ---
        let referenceCondition: any[] = [];
        let referenceAllergy: any[] = [];
        let referenceFamilyHistory: any[] = [];
        let referenceMedication: any[] = [];

        const sections = data.resource?.section || [];
        sections.forEach((sec: any) => {
            const code = sec.code?.coding?.[0]?.code;
            if (code === '11450-4') {
                referenceCondition = sec.entry;
            } else if (code === '48765-2') {
                referenceAllergy = sec.entry;
            } else if (code === '10157-6') {
                referenceFamilyHistory = sec.entry;
            } else if (code === '10160-0') {
                referenceMedication = sec.entry;
            }
        });

        // 1. Cargar Paciente
        if (referencePatient) {
            this.isLoadingPatientData.set(true);
            this.consultarIhceService.consultarRecurso(referencePatient).subscribe({
                next: (response) => {
                    this.patientData.set(response);
                    this.patientParsedData.set(parseFhirPatient(response));
                    this.isLoadingPatientData.set(false);
                },
                error: () => this.isLoadingPatientData.set(false)
            });
        }

        // 2. Cargar Organización (Custodian)
        if (referenceCustodian) {
            this.isLoadingCustodianData.set(true);
            this.consultarIhceService.consultarRecurso(referenceCustodian).subscribe({
                next: (response) => {
                    this.custodianData.set(response);
                    this.custodianParsedData.set(parseFhirCustodian(response));
                    this.isLoadingCustodianData.set(false);
                },
                error: () => this.isLoadingCustodianData.set(false)
            });
        }

        // 3. Cargar Profesional (Practitioner)
        if (referencePractitioner) {
            this.isLoadingPractitionerData.set(true);
            this.consultarIhceService.consultarRecurso(referencePractitioner).subscribe({
                next: (response) => {
                    this.practitionerData.set(response);
                    this.practitionerParsedData.set(parseFhirPractitioner(response));
                    this.isLoadingPractitionerData.set(false);
                },
                error: () => this.isLoadingPractitionerData.set(false)
            });
        }

        // 4. Secciones Clínicas (Condition)
        if (Array.isArray(referenceCondition) && referenceCondition.length > 0) {
            this.isLoadingConditionData.set(true);
            const conditionRefs = referenceCondition.map((e: any) => e?.reference).filter(Boolean);
            const conditionResults: any[] = [];
            let conditionPending = conditionRefs.length;

            conditionRefs.forEach((ref: string) => {
                this.consultarIhceService.consultarRecurso(ref).subscribe({
                    next: (response) => {
                        const fhir = response?.data ?? response;
                        conditionResults.push(parseFhirCondition(fhir));
                        conditionPending--;
                        if (conditionPending === 0) {
                            this.conditionParsedData.set(conditionResults.filter(Boolean));
                            this.isLoadingConditionData.set(false);
                        }
                    },
                    error: () => {
                        conditionPending--;
                        if (conditionPending === 0) {
                            this.conditionParsedData.set(conditionResults.filter(Boolean));
                            this.isLoadingConditionData.set(false);
                        }
                    }
                });
            });
        }

        // 5. Secciones Clínicas (AllergyIntolerance)
        if (Array.isArray(referenceAllergy) && referenceAllergy.length > 0) {
            this.isLoadingAllergyData.set(true);
            const allergyRefs = referenceAllergy.map((e: any) => e?.reference).filter(Boolean);
            const allergyResults: any[] = [];
            let allergyPending = allergyRefs.length;

            allergyRefs.forEach((ref: string) => {
                this.consultarIhceService.consultarRecurso(ref).subscribe({
                    next: (response) => {
                        const fhir = response?.data ?? response;
                        allergyResults.push(parseFhirAllergyIntolerance(fhir));
                        allergyPending--;
                        if (allergyPending === 0) {
                            this.allergyParsedData.set(allergyResults.filter(Boolean));
                            this.isLoadingAllergyData.set(false);
                        }
                    },
                    error: () => {
                        allergyPending--;
                        if (allergyPending === 0) {
                            this.allergyParsedData.set(allergyResults.filter(Boolean));
                            this.isLoadingAllergyData.set(false);
                        }
                    }
                });
            });
        }

        // 6. Secciones Clínicas (FamilyMemberHistory)
        if (Array.isArray(referenceFamilyHistory) && referenceFamilyHistory.length > 0) {
            this.isLoadingFamilyHistoryData.set(true);
            const familyRefs = referenceFamilyHistory.map((e: any) => e?.reference).filter(Boolean);
            const familyResults: any[] = [];
            let familyPending = familyRefs.length;

            familyRefs.forEach((ref: string) => {
                this.consultarIhceService.consultarRecurso(ref).subscribe({
                    next: (response) => {
                        const fhir = response?.data ?? response;
                        familyResults.push(parseFhirFamilyMemberHistory(fhir));
                        familyPending--;
                        if (familyPending === 0) {
                            this.familyHistoryParsedData.set(familyResults.filter(Boolean));
                            this.isLoadingFamilyHistoryData.set(false);
                        }
                    },
                    error: () => {
                        familyPending--;
                        if (familyPending === 0) {
                            this.familyHistoryParsedData.set(familyResults.filter(Boolean));
                            this.isLoadingFamilyHistoryData.set(false);
                        }
                    }
                });
            });
        }

        // 7. Secciones Clínicas (MedicationStatement)
        if (Array.isArray(referenceMedication) && referenceMedication.length > 0) {
            this.isLoadingMedicationData.set(true);
            const medRefs = referenceMedication.map((e: any) => e?.reference).filter(Boolean);
            const medResults: any[] = [];
            let medPending = medRefs.length;

            medRefs.forEach((ref: string) => {
                this.consultarIhceService.consultarRecurso(ref).subscribe({
                    next: (response) => {
                        const fhir = response?.data ?? response;
                        medResults.push(parseFhirMedicationStatement(fhir));
                        medPending--;
                        if (medPending === 0) {
                            this.medicationParsedData.set(medResults.filter(Boolean));
                            this.isLoadingMedicationData.set(false);
                        }
                    },
                    error: () => {
                        medPending--;
                        if (medPending === 0) {
                            this.medicationParsedData.set(medResults.filter(Boolean));
                            this.isLoadingMedicationData.set(false);
                        }
                    }
                });
            });
        }
    }

    cerrarModal() {
        this.ref.close();
    }
}
