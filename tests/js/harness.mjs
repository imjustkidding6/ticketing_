/** A test harness small enough not to need a dependency. */

const tests = [];

export function test(name, fn) {
    tests.push({ name, fn });
}

export function run() {
    let failed = 0;

    for (const { name, fn } of tests) {
        try {
            fn();
            console.log(`  ✓ ${name}`);
        } catch (error) {
            failed++;
            console.log(`  ✗ ${name}`);
            console.log(`      ${error.message.split('\n').join('\n      ')}`);
        }
    }

    const passed = tests.length - failed;
    console.log(`\n  ${passed} passed${failed ? `, ${failed} failed` : ''} (${tests.length} tests)\n`);
    process.exit(failed ? 1 : 0);
}
