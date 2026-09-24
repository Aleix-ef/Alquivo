FROM node:22-alpine AS build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
ARG VITE_API_URL=/api/v1
ENV VITE_API_URL=$VITE_API_URL
ARG VITE_PUBLIC_SITE_URL=
ARG VITE_SEO_INDEXABLE=false
ARG VITE_BETA_PROGRAM_ENABLED=true
ENV VITE_PUBLIC_SITE_URL=$VITE_PUBLIC_SITE_URL
ENV VITE_SEO_INDEXABLE=$VITE_SEO_INDEXABLE
ENV VITE_BETA_PROGRAM_ENABLED=$VITE_BETA_PROGRAM_ENABLED
RUN npm run build

FROM nginx:1.30.4-alpine
COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist-ssr/seo-map.conf /etc/nginx/seo-map.conf
COPY --from=build /app/dist-ssr/seo-locations.conf /etc/nginx/seo-locations.conf
EXPOSE 80
