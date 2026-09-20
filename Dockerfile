FROM node:22.22.0-alpine AS build

WORKDIR /app
RUN corepack enable && corepack prepare pnpm@9.9.0 --activate

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
COPY docs/package.json ./docs/
RUN pnpm install --frozen-lockfile

COPY . .
# H5 uses the Compose gateway; mini-program builds retain their own public URLs.
ARG VITE_API_BASE_URL=/api/v1
ARG VITE_WS_BASE_URL=
RUN VITE_API_BASE_URL="$VITE_API_BASE_URL" VITE_WS_BASE_URL="$VITE_WS_BASE_URL" pnpm build:h5:production

FROM nginx:1.27-alpine

COPY deploy/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist/build/h5 /usr/share/nginx/html
# 确保爬虫入口文件落在站点根目录（即使构建产物未包含 public 文件）
COPY public/robots.txt /usr/share/nginx/html/robots.txt

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=3s --retries=3 CMD wget -q -O /dev/null http://127.0.0.1/ || exit 1
