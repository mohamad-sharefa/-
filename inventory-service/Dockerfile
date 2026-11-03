# Built from the repository root so the shared proto contract can be copied in:
#   docker build -f inventory-service/Dockerfile .
FROM node:22-alpine

WORKDIR /app

COPY inventory-service/package*.json ./
RUN npm install --omit=dev --no-audit --no-fund

COPY inventory-service/src ./src
COPY inventory-service/test ./test
COPY proto ./proto

ENV NODE_ENV=production
EXPOSE 50051
USER node
CMD ["node", "src/server.js"]
