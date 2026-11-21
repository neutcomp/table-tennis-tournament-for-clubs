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
