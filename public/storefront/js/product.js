/*!
 * Radharani Jewellery Works - product detail page (/shop/{slug})
 * Everything is rendered from RJ_DATA, so the ERP only has to supply data.
 */
(function () {
  "use strict";
  var RJ = window.RJ,
    D = RJ.data,
    doc = document,
    esc = RJ.esc,
    inr = RJ.inr;
  var $ = function (s) {
    return doc.querySelector(s);
  };
  var root = $("[data-pdp]");
  var id = D.currentId;
  var p = id ? RJ.product(id) : null;

  /* ---------- not found ---------- */
  if (!p) {
    root.innerHTML =
      '<nav class="crumbs" aria-label="Breadcrumb"><a href="' + RJ.urls.home + '">Home</a><i class="ph ph-caret-right"></i><a href="' + RJ.shop() + '">Jewellery</a></nav>' +
      '<section class="missing"><i class="ph ph-diamond"></i><h1>This piece is no longer online</h1>' +
      "<p>It may have been sold in the showroom, or the link is incomplete. Here is everything we have right now.</p>" +
      '<a class="btn btn--solid" href="' + RJ.shop() + '">Browse all jewellery</a></section>';
    doc.title = "Piece not found | Radharani Jewellery Works";
    RJ.boot({ smooth: false });
    return;
  }

  var cat = RJ.find(D.categories, p.category);
  var col = p.collection ? RJ.find(D.collections, p.collection) : null;
  var pur = D.purities[p.purity];
  var pr = RJ.price(p);
  var metalWord = pur.metal.charAt(0).toUpperCase() + pur.metal.slice(1);
  /* one listing is one physical piece, so its size is fixed: shown as a
     single, already-selected chip */
  var sizeSpec = p.sizeType && p.size ? D.sizes[p.sizeType] : null;
  if (sizeSpec) sizeSpec = { label: sizeSpec.label, options: [p.size] };
  var selectedSize = sizeSpec ? p.size : "";
  RJ.recent(p.id);

  doc.title = p.name + " | " + pur.label + " | Radharani Jewellery Works";
  var md = doc.querySelector('meta[name="description"]');
  if (md) md.setAttribute("content", p.description);

  /* gallery: exactly one frame per stored photo, in the order set in the
     ERP (first = cover). No invented close-up crops: what the customer
     sees is what the shop photographed. */
  var photos = p.images.length ? p.images : [""];
  var frames = photos.map(function (src, i) {
    return {
      src: RJ.img(src, { w: 1200, h: 1200 }),
      alt: i ? p.name + ", view " + (i + 1) : p.name,
    };
  });
  var thumbSrc = function (i) {
    return RJ.img(photos[i], { w: 180, h: 180 });
  };

  var tag = p.isNew ? "New" : p.isBestseller ? "Bestseller" : "";
  var pageUrl = location.href.split("#")[0];
  var enquiryText = function () {
    return (
      "Namaste Radharani Jewellery. I'd like to know more about this piece:\n" +
      p.name +
      " (" +
      p.code +
      ")\n" +
      pur.label +
      ", net " +
      RJ.grams(p.netWt) +
      (selectedSize ? "\n" + sizeSpec.label + ": " + selectedSize : "") +
      "\nShown price: " +
      inr(pr.total) +
      "\n" +
      pageUrl
    );
  };
  var viewingText = function () {
    return (
      "Namaste Radharani Jewellery. I'd like to see the " +
      p.name +
      " (" +
      p.code +
      ") in the showroom" +
      (selectedSize
        ? ", " + sizeSpec.label.toLowerCase() + " " + selectedSize
        : "") +
      ". Which day and time would suit?"
    );
  };

  /* ---------- render ---------- */
  root.innerHTML =
    '<nav class="crumbs" aria-label="Breadcrumb">' +
    '<a href="' + RJ.urls.home + '">Home</a><i class="ph ph-caret-right"></i>' +
    '<a href="' +
    RJ.shop({ category: cat.slug }) +
    '">' +
    esc(cat.name) +
    '</a><i class="ph ph-caret-right"></i>' +
    '<span aria-current="page">' +
    esc(p.name) +
    "</span>" +
    "</nav>" +
    '<article class="pdp" itemscope itemtype="https://schema.org/Product">' +
    '<div class="gallery">' +
    '<div class="stage" data-stage>' +
    (tag ? '<span class="tag">' + tag + "</span>" : "") +
    '<button class="save" data-save="' +
    esc(p.id) +
    '" aria-label="Save ' +
    esc(p.name) +
    '" aria-pressed="false"><i class="ph ph-heart"></i><i class="ph-fill ph-heart"></i></button>' +
    frames
      .map(function (f, i) {
        return (
          '<figure class="media frame' +
          (i === 0 ? " is-on" : "") +
          '"><img src="' +
          f.src +
          '" alt="' +
          esc(f.alt) +
          '"' +
          (i ? ' loading="lazy"' : ' fetchpriority="high" itemprop="image"') +
          "></figure>"
        );
      })
      .join("") +
    '<span class="stage__hint"><i class="ph ph-magnifying-glass-plus"></i>Hover to zoom</span>' +
    "</div>" +
    '<div class="thumbs" role="tablist" aria-label="Photos">' +
    frames
      .map(function (f, i) {
        return (
          '<button class="media thumb' +
          (i === 0 ? " is-on" : "") +
          '" data-frame="' +
          i +
          '" role="tab" aria-selected="' +
          (i === 0) +
          '" aria-label="Photo ' +
          (i + 1) +
          '"><img loading="lazy" src="' +
          thumbSrc(i) +
          '" alt=""></button>'
        );
      })
      .join("") +
    "</div>" +
    "</div>" +
    '<div class="info">' +
    '<div class="info__top">' +
    '<div class="info__eyebrow">' +
    (col
      ? '<a class="eyebrow" href="' +
        RJ.shop({ collection: col.slug }) +
        '">' +
        esc(col.name) +
        " collection</a>"
      : '<a class="eyebrow" href="' +
        RJ.shop({ category: cat.slug }) +
        '">' +
        esc(cat.name) +
        "</a>") +
    "</div>" +
    '<h1 itemprop="name">' +
    esc(p.name) +
    "</h1>" +
    '<div class="info__code"><span>Code <span itemprop="sku">' +
    esc(p.code) +
    '</span></span><span class="stock"><i class="ph ph-storefront"></i>At the showroom</span></div>' +
    "</div>" +
    '<div class="price">' +
    '<div class="price__row"><b data-price>' +
    inr(pr.total) +
    "</b><small>incl. making charges</small></div>" +
    "<small>Worked out at today's " +
    esc(pur.metal) +
    " rate of " +
    inr(pr.rate) +
    "/g. The final price follows the rate on the day you buy.</small>" +
    '<button type="button" data-open-breakup><i class="ph ph-receipt"></i>See the price breakup</button>' +
    "</div>" +
    '<div class="facts">' +
    '<div class="fact"><small>Purity</small><span>' +
    esc(pur.label) +
    "</span></div>" +
    '<div class="fact"><small>Net weight</small><span>' +
    RJ.grams(p.netWt) +
    "</span></div>" +
    '<div class="fact"><small>Stones</small><span>' +
    esc(p.stones) +
    "</span></div>" +
    "</div>" +
    (sizeSpec
      ? '<div class="sizes">' +
        '<div class="sizes__head"><span>' +
        esc(sizeSpec.label) +
        "</span><small>Other sizes made to order.</small></div>" +
        '<div class="sizes__opts" role="group" aria-label="' +
        esc(sizeSpec.label) +
        '">' +
        sizeSpec.options
          .map(function (s) {
            return (
              '<button type="button" class="chip" data-size="' +
              esc(s) +
              '" aria-pressed="true" disabled>' +
              esc(s) +
              "</button>"
            );
          })
          .join("") +
        "</div>" +
        "</div>"
      : "") +
    '<div class="cta" data-cta>' +
    '<a class="btn btn--solid btn--block" data-enquire target="_blank" rel="noopener" data-magnetic><i class="ph ph-whatsapp-logo"></i>Enquire on WhatsApp</a>' +
    '<div class="cta__row">' +
    '<a class="btn btn--ghost" data-viewing target="_blank" rel="noopener"><i class="ph ph-calendar-check"></i>Book a viewing</a>' +
    '<button class="sq save" data-save="' +
    esc(p.id) +
    '" aria-label="Save this piece" aria-pressed="false"><i class="ph ph-heart"></i><i class="ph-fill ph-heart"></i></button>' +
    '<button class="sq" data-share aria-label="Share this piece"><i class="ph ph-share-network"></i></button>' +
    "</div>" +
    "</div>" +
    '<ul class="promise">' +
    '<li><i class="ph ph-seal-check"></i>BIS hallmarked with HUID</li>' +
    '<li><i class="ph ph-scales"></i>Weighed in front of you</li>' +
    '<li><i class="ph ph-arrows-clockwise"></i>Old gold accepted in exchange</li>' +
    '<li><i class="ph ph-gift"></i>Gift-wrapped at the counter</li>' +
    "</ul>" +
    '<div class="accs">' +
    '<div class="acc is-open"><button class="acc__btn" aria-expanded="true">Description <i class="ph ph-plus"></i></button><div class="acc__panel"><div class="acc__inner">' +
    '<p itemprop="description">' +
    esc(p.description) +
    "</p>" +
    (col && col.blurb
      ? "<p>Part of our " +
        esc(col.name) +
        " collection: " +
        esc(col.blurb.charAt(0).toLowerCase() + col.blurb.slice(1)) +
        "</p>"
      : "") +
    "</div></div></div>" +
    '<div class="acc"><button class="acc__btn" aria-expanded="false">Product details <i class="ph ph-plus"></i></button><div class="acc__panel"><div class="acc__inner"><dl class="spec">' +
    [
      ["Product code", p.code],
      ["Metal", metalWord],
      ["Purity", pur.label],
      ["Gross weight", RJ.grams(p.grossWt)],
      ["Net weight", RJ.grams(p.netWt)],
      ["Stones", p.stones],
      sizeSpec ? [sizeSpec.label, p.size] : null,
      ["Size", p.dims || "-"],
      ["Hallmark", p.hallmarked ? "BIS, with HUID" : "-"],
      ["Category", cat.name],
      ["Collection", col ? col.name : "-"],
    ]
      .filter(Boolean)
      .map(function (r) {
        return "<div><dt>" + esc(r[0]) + "</dt><dd>" + esc(r[1]) + "</dd></div>";
      })
      .join("") +
    "</dl></div></div></div>" +
    '<div class="acc" data-breakup><button class="acc__btn" aria-expanded="false">Price breakup <i class="ph ph-plus"></i></button><div class="acc__panel"><div class="acc__inner">' +
    '<table class="breakup"><tbody>' +
    "<tr><td>" +
    metalWord +
    " rate today</td><td>" +
    inr(pr.rate) +
    "/g</td></tr>" +
    "<tr><td>Net weight</td><td>" +
    RJ.grams(pr.weight) +
    "</td></tr>" +
    '<tr class="sub"><td>' +
    metalWord +
    " value</td><td>" +
    inr(pr.metal) +
    "</td></tr>" +
    "<tr><td>Making charges" +
    (pr.makingLabel === "flat" ? "" : " (" + esc(pr.makingLabel) + ")") +
    "</td><td>" +
    inr(pr.making) +
    "</td></tr>" +
    (pr.stones
      ? "<tr><td>Stones (" +
        esc(p.stones) +
        ")</td><td>" +
        inr(pr.stones) +
        "</td></tr>"
      : "") +
    (pr.huid
      ? "<tr><td>HUID hallmarking</td><td>" + inr(pr.huid) + "</td></tr>"
      : "") +
    (pr.discount > 0
      ? "<tr><td>Discount</td><td>-" + inr(pr.discount) + "</td></tr>"
      : "") +
    '<tr class="sub"><td>Subtotal</td><td>' +
    inr(pr.sub) +
    "</td></tr>" +
    '<tr class="total"><td>Total</td><td>' +
    inr(pr.total) +
    "</td></tr>" +
    "</tbody></table>" +
    '<p class="note">The same lines appear on your bill.' +
    (D.rates.updated
      ? " Today's rate was last updated at " + esc(D.rates.updated) + "."
      : "") +
    "</p>" +
    "</div></div></div>" +
    '<div class="acc"><button class="acc__btn" aria-expanded="false">Care <i class="ph ph-plus"></i></button><div class="acc__panel"><div class="acc__inner">' +
    "<p>Put jewellery on after perfume and creams, and take it off before bathing or swimming. Store pieces separately in the pouch they came in.</p>" +
    "<p>Bring it in any time for a free clean and check of the clasps and settings.</p>" +
    "</div></div></div>" +
    /* SAMPLE POLICY TEXT: confirm the exact exchange and buyback terms with the store before launch */
    '<div class="acc"><button class="acc__btn" aria-expanded="false">Exchange and buyback <i class="ph ph-plus"></i></button><div class="acc__panel"><div class="acc__inner">' +
    "<p>Gold bought from us can be exchanged against a new piece at the day's rate, with the purity tested in front of you. Making charges are not refunded.</p>" +
    '<p><a class="link" href="' + RJ.urls.home + '#exchange">How old gold exchange works <i class="ph ph-arrow-right"></i></a></p>' +
    "</div></div></div>" +
    "</div>" +
    "</div>" +
    "</article>";

  /* ---------- related rows ---------- */
  var rowHTML = function (title, items) {
    if (!items.length) return "";
    return (
      '<section class="more"><div class="wrap"><div class="head-row"><h2 class="h-lg" data-split>' +
      title +
      "</h2></div>" +
      '<div class="row4">' +
      items
        .map(function (x) {
          return RJ.card(x);
        })
        .join("") +
      "</div></div></section>"
    );
  };
  var related = D.products
    .filter(function (x) {
      return (
        x.id !== p.id &&
        ((col && x.collection === col.slug) || x.category === p.category)
      );
    })
    .sort(function (a, b) {
      return (
        (b.category === p.category ? 1 : 0) -
        (a.category === p.category ? 1 : 0)
      );
    })
    .slice(0, 8);
  if (related.length < 4)
    related = related
      .concat(
        D.products.filter(function (x) {
          return (
            x.id !== p.id &&
            related.indexOf(x) < 0 &&
            (x.occasions || []).some(function (o) {
              return (p.occasions || []).indexOf(o) > -1;
            })
          );
        }),
      )
      .slice(0, 8);
  var recent = RJ.recent()
    .filter(function (x) {
      return x !== p.id;
    })
    .map(RJ.product)
    .filter(Boolean)
    .slice(0, 8);
  $("[data-more]").innerHTML =
    rowHTML(
      col
        ? "More from <em>" + esc(col.name) + "</em>"
        : "You may also <em>like</em>",
      related,
    ) + rowHTML("Recently <em>viewed</em>", recent);

  /* ---------- mobile dock ---------- */
  var dock = $("[data-dock]");
  dock.innerHTML =
    "<div><b>" +
    inr(pr.total) +
    "</b><small>" +
    esc(pur.label) +
    ", " +
    RJ.grams(p.netWt) +
    "</small></div>" +
    '<a class="btn btn--solid" data-enquire target="_blank" rel="noopener"><i class="ph ph-whatsapp-logo"></i>Enquire</a>';

  /* ---------- enquiry links (refresh when a size is picked) ---------- */
  var paintLinks = function () {
    doc.querySelectorAll("[data-enquire]").forEach(function (a) {
      a.href = RJ.wa(enquiryText());
    });
    doc.querySelectorAll("[data-viewing]").forEach(function (a) {
      a.href = RJ.wa(viewingText());
    });
  };
  paintLinks();
  doc.querySelectorAll("[data-size]").forEach(function (b) {
    b.addEventListener("click", function () {
      var on = b.getAttribute("aria-pressed") !== "true";
      doc.querySelectorAll("[data-size]").forEach(function (x) {
        x.setAttribute("aria-pressed", "false");
      });
      b.setAttribute("aria-pressed", on ? "true" : "false");
      selectedSize = on ? b.getAttribute("data-size") : "";
      paintLinks();
    });
  });

  /* ---------- gallery: thumbs, hover zoom, swipe ---------- */
  var stage = $("[data-stage]");
  var frameEls = stage.querySelectorAll(".frame");
  var thumbs = doc.querySelectorAll(".thumb");
  var cur = 0;
  var show = function (n) {
    n = (n + frameEls.length) % frameEls.length;
    if (n === cur) return;
    var prev = frameEls[cur],
      next = frameEls[n];
    thumbs[cur].classList.remove("is-on");
    thumbs[cur].setAttribute("aria-selected", "false");
    thumbs[n].classList.add("is-on");
    thumbs[n].setAttribute("aria-selected", "true");
    cur = n;
    stage.classList.remove("is-zoom");
    if (window.gsap && !RJ.reduce) {
      next.classList.add("is-on");
      gsap.fromTo(
        next,
        { autoAlpha: 0, scale: 1.04 },
        {
          autoAlpha: 1,
          scale: 1,
          duration: 0.7,
          ease: "expo.out",
          onComplete: function () {
            prev.classList.remove("is-on");
            gsap.set(prev, { clearProps: "all" });
          },
        },
      );
    } else {
      prev.classList.remove("is-on");
      next.classList.add("is-on");
    }
  };
  thumbs.forEach(function (t) {
    t.addEventListener("click", function () {
      show(parseInt(t.getAttribute("data-frame"), 10));
    });
  });
  if (window.matchMedia("(hover: hover) and (pointer: fine)").matches) {
    stage.addEventListener("mousemove", function (e) {
      if (e.target.closest(".save")) {
        stage.classList.remove("is-zoom");
        return;
      }
      var r = stage.getBoundingClientRect();
      var img = frameEls[cur].querySelector("img");
      img.style.transformOrigin =
        ((e.clientX - r.left) / r.width) * 100 +
        "% " +
        ((e.clientY - r.top) / r.height) * 100 +
        "%";
      stage.classList.add("is-zoom");
    });
    stage.addEventListener("mouseleave", function () {
      stage.classList.remove("is-zoom");
    });
  }
  var sx = null;
  stage.addEventListener(
    "touchstart",
    function (e) {
      sx = e.touches[0].clientX;
    },
    { passive: true },
  );
  stage.addEventListener("touchend", function (e) {
    if (sx === null) return;
    var dx = e.changedTouches[0].clientX - sx;
    if (Math.abs(dx) > 40) show(cur + (dx < 0 ? 1 : -1));
    sx = null;
  });
  doc.addEventListener("keydown", function (e) {
    if (e.target.closest("input, textarea")) return;
    if (e.key === "ArrowRight") show(cur + 1);
    if (e.key === "ArrowLeft") show(cur - 1);
  });

  /* ---------- accordions, breakup shortcut ---------- */
  RJ.accordion(root);
  $("[data-open-breakup]").addEventListener("click", function () {
    var acc = $("[data-breakup]");
    if (!acc.classList.contains("is-open"))
      acc.querySelector(".acc__btn").click();
    setTimeout(function () {
      var y = acc.getBoundingClientRect().top + window.scrollY - 170;
      if (window.gsap && window.ScrollToPlugin && !RJ.reduce)
        gsap.to(window, { duration: 0.9, ease: "expo.inOut", scrollTo: y });
      else window.scrollTo(0, y);
    }, 120);
  });

  /* ---------- share ---------- */
  var toast = $("[data-toast]");
  var say = function (msg) {
    toast.innerHTML = '<i class="ph ph-check-circle"></i>' + esc(msg);
    toast.classList.add("is-in");
    clearTimeout(say.t);
    say.t = setTimeout(function () {
      toast.classList.remove("is-in");
    }, 2200);
  };
  $("[data-share]").addEventListener("click", function () {
    var data = {
      title: p.name + " | Radharani Jewellery Works",
      text: p.name + ", " + pur.label,
      url: pageUrl,
    };
    if (navigator.share) {
      navigator.share(data).catch(function () {});
      return;
    }
    if (navigator.clipboard)
      navigator.clipboard.writeText(pageUrl).then(
        function () {
          say("Link copied");
        },
        function () {
          say(pageUrl);
        },
      );
    else say(pageUrl);
  });
  doc.addEventListener("rj:saved", function (e) {
    say(
      e.detail.indexOf(p.id) > -1
        ? "Saved to your list"
        : "Removed from your list",
    );
  });

  /* ---------- structured data for search engines ---------- */
  var ld = doc.createElement("script");
  ld.type = "application/ld+json";
  ld.textContent = JSON.stringify({
    "@context": "https://schema.org",
    "@type": "Product",
    name: p.name,
    sku: p.code,
    description: p.description,
    image: [RJ.img(p.images[0], { w: 1200, h: 1200 })],
    category: cat.name,
    material: pur.label,
    brand: { "@type": "Brand", name: "Radharani Jewellery Works" },
    offers: {
      "@type": "Offer",
      priceCurrency: "INR",
      price: pr.total,
      availability: "https://schema.org/InStoreOnly",
      url: pageUrl,
    },
  });
  doc.head.appendChild(ld);

  /* ---------- motion ---------- */
  RJ.boot({ smooth: false }, function (ctx) {
    /* mobile dock appears once the main enquiry button scrolls away */
    var cta = $("[data-cta]");
    var dockOn = function (on) {
      dock.classList.toggle("is-in", on);
      dock.setAttribute("aria-hidden", on ? "false" : "true");
    };
    if (ctx.gsap) {
      ScrollTrigger.create({
        trigger: cta,
        start: "bottom top+=80",
        endTrigger: ".footer",
        end: "top bottom",
        onToggle: function (s) {
          dockOn(s.isActive);
        },
      });
      gsap.from(".stage", {
        clipPath: "inset(8% 8% 8% 8% round 22px)",
        duration: 1.3,
        ease: "expo.inOut",
        delay: 0.3,
      });
      gsap.from(".stage .frame.is-on img", {
        scale: 1.15,
        duration: 1.8,
        ease: "expo.out",
        delay: 0.3,
      });
      gsap.from(".thumb", {
        y: 16,
        autoAlpha: 0,
        duration: 0.8,
        ease: "expo.out",
        stagger: 0.06,
        delay: 0.6,
      });
      gsap.from(".info > *", {
        y: 30,
        autoAlpha: 0,
        duration: 1,
        ease: "expo.out",
        stagger: 0.07,
        delay: 0.4,
      });
      var o = { v: 0 },
        priceEl = $("[data-price]");
      gsap.to(o, {
        v: pr.total,
        duration: 1.4,
        delay: 0.6,
        ease: "power2.out",
        onUpdate: function () {
          priceEl.textContent = inr(o.v);
        },
      });
      gsap.utils.toArray(".row4").forEach(function (r) {
        gsap.from(r.children, {
          y: 40,
          autoAlpha: 0,
          duration: 1,
          ease: "expo.out",
          stagger: 0.07,
          scrollTrigger: { trigger: r, start: "top 88%", once: true },
        });
      });
    } else {
      var io = new IntersectionObserver(function (en) {
        dockOn(!en[0].isIntersecting && en[0].boundingClientRect.top < 0);
      });
      io.observe(cta);
    }
  });
})();
