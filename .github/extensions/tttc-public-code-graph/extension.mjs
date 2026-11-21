import http from "node:http";
import fs from "node:fs/promises";
import path from "node:path";
import { createCanvas, CanvasError, joinSession } from "@github/copilot-sdk/extension";
import { buildGraph } from "./graph.mjs";

const DEFAULT_FILE = "includes/class-tttc-public.php";
const instances = new Map();

function validateFilePath(input) {
  const filePath = typeof input?.filePath === "string" && input.filePath.trim()
    ? input.filePath.trim()
    : DEFAULT_FILE;

  if (filePath.includes("\0")) {
    throw new CanvasError("invalid_file_path", "The file path is invalid.");
  }
  return path.isAbsolute(filePath) ? filePath : path.resolve(process.cwd(), filePath);
}

async function readGraph(filePath) {
  try {
    const source = await fs.readFile(filePath, "utf8");
    return buildGraph(source, path.relative(process.cwd(), filePath).replaceAll(path.sep, "/"));
  } catch (error) {
    throw new CanvasError(
      "graph_source_error",
      `Could not read or graph the PHP source: ${error instanceof Error ? error.message : "Unknown error"}`
    );
  }
}

function htmlResponse(res, status, body, contentType = "text/html; charset=utf-8") {
  res.writeHead(status, {
    "Content-Type": contentType,
    "Cache-Control": "no-store",
    "X-Content-Type-Options": "nosniff",
  });
  res.end(body);
}

async function createInstanceServer(instance) {
  const server = http.createServer(async (req, res) => {
    const url = new URL(req.url || "/", "http://127.0.0.1");
    if (req.method === "GET" && url.pathname === "/") {
      const html = await fs.readFile(new URL("./index.html", import.meta.url), "utf8");
      htmlResponse(res, 200, html);
      return;
    }
    if (req.method === "GET" && url.pathname === "/graph.json") {
      try {
        const graph = await readGraph(instance.filePath);
        instance.graph = graph;
        htmlResponse(res, 200, JSON.stringify(graph), "application/json; charset=utf-8");
      } catch (error) {
        htmlResponse(
          res,
          500,
          JSON.stringify({ error: error instanceof Error ? error.message : "Unknown error" }),
          "application/json; charset=utf-8"
        );
      }
      return;
    }
    htmlResponse(res, 404, "Not found", "text/plain; charset=utf-8");
  });

  await new Promise((resolve, reject) => {
    server.once("error", reject);
    server.listen(0, "127.0.0.1", resolve);
  });

  const address = server.address();
  if (!address || typeof address === "string") {
    throw new CanvasError("server_error", "Could not start the graph canvas server.");
  }
  instance.server = server;
  instance.url = `http://127.0.0.1:${address.port}`;
  return instance.url;
}

const canvas = createCanvas({
  id: "tttc-public-code-graph",
  displayName: "TTTC Public Code Graph",
  description: "Displays an interactive method graph for the public WordPress PHP class.",
  inputSchema: {
    type: "object",
    properties: {
      filePath: {
        type: "string",
        description: "Workspace-relative or absolute PHP file path.",
      },
    },
    additionalProperties: false,
  },
  actions: [
    {
      name: "refreshGraph",
      description: "Reload the PHP source and rebuild the method graph.",
      inputSchema: { type: "object", properties: {}, additionalProperties: false },
      handler: async (ctx) => {
        const instance = instances.get(ctx.instanceId);
        if (!instance) {
          throw new CanvasError("instance_not_found", "The canvas instance is not active.");
        }
        instance.graph = await readGraph(instance.filePath);
        return {
          filePath: instance.filePath,
          methodCount: instance.graph.nodes.filter((node) => node.type === "method").length,
          edgeCount: instance.graph.edges.length,
        };
      },
    },
  ],
  open: async (ctx) => {
    const filePath = validateFilePath(ctx.input);
    const existing = instances.get(ctx.instanceId);
    if (existing) {
      existing.filePath = filePath;
      existing.graph = await readGraph(filePath);
      return {
        url: existing.url,
        title: "TTTC Public Code Graph",
        status: path.relative(process.cwd(), filePath),
      };
    }

    const graph = await readGraph(filePath);
    const instance = { filePath, graph, server: null, url: null };
    instances.set(ctx.instanceId, instance);
    try {
      await createInstanceServer(instance);
      return {
        url: instance.url,
        title: "TTTC Public Code Graph",
        status: path.relative(process.cwd(), filePath),
      };
    } catch (error) {
      instances.delete(ctx.instanceId);
      throw error;
    }
  },
  onClose: (ctx) => {
    const instance = instances.get(ctx.instanceId);
    if (instance?.server) {
      instance.server.close();
    }
    instances.delete(ctx.instanceId);
  },
});

await joinSession({ canvases: [canvas] });
