<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">

        <title>Customer Testimonials</title>

        @include('widgets.partials.styles')
    </head>
    <body class="kudosly-widget-body">
        @include('widgets.partials.render', ['widget' => $widget, 'testimonials' => $testimonials])

        <script>
            (function () {
                var widgetId = @json($widget->public_id);

                function report() {
                    var height = document.body.scrollHeight;

                    if (window.parent === window) {
                        return;
                    }

                    window.parent.postMessage({
                        type: 'kudosly-widget-resize',
                        widgetId: widgetId,
                        height: height
                    }, '*');
                }

                if (window.ResizeObserver) {
                    new ResizeObserver(report).observe(document.body);
                } else {
                    window.addEventListener('load', report);
                }

                report();
            })();
        </script>
    </body>
</html>
