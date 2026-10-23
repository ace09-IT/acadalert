'use strict';

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const VIEWS = path.join(__dirname, '..', '..', 'resources', 'views');

function walk(dir) {
    return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const full = path.join(dir, entry.name);

        if (entry.isDirectory()) return walk(full);
        return entry.name.endsWith('.blade.php') ? [full] : [];
    });
}

let checked = 0;
let failed = 0;

for (const file of walk(VIEWS)) {
    const raw = fs
        .readFileSync(file, 'utf8')
        // Strip HTML comments first: they may contain the literal text "<script>",
        // which would otherwise start a phantom block.
        .replace(/<!--[\s\S]*?-->/g, '');

    const blocks = raw.match(/<script(?![^>]*\bsrc=)[^>]*>([\s\S]*?)<\/script>/gi) || [];

    blocks.forEach((block, index) => {
        const body = block
            .replace(/^<script[^>]*>/i, '')
            .replace(/<\/script>$/i, '')
            .replace(/@json\([^)]*\)/g, '0')
            .replace(/\{!![\s\S]*?!!\}/g, '0')
            .replace(/\{\{[^}]*\}\}/g, '0')
            // Only whole-line directives are dropped (never part of a statement).
            .replace(/^[ \t]*@(if|elseif|else|endif|foreach|endforeach|forelse|empty|endforelse|php|endphp|section|endsection|push|endpush|auth|endauth|guest|endguest|can|endcan|isset|endisset|unless|endunless|switch|break|default|endswitch)\b[^\n]*$/gm, '');

        if (body.trim() === '') return;

        const tmp = path.join(__dirname, '.sweep.tmp.js');
        fs.writeFileSync(tmp, body, 'utf8');

        try {
            execFileSync(process.execPath, ['--check', tmp], { stdio: 'pipe' });
            checked++;
        } catch (error) {
            failed++;
            const message = String(error.stderr || error.message).split('\n').slice(0, 4).join(' ');
            console.log('FAIL  ' + path.relative(VIEWS, file) + ' block #' + (index + 1) + ': ' + message);
        } finally {
            fs.unlinkSync(tmp);
        }
    });
}

console.log(checked + ' inline script block(s) syntax-checked, ' + failed + ' failing.');
process.exit(failed === 0 ? 0 : 1);
