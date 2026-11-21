export function buildGraph(source, displayPath) {
  const methodPattern =
    /^\s*(public|private|protected)\s+(?:static\s+)?(?:function\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*\(/gm;
  const methods = [];
  let match;
  while ((match = methodPattern.exec(source))) {
    const visibility = match[1];
    const name = match[2];
    const line = source.slice(0, match.index).split("\n").length;
    methods.push({ name, visibility, line, index: match.index });
  }

  const nodes = [{
    id: "class",
    label: "TTTC_Public",
    type: "class",
  }, ...methods.map((method) => ({
    id: `method:${method.name}`,
    label: method.name,
    type: "method",
    visibility: method.visibility,
    line: method.line,
  }))];

  const edges = [];
  for (let index = 0; index < methods.length; index++) {
    const method = methods[index];
    const nextMethod = methods[index + 1];
    const body = source.slice(method.index, nextMethod ? nextMethod.index : source.length);

    edges.push({
      from: "class",
      to: `method:${method.name}`,
      label: "contains",
    });

    for (const called of methods) {
      if (called.name !== method.name && new RegExp(`\\$this->${called.name}\\s*\\(`).test(body)) {
        edges.push({
          from: `method:${method.name}`,
          to: `method:${called.name}`,
          label: "calls",
        });
      }
    }

    const hookCalls = [...body.matchAll(/\b(add_(?:action|filter|shortcode)|register_rest_route)\s*\(/g)]
      .map((hook) => hook[1])
      .filter((value, index, values) => values.indexOf(value) === index);
    for (const hook of hookCalls) {
      const hookId = `hook:${hook}`;
      if (!nodes.some((node) => node.id === hookId)) {
        nodes.push({ id: hookId, label: hook, type: "hook" });
      }
      edges.push({
        from: `method:${method.name}`,
        to: hookId,
        label: "registers",
      });
    }
  }

  return { displayPath, nodes, edges };
}

export async function buildDirectoryGraph(directory, workspaceRoot) {
  const fs = await import("node:fs/promises");
  const path = await import("node:path");
  const excluded = new Set([".git", ".github", "node_modules", "vendor"]);
  const files = [];

  async function visit(current) {
    const entries = await fs.readdir(current, { withFileTypes: true });
    for (const entry of entries) {
      if (entry.isDirectory()) {
        if (!excluded.has(entry.name)) {
          await visit(path.join(current, entry.name));
        }
      } else if (entry.isFile() && entry.name.endsWith(".php")) {
        files.push(path.join(current, entry.name));
      }
    }
  }

  await visit(directory);
  const nodes = [];
  const edges = [];
  for (const file of files.sort()) {
    const source = await fs.readFile(file, "utf8");
    const displayPath = path.relative(workspaceRoot, file).replaceAll(path.sep, "/");
    const graph = buildGraph(source, displayPath);
    const fileKey = displayPath.replace(/[^a-zA-Z0-9]+/g, "-");
    for (const node of graph.nodes) {
      nodes.push({
        ...node,
        id: `${fileKey}:${node.id}`,
        file: displayPath,
      });
    }
    for (const edge of graph.edges) {
      edges.push({
        ...edge,
        from: `${fileKey}:${edge.from}`,
        to: `${fileKey}:${edge.to}`,
      });
    }
  }

  return {
    displayPath: path.relative(workspaceRoot, directory).replaceAll(path.sep, "/") || ".",
    nodes,
    edges,
  };
}
