<?php
declare(strict_types=1);

/**
 * Home page content — every word and photo on / , editable at
 * /admin/home.php.
 *
 * The current copy lives here as defaults, NOT in the database: an
 * untouched install renders exactly what it rendered before, a missing
 * site_settings table changes nothing, and "revert to original" is just
 * deleting a row. The database only ever holds what mom or stepdad have
 * actually changed.
 *
 * home_schema() drives both the page and the admin form, so a new field
 * is added in one place.
 */

function home_schema(): array {
    static $schema = null;
    if ($schema !== null) return $schema;
    return $schema = [
        'seo' => [
            'label' => 'Search engines & sharing',
            'blurb' => 'What Google shows and what appears when the site is shared.',
            'fields' => [
                'seo_title' => [
                    'label' => 'Page title',
                    'type' => 'text',
                    'default' => 'Scrappy Dolls — Handmade Cloth Dolls & Memory Dolls by Kanda Kay',
                    'hint' => 'Shown in the browser tab and as the blue headline in Google.',
                ],
                'seo_description' => [
                    'label' => 'Description',
                    'type' => 'textarea',
                    'default' => 'Scrappy Dolls by artist Kanda Kay — one-of-a-kind handmade cloth dolls and custom memory dolls stitched from quilting cottons, vintage prints, and fabric remnants. OOAK art dolls, folk art tradition, and the scrappy doll community.',
                    'hint' => 'The grey summary under the headline in Google. Around 155 characters reads best.',
                ],
                'seo_image' => [
                    'label' => 'Sharing image',
                    'type' => 'image',
                    'default' => 'images/og-image.jpg',
                    'hint' => 'Used when someone posts a link on Facebook.',
                    'alt_default' => 'Scrappy Dolls by Kanda Kay',
                ],
            ],
        ],
        'hero' => [
            'label' => 'Hero — the top of the page',
            'blurb' => 'The first thing a visitor sees.',
            'fields' => [
                'hero_eyebrow' => [
                    'label' => 'Eyebrow',
                    'type' => 'text',
                    'default' => 'Handmade by Kanda Kay',
                    'hint' => 'The small caps label above the headline.',
                ],
                'hero_headline' => [
                    'label' => 'Headline',
                    'type' => 'headline',
                    'default' => 'Cloth dolls,
stitched *one at a time*.',
                    'hint' => 'Enter for a line break. Put *stars* around words to colour them rose.',
                ],
                'hero_lede' => [
                    'label' => 'Opening paragraph',
                    'type' => 'rich',
                    'default' => 'Scrappy Dolls is a growing collection of one-of-a-kind cloth dolls and custom memory dolls — each hand-cut and stitched from quilting cottons, vintage prints, and beloved fabric remnants too lovely to throw away.',
                ],
                'hero_btn1_label' => [
                    'label' => 'Main button',
                    'type' => 'text',
                    'default' => 'Follow on Facebook',
                ],
                'hero_btn1_url' => [
                    'label' => 'Main button link',
                    'type' => 'link',
                    'default' => 'https://www.facebook.com/kandakayartist/',
                ],
                'hero_btn2_label' => [
                    'label' => 'Second button',
                    'type' => 'text',
                    'default' => 'See the dolls',
                ],
                'hero_btn2_url' => [
                    'label' => 'Second button link',
                    'type' => 'link',
                    'default' => '#gallery',
                ],
                'hero_image' => [
                    'label' => 'Hero photo',
                    'type' => 'image',
                    'default' => 'images/doll-rainbow-hair.jpg',
                    'alt_default' => 'Handmade Scrappy Doll by Kanda Kay with multicolored yarn hair, embroidered features, and a vibrant patchwork dress with lace trim',
                ],
                'hero_badge' => [
                    'label' => 'Badge on the photo',
                    'type' => 'headline',
                    'default' => 'No two *alike*',
                ],
            ],
            'list' => [
                'key' => 'hero_stats',
                'label' => 'Three small facts under the buttons',
                'noun' => 'fact',
                'item_fields' => [
                    'k' => ['label' => 'Big text', 'type' => 'text'],
                    'v' => ['label' => 'Small text', 'type' => 'text'],
                ],
                'default' => [
                    ['k' => '100%', 'v' => 'Handmade'],
                    ['k' => '1 of 1', 'v' => 'Every doll'],
                    ['k' => '♥', 'v' => 'Stitched with love'],
                ],
            ],
        ],
        'about' => [
            'label' => 'The Studio',
            'blurb' => '',
            'fields' => [
                'about_eyebrow' => [
                    'label' => 'Eyebrow',
                    'type' => 'text',
                    'default' => 'The Studio',
                ],
                'about_headline' => [
                    'label' => 'Headline',
                    'type' => 'headline',
                    'default' => 'Made by hand.
Made *to keep*.',
                ],
                'about_quote' => [
                    'label' => 'Pull quote',
                    'type' => 'rich',
                    'default' => 'Every Scrappy Doll begins as a pile of fabric — quilt offcuts, an old pillowcase, the last good piece of a favorite shirt.',
                    'hint' => 'The larger italic line.',
                ],
                'about_body' => [
                    'label' => 'Body',
                    'type' => 'rich',
                    'default' => 'Kanda Kay cuts and pieces each doll by hand, machine-stitches the seams for strength, and finishes with embroidered features and a name only that doll will ever wear. The result is a small, characterful keepsake — warm-feeling, hand-finished, and unmistakably one of a kind.',
                    'hint' => 'Leave a blank line between paragraphs to start a new one.',
                ],
                'about_image' => [
                    'label' => 'Photo',
                    'type' => 'image',
                    'default' => 'images/doll-feature-horns.jpg',
                    'alt_default' => 'A handmade Scrappy Doll by Kanda Kay — brown curly hair, a poppy headband, and a green floral dress',
                ],
            ],
        ],
        'process' => [
            'label' => 'The Process',
            'blurb' => '',
            'fields' => [
                'process_eyebrow' => [
                    'label' => 'Eyebrow',
                    'type' => 'text',
                    'default' => 'The Process',
                ],
                'process_headline' => [
                    'label' => 'Headline',
                    'type' => 'headline',
                    'default' => 'From scraps
to *heirloom*.',
                ],
            ],
            'list' => [
                'key' => 'process_items',
                'label' => 'Steps',
                'noun' => 'step',
                'item_fields' => [
                    'title' => ['label' => 'Title', 'type' => 'text'],
                    'body' => ['label' => 'Text', 'type' => 'rich'],
                ],
                'default' => [
                    ['title' => 'Gather', 'body' => 'Vintage prints, quilt remnants, and meaningful scraps — every doll begins with fabric that already has a story.'],
                    ['title' => 'Cut & piece', 'body' => 'Pattern pieces are hand-cut, then arranged and pieced into a unique combination of color, weight, and texture.'],
                    ['title' => 'Stitch', 'body' => 'Each seam is machine-sewn for strength and hand-stitched for detail. Faces are embroidered with thread — not printed or stamped.'],
                    ['title' => 'Finish', 'body' => 'Hair, jewelry, dresses, and details are added one at a time until a doll has clearly arrived as itself.'],
                ],
            ],
        ],
        'size' => [
            'label' => 'Hold one in your hands',
            'blurb' => '',
            'fields' => [
                'size_eyebrow' => [
                    'label' => 'Eyebrow',
                    'type' => 'text',
                    'default' => 'Hold one in your hands',
                ],
                'size_headline' => [
                    'label' => 'Headline',
                    'type' => 'headline',
                    'default' => 'About a *foot tall*.',
                ],
                'size_body' => [
                    'label' => 'Body',
                    'type' => 'rich',
                    'default' => 'Most Scrappy Dolls stand around 12 inches — small enough to hold, big enough to have presence on a shelf, a bookcase, or a window seat.

There\'s natural variation: some are a little taller, some a little stouter, depending on the fabric and the personality that emerges along the way. Each one\'s exact size is part of who she is.',
                ],
                'size_image' => [
                    'label' => 'Size photo',
                    'type' => 'image',
                    'default' => 'images/size.png',
                    'alt_default' => 'A handmade Scrappy Doll standing beside a 12-inch wooden ruler, showing the doll is approximately one foot tall',
                ],
            ],
        ],
        'artist' => [
            'label' => 'Meet Kanda',
            'blurb' => '',
            'fields' => [
                'artist_eyebrow' => [
                    'label' => 'Eyebrow',
                    'type' => 'text',
                    'default' => 'Meet Kanda Kay',
                ],
                'artist_headline' => [
                    'label' => 'Headline',
                    'type' => 'headline',
                    'default' => 'A lifetime of *making*.',
                ],
                'artist_lede' => [
                    'label' => 'Opening paragraph',
                    'type' => 'rich',
                    'default' => 'Kanda grew up in a family of painters, photographers, musicians, and seamstresses — making was simply the language spoken at home.',
                ],
                'artist_body' => [
                    'label' => 'Body',
                    'type' => 'rich',
                    'default' => 'After studying art education at [Kansas University (KU)](https://ku.edu/), she opened her own weaving shop. While homeschooling her three children, she kept creative work at the center of family life — and watched that next generation grow into artists, musicians, photographers, graphic designers, and web developers in their own right.

In retirement, she founded [Art Safari Studio](https://www.facebook.com/kandakayartist/) and has never stopped making. Her work there has gravitated toward combining everyday materials — quilt offcuts, vintage prints, the last good piece of a beloved shirt — into one-of-a-kind pieces. Scrappy Dolls is where that lifelong practice has landed.',
                    'hint' => 'Links look like [the words](https://the-address.com).',
                ],
                'artist_image' => [
                    'label' => 'Photo of Kanda',
                    'type' => 'image',
                    'default' => 'images/kanda-kay.png',
                    'alt_default' => 'Kanda Kay — artist and maker behind Scrappy Dolls',
                ],
            ],
        ],
        'gallery' => [
            'label' => 'Available Now (the doll roster)',
            'blurb' => 'The dolls themselves come from the shop — this is just the wording around them.',
            'fields' => [
                'gallery_eyebrow' => [
                    'label' => 'Eyebrow',
                    'type' => 'text',
                    'default' => 'Available Now',
                ],
                'gallery_headline' => [
                    'label' => 'Headline',
                    'type' => 'headline',
                    'default' => 'A roster of *characters*.',
                ],
                'gallery_note' => [
                    'label' => 'Note beside the headline',
                    'type' => 'rich',
                    'default' => 'A live look at the studio. Click any doll to take her home.',
                ],
            ],
        ],
        'testimonials' => [
            'label' => 'Kind Words',
            'blurb' => '',
            'fields' => [
                'testimonials_eyebrow' => [
                    'label' => 'Eyebrow',
                    'type' => 'text',
                    'default' => 'Kind Words',
                ],
                'testimonials_headline' => [
                    'label' => 'Headline',
                    'type' => 'headline',
                    'default' => 'From *collectors*.',
                ],
            ],
            'list' => [
                'key' => 'testimonial_items',
                'label' => 'Quotes',
                'noun' => 'quote',
                'item_fields' => [
                    'quote' => ['label' => 'What they said', 'type' => 'rich'],
                    'name' => ['label' => 'Name', 'type' => 'text'],
                    'meta' => ['label' => 'Underneath the name', 'type' => 'text'],
                ],
                'default' => [
                    ['quote' => 'Kanda is an AMAZING artist! She is friendly, professional, very reasonable in pricing, and responsive. We are SO HAPPY with the final product — she captured our furr-babes so perfectly in her whimsical, fun way!', 'name' => 'Carrie S.', 'meta' => 'Commissioned pet portraits'],
                    ['quote' => 'I know and recommend this artist — she is amazing and talented. One of a kind.', 'name' => 'Albert H.', 'meta' => 'Collector'],
                    ['quote' => 'This artist is magic! I have quite a few pieces, plus one that was specifically commissioned.', 'name' => 'Terise B.', 'meta' => 'Collector & commission client'],
                ],
            ],
        ],
        'tradition' => [
            'label' => 'The Tradition',
            'blurb' => '',
            'fields' => [
                'tradition_eyebrow' => [
                    'label' => 'Eyebrow',
                    'type' => 'text',
                    'default' => 'The Tradition',
                ],
                'tradition_headline' => [
                    'label' => 'Headline',
                    'type' => 'headline',
                    'default' => 'What are *scrappy dolls*?',
                ],
                'tradition_body' => [
                    'label' => 'Body',
                    'type' => 'rich',
                    'default' => 'Scrappy dolls are handmade cloth dolls stitched from leftover fabric — quilting cottons, vintage prints, worn-out clothing, and remnants too small for anything else but too beautiful to discard. The tradition runs centuries deep. In early America, mothers and grandmothers fashioned dolls from household scraps — old dresses, flour sacks, handkerchiefs — using whatever the household could spare. Appalachian folk dolls, prairie dolls, and Amish faceless dolls all grew from this same impulse: take what you have and make something worth keeping.

What sets scrappy dolls apart is the material itself. Every scrap carries a history — a quilt that wore through, a child\'s outgrown shirt, the last cut from a bolt of fabric a grandmother picked out. The doll becomes a vessel for those stories. No two scrappy dolls look alike because no two fabric piles are the same. The wonky proportions, mismatched prints, and hand-stitched imperfections are not flaws. They are the entire point.',
                ],
            ],
        ],
        'faq' => [
            'label' => 'Frequently Asked',
            'blurb' => '',
            'fields' => [
                'faq_eyebrow' => [
                    'label' => 'Eyebrow',
                    'type' => 'text',
                    'default' => 'Frequently Asked',
                ],
                'faq_headline' => [
                    'label' => 'Headline',
                    'type' => 'headline',
                    'default' => 'Good *questions*.',
                ],
            ],
            'list' => [
                'key' => 'faq_items',
                'label' => 'Questions',
                'noun' => 'question',
                'item_fields' => [
                    'q' => ['label' => 'Question', 'type' => 'text'],
                    'a' => ['label' => 'Answer', 'type' => 'rich'],
                ],
                'default' => [
                    ['q' => 'Are dolls available to purchase?', 'a' => 'Yes. Browse available dolls in the [shop](/shop/) — each is one of a kind, so when she\'s gone, she\'s gone. New work is announced on [Art Safari Studio\'s Facebook page](https://www.facebook.com/kandakayartist/) as it comes off the table.'],
                    ['q' => 'Can a doll be made from my own fabric?', 'a' => 'Memory dolls — made from outgrown clothing, a wedding dress, a beloved quilt — are part of what scrappy dolls are best at. Reach out to Kanda on [Facebook](https://www.facebook.com/kandakayartist/) to talk through your fabric and what you\'d like.'],
                    ['q' => 'How big are the dolls?', 'a' => 'Most Scrappy Dolls stand around 12 inches tall — about a foot — with natural variation depending on the fabric and the character that emerges. [See the size comparison →](#size)'],
                    ['q' => 'How much is shipping?', 'a' => '**Free shipping on orders $50 or more.** Otherwise: $7.99 for the first doll and $2.99 each additional doll in the same order. Calculated automatically at checkout — bundling is the cheapest way to bring more than one home.'],
                    ['q' => 'How do I care for a Scrappy Doll?', 'a' => 'Spot clean only, with a damp cloth and mild soap if needed. Treat your Scrappy Doll as a display piece — hand-washing, soaking, or laundering will loosen the adhesives and undermine the fabric construction, and can cause the doll to fall apart.'],
                    ['q' => 'How long does it take to make a doll?', 'a' => 'It depends on the fabric, the character, and the level of detail. A doll can take anywhere from an afternoon to several days — and each one tells you when it\'s done.'],
                ],
            ],
        ],
        'follow' => [
            'label' => 'Follow (the dark card near the bottom)',
            'blurb' => '',
            'fields' => [
                'follow_eyebrow' => [
                    'label' => 'Eyebrow',
                    'type' => 'text',
                    'default' => 'Stay close',
                ],
                'follow_headline' => [
                    'label' => 'Headline',
                    'type' => 'headline',
                    'default' => 'See new dolls
as they\'re *finished*.',
                ],
                'follow_body' => [
                    'label' => 'Body',
                    'type' => 'rich',
                    'default' => 'Follow Art Safari Studio on Facebook for new work, sneak peeks of what\'s on the table, and the stories behind the dolls.',
                ],
                'follow_btn_label' => [
                    'label' => 'Button',
                    'type' => 'text',
                    'default' => 'Follow on Facebook',
                ],
                'follow_btn_url' => [
                    'label' => 'Button link',
                    'type' => 'link',
                    'default' => 'https://www.facebook.com/kandakayartist/',
                ],
            ],
        ],
        'footer' => [
            'label' => 'Footer',
            'blurb' => '',
            'fields' => [
                'footer_tagline' => [
                    'label' => 'Line under the name',
                    'type' => 'rich',
                    'default' => '[from Art Safari Studio · Handmade by Kanda Kay](https://www.facebook.com/kandakayartist/)',
                ],
                'footer_legal' => [
                    'label' => 'Small print',
                    'type' => 'text',
                    'default' => 'Scrappy Dolls · San Antonio, Texas.',
                    'hint' => 'The year is added automatically in front of this.',
                ],
            ],
        ],
    ];
}

/** Flat map of field key => definition, across all sections. */
function home_fields(): array {
    static $flat = null;
    if ($flat !== null) return $flat;
    $flat = [];
    foreach (home_schema() as $section) {
        foreach ($section['fields'] as $key => $def) $flat[$key] = $def;
    }
    return $flat;
}

function home_field(string $key): array {
    return home_fields()[$key] ?? ['label' => $key, 'type' => 'text', 'default' => ''];
}

/** Settings key for a home field — namespaced so it can\'t collide. */
function home_setting_key(string $key): string {
    return 'home_' . $key;
}

/**
 * The raw value of a field: what they saved, or the original copy.
 * An empty saved value means "empty on purpose" — only a missing row
 * falls back to the default.
 */
function home_raw(string $key): string {
    $all = settings_all();
    $sk  = home_setting_key($key);
    if (array_key_exists($sk, $all)) return $all[$sk];
    return (string)(home_field($key)['default'] ?? '');
}

/** Has this field been changed from the original copy? */
function home_is_customized(string $key): bool {
    return array_key_exists(home_setting_key($key), settings_all());
}

// ---------------------------------------------------------------
// Rendering
// ---------------------------------------------------------------

/** Plain text, escaped. */
function home_text(string $key): string {
    return h(home_raw($key));
}

/**
 * The small formatting vocabulary mom and stepdad get. Everything is
 * escaped first, so what comes back is only ever the tags below.
 *
 *   [words](https://link)  -> a link (external ones open in a new tab)
 *   **words**              -> bold
 *   *words*                -> italic
 */
function home_inline(string $text): string {
    $out = h($text);

    $out = preg_replace_callback(
        '/\[([^\]]{1,200})\]\(([^)\s]{1,300})\)/',
        function (array $m): string {
            $label = $m[1];
            $href  = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
            // Same rule as the promo bar: same-site paths, anchors, mailto, or http(s).
            $ok = preg_match('#^/[^/\\\\]#', $href)
               || preg_match('#^\#[\w-]+$#', $href)
               || preg_match('#^mailto:#i', $href)
               || preg_match('#^https?://#i', $href);
            if (!$ok) return $label;
            $external = (bool)preg_match('#^https?://#i', $href);
            return '<a href="' . h($href) . '"'
                . ($external ? ' target="_blank" rel="noopener"' : '') . '>' . $label . '</a>';
        },
        $out
    ) ?? $out;

    $out = preg_replace('/\*\*([^*]{1,300})\*\*/', '<strong>$1</strong>', $out) ?? $out;
    $out = preg_replace('/(?<!\*)\*([^*]{1,300})\*(?!\*)/', '<em>$1</em>', $out) ?? $out;

    return $out;
}

/** A field as inline HTML (links/bold/italic honoured, no wrapper). */
function home_line(string $key): string {
    return home_inline(home_raw($key));
}

/**
 * Any text as paragraphs: blank lines start a new one. $class lands on
 * every <p> so the section keeps its type styling. Used directly for
 * list-item bodies, which aren\'t settings keys of their own.
 */
function home_paragraphs(string $text, string $class = ''): string {
    $blocks = preg_split('/\R{2,}/', trim($text)) ?: [];
    $html = '';
    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') continue;
        $html .= '<p' . ($class !== '' ? ' class="' . h($class) . '"' : '') . '>'
            . nl2br(home_inline($block)) . '</p>';
    }
    return $html;
}

/** A field as paragraphs. */
function home_rich(string $key, string $class = 'rich-p'): string {
    return home_paragraphs(home_raw($key), $class);
}

/**
 * A headline: line breaks become <br>, and *starred words* become the
 * rose accent the design already uses.
 */
function home_headline(string $key, string $accentVar = '--rose'): string {
    $out = h(home_raw($key));
    // '' asks for a bare <em> — the hero badge styles its own em in CSS.
    $open = $accentVar === ''
        ? '<em>'
        : '<em style="color: var(' . $accentVar . '); font-style: italic; font-weight: 400;">';
    $out = preg_replace('/\*([^*]{1,200})\*/', $open . '$1</em>', $out) ?? $out;
    return nl2br($out);
}

/** A URL field, safe to drop into href. */
function home_url(string $key): string {
    $raw = trim(home_raw($key));
    if ($raw === '') return '';
    $ok = preg_match('#^/[^/\\\\]#', $raw)
       || preg_match('#^\#[\w-]+$#', $raw)
       || preg_match('#^mailto:#i', $raw)
       || preg_match('#^https?://#i', $raw);
    return $ok ? h($raw) : '';
}

// ---------------------------------------------------------------
// Images
// ---------------------------------------------------------------

/**
 * Where a section image actually lives. Uploaded files sit in /uploads/;
 * the originals that shipped with the site sit in /images/.
 */
function home_image_url(string $key): string {
    $val = trim(home_raw($key));
    if ($val === '') {
        $val = (string)(home_field($key)['default'] ?? '');
    }
    if ($val === '') return '';
    // Defaults are repo paths ("images/x.jpg"); uploads are bare filenames.
    return strpos($val, '/') !== false ? url($val) : url('uploads/' . $val);
}

function home_image_alt(string $key): string {
    $altKey = $key . '_alt';
    $all = settings_all();
    if (array_key_exists(home_setting_key($altKey), $all)) {
        return h($all[home_setting_key($altKey)]);
    }
    return h((string)(home_field($key)['alt_default'] ?? ''));
}

// ---------------------------------------------------------------
// Repeating lists (process steps, testimonials, FAQ, hero facts)
// ---------------------------------------------------------------

/** The list definition for a section, or null. */
function home_list_def(string $section): ?array {
    return home_schema()[$section]['list'] ?? null;
}

/**
 * The rows for a section\'s list: what they saved, or the original set.
 * Bad JSON falls back to the original rather than emptying the section.
 */
function home_list(string $section): array {
    $def = home_list_def($section);
    if (!$def) return [];
    $all = settings_all();
    $sk  = home_setting_key($def['key']);
    if (array_key_exists($sk, $all)) {
        $rows = json_decode($all[$sk], true);
        if (is_array($rows)) {
            // Keep only the fields the schema knows about.
            $clean = [];
            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $item = [];
                foreach ($def['item_fields'] as $fk => $_) $item[$fk] = (string)($row[$fk] ?? '');
                $clean[] = $item;
            }
            return $clean;
        }
        error_log('home_list: bad JSON for ' . $sk . ' — showing the original ' . $section . ' items');
    }
    return $def['default'];
}

/**
 * Save one uploaded section photo: sniff the type, downscale to a
 * web-friendly JPEG, drop it in /uploads/. Mirrors handle_image_upload()
 * for products, minus the product_images row and the thumbnail — these
 * are shown full width, so only the display size is needed.
 *
 * Returns ['filename' => string|null, 'error' => string|null].
 */
function home_handle_image_upload(string $key, array $file): array {
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) return ['filename' => null, 'error' => null];

    $label = home_field($key)['label'] ?? $key;
    if ($err !== UPLOAD_ERR_OK) {
        $msg = $err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE
            ? "That photo is too big for the server to accept. Try one under 10 MB."
            : "Upload failed (error $err).";
        return ['filename' => null, 'error' => "$label: $msg"];
    }

    $cfg     = config('uploads');
    $maxSize = (int)($cfg['max_size'] ?? 10485760);
    $allowed = $cfg['allowed_mimes'] ?? ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) {
        return ['filename' => null, 'error' => "$label: that file is too large (limit "
            . round($maxSize / 1048576) . " MB)."];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
    if ($finfo) finfo_close($finfo);
    if (!$mime || !in_array($mime, $allowed, true)) {
        return ['filename' => null, 'error' => "$label: that doesn\'t look like a photo "
            . '(JPEG, PNG, WEBP, or GIF).'];
    }

    $uploadDir = realpath(__DIR__ . '/../uploads');
    if ($uploadDir === false) {
        @mkdir(__DIR__ . '/../uploads', 0755, true);
        $uploadDir = realpath(__DIR__ . '/../uploads');
    }
    if ($uploadDir === false) {
        return ['filename' => null, 'error' => "$label: the uploads folder isn\'t writable."];
    }

    $scratch = $uploadDir . DIRECTORY_SEPARATOR . '_tmp_' . bin2hex(random_bytes(8));
    if (!move_uploaded_file($file['tmp_name'], $scratch)) {
        return ['filename' => null, 'error' => "$label: couldn\'t save the upload."];
    }

    // Always JPEG out, downscaled to the display long edge — the same
    // treatment doll photos get.
    $newName = 'home-' . preg_replace('/[^a-z0-9_]/', '', $key) . '-'
        . bin2hex(random_bytes(6)) . '.jpg';
    $dest = $uploadDir . DIRECTORY_SEPARATOR . $newName;

    $ok = image_resize($scratch, $dest, IMAGE_DISPLAY_LONG_EDGE, IMAGE_DISPLAY_QUALITY);
    @unlink($scratch);

    if (!$ok) {
        @unlink($dest);
        return ['filename' => null, 'error' => "$label: couldn\'t process that image."];
    }
    @chmod($dest, 0644);

    return ['filename' => $newName, 'error' => null];
}

/**
 * Remove a previously uploaded section photo. Only ever touches files in
 * /uploads/ — the originals in /images/ are part of the repo and stay.
 */
function home_delete_uploaded_image(string $filename): void {
    $filename = trim($filename);
    if ($filename === '' || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) return;
    if (strpos($filename, 'home-') !== 0) return;
    $dir = realpath(__DIR__ . '/../uploads');
    if (!$dir) return;
    $path = $dir . DIRECTORY_SEPARATOR . $filename;
    if (is_file($path)) @unlink($path);
}

/** Is this the settings key of one of the repeating lists? */
function home_is_list_key(string $key): bool {
    foreach (home_schema() as $section) {
        if (($section['list']['key'] ?? null) === $key) return true;
    }
    return false;
}
