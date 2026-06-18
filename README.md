# Proyecto de Interoperabilidad HL7 - FCI Club Noel

Este proyecto facilita la interoperabilidad de datos de salud utilizando el estándar HL7 FHIR, integrando un backend robusto en Laravel y un frontend moderno en Angular.

## 🏗️ Arquitectura del Sistema

El proyecto está compuesto por los siguientes servicios:

- **Backend:** API REST construida con **Laravel 10** y **PHP 8.2**.
- **Frontend:** Aplicación SPA construida con **Angular 21** y **PrimeNG**.
- **Base de Datos:** **PostgreSQL** con esquema específico `ihce`.
- **Infraestructura:** Orquestación con **Docker Compose** y un proxy inverso **Nginx**.

---

## 🚀 Inicio Rápido (Entorno de Desarrollo)

La forma más rápida de poner en marcha el proyecto es utilizando el script de despliegue automatizado.

### 1. Requisitos Previos
- Docker y Docker Compose instalados.
- Git.

### 2. Configuración de Variables de Entorno
Copia los archivos de ejemplo y configura las credenciales necesarias (especialmente las del Ministerio de Salud si aplica):

```bash
# En la raíz del proyecto
cp .env.example .env

# En el directorio del Backend
cp Hl7RestAPI/.env.example Hl7RestAPI/.env

# El script de despliegue se encargará de crear el environment.ts del Frontend
```

### 3. Ejecución del Despliegue
Ejecuta el script de desarrollo para levantar los contenedores y configurar el sistema (permisos, migraciones, llaves OAuth):

```bash
# Dar permisos de ejecución si es necesario
cd ..
chown -R fcl-adm:fcl-adm HL7interoperabilidad
chmod -R 777 HL7interoperabilidad
cd HL7interoperabilidad


# Ejecutar el despliegue completo
# DEV
bash deploy-dev.sh
# PROD
bash deploy-prod.sh
```

> [!IMPORTANT]
> **Base de Datos:** El archivo `docker-compose.dev.yml` no incluye un contenedor de PostgreSQL. Debes tener una instancia de PostgreSQL accesible (local o remota) y configurar el `DB_HOST` en tu archivo `Hl7RestAPI/.env`. Si usas Docker en Windows/Mac, puedes usar `host.docker.internal` para referirte a tu máquina local.

**Opciones del script:**
- `--si-seeder`: Ejecuta los seeders para poblar la base de datos.
- `--solo-frontend`: Despliega únicamente el servicio de Angular.
- `--solo-backend`: Despliega únicamente el servicio de Laravel y base de datos.

---

## 🛠️ Configuración Manual

Si prefieres configurar los servicios de forma independiente:

### Backend (Laravel)
1. Instalar dependencias: `composer install`
2. Generar llave de app: `php artisan key:generate`
3. Configurar base de datos en `.env` (PostgreSQL).
4. Ejecutar migraciones: `php artisan migrate`
5. Instalar Passport: `php artisan passport:install`
6. Asegurar permisos en `storage` y `bootstrap/cache`.

### Frontend (Angular)
1. Instalar dependencias: `npm install`
2. Configurar entorno: `cp src/environments/environment.example.ts src/environments/environment.ts`
3. Iniciar servidor: `ng serve`

---

## 🌐 Puertos e Infraestructura

| Servicio | Puerto Host (Dev) | Puerto Host (Prod) | URL Local sugerida |
| :--- | :--- | :--- | :--- |
| **Frontend** | 4202 | 4203 | `http://localhost:4202` |
| **Backend API** | 8002 | 8003 | `http://localhost:8002` |
| **Nginx Proxy** | 80 | 80 | `http://localhost` |

---

## 📂 Estructura del Proyecto

- `/HL7Frontend`: Código fuente de la aplicación Angular.
- `/Hl7RestAPI`: Código fuente de la API Laravel.
- `/definitions_hl7_json`: Definiciones FHIR (CodeSystems, CapabilityStatements, etc.).
- `/Bruno`: Colecciones de peticiones API para pruebas (alternativa moderna a Postman).
- `/nginx`: Configuraciones de servidor para diferentes entornos.

---

## 🧪 Pruebas y Consumo de la API

El proyecto incluye herramientas para facilitar las pruebas de los endpoints:

### Colecciones de Bruno (Recomendado)
En la carpeta `/Bruno` encontrarás colecciones listas para importar en **Bruno** (un cliente API de código abierto). Estas colecciones están actualizadas con los flujos de HL7 y autenticación.

### Guía de Postman y OAuth2
Para detalles específicos sobre cómo obtener tokens de acceso (Password Grant, Client Credentials) y configurar **Postman**, consulta la guía detallada:
👉 [POSTMAN_GUIDE.md](./POSTMAN_GUIDE.md)

## ⏱️ Configuración de Timeouts y Performance

El flujo de generación y envío del **RDA (Resumen Digital de Atención)** involucra múltiples capas con timeouts independientes. Si cualquier capa expira antes que las demás, la petición se corta. Esta sección documenta la configuración actual y cómo ajustarla.

### Capas de Timeout

El request del RDA atraviesa la siguiente cadena:

```
Frontend (Angular) → Nginx (Proxy) → Apache + PHP → Laravel HTTP Client → APIs Externas
                                                  └→ Browsershot (Chromium)
```

| Capa | Archivo de Configuración | Timeout Actual | Descripción |
| :--- | :--- | :--- | :--- |
| **Nginx (Proxy)** | `nginx/nginx-dev.conf` / `nginx-prod.conf` | **900s** | `proxy_read_timeout` / `proxy_send_timeout` para la API |
| **PHP** | `Hl7RestAPI/docker/php/custom.ini` | **900s** | `max_execution_time` — tiempo máximo de ejecución de un script |
| **Laravel → IHCE** | `Hl7RestAPI/config/services.php` + `.env` | **120s** | `IHCE_TIMEOUT` — timeout HTTP hacia el Ministerio de Salud |
| **Laravel → SIIS** | `Hl7RestAPI/app/Services/Hl7/RdaService.php` | **60s** | Petición HTTP al servidor SIIS Legacy (Epicrisis) |
| **Browsershot** | `Hl7RestAPI/app/Services/Hl7/RdaService.php` | **~30s** | `waitUntilNetworkIdle()` — Chromium renderizando HTML a PDF |

### PHP (`custom.ini`)

La configuración personalizada de PHP se encuentra en [`Hl7RestAPI/docker/php/custom.ini`](./Hl7RestAPI/docker/php/custom.ini) y se copia al contenedor durante el build del Dockerfile:

```ini
max_execution_time = 900    ; 15 minutos (procesos pesados con Browsershot)
max_input_time = 120        ; Parseo de datos de entrada
memory_limit = 512M         ; Bundles FHIR grandes + PDF en Base64
post_max_size = 50M
upload_max_filesize = 50M
```

> **⚠️ IMPORTANTE:** Sin este archivo, PHP usaría su default de **30 segundos**, insuficiente para el flujo del RDA que incluye generación de PDF con Chromium Headless (Browsershot). Un PDF de epicrisis complejo puede tardar hasta 10 minutos.

### Nginx

Los timeouts del proxy inverso para la API están configurados en:
- **Desarrollo:** [`nginx/nginx-dev.conf`](./nginx/nginx-dev.conf)
- **Producción:** [`nginx/nginx-prod.conf`](./nginx/nginx-prod.conf)

```nginx
proxy_connect_timeout 900s;
proxy_read_timeout    900s;
proxy_send_timeout    900s;
```

> **⚠️ NOTA:** Los timeouts de Nginx **deben ser iguales o mayores** al `max_execution_time` de PHP. Si Nginx tiene un timeout menor, cortará la conexión aunque PHP siga procesando.

### Variables de Entorno (IHCE)

El timeout de las peticiones HTTP al Ministerio (IHCE) es configurable desde el `.env`:

```env
IHCE_TIMEOUT=120
```

Este valor se lee en `config/services.php` como `services.ihce.timeout` y se usa en todos los métodos `sendRda*()` de los servicios HL7:

```php
$apiTimeout = $config['timeout'] ?? 120;
Http::connectTimeout($apiTimeout)->timeout($apiTimeout)->post(...);
```

### Escenarios Importantes

| Escenario | Tiempo estimado | Riesgo |
| :--- | :--- | :--- |
| RDA Paciente (individual) | 5-20s | ✅ Bajo |
| RDA Consulta/Urgencias/Hospitalización | 10-600s | ⚠️ Depende de la Epicrisis |
| Orquestador (Paciente + RDA Secundario) | 15-600s+ | ⚠️ Suma secuencial de ambos |
| Epicrisis con Browsershot desactivada (Feature Flag) | ~0s | ✅ Se salta SIIS + Chrome |

> **📝 NOTA:** El **Feature Flag** `IHCE_sw_envio_epicrisis` en la tabla `public.system_modulos_variables` controla si se genera el PDF real de la Epicrisis o se usa un PDF de prueba (fallback). Con el flag apagado (`≠ 1`), se omite la llamada al SIIS y a Browsershot, reduciendo drásticamente el tiempo de ejecución.

### ¿Cómo ajustar los timeouts?

1. **PHP:** Editar `Hl7RestAPI/docker/php/custom.ini` y hacer rebuild de la imagen (`docker compose build api`).
2. **Nginx:** Editar `nginx/nginx-dev.conf` o `nginx-prod.conf` y reiniciar el contenedor Nginx.
3. **IHCE:** Cambiar `IHCE_TIMEOUT` en `Hl7RestAPI/.env` y ejecutar `php artisan config:clear` dentro del contenedor.

---

## 📝 Reglas de Desarrollo

Este proyecto sigue reglas específicas de desarrollo asistido por IA (ver `.agents/rules/GEMINI.md`):
- Los logs y tests de Backend se guardan en `Hl7RestAPI/cache`.
- Los logs y tests de Frontend se guardan en `HL7Frontend/cache`.
- El idioma oficial para documentación y respuestas técnicas es **Español (Colombia)**.

---

## ⚖️ Licencia
Este proyecto es software de código abierto bajo la licencia [MIT](https://opensource.org/licenses/MIT).
