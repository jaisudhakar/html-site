<?php
/**
 * Copy this file to config.php and fill in your keys.
 * config.php is private: it is blocked from the web by .htaccess and is not committed to git.
 */
return [
    // Brand shown on the site
    'site_name' => 'Design2HTML',

    // ---- Claude (Anthropic) ----
    // Create a key at https://platform.claude.com/settings/keys
    'anthropic_api_key' => 'sk-ant-xxxxxxxxxxxxxxxx',
    // Model that turns the design into HTML.
    //   claude-opus-5-5   best quality (default), roughly ₹15–30 of API cost per page
    //   claude-sonnet-5-5 very good, roughly half the cost of Opus
    //   claude-haiku-5-5  fastest and cheapest (well under ₹1 per page), simpler results
    'model' => 'claude-opus-5-5',
    // How hard the model thinks: low | medium | high. Higher = better fidelity, slower, costlier.
    'effort' => 'medium',
    // Upper limit of generated tokens per page
    'max_tokens' => 64000,

    // ---- Razorpay ----
    // Dashboard → Account & Settings → API Keys. Use rzp_test_ keys first, then rzp_live_ keys.
    'razorpay_key_id' => 'rzp_test_xxxxxxxxxxxx',
    'razorpay_key_secret' => 'xxxxxxxxxxxxxxxxxxxxxxxx',
    // Dashboard → Webhooks → add https://YOURDOMAIN/api/razorpay-webhook.php
    // with events "payment.captured" and "order.paid", and paste the same secret here.
    // Optional, but it unlocks the download even if the customer closes the tab right after paying.
    'razorpay_webhook_secret' => '',

    // Price in paise (1000 = ₹10)
    'price_paise' => 1000,

    // ---- Limits ----
    'max_upload_mb' => 8,
    // Free generations allowed per visitor IP per 24 hours (generation costs you API money)
    'generations_per_ip_per_day' => 5,
    // Delete jobs (images + generated pages) older than this many days
    'keep_jobs_days' => 30,

    // Folder for uploaded images and generated pages. Must be writable.
    // Default is ./storage (protected by .htaccess). For extra safety, point it outside public_html.
    'storage_dir' => __DIR__ . '/storage',
];
