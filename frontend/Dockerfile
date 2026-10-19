# Stage 1: Build de l'application Angular
FROM node:24-alpine AS builder

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# Stage 2: Serveur Nginx pour les fichiers statiques
FROM nginx:stable-alpine

# Le builder Angular place la compilation dans dist/gtech-frontend/browser :
# on copie ce contenu à la racine servie par nginx (sinon la page d'accueil
# par défaut de nginx est affichée à la place de l'application).
COPY --from=builder /app/dist/gtech-frontend/browser /usr/share/nginx/html

# Configuration Nginx pour le reverse proxy vers l'API Laravel
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
