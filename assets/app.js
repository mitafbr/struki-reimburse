// =========================================================
// Struki — interaksi & animasi. Semua halaman tetap berfungsi tanpa JavaScript.
// =========================================================
(function () {
  'use strict';
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const rp = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');
  const angka = s => parseInt(String(s).replace(/[^0-9]/g, ''), 10) || 0;
  const store = {
    get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* mode privat */ } }
  };
  const ICON_CHECK = '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7"/></svg>';

  // ---------- 1. Tema terang / gelap ----------
  $$('.theme-toggle').forEach(b => b.addEventListener('click', () => {
    const root = document.documentElement;
    const now = root.dataset.theme || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    const next = now === 'dark' ? 'light' : 'dark';
    root.dataset.theme = next;
    store.set('struki-theme', next);
  }));

  // ---------- 2. Menu samping di HP ----------
  const side = $('#side'), tog = $('.side-toggle');
  if (side && tog) tog.addEventListener('click', () => {
    const open = side.classList.toggle('open');
    tog.setAttribute('aria-expanded', open);
  });

  // ---------- 3. Progress bar + tautan aktif (halaman depan) ----------
  const bar = $('#progress');
  if (bar) {
    const onScroll = () => {
      const h = document.documentElement.scrollHeight - innerHeight;
      bar.style.transform = `scaleX(${h > 0 ? scrollY / h : 0})`;
    };
    addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }
  const navLinks = $$('.lnav-links a');
  if (navLinks.length && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver(en => en.forEach(x => {
      if (x.isIntersecting) navLinks.forEach(a => a.classList.toggle('on', a.getAttribute('href') === '#' + x.target.id));
    }), { rootMargin: '-40% 0px -55% 0px' });
    navLinks.forEach(a => { const s = $(a.getAttribute('href')); if (s) io.observe(s); });
  }

  // tampilan produk membesar pelan saat digulir
  const show = $('#showcase');
  if (show && !reduce) {
    const sc = () => {
      const r = show.getBoundingClientRect();
      const p = Math.min(1, Math.max(0, 1 - r.top / innerHeight));
      show.style.transform = `scale(${0.94 + p * 0.06})`;
    };
    addEventListener('scroll', sc, { passive: true });
    sc();
  }

  // ---------- 4. Riak tombol ----------
  document.addEventListener('pointerdown', e => {
    const b = e.target.closest('.btn');
    if (!b || b.disabled || reduce) return;
    const r = b.getBoundingClientRect(), s = Math.max(r.width, r.height);
    const sp = document.createElement('span');
    sp.className = 'ripple';
    sp.style.cssText = `width:${s}px;height:${s}px;left:${e.clientX - r.left - s / 2}px;top:${e.clientY - r.top - s / 2}px`;
    b.appendChild(sp);
    setTimeout(() => sp.remove(), 650);
  });

  // ---------- 5. Tombol magnet & kartu miring ----------
  if (!reduce && matchMedia('(pointer:fine)').matches) {
    $$('.magnet').forEach(b => {
      b.addEventListener('pointermove', e => {
        const r = b.getBoundingClientRect();
        b.style.transform = `translate(${(e.clientX - r.left - r.width / 2) * .15}px, ${(e.clientY - r.top - r.height / 2) * .25}px)`;
      });
      b.addEventListener('pointerleave', () => b.style.transform = '');
    });
    $$('.tilt').forEach(c => {
      c.addEventListener('pointermove', e => {
        const r = c.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
        c.style.transform = `perspective(800px) rotateX(${-y * 8}deg) rotateY(${x * 10}deg) translateY(-4px)`;
      });
      c.addEventListener('pointerleave', () => c.style.transform = '');
    });
  }

  // ---------- 6. Konfeti koin ----------
  function confetti() {
    if (reduce) return;
    const box = document.createElement('div');
    box.className = 'confetti';
    const warna = ['#2B59FF', '#8B5CF6', '#34C759', '#FBBF24', '#FF6B8B'];
    for (let i = 0; i < 80; i++) {
      const p = document.createElement('i');
      if (i % 4 === 0) p.className = 'coin-c';
      p.style.cssText = `left:${Math.random() * 100}%;background:${warna[i % warna.length]};--x:${(Math.random() - .5) * 260}px;--rot:${Math.random() * 900 - 450}deg;--t:${1.6 + Math.random() * 1.6}s;animation-delay:${Math.random() * .4}s`;
      box.appendChild(p);
    }
    document.body.appendChild(box);
    setTimeout(() => box.remove(), 3800);
  }

  // ---------- 7. Notifikasi ----------
  const toast = $('.toast');
  if (toast) {
    const tutup = () => { toast.classList.add('hide'); setTimeout(() => toast.remove(), 420); };
    const t = setTimeout(tutup, 5000);
    toast.addEventListener('click', () => { clearTimeout(t); tutup(); });
    if (toast.dataset.extra === 'confetti') setTimeout(confetti, 300);
  }

  // ---------- 8. Muncul saat terlihat, angka menghitung, bar memanjang ----------
  function fmt(n, type) {
    if (type === 'rp') return rp(n);
    if (type === 'dec') return n.toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
    return Math.round(n).toLocaleString('id-ID');
  }
  function countUp(el) {
    if (el.dataset.done) return;
    el.dataset.done = 1;
    const target = parseFloat(el.dataset.count) || 0, type = el.dataset.type, suf = el.dataset.suffix || '';
    if (reduce || !target) { el.textContent = fmt(target, type) + suf; return; }
    const t0 = performance.now(), dur = 1200;
    const step = now => {
      const p = Math.min(1, (now - t0) / dur), k = 1 - Math.pow(1 - p, 4);
      el.textContent = fmt(target * k, type) + suf;
      if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  }
  const reveal = el => {
    el.classList.add('in');
    if (el.matches('[data-count]')) countUp(el);
    $$('[data-count]', el).forEach(countUp);
    if (el.matches('[data-w]')) el.style.width = el.dataset.w;
    $$('[data-w]', el).forEach(b => b.style.width = b.dataset.w);
  };
  const watch = $$('.reveal, .steps-line, .bx, [data-count], [data-w]');
  if ('IntersectionObserver' in window && !reduce) {
    const io = new IntersectionObserver(en => en.forEach(x => { if (x.isIntersecting) { reveal(x.target); io.unobserve(x.target); } }), { threshold: .15 });
    watch.forEach(el => io.observe(el));
  } else watch.forEach(reveal);

  // ---------- 9. Saring status di tabel ----------
  $$('.seg').forEach(seg => seg.addEventListener('click', e => {
    const b = e.target.closest('button');
    if (!b) return;
    $$('button', seg).forEach(x => x.classList.toggle('on', x === b));
    const f = b.dataset.filter, card = seg.closest('.card') || document;
    let i = 0;
    $$('tr[data-status]', card).forEach(tr => {
      const hide = f && tr.dataset.status !== f;
      tr.classList.toggle('hidden', hide);
      if (!hide && !reduce) { tr.style.animation = 'none'; void tr.offsetWidth; tr.style.animation = `rise .4s ${i++ * 40}ms var(--ease) both`; }
    });
  }));
  // baris tabel bisa diklik
  document.addEventListener('click', e => {
    const tr = e.target.closest('tr[data-href]');
    if (tr && !e.target.closest('a, button, input, label')) location.href = tr.dataset.href;
  });

  // ---------- 10. Password & kartu pegawai ----------
  $$('.pw-eye').forEach(b => b.addEventListener('click', () => {
    const inp = b.previousElementSibling, lihat = inp.type === 'password';
    inp.type = lihat ? 'text' : 'password';
    b.classList.toggle('on', lihat);
    b.setAttribute('aria-label', lihat ? 'Sembunyikan password' : 'Tampilkan password');
  }));
  const pw1 = $('#pw1'), meter = $('.meter i'), mtxt = $('.meter-txt');
  if (pw1 && meter) pw1.addEventListener('input', () => {
    const v = pw1.value;
    let s = 0;
    if (v.length >= 6) s++;
    if (v.length >= 10) s++;
    if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
    if (/[0-9]/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    const lvl = [['0%', '#FF453A', 'Minimal 6 karakter'], ['25%', '#FF453A', 'Lemah'], ['50%', '#FF9F0A', 'Cukup'], ['75%', '#2B59FF', 'Kuat'], ['100%', '#34C759', 'Sangat kuat']][Math.min(s, 4)];
    meter.style.width = v ? lvl[0] : '0%';
    meter.style.background = lvl[1];
    mtxt.textContent = v ? lvl[2] : 'Minimal 6 karakter';
  });
  $$('[data-live]').forEach(inp => {
    const out = $(`[data-out="${inp.dataset.live}"]`);
    if (!out) return;
    const def = out.textContent;
    const upd = () => {
      let v = inp.value.trim();
      if (inp.dataset.live === 'rek' && v) v = v.replace(/[^0-9]/g, '').replace(/(.{4})/g, '$1 ').trim();
      out.textContent = v || def;
      out.classList.remove('pulse-once'); void out.offsetWidth; out.classList.add('pulse-once');
    };
    inp.addEventListener('input', upd);
    inp.addEventListener('change', upd);
  });
  $$('[data-acc]').forEach(b => b.addEventListener('click', () => {
    $$('.demo-acc').forEach(x => x.classList.toggle('sel', x === b));
    $('#lu').value = b.dataset.acc;
    $('#lp').value = 'demo123';
    $('#lp').focus();
  }));

  // ---------- 11. Pratinjau file di zona unggah ----------
  function preview(drop, file) {
    const pv = $('.drop-prev', drop), txt = $('.drop-txt', drop);
    if (/pdf$/i.test(file.type) || /\.pdf$/i.test(file.name)) pv.innerHTML = '<span class="pdf-chip">PDF</span>';
    else { pv.innerHTML = ''; const img = new Image(); img.alt = 'Nota'; img.src = URL.createObjectURL(file); pv.appendChild(img); }
    const nama = file.name.length > 30 ? file.name.slice(0, 27) + '…' : file.name;
    if (txt) txt.innerHTML = `<b>${nama.replace(/</g, '&lt;')}</b><small>${(file.size / 1024 / 1024).toFixed(2)} MB${file.size > 5 * 1024 * 1024 ? ' · terlalu besar, maksimal 5 MB' : ' · siap dikirim'}</small>`;
    const wrap = drop.closest('.nota');
    if (wrap) wrap.classList.add('has');
  }
  document.addEventListener('change', e => {
    const inp = e.target;
    if (inp.type === 'file' && inp.files[0] && inp.closest('.drop')) preview(inp.closest('.drop'), inp.files[0]);
  });
  ['dragenter', 'dragover'].forEach(ev => document.addEventListener(ev, e => { const d = e.target.closest && e.target.closest('.drop'); if (d) { e.preventDefault(); d.classList.add('over'); } }));
  ['dragleave', 'drop'].forEach(ev => document.addEventListener(ev, e => { const d = e.target.closest && e.target.closest('.drop'); if (d) d.classList.remove('over'); }));
  document.addEventListener('drop', e => {
    const d = e.target.closest && e.target.closest('.drop');
    if (!d || !e.dataTransfer.files.length) return;
    e.preventDefault();
    const inp = $('input[type=file]', d);
    inp.files = e.dataTransfer.files;
    inp.dispatchEvent(new Event('change', { bubbles: true }));
  });

  // ---------- 12. Form pengajuan ----------
  const form = $('#cform');
  if (form) {
    const KAT = window.STRUKI_KATEGORI || {};
    const box = $('#items'), lines = $('#pvLines'), total = $('#total');
    const fB = $('#fBerangkat'), fP = $('#fPulang'), msg = $('#dateMsg'), btn = $('#btnKirim'), hint = $('#kirimHint');
    let next = window.STRUKI_NEXT || 1;
    const esc = s => s.replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const rows = () => $$('.item', box).filter(r => !r.classList.contains('removing'));
    const punyaNota = r => $('.nota-file', r).files.length > 0 || !!$('.nota-lama', r).value;
    const setCek = (k, ok, teks) => { const li = $(`[data-c="${k}"]`); if (!li) return; li.className = ok === null ? '' : (ok ? 'ok' : 'warn'); if (teks) li.textContent = teks; };

    const hitung = () => {
      let t = 0, html = '', nota = 0, rentangOk = true;
      const a = fB.value, b = fP.value;
      rows().forEach(row => {
        const n = angka($('.nominal', row).value), k = $('.kat', row).value, ket = $('.ket', row).value.trim(), tg = $('.tg', row).value;
        const lim = KAT[k] && KAT[k].limit, over = lim && n > lim;
        t += n;
        if (punyaNota(row)) nota++;
        if (tg && a && b && (tg < a || tg > b)) rentangOk = false;
        $('.limit-msg', row).textContent = over ? `Rp ${(n - lim).toLocaleString('id-ID')} di atas batas ${KAT[k].label.toLowerCase()}. Tetap bisa diajukan, tapi ditandai.` : '';
        row.classList.toggle('is-over', !!over);
        if (n || ket) html += `<div class="ln${over ? ' over' : ''}"><span>${esc(ket || KAT[k].label)}</span><b class="num">${n.toLocaleString('id-ID')}</b></div>`;
      });
      if (lines.dataset.html !== html) { lines.innerHTML = html || '<span class="empty-ln">Item biaya muncul di sini saat kamu mengetik</span>'; lines.dataset.html = html; }
      const txt = rp(t);
      if (total.textContent !== txt) { total.textContent = txt; total.classList.remove('bump'); void total.offsetWidth; total.classList.add('bump'); }
      const n = rows().length, lengkap = nota === n;
      setCek('nota', lengkap, `${nota} dari ${n} nota ${lengkap ? 'lengkap' : 'terunggah'}`);
      setCek('rentang', a && b ? rentangOk : null);
      rows().forEach(r => $('.hapus', r).hidden = n === 1);
      const tglOk = !msg.classList.contains('bad') && !!(a && b);
      setCek('tgl', a && b ? tglOk : null);
      const siap = lengkap && rentangOk && tglOk && t > 0;
      btn.classList.toggle('ready', siap);
      hint.textContent = siap ? 'Semua aturan terpenuhi. Siap dikirim.' : 'Lengkapi nota dan tanggal dulu. Sistem tetap memeriksa saat dikirim.';
    };

    box.addEventListener('input', e => {
      if (e.target.classList.contains('nominal')) {
        const pos = e.target.value.length - e.target.selectionStart;
        const n = angka(e.target.value);
        e.target.value = n ? n.toLocaleString('id-ID') : '';
        const p = Math.max(0, e.target.value.length - pos);
        e.target.setSelectionRange(p, p);
      }
      hitung();
    });
    box.addEventListener('change', hitung);
    box.addEventListener('click', e => {
      const h = e.target.closest('.hapus');
      if (h) {
        const row = h.closest('.item');
        row.classList.add('removing');
        hitung();
        setTimeout(() => { row.remove(); hitung(); }, reduce ? 0 : 300);
      }
      const up = e.target.closest('.up-open');
      if (up) $('.nota-file', up.closest('.item')).click();
      const cam = e.target.closest('.cam-open');
      if (cam) bukaKamera($('.nota-file', cam.closest('.item')));
    });
    $('#tambah').addEventListener('click', () => {
      box.insertAdjacentHTML('beforeend', $('#tpl').innerHTML.replaceAll('__IDX__', next++));
      const row = box.lastElementChild;
      row.classList.add('adding');
      batasTanggal();
      hitung();
      $('.ket', row).focus();
    });

    // DR-01 & DR-02
    const batasTanggal = () => {
      const a = fB.value, b = fP.value, min = form.dataset.min, today = form.dataset.today;
      if (a) fP.min = min && min > a ? min : a;
      $$('.tg', box).forEach(t => { t.min = a || ''; t.max = b || today; });
      let m = '', bad = false;
      if (a && b && b < a) { m = 'Tanggal pulang tidak boleh sebelum tanggal berangkat.'; bad = true; }
      else if (b && min && b < min) { m = 'Sudah lewat 14 hari sejak pulang dinas. Pengajuan akan ditolak sistem.'; bad = true; }
      else if (a && b) {
        const hari = Math.round((Date.parse(b) - Date.parse(a)) / 864e5) + 1;
        const sisa = min ? Math.round((Date.parse(b) - Date.parse(min)) / 864e5) : null;
        m = `${hari} hari dinas.` + (sisa !== null ? ` Batas mengajukan tinggal ${sisa} hari lagi.` : '');
      }
      msg.textContent = m;
      msg.classList.toggle('bad', bad);
      hitung();
    };
    [fB, fP].forEach(x => x.addEventListener('change', batasTanggal));
    batasTanggal();

    // ---------- kamera: foto nota langsung ----------
    const cam = $('#cam'), video = $('#camVideo'), shot = $('#camShot'), canvas = $('#camCanvas');
    const bar1 = $('#camBar'), bar2 = $('#camAfter'), camHint = $('#camHint'), fallback = $('#camFallback');
    let stream = null, target = null, blob = null, facing = 'environment';

    async function mulai() {
      if (stream) stream.getTracks().forEach(t => t.stop());
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: facing }, width: { ideal: 1920 }, height: { ideal: 1440 } }, audio: false });
      video.srcObject = stream;
    }
    function tutupKamera() {
      if (stream) stream.getTracks().forEach(t => t.stop());
      stream = null;
      cam.hidden = true;
      document.body.style.overflow = '';
    }
    function ulang() {
      cam.classList.remove('shot');
      shot.hidden = true; video.hidden = false;
      bar1.hidden = false; bar2.hidden = true;
      camHint.textContent = 'Posisikan nota di dalam bingkai';
    }
    async function bukaKamera(input) {
      target = input;
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { fallback.click(); return; }
      cam.hidden = false;
      document.body.style.overflow = 'hidden';
      ulang();
      try { await mulai(); }
      catch (err) { tutupKamera(); fallback.click(); }
    }
    // HP tanpa izin kamera di browser: pakai kamera bawaan lewat input
    fallback.addEventListener('change', () => {
      if (!fallback.files[0] || !target) return;
      const dt = new DataTransfer();
      dt.items.add(fallback.files[0]);
      target.files = dt.files;
      target.dispatchEvent(new Event('change', { bubbles: true }));
      fallback.value = '';
    });
    $('#camSnap').addEventListener('click', () => {
      const w = video.videoWidth, h = video.videoHeight;
      if (!w) return;
      canvas.width = w; canvas.height = h;
      canvas.getContext('2d').drawImage(video, 0, 0, w, h);
      const fl = document.createElement('span');
      fl.className = 'cam-flash';
      $('.cam-box').appendChild(fl);
      setTimeout(() => fl.remove(), 400);
      canvas.toBlob(b => {
        blob = b;
        shot.src = URL.createObjectURL(b);
        shot.hidden = false; video.hidden = true;
        cam.classList.add('shot');
        bar1.hidden = true; bar2.hidden = false;
        camHint.textContent = 'Nominal dan tulisan terbaca jelas?';
      }, 'image/jpeg', 0.88);
    });
    $('#camRetake').addEventListener('click', ulang);
    $('#camUse').addEventListener('click', () => {
      if (!blob || !target) return;
      const file = new File([blob], 'nota_kamera_' + Date.now() + '.jpg', { type: 'image/jpeg' });
      const dt = new DataTransfer();
      dt.items.add(file);
      target.files = dt.files;
      target.dispatchEvent(new Event('change', { bubbles: true }));
      tutupKamera();
    });
    $('#camClose').addEventListener('click', tutupKamera);
    $('#camFlip').addEventListener('click', async () => { facing = facing === 'environment' ? 'user' : 'environment'; try { await mulai(); } catch (e) { /* hanya satu kamera */ } });
    cam.addEventListener('click', e => { if (e.target === cam) tutupKamera(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !cam.hidden) tutupKamera(); });

    // judul ikut tampil, lalu hitung awal
    hitung();
  }

  // ---------- 13. Halaman detail ----------
  const cap = $('[data-cap]');
  if (cap && !reduce) cap.classList.add('thunk');
  const track = $('.track');
  if (track) setTimeout(() => track.classList.add('run'), 150);

  const vBody = $('#vBody'), vLabel = $('#vLabel'), vZoom = $('#vZoom');
  const tampilNota = li => {
    if (!vBody || !li) return;
    $$('.line').forEach(x => x.classList.toggle('sel', x === li));
    const src = li.dataset.nota;
    vLabel.textContent = 'Nota · ' + li.dataset.label;
    vZoom.hidden = !src || /\.pdf$/i.test(src);
    vZoom.dataset.src = src;
    if (!src) vBody.innerHTML = '<div class="viewer-empty"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M7 3h7l5 5v13H7zM14 3v5h5"/></svg>Item data contoh ini belum punya file nota. Pengajuan baru selalu punya nota.</div>';
    else if (/\.pdf$/i.test(src)) vBody.innerHTML = `<object data="${src}" type="application/pdf"><div class="viewer-empty">Nota berupa PDF.<br><a class="btn soft sm" href="${src}" target="_blank" rel="noopener">Buka PDF di tab baru</a></div></object>`;
    else vBody.innerHTML = `<img src="${src}" alt="Nota" data-zoom="${src}">`;
  };
  $$('.line').forEach(li => {
    li.addEventListener('click', e => { if (!e.target.closest('.chk')) tampilNota(li); });
    li.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); tampilNota(li); } });
  });
  if (vBody) tampilNota($('.line'));
  if (vZoom) vZoom.addEventListener('click', () => vZoom.dataset.src && lightbox(vZoom.dataset.src));

  // Finance: centang semua → tombol aktif + cap pratinjau
  const chks = $$('.chk input'), btnV = $('#btnVerif'), vCap = $('#vCap');
  if (btnV) {
    const upd = () => {
      const n = chks.filter(c => c.checked).length, ok = n === chks.length;
      btnV.disabled = !ok;
      btnV.innerHTML = ok ? ICON_CHECK + 'Verifikasi semua nota' : `Verifikasi (${n}/${chks.length} dicek)`;
      if (vCap) vCap.hidden = !ok;
    };
    chks.forEach(c => c.addEventListener('change', () => { c.closest('.line').classList.toggle('checked', c.checked); tampilNota(c.closest('.line')); upd(); }));
    upd();
  }

  // catatan wajib untuk revisi / tolak
  const fa = $('#faksi');
  if (fa) fa.addEventListener('submit', e => {
    const a = e.submitter ? e.submitter.value : '';
    const note = $('[name=catatan]', fa);
    if ((a === 'revisi' || a === 'tolak') && note && !note.value.trim()) {
      e.preventDefault();
      note.focus();
      note.placeholder = 'Tulis catatan dulu supaya pegawai tahu apa yang harus diperbaiki';
      const f = note.closest('.field');
      f.classList.add('bad');
      note.classList.remove('shake'); void note.offsetWidth; note.classList.add('shake');
    }
  });

  // salin nomor rekening
  $$('[data-copy]').forEach(b => b.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(b.dataset.copy); b.textContent = 'Tersalin ✓'; } catch (e) { b.textContent = 'Salin manual'; }
    setTimeout(() => b.textContent = 'Salin', 1800);
  }));

  // ---------- 14. Lightbox nota ----------
  function lightbox(src) {
    const lb = document.createElement('div');
    lb.className = 'lightbox';
    lb.setAttribute('role', 'dialog');
    lb.setAttribute('aria-label', 'Nota diperbesar, klik untuk menutup');
    lb.innerHTML = `<img src="${src}" alt="Nota diperbesar">`;
    lb.addEventListener('click', () => lb.remove());
    document.body.appendChild(lb);
  }
  document.addEventListener('click', e => {
    const z = e.target.closest('[data-zoom]');
    if (z) { e.preventDefault(); lightbox(z.dataset.zoom); }
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') { const l = $('.lightbox'); if (l) l.remove(); } });
})();
