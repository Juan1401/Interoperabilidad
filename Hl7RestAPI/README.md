<p align="center"><img src="https://res.cloudinary.com/dtfbvvkyp/image/upload/v1566331377/laravel-logolockup-cmyk-red.svg" width="400"></p>

<p align="center">
<a href="https://travis-ci.org/laravel/framework"><img src="https://travis-ci.org/laravel/framework.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://poser.pugx.org/laravel/framework/d/total.svg" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://poser.pugx.org/laravel/framework/v/stable.svg" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://poser.pugx.org/laravel/framework/license.svg" alt="License"></a>
</p>

## Acerca de Laravel

Laravel es un marco de aplicación web con una sintaxis expresiva y elegante. Creemos que el desarrollo debe ser una experiencia placentera y creativa para ser realmente satisfactorio. Laravel elimina el dolor del desarrollo al facilitar las tareas comunes utilizadas en muchos proyectos web, como:

# API RESTFul FCI CLUB NOEL [INFO](https://www.redhat.com/es/topics/api/what-is-a-rest-api)

- [Motor de enrutamiento simple y rápido](https://laravel.com/docs/routing).
- [Potente contenedor de inyección de dependencia](https://laravel.com/docs/container).
- Múltiples back-ends para [session](https://laravel.com/docs/session) y almacenamiento [cache](https://laravel.com/docs/cache)
- Expresiva e intuitiva  [DataBase ORM](https://laravel.com/docs/eloquent).
- Independientes de la base de datos [Migraciones de esquemas](https://laravel.com/docs/migrations).
- [Robusto procesamiento de trabajos en segundo plano](https://laravel.com/docs/queues).
- [Transmisión de eventos en tiempo real](https://laravel.com/docs/broadcasting).

Laravel es accesible, potente y proporciona las herramientas necesarias para aplicaciones grandes y robustas.

## Instalacion de la aplicacion

### Version de PHP: 7.2.34
### Version de POSTGRESQL: 13
### Version de LARAVEL: 7.29 
### Version de LARAVEL PASSPORT: 9.4
### Version de LARAVEL UI: 2.5


1. Se descarga el proyecto con el versionador git
```bash
git init
git remote add origin http://192.168.0.60/asistencial/hl7restapi.git
```
2. Se crea el archivo `.env` apartir del archivo `.env.example` y se configura los datos de la base de datos la linea `APP_URL=http://metatron.fciclubnoel.com`
```bash
// Comandos para ejecutar composer
php72 /usr/local/bin/composer install <-> composer install

// comando para instalacion por primera vez y desarrollo
npm install
// comando para produccion despues de la instalacion por primera vez
npm ci
```
3. Se genera Application key set successfully ejecutando el siguiente comando.

* Crear una key para ejecutar la app en laravel
```bash
php72 artisan key:generate
php72 artisan passport:keys
chmod -R 775 storage/oauth-private.key
chmod 600 storage/oauth-private.key
```
4. Para iniciar el  
```bash
php72 artisan serve

php72 artisan serve --host=metatron.fciclubnoel.com --port=8082
```

5. Dar permiso a las carpetas
* Una vez en la consola dar los siguientes permisos
* Cambiar el propietario y grupo de los archivos y carpetas a www-data

```bash
mkdir -p Models
mkdir -p public/upload
```

* Otorgar permisos de escritura a la carpeta de almacenamiento y al archivo de registro
```bash
chmod -R 775 storage
chmod -R 775 storage/logs/
```

* Otorgar permisos de escritura a la carpeta de carga
```bash
chmod -R 775 public/upload
mkdir -p storage/logs
chmod -R 775 storage/logs
mkdir -p bootstrap/cache
chmod -R 775 bootstrap/cache
```

```bash
php72 artisan migrate
php72 artisan db:seed
```
* Crear cliente
```bash
php72 artisan passport:client --password
```
* Crear Personal Access Tokens
```bash
php72 artisan passport:client --personal
```


* Para limpiar la cache de las view cuando estemos trabajando Blade
```bash
php72 artisan view:clear
```

* Para ver los servicios disponibles
```bash
php72 artisan route:list
```

## Licencia

El marco de Laravel es un software de código abierto con licencia [MIT](https://opensource.org/licenses/MIT)
