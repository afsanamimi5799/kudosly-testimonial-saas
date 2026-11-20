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
