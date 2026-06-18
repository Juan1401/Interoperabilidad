#!/bin/bash
# deploy-prod.sh
# Automatización del despliegue en entorno de PRODUCCIÓN

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

echo "🚀 Iniciando despliegue de entorno de PRODUCCIÓN..."

# 0. Preparar variables de entorno Frontend
echo "📄 Verificando archivo environment.ts del Frontend..."
if [ ! -f "HL7Frontend/src/environments/environment.ts" ] && [ -f "HL7Frontend/src/environments/environment.example.ts" ]; then
    echo "📄 Creando environment.ts a partir de environment.example.ts..."
    cp HL7Frontend/src/environments/environment.example.ts HL7Frontend/src/environments/environment.ts
fi

# 1. Levantar contenedores
# El Dockerfile de producción ya incluye 'composer install --no-dev' y el build de Angular.
echo "📦 Construyendo y levantando contenedores..."
if [ "$BUILD_FRONTEND" = true ] && [ "$BUILD_BACKEND" = true ]; then
    docker-compose -f docker-compose.yml down
    docker-compose -f docker-compose.yml up -d --build
elif [ "$BUILD_FRONTEND" = true ]; then
    docker-compose -f docker-compose.yml up -d --build frontend nginx
elif [ "$BUILD_BACKEND" = true ]; then
    docker-compose -f docker-compose.yml up -d --build api nginx
fi

echo "⏳ Esperando 10 segundos para inicialización..."
sleep 10

if [ "$BUILD_BACKEND" = true ]; then
    # 2. Comandos Post-Build Backend
    # Aunque el Dockerfile ya hace muchas cosas, corremos comandos finales en el contenedor vivo
    echo "🔧 Finalizando configuración Backend..."

    # Crear estructura de directorios de caché PRIMERO
    echo "📁 Creando directorios de framework Laravel..."
    docker exec hl7restapi mkdir -p storage/framework/cache/data
    docker exec hl7restapi mkdir -p storage/framework/sessions
    docker exec hl7restapi mkdir -p storage/framework/views
    docker exec hl7restapi mkdir -p storage/framework/testing

    # Aseguramos permisos (ahora que los directorios existen)
    echo "🔒 Configurando permisos globales y en storage/cache..."
    docker exec hl7restapi chown -R www-data:www-data /var/www/html
    docker exec hl7restapi chmod -R 755 /var/www/html
    docker exec hl7restapi chmod -R 775 storage bootstrap/cache
    docker exec hl7restapi chmod -R 777 storage/logs storage/framework

    # CRÍTICO: Re-aplicar permisos antes de cache (el build puede crear archivos como root)
    echo "🔒 Re-aplicando permisos antes de comandos de caché..."
    docker exec hl7restapi chown -R www-data:www-data storage bootstrap/cache
    docker exec hl7restapi chmod -R 777 storage/framework

    # Caché de configuración para rendimiento (solo en prod - Laravel maneja permisos)
    docker exec -u www-data hl7restapi php artisan config:cache || true
    docker exec -u www-data hl7restapi php artisan route:cache || true
    docker exec -u www-data hl7restapi php artisan view:cache || true

    # Crear HomeController si no existe (necesario para login)
    echo "🏠 Verificando HomeController..."
    docker exec hl7restapi bash -c "[ ! -f app/Http/Controllers/HomeController.php ] && php artisan make:controller HomeController || echo 'HomeController ya existe'"

    echo "🏠 Verificando ClientController..."
    docker exec hl7restapi bash -c "[ ! -f app/Http/Controllers/ClientController.php ] && php artisan make:controller ClientController || echo 'ClientController ya existe'"

    echo "🔑 Verificando llave de aplicación..."
    if docker exec hl7restapi grep -q "APP_KEY=base64:" .env; then
        echo "✅ La llave de aplicación ya existe, omitiendo generación."
    else
        echo "🔑 Generando llave de aplicación..."
        docker exec hl7restapi php artisan key:generate --force
    fi



    # Base de datos
    echo "🗄️  Configurando base de datos..."
    # Crear directorios necesarios
    docker exec hl7restapi mkdir -p Models public/upload storage/logs
    docker exec hl7restapi bash -c "echo 'DB::statement(\"CREATE SCHEMA IF NOT EXISTS ihce\");' | php artisan tinker" || true
    docker exec hl7restapi php artisan migrate --force

    # OAuth Passport keys (Necesario para generación de tokens)
    echo "🔐 Verificando configuración de Passport..."

    # Asegurar que las llaves existan físicamente en el storage
    if docker exec hl7restapi [ ! -f storage/oauth-private.key ]; then
        echo "🔐 Generando llaves OAuth faltantes..."
        docker exec hl7restapi php artisan passport:keys --force
        docker exec hl7restapi chown www-data:www-data storage/oauth-private.key storage/oauth-public.key
        docker exec hl7restapi chmod 600 storage/oauth-private.key storage/oauth-public.key
    fi

    CLIENTS=$(docker exec hl7restapi php artisan tinker --execute="echo DB::table('oauth_clients')->count();" | tr -d '\r' | grep -oE '^[0-9]+' || echo "0")

    if [ -z "$CLIENTS" ]; then
        CLIENTS=0
    fi

    if [ "$CLIENTS" -eq 0 ]; then
        echo "🔐 Generando clientes OAuth..."
        docker exec hl7restapi php artisan passport:install --force
    else
        echo "✅ Clientes OAuth ya existen ($CLIENTS encontrados), omitiendo generación."
    fi

    # Poblar base de datos
    if [ "$RUN_SEEDERS" = true ]; then
        echo "🌱 Poblando base de datos con datos iniciales..."
        docker exec hl7restapi php artisan db:seed --force || echo "⚠️  No hay seeders configurados"
    else
        echo "⏩ Omitiendo ejecución de seeders..."
    fi

    # Configurar permisos adicionales
    echo "🔐 Configurando permisos adicionales..."
    docker exec hl7restapi chown -R www-data:www-data public/upload storage
    docker exec hl7restapi chmod -R 775 public/upload
    docker exec hl7restapi chmod -R 777 storage/logs storage/framework/cache storage/framework/sessions storage/framework/views
fi

echo "✅ ¡Despliegue PROD completado!"
echo "   📡 Proxy Nginx: hl7nginx (puertos 4203 y 8003)"
if [ "$BUILD_FRONTEND" = true ]; then
    echo "   - Frontend App: http://raziel.fciclubnoel.com:4203"
    echo "     (o localmente: http://localhost:4203)"
fi
if [ "$BUILD_BACKEND" = true ]; then
    echo "   - Backend API:  http://raziel-api.fciclubnoel.com:8003"
    echo "     (o localmente: http://localhost:8003)"
fi
