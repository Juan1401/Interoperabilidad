# 📮 Guía de Uso - Colección Postman OAuth2

Esta guía explica cómo usar la colección de Postman para autenticación OAuth2 con el backend Hl7RestAPI.

---

## 🚀 Inicio Rápido

### 1. Importar la Colección

1. Abrir **Postman**
2. Clic en **Import** (esquina superior izquierda)
3. Seleccionar el archivo: `Hl7RestAPI-OAuth2.postman_collection.json`
4. Clic en **Import**

### 2. Configurar Variables de Entorno

#### Opción A: Crear Environment Manual

1. En Postman, ir a **Environments** (icono de engranaje)
2. Clic en **Create Environment**
3. Nombre: `Hl7 Development`
4. Agregar variables:

| Variable | Initial Value | Current Value |
|----------|--------------|---------------|
| `base_url` | `http://localhost:8002` | `http://localhost:8002` |
| `password_client_id` | `2` | `2` |
| `password_client_secret` | `YOUR_SECRET_HERE` | `YOUR_SECRET_HERE` |
| `client_credentials_id` | `3` | `3` |
| `client_credentials_secret` | `YOUR_SECRET_HERE` | `YOUR_SECRET_HERE` |
| `access_token` | *(dejar vacío)* | *(se llena automáticamente)* |
| `refresh_token` | *(dejar vacío)* | *(se llena automáticamente)* |
| `personal_access_token` | *(dejar vacío)* | *(se llena automáticamente)* |

5. **Save** y seleccionar el environment como activo

#### Opción B: Usar Variables de Colección

La colección ya incluye variables base. Solo necesitas actualizarlas:

1. En Postman, clic derecho en la colección `Hl7RestAPI - OAuth2 Authentication`
2. **Edit**
3. Ir a pestaña **Variables**
4. Actualizar los valores según tu configuración

---

## 🔑 Obtener Client ID y Secret

### Desde la Base de Datos

```bash
# Conectar al contenedor
docker exec -it hl7restapi_dev bash

# Ejecutar tinker
php artisan tinker

# Obtener client ID y secret para Password Grant
DB::table('oauth_clients')->where('password_client', 1)->get(['id', 'secret']);

# Obtener client ID y secret para Client Credentials
DB::table('oauth_clients')->where('personal_access_client', 0)->where('password_client', 0)->get(['id', 'secret']);
```

### Desde la Interfaz Web

1. Navegar a: `http://localhost:8002/client`
2. Login con:
   - Email: `admin@fciclubnoel.com`
   - Password: `S1N3RG1@S`
3. Ver la tabla "Clientes OAuth Registrados"
4. Copiar ID y Secret del cliente deseado

---

## 📋 Flujos de Autenticación

### Flujo 1: Password Grant (Usuario + Password)

**Uso:** Aplicaciones first-party que tienen credenciales del usuario.

#### Pasos:

1. **Ejecutar:** `1. Authentication > Password Grant - Obtener Token`
   - Automáticamente guarda `access_token` y `refresh_token`

2. **Usar el token:** Ejecutar `4. Protected Routes > Get User Info`
   - Verás la información del usuario autenticado

3. **Renovar token:** `1. Authentication > Refresh Token`
   - Cuando el access_token expire

#### Ejemplo de Response:

```json
{
  "token_type": "Bearer",
  "expires_in": 31536000,
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refresh_token": "def50200e8f4a1..."
}
```

---

### Flujo 2: Client Credentials (Máquina a Máquina)

**Uso:** Servicios backend que no actúan en nombre de un usuario.

#### Pasos:

1. **Ejecutar:** `1. Authentication > Client Credentials Grant - Obtener Token`
   - Guarda `client_access_token`

2. **Usar:** Cambiar `{{access_token}}` por `{{client_access_token}}` en las rutas protegidas

#### Características:

- ✅ No requiere usuario
- ❌ No genera refresh_token
- ✅ Ideal para scripts automatizados

---

### Flujo 3: Personal Access Tokens (PAT)

**Uso:** Tokens de larga duración para el usuario, sin renovación automática.

#### Pasos:

1. **Autenticarse primero** con Password Grant

2. **Ejecutar:** `2. Personal Access Tokens > Crear Personal Access Token`
   - Guarda automáticamente en `{{personal_access_token}}`

3. **Usar el PAT:** `4. Protected Routes > Get User Info (PAT)`

4. **Gestionar PATs:**
   - Listar: `2. Personal Access Tokens > Listar Personal Access Tokens`
   - Revocar: `2. Personal Access Tokens > Revocar Personal Access Token`

#### Ventajas:

- ✅ No expira por defecto
- ✅ Más simple que Password Grant
- ✅ Revocable manualmente
- ❌ No tiene refresh_token

---

## 🔄 Renovación de Tokens

### ¿Cuándo renovar?

El `expires_in` te indica cuántos segundos es válido el token (por defecto: 1 año = 31536000 segundos).

### Flujo de Renovación:

1. **Intentar** usar el `access_token` actual
2. Si obtienes **401 Unauthorized**:
   - Ejecutar `1. Authentication > Refresh Token`
   - Esto genera nuevo `access_token` y `refresh_token`
3. **Reintentar** la petición original

### Script de Test (Postman):

```javascript
// En el tab "Tests" de cualquier request protegido
if (pm.response.code === 401) {
    // Token expirado, hacer refresh automático
    pm.execution.setNextRequest("Refresh Token");
}
```

---

## 🛠️ Scripts Automáticos en Postman

La colección incluye **scripts de test** que automatizan:

### 1. Guardar tokens automáticamente

```javascript
// En "Password Grant - Obtener Token"
if (pm.response.code === 200) {
    var jsonData = pm.response.json();
    pm.environment.set("access_token", jsonData.access_token);
    pm.environment.set("refresh_token", jsonData.refresh_token);
}
```

### 2. Logs informativos en consola

```javascript
console.log("✅ Token guardado:", jsonData.access_token);
```

**Ver Console:** `View > Show Postman Console` (Alt+Ctrl+C)

---

## 📊 Endpoints Disponibles

### Autenticación

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/api/login` | POST | Login simple (sin OAuth) |
| `/oauth/token` | POST | Obtener token OAuth2 |
| `/api/user` | GET | Info del usuario autenticado |

### OAuth Clients

| Endpoint | Método | Auth | Descripción |
|----------|--------|------|-------------|
| `/oauth/clients` | GET | Bearer | Listar clientes |
| `/oauth/clients` | POST | Bearer | Crear cliente |
| `/oauth/clients/:id` | PUT | Bearer | Actualizar cliente |
| `/oauth/clients/:id` | DELETE | Bearer | Eliminar cliente |

### Personal Access Tokens

| Endpoint | Método | Auth | Descripción |
|----------|--------|------|-------------|
| `/oauth/personal-access-tokens` | GET | Bearer | Listar PATs |
| `/oauth/personal-access-tokens` | POST | Bearer | Crear PAT |
| `/oauth/personal-access-tokens/:id` | DELETE | Bearer | Revocar PAT |

---

## 🐛 Troubleshooting

### Error 401: "Unauthenticated"

**Causas:**
- Token expirado
- Token inválido
- Token no incluido en header

**Solución:**
1. Verificar que la variable `{{access_token}}` tenga valor
2. Renovar token con Refresh Token
3. Verificar header `Authorization: Bearer {{access_token}}`

---

### Error 400: "Invalid client"

**Causas:**
- Client ID o Secret incorrectos
- Cliente no existe en la BD

**Solución:**
1. Verificar Client ID y Secret en BD:
   ```bash
   docker exec hl7restapi_dev php artisan tinker
   DB::table('oauth_clients')->get(['id', 'secret']);
   ```
2. Actualizar variables de entorno en Postman

---

### Error 400: "Invalid grant"

**Causas:**
- Refresh token inválido o expirado
- Grant type incorrecto

**Solución:**
1. Hacer login de nuevo con Password Grant
2. Verificar que `grant_type` sea correcto

---

### Error 422: "The given data was invalid"

**Causas:**
- Campos requeridos faltantes
- Formato de datos incorrecto

**Solución:**
1. Verificar el body de la petición
2. Revisar documentación del endpoint

---

## 📝 Ejemplos de Uso Real

### Ejemplo 1: Login y Consultar Perfil

```javascript
// 1. Password Grant
POST http://localhost:8002/oauth/token
Body: {
    grant_type: "password",
    client_id: "2",
    client_secret: "secret_aqui",
    username: "admin@fciclubnoel.com",
    password: "S1N3RG1@S"
}

// Response incluye: access_token, refresh_token

// 2. Get User Info
GET http://localhost:8002/api/user
Headers: {
    Authorization: "Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

### Ejemplo 2: Crear PAT para Script

```javascript
// 1. Autenticarse primero (Password Grant)

// 2. Crear PAT
POST http://localhost:8002/oauth/personal-access-tokens
Headers: {
    Authorization: "Bearer {{access_token}}"
}
Body: {
    name: "Script de ETL",
    scopes: []
}

// 3. Usar el PAT en tu script Python/Node/etc
headers = {
    "Authorization": f"Bearer {personal_access_token}"
}
```

---

## 🔐 Mejores Prácticas

1. **Nunca commitear tokens** al repositorio
2. **Usar environments** separados para dev/prod
3. **Rotar secrets** regularmente en producción
4. **Revocar PATs** que ya no se usan
5. **Usar HTTPS** en producción (nunca HTTP)
6. **Implementar rate limiting** en la API
7. **Logs de auditoría** para accesos OAuth

---

## 📚 Referencias

- [OAuth 2.0 RFC](https://tools.ietf.org/html/rfc6749)
- [Laravel Passport Docs](https://laravel.com/docs/10.x/passport)
- [Postman OAuth 2.0](https://learning.postman.com/docs/sending-requests/authorization/#oauth-20)

---

**Creado:** 22 de diciembre de 2025  
**Versión:** 1.0  
**Compatible con:** Laravel 10.x + Passport 11.x
