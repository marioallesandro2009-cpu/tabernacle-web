/* Tabernacle Ways v3: gentle motion only. Nothing is pinned, nothing needs the visitor to work for it.
   The page reads fine if this file never runs. */
(function () {
  "use strict";

  var doc = document.documentElement;
  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var hasGsap = typeof window.gsap !== "undefined" && typeof window.ScrollTrigger !== "undefined";
  var TW = window.TW || { msg: {} };
  var isId = TW.lang === "id";
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var WA = TW.wa || "";
  var UTM = readUtm();

  initMenu();
  initNav();
  initWhatsApp();
  initPackages();
  initPackageFilter();
  initLightbox();
  initFilter();
  initFilm();
  fitFootWord();

  if (!hasGsap || reduce) {
    doc.classList.remove("is-loading");
    initBoard(false);
    return;
  }

  gsap.registerPlugin(ScrollTrigger);
  if (window.SplitText) gsap.registerPlugin(SplitText);
  gsap.defaults({ ease: "expo.out" });

  /* smooth scroll */
  var lenis = null;
  if (window.Lenis) {
    lenis = new Lenis({ lerp: 0.12, smoothWheel: true });
    lenis.on("scroll", ScrollTrigger.update);
    gsap.ticker.add(function (t) { lenis.raf(t * 1000); });
    gsap.ticker.lagSmoothing(0);
    window.__lenis = lenis;
  }

  $$('a[href^="#"]').forEach(function (a) {
    a.addEventListener("click", function (e) {
      var id = a.getAttribute("href");
      if (id.length < 2) return;
      var el = $(id);
      if (!el) return;
      e.preventDefault();
      closeMenu();
      if (lenis) lenis.scrollTo(el, { offset: -70, duration: 1.4 }); else el.scrollIntoView();
    });
  });

  /* gates */
  var gates = {
    root: $(".gates"), a: $(".gate-a"), b: $(".gate-b"), c: $(".gate-c"),
    open: function (onStart) {
      var tl = gsap.timeline({
        onComplete: function () {
          doc.classList.remove("is-loading");
          gates.root.classList.remove("on");
          gsap.set(gates.root, { clearProps: "visibility" });
          ScrollTrigger.refresh();
        }
      });
      tl.fromTo(".gate-mark svg", { scale: 0.88, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.7, ease: "power3.out" })
        .add(function () { if (onStart) onStart(); }, 0.7)
        .to(gates.a, { xPercent: -101, duration: 1.1, ease: "expo.inOut" }, 0.7)
        .to(gates.c, { xPercent: 101, duration: 1.1, ease: "expo.inOut" }, 0.7)
        .to(gates.b, { yPercent: -101, duration: 1.1, ease: "expo.inOut" }, 0.76);
    },
    close: function (done) {
      gates.root.classList.add("on");
      gsap.set(gates.root, { visibility: "visible" });
      gsap.timeline({ onComplete: done })
        .fromTo(gates.a, { xPercent: -101 }, { xPercent: 0, duration: 0.65, ease: "expo.inOut" }, 0)
        .fromTo(gates.c, { xPercent: 101 }, { xPercent: 0, duration: 0.65, ease: "expo.inOut" }, 0)
        .fromTo(gates.b, { yPercent: 101 }, { yPercent: 0, duration: 0.65, ease: "expo.inOut" }, 0.04);
    }
  };
  window.__gatesClose = gates.close;

  $$("a[href]").forEach(function (a) {
    var href = a.getAttribute("href");
    if (!href || href.charAt(0) === "#" || a.hasAttribute("download") || a.target === "_blank" || /^(mailto:|tel:|javascript:)/i.test(href)) return;
    var u;
    try { u = new URL(a.href, location.href); } catch (err) { return; }
    if (u.origin !== location.origin || /^\/(admin|uploads|build|storage)(\/|$)/.test(u.pathname) || /\.(pdf|xml|txt|zip|webp|jpg|png|mp4)$/i.test(u.pathname)) return;
    a.addEventListener("click", function (e) {
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button) return;
      if (u.pathname === location.pathname && u.search === location.search) {
        var hash = u.hash && u.hash.length > 1 ? u.hash.slice(1) : "", el = hash && $("#" + hash);
        e.preventDefault();
        closeMenu();
        if (lenis) lenis.scrollTo(el || 0, { offset: -70, duration: 1.4 });
        return;
      }
      e.preventDefault();
      closeMenu();
      gates.close(function () { location.href = u.href; });
    });
  });
  window.addEventListener("pageshow", function (e) {
    if (e.persisted) { gates.root.classList.remove("on"); gsap.set([gates.a, gates.c, gates.b], { xPercent: 0, yPercent: -101 }); }
  });

  /* reveals */
  function reveals() {
    $$("[data-lines]").forEach(function (el) {
      if (!window.SplitText) return;
      var s = new SplitText(el, { type: "lines", mask: "lines" });
      gsap.set(s.lines, { yPercent: 105 });
      ScrollTrigger.create({ trigger: el, start: "top 88%", once: true, onEnter: function () { gsap.to(s.lines, { yPercent: 0, duration: 1.1, stagger: 0.08 }); } });
    });
    $$("[data-fade]").forEach(function (el) {
      gsap.from(el, { y: 24, autoAlpha: 0, duration: 1, scrollTrigger: { trigger: el, start: "top 90%", once: true } });
    });
    $$("[data-stagger]").forEach(function (el) {
      gsap.from(el.children, { y: 24, autoAlpha: 0, duration: 0.9, stagger: 0.07, scrollTrigger: { trigger: el, start: "top 88%", once: true } });
    });
    $$("[data-reveal]").forEach(function (el) {
      var img = $("img", el);
      var tl = gsap.timeline({ scrollTrigger: { trigger: el, start: "top 88%", once: true } });
      tl.fromTo(el, { clipPath: "inset(12% 0 0 0)", autoAlpha: 0 }, { clipPath: "inset(0% 0 0 0)", autoAlpha: 1, duration: 1.3, clearProps: "clipPath" }, 0);
      if (img) tl.fromTo(img, { scale: 1.12 }, { scale: 1, duration: 1.6, clearProps: "transform" }, 0);
    });
    $$("[data-count]").forEach(function (el) {
      var end = parseFloat(el.dataset.count), suf = el.dataset.suffix || "", o = { v: 0 };
      ScrollTrigger.create({ trigger: el, start: "top 90%", once: true, onEnter: function () {
        gsap.to(o, { v: end, duration: 1.8, ease: "power3.out", onUpdate: function () {
          var n = Math.round(o.v);
          el.textContent = (el.dataset.fmt === "comma" ? n.toLocaleString("en-US") : n) + suf;
        } });
      } });
    });
    $$("[data-scramble]").forEach(function (el, i) {
      var final = el.textContent, chars = "0123456789";
      el.setAttribute("aria-label", final);
      ScrollTrigger.create({ trigger: el, start: "top 92%", once: true, onEnter: function () {
        var o = { p: 0 };
        gsap.to(o, { p: 1, duration: 1.2, delay: i * 0.08, ease: "power2.out", onUpdate: function () {
          var n = Math.floor(o.p * final.length), s = final.slice(0, n);
          for (var k = n; k < final.length; k++) s += /\d/.test(final[k]) ? chars[Math.floor(Math.random() * 10)] : final[k];
          el.textContent = s;
        }, onComplete: function () { el.textContent = final; } });
      } });
    });
  }

  function hero() {
    var h = $(".hero");
    if (h) {
      var parts = $$(".hero h1 .ln > span");
      gsap.set(parts, { yPercent: 110 });
      gsap.set(".hero-foot > *", { autoAlpha: 0, y: 16 });
      gsap.set(".hero-bg img", { scale: 1.15 });
      h.__enter = function () {
        gsap.to(".hero-bg img", { scale: 1, duration: 2.4 });
        gsap.to(parts, { yPercent: 0, duration: 1.4, stagger: 0.1, delay: 0.15 });
        gsap.to(".hero-foot > *", { autoAlpha: 1, y: 0, duration: 1, stagger: 0.08, delay: 0.6 });
      };
      gsap.to(".hero-bg", { yPercent: 10, ease: "none", scrollTrigger: { trigger: h, start: "top top", end: "bottom top", scrub: true } });
    }
    var a = $(".ab-hero");
    if (a) {
      var p2 = $$(".ab-hero h1 .ln > span");
      gsap.set(p2, { yPercent: 110 });
      a.__enter = function () { gsap.to(p2, { yPercent: 0, duration: 1.3, stagger: 0.1, delay: 0.15 }); };
    }
  }

  function boot() {
    hero();
    reveals();
    initBoard(true);
    var h = $(".hero") || $(".ab-hero");
    gates.open(function () {
      if (h && h.__enter) h.__enter();
      if (location.hash && lenis) {
        var el = $(location.hash);
        if (el) setTimeout(function () { lenis.scrollTo(el, { immediate: true, offset: -70 }); }, 200);
      }
    });
  }
  var started = false;
  var go = function () { if (!started) { started = true; boot(); } };
  if (document.fonts && document.fonts.ready) { document.fonts.ready.then(go); setTimeout(go, 1500); } else go();

  /* ======== works without GSAP too ======== */

  function closeMenu() {
    document.body.classList.remove("menu-open");
    var b = $(".burger"); if (b) b.setAttribute("aria-expanded", "false");
    if (window.__lenis) window.__lenis.start();
  }
  function initMenu() {
    var b = $(".burger");
    if (!b) return;
    b.addEventListener("click", function () {
      var on = document.body.classList.toggle("menu-open");
      b.setAttribute("aria-expanded", String(on));
      if (window.__lenis) { if (on) window.__lenis.stop(); else window.__lenis.start(); }
    });
    $$(".menu a").forEach(function (a) { a.addEventListener("click", closeMenu); });
  }

  /* solid bar once the hero is behind us, so it never sits on top of text */
  function initNav() {
    var nav = $(".nav");
    if (!nav) return;
    var always = nav.hasAttribute("data-solid");
    var set = function () { nav.classList.toggle("solid", always || window.scrollY > 40); };
    set();
    window.addEventListener("scroll", set, { passive: true });
  }

  function readUtm() {
    var o = {};
    try {
      var p = new URLSearchParams(location.search);
      ["utm_source", "utm_medium", "utm_campaign"].forEach(function (k) {
        if (p.get(k)) sessionStorage.setItem("tw_" + k, p.get(k));
        var v = sessionStorage.getItem("tw_" + k);
        if (v) o[k] = v;
      });
    } catch (err) { /* private mode: no UTM memory */ }
    return o;
  }
  window.__twUtm = UTM;

  /* every WhatsApp button opens wa.me and is recorded as a lead of type "wa_click" */
  function trackWa(a, label) {
    a.addEventListener("click", function () {
      try {
        var fd = new FormData();
        fd.append("_token", TW.csrf || "");
        fd.append("page_url", location.href);
        fd.append("label", label || "");
        fd.append("referrer", document.referrer || "");
        if (a.dataset.tourId) fd.append("tour_id", a.dataset.tourId);
        Object.keys(UTM).forEach(function (k) { fd.append(k, UTM[k]); });
        navigator.sendBeacon("/track/wa", fd);
      } catch (err) { /* never block the click */ }
    });
  }

  function waHref(text) { return "https://wa.me/" + WA + (text ? "?text=" + encodeURIComponent(text) : ""); }

  function initWhatsApp() {
    if (!WA) return;
    $$("[data-wa]").forEach(function (a) {
      a.setAttribute("href", waHref((TW.msg || {}).plan || ""));
      a.setAttribute("target", "_blank");
      a.setAttribute("rel", "noopener");
      trackWa(a, "Rencanakan perjalanan");
    });
  }

  /* each package opens WhatsApp already asking about that trip */
  function initPackages() {
    if (!WA) return;
    $$("[data-pkg]").forEach(function (a) {
      var n = a.dataset.pkg;
      a.setAttribute("href", waHref(((TW.msg || {}).pkg || "").replace(":name", n)));
      a.setAttribute("target", "_blank");
      a.setAttribute("rel", "noopener");
      trackWa(a, n);
    });
  }

  /* packages page: type and region filters, the uneven tile rhythm follows what is visible */
  function initPackageFilter() {
    var grid = $(".pk-grid");
    if (!grid) return;
    var tiles = $$(".pkg", grid), spans = [7, 5, 4, 4, 4, 5, 7, 4, 4, 4], state = { type: "all", region: "all", country: "all", q: "" };
    var qIn = $(".pk-q input"), sel = $(".pk-country select");
    var params = new URLSearchParams(location.search);
    if (params.get("country") && sel && $('option[value="' + params.get("country") + '"]', sel)) { state.country = params.get("country"); sel.value = state.country; }
    var norm = function (s) { return s.toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "").replace(/[^a-z0-9]+/g, " ").trim(); };
    if (params.get("q") && qIn) { state.q = norm(params.get("q")); qIn.value = params.get("q"); }
    var count = $(".pk-count span"), empty = $(".pk-empty");
    var apply = function () {
      var shown = 0;
      tiles.forEach(function (t) {
        var ok = (state.type === "all" || t.dataset.type === state.type) && (state.region === "all" || t.dataset.region === state.region) &&
          (state.country === "all" || t.dataset.countries.split("|").indexOf(state.country) > -1) &&
          (!state.q || state.q.split(/\s+/).every(function (w) { return (" " + t.dataset.search).indexOf(" " + w) > -1; }));
        t.hidden = !ok;
        t.className = t.className.replace(/\bs\d+\b/, "").trim();
        if (ok) { t.classList.add("s" + spans[shown % spans.length]); shown++; }
      });
      if (count) count.textContent = shown;
      if (empty) empty.hidden = shown > 0;
      if (hasGsap && !reduce) {
        gsap.fromTo(tiles.filter(function (t) { return !t.hidden; }), { autoAlpha: 0, y: 14 }, { autoAlpha: 1, y: 0, duration: 0.5, stagger: 0.03, ease: "power3.out" });
        ScrollTrigger.refresh();
      }
    };
    $$(".pk-filter").forEach(function (bar) {
      bar.addEventListener("click", function (e) {
        var b = e.target.closest("button");
        if (!b) return;
        $$("button", bar).forEach(function (x) { x.setAttribute("aria-pressed", String(x === b)); });
        state[bar.dataset.key] = b.dataset.v;
        apply();
      });
    });
    var keepUrl = function () {
      var u = new URLSearchParams(location.search);
      if (state.country !== "all") u.set("country", state.country); else u.delete("country");
      if (state.q) u.set("q", state.q); else u.delete("q");
      var s = u.toString();
      history.replaceState(null, "", location.pathname + (s ? "?" + s : "") + location.hash);
    };
    if (sel) sel.addEventListener("change", function () { state.country = sel.value; apply(); keepUrl(); });
    if (qIn) {
      var t;
      qIn.addEventListener("input", function () { clearTimeout(t); t = setTimeout(function () { state.q = norm(qIn.value); apply(); keepUrl(); }, 180); });
    }
    if (state.country !== "all" || state.q) apply();
  }

  function initFilm() {
    var v = $(".film video");
    if (v && "IntersectionObserver" in window) {
      new IntersectionObserver(function (es) {
        es.forEach(function (e) {
          if (e.isIntersecting && !reduce) { var p = v.play(); if (p && p.catch) p.catch(function () {}); } else v.pause();
        });
      }, { threshold: 0.3 }).observe(v);
    }
    var vb = $(".vb"), btn = $("[data-film]");
    if (!vb || !btn) return;
    var vv = $("video", vb);
    var close = function () {
      vb.classList.remove("on"); vv.pause();
      if (window.__lenis) window.__lenis.start();
      btn.focus();
    };
    btn.addEventListener("click", function () {
      if (!vv.getAttribute("src")) vv.setAttribute("src", btn.dataset.film);
      if (v) v.pause();
      vb.classList.add("on");
      if (window.__lenis) window.__lenis.stop();
      vv.currentTime = 0;
      var p = vv.play(); if (p && p.catch) p.catch(function () {});
      $(".x", vb).focus();
    });
    vb.addEventListener("click", function (e) { if (e.target === vb || e.target.closest(".x")) close(); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape" && vb.classList.contains("on")) close(); });
  }

  function initLightbox() {
    var lb = $(".lb");
    if (!lb) return;
    var img = $(".lb-img img", lb), cap = $(".lb-cap", lb), meta = $(".lb-meta", lb), last = null;
    var close = function () { lb.classList.remove("on"); if (window.__lenis) window.__lenis.start(); if (last) last.focus(); };
    document.addEventListener("click", function (e) {
      var t = e.target.closest("[data-full]");
      if (!t) return;
      last = t;
      img.src = t.dataset.full; img.alt = t.dataset.title || "";
      cap.textContent = t.dataset.title || ""; meta.textContent = t.dataset.meta || "";
      lb.classList.add("on");
      if (window.__lenis) window.__lenis.stop();
      $(".x", lb).focus();
    });
    lb.addEventListener("click", function (e) { if (!e.target.closest(".lb-img img")) close(); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape" && lb.classList.contains("on")) close(); });
  }

  function initFilter() {
    var bar = $(".pf-filter");
    if (!bar) return;
    var items = $$(".pf-grid > li");
    bar.addEventListener("click", function (e) {
      var b = e.target.closest("button");
      if (!b) return;
      $$("button", bar).forEach(function (x) { x.setAttribute("aria-pressed", String(x === b)); });
      var f = b.dataset.f;
      items.forEach(function (t) { t.hidden = !(f === "all" || t.dataset.cat.indexOf(f) > -1); });
      if (hasGsap && !reduce) {
        gsap.fromTo(items.filter(function (t) { return !t.hidden; }), { autoAlpha: 0, y: 16 }, { autoAlpha: 1, y: 0, duration: 0.6, stagger: 0.04, ease: "power3.out" });
        ScrollTrigger.refresh();
      }
    });
  }

  function fitFootWord() {
    var w = $(".foot-word");
    if (!w) return;
    var fit = function () {
      w.style.fontSize = "";
      var box = w.clientWidth, span = document.createElement("span");
      span.style.cssText = "display:inline-block;white-space:nowrap";
      span.textContent = w.dataset.text || (w.dataset.text = w.textContent.trim());
      w.textContent = ""; w.appendChild(span);
      w.style.fontSize = parseFloat(getComputedStyle(w).fontSize) * box / span.offsetWidth * 0.995 + "px";
    };
    fit();
    if (document.fonts) document.fonts.ready.then(fit);
    var t; window.addEventListener("resize", function () { clearTimeout(t); t = setTimeout(fit, 200); });
  }

  /* split-flap board for the visa list */
  function initBoard(animated) {
    var board = $(".board");
    if (!board) return;
    var glyphs = "ABCDEFGHIJKLMNOPRSTUVWXYZ";
    var rows = $$("[data-flaps]", board);
    var size = rows.reduce(function (n, r) { return Math.max(n, r.dataset.flaps.length); }, 0);
    var built = rows.map(function (r) {
      var txt = r.dataset.flaps.toUpperCase().padEnd(size, " ");
      r.textContent = ""; r.setAttribute("aria-label", r.dataset.flaps);
      return txt.split("").map(function (ch) {
        var c = document.createElement("div");
        c.className = "flap"; c.setAttribute("aria-hidden", "true");
        c.innerHTML = '<span class="top"></span><span class="bot"></span><span class="fl-t"></span><span class="fl-b"></span>';
        r.appendChild(c);
        var p = { top: c.children[0], bot: c.children[1], ft: c.children[2], fb: c.children[3] };
        var start = animated ? " " : ch;
        p.top.textContent = p.bot.textContent = p.ft.textContent = p.fb.textContent = start;
        return { p: p, target: ch, cur: start };
      });
    });
    if (!animated) return;
    function flip(cell, next, dur) {
      var p = cell.p, old = cell.cur;
      p.top.textContent = next; p.ft.textContent = old; p.bot.textContent = old; p.fb.textContent = next;
      var a = p.ft.animate([{ transform: "rotateX(0deg)" }, { transform: "rotateX(-90deg)" }], { duration: dur, easing: "ease-in", fill: "forwards" });
      a.onfinish = function () {
        var b = p.fb.animate([{ transform: "rotateX(90deg)" }, { transform: "rotateX(0deg)" }], { duration: dur, easing: "ease-out", fill: "forwards" });
        b.onfinish = function () { p.bot.textContent = next; };
      };
      cell.cur = next;
    }
    function run(cell, delay) {
      var steps = cell.target === " " ? 0 : 2 + Math.floor(Math.random() * 4), i = 0;
      var tick = function () {
        if (i < steps) { flip(cell, glyphs[Math.floor(Math.random() * glyphs.length)], 45); i++; setTimeout(tick, 100); }
        else if (cell.cur !== cell.target) flip(cell, cell.target, 60);
      };
      setTimeout(tick, delay);
    }
    ScrollTrigger.create({ trigger: board, start: "top 75%", once: true, onEnter: function () {
      built.forEach(function (cells, r) { cells.forEach(function (cell, c) { run(cell, r * 150 + c * 40); }); });
    } });
  }
})();
