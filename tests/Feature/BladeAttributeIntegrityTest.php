<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * An attribute spliced INSIDE another attribute's quoted value.
 *
 * `x-data="autofillBuilder data-tour="client-autofill"(@js($data))"` shipped and broke
 * the client autofill builder completely: Alpine evaluated `autofillBuilder data-tour=`
 * as its expression, threw, and never initialised the component — so the department
 * dropdown rendered empty and the QR code never appeared. The page returned 200
 * throughout, every PHPUnit test stayed green, and the Blade compiled without
 * complaint, because nothing in the stack parses HTML attributes.
 *
 * It was introduced by adding a `data-tour` anchor to a tag that already carried an
 * `x-data`, which is a mechanical mistake that will recur — the tour work touched 31
 * templates. This scans for the shape rather than the one instance.
 */
class BladeAttributeIntegrityTest extends TestCase
{
    /**
     * Attributes whose values are JavaScript and therefore cannot contain `foo="bar"`.
     *
     * @var list<string>
     */
    private const JS_ATTRIBUTES = ['x-data', 'x-init', 'x-show', 'x-model', 'x-on:click', '@click', 'x-bind'];

    public function test_no_attribute_is_nested_inside_another_attributes_value(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            foreach (explode("\n", (string) file_get_contents($file->getPathname())) as $i => $line) {
                foreach (self::JS_ATTRIBUTES as $attr) {
                    // The value runs to the next double quote. A second `name="` before
                    // that quote closes means another attribute was spliced into it.
                    if (preg_match('/'.preg_quote($attr, '/').'="([^"]*?)\s[a-zA-Z@:_.-]+="/', $line)) {
                        $offenders[] = sprintf(
                            '%s:%d  (%s)',
                            str_replace(base_path().'/', '', $file->getPathname()),
                            $i + 1,
                            $attr
                        );
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "An HTML attribute is nested inside a JavaScript attribute's value. The JS will "
            .'throw at run time and the component will never initialise, while the page still '
            ."returns 200:\n  ".implode("\n  ", $offenders)
        );
    }

    public function test_the_client_autofill_builder_has_an_intact_alpine_expression(): void
    {
        // The specific regression. Pinned separately because this component is the one
        // that shipped broken, and its x-data takes a server-rendered @js payload —
        // the shape most likely to be mangled again.
        $view = (string) file_get_contents(resource_path('views/clients/show.blade.php'));

        $this->assertStringContainsString('x-data="autofillBuilder(', $view);
        $this->assertStringContainsString('data-tour="client-autofill"', $view);
        $this->assertStringNotContainsString('autofillBuilder data-tour', $view);
    }
}
