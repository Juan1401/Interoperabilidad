---
trigger: always_on
description: Guía maestra para agentes de IA en el proyecto HL7 Interoperabilidad
---

🤖 Guía Maestra de Interoperabilidad (ONE SOLUTION)
Este documento es la "Fuente de Verdad" absoluta para tu comportamiento como agente de IA en este proyecto. Debes seguir estas instrucciones de forma estricta para garantizar la integridad, escalabilidad y seguridad del ecosistema.

1. Identidad y Arquitectura Core
Este es un ecosistema de interoperabilidad clínica basado en el estándar HL7 FHIR.

Backend (/iops_api): Laravel 10 (PHP 8.2). Actúa como motor de integración y API REST.

Frontend (/iops_frontend): Angular 21 + PrimeNG + TailwindCSS. Actúa como interfaz de gestión.

Infraestructura: Orquestación estricta con Docker Compose. Nginx funciona como proxy inverso.

2. Mandatos Generales de Comportamiento (OBLIGATORIO)

Idioma: Todas tus explicaciones, comentarios en el código (//) y documentación técnica deben ser generados en Español (Colombia).

Rutas de Salida (Logs/Tests): Si generas scripts de pruebas o logs, NUNCA los guardes en la raíz. Utiliza siempre:

Backend: iops_api/cache/

Frontend: iops_frontend/cache/

Validación de Base de Datos: NUNCA asumas ni inventes nombres de tablas o columnas. Verifica siempre la estructura de PostgreSQL (específicamente el esquema ihce) antes de generar consultas o migraciones.

3. Reglas Estrictas de Modificación de Código

Inserción Segura: Cuando vayas a crear o sugerir nuevas funciones o métodos, DEBES agregarlos estrictamente al final del archivo o en las últimas líneas de la clase correspondiente. NUNCA reescribas el archivo completo a menos que se te pida explícitamente, para evitar alterar código funcional existente.

4. Estándares Técnicos de Desarrollo

Backend (Laravel): Aplica el estándar PSR-12. Usa estrictamente Inyección de Dependencias. Todas las migraciones nuevas deben apuntar al esquema ihce.

Frontend (Angular): Utiliza Angular Signals y componentes Standalone (standalone: true). Usa la nueva sintaxis de control de flujo (@if, @for). Mantén el ecosistema visual atado a PrimeNG apoyado en clases utilitarias de TailwindCSS.

Seguridad: La autenticación sigue estrictamente el flujo OAuth2 (Laravel Passport) documentado en POSTMAN_GUIDE.md. No inventes flujos JWT paralelos.

5. Flujos de Trabajo y Validaciones

Despliegue: El script deploy-dev.sh y deploy-prod.sh es la ley de cómo se inicializa el entorno (permisos, llaves, migraciones). Analízalo SIEMPRE antes de proponer cambios de infraestructura.

Reglas HL7 FHIR: Antes de programar nuevos endpoints de salud, es obligatorio consultar las definiciones base ubicadas en /definitions_hl7_json.

Pruebas (Testing): Utiliza exclusivamente las colecciones de Bruno ubicadas en /Bruno como referencia para conocer el payload y comportamiento esperado de la API.

6. Restricciones de Infraestructura de Red

El acceso desde los contenedores al Host (Base de Datos) debe usar obligatoriamente host.docker.internal o la IP configurada en tu archivo .env.

Queda estrictamente prohibido modificar los archivos de configuración de Nginx (/nginx) sin validar previamente el impacto total en el proxy inverso.

7. Codificaci�n de Archivos

Codificaci�n: Todo archivo creado, generado o modificado debe guardarse obligatoriamente en codificaci�n UTF-8. Nunca utilizar ISO-8859-1 o Latin-1.
Saltos de L�nea: Configurar estrictamente en LF (Unix), cero CRLF.
