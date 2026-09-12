import { handleChatRequest, type ChatPayload } from "./forward";

function readBody(req: { body?: unknown }): ChatPayload {
  const raw = req.body;
  if (raw && typeof raw === "object") return raw as ChatPayload;
  if (typeof raw === "string" && raw.trim()) {
    return JSON.parse(raw) as ChatPayload;
  }
  return {};
}

export default async function handler(
  req: {
    method?: string;
    headers: Record<string, string | string[] | undefined>;
    body?: unknown;
  },
  res: {
    status: (code: number) => { json: (body: unknown) => void; end: () => void };
    setHeader: (name: string, value: string) => void;
  },
) {
  const result = await handleChatRequest({
    method: req.method,
    authorization: req.headers.authorization ?? req.headers.Authorization,
    payload: readBody(req),
  });
  res.setHeader("Cache-Control", "no-store");
  if (result.status === 204) {
    res.status(204).end();
    return;
  }
  res.status(result.status).json(result.body);
}
