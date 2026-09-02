<?php
declare(strict_types=1);

/**
 * TECHBISS — social media profile & cover image generator.
 *
 * Reuses the exact brand geometry and colours from make-brand-assets.php to
 * produce ready-to-upload profile pictures and cover/banner photos for every
 * platform that takes one. Writes into assets/images/brand/social/, which is
 * part of the repo — run this again whenever the brand colours or wordmark
 * change and the whole set stays in step.
 *
 *   php tools/make-social-assets.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

const OUT = __DIR__ . '/../assets/images/brand/social';
const SS  = 4;
const FONT_BOLD    = '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf';
const FONT_REGULAR = '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf';

const ACCENT = [0xC8, 0xFF, 0x4D];
const MID    = [0x77, 0xEC, 0x8B];
const CYAN   = [0x26, 0xD9, 0xC9];
const INK    = [0x07, 0x08, 0x0A];

// ---------------------------------------------------------------------
// Drawing primitives — identical to tools/make-brand-assets.php
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

/** Same T-mark as the app icons. $fullBleedBg fills the whole canvas — needed for
 *  any profile picture, since every platform in this list circle-crops it itself. */
function renderMark(int $size, bool $rings, float $bleed, bool $heavyGlyph, bool $fullBleedBg): \GdImage
{
    $im = canvas($size, $size);
    $inset = $size * $bleed;
    $tileW = $size - $inset * 2;
    $radius = $tileW * ($heavyGlyph ? 0.22 : 0.24);

    $tileMask = coverageMask($size, function (\GdImage $im, int $white, float $s) use ($bleed, $radius, $size, $fullBleedBg): void {
        if ($fullBleedBg) {
            imagefilledrectangle($im, 0, 0, (int) round($s), (int) round($s), $white);
            return;
        }
        $k = $s / $size;
        roundedRect($im, $bleed * $s, $bleed * $s, $s - $bleed * 2 * $s, $s - $bleed * 2 * $s, $radius * $k, $white);
    });

    $glyphMask = coverageMask($size, function (\GdImage $im, int $white, float $s) use ($heavyGlyph, $inset, $tileW, $size): void {
        $u = static fn (float $v): float => ($inset + $tileW * ($v / 100)) * ($s / $size);
        if ($heavyGlyph) {
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
        $ring(0.99, 0.966);
        $ring(0.86, 0.836);
    }) : [];

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

function text(\GdImage $im, string $font, float $size, int $x, int $y, int $colour, string $s, float $tracking = 0): array
{
    if ($tracking == 0.0) {
        $box = imagettftext($im, $size, 0, $x, $y, $colour, $font, $s);
        return [$box[2] - $box[0], $box[1] - $box[7]];
    }
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

function pill(\GdImage $dst, int $x, int $y, int $w, int $h, int $alpha, array $rgb, ?array $borderRgb): void
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

function save(\GdImage $im, string $name): void
{
    $path = OUT . '/' . $name;
    imagesavealpha($im, true);
    imagepng($im, $path, 9);
    printf("  %-34s %5d KB\n", $name, (int) ceil(filesize($path) / 1024));
    imagedestroy($im);
}

// ---------------------------------------------------------------------
// Background: the same glow + fine grid used on og-image.png, at any size.
// ---------------------------------------------------------------------
function paintBackground(\GdImage $im, int $W, int $H): void
{
    imagealphablending($im, true);
    imagefilledrectangle($im, 0, 0, $W, $H, imagecolorallocate($im, INK[0], INK[1], INK[2]));

    $gx1 = $W * 0.17; $gy1 = -$H * 0.2; $gr1 = $W * 0.5;
    $gx2 = $W * 0.93; $gy2 = -$H * 0.19; $gr2 = $W * 0.42;
    for ($y = 0; $y < $H; $y++) {
        for ($x = 0; $x < $W; $x += 2) {
            $d1 = sqrt((($x - $gx1) / $gr1) ** 2 + (($y - $gy1) / $gr1) ** 2);
            $d2 = sqrt((($x - $gx2) / $gr2) ** 2 + (($y - $gy2) / $gr2) ** 2);
            $a  = max(0, 1 - $d1) ** 2 * 0.42 + max(0, 1 - $d2) ** 2 * 0.20;
            if ($a <= 0.004) {
                continue;
            }
            $c = imagecolorat($im, $x, $y);
            $r = (int) min(255, (($c >> 16) & 0xFF) + (0xC8 - 0x07) * $a);
            $g = (int) min(255, (($c >> 8) & 0xFF) + (0xFF - 0x08) * $a * 0.9);
            $b = (int) min(255, ($c & 0xFF) + (0x4D - 0x0A) * $a);
            $col = imagecolorallocate($im, $r, $g, $b);
            imagesetpixel($im, $x, $y, $col);
            imagesetpixel($im, $x + 1, $y, $col);
        }
    }

    $step = max(36, (int) round(min($W, $H) / 11));
    for ($x = 0; $x <= $W; $x += $step) {
        for ($y = 0; $y < $H; $y++) {
            $f = 1 - ($y / $H) * 0.85;
            imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, 255, 255, 255, (int) (127 - 9 * $f)));
        }
    }
    for ($y = 0; $y <= $H; $y += $step) {
        $f = 1 - ($y / $H) * 0.85;
        $c = imagecolorallocatealpha($im, 255, 255, 255, (int) (127 - 9 * $f));
        imageline($im, 0, $y, $W, $y, $c);
    }
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

// ---------------------------------------------------------------------
// Profile picture: full-bleed gradient tile, glyph safely centred — every
// platform below crops this to a circle itself, so no transparent margin.
// ---------------------------------------------------------------------
function makeProfile(string $name, int $size): void
{
    save(renderMark($size, true, 0.16, $size <= 200, true), $name);
}

// ---------------------------------------------------------------------
// Wide cover / header: mark + wordmark, headline, subhead, capability chips.
// Content sits inside a centred "safe box" ($safeW × $safeH) so a platform
// that crops the canvas differently per device never clips what matters.
// ---------------------------------------------------------------------
function makeWideCover(string $name, int $W, int $H, int $safeW, int $safeH, string $headline, string $sub, array $chips): void
{
    $im = canvas($W, $H);
    paintBackground($im, $W, $H);

    $white  = imagecolorallocate($im, 255, 255, 255);
    $soft   = imagecolorallocate($im, 0xC7, 0xCC, 0xD1);
    $muted  = imagecolorallocate($im, 0x9B, 0xA3, 0xAC);
    $accent = imagecolorallocate($im, 0xC8, 0xFF, 0x4D);

    $safeX = (int) round(($W - $safeW) / 2);
    $safeY = (int) round(($H - $safeH) / 2);
    $scale = max(0.5, min(1.3, $safeH / 312));
    $mark  = (int) round(58 * $scale);
    $fMark = 22 * $scale;
    $fHead = 34 * $scale;
    $fSub  = 16 * $scale;
    $fChip = 13 * $scale;
    $maxText = $safeW;

    $m = renderMark($mark, true, 0.0, false, false);
    imagecopy($im, $m, $safeX, $safeY, 0, 0, $mark, $mark);
    imagedestroy($m);
    text($im, FONT_BOLD, $fMark, (int) round($safeX + $mark + $scale * 14), (int) round($safeY + $mark * 0.62), $white, 'TECHBISS', $scale * 3.4);

    $y = $safeY + $mark + (int) round($scale * 42);
    foreach (wrap(FONT_BOLD, $fHead, $headline, $maxText) as $line) {
        $tint = 'Starts Here.';
        if (str_contains($line, $tint)) {
            $head = substr($line, 0, (int) strpos($line, $tint));
            text($im, FONT_BOLD, $fHead, $safeX, $y, $white, $head);
            text($im, FONT_BOLD, $fHead, $safeX + measure(FONT_BOLD, $fHead, $head), $y, $accent, $tint);
        } else {
            text($im, FONT_BOLD, $fHead, $safeX, $y, $white, $line);
        }
        $y += (int) round($fHead * 1.22);
    }

    $y += (int) round($scale * 6);
    foreach (wrap(FONT_REGULAR, $fSub, $sub, $maxText) as $line) {
        text($im, FONT_REGULAR, $fSub, $safeX, $y, $soft, $line);
        $y += (int) round($fSub * 1.5);
    }

    // Chips, right-aligned to the bottom of the safe box, dropped once they
    // would not fit — a short banner (e.g. Facebook's) just gets fewer.
    $bottom = $safeY + $safeH;
    if ($bottom - $y > (int) round($fChip * 2)) {
        $cx = $safeX;
        $cy = $bottom - (int) round($fChip * 2.5);
        foreach ($chips as $chip) {
            $w = measure(FONT_REGULAR, $fChip, $chip) + (int) round($scale * 30);
            $h = (int) round($fChip * 2.3);
            if ($cx + $w > $safeX + $safeW) {
                break;
            }
            pill($im, (int) $cx, (int) $cy, (int) $w, $h, 112, [255, 255, 255], [255, 255, 255]);
            text($im, FONT_REGULAR, $fChip, (int) $cx + (int) round($scale * 15), (int) $cy + (int) round($h * 0.68), $muted, $chip);
            $cx += $w + (int) round($scale * 7);
        }
    }

    // Accent rule along the very bottom of the full canvas.
    for ($x = 0; $x < $W; $x++) {
        [$r, $g, $b] = gradientAt($x / max(1, $W - 1));
        $ruleH = max(3, (int) round($H * 0.01));
        imagefilledrectangle($im, $x, $H - $ruleH, $x, $H, imagecolorallocate($im, $r, $g, $b));
    }

    $path = OUT . '/' . $name;
    imagepng($im, $path, 9);
    printf("  %-34s %5d KB  %dx%d\n", $name, (int) ceil(filesize($path) / 1024), $W, $H);
    imagedestroy($im);
}

/** Very short banner (LinkedIn company page): mark + wordmark + one line, centred. */
function makeSlimCover(string $name, int $W, int $H): void
{
    $im = canvas($W, $H);
    paintBackground($im, $W, $H);
    $white  = imagecolorallocate($im, 255, 255, 255);
    $soft   = imagecolorallocate($im, 0xC7, 0xCC, 0xD1);

    $mark = (int) round($H * 0.52);
    $y = (int) round(($H - $mark) / 2);
    $x = (int) round($W * 0.055);
    $m = renderMark($mark, false, 0.0, true, false);
    imagecopy($im, $m, $x, $y, 0, 0, $mark, $mark);
    imagedestroy($m);

    $fMark = $mark * 0.34;
    $fSub  = $mark * 0.19;
    $tx = $x + $mark + (int) round($mark * 0.24);
    [$tw] = text($im, FONT_BOLD, $fMark, $tx, (int) round($H / 2 - $mark * 0.06), $white, 'TECHBISS', $mark * 0.05);
    text($im, FONT_REGULAR, $fSub, $tx, (int) round($H / 2 + $mark * 0.32), $soft, 'Your Digital Business Starts Here.');

    for ($x2 = 0; $x2 < $W; $x2++) {
        [$r, $g, $b] = gradientAt($x2 / max(1, $W - 1));
        imagefilledrectangle($im, $x2, $H - 3, $x2, $H, imagecolorallocate($im, $r, $g, $b));
    }

    $path = OUT . '/' . $name;
    imagepng($im, $path, 9);
    printf("  %-34s %5d KB  %dx%d\n", $name, (int) ceil(filesize($path) / 1024), $W, $H);
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
        fwrite(STDERR, "Font not found: $f\n");
        exit(1);
    }
}

echo "Profile pictures (square — every platform below crops these to a circle itself)\n";
makeProfile('profile-master-1024.png', 1024);
makeProfile('whatsapp-profile-1080.png', 1080);
makeProfile('facebook-profile-320.png', 320);
makeProfile('instagram-profile-320.png', 320);
makeProfile('x-twitter-profile-400.png', 400);
makeProfile('linkedin-profile-400.png', 400);
makeProfile('youtube-profile-800.png', 800);
makeProfile('tiktok-profile-500.png', 500);
makeProfile('telegram-profile-512.png', 512);

echo "\nCover / header photos\n";
$headline = 'Your Digital Business Starts Here.';
$sub      = 'Websites, apps, hosting and domains — built and run for you.';
$chips    = ['Websites', 'Mobile Apps', 'Hosting', 'Branding'];

// WhatsApp/WhatsApp Business itself has no cover-photo slot — only a profile
// picture. This is for the tools that do put one on a WhatsApp-linked page
// (a WhatsApp Business API provider's hosted profile, a link-in-bio page, a
// CRM's chat widget) at their commonly used 1200x600.
makeWideCover('whatsapp-cover-1200x600.png', 1200, 600, 1020, 440, $headline, $sub, $chips);

// Facebook: 820x312 upload; ~170px on the left is covered by the profile
// picture puck on desktop, so the safe box is shifted right and shortened.
makeWideCover('facebook-cover-820x312.png', 820, 312, 700, 240, $headline, $sub, $chips);

// X/Twitter: 1500x500, safe box leaves margin all round for the app's own
// gradient/avatar overlap at the bottom-left.
makeWideCover('x-twitter-header-1500x500.png', 1500, 500, 1300, 380, $headline, $sub, $chips);

// LinkedIn personal profile banner: 1584x396.
makeWideCover('linkedin-personal-banner-1584x396.png', 1584, 396, 1400, 300, $headline, $sub, $chips);

// LinkedIn company page banner: 1128x191 — too short for a headline, so this
// gets the compact mark + wordmark + tagline treatment instead.
makeSlimCover('linkedin-company-banner-1128x191.png', 1128, 191);

// YouTube: upload canvas is 2560x1440, but only the centred 1546x423 box is
// guaranteed visible across TV, desktop and mobile — everything else is cropped
// unpredictably per device, so all content stays inside that box.
makeWideCover('youtube-banner-2560x1440.png', 2560, 1440, 1546, 423, $headline, $sub, $chips);

echo "\nDone. Files in " . OUT . "\n";
