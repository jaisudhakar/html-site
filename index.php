<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

$siteName = htmlspecialchars((string) config('site_name'), ENT_QUOTES);
$price = htmlspecialchars(format_price(), ENT_QUOTES);
$maxMb = (int) config('max_upload_mb');
$appConfig = [
    'price' => format_price(),
    'siteName' => (string) config('site_name'),
    'maxUploadMb' => $maxMb,
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0b0a08">
<title><?= $siteName ?> · Your design, crafted into a website</title>
<meta name="description" content="Upload a screenshot or mockup of a website design. AI crafts it into a refined, responsive HTML website you can preview for free and own for <?= $price ?>.">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500;1,600&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=2">
</head>
<body>

<header class="topbar">
  <div class="container topbar-inner">
    <a class="brand" href="./">
      <img src="assets/favicon.svg" alt="" width="30" height="30">
      <span><?= $siteName ?></span>
    </a>
    <nav class="topnav">
      <a href="#how">The Process</a>
      <a href="#faq">Enquiries</a>
      <a href="#app" class="btn btn-sm btn-outline">Begin</a>
    </nav>
  </div>
</header>

<main>
  <section class="hero">
    <div class="container hero-inner">
      <p class="eyebrow"><span class="rule"></span>AI website atelier<span class="rule"></span></p>
      <h1>Your design,<br><em class="gold-text">masterfully crafted</em><br>into a website.</h1>
      <p class="lead">Share a screenshot, mockup or sketch. Our AI renders it as a refined, fully responsive HTML website in minutes. View it at no cost; own it for just <?= $price ?>.</p>
      <ul class="hallmarks" aria-label="What you get">
        <li>Pixel-faithful layout</li>
        <li>Responsive on every device</li>
        <li>One elegant HTML file</li>
      </ul>
    </div>
  </section>

  <section id="app" class="container app-section">
    <div class="card app-card">
      <span class="corner tl" aria-hidden="true"></span><span class="corner tr" aria-hidden="true"></span>
      <span class="corner bl" aria-hidden="true"></span><span class="corner br" aria-hidden="true"></span>

      <!-- Step 1: upload -->
      <div class="view" id="view-upload">
        <div class="card-heading">
          <p class="kicker">Commission</p>
          <h2>Present your design</h2>
        </div>
        <form id="upload-form" novalidate>
          <label class="dropzone" id="dropzone" for="file-input">
            <input type="file" id="file-input" name="design" accept="image/png,image/jpeg,image/webp,image/gif" hidden>
            <div class="dz-empty" id="dz-empty">
              <div class="dz-icon" aria-hidden="true">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4"/><path d="m6.5 9.5 5.5-5.5 5.5 5.5"/><path d="M4 20h16"/></svg>
              </div>
              <strong>Drop your design here, or browse</strong>
              <span class="muted">PNG, JPG, WEBP or GIF &middot; up to <?= $maxMb ?> MB &middot; or paste a screenshot (Ctrl+V)</span>
            </div>
            <div class="dz-preview" id="dz-preview" hidden>
              <img id="dz-img" alt="Your design">
              <div class="dz-meta">
                <span id="dz-name"></span>
                <button type="button" class="link-btn" id="dz-change">Change image</button>
              </div>
            </div>
          </label>

          <label class="field">
            <span class="field-label">Notes for the artisan <span class="muted">(optional)</span></span>
            <textarea id="notes" name="notes" rows="2" maxlength="1500" placeholder="e.g. Business name is “Sri Balaji Jewellers”, phone 98xxxxxx10, use deep maroon and gold"></textarea>
          </label>

          <div class="form-error" id="upload-error" role="alert" hidden></div>

          <button type="submit" class="btn btn-gold btn-lg btn-block" id="generate-btn" disabled>
            Craft my website
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </button>
          <p class="fine">Complimentary preview &middot; <?= $price ?> to download &middot; UPI, cards &amp; netbanking</p>
        </form>
      </div>

      <!-- Step 2: working -->
      <div class="view" id="view-working" hidden>
        <div class="working">
          <div class="seal" aria-hidden="true"><span></span><span></span><i></i></div>
          <h2 id="work-title">Uploading your design…</h2>
          <p class="muted" id="work-sub">This usually takes 1–3 minutes. You may keep this tab open in the background.</p>
          <div class="progress" aria-hidden="true"><div class="progress-bar" id="progress-bar"></div></div>
          <ol class="stages" id="stages">
            <li data-stage="upload">Received</li>
            <li data-stage="read">Studying</li>
            <li data-stage="code">Crafting</li>
            <li data-stage="done">Finished</li>
          </ol>
        </div>
      </div>

      <!-- Error -->
      <div class="view" id="view-error" hidden>
        <div class="working">
          <div class="err-icon" aria-hidden="true">!</div>
          <h2>Something went wrong</h2>
          <p class="muted" id="error-text"></p>
          <div class="row-center">
            <button class="btn btn-gold" id="retry-btn">Try again</button>
            <button class="btn btn-outline" data-action="new">Upload another design</button>
          </div>
        </div>
      </div>

      <!-- Step 3: result -->
      <div class="view" id="view-result" hidden>
        <div class="result-head">
          <div>
            <p class="kicker">Your website</p>
            <h2 id="result-title">Your website is ready</h2>
            <p class="muted" id="result-sub">Take a look below. The watermark is removed in the file you download.</p>
          </div>
          <div class="devices" role="group" aria-label="Preview size">
            <button class="dev active" data-w="100%" title="Desktop" aria-label="Desktop"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="4" width="20" height="13" rx="1.5"/><path d="M8 21h8M12 17v4"/></svg></button>
            <button class="dev" data-w="820px" title="Tablet" aria-label="Tablet"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="5" y="2" width="14" height="20" rx="1.5"/><path d="M11 18h2"/></svg></button>
            <button class="dev" data-w="390px" title="Mobile" aria-label="Mobile"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="7" y="2" width="10" height="20" rx="1.5"/><path d="M11 18h2"/></svg></button>
            <a class="dev" id="open-full" target="_blank" rel="noopener" title="Open full screen" aria-label="Open full screen"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg></a>
          </div>
        </div>

        <div class="browser">
          <div class="browser-bar"><i></i><i></i><i></i><span id="browser-url">preview</span></div>
          <div class="frame-wrap"><iframe id="preview-frame" title="Website preview" sandbox="allow-scripts allow-popups allow-forms" loading="lazy"></iframe></div>
        </div>

        <div class="paybox" id="paybox">
          <div class="pay-info">
            <strong>Own the complete HTML file</strong>
            <span class="muted">One file with all styles and scripts inside &middot; no watermark &middot; yours to use anywhere</span>
          </div>
          <button class="btn btn-gold btn-lg" id="pay-btn">Pay <?= $price ?> &amp; Download</button>
        </div>

        <div class="paybox paid" id="paidbox" hidden>
          <div class="pay-info">
            <strong>Payment received, thank you.</strong>
            <span class="muted">Your download should begin automatically. You may download again at any time from this page.</span>
          </div>
          <a class="btn btn-gold btn-lg" id="download-btn" href="#">Download HTML</a>
        </div>

        <div class="form-error" id="pay-error" role="alert" hidden></div>
        <div class="row-center"><button class="link-btn" data-action="new">&larr; Craft another design</button></div>
      </div>
    </div>

    <div class="recent" id="recent" hidden>
      <h3>Your recent websites</h3>
      <ul id="recent-list"></ul>
    </div>
  </section>

  <section id="how" class="container section">
    <p class="kicker center">The process</p>
    <h2 class="section-title">Four quiet steps to a <em class="gold-text">finished website</em></h2>
    <div class="steps">
      <div class="step"><span class="num">I</span><h3>Present your design</h3><p>A screenshot of a site you admire, a Figma or Canva export, or a photograph of a sketch.</p></div>
      <div class="step"><span class="num">II</span><h3>AI crafts the page</h3><p>Layout, palette, typography, copy and icons are rebuilt as clean, responsive HTML and CSS.</p></div>
      <div class="step"><span class="num">III</span><h3>View it at no cost</h3><p>Review it on desktop, tablet and mobile before you decide.</p></div>
      <div class="step"><span class="num">IV</span><h3>Own it for <?= $price ?></h3><p>Pay securely with UPI, card or netbanking via Razorpay and receive your file instantly.</p></div>
    </div>
  </section>

  <section id="faq" class="container section faq">
    <p class="kicker center">Enquiries</p>
    <h2 class="section-title">Questions, answered</h2>
    <details><summary>What do I receive?</summary><p>A single .html file with all styles and scripts inside. Open it in any browser, refine it in any text editor, or publish it on any hosting: cPanel, Netlify, GitHub Pages and more.</p></details>
    <details><summary>How closely will it match my design?</summary><p>Very closely. The AI follows the layout, colours, fonts and wording it can see. Photographs are replaced with placeholder images that you can swap for your own.</p></details>
    <details><summary>Do I pay if I'm not pleased with the result?</summary><p>No. Crafting and previewing is complimentary. You pay <?= $price ?> only when you choose to download.</p></details>
    <details><summary>Which payment methods are accepted?</summary><p>UPI (GPay, PhonePe, Paytm), debit and credit cards, netbanking and wallets, processed securely by Razorpay.</p></details>
    <details><summary>I paid but the download didn't begin.</summary><p>Return to this site in the same browser: your website is listed under “Your recent websites” with a download link. If anything is amiss, contact us with your Razorpay payment ID.</p></details>
  </section>
</main>

<footer class="footer">
  <div class="container">
    <div class="footer-mark"><span class="rule"></span><img src="assets/favicon.svg" alt="" width="26" height="26"><span class="rule"></span></div>
    <p>&copy; <?= date('Y') ?> <?= $siteName ?> &middot; Payments secured by Razorpay</p>
  </div>
</footer>

<script>window.APP_CONFIG = <?= json_encode($appConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script src="https://checkout.razorpay.com/v1/checkout.js" defer></script>
<script src="assets/app.js?v=2" defer></script>
</body>
</html>
