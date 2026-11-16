<?php
declare(strict_types=1);

use Anthropic\Beta\Messages\BetaRawContentBlockDeltaEvent;
use Anthropic\Beta\Messages\BetaRawMessageDeltaEvent;
use Anthropic\Beta\Messages\BetaTextDelta;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\OverloadedException;
use Anthropic\Core\Exceptions\RateLimitException;

/**
 * Turns an uploaded website design image into a single self-contained HTML file using Claude.
 */
final class SiteGenerator
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are an expert front-end developer who converts website design images (screenshots, mockups, Figma exports, hand-drawn wireframes) into production-quality websites.

Build ONE complete, self-contained HTML file that recreates the design as faithfully as possible.

Fidelity
- Match the layout, section order, spacing, alignment, colors, font sizes, font weights, border radii, shadows and visual hierarchy you see.
- Reproduce all visible text exactly. Where text is unreadable or the design uses lorem ipsum, write short, realistic copy that fits the context.
- If the image is several slices of one tall page, they are in top-to-bottom order: build one continuous page.
- If the image is a rough wireframe, keep its structure and give it a clean, modern, professional finish.

Technical rules
- Output a full document starting with <!DOCTYPE html>, with <meta charset="utf-8">, a responsive viewport meta tag and a meaningful <title>.
- Put all CSS in one <style> tag in <head> and any JavaScript in one <script> tag before </body>. No CSS frameworks, no build step, no external JS libraries.
- Google Fonts <link> tags are allowed; pick the closest match to the fonts in the design, with sensible fallbacks.
- Use CSS custom properties for the color palette, modern layout (flexbox/grid), and semantic HTML5 (header, nav, main, section, footer).
- Fully responsive: looks right on desktop and collapses gracefully on tablets and phones (a working hamburger menu if the design has a nav bar).
- Icons and logos: inline SVG. Illustrations and decorative shapes: inline SVG or CSS.
- Photos: use https://picsum.photos/seed/<one-descriptive-word>/<width>/<height> with descriptive alt text, sized to match the design.
- Buttons and links get hover and focus states; forms get proper labels. Use href="#" or in-page anchors for links.
- Accessible: sufficient contrast, alt text, aria-labels on icon-only buttons.

Response format
Reply with the HTML file only: begin with <!DOCTYPE html> and end with </html>. No explanations and no markdown code fences.
PROMPT;

    private const MAX_SLICE_WIDTH = 1280;
    private const MAX_SLICE_HEIGHT = 1800;
    private const MAX_SLICES = 8;

    /**
     * Runs the generation. $onProgress(int $charsSoFar) is called as HTML streams in.
     * Returns the final HTML. Throws RuntimeException with a customer-friendly message on failure.
     */
    public static function generate(string $imagePath, string $mime, string $notes, callable $onProgress): string
    {
        $client = new Client(
            apiKey: (string) config('anthropic_api_key'),
            requestOptions: ['timeout' => 900.0, 'maxRetries' => 2],
        );

        $content = [];
        $slices = self::prepareImages($imagePath, $mime);
        foreach ($slices as $i => $slice) {
            if (count($slices) > 1) {
                $content[] = ['type' => 'text', 'text' => sprintf('Design slice %d of %d (top to bottom):', $i + 1, count($slices))];
            }
            $content[] = [
                'type' => 'image',
                'source' => ['type' => 'base64', 'mediaType' => $slice['mime'], 'data' => $slice['data']],
            ];
        }

        $instruction = 'Convert this website design into a single self-contained HTML file following your instructions.';
        $notes = trim($notes);
        if ($notes !== '') {
            // The customer's notes are data about their site, wrapped so they can't override the output rules.
            $instruction .= "\n\nThe customer added these notes about their website (apply them where sensible):\n<customer_notes>\n"
                . $notes . "\n</customer_notes>";
        }
        $content[] = ['type' => 'text', 'text' => $instruction];

        $html = '';
        $stopReason = null;

        try {
            $stream = $client->beta->messages->createStream(
                model: (string) config('model'),
                maxTokens: (int) (config('max_tokens') ?: 64000),
                system: self::SYSTEM_PROMPT,
                messages: [['role' => 'user', 'content' => $content]],
                outputConfig: ['effort' => (string) (config('effort') ?: 'medium')],
                // If the model declines for a policy reason, let the API retry on its default fallback model.
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );

            $lastReport = 0;
            foreach ($stream as $event) {
                if ($event instanceof BetaRawContentBlockDeltaEvent && $event->delta instanceof BetaTextDelta) {
                    $html .= $event->delta->text;
                    $len = strlen($html);
                    if ($len - $lastReport >= 1500) {
                        $lastReport = $len;
                        $onProgress($len);
                    }
                } elseif ($event instanceof BetaRawMessageDeltaEvent && $event->delta->stopReason !== null) {
                    $stopReason = $event->delta->stopReason;
                }
            }
        } catch (AuthenticationException $e) {
            error_log('[design2html] Anthropic auth error: ' . $e->getMessage());
            throw new RuntimeException('The AI service is not configured correctly. Please contact the site owner.');
        } catch (BadRequestException $e) {
            error_log('[design2html] Anthropic bad request: ' . $e->getMessage());
            throw new RuntimeException('The AI could not read this image. Try a PNG or JPG screenshot.');
        } catch (RateLimitException | OverloadedException $e) {
            error_log('[design2html] Anthropic busy: ' . $e->getMessage());
            throw new RuntimeException('The AI is busy right now. Please try again in a minute.');
        } catch (APIStatusException $e) {
            error_log('[design2html] Anthropic API error: ' . $e->getMessage());
            throw new RuntimeException('The AI service returned an error. Please try again.');
        } catch (APIConnectionException $e) {
            error_log('[design2html] Anthropic connection error: ' . $e->getMessage());
            throw new RuntimeException('Could not reach the AI service. Please try again.');
        }

        if ($stopReason === 'refusal') {
            throw new RuntimeException('The AI declined to process this image. Please try a different design.');
        }

        $html = self::extractHtml($html);
        if ($html === null) {
            $suffix = $stopReason === 'max_tokens' ? ' (the page was too long)' : '';
            throw new RuntimeException('The AI did not return a complete web page' . $suffix . '. Please try again.');
        }
        return $html;
    }

    /** Pulls the HTML document out of the model's reply, tolerating stray code fences or text. */
    public static function extractHtml(string $text): ?string
    {
        $start = stripos($text, '<!DOCTYPE html');
        if ($start === false) {
            $start = stripos($text, '<html');
        }
        $end = strripos($text, '</html>');
        if ($start === false || $end === false || $end < $start) {
            return null;
        }
        return substr($text, $start, $end - $start + strlen('</html>')) . "\n";
    }

    /** Pulls the <title> out of the generated page. */
    public static function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            return $title === '' ? null : mb_substr($title, 0, 120);
        }
        return null;
    }

    /**
     * Resizes the design and cuts tall full-page screenshots into slices, so Claude sees
     * every part at a readable resolution. Falls back to the original file if GD is missing.
     *
     * @return list<array{mime: string, data: string}>
     */
    private static function prepareImages(string $path, string $mime): array
    {
        $raw = (string) file_get_contents($path);
        $canProcess = extension_loaded('gd') && $mime !== 'image/gif';
        $src = $canProcess ? @imagecreatefromstring($raw) : false;

        if ($src === false) {
            if (strlen($raw) > 5 * 1024 * 1024) {
                throw new RuntimeException('This image is too large. Please upload one under 5 MB.');
            }
            return [['mime' => $mime, 'data' => base64_encode($raw)]];
        }

        $w = imagesx($src);
        $h = imagesy($src);

        // Scale to the target width, and further if the page would need more than MAX_SLICES slices.
        $scale = min(1.0, self::MAX_SLICE_WIDTH / $w);
        if ($h * $scale > self::MAX_SLICE_HEIGHT * self::MAX_SLICES) {
            $scale = (self::MAX_SLICE_HEIGHT * self::MAX_SLICES) / $h;
        }
        $newW = max(1, (int) round($w * $scale));
        $newH = max(1, (int) round($h * $scale));

        $count = (int) ceil($newH / self::MAX_SLICE_HEIGHT);
        $sliceH = (int) ceil($newH / $count);
        $srcSliceH = $sliceH / $scale;

        $slices = [];
        for ($i = 0; $i < $count; $i++) {
            $destH = min($sliceH, $newH - $i * $sliceH);
            $dst = imagecreatetruecolor($newW, $destH);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // flatten transparency onto white
            imagecopyresampled(
                $dst, $src,
                0, 0, 0, (int) round($i * $srcSliceH),
                $newW, $destH, $w, (int) round($destH / $scale)
            );
            ob_start();
            imagejpeg($dst, null, 88);
            $slices[] = ['mime' => 'image/jpeg', 'data' => base64_encode((string) ob_get_clean())];
            imagedestroy($dst);
        }
        imagedestroy($src);
        return $slices;
    }
}
