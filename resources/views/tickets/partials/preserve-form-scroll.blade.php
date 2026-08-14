<input type="hidden" name="_form_scroll_y" value="{{ old('_form_scroll_y', 0) }}" data-form-scroll-y>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('form[data-preserve-scroll]');
        const scrollInput = form?.querySelector('[data-form-scroll-y]');

        if (!form || !scrollInput) return;

        form.addEventListener('submit', function () {
            scrollInput.value = String(Math.max(0, Math.round(window.scrollY)));
        });

        @if($errors->any() && is_numeric(old('_form_scroll_y')))
            const submittedScrollY = Number(@json(old('_form_scroll_y')));
            if (submittedScrollY > 0) {
                requestAnimationFrame(function () {
                    window.scrollTo({ top: submittedScrollY, behavior: 'instant' });
                });
            }
        @endif
    });
</script>
