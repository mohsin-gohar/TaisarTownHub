# G-15 — frontend image: Vite build → nginx static serve (with SPA fallback).
#
# The API origin is injected at BUILD time through VITE_API_URL (Vite bakes
# env-prefixed vars into the bundle). Same-origin `/api` deployments should
# leave VITE_API_URL=/api and reverse-proxy /api to the API service.

# ---------- build ----------
FROM node:20-alpine AS build
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

# Vite reads VITE_* from the environment at build time.
ARG VITE_API_URL=/api
ARG VITE_APP_MODE=production
ENV VITE_API_URL=$VITE_API_URL
ENV VITE_APP_MODE=$VITE_APP_MODE
RUN npm run build

# ---------- serve ----------
FROM nginx:1.27-alpine

# Per-route prerendered meta HTML + sitemap live in dist/.
COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
