import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
    providedIn: 'root'
})
export class IpService {
    private http = inject(HttpClient);

    /**
     * Detecta la dirección IP real de la máquina cliente (red local LAN IP, ej. 192.168.x.x / 10.x.x.x).
     * Combina candidatos WebRTC local ICE con fallback a la API backend y servicios de IP.
     */
    public async detectLocalIp(): Promise<string> {
        // 1. Intentar obtener la IP física de la interfaz LAN mediante WebRTC en el navegador
        const webrtcIp = await this.getWebRtcIp();
        if (webrtcIp) {
            // console.log('[IpService] IP local LAN detectada vía WebRTC:', webrtcIp);
            return webrtcIp;
        }

        // 2. Si WebRTC no la detecta, consultar backend Laravel (Nginx proxy headers)
        try {
            const token = sessionStorage.getItem('HL7_OAUTH_TOKEN') || localStorage.getItem('HL7_OAUTH_TOKEN');
            const res = await firstValueFrom(
                this.http.get<{ ip: string }>(`${environment.apiAuth.url}/api/client-ip`, {
                    headers: { Authorization: `Bearer ${token ?? ''}` }
                })
            );

            if (res?.ip && !res.ip.startsWith('172.27.') && !res.ip.startsWith('127.')) {
                return res.ip;
            }
        } catch (e) {}

        // 3. Fallback: servicio externo público de IP
        try {
            const res = await firstValueFrom(this.http.get<{ ip: string }>('https://api.ipify.org?format=json'));
            if (res?.ip) return res.ip;
        } catch (e) {}

        return '127.0.0.1';
    }

    /**
     * Extrae las IPs locales IPv4 de la máquina usando WebRTC (RTCPeerConnection).
     */
    private getWebRtcIp(): Promise<string | null> {
        return new Promise((resolve) => {
            try {
                const RTCPeerConnection =
                    window.RTCPeerConnection ||
                    (window as any).webkitRTCPeerConnection ||
                    (window as any).mozRTCPeerConnection;

                if (!RTCPeerConnection) {
                    resolve(null);
                    return;
                }

                const pc = new RTCPeerConnection({
                    iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
                });

                pc.createDataChannel('ip-check');
                let resolved = false;

                pc.onicecandidate = (event) => {
                    if (event && event.candidate && event.candidate.candidate) {
                        const candidate = event.candidate.candidate;
                        // Extraer direcciones IPv4
                        const matches = candidate.match(/\b(?:[0-9]{1,3}\.){3}[0-9]{1,3}\b/g);
                        if (matches) {
                            for (const ip of matches) {
                                // Priorizar IP de la tarjeta de red local (192.168.x.x, 10.x.x.x, 172.16-31.x.x)
                                if (
                                    !ip.startsWith('127.') &&
                                    !ip.startsWith('0.') &&
                                    !ip.startsWith('172.27.') // Ignorar subred del puente Docker dev
                                ) {
                                    if (!resolved) {
                                        resolved = true;
                                        try { pc.close(); } catch (e) {}
                                        resolve(ip);
                                        return;
                                    }
                                }
                            }
                        }
                    }
                };

                pc.createOffer()
                    .then((offer) => pc.setLocalDescription(offer))
                    .catch(() => {
                        if (!resolved) resolve(null);
                    });

                // Timeout de seguridad a los 1200ms
                setTimeout(() => {
                    if (!resolved) {
                        resolved = true;
                        try { pc.close(); } catch (e) {}
                        resolve(null);
                    }
                }, 1200);
            } catch (e) {
                resolve(null);
            }
        });
    }
}
