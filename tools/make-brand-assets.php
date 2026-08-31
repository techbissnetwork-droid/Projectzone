<?php
declare(strict_types=1);

/**
 * TECHBISS — brand asset generator.
 *
 * Draws the favicons, app icons and social share image from the same geometry
 * as assets/images/brand/logo-mark.svg. Run it again after changing the brand
 * colours below and every raster asset stays in step with the vector master.
 *
 *   php tools/make-brand-assets.php
 *
 * Everything is drawn with GD rather than rasterised from the SVG, so the build
 * needs no ImageMagick or librsvg. Shapes are drawn on a supersampled canvas and
 * scaled down, which is what gives the edges their antialiasing.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is a build tool and only runs from the command line.\n");
}

const OUT   = __DIR__ . '/../assets/images/brand';
const SS    = 4;               // supersampling factor
const FONT_BOLD    = '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf';
const FONT_REGULAR = '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf';

// Brand colours — keep in step with assets/css/design-system.css
const ACCENT = [0x4F, 0x8C, 0xFF];
const MID    = [0x3F, 0x82, 0xF2];
const CYAN   = [0x34, 0xD3, 0xE0];
const INK    = [0x06, 0x07, 0x0C];
const PAPER  = [0xFF, 0xFF, 0xFF];

// ---------------------------------------------------------------------
// Drawing primitives
// ---------------------------------------------------------------------

function canvas(int $w, int $h): \GdImage
{
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);
    return $im;
}

/** Filled rounded rectangle: body plus circular corners. */
function roundedRect(\GdImage $im, float $x, float $y, float $w, float $h, float $r, int $colour): void
{
    $r = min($r, $w / 2, $h / 2);
    $x2 = $x + $w; $y2 = $y + $h;
    imagefilledrectangle($im, (int) round($x + $r), (int) round($y), (int) round($x2 - $r), (int) round($y2), $colour);
    imagefilledrectangle($im, (int) round($x), (int) round($y + $r), (int) round($x2), (int) round($y2 - $r), $colour);
    $d = (int) round($r * 2);
    foreach ([[$x + $r, $y + $r], [$x2 - $r, $y + $r], [$x + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
        imagefilledellipse($im, (int) round($cx), (int) round($cy), $d, $d, $colour);
    }
}

/**
 * Build an 8-bit coverage mask by drawing at SS× and averaging down. Doing the
 * antialiasing here rather than relying on GD's own means the mark keeps clean
 * edges at 16px, where a single misplaced pixel is visible.
 *
 * @return array<int,array<int,float>> coverage 0..1 indexed [y][x]
 */
function coverageMask(int $size, callable $draw): array
{
    $big  = $size * SS;
    $im   = imagecreatetruecolor($big, $big);
    $black = imagecolorallocate($im, 0, 0, 0);
    $white = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, 0, 0, $big, $big, $black);
    $draw($im, $white, (float) $big);

    $small = imagecreatetruecolor($size, $size);
    imagecopyresampled($small, $im, 0, 0, 0, 0, $size, $size, $big, $big);
    imagedestroy($im);

    $mask = [];
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            $mask[$y][$x] = (imagecolorat($small, $x, $y) & 0xFF) / 255;
        }
    }
    imagedestroy($small);
    return $mask;
}

/** Diagonal three-stop gradient sampled at pixel resolution. */
function gradientAt(float $t): array
{
    $t = max(0.0, min(1.0, $t));
    [$from, $to, $local] = $t < 0.55
        ? [ACCENT, MID, $t / 0.55]
        : [MID, CYAN, ($t - 0.55) / 0.45];
    return [
        (int) round($from[0] + ($to[0] - $from[0]) * $local),
        (int) round($from[1] + ($to[1] - $from[1]) * $local),
        (int) round($from[2] + ($to[2] - $from[2]) * $local),
    ];
}

/**
 * Render the mark at any size.
 *
 * @param bool  $rings  draw the orbit rings (dropped below ~48px, where they
 *                      only add noise once the browser downsamples)
 * @param float $bleed  0 for a normal icon; ~0.1 for a maskable icon, which
 *                      platforms crop to a circle
 */
function renderMark(int $size, bool $rings = true, float $bleed = 0.0, bool $heavyGlyph = false): \GdImage
{
    $im = canvas($size, $size);

    // Tile geometry on a 0..1 grid, then scaled — matches logo-mark.svg.
    $inset = $size * $bleed;
    $tileW = $size - $inset * 2;
    $radius = $tileW * ($heavyGlyph ? 0.22 : 0.24);

    $tileMask = coverageMask($size, function (\GdImage $im, int $white, float $s) use ($bleed, $radius, $size): void {
        $k = $s / $size;
        roundedRect($im, $bleed * $s, $bleed * $s, $s - $bleed * 2 * $s, $s - $bleed * 2 * $s, $radius * $k, $white);
    });

    // Glyph: crossbar + stem. The heavy variant thickens both for small sizes.
    $glyphMask = coverageMask($size, function (\GdImage $im, int $white, float $s) use ($heavyGlyph, $inset, $tileW, $size): void {
        $u = static fn (float $v): float => ($inset + $tileW * ($v / 100)) * ($s / $size);
        if ($heavyGlyph) {
            // Small sizes get a heavier glyph so the T survives downsampling.
            roundedRect($im, $u(19), $u(25), $u(62) - $u(0), $u(16) - $u(0), $u(2) - $u(0), $white);
            roundedRect($im, $u(42), $u(37), $u(16) - $u(0), $u(38) - $u(0), $u(2) - $u(0), $white);
        } else {
            roundedRect($im, $u(22), $u(26), $u(56) - $u(0), $u(14) - $u(0), $u(2) - $u(0), $white);
            roundedRect($im, $u(43), $u(38), $u(14) - $u(0), $u(36) - $u(0), $u(2) - $u(0), $white);
        }
    });

    $ringMask = $rings ? coverageMask($size, function (\GdImage $im, int $white, float $s) use ($inset, $tileW, $size): void {
        $k     = $s / $size;
        $cx    = (int) round(($inset + $tileW / 2) * $k);
        $black = imagecolorallocate($im, 0, 0, 0);
        $ring  = static function (float $outer, float $inner) use ($im, $cx, $tileW, $k, $white, $black): void {
            $d = static fn (float $f): int => (int) round($tileW * $f * $k);
            imagefilledellipse($im, $cx, $cx, $d($outer), $d($outer), $white);
            imagefilledellipse($im, $cx, $cx, $d($inner), $d($inner), $black);
        };
        // Both rings sit near the tile edge so they frame the glyph instead of
        // crossing it, which at small sizes made the mark read as a target.
        // Largest first: each inner cut-out sits outside the next ring.
        $ring(0.99, 0.966);
        $ring(0.86, 0.836);
    }) : [];

    // Composite: gradient inside the tile, rings at low opacity, glyph on top.
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            $tile = $tileMask[$y][$x];
            if ($tile <= 0.002) {
                continue;
            }
            [$r, $g, $b] = gradientAt(($x + $y) / (2 * max(1, $size - 1)));

            if ($rings) {
                $ring = ($ringMask[$y][$x] ?? 0) * 0.15;
                if ($ring > 0) {
                    $r += (255 - $r) * $ring;
                    $g += (255 - $g) * $ring;
                    $b += (255 - $b) * $ring;
                }
            }
            $glyph = $glyphMask[$y][$x];
            if ($glyph > 0) {
                $r += (255 - $r) * $glyph;
                $g += (255 - $g) * $glyph;
                $b += (255 - $b) * $glyph;
            }
            $alpha = (int) round((1 - $tile) * 127);
            imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, (int) $r, (int) $g, (int) $b, $alpha));
        }
    }
    return $im;
}

/** Write a PNG and report it. */
function save(\GdImage $im, string $name): void
{
    $path = OUT . '/' . $name;
    imagesavealpha($im, true);
    imagepng($im, $path, 9);
    printf("  %-28s %5d KB\n", $name, (int) ceil(filesize($path) / 1024));
    imagedestroy($im);
}

/** Assemble a multi-resolution .ico from PNG frames. */
function writeIco(array $sizes, string $name): void
{
    $frames = [];
    foreach ($sizes as $s) {
        $im = renderMark($s, $s >= 64, 0.0, $s <= 48);
        ob_start();
        imagepng($im, null, 9);
        $frames[] = ['size' => $s, 'data' => (string) ob_get_clean()];
        imagedestroy($im);
    }

    // ICONDIR: reserved, type 1 (icon), image count
    $ico = pack('vvv', 0, 1, count($frames));
    $offset = 6 + 16 * count($frames);
    foreach ($frames as $f) {
        // ICONDIRENTRY. 0 means 256 in the width/height byte; planes 1, 32bpp.
        $ico .= pack(
            'CCCCvvVV',
            $f['size'] >= 256 ? 0 : $f['size'],
            $f['size'] >= 256 ? 0 : $f['size'],
            0, 0, 1, 32,
            strlen($f['data']),
            $offset
        );
        $offset += strlen($f['data']);
    }
    foreach ($frames as $f) {
        $ico .= $f['data'];
    }

    $path = OUT . '/' . $name;
    file_put_contents($path, $ico);
    printf("  %-28s %5d KB  (%s)\n", $name, (int) ceil(filesize($path) / 1024), implode('/', $sizes));
}

// ---------------------------------------------------------------------
// Social share image
// ---------------------------------------------------------------------

/**
 * Translucent rounded pill, composited in one pass.
 *
 * roundedRect() overlaps its body and corner circles; with alpha blending on,
 * those overlaps blend twice and the corners come out darker than the middle.
 * Building the shape on its own layer with blending off avoids that.
 */
function pill(\GdImage $dst, int $x, int $y, int $w, int $h, int $alpha, array $rgb = [255, 255, 255], ?array $borderRgb = null): void
{
    $layer = imagecreatetruecolor($w, $h);
    imagealphablending($layer, false);
    imagesavealpha($layer, true);
    imagefilledrectangle($layer, 0, 0, $w, $h, imagecolorallocatealpha($layer, 0, 0, 0, 127));

    if ($borderRgb !== null) {
        roundedRect($layer, 0, 0, $w, $h, $h / 2, imagecolorallocatealpha($layer, $borderRgb[0], $borderRgb[1], $borderRgb[2], max(0, $alpha - 18)));
        roundedRect($layer, 1, 1, $w - 2, $h - 2, ($h - 2) / 2, imagecolorallocatealpha($layer, $rgb[0], $rgb[1], $rgb[2], $alpha));
    } else {
        roundedRect($layer, 0, 0, $w, $h, $h / 2, imagecolorallocatealpha($layer, $rgb[0], $rgb[1], $rgb[2], $alpha));
    }

    imagealphablending($dst, true);
    imagecopy($dst, $layer, $x, $y, 0, 0, $w, $h);
    imagedestroy($layer);
}

function text(\GdImage $im, string $font, float $size, int $x, int $y, int $colour, string $s, float $tracking = 0): array
{
    if ($tracking == 0.0) {
        $box = imagettftext($im, $size, 0, $x, $y, $colour, $font, $s);
        return [$box[2] - $box[0], $box[1] - $box[7]];
    }
    // GD has no letter-spacing, so tracked text is drawn a character at a time.
    $cursor = $x;
    foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
        imagettftext($im, $size, 0, (int) round($cursor), $y, $colour, $font, $ch);
        $m = imagettfbbox($size, 0, $font, $ch);
        $cursor += ($m[2] - $m[0]) + $tracking;
    }
    return [(int) round($cursor - $x), (int) $size];
}

function measure(string $font, float $size, string $s, float $tracking = 0): int
{
    if ($tracking == 0.0) {
        $b = imagettfbbox($size, 0, $font, $s);
        return $b[2] - $b[0];
    }
    $w = 0;
    foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
        $b = imagettfbbox($size, 0, $font, $ch);
        $w += ($b[2] - $b[0]) + $tracking;
    }
    return (int) round($w - $tracking);
}

/** Greedy wrap by measured width. @return array<int,string> */
function wrap(string $font, float $size, string $text, int $maxWidth): array
{
    $lines = [];
    $line  = '';
    foreach (explode(' ', $text) as $word) {
        $try = $line === '' ? $word : $line . ' ' . $word;
        if ($line !== '' && measure($font, $size, $try) > $maxWidth) {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $try;
        }
    }
    if ($line !== '') {
        $lines[] = $line;
    }
    return $lines;
}

function makeOgImage(string $name, string $headline, string $sub, array $chips): void
{
    $W = 1200; $H = 630;
    $im = imagecreatetruecolor($W, $H);
    imagealphablending($im, true);
    imagefilledrectangle($im, 0, 0, $W, $H, imagecolorallocate($im, INK[0], INK[1], INK[2]));

    // Accent glow behind the top-left, mirroring the site's hero treatment.
    for ($y = 0; $y < $H; $y++) {
        for ($x = 0; $x < $W; $x += 2) {
            $d1 = sqrt((($x - 210) / 620) ** 2 + (($y + 130) / 620) ** 2);
            $d2 = sqrt((($x - 1120) / 520) ** 2 + (($y - 120) / 520) ** 2);
            $a  = max(0, 1 - $d1) ** 2 * 0.42 + max(0, 1 - $d2) ** 2 * 0.20;
            if ($a <= 0.004) {
                continue;
            }
            $c = imagecolorat($im, $x, $y);
            $r = (int) min(255, (($c >> 16) & 0xFF) + (0x4F - 0x06) * $a);
            $g = (int) min(255, (($c >> 8) & 0xFF) + (0x8C - 0x07) * $a * 0.9);
            $b = (int) min(255, ($c & 0xFF) + (0xFF - 0x0C) * $a);
            $col = imagecolorallocate($im, $r, $g, $b);
            imagesetpixel($im, $x, $y, $col);
            imagesetpixel($im, $x + 1, $y, $col);
        }
    }

    // Fine grid, fading out toward the bottom.
    for ($x = 0; $x <= $W; $x += 60) {
        for ($y = 0; $y < $H; $y++) {
            $f = 1 - ($y / $H) * 0.85;
            imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, 255, 255, 255, (int) (127 - 9 * $f)));
        }
    }
    for ($y = 0; $y <= $H; $y += 60) {
        $f = 1 - ($y / $H) * 0.85;
        $c = imagecolorallocatealpha($im, 255, 255, 255, (int) (127 - 9 * $f));
        imageline($im, 0, $y, $W, $y, $c);
    }

    $white = imagecolorallocate($im, 255, 255, 255);
    $soft  = imagecolorallocate($im, 0xB4, 0xBC, 0xCD);
    $muted = imagecolorallocate($im, 0x83, 0x8D, 0xA3);
    $accent = imagecolorallocate($im, 0x7A, 0xAB, 0xFF);

    $PAD = 84;
    $maxText = $W - $PAD * 2;

    // Mark + wordmark
    $mark = renderMark(84, true);
    imagecopy($im, $mark, $PAD, 70, 0, 0, 84, 84);
    imagedestroy($mark);
    text($im, FONT_BOLD, 33, $PAD + 106, 123, $white, 'TECHBISS', 6.2);

    // Headline. The closing phrase is tinted, the same emphasis the hero uses.
    $y = 238;
    foreach (wrap(FONT_BOLD, 60, $headline, $maxText) as $line) {
        $tint = 'Starts Here.';
        if (str_contains($line, $tint)) {
            $head = substr($line, 0, (int) strpos($line, $tint));
            text($im, FONT_BOLD, 60, $PAD, $y, $white, $head);
            text($im, FONT_BOLD, 60, $PAD + measure(FONT_BOLD, 60, $head), $y, $accent, $tint);
        } else {
            text($im, FONT_BOLD, 60, $PAD, $y, $white, $line);
        }
        $y += 74;
    }

    // Supporting copy, wrapped to the same measure
    $y += 12;
    foreach (wrap(FONT_REGULAR, 25, $sub, $maxText) as $line) {
        text($im, FONT_REGULAR, 25, $PAD, $y, $soft, $line);
        $y += 38;
    }

    // Capability chips, dropped once the row would reach the right margin
    $cx = $PAD;
    $cy = $H - 104;
    foreach ($chips as $chip) {
        $w = measure(FONT_REGULAR, 18, $chip) + 38;
        if ($cx + $w > $W - $PAD) {
            break;
        }
        pill($im, (int) $cx, (int) $cy, (int) $w, 42, 112, [255, 255, 255], [255, 255, 255]);
        text($im, FONT_REGULAR, 18, (int) $cx + 19, (int) $cy + 27, $muted, $chip);
        $cx += $w + 9;
    }

    // Accent rule: a true gradient rather than two abutting blocks
    for ($x = 0; $x < $W; $x++) {
        [$r, $g, $b] = gradientAt($x / max(1, $W - 1));
        imagefilledrectangle($im, $x, $H - 6, $x, $H, imagecolorallocate($im, $r, $g, $b));
    }

    $path = OUT . '/' . $name;
    imagepng($im, $path, 9);
    printf("  %-28s %5d KB  1200x630\n", $name, (int) ceil(filesize($path) / 1024));
    imagedestroy($im);
}

// ---------------------------------------------------------------------
// Build
// ---------------------------------------------------------------------
if (!is_dir(OUT)) {
    mkdir(OUT, 0775, true);
}
foreach ([FONT_BOLD, FONT_REGULAR] as $f) {
    if (!is_file($f)) {
        fwrite(STDERR, "Font not found: $f\nInstall fonts-liberation, or point FONT_BOLD/FONT_REGULAR at a font you have.\n");
        exit(1);
    }
}

echo "Icons\n";
foreach ([16, 32, 48] as $s) {
    save(renderMark($s, false, 0.0, true), "favicon-{$s}.png");   // no rings, heavier glyph
}
save(renderMark(180), 'apple-touch-icon.png');
save(renderMark(192), 'icon-192.png');
save(renderMark(512), 'icon-512.png');
// Maskable icons are cropped to a circle by Android, so the mark is inset.
save(renderMark(512, true, 0.10), 'icon-maskable-512.png');
writeIco([16, 32, 48], 'favicon.ico');

echo "\nLogo rasters\n";
save(renderMark(256), 'logo-mark-256.png');

echo "\nSocial\n";
makeOgImage(
    'og-image.png',
    'Your Digital Business Starts Here.',
    'From offline business to premium digital brand — one partner, everything digital.',
    ['Domain', 'Hosting', 'Website', 'Business Email', 'Apps', 'Branding', 'SEO']
);

echo "\nDone. Assets written to assets/images/brand/\n";
