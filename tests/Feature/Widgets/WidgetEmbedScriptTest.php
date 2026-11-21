<?php

use App\Models\Widget;

/*
|--------------------------------------------------------------------------
| The widget loader script
|--------------------------------------------------------------------------
|
| The loader script is placed on a website that the customer does not
| control. It must therefore never inject a stylesheet and never touch
| anything on the host page beyond the placeholder it was asked to fill.
|
*/

test('the loader script is served as javascript', function () {
    $response = $this->get(route('widgets.embed.script'));

    $response->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('javascript');
});

test('the loader script is served without a session', function () {
    $this->get(route('widgets.embed.script'))->assertOk();
});

test('the loader script targets the placeholder attribute', function () {
    $script = $this->get(route('widgets.embed.script'))->getContent();

    expect($script)->toContain('[data-kudosly-widget]');
});

test('the loader script inserts an iframe', function () {
    $script = $this->get(route('widgets.embed.script'))->getContent();

    expect($script)->toContain("document.createElement('iframe')")
        ->and($script)->toContain('iframe.setAttribute(\'src\'');
});

test('the loader script never injects a stylesheet', function () {
    $script = $this->get(route('widgets.embed.script'))->getContent();

    expect($script)->not->toContain('createElement(\'style\')')
        ->and($script)->not->toContain('createElement("style")')
        ->and($script)->not->toContain('appendChild(style');
});

test('the loader script never injects a stylesheet link', function () {
    $script = $this->get(route('widgets.embed.script'))->getContent();

    expect($script)->not->toContain('createElement(\'link\')')
        ->and($script)->not->toContain('createElement("link")');
});

test('the loader script contains no global style rules', function () {
    $script = $this->get(route('widgets.embed.script'))->getContent();

    // A rule that reaches outside the widget would change the host page.
    expect($script)->not->toMatch('/body\s*\{/')
        ->and($script)->not->toMatch('/\bhtml\s*\{/')
        ->and($script)->not->toContain('document.head')
        ->and($script)->not->toContain('insertRule')
        ->and($script)->not->toContain('cssText');
});

test('the loader script only writes inside the placeholder it was given', function () {
    $script = $this->get(route('widgets.embed.script'))->getContent();

    expect($script)->toContain('placeholder.appendChild(iframe)');
});

test('the loader script applies the height the widget reports', function () {
    $script = $this->get(route('widgets.embed.script'))->getContent();

    // The widget document reports its height, and the loader resizes the
    // iframe so that the content is never clipped.
    expect($script)->toContain('kudosly-widget-resize')
        ->and($script)->toContain("addEventListener('message'")
        ->and($script)->toContain('iframe.style.height');
});

/*
|--------------------------------------------------------------------------
| The resize message
|--------------------------------------------------------------------------
|
| The loader listens for a message that tells it how tall the widget is.
| That listener lives on a page the customer does not control, so it only
| acts on a message that comes from the widget itself, on the very iframe
| it placed, and that carries a height that is safe to use. Every other
| message is ignored.
|
*/

test('the loader only accepts a message from the widget origin', function () {
    $script = $this->get(route('widgets.embed.script'))->getContent();

    expect($script)->toContain('event.origin')
        ->and($script)->toContain('widgetOrigin');
});

test('the loader only accepts a message from its own iframe', function () {
    $script = $this->get(route('widgets.embed.script'))->getContent();

    expect($script)->toContain('event.source')
        ->and($script)->toContain('iframe.contentWindow');
});

test('the loader only accepts a message with the expected shape', function () {
    $script = $this->get(route('widgets.embed.script'))->getContent();

    // The payload has to be an object with the expected type, the expected
    // widget and a number for the height.
    expect($script)->toContain("event.data.type !== 'kudosly-widget-resize'")
        ->and($script)->toContain('event.data.widgetId !== widgetId')
        ->and($script)->toContain('typeof height');
});

test('the loader refuses a height outside the safe range', function () {
    $script = $this->get(route('widgets.embed.script'))->getContent();

    expect($script)->toContain('MIN_HEIGHT')
        ->and($script)->toContain('MAX_HEIGHT')
        ->and($script)->toContain('height < MIN_HEIGHT || height > MAX_HEIGHT');
});

test('the loader refuses a height that is not a real number', function () {
    $script = $this->get(route('widgets.embed.script'))->getContent();

    // A height of NaN or Infinity would produce a broken frame height.
    expect($script)->toContain('!isFinite(height)');
});

test('the loader refuses a payload that is not an object', function () {
    $script = $this->get(route('widgets.embed.script'))->getContent();

    expect($script)->toContain("typeof event.data !== 'object'")
        ->and($script)->toContain('Array.isArray(event.data)');
});

test('the loader keeps working on a third party website', function () {
    $script = $this->get(route('widgets.embed.script'))->getContent();

    // The expected origin is the origin of the widget, which the loader
    // reads from its own iframe address. The host page never has to be
    // known, so the widget works on any website that embeds it.
    expect($script)->toContain('iframe.getAttribute(\'src\')')
        ->and($script)->not->toContain('document.referrer')
        ->and($script)->not->toContain('parent.location');
});

test('the widget document reports its height to the host', function () {
    $widget = Widget::factory()->create();

    $html = $this->get(route('widgets.embed.show', ['widget' => $widget->public_id]))->getContent();

    expect($html)->toContain('kudosly-widget-resize')
        ->and($html)->toContain('postMessage');
});

test('the loader script may be cached', function () {
    $response = $this->get(route('widgets.embed.script'));

    expect($response->headers->get('Cache-Control'))->toContain('public');
});

test('the widget stylesheet is scoped to the widget', function () {
    $widget = Widget::factory()->create();

    $html = $this->get(route('widgets.embed.show', ['widget' => $widget->public_id]))->getContent();

    // Every rule targets a kudosly-widget class, so nothing escapes into
    // the host page.
    expect($html)->toContain('.kudosly-widget')
        ->and($html)->not->toMatch('/<style>\s*body\s*\{/');
});

/*
|--------------------------------------------------------------------------
| The loader finds the widget on a third party website
|--------------------------------------------------------------------------
|
| The loader is placed on a website that the customer does not control,
| so it can never look for the widget under a relative path. A relative
| path would send the request to the host website, which answers with
| its own pages and never with the widget. The iframe therefore has to
| carry the full address of the widget on the Kudosly origin.
|
*/

test('the loader never looks for the widget under a relative path', function () {
    $script = $this->get(route('widgets.embed.script'))->getContent();

    // A relative path would be resolved against the host website.
    expect($script)->not->toContain("iframe.setAttribute('src', '/embed/'")
        ->and($script)->toContain("iframe.setAttribute('src', embedUrlFor(placeholder));");
});

test('the loader reads its own origin while the script is running', function () {
    $script = $this->get(route('widgets.embed.script'))->getContent();

    // The script is async, so document.currentScript is gone by the time
    // the placeholders are looked for. The origin has to be captured
    // while the script is still running.
    expect($script)->toContain('var LOADER_ORIGIN = (function ()')
        ->and($script)->toContain('document.currentScript')
        ->and($script)->toContain('new URL(script.src).origin');
});

test('the loader builds the widget address from the origin of the loader', function () {
    $script = $this->get(route('widgets.embed.script'))->getContent();

    expect($script)->toContain('LOADER_ORIGIN + \'/widgets/embed.js\'')
        ->and($script)->toContain("url.pathname.replace(/\\/widgets\\/embed\\.js$/, '/embed/'");
});

test('the loader falls back to the page it runs on', function () {
    $script = $this->get(route('widgets.embed.script'))->getContent();

    // Without a script address there is nothing better to go by than the
    // address of the page itself.
    expect($script)->toContain('window.location.href');
});

/*
|--------------------------------------------------------------------------
| The widget measures its own content
|--------------------------------------------------------------------------
|
| The document element is sized by the iframe, not by the content, so
| measuring it would report the height of the iframe and the frame could
| never become smaller. The body is sized by the content, so measuring
| the body gives the height that has to be applied and lets the frame
| both grow and shrink.
|
*/

test('the widget measures the height of its content', function () {
    $widget = Widget::factory()->create();

    $html = $this->get(route('widgets.embed.show', ['widget' => $widget->public_id]))->getContent();

    expect($html)->toContain('document.body.scrollHeight')
        ->and($html)->not->toContain('document.documentElement.scrollHeight');
});

test('the widget watches its content for changes', function () {
    $widget = Widget::factory()->create();

    $html = $this->get(route('widgets.embed.show', ['widget' => $widget->public_id]))->getContent();

    // Watching the document element would never notice that the content
    // changed, because the document element is sized by the iframe.
    expect($html)->toContain('new ResizeObserver(report).observe(document.body)')
        ->and($html)->not->toContain('observe(document.documentElement)');
});
