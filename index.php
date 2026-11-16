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
<title><?= $siteName ?> · Turn any design into an HTML website</title>
<meta name="description" content="Upload a screenshot or mockup of a website design. AI builds it as a clean, responsive HTML page you can preview for free and download for <?= $price ?>.">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=1">
</head>
<body>

<header class="topbar">
  <div class="container topbar-inner">
    <a class="brand" href="./">
      <img src="assets/favicon.svg" alt="" width="28" height="28">
      <span><?= $siteName ?></span>
    </a>
    <nav class="topnav">
      <a href="#how">How it works</a>
      <a href="#faq">FAQ</a>
      <a href="#app" class="btn btn-sm btn-primary">Start now</a>
    </nav>
  </div>
</header>

<main>
  <section class="hero">
    <div class="container hero-inner">
      <span class="pill">AI design-to-code · Only <?= $price ?> per website</span>
      <h1>Turn any website design into a<br><span class="grad">ready-to-use HTML website</span></h1>
      <p class="lead">Upload a screenshot, mockup or Figma export. Our AI rebuilds it as a clean, responsive HTML page in about two minutes. Preview it free, pay <?= $price ?> only if you love it.</p>
    </div>
  </section>

  <section id="app" class="container app-section">
    <div class="card app-card">

      <!-- Step 1: upload -->
      <div class="view" id="view-upload">
        <form id="upload-form" novalidate>
          <label class="dropzone" id="dropzone" for="file-input">
            <input type="file" id="file-input" name="design" accept="image/png,image/jpeg,image/webp,image/gif" hidden>
            <div class="dz-empty" id="dz-empty">
              <div class="dz-icon" aria-hidden="true">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4"/><path d="m6 10 6-6 6 6"/><path d="M4 20h16"/></svg>
              </div>
              <strong>Drop your design here, or click to browse</strong>
              <span class="muted">PNG, JPG, WEBP or GIF · up to <?= $maxMb ?> MB · you can also paste a screenshot (Ctrl+V)</span>
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
            <span class="field-label">Notes for the AI <span class="muted">(optional)</span></span>
            <textarea id="notes" name="notes" rows="2" maxlength="1500" placeholder="e.g. Business name is “Sri Balaji Traders”, use phone 98xxxxxx10, make the buttons green"></textarea>
          </label>

          <div class="form-error" id="upload-error" role="alert" hidden></div>

          <button type="submit" class="btn btn-primary btn-lg btn-block" id="generate-btn" disabled>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 3 1.9 5.8L20 10l-5 3.6L16.5 20 12 16.4 7.5 20 9 13.6 4 10l6.1-1.2z"/></svg>
            Generate my website
          </button>
          <p class="fine">Free preview · Pay <?= $price ?> via UPI, card or netbanking only to download</p>
        </form>
      </div>

      <!-- Step 2: working -->
      <div class="view" id="view-working" hidden>
        <div class="working">
          <div class="orb" aria-hidden="true"><span></span><span></span><span></span></div>
          <h2 id="work-title">Uploading your design…</h2>
          <p class="muted" id="work-sub">This usually takes 1–3 minutes. You can keep this tab open in the background.</p>
          <div class="progress" aria-hidden="true"><div class="progress-bar" id="progress-bar"></div></div>
          <ol class="stages" id="stages">
            <li data-stage="upload">Upload</li>
            <li data-stage="read">Read design</li>
            <li data-stage="code">Write HTML &amp; CSS</li>
            <li data-stage="done">Ready</li>
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
            <button class="btn btn-primary" id="retry-btn">Try again</button>
            <button class="btn btn-ghost" data-action="new">Upload another design</button>
          </div>
        </div>
      </div>

      <!-- Step 3: result -->
      <div class="view" id="view-result" hidden>
        <div class="result-head">
          <div>
            <h2 id="result-title">Your website is ready</h2>
            <p class="muted" id="result-sub">Check the preview below. The watermark is removed in the file you download.</p>
          </div>
          <div class="devices" role="group" aria-label="Preview size">
            <button class="dev active" data-w="100%" title="Desktop" aria-label="Desktop"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg></button>
            <button class="dev" data-w="820px" title="Tablet" aria-label="Tablet"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M11 18h2"/></svg></button>
            <button class="dev" data-w="390px" title="Mobile" aria-label="Mobile"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg></button>
            <a class="dev" id="open-full" target="_blank" rel="noopener" title="Open full screen" aria-label="Open full screen"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg></a>
          </div>
        </div>

        <div class="browser">
          <div class="browser-bar"><i></i><i></i><i></i><span id="browser-url">preview</span></div>
          <div class="frame-wrap"><iframe id="preview-frame" title="Website preview" sandbox="allow-scripts allow-popups allow-forms" loading="lazy"></iframe></div>
        </div>

        <div class="paybox" id="paybox">
          <div class="pay-info">
            <strong>Download the full HTML file</strong>
            <span class="muted">One file with all the CSS and JS inside · no watermark · use it anywhere</span>
          </div>
          <button class="btn btn-primary btn-lg" id="pay-btn">Pay <?= $price ?> &amp; Download</button>
        </div>

        <div class="paybox paid" id="paidbox" hidden>
          <div class="pay-info">
            <strong>✓ Payment received, thank you!</strong>
            <span class="muted">Your download should start automatically. You can download again any time from this page.</span>
          </div>
          <a class="btn btn-success btn-lg" id="download-btn" href="#">Download HTML</a>
        </div>

        <div class="form-error" id="pay-error" role="alert" hidden></div>
        <div class="row-center"><button class="link-btn" data-action="new">← Convert another design</button></div>
      </div>
    </div>

    <div class="recent" id="recent" hidden>
      <h3>Your recent websites</h3>
      <ul id="recent-list"></ul>
    </div>
  </section>

  <section id="how" class="container section">
    <h2 class="section-title">How it works</h2>
    <div class="steps">
      <div class="step"><span class="num">1</span><h3>Upload your design</h3><p>A screenshot of a site you like, a Figma or Canva export, or even a hand-drawn wireframe photo.</p></div>
      <div class="step"><span class="num">2</span><h3>AI builds the page</h3><p>Layout, colors, fonts, text and icons are rebuilt as clean, responsive HTML and CSS.</p></div>
      <div class="step"><span class="num">3</span><h3>Preview for free</h3><p>See it on desktop, tablet and mobile before paying anything.</p></div>
      <div class="step"><span class="num">4</span><h3>Pay <?= $price ?>, download</h3><p>Pay securely with UPI, card or netbanking via Razorpay and get your .html file instantly.</p></div>
    </div>
  </section>

  <section id="faq" class="container section faq">
    <h2 class="section-title">Questions</h2>
    <details><summary>What do I get?</summary><p>A single .html file with all CSS and JavaScript inside. Open it in any browser, edit it in any text editor, or upload it to any hosting (cPanel, Netlify, GitHub Pages…).</p></details>
    <details><summary>Will it look exactly like my design?</summary><p>Very close. The AI matches the layout, colors, fonts and text it can see. Photos are replaced with placeholder images that you can swap for your own.</p></details>
    <details><summary>Do I pay if I don't like the result?</summary><p>No. Generating and previewing is free. You only pay <?= $price ?> when you choose to download.</p></details>
    <details><summary>Which payment methods are supported?</summary><p>UPI (GPay, PhonePe, Paytm), debit and credit cards, netbanking and wallets, processed securely by Razorpay.</p></details>
    <details><summary>Paid but the download didn't start?</summary><p>Open this site again on the same browser: your website is listed under “Your recent websites” with a download button. If anything is wrong, contact us with your Razorpay payment ID.</p></details>
  </section>
</main>

<footer class="footer">
  <div class="container">© <?= date('Y') ?> <?= $siteName ?> · Payments secured by Razorpay</div>
</footer>

<script>window.APP_CONFIG = <?= json_encode($appConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script src="https://checkout.razorpay.com/v1/checkout.js" defer></script>
<script src="assets/app.js?v=1" defer></script>
</body>
</html>
