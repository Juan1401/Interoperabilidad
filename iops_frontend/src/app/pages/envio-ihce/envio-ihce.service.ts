import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

@Injectable({
    providedIn: 'root'
})
export class EnvioIhceService {
    private http = inject(HttpClient);
    // Reemplace con la URL base de su entorno si es dinámica
    private apiUrl = `${environment.apiAuth.url}/api/hl7`;

    private getAuthHeaders(): HttpHeaders {
        let headers = new HttpHeaders();
        const token = sessionStorage.getItem('HL7_OAUTH_TOKEN');

        if (token) {
            headers = headers.set('Authorization', `Bearer ${token}`);
        }

        // Fallback if token is in a different key (e.g. auth_token), adjust as needed
        return headers;
    }

    getTiposIdPacientes(): Observable<any> {
        return this.http.get<any>(`${this.apiUrl}/system/tipos-id-pacientes`, {
            headers: this.getAuthHeaders()
        });
    }

    getIhceCatEstadosEnvio(): Observable<any> {
        return this.http.get<any>(`${this.apiUrl}/system/ihce-cat-estados-envio`, {
            headers: this.getAuthHeaders()
        });
    }

    getIhceCatTiposRda(): Observable<any> {
        return this.http.get<any>(`${this.apiUrl}/system/ihce-cat-tipos-rda`, {
            headers: this.getAuthHeaders()
        });
    }

    buscarIngresos(filtros: any, page: number = 1): Observable<any> {
        return this.http.post<any>(`${this.apiUrl}/lista-ingreso?page=${page}`, filtros, {
            headers: this.getAuthHeaders().set('Content-Type', 'application/json')
        });
    }

    enviarRdaPaciente(payload: { ingreso: string; usuario_id: string }): Observable<any> {
        const body = new HttpParams().set('ingreso', payload.ingreso).set('usuario_id', payload.usuario_id);

        return this.http.post<any>(`${this.apiUrl}/rda/orquestador`, body.toString(), {
            headers: this.getAuthHeaders().set('Content-Type', 'application/x-www-form-urlencoded')
        });
    }

    obtenerJsonLog(ingresoId: number, tipoEndpoint: string, tipoRdaId?: number): Observable<any> {
        const payload: any = { ingreso_id: ingresoId };
        if (tipoRdaId !== undefined && tipoRdaId !== null) {
            payload.tipo_rda_id = tipoRdaId;
        }

        return this.http.post<any>(`${environment.apiAuth.url}/api/hl7/system/${tipoEndpoint}`, payload, { headers: this.getAuthHeaders().set('Content-Type', 'application/json') });
    }

    // /**
    //  * Envía el JSON del formulario manual RDA Paciente al orquestador HL7.
    //  * @param payload Objeto que mapea exactamente la estructura del FormGroup.
    //  */
    // postRdaPaciente(payload: any): Observable<any> {
    //     return this.http.post<any>(`${this.apiUrl}/rda/paciente`, payload, {
    //         headers: this.getAuthHeaders().set('Content-Type', 'application/json')
    //     });
    // }

    /**
     * Envía el JSON del formulario manual RDA Paciente al backend de Laravel.
     * @param payload Objeto con los datos demográficos y antecedentes del paciente.
     */
    postRdaPaciente(payload: any): Observable<any> {
        return this.http.post<any>(`${this.apiUrl}/rda/paciente/manual`, payload, {
            headers: this.getAuthHeaders().set('Content-Type', 'application/json')
        });
    }

    // ─── Catálogos HL7 (Listas desplegables para el formulario RDA) ───────────────

    /** Retorna los tipos de documento de identificación (CC, TI, RC, etc.) */
    getCatalogoTiposDocumento(): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/catalogs/tipos-documento`, {
            headers: this.getAuthHeaders()
        });
    }

    /** Retorna los grupos de género biológico (Masculino, Femenino, Indeterminado) */
    getCatalogoGeneros(): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/catalogs/generos`, {
            headers: this.getAuthHeaders()
        });
    }

    /** Retorna las zonas de residencia (Urbana / Rural) */
    getCatalogoZonas(): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/catalogs/zonas`, {
            headers: this.getAuthHeaders()
        });
    }

    /** Retorna todos los municipios activos de Colombia ordenados por nombre */
    getCatalogoMunicipios(): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/catalogs/municipios`, {
            headers: this.getAuthHeaders()
        });
    }

    /**
     * Búsqueda de diagnósticos CIE-10 por autocompletado.
     * @param query Término de búsqueda (mínimo 2 caracteres).
     */
    searchDiagnosticos(query: string): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/catalogs/search/diagnosticos`, {
            headers: this.getAuthHeaders().set('X-Skip-Loading', 'true'),
            params: { q: query }
        });
    }

    /**
     * Búsqueda de medicamentos DCI (Denominación Común Internacional) por autocompletado.
     * @param query Término de búsqueda (mínimo 2 caracteres).
     */
    searchMedicamentosDci(query: string): Observable<any[]> {
        return this.http.get<any>(`${this.apiUrl}/catalogs/search/medicamentos-dci`, {
            headers: this.getAuthHeaders().set('X-Skip-Loading', 'true'),
            params: { q: query }
        }).pipe(
            map(res => res.data || [])
        );
    }

    /** Retorna las Unidades de Medida (UMM) */
    getUnidadesMedida(): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/catalogs/unidades-medida`, {
            headers: this.getAuthHeaders()
        });
    }

    /** Retorna las Vías de Administración (VAD) */
    getViasAdministracion(): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/catalogs/vias-administracion`, {
            headers: this.getAuthHeaders()
        });
    }

    /** Retorna los Tipos de Alergia desde el catálogo dinámico */
    getTiposAlergia(): Observable<any[]> {
        return this.http.get<any>(`${this.apiUrl}/catalogs/dynamic/TipoAlergia`, {
            headers: this.getAuthHeaders()
        }).pipe(map(res => res.data || []));
    }

    /** Retorna los Parentescos Familiares desde el catálogo dinámico */
    getParentescos(): Observable<any[]> {
        return this.http.get<any>(`${this.apiUrl}/catalogs/dynamic/ParentescoAntecedente`, {
            headers: this.getAuthHeaders()
        }).pipe(map(res => res.data || []));
    }

    /** Retorna los Niveles de Severidad de Alergia (estático por ahora, sin catálogo SNOMED en BD) */
    getSeveridades(): Observable<any[]> {
        // Fallback estático mientras no exista el catálogo SeveridadAlergia en BD
        return new Observable(subscriber => {
            subscriber.next([
                { value: 'mild', label: 'Leve' },
                { value: 'moderate', label: 'Moderada' },
                { value: 'severe', label: 'Severa' }
            ]);
            subscriber.complete();
        });
    }
}

