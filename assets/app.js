(function () {
  'use strict';

  var CFG = window.APP_CONFIG || {};
  var EXPECTED_CHARS = 28000; // typical size of a generated page, used for the progress bar
  var RECENT_KEY = 'd2h_recent';

  var $ = function (id) { return document.getElementById(id); };
  var views = ['upload', 'working', 'error', 'result'];
  var state = { file: null, jobId: null, es: null, pollTimer: null, job: null };

  // ---------- helpers ----------
  function show(name) {
    views.forEach(function (v) { $('view-' + v).hidden = v !== name; });
    var card = $('app');
    if (name !== 'upload' && card.getBoundingClientRect().top < 0) card.scrollIntoView({ behavior: 'smooth' });
  }

  function api(path, opts) {
    return fetch('api/' + path, opts).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: 'Server error (' + r.status + ')' }; })
        .then(function (data) {
          if (!r.ok || data.ok === false) throw new Error(data.error || 'Request failed');
          return data;
        });
    });
  }

  function postJson(path, body) {
    return api(path, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
  }

  function setError(el, msg) {
    el.textContent = msg || '';
    el.hidden = !msg;
  }

  function setUrlJob(id) {
    var url = new URL(window.location.href);
    if (id) url.searchParams.set('job', id); else url.searchParams.delete('job');
    window.history.replaceState(null, '', url.toString());
  }

  // ---------- recent websites (stored in this browser only) ----------
  function getRecent() {
    try { return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]'); } catch (e) { return []; }
  }
  function saveRecent(job) {
    try {
      var list = getRecent().filter(function (j) { return j.id !== job.id; });
      list.unshift({ id: job.id, title: job.title || 'Untitled website', paid: !!job.paid, state: job.state, t: Date.now() });
      localStorage.setItem(RECENT_KEY, JSON.stringify(list.slice(0, 8)));
    } catch (e) { /* storage unavailable */ }
    renderRecent();
  }
  function renderRecent() {
    var list = getRecent().filter(function (j) { return j.id !== state.jobId; });
    var ul = $('recent-list');
    ul.innerHTML = '';
    list.forEach(function (j) {
      var li = document.createElement('li');
      var a = document.createElement('a');
      a.href = '?job=' + encodeURIComponent(j.id);
      var title = document.createElement('span');
      title.textContent = j.title;
      var tag = document.createElement('span');
      tag.className = 'tag' + (j.paid ? ' paid' : '');
      tag.textContent = j.paid ? 'Paid · download' : (j.state === 'done' ? 'Ready' : 'Open');
      a.appendChild(title);
      a.appendChild(tag);
      li.appendChild(a);
      ul.appendChild(li);
    });
    $('recent').hidden = list.length === 0;
  }

  // ---------- step 1: choose image ----------
  var fileInput = $('file-input');
  var dropzone = $('dropzone');

  function pickFile(file) {
    setError($('upload-error'), '');
    if (!file) return;
    if (!/^image\/(png|jpe?g|webp|gif)$/.test(file.type)) {
      setError($('upload-error'), 'Please choose a PNG, JPG, WEBP or GIF image.');
      return;
    }
    if (file.size > CFG.maxUploadMb * 1024 * 1024) {
      setError($('upload-error'), 'This image is larger than ' + CFG.maxUploadMb + ' MB.');
      return;
    }
    state.file = file;
    $('dz-img').src = URL.createObjectURL(file);
    $('dz-name').textContent = file.name || 'Pasted screenshot';
    $('dz-empty').hidden = true;
    $('dz-preview').hidden = false;
    $('generate-btn').disabled = false;
  }

  fileInput.addEventListener('change', function () { pickFile(fileInput.files[0]); });
  $('dz-change').addEventListener('click', function (e) { e.preventDefault(); fileInput.click(); });

  ['dragenter', 'dragover'].forEach(function (ev) {
    dropzone.addEventListener(ev, function (e) { e.preventDefault(); dropzone.classList.add('drag'); });
  });
  ['dragleave', 'drop'].forEach(function (ev) {
    dropzone.addEventListener(ev, function (e) { e.preventDefault(); dropzone.classList.remove('drag'); });
  });
  dropzone.addEventListener('drop', function (e) {
    if (e.dataTransfer && e.dataTransfer.files.length) pickFile(e.dataTransfer.files[0]);
  });
  document.addEventListener('paste', function (e) {
    if ($('view-upload').hidden) return;
    var items = (e.clipboardData && e.clipboardData.items) || [];
    for (var i = 0; i < items.length; i++) {
      if (items[i].type.indexOf('image/') === 0) {
        var blob = items[i].getAsFile();
        if (blob) {
          var ext = blob.type.split('/')[1] || 'png';
          pickFile(new File([blob], 'screenshot.' + ext, { type: blob.type }));
        }
        break;
      }
    }
  });

  // ---------- step 2: upload + generate ----------
  function setStage(stage) {
    var order = ['upload', 'read', 'code', 'done'];
    var idx = order.indexOf(stage);
    Array.prototype.forEach.call($('stages').children, function (li) {
      var i = order.indexOf(li.getAttribute('data-stage'));
      li.classList.toggle('complete', i < idx);
      li.classList.toggle('active', i === idx);
    });
    var titles = {
      upload: 'Receiving your design…',
      read: 'Studying every detail…',
      code: 'Crafting your website…',
      done: 'Finished'
    };
    $('work-title').textContent = titles[stage];
  }

  function setProgress(pct, indeterminate) {
    $('progress-bar').parentNode.classList.toggle('indeterminate', !!indeterminate);
    $('progress-bar').style.width = Math.max(4, Math.min(100, pct)) + '%';
  }

  function onCodeProgress(chars) {
    if (!chars) return;
    setStage('code');
    var pct = 20 + 75 * (1 - Math.exp(-chars / EXPECTED_CHARS));
    setProgress(pct, false);
    $('work-sub').textContent = (chars / 1024).toFixed(1) + ' KB of code written so far…';
  }

  $('upload-form').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!state.file) return;
    show('working');
    setStage('upload');
    setProgress(2, false);
    $('work-sub').textContent = 'This usually takes 1–3 minutes. You may keep this tab open in the background.';

    var fd = new FormData();
    fd.append('design', state.file);
    fd.append('notes', $('notes').value);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'api/upload.php');
    xhr.upload.onprogress = function (ev) {
      if (ev.lengthComputable) setProgress((ev.loaded / ev.total) * 15, false);
    };
    xhr.onload = function () {
      var data;
      try { data = JSON.parse(xhr.responseText); } catch (err) { data = { ok: false, error: 'Upload failed (' + xhr.status + ')' }; }
      if (xhr.status >= 400 || !data.ok) {
        show('upload');
        setError($('upload-error'), data.error || 'Upload failed.');
        return;
      }
      state.jobId = data.id;
      setUrlJob(data.id);
      saveRecent({ id: data.id, state: 'uploaded' });
      startGeneration();
    };
    xhr.onerror = function () {
      show('upload');
      setError($('upload-error'), 'Network error. Please check your connection and try again.');
    };
    xhr.send(fd);
  });

  function stopWatching() {
    if (state.es) { state.es.close(); state.es = null; }
    if (state.pollTimer) { clearTimeout(state.pollTimer); state.pollTimer = null; }
  }

  function startGeneration() {
    stopWatching();
    show('working');
    setStage('read');
    setProgress(18, true);

    if (!window.EventSource) { poll(); return; }

    var finished = false;
    var es = new EventSource('api/generate.php?id=' + encodeURIComponent(state.jobId));
    state.es = es;

    es.addEventListener('started', function () { setStage('read'); setProgress(18, true); });
    es.addEventListener('progress', function (ev) {
      onCodeProgress(JSON.parse(ev.data).progress_chars);
    });
    es.addEventListener('done', function (ev) {
      finished = true;
      stopWatching();
      onDone(JSON.parse(ev.data));
    });
    es.addEventListener('error', function (ev) {
      // Named "error" events from the server carry data; connection errors don't.
      if (ev.data) {
        finished = true;
        stopWatching();
        var info = JSON.parse(ev.data);
        showFailure(info.error, info.code);
      } else if (!finished) {
        // Connection dropped (proxy timeout, phone sleep…). The server keeps working; poll instead.
        stopWatching();
        state.pollTimer = setTimeout(poll, 3000);
      }
    });
    es.addEventListener('running', function () {
      finished = true;
      stopWatching();
      poll();
    });
  }

  function poll() {
    api('status.php?id=' + encodeURIComponent(state.jobId))
      .then(function (data) {
        var job = data.job;
        if (job.state === 'done') { onDone(job); return; }
        if (job.state === 'error') { showFailure(job.error); return; }
        if (job.state === 'uploaded') { startGeneration(); return; }
        if (job.progress_chars) onCodeProgress(job.progress_chars); else { setStage('read'); setProgress(18, true); }
        state.pollTimer = setTimeout(poll, 4000);
      })
      .catch(function (err) {
        if (/not found|invalid job/i.test(err.message)) { showFailure(err.message); return; }
        state.pollTimer = setTimeout(poll, 6000);
      });
  }

  function showFailure(msg, code) {
    state.exhausted = code === 'attempts_exhausted';
    show('error');
    $('error-text').textContent = msg || 'Please try again.';
    $('retry-btn').textContent = state.exhausted
      ? (state.file ? 'Start fresh with the same image' : 'Upload the design again')
      : 'Try again';
  }

  $('retry-btn').addEventListener('click', function () {
    if (state.exhausted || !state.jobId) {
      if (state.file) {
        // Same image and notes, new job.
        state.exhausted = false;
        $('upload-form').requestSubmit ? $('upload-form').requestSubmit() : $('generate-btn').click();
      } else {
        resetToUpload();
      }
      return;
    }
    startGeneration();
  });

  // ---------- step 3: result ----------
  function onDone(job) {
    setStage('done');
    setProgress(100, false);
    state.job = job;
    saveRecent(job);
    setTimeout(function () { showResult(job); }, 350);
  }

  function previewUrl() {
    return 'preview.php?id=' + encodeURIComponent(state.jobId) + '&t=' + Date.now();
  }

  function showResult(job) {
    show('result');
    $('result-title').textContent = job.title ? job.title : 'Your website is ready';
    $('browser-url').textContent = job.title || 'preview';
    $('preview-frame').src = previewUrl();
    $('open-full').href = 'preview.php?id=' + encodeURIComponent(state.jobId);
    $('download-btn').href = 'api/download.php?id=' + encodeURIComponent(state.jobId);
    setPaid(!!job.paid, false);
    renderRecent();
  }

  function setPaid(paid, autoDownload) {
    $('paybox').hidden = paid;
    $('paidbox').hidden = !paid;
    $('result-sub').textContent = paid
      ? 'Thank you. Your download carries no watermark.'
      : 'Take a look below. The watermark is removed in the file you download.';
    if (paid && autoDownload) {
      $('preview-frame').src = previewUrl();
      window.location.href = $('download-btn').href;
    }
  }

  Array.prototype.forEach.call(document.querySelectorAll('.dev[data-w]'), function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.dev[data-w]').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      $('preview-frame').style.width = btn.getAttribute('data-w');
    });
  });

  // ---------- step 4: payment ----------
  $('pay-btn').addEventListener('click', function () {
    var btn = $('pay-btn');
    setError($('pay-error'), '');
    if (typeof window.Razorpay !== 'function') {
      setError($('pay-error'), 'The payment window could not load. Please disable ad-blockers for this site and refresh.');
      return;
    }
    btn.classList.add('loading');
    postJson('create-order.php', { id: state.jobId })
      .then(function (order) {
        if (order.paid) { btn.classList.remove('loading'); markPaid(); return; }
        var rzp = new window.Razorpay({
          key: order.key_id,
          amount: order.amount,
          currency: order.currency,
          name: order.name,
          description: order.description,
          order_id: order.order_id,
          notes: { job_id: state.jobId },
          theme: { color: '#c9a96a' },
          handler: function (resp) {
            btn.classList.add('loading');
            postJson('verify-payment.php', {
              id: state.jobId,
              razorpay_order_id: resp.razorpay_order_id,
              razorpay_payment_id: resp.razorpay_payment_id,
              razorpay_signature: resp.razorpay_signature
            })
              .then(markPaid)
              .catch(function (err) { setError($('pay-error'), err.message); })
              .then(function () { btn.classList.remove('loading'); });
          },
          modal: { confirm_close: true }
        });
        rzp.on('payment.failed', function (resp) {
          var reason = resp && resp.error && resp.error.description;
          setError($('pay-error'), 'Payment failed' + (reason ? ': ' + reason : '') + '. No money was taken; please try again.');
        });
        rzp.open();
        btn.classList.remove('loading');
      })
      .catch(function (err) {
        btn.classList.remove('loading');
        setError($('pay-error'), err.message);
      });
  });

  function markPaid() {
    state.job = Object.assign({}, state.job, { paid: true });
    saveRecent(state.job);
    setPaid(true, true);
  }

  // ---------- reset / resume ----------
  function resetToUpload() {
    stopWatching();
    state.jobId = null;
    state.job = null;
    state.file = null;
    state.exhausted = false;
    setUrlJob(null);
    fileInput.value = '';
    $('notes').value = '';
    $('dz-empty').hidden = false;
    $('dz-preview').hidden = true;
    $('generate-btn').disabled = true;
    setError($('upload-error'), '');
    setError($('pay-error'), '');
    show('upload');
    renderRecent();
    $('app').scrollIntoView({ behavior: 'smooth' });
  }
  Array.prototype.forEach.call(document.querySelectorAll('[data-action="new"]'), function (b) {
    b.addEventListener('click', resetToUpload);
  });

  var resumeId = new URL(window.location.href).searchParams.get('job');
  if (resumeId && /^[a-f0-9]{32}$/.test(resumeId)) {
    state.jobId = resumeId;
    show('working');
    setStage('read');
    setProgress(18, true);
    $('app').scrollIntoView();
    poll();
  } else {
    show('upload');
  }
  renderRecent();
})();
