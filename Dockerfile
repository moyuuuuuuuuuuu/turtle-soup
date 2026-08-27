FROM node:22.22.0-alpine AS build

WORKDIR /app
RUN corepack enable && corepack prepare pnpm@9.9.0 --activate

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
COPY docs/package.json ./docs/
RUN pnpm install --frozen-lockfile

COPY . .
RUN pnpm build:h5:production

FROM nginx:1.27-alpine

COPY deploy/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist/build/h5 /usr/share/nginx/html

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=3s --retries=3 CMD wget -q -O /dev/null http://127.0.0.1/ || exit 1
