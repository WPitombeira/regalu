import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

// Vite's native Rolldown binary can SIGILL on older virtual CPUs.
// Load only Rolldown through its official WASI binding, not Tailwind's bindings.
const require = createRequire(import.meta.resolve('vite/package.json'));
const rolldown = JSON.parse(readFileSync(require.resolve('rolldown/package.json'), 'utf8'));
const binding = JSON.parse(readFileSync(require.resolve('@rolldown/binding-wasm32-wasi/package.json'), 'utf8'));
if (rolldown.version !== binding.version) {
    throw new Error(`Portable binding ${binding.version} must match Vite's Rolldown ${rolldown.version}. Update the binding and lockfile together.`);
}

const previous = process.env.NAPI_RS_FORCE_WASI;
try {
    process.env.NAPI_RS_FORCE_WASI = 'error';
    await import(require.resolve('rolldown'));
} finally {
    if (previous === undefined) delete process.env.NAPI_RS_FORCE_WASI;
    else process.env.NAPI_RS_FORCE_WASI = previous;
}

const cli = new URL('./bin/vite.js', import.meta.resolve('vite/package.json'));
process.argv[1] = fileURLToPath(cli);
await import(cli.href);
