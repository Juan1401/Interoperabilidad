import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface ConsultaPacientePayload {
    tipo_documento: string;
    numero_documento: string;
    tipo_doc_usuario: string;
    numero_doc_usuario: string;
}

/** Payload para el Proxy Gateway de documentos externos del Ministerio IHCE. */
export interface DocumentoExternoPayload {
    /** URL completa y absoluta del recurso en IHCE. Ej: https://sandbox.ihcecol.gov.co/ihce/DocumentReference/{uuid}/0 */
    url: string;
}

/** Estructura de la respuesta del backend para un documento externo exitoso. */
export interface DocumentoExternoResponse {
    status: number;
    success: boolean;
    message: string;
    /** Si es JSON (FHIR): objeto completo. Si es binario (PDF/imagen): { raw_body: string, encoding: 'base64', content_len: number } */
    data: FhirDocumentoExternoData | DocumentoBinarioData | null;
}

/** Documento externo cuando el Ministerio responde con JSON/FHIR. */
export type FhirDocumentoExternoData = Record<string, unknown>;

/** Documento externo cuando el Ministerio responde con contenido binario (PDF, imagen, etc.). */
export interface DocumentoBinarioData {
    raw_body: string; // Contenido en Base64
    encoding: 'base64';
    content_len: number; // Tamaño en bytes del contenido original
}

/** Payload para registrar la auditoría de visualización de un RDA. */
export interface RdaAuditPayload {
    user_id: number;
    patient_document_type: string;
    patient_document_number: string;
    tipo_rda_id: number;
    rda_id: string;
}

@Injectable({
    providedIn: 'root'
})
export class ConsultarIhceService {
    private http = inject(HttpClient);
    // Reemplace con la URL base de su entorno si es dinámica
    private apiUrl = `${environment.apiAuth.url}/api/hl7`;

    private getAuthHeaders(): HttpHeaders {
        let headers = new HttpHeaders();
        const token = sessionStorage.getItem('HL7_OAUTH_TOKEN');

        if (token) {
            headers = headers.set('Authorization', `Bearer ${token}`);
        }

        return headers;
    }

    consultarPacienteExacto(payload: ConsultaPacientePayload): Observable<any> {
        return this.http.post<any>(`${this.apiUrl}/consulta-ministerio/paciente-exacto`, payload, {
            headers: this.getAuthHeaders().set('Content-Type', 'application/json')
        });
    }

    consultarPacienteSimilar(payload: ConsultaPacientePayload): Observable<any> {
        return this.http.post<any>(`${this.apiUrl}/consulta-ministerio/paciente-similar`, payload, {
            headers: this.getAuthHeaders().set('Content-Type', 'application/json')
        });
    }

    consultarRdaPaciente(payload: ConsultaPacientePayload): Observable<any> {
        return this.http.post<any>(`${this.apiUrl}/consulta-ministerio/rda-paciente`, payload, {
            headers: this.getAuthHeaders().set('Content-Type', 'application/json')
        });
    }

    consultarRdaEncuentros(payload: ConsultaPacientePayload): Observable<any> {
        return this.http.post<any>(`${this.apiUrl}/consulta-ministerio/rda-encuentros-clinicos-fechas`, payload, {
            headers: this.getAuthHeaders().set('Content-Type', 'application/json')
        });
    }

    consultarRecurso(resourcePath: string): Observable<any> {
        const path = resourcePath.startsWith('/') ? resourcePath : `/${resourcePath}`;
        return this.http.post<any>(
            `${this.apiUrl}/consulta-ministerio/recurso`,
            { resource_path: path },
            {
                headers: this.getAuthHeaders().set('Content-Type', 'application/json')
            }
        );
    }

    /**
     * Proxy Gateway — Consulta un documento externo del Ministerio IHCE por su URL completa.
     *
     * Endpoint: POST /api/hl7/consulta-ministerio/documento-externo
     * La URL se envía tal cual viene del campo `content[].attachment.url` del DocumentReference FHIR.
     * El backend puede responder con JSON/FHIR o con contenido binario codificado en Base64.
     *
     * @param url URL absoluta del documento en IHCE. Ej: https://sandbox.ihcecol.gov.co/ihce/DocumentReference/{uuid}/0
     */
    consultarDocumentoExterno(url: string): Observable<DocumentoExternoResponse> {
        const payload: DocumentoExternoPayload = { url };
        return this.http.post<DocumentoExternoResponse>(`${this.apiUrl}/consulta-ministerio/documento-externo`, payload, { headers: this.getAuthHeaders().set('Content-Type', 'application/json') });
    }

    /**
     * Registra en auditoría la visualización de un documento RDA por parte del usuario.
     * Fire-and-forget: no bloquea la UI en caso de fallo.
     */
    registrarAuditoriaRda(payload: RdaAuditPayload): void {
        // console.log('[Auditoría RDA] Payload enviado:', JSON.stringify(payload, null, 2));
        this.http.post<any>(`${this.apiUrl}/consulta-ministerio/audit/rda-view`, payload, { headers: this.getAuthHeaders().set('Content-Type', 'application/json') }).subscribe({
            next: (res) => console.log('[Auditoría RDA] ✅ Registrado correctamente:', res),
            error: (err) => console.warn('[Auditoría RDA] ❌ Fallo silencioso al registrar:', err?.error ?? err)
        });
    }
}
