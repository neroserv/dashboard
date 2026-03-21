<?php

namespace App\Mail;

use App\Models\Brand;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TransactionalTemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{subject: string, greeting: string, body: string, action_text: string|null}  $content
     */
    public function __construct(
        public array $content,
        public ?string $actionUrl = null,
        public bool $isTest = false,
        public ?Brand $brand = null
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->content['subject'];
        if ($this->isTest) {
            $subject = '[Test] '.$subject;
        }

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $html = $this->getCompiledHtml();
        $html = $this->replacePlaceholders($html);

        return new Content(
            htmlString: $html,
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Render the email HTML (e.g. for preview in admin). Same output as sent mail.
     *
     * @param  array{subject: string, greeting: string, body: string, action_text: string|null}  $content
     */
    public static function renderHtml(array $content, ?string $actionUrl, ?Brand $brand = null): string
    {
        $mailable = new self($content, $actionUrl, false, $brand);

        return $mailable->replacePlaceholders($mailable->getCompiledHtml());
    }

    private function getCompiledHtml(): string
    {
        $path = config('maizzle.compiled_path');

        if (! $path || ! is_file($path)) {
            return $this->fallbackHtml();
        }

        $html = file_get_contents($path);
        if ($html === false) {
            return $this->fallbackHtml();
        }

        return $html;
    }

    private function replacePlaceholders(string $html): string
    {
        $greeting = '<p style="margin: 0 0 16px 0;">'.e($this->content['greeting']).'</p>';
        $bodyHtml = $this->bodyToHtml($this->content['body']);
        $actionButton = $this->buildActionButton();
        $header = $this->getGlobalHeader();
        $footer = $this->getGlobalFooter();

        return str_replace(
            ['__HEADER__', '__GREETING__', '__BODY__', '__ACTION_BUTTON__', '__FOOTER__'],
            [$header, $greeting, $bodyHtml, $actionButton, $footer],
            $html
        );
    }

    private function getGlobalHeader(): string
    {
        if ($this->brand !== null) {
            $custom = $this->brand->mail_header;
            if ($custom !== null && trim($custom) !== '') {
                return $this->constrainEmailImagesForOutlook(trim($custom));
            }

            $logoImg = $this->buildBrandLogoImg($this->brand);
            if ($logoImg !== '') {
                return $logoImg;
            }

            $color = $this->brandPrimaryColor();

            return '<span style="font-size: 18px; font-weight: 600; color: '.$color.';">'.e($this->brand->name).'</span>';
        }

        $custom = config('maizzle.header');
        if ($custom !== null && trim((string) $custom) !== '') {
            return $this->constrainEmailImagesForOutlook(trim((string) $custom));
        }

        $appName = e(config('app.name'));
        $color = $this->brandPrimaryColor();

        return '<span style="font-size: 18px; font-weight: 600; color: '.$color.';">'.$appName.'</span>';
    }

    /**
     * Build an img tag for the brand logo for use in the email header.
     * Returns empty string if brand has no logo_url.
     * SVG URLs are converted to .png for the email, because many clients (e.g. Gmail) do not display SVG images.
     */
    private function buildBrandLogoImg(Brand $brand): string
    {
        $logoUrl = $brand->logo_url;
        if ($logoUrl === null || trim($logoUrl) === '') {
            return '';
        }

        $logoUrl = trim($logoUrl);
        if (! Str::startsWith($logoUrl, ['http://', 'https://'])) {
            $path = ltrim($logoUrl, '/');
            if (Storage::disk('public')->exists($path)) {
                $logoUrl = rtrim(config('app.url'), '/').'/storage/'.$path;
            } else {
                return '';
            }
        }

        if (Str::endsWith(strtolower($logoUrl), '.svg')) {
            $logoUrl = Str::beforeLast($logoUrl, '.svg').'.png';
        }

        $alt = e($brand->name);

        $imgStyle = 'display:block;max-width:200px;height:auto;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;line-height:100%;';

        return '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="200" style="max-width:200px;width:200px;"><tr><td style="line-height:0;font-size:0;mso-line-height-rule:exactly;"><img src="'.e($logoUrl).'" width="200" alt="'.$alt.'" style="'.$imgStyle.'" /></td></tr></table>';
    }

    /**
     * Outlook ignores CSS max-width on images; custom mail headers often use full-size assets.
     * Force HTML width and inline styles so headers render at a sane size in Outlook/Gmail.
     */
    private function constrainEmailImagesForOutlook(string $html): string
    {
        if (stripos($html, '<img') === false) {
            return $html;
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $wrapped = '<?xml encoding="UTF-8"><div id="email-img-root">'.$html.'</div>';
        $loaded = @$dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if (! $loaded) {
            libxml_clear_errors();

            return $this->constrainEmailImagesForOutlookRegex($html);
        }

        $root = $dom->getElementById('email-img-root');
        if ($root === null) {
            libxml_clear_errors();

            return $this->constrainEmailImagesForOutlookRegex($html);
        }

        foreach ($root->getElementsByTagName('img') as $img) {
            if (! $img instanceof \DOMElement) {
                continue;
            }
            $img->setAttribute('width', '200');
            $img->removeAttribute('height');
            $style = trim($img->getAttribute('style'), '; ');
            $extra = 'max-width:200px;height:auto;display:block;border:0;outline:none;line-height:100%;';
            $img->setAttribute('style', trim($style !== '' ? $style.';'.$extra : $extra, '; '));
        }

        $fragment = '';
        foreach ($root->childNodes as $child) {
            $fragment .= $dom->saveHTML($child);
        }
        libxml_clear_errors();

        return $fragment !== '' ? $fragment : $html;
    }

    /**
     * Fallback when DOM parsing fails on malformed header HTML fragments.
     */
    private function constrainEmailImagesForOutlookRegex(string $html): string
    {
        $result = preg_replace_callback('/<img\b[^>]*>/i', function (array $m): string {
            $tag = $m[0];
            $tag = preg_replace('/\sheight\s*=\s*["\']?[^"\'\s>]+["\']?/i', '', $tag) ?? $tag;
            if (preg_match('/\bwidth\s*=\s*["\']?(\d+)/i', $tag, $wm)) {
                if ((int) $wm[1] > 200) {
                    $tag = preg_replace('/\bwidth\s*=\s*["\']?\d+["\']?/i', 'width="200"', $tag) ?? $tag;
                }
            } elseif (! preg_match('/\bwidth\s*=/i', $tag)) {
                $tag = preg_replace('/<img/i', '<img width="200"', $tag, 1) ?? $tag;
            }
            if (preg_match('/style\s*=\s*(["\'])(.*?)\1/s', $tag, $sm)) {
                $q = $sm[1];
                $st = $sm[2];
                if (! preg_match('/max-width\s*:/i', $st)) {
                    $st .= ';max-width:200px;height:auto;display:block;border:0';
                }
                $tag = preg_replace('/style\s*=\s*["\'].*?["\']/s', 'style='.$q.$st.$q, $tag, 1) ?? $tag;
            } else {
                $tag = preg_replace('/<img/i', '<img style="max-width:200px;height:auto;display:block;border:0"', $tag, 1) ?? $tag;
            }

            return $tag;
        }, $html);

        return is_string($result) ? $result : $html;
    }

    private function getGlobalFooter(): string
    {
        if ($this->brand !== null) {
            $custom = $this->brand->mail_footer;
            if ($custom !== null && trim($custom) !== '') {
                return trim($custom);
            }

            return 'Diese E-Mail wurde von '.e($this->brand->name).' gesendet.';
        }

        $custom = config('maizzle.footer');
        if ($custom !== null && trim((string) $custom) !== '') {
            return trim((string) $custom);
        }

        return 'Diese E-Mail wurde von '.e(config('app.name')).' gesendet.';
    }

    private function bodyToHtml(string $body): string
    {
        $body = trim($body);
        if ($body === '') {
            return '<p style="margin: 0;">&nbsp;</p>';
        }
        $bodyWithParagraphs = preg_replace('/\n/', "\n\n", $body);
        $html = Str::markdown($bodyWithParagraphs);

        return $html;
    }

    private function buildActionButton(): string
    {
        if (empty($this->content['action_text']) || $this->actionUrl === null) {
            return '';
        }

        $url = e($this->actionUrl);
        $text = e($this->content['action_text']);
        $primary = $this->brandPrimaryColor();

        return sprintf(
            '<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin:0 auto;"><tr><td align="center" bgcolor="%1$s" style="background-color:%1$s;border-radius:8px;mso-line-height-rule:exactly;"><a href="%2$s" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:12px 24px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,\'Helvetica Neue\',Arial,sans-serif;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;line-height:1.25;">%3$s</a></td></tr></table>',
            e($primary),
            $url,
            $text
        );
    }

    private function brandPrimaryColor(): string
    {
        $colors = $this->brand?->theme_colors;
        if (is_array($colors) && ! empty($colors['primary'])) {
            return $this->sanitizeCssColor($colors['primary']);
        }

        return '#059669';
    }

    private function sanitizeCssColor(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', $value)) {
            return $value;
        }
        if (preg_match('/^rgb\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\)$/', $value)) {
            return $value;
        }

        return '#059669';
    }

    private function fallbackHtml(): string
    {
        $header = $this->getGlobalHeader();
        $greeting = '<p>'.e($this->content['greeting']).'</p>';
        $bodyHtml = $this->bodyToHtml($this->content['body']);
        $actionButton = $this->buildActionButton();
        $footer = $this->getGlobalFooter();

        return '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div style="padding:16px;border-bottom:1px solid #eee">'.$header.'</div>'.$greeting.$bodyHtml.$actionButton.'<div style="padding:16px;font-size:12px;color:#6b7280">'.$footer.'</div></body></html>';
    }
}
