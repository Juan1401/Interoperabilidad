import { Injectable } from '@angular/core';
import * as CryptoJS from 'crypto-js';

@Injectable({
    providedIn: 'root'
})
export class CryptoService {

    // TODO: Se recomienda mover esta llave a un archivo environment.ts para mayor seguridad.
    // private readonly secretKey = '12345678901234567890123456789012';
    private secretKey = '';

    /**
     * Establece la llave secreta para los algoritmos criptográficos.
     */
    public setSecretKey(key: string): void {
        this.secretKey = key;
    }

    /**
     * Desencripta un token JWT o Base64 URL Safe y devuelve su carga útil.
     * Encapsula la lógica de desencriptación CBC que antes residía en el componente principal.
     * 
     * @param tokenParam El token Base64 URL Safe proveniente de la URL
     * @returns El objeto JSON desencriptado original, o null si el token es inválido
     */
    public decryptTokenPayload<T = any>(tokenParam: string): T | null {
        try {
            // 1. Revertir caracteres seguros para URL (- por +, _ por /).
            // IMPORTANTE: Angular Router convierte '+' en espacios (' ') al leer Query Params. Debemos restaurarlos a '+'.
            let b64Token = tokenParam.replace(/-/g, '+').replace(/_/g, '/').replace(/ /g, '+');

            // 2. Añadir padding '=' necesario para base64
            while (b64Token.length % 4 !== 0) {
                b64Token += '=';
            }

            // 3. Decodificar Base64 a string Latin1 para procesar los bytes
            const decodedRawStr = CryptoJS.enc.Base64.parse(b64Token).toString(CryptoJS.enc.Latin1);

            if (decodedRawStr.length <= 16) {
                throw new Error('Token demasiado corto para contener un IV válido y un Payload');
            }

            // 4. Extraer vector de Inicialización (IV) de los primeros 16 caracteres
            const ivStr = decodedRawStr.slice(0, 16);
            const iv = CryptoJS.enc.Latin1.parse(ivStr);

            // 5. El resto es el string Base64 del ciphertext
            const ciphertextBase64Str = decodedRawStr.slice(16);
            const ciphertext = CryptoJS.enc.Base64.parse(ciphertextBase64Str);

            // 6. Preparar Llave y parámetros del Cifrado
            const key = CryptoJS.enc.Utf8.parse(this.secretKey);
            const cipherParams = CryptoJS.lib.CipherParams.create({
                ciphertext: ciphertext
            });

            // 7. Ejecutar Desencriptación AES
            const decrypted = CryptoJS.AES.decrypt(cipherParams, key, {
                iv: iv,
                mode: CryptoJS.mode.CBC,
                padding: CryptoJS.pad.Pkcs7
            });

            // 8. Convertir a UTF-8 comprobando que la desencriptación fue exitosa (Evita Malformed UTF-8)
            let decryptedStr: string;
            try {
                decryptedStr = decrypted.toString(CryptoJS.enc.Utf8);
                if (!decryptedStr) throw new Error('Data vacía o corrupta');
            } catch (cryptoErr) {
                throw new Error('La llave AES / IV son incorrectos, o el token está corrupto (Malformed UTF-8)');
            }

            // 9. Transformar de string a su JSON objeto subyacente
            return JSON.parse(decryptedStr) as T;

        } catch (error: any) {
            console.warn('[CryptoService] Fallo al desencriptar token:', error.message);
            return null; // Retornamos null elegantemente para no romper el ciclo de vida de la App
        }
    }
}
