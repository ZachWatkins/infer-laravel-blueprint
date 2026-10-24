const path = require('node:path');

const workspaceRoot = path.resolve(__dirname, '..', '..');
const protectedDirectory = path.join(workspaceRoot, 'blueprint');
const pathArgumentNames = new Set([
    'path',
    'file',
    'filename',
    'filepath',
    'file_path',
    'uri',
    'destination',
    'target',
]);

function isWithinProtectedDirectory(candidate, cwd) {
    const normalized = candidate.replace(/^file:\/\//i, '').replaceAll('/', path.sep);
    const resolved = path.resolve(cwd, normalized);
    const relative = path.relative(protectedDirectory, resolved);

    return relative === '' || (!relative.startsWith(`..${path.sep}`) && relative !== '..' && !path.isAbsolute(relative));
}

function referencesProtectedDirectoryInCommand(command, cwd) {
    const absolutePattern = new RegExp(
        path.resolve(protectedDirectory).replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replaceAll(path.sep, '[\\\\/]'),
        'i',
    );
    const relativePattern = /(?:^|[\s"'`=])(?:\.{1,2}[\\/])*blueprint(?:[\\/]|$)/i;

    return absolutePattern.test(command)
        || (relativePattern.test(command) && isWithinProtectedDirectory('blueprint', cwd));
}

function patchTargetsProtectedDirectory(patch) {
    const pathHeaders = patch.matchAll(/^\*\*\* (?:Add|Update|Delete) File: (.+)$|^\*\*\* Move to: (.+)$/gm);

    for (const match of pathHeaders) {
        const candidate = match[1] ?? match[2];
        if (isWithinProtectedDirectory(candidate, workspaceRoot)) {
            return true;
        }
    }

    return false;
}

function argsTargetProtectedDirectory(args, cwd, toolName) {
    if (typeof args === 'string') {
        return toolName.toLowerCase().includes('patch') && patchTargetsProtectedDirectory(args);
    }
    if (Array.isArray(args)) {
        return args.some((value) => argsTargetProtectedDirectory(value, cwd, toolName));
    }
    if (args === null || typeof args !== 'object') {
        return false;
    }

    return Object.entries(args).some(([key, value]) => {
        if (typeof value === 'string') {
            const normalizedKey = key.toLowerCase();

            if (normalizedKey === 'command' || normalizedKey === 'cmd' || normalizedKey === 'shell') {
                return referencesProtectedDirectoryInCommand(value, cwd);
            }
            if (pathArgumentNames.has(normalizedKey)) {
                return isWithinProtectedDirectory(value, cwd);
            }
            if (normalizedKey === 'patch' || normalizedKey === 'input') {
                return patchTargetsProtectedDirectory(value);
            }
        }

        return argsTargetProtectedDirectory(value, cwd, toolName);
    });
}

function deny(reason) {
    process.stdout.write(`${JSON.stringify({
        hookSpecificOutput: {
            hookEventName: 'PreToolUse',
            permissionDecision: 'deny',
            permissionDecisionReason: reason,
        },
    })}\n`);
}

let input;
try {
    input = JSON.parse(require('node:fs').readFileSync(0, 'utf8'));
} catch {
    deny('Unable to verify the target path; access to blueprint/ is blocked by workspace policy.');
    process.exit(0);
}

const toolName = typeof input.toolName === 'string' ? input.toolName : '';
const readOnlyTool = /(?:^|[._-])(?:read|view|search|list|glob|grep|rg|status|diff|show|inspect)(?:$|[._-])/i.test(toolName);
const cwd = typeof input.cwd === 'string' ? input.cwd : workspaceRoot;

if (!readOnlyTool && argsTargetProtectedDirectory(input.toolArgs, cwd, toolName)) {
    deny('blueprint/ is a protected submodule; write operations targeting it are blocked.');
}
