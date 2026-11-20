@php
    $iframeSnippet = '<iframe src="'.$widget->embedUrl().'" width="100%" height="500" loading="lazy" title="Customer Testimonials"></iframe>';
    $scriptSnippet = '<div data-kudosly-widget="'.$widget->public_id.'"></div>'."\n".'<script async src="'.$widget->embedScriptUrl().'"></script>';
@endphp

<div class="space-y-4">
    <div>
        <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">iframe embed</h3>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Paste this into any page. It works on every website and every device.
        </p>

        <textarea readonly id="widget-iframe-code" rows="3" readonly
            class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300">{{ $iframeSnippet }}</textarea>

        <button type="button" data-copy-target="widget-iframe-code"
            class="mt-2 inline-flex items-center rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            Copy iframe code
        </button>
    </div>

    <div>
        <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">JavaScript embed</h3>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            This loader inserts the widget into the placeholder. It never changes the styles of your page.
        </p>

        <textarea readonly id="widget-script-code" rows="4" readonly
            class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300">{{ $scriptSnippet }}</textarea>

        <button type="button" data-copy-target="widget-script-code"
            class="mt-2 inline-flex items-center rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            Copy JavaScript code
        </button>
    </div>
</div>

<script>
    (function () {
        const buttons = Array.from(document.querySelectorAll('[data-copy-target]'));

        if (!buttons.length) {
            return;
        }

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                const target = document.getElementById(button.dataset.copyTarget);

                if (!target) {
                    return;
                }

                target.select();
                target.setSelectionRange(0, target.value.length);

                try {
                    navigator.clipboard.writeText(target.value).then(function () {
                        button.textContent = 'Copied';
                        setTimeout(function () {
                            button.textContent = button.dataset.copyLabel || 'Copy';
                        }, 1500);
                    });
                } catch (error) {
                    document.execCommand('copy');
                }
            });
        });
    })();
</script>
