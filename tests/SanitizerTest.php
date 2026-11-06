<?php

use AltDesign\AltGoogleMapsAddon\Fieldtypes\GoogleMapsFieldtype;
use AltDesign\AltGoogleMapsAddon\Tests\TestCase;

uses(TestCase::class);

// ── sanitizeHtml ──────────────────────────────────────────────────────────────

it('strips <script> tags from infobox content', function () {
    $input = '<p>Hello</p><script>alert("xss")</script>';
    expect(GoogleMapsFieldtype::sanitizeHtml($input))->not->toContain('<script>');
});

it('strips <script> with attributes', function () {
    $input = '<script type="text/javascript">evil()</script>';
    expect(GoogleMapsFieldtype::sanitizeHtml($input))
        ->not->toContain('<script')
        ->not->toContain('evil()');
});

it('strips self-closing <script> tags', function () {
    $input = '<script src="evil.js"/>';
    expect(GoogleMapsFieldtype::sanitizeHtml($input))->not->toContain('<script');
});

it('strips <iframe> tags', function () {
    $input = '<iframe src="https://evil.com"></iframe>';
    expect(GoogleMapsFieldtype::sanitizeHtml($input))->not->toContain('<iframe');
});

it('strips <style> tags', function () {
    $input = '<style>body{background:url("javascript:alert(1)")}</style>';
    expect(GoogleMapsFieldtype::sanitizeHtml($input))->not->toContain('<style');
});

it('strips <object> and <embed> tags', function () {
    expect(GoogleMapsFieldtype::sanitizeHtml('<object data="x.swf"></object>'))->not->toContain('<object');
    expect(GoogleMapsFieldtype::sanitizeHtml('<embed src="x.swf">'))->not->toContain('<embed');
});

it('strips on* event handler attributes (double-quoted)', function () {
    $input = '<img src="x.png" onerror="alert(1)">';
    expect(GoogleMapsFieldtype::sanitizeHtml($input))->not->toContain('onerror');
});

it('strips on* event handler attributes (single-quoted)', function () {
    $input = "<a href='#' onclick='evil()'>click</a>";
    expect(GoogleMapsFieldtype::sanitizeHtml($input))->not->toContain('onclick');
});

it('strips on* event handler attributes (unquoted)', function () {
    $input = '<img src=x onload=evil()>';
    expect(GoogleMapsFieldtype::sanitizeHtml($input))->not->toContain('onload');
});

it('removes javascript: from href', function () {
    $input = '<a href="javascript:alert(1)">click</a>';
    $result = GoogleMapsFieldtype::sanitizeHtml($input);
    expect($result)->not->toContain('javascript:');
});

it('removes javascript: from src', function () {
    $input = '<img src="javascript:alert(1)">';
    $result = GoogleMapsFieldtype::sanitizeHtml($input);
    expect($result)->not->toContain('javascript:');
});

it('removes data: URI from src', function () {
    $input = '<img src="data:text/html,<script>alert(1)</script>">';
    $result = GoogleMapsFieldtype::sanitizeHtml($input);
    expect($result)->not->toContain('data:text/html');
});

it('removes vbscript: from href', function () {
    $input = '<a href="vbscript:msgbox(1)">x</a>';
    expect(GoogleMapsFieldtype::sanitizeHtml($input))->not->toContain('vbscript:');
});

it('preserves safe HTML tags', function () {
    $input = '<p><strong>Hello</strong> <em>world</em></p>';
    $result = GoogleMapsFieldtype::sanitizeHtml($input);
    expect($result)
        ->toContain('<p>')
        ->toContain('<strong>')
        ->toContain('<em>');
});

it('preserves safe links', function () {
    $input = '<a href="https://example.com">Visit</a>';
    $result = GoogleMapsFieldtype::sanitizeHtml($input);
    expect($result)
        ->toContain('href="https://example.com"')
        ->toContain('Visit');
});

it('returns empty string for empty input', function () {
    expect(GoogleMapsFieldtype::sanitizeHtml(''))->toBe('');
    expect(GoogleMapsFieldtype::sanitizeHtml(null))->toBe('');
});

it('handles case-insensitive script tags', function () {
    $input = '<SCRIPT>alert(1)</SCRIPT>';
    expect(GoogleMapsFieldtype::sanitizeHtml($input))->not->toContain('alert(1)');
});

it('handles mixed-case on* attributes', function () {
    $input = '<img src="x" onError="evil()">';
    expect(GoogleMapsFieldtype::sanitizeHtml($input))->not->toContain('onError');
});

// ── sanitizePlainText ─────────────────────────────────────────────────────────

it('strips all HTML tags from plain text', function () {
    $input = '<b>Bold</b> and <i>italic</i>';
    expect(GoogleMapsFieldtype::sanitizePlainText($input))->toBe('Bold and italic');
});

it('trims whitespace from plain text', function () {
    expect(GoogleMapsFieldtype::sanitizePlainText('  hello  '))->toBe('hello');
});

it('returns empty string for null plain text', function () {
    expect(GoogleMapsFieldtype::sanitizePlainText(null))->toBe('');
});

it('strips script tags (but not their text content) from plain text', function () {
    // strip_tags() removes <script>…</script> markup but keeps inner text.
    // That is safe because plain text fields are rendered via textContent, never innerHTML.
    $input = '<script>alert(1)</script>Title';
    expect(GoogleMapsFieldtype::sanitizePlainText($input))->toBe('alert(1)Title');
});

// ── sanitizeUrl ───────────────────────────────────────────────────────────────

it('allows https URLs', function () {
    $url = 'https://example.com/marker.png';
    expect(GoogleMapsFieldtype::sanitizeUrl($url))->toBe($url);
});

it('allows http URLs', function () {
    $url = 'http://example.com/marker.png';
    expect(GoogleMapsFieldtype::sanitizeUrl($url))->toBe($url);
});

it('allows relative paths', function () {
    $url = '/assets/img/pin.png';
    expect(GoogleMapsFieldtype::sanitizeUrl($url))->toBe($url);
});

it('blocks javascript: protocol', function () {
    expect(GoogleMapsFieldtype::sanitizeUrl('javascript:alert(1)'))->toBe('');
});

it('blocks data: URI', function () {
    expect(GoogleMapsFieldtype::sanitizeUrl('data:text/html,<script>alert(1)</script>'))->toBe('');
});

it('blocks vbscript: protocol', function () {
    expect(GoogleMapsFieldtype::sanitizeUrl('vbscript:msgbox(1)'))->toBe('');
});

it('blocks javascript: with leading whitespace', function () {
    expect(GoogleMapsFieldtype::sanitizeUrl('  javascript:evil()'))->toBe('');
});

it('returns empty string for empty URL', function () {
    expect(GoogleMapsFieldtype::sanitizeUrl(''))->toBe('');
    expect(GoogleMapsFieldtype::sanitizeUrl(null))->toBe('');
});

it('blocks DATA: protocol (uppercase)', function () {
    expect(GoogleMapsFieldtype::sanitizeUrl('DATA:text/html,x'))->toBe('');
});

it('strips event handlers on a slash-separated svg tag', function () {
    expect(GoogleMapsFieldtype::sanitizeHtml('<svg/onload=alert(1)>'))->not->toContain('onload');
});

it('strips event handlers separated by slashes on an img tag', function () {
    expect(GoogleMapsFieldtype::sanitizeHtml('<img/src=x/onerror=alert(1)>'))->not->toContain('onerror');
});

it('removes entity-encoded javascript: URLs from links', function () {
    $result = GoogleMapsFieldtype::sanitizeHtml('<a href="jav&#x61;script:alert(1)">x</a>');
    expect($result)->not->toContain('href');
});

it('removes javascript: URLs hidden by whitespace inside the scheme', function () {
    $result = GoogleMapsFieldtype::sanitizeHtml("<a href=\"java\tscript:alert(1)\">x</a>");
    expect($result)->not->toContain('href');
});

it('keeps the text of unknown tags and drops their attributes', function () {
    expect(GoogleMapsFieldtype::sanitizeHtml('<font color="red" onclick="x()">Open</font>'))->toBe('Open');
});

it('adds rel noopener to links that open a new tab', function () {
    $result = GoogleMapsFieldtype::sanitizeHtml('<a href="https://example.com" target="_blank">Visit</a>');
    expect($result)->toContain('rel="noopener noreferrer"');
});
