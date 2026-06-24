# --- ETAPA 1: Construcción (Build) ---
FROM node:20-bookworm AS build
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
# El 'iops_frontend' debe coincidir con el nombre en angular.json
RUN npm run build -- --configuration production

# --- ETAPA 2: Servidor (Serve) ---
FROM nginx:stable-alpine
# Copia los archivos compilados desde la etapa de 'build'
COPY --from=build /app/dist/sakai-ng/browser/ /usr/share/nginx/html
# Copia la configuración de Nginx
COPY nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
