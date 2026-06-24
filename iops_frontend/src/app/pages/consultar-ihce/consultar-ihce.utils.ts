export interface PatientData {
  fullName: string;
  docType: string;
  documentValue: string;
  active: string;
  deceased: string;
  birthDate: string;
  birthTime: string;
  age: string;
  biologicalGender: string;
  genderIdentity: string;
  ethnicity: string;
  disability: string;
  nationality: string;
  country: string;
  countryCode: string;
  city: string;
  residenceZone: string;
  divipola: string;
}

export interface CustodianData {
  id: string;
  name: string;
  nit: string;
  reps: string;
  type: string;
}

export interface PractitionerQualification {
  name: string;
  start: string;
}

export interface PractitionerData {
  fullName: string;
  docType: string;
  documentValue: string;
  active: string;
  qualifications: PractitionerQualification[];
}

export interface EncounterDiagnosis {
  role: string;
  typeDisplay: string;
}

export interface EncounterData {
  id: string;
  status: string;
  classDisplay: string;
  modalidad: string;
  grupoServicio: string;
  tipoServicio: string;
  entornoAtencion: string;
  startDate: string;
  endDate: string;
  causaExternaDisplay: string;
  causaExternaCode: string;
  diagnosticos: EncounterDiagnosis[];
  // Campos de Hospitalización
  viaIngreso: string;
  condicionEgreso: string;
  estadoEgreso: string;
  destinoEgreso: string;
}

export interface ConditionData {
  // Identificación del recurso
  conditionId: string;
  lastUpdated: string;
  // Código de diagnóstico
  diagnosisCode: string;
  diagnosisDisplay: string;
  diagnosisText: string;
  // Categoría
  categoryCode: string;
  categoryDisplay: string;
  categorySystem: string;
  // Estado clínico
  clinicalStatusCode: string;
  clinicalStatusDisplay: string;
  // Estado de verificación
  verificationCode: string;
  verificationDisplay: string;
  // Referencias
  patientRef: string;
  // Campos opcionales adicionales
  onsetDateTime?: string;
  recordedDate?: string;
  note?: string;
}

export interface AllergyIntoleranceData {
  allergyId: string;
  clinicalStatusCode: string;
  clinicalStatusDisplay: string;
  verificationCode: string;
  verificationDisplay: string;
  allergyCode: string;
  allergyDisplay: string;
  allergyText: string;
  patientRef: string;
  lastUpdated?: string;
}

export interface FamilyHistoryData {
  status: string;
  relationshipCode: string;
  relationshipDisplay: string;
  conditionCode: string;
  conditionDisplay: string;
  patientRef: string;
}

export interface MedicationData {
  status: string;
  medicationCode: string;
  medicationDisplay: string;
  medicationSystem: string;
  patientRef: string;
}

/**
 * Mapeo simple de un recurso FHIR Patient a un objeto plano para la vista
 */
export function parseFhirPatient(rawResource: any): PatientData | null {
  if (!rawResource) return null;

  // Extraer el recurso dinámicamente: si la API responde con { data: { resourceType: "Patient" } }, tomamos `data`.
  const resource = rawResource?.data ? rawResource.data : rawResource;
  
  if (resource?.resourceType !== 'Patient') return null;

  // Helper avanzado para extraer valores de extensiones independientemente de su estructura (incluye valueCoding y fallback a code)
  const extractExtensionValue = (exts: any[] | undefined, urlPart: string): string => {
    if (!exts || !Array.isArray(exts)) return '---';
    const ext = exts.find((e: any) => e?.url?.toLowerCase()?.includes(urlPart.toLowerCase()));
    return ext?.valueString || 
           ext?.valueCode || 
           ext?.valueCoding?.display || 
           ext?.valueCoding?.code || 
           ext?.valueCodeableConcept?.text ||
           ext?.valueCodeableConcept?.coding?.[0]?.display || 
           '---';
  };

  const rootExts = resource?.extension || [];

  // Calcular la edad correctamente considerando mes y día
  let age = '---';
  if (resource?.birthDate) {
    try {
      const birth = new Date(resource.birthDate);
      const today = new Date();
      let ageValue = today.getFullYear() - birth.getFullYear();
      const m = today.getMonth() - birth.getMonth();
      if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
        ageValue--;
      }
      age = ageValue.toString();
    } catch (e) {
      age = '---';
    }
  }

  // Filtrado de Identificador Colombiano prioritario (ej. Registro civil sobre un valor genérico)
  const identifiers = resource?.identifier?.[0]?.type?.coding || [];
  const colIdentifier = identifiers.find((c: any) => c?.system?.includes('ColombianPersonIdentifier')) || identifiers[0];
  const docCode = colIdentifier?.code ? `${colIdentifier.code} – ` : '';
  const docType = colIdentifier?.display ? `${docCode}${colIdentifier.display}` : resource?.identifier?.[0]?.type?.text || '---';

  // Extraer bloque de dirección
  const address = resource?.address?.[0] || {};
  const addressExts = address.extension || [];

  const nameNode = resource?.name?.[0] || {};
  const givenName = Array.isArray(nameNode.given) ? nameNode.given.join(' ') : (nameNode.given || '');
  const familyName = nameNode.family || '';
  const parsedFullName = nameNode.text || `${givenName} ${familyName}`.trim() || 'Desconocido';

  const bioGenderExt = extractExtensionValue(resource?._gender?.extension, 'biologicalgender');
  const fallbackGender = resource?.gender === 'male' ? 'Masculino' : resource?.gender === 'female' ? 'Femenino' : (resource?.gender || '---');
  
  const countryCodeExt = extractExtensionValue(address._country?.extension, 'countrycode');
  const divipolaExt = extractExtensionValue(address._city?.extension, 'divipolamunicipality');

  return {
    fullName: parsedFullName,
    docType: docType,
    documentValue: resource?.identifier?.[0]?.value || '---',
    active: resource?.active ? 'Sí' : 'No',
    deceased: (resource?.deceasedBoolean || resource?.deceasedDateTime) ? 'Sí' : 'No',
    birthDate: resource?.birthDate || '---',
    birthTime: extractExtensionValue(rootExts, 'birthtime'),
    age: age,
    biologicalGender: bioGenderExt !== '---' ? bioGenderExt : fallbackGender,
    genderIdentity: extractExtensionValue(rootExts, 'genderidentity'),
    ethnicity: extractExtensionValue(rootExts, 'ethnicity'),
    disability: extractExtensionValue(rootExts, 'disability'),
    nationality: extractExtensionValue(rootExts, 'nationality'),
    country: address.country || 'Colombia',
    countryCode: countryCodeExt !== '---' ? countryCodeExt : 'CO',
    city: address.city || '---',
    residenceZone: extractExtensionValue(addressExts, 'residencezone'),
    divipola: divipolaExt !== '---' ? divipolaExt : (address.district || '---')
  };
}

/**
 * Mapeo de un recurso FHIR Organization (Custodian) a un objeto plano para la vista
 */
export function parseFhirCustodian(rawResource: any): CustodianData | null {
  if (!rawResource) return null;

  const resource = rawResource?.data ? rawResource.data : rawResource;
  
  if (resource?.resourceType !== 'Organization') return null;

  const identifiers = resource?.identifier || [];
  
  // Buscar NIT
  const nitIdentifier = identifiers.find((i: any) => 
    i?.type?.coding?.some((c: any) => c?.code === 'NIT')
  );
  
  // Buscar Código de habilitación (CodigoPrestador)
  const repsIdentifier = identifiers.find((i: any) => 
    i?.type?.coding?.some((c: any) => c?.code === 'CodigoPrestador')
  );

  // Extraer el tipo de organización
  const types = resource?.type || [];
  const orgType = types.find((t: any) => 
    t?.coding?.some((c: any) => c?.system?.includes('ColombianProviderClass'))
  );
  const orgTypeDisplay = orgType?.coding?.find((c: any) => c?.system?.includes('ColombianProviderClass'))?.display || '---';

  return {
    id: resource?.id || '---',
    name: resource?.name || '---',
    nit: nitIdentifier?.value || '---',
    reps: repsIdentifier?.value || '---',
    type: orgTypeDisplay
  };
}

/**
 * Mapeo de un recurso FHIR Practitioner a un objeto plano para la vista
 */
export function parseFhirPractitioner(rawResource: any): PractitionerData | null {
  if (!rawResource) return null;

  const resource = rawResource?.data ? rawResource.data : rawResource;

  if (resource?.resourceType !== 'Practitioner') return null;

  const identifier = resource?.identifier?.[0];
  const colIdentifier = identifier?.type?.coding?.find((c: any) =>
    c?.system?.includes('ColombianPersonIdentifier')
  ) ?? identifier?.type?.coding?.[0];

  const nameBlock = resource?.name?.[0] || {};
  const given = Array.isArray(nameBlock.given) ? nameBlock.given.join(' ') : (nameBlock.given || '');
  const family = nameBlock.family || '';
  const fullName = `${given} ${family}`.trim() || 'Desconocido';

  const docCode = colIdentifier?.code ? `${colIdentifier.code} – ` : '';
  const docType = colIdentifier?.display ? `${docCode}${colIdentifier.display}` : identifier?.type?.text || '---';

  // Calificaciones
  const qualifications: PractitionerQualification[] = (resource?.qualification || []).map((q: any) => ({
    name: q?.code?.coding?.[0]?.display || '---',
    start: q?.period?.start || '---'
  }));

  return {
    fullName,
    docType,
    documentValue: identifier?.value || '---',
    active: resource?.active ? 'Activo' : 'Inactivo',
    qualifications
  };
}

/**
 * Mapeo de un recurso FHIR Condition a un objeto plano para la vista.
 * Soporta ambas variantes del campo `code`:
 *   - Con coding[]:  { coding: [{ code, display }], text? }
 *   - Solo text:     { text: "Diabetes" }  (sin coding)
 */
export function parseFhirCondition(rawResource: any): ConditionData | null {
  if (!rawResource) return null;

  const resource = rawResource?.data ? rawResource.data : rawResource;

  if (resource?.resourceType !== 'Condition') return null;

  // --- Estado clínico ---
  const clinicalStatus = resource?.clinicalStatus?.coding?.[0];

  // --- Estado de verificación ---
  const verificationStatus = resource?.verificationStatus?.coding?.[0];

  // --- Categoría (puede no tener coding) ---
  const categoryBlock = resource?.category?.[0];
  const categoryCoding = categoryBlock?.coding?.[0];

  // --- Código de diagnóstico ---
  // Prioridad: coding[0] → fallback a sólo text
  const codeCoding  = resource?.code?.coding?.[0];
  const codeText    = resource?.code?.text || '';
  const diagnosisCode    = codeCoding?.code    || '---';
  const diagnosisDisplay = codeCoding?.display || codeText || '---';

  // --- Campos opcionales ---
  const notes = Array.isArray(resource?.note)
    ? resource.note.map((n: any) => n?.text || '').filter(Boolean).join(' | ')
    : undefined;

  return {
    // Identificación
    conditionId:  resource?.id             || '---',
    lastUpdated:  resource?.meta?.lastUpdated || '---',
    // Diagnóstico
    diagnosisCode,
    diagnosisDisplay,
    diagnosisText:         codeText || '---',
    // Categoría
    categoryCode:    categoryCoding?.code    || '---',
    categoryDisplay: categoryCoding?.display || categoryBlock?.text || '---',
    categorySystem:  categoryCoding?.system  || '---',
    // Estado clínico
    clinicalStatusCode:    clinicalStatus?.code    || '---',
    clinicalStatusDisplay: clinicalStatus?.display || '---',
    // Verificación
    verificationCode:    verificationStatus?.code    || '---',
    verificationDisplay: verificationStatus?.display || '---',
    // Referencias
    patientRef: resource?.subject?.reference || '---',
    // Opcionales
    onsetDateTime: resource?.onsetDateTime   || undefined,
    recordedDate:  resource?.recordedDate    || undefined,
    note:          notes                     || undefined,
  };
}

/**
 * Mapeo de un recurso FHIR AllergyIntolerance a un objeto plano para la vista
 */
export function parseFhirAllergyIntolerance(rawResource: any): AllergyIntoleranceData | null {
  if (!rawResource) return null;

  const resource = rawResource?.data ? rawResource.data : rawResource;

  if (resource?.resourceType !== 'AllergyIntolerance') return null;

  const clinicalStatus     = resource?.clinicalStatus?.coding?.[0];
  const verificationStatus = resource?.verificationStatus?.coding?.[0];
  const code               = resource?.code?.coding?.[0];

  return {
    allergyId:             resource?.id           || '---',
    clinicalStatusCode:    clinicalStatus?.code    || '---',
    clinicalStatusDisplay: clinicalStatus?.display || '---',
    verificationCode:      verificationStatus?.code    || '---',
    verificationDisplay:   verificationStatus?.display || '---',
    allergyCode:           code?.code    || '---',
    allergyDisplay:        code?.display || '---',
    allergyText:           resource?.code?.text || '---',
    patientRef:            resource?.patient?.reference || '---',
    lastUpdated:           resource?.meta?.lastUpdated || '---'
  };
}

/**
 * Mapeo de un recurso FHIR FamilyMemberHistory a un objeto plano para la vista
 */
export function parseFhirFamilyMemberHistory(rawResource: any): FamilyHistoryData | null {
  if (!rawResource) return null;

  const resource = rawResource?.data ? rawResource.data : rawResource;

  if (resource?.resourceType !== 'FamilyMemberHistory') return null;

  const relationship  = resource?.relationship?.coding?.[0];
  const condition     = resource?.condition?.[0];
  const conditionCode = condition?.code?.coding?.[0];

  return {
    status:              resource?.status           || '---',
    relationshipCode:    relationship?.code        || '---',
    relationshipDisplay: relationship?.display     || '---',
    conditionCode:       conditionCode?.code       || '---',
    conditionDisplay:    conditionCode?.display    || '---',
    patientRef:          resource?.patient?.reference || '---'
  };
}

/**
 * Mapeo de un recurso FHIR MedicationStatement a un objeto plano para la vista
 */
export function parseFhirMedicationStatement(rawResource: any): MedicationData | null {
  if (!rawResource) return null;

  const resource = rawResource?.data ? rawResource.data : rawResource;

  if (resource?.resourceType !== 'MedicationStatement') return null;

  const medicationCoding = resource?.medicationCodeableConcept?.coding?.[0];

  return {
    status:              resource?.status                 || '---',
    medicationCode:      medicationCoding?.code         || '---',
    medicationDisplay:   medicationCoding?.display      || '---',
    medicationSystem:    medicationCoding?.system       || '---',
    patientRef:          resource?.subject?.reference    || '---'
  };
}

export function parseFhirEncounter(rawResource: any): EncounterData | null {
  if (!rawResource) return null;

  const resource = rawResource?.data ? rawResource.data : rawResource;
  if (resource?.resourceType !== 'Encounter') return null;

  const classInfo = resource?.class;
  
  // Extraer información de `type` (viene como array con múltiples codificaciones)
  let modalidad = '---', grupoServicio = '---', tipoServicio = '---', entornoAtencion = '---';
  (resource?.type || []).forEach((t: any) => {
    const sys = t?.coding?.[0]?.system || '';
    const display = t?.coding?.[0]?.display || '';
    if (sys.includes('ColombianTechModality')) modalidad = display;
    if (sys.includes('GrupoServicios')) grupoServicio = display;
    if (sys.includes('REPShealthcareServices')) tipoServicio = display;
    if (sys.includes('EntornoAtencion')) entornoAtencion = display;
  });

  // Extraer Causa Externa / Motivo
  const reasonCode = resource?.reasonCode?.[0]?.coding?.[0] || {};
  
  // Extraer diagnósticos del encuentro
  const diagnosticos: EncounterDiagnosis[] = (resource?.diagnosis || []).map((diag: any) => {
    const role = diag?.use?.coding?.[0]?.display || '---';
    const typeExt = diag?.extension?.find((e: any) => e?.url?.includes('ExtensionDiagnosisType'));
    const typeDisplay = typeExt?.valueCoding?.display || '---';
    return { role, typeDisplay };
  });

  // Identifier (N° Encuentro)
  const identifierSys = resource?.identifier?.find((i: any) => i.system?.includes('NamingSystem/Encounters')) || resource?.identifier?.[0];

  // Extraer datos de Hospitalización
  const hosp = resource?.hospitalization || {};
  const viaIngreso = hosp.admitSource?.coding?.[0]?.display || '---';
  const condicionEgreso = hosp.dischargeDisposition?.coding?.[0]?.display || '---';
  const destinoEgreso = hosp.destination?.reference || '---';
  const estadoEgresoExt = hosp.extension?.find((e: any) => e.url?.includes('ExtensionDischargeDeceasedStatus'));
  const estadoEgreso = estadoEgresoExt?.valueCoding?.display || '---';

  return {
    id: identifierSys?.value || resource?.id || '---',
    status: resource?.status || '---',
    classDisplay: classInfo?.display || '---',
    modalidad,
    grupoServicio,
    tipoServicio,
    entornoAtencion,
    startDate: resource?.period?.start || '---',
    endDate: resource?.period?.end || '---',
    causaExternaDisplay: reasonCode.display || '---',
    causaExternaCode: reasonCode.code || '---',
    diagnosticos,
    viaIngreso,
    condicionEgreso,
    estadoEgreso,
    destinoEgreso
  };
}
