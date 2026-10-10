/*!
 * Radharani Jewellery Works - core (loaded on every page, after data.js and gsap-bundle.js)
 *
 *  - renders the shared header, mobile drawer and footer into #rj-header / #rj-footer
 *  - helpers: prices, image URLs, WhatsApp links, product cards
 *  - saved pieces (per browser), search, image fallbacks
 *  - page-to-page curtain transition
 *  - RJ.boot(): shared motion (smooth scroll optional, parallax, reveals, magnetic buttons)
 *
 * RJ_DATA comes from the ERP (App\Services\StorefrontCatalog): pieces,
 * categories, collections, today's rates, shop details and page URLs.
 * Prices arrive already worked out (PricingService, no GST); nothing here
 * computes a price.
 */
(function () {
  "use strict";

  var D = window.RJ_DATA;
  var U = D.urls;

  /* ------------------------------------------------------------------
     SETTINGS: shop details used everywhere (ERP > Website > Settings)
  ------------------------------------------------------------------ */
  var CONFIG = D.config;
  D.budgets.forEach(function (b) {
    if (b.max == null) b.max = Infinity;
  });
  var doc = document;
  var body = doc.body;
  var reduce = !!(
    window.matchMedia &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches
  );
  body.classList.remove("no-js");

  /* ================= helpers ================= */
  var esc = function (s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      }[c];
    });
  };
  var inr = function (n) {
    return "₹" + Math.round(n).toLocaleString("en-IN");
  };
  var grams = function (n) {
    return n.toFixed(2) + " g";
  };
  var wa = function (msg) {
    return (
      "https://wa.me/" + CONFIG.whatsapp + "?text=" + encodeURIComponent(msg)
    );
  };
  /* an uploaded photo URL is used as it is; Unsplash photos (the site's own
     marketing images and demo data) get the design's crop and size */
  var UNSPLASH = "https://images.unsplash.com/photo-";
  var isUnsplash = function (id) {
    return !!id && (id.indexOf(UNSPLASH) === 0 || /^\d{10,}-[0-9a-f]+$/.test(id));
  };
  var img = function (id, o) {
    o = o || {};
    if (!id) return "data:,"; // no photo yet: fails to load, so the frame shows its empty state
    if (!isUnsplash(id)) return id;
    id = id.replace(UNSPLASH, "").split("?")[0];
    var u =
      "https://images.unsplash.com/photo-" +
      id +
      "?auto=format&fit=crop&q=" +
      (o.q || 78) +
      "&w=" +
      (o.w || 800);
    if (o.h) u += "&h=" + o.h;
    if (o.zoom)
      u +=
        "&crop=focalpoint&fp-x=" +
        (o.x || 0.5) +
        "&fp-y=" +
        (o.y || 0.5) +
        "&fp-z=" +
        o.zoom;
    return u;
  };
  var byId = {};
  D.products.forEach(function (p) {
    byId[p.id] = p;
  });
  var find = function (list, slug) {
    for (var i = 0; i < list.length; i++)
      if (list[i].slug === slug) return list[i];
    return null;
  };

  /* price breakdown worked out by the ERP: today's rate x net weight +
     making + stones (+ HUID, - discount) + GST. p.price is its total. */
  var price = function (p) {
    return p.pr;
  };

  var productUrl = function (p) {
    return U.product.replace("__ID__", encodeURIComponent(p.id));
  };
  var enquiryMsg = function (p, extra) {
    return (
      "Namaste Radharani Jewellery. I'd like to know more about the " +
      p.name +
      " (" +
      p.code +
      ")" +
      (extra || "") +
      "."
    );
  };

  /* shared product card */
  var card = function (p, o) {
    o = o || {};
    var pur = D.purities[p.purity];
    var tag = p.isNew ? "New" : p.isBestseller ? "Bestseller" : "";
    var w = o.w || 600;
    return (
      "" +
      '<article class="pcard" data-id="' +
      esc(p.id) +
      '">' +
      (tag ? '<span class="tag">' + tag + "</span>" : "") +
      '<button class="save" data-save="' +
      esc(p.id) +
      '" aria-label="Save ' +
      esc(p.name) +
      '" aria-pressed="false"><i class="ph ph-heart"></i><i class="ph-fill ph-heart"></i></button>' +
      '<a class="pcard__link" href="' +
      productUrl(p) +
      '">' +
      '<figure class="media zoom">' +
      '<img loading="lazy" src="' +
      img(p.images[0], { w: w, h: w }) +
      '" alt="' +
      esc(p.name) +
      '">' +
      /* hover shows the piece's second photo, if it has one */
      (p.images[1]
        ? '<img class="pcard__alt" loading="lazy" src="' +
          img(p.images[1], { w: w, h: w }) +
          '" alt="">'
        : "") +
      "</figure>" +
      "<h3>" +
      esc(p.name) +
      "</h3>" +
      '<p class="pcard__meta">' +
      esc(pur.label) +
      ", " +
      grams(p.netWt) +
      "</p>" +
      "</a>" +
      '<div class="pcard__foot">' +
      '<span class="pcard__price">' +
      inr(p.price) +
      "</span>" +
      '<a class="pcard__enq" href="' +
      wa(enquiryMsg(p)) +
      '" target="_blank" rel="noopener"><i class="ph ph-whatsapp-logo"></i>Enquire</a>' +
      "</div>" +
      "</article>"
    );
  };

  var shop = function (params) {
    var q = new URLSearchParams(params || {}).toString();
    return U.shop + (q ? "?" + q : "");
  };
  var mark = U.asset + "img/mark.png";

  /* ================= header / drawer / footer ================= */
  var page = body.getAttribute("data-page") || "home"; // home | shop | product
  var params = new URLSearchParams(location.search);
  var home = page === "home";
  var anchor = function (id) {
    return (home ? "" : U.home) + "#" + id;
  };

  /* categories ticked "in menu bar" sit in the bar (first five if none are);
     every category is in the "All jewellery" menu */
  /* top bar: the metals from the category tree, each opening its subcategories */
  var catLinks = (D.menu || [])
    .map(function (m) {
      var cur =
        page === "shop" && params.get("metal") === m.metal
          ? ' class="is-current" aria-current="page"'
          : "";
      return (
        '<div class="has-mega"><button type="button" aria-haspopup="true"' + cur + ">" +
        esc(m.label) + ' <i class="ph ph-caret-down"></i></button>' +
        '<div class="mega"><div class="wrap"><div><h4>' + esc(m.label) + "</h4>" +
        '<ul><li><a href="' + shop({ metal: m.metal }) + '">All ' + esc(m.label) + "</a></li>" +
        m.items
          .map(function (c) {
            return '<li><a href="' + shop({ category: c.slug }) + '">' + esc(c.name) + "</a></li>";
          })
          .join("") +
        "</ul></div></div></div></div>"
      );
    })
    .join("");
  var bridalCur =
    page === "shop" && params.get("occasion") === "bridal"
      ? ' class="is-current" aria-current="page"'
      : "";

  var list = function (items) {
    return (
      "<ul>" +
      items
        .map(function (i) {
          return '<li><a href="' + i[1] + '">' + i[0] + "</a></li>";
        })
        .join("") +
      "</ul>"
    );
  };

  /* today's rate per gram for each metal the shop has entered a rate for */
  var rateChips = [
    ["Gold", D.rates.gold],
    ["Silver", D.rates.silver],
    ["Platinum", D.rates.platinum],
  ]
    .filter(function (r) {
      return r[1] > 0;
    })
    .map(function (r) {
      return "<span>" + r[0] + " <b>" + inr(r[1]) + "/g</b></span>";
    })
    .join("");

  /* "By metal" menu: each purity on sale, silver as one metal link */
  var metalLinks = [];
  Object.keys(D.purities).forEach(function (k) {
    var pu = D.purities[k];
    if (pu.metal === "silver") return;
    metalLinks.push([
      pu.metal === "gold" ? "Gold " + k : pu.label,
      shop({ purity: k }),
    ]);
  });
  if (D.metals.indexOf("silver") > -1)
    metalLinks.push(["Silver", shop({ metal: "silver" })]);
  metalLinks.push(["All jewellery", shop()]);

  /* customer portal: "Sign in", or "My account" once signed in */
  var accountLabel = D.customer ? "My account" : "Sign in";

  var headerHTML =
    "" +
    '<header class="header">' +
    '<div class="util"><div class="wrap">' +
    '<div class="util__rate" title="Updated ' +
    esc(D.rates.updated) +
    '">' +
    rateChips +
    "</div>" +
    '<div class="util__links">' +
    '<a href="' +
    anchor("visit") +
    '"><i class="ph ph-map-pin"></i>Visit the showroom</a>' +
    '<a href="tel:' +
    CONFIG.tel +
    '"><i class="ph ph-phone"></i>' +
    CONFIG.phone +
    "</a>" +
    '<a href="' +
    U.account +
    '"><i class="ph ph-user-circle"></i>' +
    accountLabel +
    "</a>" +
    "</div>" +
    "</div></div>" +
    '<div class="bar"><div class="wrap">' +
    '<button class="icon-btn burger" aria-label="Open menu" aria-expanded="false"><i class="ph ph-list"></i></button>' +
    '<a href="' +
    U.home +
    '" class="brand" aria-label="Radharani Jewellery Works, home">' +
    '<img src="' +
    mark +
    '" alt="">' +
    '<span class="brand__word"><span class="brand__name">Radharani</span><span class="brand__sub">Jewellery Works</span></span>' +
    "</a>" +
    '<form class="search" data-search role="search" action="' +
    U.shop +
    '">' +
    '<label class="sr-only" for="q">Search jewellery</label>' +
    '<input id="q" name="q" type="search" placeholder="Search jhumkas, kada, mangalsutra" autocomplete="off" value="' +
    esc(page === "shop" ? params.get("q") || "" : "") +
    '">' +
    '<button type="submit" aria-label="Search"><i class="ph ph-magnifying-glass"></i></button>' +
    "</form>" +
    '<div class="actions">' +
    '<a class="icon-btn hide-sm" href="' +
    anchor("visit") +
    '" aria-label="Visit the showroom"><i class="ph ph-storefront"></i></a>' +
    '<a class="icon-btn hide-sm" href="tel:' +
    CONFIG.tel +
    '" aria-label="Call the showroom"><i class="ph ph-phone"></i></a>' +
    '<a class="icon-btn" href="' +
    U.account +
    '" aria-label="' +
    accountLabel +
    '" title="' +
    accountLabel +
    '"><i class="ph ph-user"></i></a>' +
    '<a class="icon-btn" href="' +
    shop({ saved: "1" }) +
    '" aria-label="Saved pieces"><i class="ph ph-heart"></i><span class="badge" data-saved-count>0</span></a>' +
    "</div>" +
    "</div></div>" +
    '<nav class="cats" aria-label="Categories"><div class="wrap">' +
    '<div class="has-mega">' +
    '<button type="button" aria-haspopup="true">All jewellery <i class="ph ph-caret-down"></i></button>' +
    '<div class="mega"><div class="wrap">' +
    (D.menu || [])
      .map(function (m) {
        return (
          "<div><h4>" + esc(m.label) + "</h4>" +
          list(
            [["All " + m.label, shop({ metal: m.metal })]].concat(
              m.items.map(function (c) {
                return [esc(c.name), shop({ category: c.slug })];
              }),
            ),
          ) +
          "</div>"
        );
      })
      .join("") +
    "<div><h4>By budget</h4>" +
    list(
      D.budgets.map(function (b) {
        return [b.label, shop({ budget: b.slug })];
      }),
    ) +
    "</div>" +
    "<div><h4>By collection</h4>" +
    list(
      D.collections.map(function (c) {
        return [c.name, shop({ collection: c.slug })];
      }),
    ) +
    "</div>" +
    '<a class="mega__feature" href="' +
    shop({ occasion: "bridal" }) +
    '">' +
    '<figure class="media zoom"><img loading="lazy" src="' +
    img("1733937108021-d5db469f2216", { w: 700 }) +
    '" alt="Bride wearing heavy gold jewellery"></figure>' +
    "<p>The bridal edit</p>" +
    "</a>" +
    "</div></div>" +
    "</div>" +
    catLinks +
    '<a href="' +
    shop({ occasion: "bridal" }) +
    '"' +
    bridalCur +
    ">Bridal</a>" +
    '<a href="' +
    anchor("gifting") +
    '">Gifting</a>' +
    '<a href="' +
    anchor("price") +
    '">How we price</a>' +
    '<a href="' +
    anchor("exchange") +
    '" class="is-accent">Old gold exchange</a>' +
    "</div></nav>" +
    "</header>" +
    '<div class="drawer" aria-hidden="true">' +
    '<div class="drawer__top">' +
    '<a href="' +
    U.home +
    '" class="brand"><img src="' +
    mark +
    '" alt=""><span class="brand__word"><span class="brand__name">Radharani</span><span class="brand__sub">Jewellery Works</span></span></a>' +
    '<button class="icon-btn" data-close aria-label="Close menu"><i class="ph ph-x"></i></button>' +
    "</div>" +
    '<form class="search" data-search role="search" action="' +
    U.shop +
    '">' +
    '<label class="sr-only" for="q2">Search jewellery</label>' +
    '<input id="q2" name="q" type="search" placeholder="Search jhumkas, kada, mangalsutra" autocomplete="off">' +
    '<button type="submit" aria-label="Search"><i class="ph ph-magnifying-glass"></i></button>' +
    "</form>" +
    '<nav aria-label="Mobile">' +
    '<a href="' +
    shop() +
    '">All jewellery <i class="ph ph-caret-right"></i></a>' +
    D.categories
      .map(function (c) {
        return (
          '<a href="' +
          shop({ category: c.slug }) +
          '">' +
          esc(c.name) +
          ' <i class="ph ph-caret-right"></i></a>'
        );
      })
      .join("") +
    (D.metals.indexOf("silver") > -1
      ? '<a href="' +
        shop({ metal: "silver" }) +
        '">Silver <i class="ph ph-caret-right"></i></a>'
      : "") +
    '<a href="' +
    shop({ occasion: "bridal" }) +
    '">Bridal <i class="ph ph-caret-right"></i></a>' +
    '<a href="' +
    anchor("exchange") +
    '">Old gold exchange <i class="ph ph-caret-right"></i></a>' +
    '<a href="' +
    anchor("visit") +
    '">Visit the showroom <i class="ph ph-caret-right"></i></a>' +
    '<a href="' +
    U.account +
    '">' +
    accountLabel +
    ' <i class="ph ph-caret-right"></i></a>' +
    "</nav>" +
    '<div class="drawer__foot">' +
    '<a class="btn btn--ghost" href="tel:' +
    CONFIG.tel +
    '"><i class="ph ph-phone"></i>Call ' +
    CONFIG.phone +
    "</a>" +
    "<span>" +
    esc(CONFIG.address) +
    ". Open " +
    esc(CONFIG.hours) +
    "</span>" +
    "</div>" +
    "</div>";

  var footerHTML =
    "" +
    '<footer class="footer"><div class="wrap">' +
    '<div class="join">' +
    "<div><h3>New designs every Friday, on WhatsApp.</h3><p>One message a week. Reply stop any time.</p></div>" +
    '<a class="btn btn--solid" href="' +
    wa("Please add me to your new designs list.") +
    '" target="_blank" rel="noopener" data-magnetic><i class="ph ph-whatsapp-logo"></i>Join the list</a>' +
    "</div>" +
    '<div class="fcols">' +
    '<div class="fbrand">' +
    '<img src="' +
    mark +
    '" alt="Radharani monogram">' +
    "<p>Radharani Jewellery Works. Family jewellers, hallmarked, weighed and priced at the day's rate.</p>" +
    '<div class="social">' +
    [
      ["instagram", "Instagram"],
      ["facebook", "Facebook"],
      ["youtube", "YouTube"],
    ]
      .filter(function (s) {
        return CONFIG[s[0]];
      })
      .map(function (s) {
        return (
          '<a href="' +
          esc(CONFIG[s[0]]) +
          '" target="_blank" rel="noopener" aria-label="' +
          s[1] +
          '"><i class="ph ph-' +
          s[0] +
          '-logo"></i></a>'
        );
      })
      .join("") +
    "</div>" +
    "</div>" +
    '<div class="fcol"><h4>Shop</h4>' +
    list([
      ["All jewellery", shop()],
      ["New arrivals", shop({ sort: "new" })],
      ["Bestsellers", shop({ sort: "popular" })],
      ["Bridal", shop({ occasion: "bridal" })],
      ["For men", shop({ for: "men" })],
      ["For kids", shop({ for: "kids" })],
    ]) +
    "</div>" +
    '<div class="fcol"><h4>Know your jewellery</h4>' +
    list([
      ["Today's gold rate", anchor("price")],
      ["How we price", anchor("price")],
      ["Hallmark and HUID", anchor("price")],
      ["Old gold exchange", anchor("exchange")],
    ]) +
    "</div>" +
    '<div class="fcol"><h4>Help</h4>' +
    list([
      ["Saved pieces", shop({ saved: "1" })],
      ["Book a visit", anchor("visit")],
      ["Call us", "tel:" + CONFIG.tel],
    ]) +
    "</div>" +
    '<div class="fcol"><h4>Visit</h4><ul><li>' +
    esc(CONFIG.address) +
    "</li><li>" +
    esc(CONFIG.hours) +
    '</li><li><a href="tel:' +
    CONFIG.tel +
    '">' +
    CONFIG.phone +
    '</a></li><li><a href="' +
    CONFIG.maps +
    '" target="_blank" rel="noopener">Get directions</a></li></ul></div>' +
    "</div>" +
    '<div class="popular"><b>Popular</b>' +
    [
      ["Gold jhumkas", { q: "jhumka" }],
      ["Temple necklace", { collection: "temple" }],
      ["Mangalsutra", { category: "mangalsutra" }],
      ["Men's chains", { for: "men", category: "chains" }],
      ["Rings under ₹25,000", { category: "rings", budget: "0-25000" }],
      ["Baby bracelets", { for: "kids" }],
      ["Silver gifts", { metal: "silver" }],
    ]
      .map(function (x) {
        return '<a href="' + shop(x[1]) + '">' + x[0] + "</a>";
      })
      .join("") +
    "</div>" +
    '<div class="legal"><span>&copy; ' +
    new Date().getFullYear() +
    ' Radharani Jewellery Works &middot; <a href="' +
    U.signIn +
    '">Sign in</a></span><nav><span>Prices are indicative. Final price follows weight and the day\'s rate.</span></nav></div>' +
    "</div></footer>";

  var slotH = doc.getElementById("rj-header");
  if (slotH) slotH.outerHTML = headerHTML;
  var slotF = doc.getElementById("rj-footer");
  if (slotF) slotF.outerHTML = footerHTML;
  if (!doc.querySelector(".curtain")) {
    body.insertAdjacentHTML(
      "afterbegin",
      '<div class="curtain" aria-hidden="true"><img src="' + mark + '" alt=""></div>',
    );
  }

  /* ================= drawer ================= */
  var burger = doc.querySelector(".burger");
  var drawer = doc.querySelector(".drawer");
  var setDrawer = function (open) {
    if (!drawer) return;
    drawer.classList.toggle("is-open", open);
    drawer.setAttribute("aria-hidden", open ? "false" : "true");
    burger.setAttribute("aria-expanded", open ? "true" : "false");
    body.classList.toggle("is-locked", open);
  };
  if (burger)
    burger.addEventListener("click", function () {
      setDrawer(true);
    });
  if (drawer)
    drawer.querySelector("[data-close]").addEventListener("click", function () {
      setDrawer(false);
    });

  /* ================= search goes to the listing page ================= */
  doc.querySelectorAll("[data-search]").forEach(function (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var q = (form.querySelector("input").value || "").trim();
      if (!q) {
        form.querySelector("input").focus();
        return;
      }
      go(shop({ q: q }));
    });
  });

  /* ================= [data-wa] links written in page HTML ================= */
  doc.querySelectorAll("[data-wa]").forEach(function (a) {
    a.href = wa(a.getAttribute("data-msg") || "Namaste Radharani Jewellery.");
    a.target = "_blank";
    a.rel = "noopener";
  });

  /* ================= image fallback (works for cards rendered later too) ================= */
  doc.addEventListener(
    "error",
    function (e) {
      var t = e.target;
      if (t && t.tagName === "IMG") {
        var m = t.closest(".media");
        if (m) m.classList.add("is-missing");
      }
    },
    true,
  );

  /* ================= saved pieces (this browser only) ================= */
  var KEY = "radharani-saved";
  var saved = [];
  try {
    saved = JSON.parse(localStorage.getItem(KEY) || "[]").filter(function (id) {
      return byId[id];
    });
  } catch (e) {
    saved = [];
  }
  var paintSaved = function () {
    doc.querySelectorAll("[data-save]").forEach(function (b) {
      var on = saved.indexOf(b.getAttribute("data-save")) > -1;
      b.classList.toggle("is-saved", on);
      b.setAttribute("aria-pressed", on ? "true" : "false");
    });
    doc.querySelectorAll("[data-saved-count]").forEach(function (badge) {
      badge.textContent = saved.length;
      badge.classList.toggle("is-on", saved.length > 0);
    });
  };
  doc.addEventListener("click", function (e) {
    var b = e.target.closest("[data-save]");
    if (!b) return;
    e.preventDefault();
    var id = b.getAttribute("data-save");
    var i = saved.indexOf(id);
    if (i > -1) saved.splice(i, 1);
    else saved.push(id);
    try {
      localStorage.setItem(KEY, JSON.stringify(saved));
    } catch (err) {}
    paintSaved();
    if (window.gsap && !reduce)
      gsap.fromTo(
        b,
        { scale: 0.7 },
        { scale: 1, duration: 0.6, ease: "elastic.out(1,0.4)" },
      );
    doc.dispatchEvent(new CustomEvent("rj:saved", { detail: saved.slice() }));
  });

  /* recently viewed (this browser only) */
  var RKEY = "radharani-recent";
  var recent = function (addId) {
    var r = [];
    try {
      r = JSON.parse(localStorage.getItem(RKEY) || "[]");
    } catch (e) {
      r = [];
    }
    r = r.filter(function (id) {
      return byId[id];
    });
    if (addId) {
      r = [addId]
        .concat(
          r.filter(function (id) {
            return id !== addId;
          }),
        )
        .slice(0, 12);
      try {
        localStorage.setItem(RKEY, JSON.stringify(r));
      } catch (e) {}
    }
    return r;
  };

  /* ================= page curtain transition ================= */
  var curtain = doc.querySelector(".curtain");
  function go(href) {
    if (!window.gsap || reduce || !curtain) {
      location.href = href;
      return;
    }
    curtain.classList.remove("is-gone");
    gsap.fromTo(
      curtain,
      { yPercent: 100 },
      {
        yPercent: 0,
        duration: 0.55,
        ease: "expo.inOut",
        onComplete: function () {
          location.href = href;
        },
      },
    );
  }
  doc.addEventListener("click", function (e) {
    var a = e.target.closest("a[href]");
    if (
      !a ||
      e.defaultPrevented ||
      e.button !== 0 ||
      e.metaKey ||
      e.ctrlKey ||
      e.shiftKey ||
      e.altKey
    )
      return;
    if (a.target === "_blank" || a.hasAttribute("download")) return;
    var href = a.getAttribute("href");
    if (
      !href ||
      href.charAt(0) === "#" ||
      /^(tel:|mailto:|https?:)/i.test(href)
    )
      return;
    var url = new URL(a.href, location.href);
    if (
      url.pathname === location.pathname &&
      url.search === location.search &&
      url.hash
    )
      return; // same page anchor
    e.preventDefault();
    setDrawer(false);
    go(a.href);
  });
  window.addEventListener("pageshow", function (e) {
    if (e.persisted && curtain) {
      curtain.classList.add("is-gone");
      if (window.gsap) gsap.set(curtain, { yPercent: -100 });
    }
  });

  /* ================= public API ================= */
  var RJ = (window.RJ = {
    config: CONFIG,
    data: D,
    urls: U,
    isUnsplash: isUnsplash,
    reduce: reduce,
    esc: esc,
    inr: inr,
    grams: grams,
    wa: wa,
    img: img,
    price: price,
    find: find,
    card: card,
    shop: shop,
    productUrl: productUrl,
    enquiryMsg: enquiryMsg,
    product: function (id) {
      return byId[id];
    },
    saved: function () {
      return saved.slice();
    },
    paintSaved: paintSaved,
    recent: recent,
    go: go,
    setDrawer: setDrawer,
    smoother: null,
  });
  paintSaved();

  /* ================= shared motion: call RJ.boot() after the page has rendered its content ================= */
  RJ.boot = function (opts, pageFn) {
    opts = opts || {};
    pageFn = pageFn || function () {};
    paintSaved();
    var header = doc.querySelector(".header");

    if (!window.gsap || reduce) {
      if (curtain) curtain.classList.add("is-gone");
      if (!opts.keepLoader) {
        var l = doc.querySelector(".loader");
        if (l) l.remove();
      }
      pageFn({ gsap: false, reduce: true });
      return;
    }

    gsap.registerPlugin(
      ScrollTrigger,
      ScrollSmoother,
      SplitText,
      ScrollToPlugin,
    );

    if (opts.smooth) {
      body.classList.add("smooth-on");
      RJ.smoother = ScrollSmoother.create({
        wrapper: "#smooth-wrapper",
        content: "#smooth-content",
        smooth: 1.1,
        effects: true,
        smoothTouch: 0.1,
      });
    }

    /* same-page anchors glide */
    doc.addEventListener("click", function (e) {
      var a = e.target.closest('a[href*="#"]');
      if (!a) return;
      var url = new URL(a.href, location.href);
      if (url.pathname !== location.pathname || !url.hash) return;
      var target = doc.querySelector(url.hash);
      if (!target) return;
      e.preventDefault();
      setDrawer(false);
      if (RJ.smoother) RJ.smoother.scrollTo(target, true, "top 90px");
      else
        gsap.to(window, {
          duration: 1,
          ease: "expo.inOut",
          scrollTo: { y: target, offsetY: 90 },
        });
    });
    /* arriving from another page with a #hash */
    if (location.hash && doc.querySelector(location.hash)) {
      var t = doc.querySelector(location.hash);
      setTimeout(function () {
        if (RJ.smoother) RJ.smoother.scrollTo(t, false, "top 90px");
        else
          window.scrollTo(
            0,
            t.getBoundingClientRect().top + window.scrollY - 90,
          );
      }, 60);
    }

    /* header hides on the way down, returns on the way up */
    ScrollTrigger.create({
      start: 0,
      end: "max",
      onUpdate: function (self) {
        var y = self.scroll();
        header.classList.toggle("is-raised", y > 10);
        var hide = y > 400 && self.direction === 1 && !opts.keepHeader;
        header.classList.toggle("is-hidden", hide);
        body.classList.toggle("head-hidden", hide);
        doc.dispatchEvent(
          new CustomEvent("rj:scroll", {
            detail: { y: y, dir: self.direction },
          }),
        );
      },
    });

    /* photo parallax inside frames */
    gsap.utils.toArray("[data-parallax]").forEach(function (im) {
      gsap.fromTo(
        im,
        { yPercent: -6 },
        {
          yPercent: 6,
          ease: "none",
          scrollTrigger: {
            trigger: im.closest(".media"),
            start: "top bottom",
            end: "bottom top",
            scrub: true,
          },
        },
      );
    });
    /* headings: words rise into place */
    gsap.utils.toArray("[data-split]").forEach(function (el) {
      var split = new SplitText(el, { type: "words", wordsClass: "sw" });
      gsap.from(split.words, {
        yPercent: 60,
        autoAlpha: 0,
        duration: 1.1,
        ease: "expo.out",
        stagger: 0.06,
        scrollTrigger: { trigger: el, start: "top 88%", once: true },
      });
    });
    /* rise-in */
    gsap.utils.toArray("[data-rise]").forEach(function (el) {
      gsap.from(el, {
        y: 50,
        autoAlpha: 0,
        duration: 1.1,
        ease: "expo.out",
        scrollTrigger: { trigger: el, start: "top 90%", once: true },
      });
    });
    /* magnetic buttons (mouse only) */
    if (window.matchMedia("(hover: hover) and (pointer: fine)").matches) {
      doc.querySelectorAll("[data-magnetic]").forEach(function (btn) {
        var xTo = gsap.quickTo(btn, "x", {
          duration: 0.6,
          ease: "elastic.out(1, 0.4)",
        });
        var yTo = gsap.quickTo(btn, "y", {
          duration: 0.6,
          ease: "elastic.out(1, 0.4)",
        });
        btn.addEventListener("mousemove", function (e) {
          var r = btn.getBoundingClientRect();
          xTo((e.clientX - r.left - r.width / 2) * 0.22);
          yTo((e.clientY - r.top - r.height / 2) * 0.3);
        });
        btn.addEventListener("mouseleave", function () {
          xTo(0);
          yTo(0);
        });
      });
    }

    /* curtain lifts to reveal the page (home runs its own loader instead) */
    if (curtain && !opts.keepLoader) {
      gsap.fromTo(
        curtain,
        { yPercent: 0 },
        {
          yPercent: -100,
          duration: 0.8,
          ease: "expo.inOut",
          delay: 0.05,
          onComplete: function () {
            curtain.classList.add("is-gone");
          },
        },
      );
    }

    pageFn({
      gsap: true,
      reduce: false,
      mm: gsap.matchMedia(),
      smoother: RJ.smoother,
    });

    window.addEventListener("load", function () {
      ScrollTrigger.refresh();
    });
    if (doc.fonts && doc.fonts.ready)
      doc.fonts.ready.then(function () {
        ScrollTrigger.refresh();
      });
  };

  /* reveal a freshly rendered group of cards */
  RJ.reveal = function (els) {
    if (!window.gsap || reduce || !els || !els.length) return;
    gsap.fromTo(
      els,
      { y: 34, autoAlpha: 0 },
      {
        y: 0,
        autoAlpha: 1,
        duration: 0.9,
        ease: "expo.out",
        stagger: 0.05,
        clearProps: "transform",
      },
    );
  };

  /* accordion (height animates with GSAP when available) */
  RJ.accordion = function (root) {
    (root || doc).querySelectorAll(".acc__btn").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var acc = btn.closest(".acc");
        var panel = acc.querySelector(".acc__panel");
        var open = !acc.classList.contains("is-open");
        btn.setAttribute("aria-expanded", open ? "true" : "false");
        if (!window.gsap || reduce) {
          acc.classList.toggle("is-open", open);
          return;
        }
        if (open) {
          acc.classList.add("is-open");
          gsap.fromTo(
            panel,
            { height: 0 },
            {
              height: "auto",
              duration: 0.6,
              ease: "expo.out",
              onComplete: function () {
                if (window.ScrollTrigger) ScrollTrigger.refresh();
              },
            },
          );
        } else {
          gsap.to(panel, {
            height: 0,
            duration: 0.45,
            ease: "expo.inOut",
            onComplete: function () {
              acc.classList.remove("is-open");
              gsap.set(panel, { clearProps: "height" });
              if (window.ScrollTrigger) ScrollTrigger.refresh();
            },
          });
        }
      });
    });
  };
})();
