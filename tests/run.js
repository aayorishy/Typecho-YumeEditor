/**
 * YumeEditor · test runner
 *
 * Runs every PHP test with the PHP CLI and every JavaScript test with the
 * current Node binary, then prints a short summary.
 *
 *   pnpm test
 *   PHP_BINARY=/path/to/php node tests/run.js
 */
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const TESTS = __dirname;
const PHP = process.env.PHP_BINARY || 'php';

const SKIP = new Set(['support.php', 'fixture.php', 'run.js']);

function listTests(extension) {
    return fs
        .readdirSync(TESTS)
        .filter((file) => file.endsWith(extension) && !SKIP.has(file))
        .sort();
}

const suites = [
    ...listTests('.php').map((file) => ({ file, command: PHP, args: [path.join(TESTS, file)] })),
    ...listTests('.js').map((file) => ({
        file,
        command: process.execPath,
        args: [path.join(TESTS, file)],
    })),
];

let failed = 0;

for (const suite of suites) {
    const label = `tests/${suite.file}`;
    process.stdout.write(`\n▶ ${label}\n`);

    const result = spawnSync(suite.command, suite.args, { stdio: 'inherit', env: process.env });

    if (result.error) {
        failed += 1;
        console.error(`✗ ${label}: ${result.error.message}`);
        continue;
    }

    if (result.status !== 0) {
        failed += 1;
        console.error(`✗ ${label}: exit code ${result.status}`);
    }
}

process.stdout.write('\n');
if (failed > 0) {
    console.error(`${failed} of ${suites.length} test files failed.`);
    process.exit(1);
}

console.log(`All ${suites.length} test files passed.`);
