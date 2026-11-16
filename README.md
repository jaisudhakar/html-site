# Design2HTML

A customer uploads an image of a website design (a screenshot, mockup, Figma or Canva export, or a photo of a wireframe). AI (Claude) rebuilds it as one responsive HTML file. The customer previews it for free with a watermark, then pays **₹10 through Razorpay** (UPI, card or netbanking) to download the clean file.

It is plain PHP with no database, so it runs on any normal **cPanel** shared hosting.

## How it works

```
Customer → upload image → api/upload.php     (checks the file, limits each IP, saves it)
         → api/generate.php                  (sends the image to Claude and streams live progress)
         → preview.php                       (sandboxed, watermarked preview)
         → api/create-order.php              (Razorpay order for ₹10)
         → Razorpay Checkout                 (UPI / card / netbanking)
         → api/verify-payment.php            (checks the HMAC signature, then unlocks)
         → api/download.php                  (clean .html file, only after payment)
           api/razorpay-webhook.php          (optional: unlocks even if the tab was closed)
```

Tall full-page screenshots are cut into slices automatically, so the AI can read every section clearly.

## Requirements

- cPanel hosting with **PHP 8.1 or newer** (choose it in cPanel → *Select PHP Version* / *MultiPHP Manager*)
- PHP extensions `curl`, `fileinfo`, `json`, `mbstring`, `openssl`, `gd` (GD is recommended; it is used to slice and resize images)
- An **Anthropic API key** from https://platform.claude.com/settings/keys (add some credit to the account)
- A **Razorpay** account with API keys (Dashboard → Account & Settings → API Keys)

The `vendor/` folder (the Claude PHP SDK and Guzzle) is committed to this repo, so you do **not** need Composer or SSH on the server.

## Deploy on cPanel

1. **Upload the files**
   - Option A, Git: cPanel → *Git™ Version Control* → *Create* → clone this repo into a folder, for example `public_html` (or `public_html/design` for a sub-folder).
   - Option B, ZIP: download the repo as a ZIP → cPanel → *File Manager* → `public_html` → *Upload* → *Extract*. Make sure `index.php` ends up directly in the folder you want to serve.
2. **Create the config**: in File Manager, copy `config.sample.php` to `config.php` and edit it:
   - `anthropic_api_key`: your Claude key
   - `razorpay_key_id` and `razorpay_key_secret`: start with your **test** keys (`rzp_test_…`)
   - `site_name`, `price_paise` (1000 = ₹10), and the limits if you want to change them
3. **Permissions**: the `storage/` folder must be writable by PHP (755 is enough on most cPanel hosts; use 775 if uploads fail).
4. **Test**: open your domain, upload a design, wait for the preview, then click *Pay ₹10 & Download*. In test mode, use Razorpay's test UPI ID `success@razorpay` or a test card.
5. **Webhook (recommended)**: Razorpay Dashboard → *Webhooks* → *Add New Webhook*
   - URL: `https://YOURDOMAIN/api/razorpay-webhook.php`
   - Secret: any strong random text; put the same text in `razorpay_webhook_secret` in `config.php`
   - Events: `payment.captured` and `order.paid`
6. **Go live**: switch to your `rzp_live_…` keys in `config.php` once Razorpay has activated your account.

### Security notes

- `config.php`, `src/`, `vendor/` and `storage/` are blocked from the web by `.htaccess`. For extra safety you can move `storage/` outside `public_html` and set `storage_dir` in `config.php`.
- The download unlocks only after the Razorpay signature is verified on the server. The browser cannot fake it.
- The AI-generated page always runs in a sandbox (`Content-Security-Policy: sandbox`), so its scripts can never touch your site.
- The preview carries a watermark and blocks right-click and copy. A technical user could still copy the code with browser developer tools. For a ₹10 product this is usually acceptable.

## Costs: please read

Each generation calls the Claude API **before** the customer pays, and not every visitor will pay.

| `model` in config.php | Quality | Approx. API cost per page |
|---|---|---|
| `claude-opus-5-5` (default) | Best | ₹15–30 |
| `claude-sonnet-5-5` | Very good | about half of Opus |
| `claude-haiku-5-5` | Good for simple pages | under ₹1 |

With Opus, **one generation can cost more than the ₹10 you charge**. Options:

- switch `model` to `claude-sonnet-5-5` or `claude-haiku-5-5`, and/or set `effort` to `low`
- raise `price_paise`
- lower `generations_per_ip_per_day` (default: 5 free generations per IP per day)

You can see your real spend in the Claude Console under *Usage*.

## Troubleshooting

| Problem | Fix |
|---|---|
| “Setup needed: copy config.sample.php…” | Create `config.php` (step 2). |
| Upload fails for big images | Raise `upload_max_filesize` / `post_max_size` in cPanel → *MultiPHP INI Editor*. |
| Progress bar stalls but the page appears later | Your host buffers the live stream. That's fine: the page falls back to checking the status every few seconds. |
| “Building took too long and was stopped” | Your host kills long PHP requests. Raise `max_execution_time` in *MultiPHP INI Editor* (600 seconds), or use a faster model or a lower `effort`. |
| “The AI service is not configured correctly” | Check `anthropic_api_key` and the credit balance in the Claude Console. |
| “Could not start the payment” | Check the Razorpay keys. The PHP error log (cPanel → *Errors*) shows Razorpay's exact message. |

## Files

```
index.php               landing page and app UI
preview.php             watermarked, sandboxed preview
api/                    upload, generate (live stream), status, create-order, verify-payment, razorpay-webhook, download
src/                    SiteGenerator (Claude), Jobs (file storage), Razorpay, RateLimiter
assets/                 style.css, app.js, favicon.svg
storage/                uploads and generated pages (private)
config.sample.php       copy to config.php
```

To update dependencies on a machine with Composer: `composer update --no-dev`.
