#!/bin/bash
# deploy-dev.sh
# Automatización del despliegue en entorno de DESARROLLO

RUN_SEEDERS=false
BUILD_FRONTEND=true
BUILD_BACKEND=true

while [[ "$#" -gt 0 ]]; do
    case $1 in
        --si-seeder)
            RUN_SEEDERS=true
            ;;
        --solo-frontend)
            BUILD_FRONTEND=true
            BUILD_BACKEND=false
            ;;
        --solo-backend)
            BUILD_FRONTEND=false
            BUILD_BACKEND=true
            ;;
        *)
            echo "❌ Error: Bandera no reconocida: $1"
            echo "Uso: $0 [--si-seeder] [--solo-frontend] [--solo-backend]"
            echo "  --si-seeder      : Ejecuta las migraciones y luego los seeders de forma forzada."
            echo "  --solo-frontend  : Omitir la construcción del Backend y las tareas de BD. Construye solo Angular."
            echo "  --solo-backend   : Omitir la construcción de Angular. Construye solo Laravel y ejecuta migraciones."
            exit 1
            ;;
    esac
    shift
done

echo "🚀 Iniciando despliegue de entorno de DESARROLLO..."

# 0. Preparar variables de entorno Frontend
echo "📄 Verificando archivo environment.ts del Frontend..."
if [ ! -f "HL7Frontend/src/environments/environment.ts" ] && [ -f "HL7Frontend/src/environments/environment.example.ts" ]; then
    echo "📄 Creando environment.ts a partir de environment.example.ts..."
    cp HL7Frontend/src/environments/environment.example.ts HL7Frontend/src/environments/environment.ts
fi

# 1. Levantar contenedores
echo "📦 Gestionando contenedores..."
if [ "$BUILD_FRONTEND" = true ] && [ "$BUILD_BACKEND" = true ]; then
    docker-compose -f docker-compose.dev.yml down
    docker-compose -f docker-compose.dev.yml up -d --build
elif [ "$BUILD_FRONTEND" = true ]; then
    docker-compose -f docker-compose.dev.yml up -d --build frontend nginx
elif [ "$BUILD_BACKEND" = true ]; then
    docker-compose -f docker-compose.dev.yml up -d --build api nginx
fi

echo "⏳ Esperando 10 segundos para inicialización de servicios..."
sleep 10

echo "⏳ Esperando inicialización de servicios base..."
sleep 5

if [ "$BUILD_BACKEND" = true ]; then
    echo "🔧 Configurando Backend (Laravel)..."
    
    echo "⏳ Esperando a que PostgreSQL esté listo (Puerto 5432)..."
    until docker exec iops_postgres_dev pg_isready -U postgres; do
      echo "   ...esperando a PostgreSQL..."
      sleep 2
    done
    echo "✅ PostgreSQL está listo y aceptando conexiones."

    # Crear estructura de directorios de caché PRIMERO
    docker exec iops_api_dev mkdir -p storage/framework/cache/data
    docker exec iops_api_dev mkdir -p storage/framework/sessions
    docker exec iops_api_dev mkdir -p storage/framework/views
    docker exec iops_api_dev mkdir -p storage/framework/testing

    # Permisos (ahora que los directorios existen)
    echo "🔒 Configurando permisos globales y en storage/cache..."
    docker exec iops_api_dev chown -R www-data:www-data /var/www/html
    docker exec iops_api_dev chmod -R 755 /var/www/html
    docker exec iops_api_dev chmod -R 775 storage bootstrap/cache
    docker exec iops_api_dev chmod -R 777 storage/logs storage/framework

    # Instalar dependencias con timeout extendido
    echo "📦 Instalando dependencias de Composer (puede tardar varios minutos)..."
    docker exec iops_api_dev bash -c "COMPOSER_PROCESS_TIMEOUT=600 composer install --no-interaction"

    # Instalar dependencias de NPM (Necesario para Browsershot / Puppeteer)
    echo "📦 Instalando dependencias de NPM en Backend..."
    docker exec iops_api_dev bash -c "npm install"

    # CRÍTICO: Re-aplicar permisos después de Composer (Composer crea archivos como root)
    echo "🔒 Re-aplicando permisos después de Composer..."
    docker exec iops_api_dev chown -R www-data:www-data storage bootstrap/cache
    docker exec iops_api_dev chmod -R 777 storage/framework

    # Limpiar cachés (Laravel maneja esto de forma segura)
    echo "🧹 Limpiando cachés..."
    docker exec -u www-data iops_api_dev php artisan config:clear || true
    docker exec -u www-data iops_api_dev php artisan cache:clear || true
    docker exec -u www-data iops_api_dev php artisan view:clear || true

    # Crear HomeController si no existe (necesario para login)
    echo "🏠 Verificando HomeController..."
    docker exec iops_api_dev bash -c "[ ! -f app/Http/Controllers/HomeController.php ] && php artisan make:controller HomeController || echo 'HomeController ya existe'"

    echo "🏠 Verificando ClientController..."
    docker exec iops_api_dev bash -c "[ ! -f app/Http/Controllers/ClientController.php ] && php artisan make:controller ClientController || echo 'ClientController ya existe'"

    echo "🔑 Verificando llave de aplicación..."
    if docker exec iops_api_dev grep -q "APP_KEY=base64:" .env; then
        echo "✅ La llave de aplicación ya existe, omitiendo generación."
    else
        echo "🔑 Generando llave de aplicación..."
        docker exec iops_api_dev php artisan key:generate --force
    fi

    # Base de datos y Migraciones
    echo "🗄️  Configurando base de datos..."
    # Crear directorios necesarios
    docker exec iops_api_dev mkdir -p Models public/upload storage/logs
    # Asegurar creación del esquema
    docker exec iops_api_dev bash -c "echo 'DB::statement(\"CREATE SCHEMA IF NOT EXISTS ihce\");' | php artisan tinker" || true
    # Correr migraciones
    docker exec iops_api_dev php artisan migrate --force

    # OAuth Passport keys (si el proyecto usa Passport) - AHORA DESPUÉS DE MIGRACIONES
    echo "🔐 Verificando configuración de Passport..."

    # Asegurar que las llaves existan físicamente en el storage
    if docker exec iops_api_dev [ ! -f storage/oauth-private.key ]; then
        echo "🔐 Generando llaves OAuth faltantes..."
        docker exec iops_api_dev php artisan passport:keys --force
        docker exec iops_api_dev chown www-data:www-data storage/oauth-private.key storage/oauth-public.key
        docker exec iops_api_dev chmod 600 storage/oauth-private.key storage/oauth-public.key
    fi

    CLIENTS=$(docker exec iops_api_dev php artisan tinker --execute="echo DB::table('oauth_clients')->count();" | tr -d '\r' | grep -oE '^[0-9]+')

    if [ "$CLIENTS" -eq 0 ]; then
        echo "🔐 Generando clientes OAuth..."
        docker exec iops_api_dev php artisan passport:install --force
    else
        echo "✅ Clientes OAuth ya existen ($CLIENTS encontrados), omitiendo generación."
    fi

    # Poblar tablas con datos iniciales
    if [ "$RUN_SEEDERS" = true ]; then
        echo "🌱 Poblando base de datos con datos iniciales..."
        docker exec iops_api_dev php artisan db:seed --force || echo "⚠️  No hay seeders configurados o ya fueron ejecutados"
    else
        echo "⏩ Omitiendo ejecución de seeders..."
    fi

    # Configurar permisos adicionales
    echo "🔐 Configurando permisos adicionales..."
    docker exec iops_api_dev chown -R www-data:www-data public/upload storage
    docker exec iops_api_dev chmod -R 775 public/upload
    docker exec iops_api_dev chmod -R 777 storage/logs storage/framework/cache storage/framework/sessions storage/framework/views
fi

if [ "$BUILD_FRONTEND" = true ]; then
    # 3. Configuración Frontend
    echo "🎨 Configurando Frontend (Angular)..."
    # Instalar dependencias si faltan (el volumen montado puede no tenerlas)
    docker exec iops_frontend_dev npm install --legacy-peer-deps
fi

echo "✅ ¡Despliegue DEV completado!"
echo "   📡 Proxy Nginx: iops_nginx_dev (puertos 4204 y 8004)"
if [ "$BUILD_BACKEND" = true ]; then
    echo "   - Backend API: http://raziel-dev-api.fciclubnoel.com:8004"
    echo "     (o localmente: http://localhost:8004)"
fi
if [ "$BUILD_FRONTEND" = true ]; then
    echo "   - Frontend App: http://raziel-dev.fciclubnoel.com:4204"
    echo "     (o localmente: http://localhost:4204)"
fi
