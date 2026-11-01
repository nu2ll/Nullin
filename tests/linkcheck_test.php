<?php

declare(strict_types=1);

if (!extension_loaded('mbstring')) {
    fwrite(STDERR, "Tests require ext-mbstring, as does the application.\n");
    exit(1);
}

require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/Features/LinkCheck.php';
require __DIR__ . '/../src/Features/LinkSites.php';
require __DIR__ . '/../src/Features/Intent.php';
require __DIR__ . '/../src/Core/Switches.php';

$count = 0;
function same($actual, $expected, string $label): void
{
    global $count;
    $count++;
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL {$label}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}
same(LinkCheck::extract('https://example.com/a?b=1'), 'https://example.com/a?b=1', 'plain url');
same(LinkCheck::extract('check this: https://evil.com/x, ok'), 'https://evil.com/x', 'url inside text, trailing punctuation dropped');
same(LinkCheck::extract('www.example.com/path'), 'https://www.example.com/path', 'www gets scheme');
same(LinkCheck::extract('example.com'), 'https://example.com', 'bare domain alone');
same(LinkCheck::extract('two words example.com'), null, 'bare domain inside sentence ignored');
same(LinkCheck::extract('سلام چطوری'), null, 'no link');
same(LinkCheck::extract('«https://t.me/+AbCdEf»'), 'https://t.me/+AbCdEf', 'guillemets stripped');
same(LinkCheck::extract(''), null, 'empty');

same(LinkCheck::detect('https://github.com/torvalds/linux/commit/abcdef1'), 'github', 'github commit');
same(LinkCheck::detect('https://github.com/torvalds'), 'github', 'github profile');
same(LinkCheck::detect('https://github.com/torvalds/linux'), 'github', 'github repo');
same(LinkCheck::detect('https://t.me/+AAAAAAAAAAAAAAAAAAAA'), 'telegram', 'telegram invite');
same(LinkCheck::detect('https://t.me/durov'), 'telegram', 'telegram public');
same(LinkCheck::detect('https://discord.gg/abc123'), 'discord', 'discord invite');
same(LinkCheck::detect('https://discord.com/channels/123/456/789'), 'discord_channels', 'discord channels');
same(LinkCheck::detect('https://gitlab.com/foo'), 'gitlab', 'gitlab profile');
same(LinkCheck::detect('https://huggingface.co/bert-base'), 'huggingface', 'huggingface');
same(LinkCheck::detect('https://docs.google.com/document/d/1AbCdEfGhIjKlMnOpQrStUvWxYz012345/edit'), 'gdoc', 'google doc');
same(LinkCheck::detect('https://example.com/x'), null, 'unknown site');
same(LinkCheck::detect('https://evil.com/?u=https://github.com/foo'), null, 'embedded url does not match');

$raw = pack('V', 123456789) . str_repeat("\1", 12);
same(LinkCheck::decodeInvite(rtrim(strtr(base64_encode($raw), '+/', '-_'), '=')), ['data' => ['user_id' => '123456789']], 'legacy invite decodes creator');
same(isset(LinkCheck::decodeInvite(rtrim(strtr(base64_encode(str_repeat("\1", 12)), '+/', '-_'), '='))['error']), true, 'new-format invite is opaque');

same(LinkCheck::snowflake('175928847299117063'), '2016-04-30 11:18:25 UTC', 'snowflake time');

same(LinkCheck::parseCount('2K'), 2000, 'count K');
same(LinkCheck::parseCount('1.5M'), 1500000, 'count M');
same(LinkCheck::parseCount('1,234'), 1234, 'count comma');
same(LinkCheck::parseCount('12 345 678'), 12345678, 'count spaces');
same(LinkCheck::parseCount('abc'), null, 'count junk');

same(LinkCheck::microsoftEmail('https://contoso-my.sharepoint.com/:x:/g/personal/john_doe_contoso_com/EabC'), 'john.doe@contoso.com', 'sharepoint email');
same(LinkCheck::microsoftEmail('https://example.com/x'), null, 'not sharepoint');

same(LinkCheck::parseFrom("From abc Mon Sep 17 00:00:00 2001\nFrom: Linus T <linus@example.org>\nDate: x\n"), ['Linus T', 'linus@example.org'], 'patch author');
same(LinkCheck::parseFrom("From: \"Doe, John\" <j@x.io>\n"), ['Doe, John', 'j@x.io'], 'quoted author');
same(LinkCheck::parseFrom("nothing here"), null, 'no author');

same(LinkCheck::formatData(['email' => 'a@b.c', 'is_noreply' => true]), "• ایمیل: <code>a@b.c</code>\n• ایمیل مخفی‌شده (noreply): بله", 'format email and bool');
same(LinkCheck::formatData(['name' => null, 'user_id' => '42']), '• آیدی عددی: <code>42</code>', 'format skips null');
same(LinkCheck::formatData(['follower_count' => 1500]), '• تعداد فالوئر: ۱,۵۰۰', 'format count');
same(LinkCheck::formatData(['name' => '<b>x</b>']), '• نام: <code>&lt;b&gt;x&lt;/b&gt;</code>', 'format escapes html');

$nid = '0123456789abcdef0123456789abcdef';
foreach ([
    ['https://vm.tiktok.com/ZMabc123/', 'tiktok'],
    ['https://www.tiktok.com/@user.name/video/123', 'tiktok'],
    ['https://chatgpt.com/share/abc-123', 'chatgpt'],
    ['https://claude.ai/share/abc-def', 'claude'],
    ['https://www.instagram.com/reel/AbC_1/', 'instagram'],
    ['https://www.perplexity.ai/search/foo-bar', 'perplexity'],
    ['https://pin.it/AbC123', 'pinterest'],
    ['https://www.reddit.com/r/php/s/AbC123', 'reddit'],
    ['https://substack.com/@user/note/c-123', 'substack'],
    ['https://suno.com/s/AbC123', 'suno'],
    ['https://www.notion.so/My-Page-' . $nid, 'notion'],
    ['https://team.notion.site/Page-' . $nid, 'notion'],
    ['https://www.linkedin.com/in/john-doe', 'linkedin'],
    ['https://youtu.be/dQw4w9WgXcQ', 'youtube'],
    ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube'],
    ['https://www.youtube.com/@mkbhd', 'youtube'],
    ['https://evil.com/?u=https://vm.tiktok.com/ZMabc123/', null],
    ['https://evil.com/https://chatgpt.com/share/abc-123', null],
    ['https://x.notion.site@evil.com/p', null],
] as [$u, $want]) {
    same(LinkCheck::detect($u), $want, 'detect ' . $u);
}

same(LinkSites::youtubeVideoId('https://youtu.be/dQw4w9WgXcQ'), 'dQw4w9WgXcQ', 'yt short link');
same(LinkSites::youtubeVideoId('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=5'), 'dQw4w9WgXcQ', 'yt watch');
same(LinkSites::youtubeVideoId('https://www.youtube.com/shorts/abcdefghijk'), 'abcdefghijk', 'yt shorts');
same(LinkSites::youtubeVideoId('https://www.youtube.com/@mkbhd'), null, 'yt channel is not video');

same(LinkSites::notionPageId('https://www.notion.so/My-Page-' . $nid . '?pvs=4'), '01234567-89ab-cdef-0123-456789abcdef', 'notion id from slug');
same(LinkSites::notionPageId('https://www.notion.so/' . $nid), '01234567-89ab-cdef-0123-456789abcdef', 'notion bare id');
same(LinkSites::notionPageId('https://www.notion.so/workspace'), null, 'notion without id');

same(LinkSites::extractBlob('var ytInitialData = {"a":{"b":"x}"},"c":[1,2]};</script>', 'ytInitialData'), ['a' => ['b' => 'x}'], 'c' => [1, 2]], 'blob extraction respects strings');
same(LinkSites::extractBlob('nothing', 'ytInitialData'), null, 'blob missing');
same(LinkSites::findKey(['x' => [['y' => ['k' => 5]]]], 'k'), 5, 'findKey nested');
same(LinkSites::findKey(['x' => 1], 'k'), null, 'findKey missing');

same(LinkCheck::formatData(['other_editors' => [['name' => 'A', 'email' => 'a@b.c']]]), "• ویرایشگرهای دیگه:\n<code>A &lt;a@b.c&gt;</code>", 'format editors');
same(LinkCheck::formatData(['external_links' => [['title' => 'T', 'url' => 'https://x.y']]]), "• لینک‌های بیرونی:\n<code>T: https://x.y</code>", 'format links');
same(LinkCheck::formatData(['private_account' => false]), '• اکانت خصوصی: خیر', 'format false bool');

same(in_array('linkcheck', Intent::NAMES, true), false, 'intent no longer knows linkcheck');
same(Intent::parse('[[linkcheck | https://x.com]]'), null, 'intent ignores linkcheck');
same(isset(Switches::TOOLS['linkcheck']), true, 'switch exists');
same(Switches::COMMAND_SWITCH['st'] ?? null, 'linkcheck', 'command maps to switch');

echo "PASS {$count} linkcheck assertions (offline, no network).\n";
