import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
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

  enviarRdaPaciente(payload: { ingreso: string, usuario_id: string }): Observable<any> {
    const body = new HttpParams()
      .set('ingreso', payload.ingreso)
      .set('usuario_id', payload.usuario_id);

    return this.http.post<any>(`${this.apiUrl}/rda/orquestador`, body.toString(), {
      headers: this.getAuthHeaders().set('Content-Type', 'application/x-www-form-urlencoded')
    });
  }

  obtenerJsonLog(ingresoId: number, tipoEndpoint: string, tipoRdaId?: number): Observable<any> {
    const payload: any = { ingreso_id: ingresoId };
    if (tipoRdaId !== undefined && tipoRdaId !== null) {
      payload.tipo_rda_id = tipoRdaId;
    }

    return this.http.post<any>(
      `${environment.apiAuth.url}/api/hl7/system/${tipoEndpoint}`,
      payload,
      { headers: this.getAuthHeaders().set('Content-Type', 'application/json') }
    );
  }

}
