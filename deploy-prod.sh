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
if [ ! -f "iops_frontend/src/environments/environment.ts" ] && [ -f "iops_frontend/src/environments/environment.example.ts" ]; then
    echo "📄 Creando environment.ts a partir de environment.example.ts..."
    cp iops_frontend/src/environments/environment.example.ts iops_frontend/src/environments/environment.ts
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
    docker exec iops_api mkdir -p storage/framework/cache/data
    docker exec iops_api mkdir -p storage/framework/sessions
    docker exec iops_api mkdir -p storage/framework/views
    docker exec iops_api mkdir -p storage/framework/testing

    # Aseguramos permisos (ahora que los directorios existen)
    echo "🔒 Configurando permisos globales y en storage/cache..."
    docker exec iops_api chown -R www-data:www-data /var/www/html
    docker exec iops_api chmod -R 755 /var/www/html
    docker exec iops_api chmod -R 775 storage bootstrap/cache
    docker exec iops_api chmod -R 777 storage/logs storage/framework

    # CRÍTICO: Re-aplicar permisos antes de cache (el build puede crear archivos como root)
    echo "🔒 Re-aplicando permisos antes de comandos de caché..."
    docker exec iops_api chown -R www-data:www-data storage bootstrap/cache
    docker exec iops_api chmod -R 777 storage/framework

    # Caché de configuración para rendimiento (solo en prod - Laravel maneja permisos)
    docker exec -u www-data iops_api php artisan config:cache || true
    docker exec -u www-data iops_api php artisan route:cache || true
    docker exec -u www-data iops_api php artisan view:cache || true

    # Crear HomeController si no existe (necesario para login)
    echo "🏠 Verificando HomeController..."
    docker exec iops_api bash -c "[ ! -f app/Http/Controllers/HomeController.php ] && php artisan make:controller HomeController || echo 'HomeController ya existe'"

    echo "🏠 Verificando ClientController..."
    docker exec iops_api bash -c "[ ! -f app/Http/Controllers/ClientController.php ] && php artisan make:controller ClientController || echo 'ClientController ya existe'"

    echo "🔑 Verificando llave de aplicación..."
    if docker exec iops_api grep -q "APP_KEY=base64:" .env; then
        echo "✅ La llave de aplicación ya existe, omitiendo generación."
    else
        echo "🔑 Generando llave de aplicación..."
        docker exec iops_api php artisan key:generate --force
    fi



    # Base de datos
    echo "🗄️  Configurando base de datos..."
    # Crear directorios necesarios
    docker exec iops_api mkdir -p Models public/upload storage/logs
    docker exec iops_api bash -c "echo 'DB::statement(\"CREATE SCHEMA IF NOT EXISTS ihce\");' | php artisan tinker" || true
    docker exec iops_api php artisan migrate --force

    # OAuth Passport keys (Necesario para generación de tokens)
    echo "🔐 Verificando configuración de Passport..."

    # Asegurar que las llaves existan físicamente en el storage
    if docker exec iops_api [ ! -f storage/oauth-private.key ]; then
        echo "🔐 Generando llaves OAuth faltantes..."
        docker exec iops_api php artisan passport:keys --force
        docker exec iops_api chown www-data:www-data storage/oauth-private.key storage/oauth-public.key
        docker exec iops_api chmod 600 storage/oauth-private.key storage/oauth-public.key
    fi

    CLIENTS=$(docker exec iops_api php artisan tinker --execute="echo DB::table('oauth_clients')->count();" | tr -d '\r' | grep -oE '^[0-9]+' || echo "0")

    if [ -z "$CLIENTS" ]; then
        CLIENTS=0
    fi

    if [ "$CLIENTS" -eq 0 ]; then
        echo "🔐 Generando clientes OAuth..."
        docker exec iops_api php artisan passport:install --force
    else
        echo "✅ Clientes OAuth ya existen ($CLIENTS encontrados), omitiendo generación."
    fi

    # Poblar base de datos
    if [ "$RUN_SEEDERS" = true ]; then
        echo "🌱 Poblando base de datos con datos iniciales..."
        docker exec iops_api php artisan db:seed --force || echo "⚠️  No hay seeders configurados"
    else
        echo "⏩ Omitiendo ejecución de seeders..."
    fi

    # Configurar permisos adicionales
    echo "🔐 Configurando permisos adicionales..."
    docker exec iops_api chown -R www-data:www-data public/upload storage
    docker exec iops_api chmod -R 775 public/upload
    docker exec iops_api chmod -R 777 storage/logs storage/framework/cache storage/framework/sessions storage/framework/views
fi

echo "✅ ¡Despliegue PROD completado!"
echo "   📡 Proxy Nginx: iops_nginx (puertos 4205 y 8005)"
if [ "$BUILD_FRONTEND" = true ]; then
    echo "   - Frontend App: http://raziel.fciclubnoel.com:4205"
    echo "     (o localmente: http://localhost:4205)"
fi
if [ "$BUILD_BACKEND" = true ]; then
    echo "   - Backend API:  http://raziel-api.fciclubnoel.com:8005"
    echo "     (o localmente: http://localhost:8005)"
fi
