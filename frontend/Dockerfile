FROM node:22-alpine AS development

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .

EXPOSE 5173
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--strictPort"]

FROM development AS build
ARG VITE_DEMO_MAILBOX_URL=http://localhost:8026
ENV VITE_DEMO_MAILBOX_URL=$VITE_DEMO_MAILBOX_URL
RUN npm run build

FROM nginx:stable-alpine AS low-memory
RUN sed -i 's/^worker_processes.*/worker_processes 1;/' /etc/nginx/nginx.conf
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 5173

# Preserve the development server for the regular Compose configuration.
FROM development AS default
