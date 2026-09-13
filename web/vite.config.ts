import { defineConfig, type Plugin } from "vite";
import react from "@vitejs/plugin-react";
import { unlink } from "node:fs/promises";
import type { IncomingMessage, ServerResponse } from "node:http";
import { resolve } from "node:path";
import { handleChatRequest, type ChatPayload } from "./api/forward";

function readJsonBody(req: IncomingMessage): Promise<ChatPayload> {
  return new Promise((resolve, reject) => {
    const chunks: Buffer[] = [];
    req.on("data", (chunk) => {
      chunks.push(Buffer.isBuffer(chunk) ? chunk : Buffer.from(chunk));
    });
    req.on("end", () => {
      const raw = Buffer.concat(chunks).toString("utf8");
      if (!raw.trim()) {
        resolve({});
        return;
      }
      try {
        resolve(JSON.parse(raw) as ChatPayload);
      } catch (error) {
        reject(error);
      }
    });
    req.on("error", reject);
  });
}

function chatProxy(): Plugin {
  const handle = async (req: IncomingMessage, res: ServerResponse) => {
    try {
      const payload = req.method === "POST" ? await readJsonBody(req) : {};
      const result = await handleChatRequest({
        method: req.method,
        authorization: req.headers.authorization,
        payload,
      });
      res.statusCode = result.status;
      res.setHeader("Content-Type", "application/json");
      res.setHeader("Cache-Control", "no-store");
      if (result.status === 204) {
        res.end();
        return;
      }
      res.end(JSON.stringify(result.body));
    } catch (error) {
      res.statusCode = 400;
      res.setHeader("Content-Type", "application/json");
      res.end(
        JSON.stringify({
          error: {
            message: error instanceof Error ? error.message : "Bad request",
          },
        }),
      );
    }
  };

  return {
    name: "chat-proxy",
    configureServer(server) {
      server.middlewares.use("/api/chat", (req, res) => {
        void handle(req, res);
      });
    },
    configurePreviewServer(server) {
      server.middlewares.use("/api/chat", (req, res) => {
        void handle(req, res);
      });
    },
  };
}

function stripLocalSecrets(): Plugin {
  return {
    name: "strip-local-secrets",
    async closeBundle() {
      await unlink(resolve("dist/api/config.local.php")).catch(() => undefined);
    },
  };
}

export default defineConfig({
  plugins: [react(), chatProxy(), stripLocalSecrets()],
  server: {
    host: true,
    port: 5173,
  },
  preview: {
    host: true,
    port: 4173,
  },
  test: {
    environment: "node",
  },
});
