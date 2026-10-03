// Builds "Ek WordPress, Hazaar Sites" — a WordPress Multisite talk deck for
// WordCamp Rajasthan 2026, using the WCR brand assets in /wcr.
//
// Run (needs pptxgenjs, react, react-dom, react-icons, sharp):
//   ASSETS=<dir with logo-h.png icon.png wappu.png turban.png> node build-deck.js
// Optional: SKILL_DIR=<pptx skill dir> applies the WCR theme colors after writing.

const path = require("path");
const pptxgen = require("pptxgenjs");
const React = require("react");
const ReactDOMServer = require("react-dom/server");
const sharp = require("sharp");
const fa = require("react-icons/fa");

const ASSETS = process.env.ASSETS || path.join(__dirname, "assets");
const OUT = process.env.OUT || path.join(__dirname, "Multisite-WordCamp-Rajasthan-2026.pptx");
const asset = (f) => path.join(ASSETS, f);

// WCR brand tokens (scss/abstracts/_variables.scss)
const HEX = {
  ink: "231F20", white: "FFFFFF", navy: "0D2343", paper: "FFFAF4",
  maroon: "C33A19", gold: "E39D1C", teal: "049395", pink: "CE185B",
  green: "166534", muted: "6B625E", line: "EADFD6", peach: "FFF0DF",
};

const THEME = {
  name: "WordCamp Rajasthan 2026",
  headFontFace: "Arial",
  bodyFontFace: "Calibri",
  colors: {
    dk1: HEX.ink, lt1: HEX.white, dk2: HEX.navy, lt2: HEX.paper,
    accent1: HEX.maroon, accent2: HEX.gold, accent3: HEX.teal,
    accent4: HEX.pink, accent5: HEX.muted, accent6: HEX.green,
    hlink: HEX.teal, folHlink: HEX.pink,
  },
};

const pres = new pptxgen();
pres.layout = "LAYOUT_WIDE"; // 13.333 x 7.5
pres.theme = { headFontFace: THEME.headFontFace, bodyFontFace: THEME.bodyFontFace };
pres.title = "Ek WordPress, Hazaar Sites — WordPress Multisite";
pres.author = "Prathamesh Palve";
pres.subject = "WordCamp Rajasthan 2026";
pres.company = "WordCamp Rajasthan 2026";

const C = pres.SchemeColor;
const INK = C.text1, NAVY = C.text2, WHITE = C.background1, PAPER = C.background2;
const MAROON = C.accent1, GOLD = C.accent2, TEAL = C.accent3, PINK = C.accent4, MUTED = C.accent5, GREEN = C.accent6;

const W = 13.333, H = 7.5;
const MENU_SLIDE = 3;
const shadow = () => ({ type: "outer", color: "3D2317", blur: 12, offset: 3, angle: 90, opacity: 0.12 });

async function icon(Comp, color, size = 256) {
  const svg = ReactDOMServer.renderToStaticMarkup(React.createElement(Comp, { color: "#" + color, size: String(size) }));
  const buf = await sharp(Buffer.from(svg)).png().toBuffer();
  return "image/png;base64," + buf.toString("base64");
}

// ---------- Layouts ----------
const footer = (color) => ({
  text: {
    text: "WordCamp Rajasthan 2026  ·  #wcrajasthan  ·  @prathameshp",
    options: { x: 0.6, y: 7.0, w: 7, h: 0.3, fontSize: 10, color, margin: 0 },
  },
});

pres.defineSlideMaster({
  title: "WCR_CONTENT",
  background: { color: PAPER },
  objects: [
    { image: { path: asset("logo-h.png"), x: 11.35, y: 0.32, w: 1.45, h: 0.573 } },
    footer(MUTED),
    { placeholder: { options: { name: "title", type: "title", x: 0.6, y: 0.35, w: 10.5, h: 0.85, fontSize: 34, bold: true, color: NAVY, fontFace: THEME.headFontFace, valign: "middle", align: "left", margin: 0 }, text: "" } },
  ],
  slideNumber: { x: 12.3, y: 7.0, w: 0.5, h: 0.3, fontSize: 10, color: MUTED, align: "right" },
});

pres.defineSlideMaster({
  title: "WCR_DARK",
  background: { color: NAVY },
  objects: [
    footer(GOLD),
    { placeholder: { options: { name: "title", type: "title", x: 0.6, y: 0.35, w: 10.5, h: 0.85, fontSize: 34, bold: true, color: WHITE, fontFace: THEME.headFontFace, valign: "middle", align: "left", margin: 0 }, text: "" } },
  ],
  slideNumber: { x: 12.3, y: 7.0, w: 0.5, h: 0.3, fontSize: 10, color: GOLD, align: "right" },
});

pres.defineSlideMaster({ title: "WCR_COVER", background: { color: NAVY }, objects: [] });

// ---------- Helpers ----------
// Invisible shape over a button/card so the whole area is clickable in
// Slide Show without underlining its text
function clickZone(slide, x, y, w, h, target, tooltip) {
  slide.addShape(pres.ShapeType.rect, { x, y, w, h, fill: { color: WHITE, transparency: 100 }, line: { type: "none" }, hyperlink: { slide: target, tooltip }, objectName: "Link: " + tooltip });
}

function button(slide, text, x, y, w, h, fill, color, target, tooltip) {
  slide.addText(text, {
    x, y, w, h, shape: pres.ShapeType.roundRect, rectRadius: h / 2, fill: { color: fill }, color, fontSize: 11, bold: true,
    align: "center", valign: "middle", margin: 0, isTextBox: true, objectName: tooltip + " button",
  });
  clickZone(slide, x, y, w, h, target, tooltip);
}

function menuButton(slide, dark = false, x = 10.55) {
  button(slide, "☰  Menu", x, 6.93, 1.4, 0.42, dark ? GOLD : NAVY, dark ? NAVY : WHITE, MENU_SLIDE, "Back to the menu");
}

// Diamond motif from the WCR mark: icon inside a coloured diamond
function diamondIcon(slide, data, x, y, size, fill, name) {
  slide.addShape(pres.ShapeType.diamond, { x, y, w: size, h: size, fill: { color: fill }, line: { color: fill }, objectName: name + " frame" });
  const s = size * 0.42;
  slide.addImage({ data, x: x + (size - s) / 2, y: y + (size - s) / 2, w: s, h: s, altText: name, objectName: name + " icon" });
}

function card(slide, x, y, w, h, fill = WHITE, name = "Card") {
  slide.addShape(pres.ShapeType.roundRect, { x, y, w, h, rectRadius: 0.18, fill: { color: fill }, line: { color: HEX.line, width: 0.75 }, shadow: shadow(), objectName: name });
}

function bubble(slide, text, x, y, w, h, fill, color, fontSize = 15) {
  slide.addText(text, {
    x, y, w, h, shape: pres.ShapeType.wedgeRoundRectCallout, fill: { color: fill }, color,
    fontSize, bold: true, italic: true, align: "center", valign: "middle", margin: 8, isTextBox: true, objectName: "Speech bubble",
  });
}

(async () => {
  const I = {};
  const want = {
    wp: [fa.FaWordpress, HEX.white], uni: [fa.FaUniversity, HEX.white], news: [fa.FaNewspaper, HEX.white],
    store: [fa.FaStore, HEX.white], gov: [fa.FaLandmark, HEX.white], lang: [fa.FaLanguage, HEX.white],
    server: [fa.FaServer, HEX.white], db: [fa.FaDatabase, HEX.white], plug: [fa.FaPlug, HEX.white],
    lock: [fa.FaLock, HEX.white], users: [fa.FaUsers, HEX.white], clock: [fa.FaClock, HEX.white],
    hdd: [fa.FaHdd, HEX.white], move: [fa.FaExchangeAlt, HEX.white], shield: [fa.FaUserShield, HEX.white],
    term: [fa.FaTerminal, HEX.white], globe: [fa.FaGlobe, HEX.white], sitemap: [fa.FaSitemap, HEX.white],
    link: [fa.FaLink, HEX.white], backup: [fa.FaCloudUploadAlt, HEX.white], code: [fa.FaCode, HEX.white],
    check: [fa.FaCheck, HEX.white], times: [fa.FaTimes, HEX.white], arrow: [fa.FaArrowRight, HEX.teal],
    folder: [fa.FaFolderOpen, HEX.white], cog: [fa.FaCogs, HEX.white], question: [fa.FaQuestion, HEX.white],
    home: [fa.FaHome, HEX.white], bolt: [fa.FaBolt, HEX.white], hand: [fa.FaHandPaper, HEX.white],
  };
  for (const [k, [Comp, col]] of Object.entries(want)) I[k] = await icon(Comp, col);

  // =====================================================================
  // 1. COVER
  // =====================================================================
  pres.addSection({ title: "Khamma Ghani" });
  let s = pres.addSlide({ masterName: "WCR_COVER", sectionTitle: "Khamma Ghani" });
  s.addShape(pres.ShapeType.ellipse, { x: 7.35, y: 0.7, w: 5.6, h: 5.6, fill: { color: PAPER }, line: { color: GOLD, width: 3, dashType: "dash" }, objectName: "Cream circle" });
  s.addImage({ path: asset("wappu.png"), x: 7.05, y: 1.35, w: 6.2, h: 4.38, altText: "Rajasthani Wapuu with a camel", objectName: "Wapuu camel" });
  s.addShape(pres.ShapeType.roundRect, { x: 0.6, y: 0.55, w: 2.1, h: 0.83, rectRadius: 0.12, fill: { color: PAPER }, line: { color: PAPER }, objectName: "Logo plate" });
  s.addImage({ path: asset("logo-h.png"), x: 0.7, y: 0.6, w: 1.9, h: 0.75, altText: "WordCamp Rajasthan logo", objectName: "Logo" });
  s.addText("खम्मा घणी!  WordCamp Rajasthan 2026", { x: 0.6, y: 1.7, w: 6.6, h: 0.45, fontSize: 18, bold: true, color: GOLD, margin: 0, isTextBox: true });
  s.addText([
    { text: "Ek WordPress,", options: { breakLine: true } },
    { text: "Hazaar Sites", options: { color: GOLD } },
  ], { x: 0.6, y: 2.2, w: 6.7, h: 2.0, fontFace: THEME.headFontFace, fontSize: 54, bold: true, color: WHITE, margin: 0, valign: "top", isTextBox: true, objectName: "Talk title" });
  s.addText("WordPress Multisite — the Rajasthani joint family of the web: kya hai, kisko chahiye, setup, challenges aur unke solutions", {
    x: 0.6, y: 4.3, w: 6.5, h: 0.85, fontSize: 17, color: PAPER, margin: 0, isTextBox: true,
  });
  s.addText([
    { text: "Prathamesh Palve", options: { bold: true, fontSize: 20, color: WHITE, breakLine: true } },
    { text: "Technical Lead, CampusPress & Edublogs  ·  @prathameshp", options: { fontSize: 14, color: GOLD } },
  ], { x: 0.6, y: 5.45, w: 6.6, h: 0.8, margin: 0, isTextBox: true });
  s.addText("4 Oct 2026  ·  Rajasthan International Centre, Jaipur  ·  #wcrajasthan", { x: 0.6, y: 6.75, w: 8, h: 0.35, fontSize: 12, color: PAPER, margin: 0, isTextBox: true });
  s.addNotes("Khamma Ghani Jaipur! Padharo mhare desh — aur aaj padharo mhare network mein. Main Prathamesh, aur aaj hum baat karenge us feature ki jiske baare mein sab sunte hain par koi poochta nahi: WordPress Multisite. Ek install, hazaar sites. Seatbelt baandh lo, camel ki sawari shuru.");

  // =====================================================================
  // 2. ABOUT
  // =====================================================================
  s = pres.addSlide({ masterName: "WCR_CONTENT", sectionTitle: "Khamma Ghani" });
  s.addText("Khamma Ghani, main hoon Prathamesh", { placeholder: "title" });
  s.addText("Din mein Multisite networks sambhalta hoon. Raat mein mummy ko samjhata hoon ki main karta kya hoon.", { x: 0.6, y: 1.25, w: 8.4, h: 0.45, fontSize: 16, italic: true, color: MUTED, margin: 0, isTextBox: true });
  const about = [
    ["8+ saal", "WordPress support, platform ops aur technical leadership", MAROON, I.bolt],
    ["CampusPress", "Technical Lead — universities aur schools ke bade Multisite networks", TEAL, I.uni],
    ["Community", "Co-organiser, WordPress Thane Meetup · WordCamp speaker", PINK, I.users],
    ["Contributor", "Marathi & Hindi translations · forum support · WPTV", GOLD, I.lang],
  ];
  about.forEach(([h, d, col, ic], i) => {
    const x = 0.6 + (i % 2) * 4.25, y = 2.0 + Math.floor(i / 2) * 2.25;
    card(s, x, y, 3.95, 1.95, WHITE, "About card " + (i + 1));
    diamondIcon(s, ic, x + 0.3, y + 0.3, 0.8, col, h);
    s.addText(h, { x: x + 1.3, y: y + 0.35, w: 2.5, h: 0.65, fontSize: 22, bold: true, color: NAVY, margin: 0, valign: "middle", isTextBox: true });
    s.addText(d, { x: x + 0.3, y: y + 1.15, w: 3.45, h: 0.7, fontSize: 14, color: INK, margin: 0, valign: "top", isTextBox: true });
  });
  s.addImage({ path: asset("turban.png"), x: 9.45, y: 2.75, w: 3.4, h: 3.4, altText: "Rajasthani mascot with folded hands", objectName: "Mascot" });
  bubble(s, "Roz hazaaron sites ka babysitter hoon!", 9.35, 1.35, 3.5, 1.15, GOLD, NAVY, 15);
  menuButton(s);
  s.addNotes("Quick intro. CampusPress aur Edublogs pe hum education ke liye bahut bade Multisite networks chalate hain — migrations, SSO, plugin governance, performance, incidents. To jo bhi aaj bolunga, woh production ke zakhmon se seekha hai.");

  // =====================================================================
  // 3. MENU (interactive)
  // =====================================================================
  s = pres.addSlide({ masterName: "WCR_CONTENT", sectionTitle: "Khamma Ghani" });
  s.addText("Aaj ki Thaali: Menu", { placeholder: "title" });
  s.addText("Kisi bhi tile pe click karo, seedha wahin pahunchoge. Har slide ka “☰ Menu” button wapas yahin laata hai.", { x: 0.6, y: 1.25, w: 10.5, h: 0.45, fontSize: 15, color: MUTED, margin: 0, isTextBox: true });
  const menu = [
    ["01", "Multisite kya bala hai?", "Haveli wala funda", 4, MAROON, I.sitemap],
    ["02", "Koi use bhi karta hai?", "Myth busting", 5, TEAL, I.question],
    ["03", "Kab lo, kab nahi", "Plus ek quiz", 6, PINK, I.hand],
    ["04", "Setup ki zaroorat", "Checklist & wp-config", 9, GOLD, I.cog],
    ["05", "File & DB structure", "Single vs Multisite", 11, NAVY, I.folder],
    ["06", "Dard aur Dawa", "Challenges → solutions", 12, GREEN, I.bolt],
  ];
  menu.forEach(([n, t, sub, target, col, ic], i) => {
    const x = 0.6 + (i % 3) * 4.1, y = 1.95 + Math.floor(i / 3) * 2.4;
    card(s, x, y, 3.8, 2.1, WHITE, "Menu tile " + n);
    diamondIcon(s, ic, x + 0.3, y + 0.3, 0.85, col, t);
    s.addText(n, { x: x + 2.6, y: y + 0.25, w: 0.95, h: 0.6, fontSize: 30, bold: true, color: col, align: "right", margin: 0, isTextBox: true });
    s.addText(t, { x: x + 0.3, y: y + 1.2, w: 3.3, h: 0.45, fontSize: 19, bold: true, color: NAVY, margin: 0, isTextBox: true });
    s.addText(sub, { x: x + 0.3, y: y + 1.62, w: 3.3, h: 0.35, fontSize: 14, color: MUTED, margin: 0, isTextBox: true });
    clickZone(s, x, y, 3.8, 2.1, target, t);
  });
  s.addNotes("Yeh interactive menu hai — Slide Show mode mein kisi bhi tile pe click karo. Audience se poochho: kaunsa pehle khaayein? (Spoiler: hum order mein hi chalenge.)");

  // =====================================================================
  // 4. WHAT IS MULTISITE
  // =====================================================================
  pres.addSection({ title: "Multisite 101" });
  s = pres.addSlide({ masterName: "WCR_CONTENT", sectionTitle: "Multisite 101" });
  s.addText("Multisite kya bala hai?", { placeholder: "title" });
  s.addText("Single site = apna 1BHK flat.  Multisite = poori haveli: ek chhat, kai kamre, ek Thakur Sa.", { x: 0.6, y: 1.25, w: 11, h: 0.45, fontSize: 16, italic: true, color: MAROON, bold: true, margin: 0, isTextBox: true });
  // Diagram
  card(s, 0.6, 1.95, 6.3, 4.75, WHITE, "Diagram card");
  s.addText([{ text: "1 WordPress install", options: { bold: true, breakLine: true } }, { text: "core + plugins + themes", options: { fontSize: 12 } }], {
    x: 1.95, y: 2.2, w: 3.6, h: 0.85, shape: pres.ShapeType.roundRect, rectRadius: 0.12, fill: { color: NAVY }, color: WHITE, fontSize: 16, align: "center", valign: "middle", margin: 4, isTextBox: true,
  });
  s.addText([{ text: "1 Database", options: { bold: true, breakLine: true } }, { text: "har site ki apni tables", options: { fontSize: 12 } }], {
    x: 1.95, y: 3.4, w: 3.6, h: 0.85, shape: pres.ShapeType.roundRect, rectRadius: 0.12, fill: { color: GOLD }, color: NAVY, fontSize: 16, align: "center", valign: "middle", margin: 4, isTextBox: true,
  });
  s.addShape(pres.ShapeType.line, { x: 3.75, y: 3.05, w: 0, h: 0.35, line: { color: MUTED, width: 2, endArrowType: "triangle" } });
  const sites = [["jaipur", MAROON], ["jodhpur", TEAL], ["udaipur", PINK], ["bikaner", GREEN]];
  sites.forEach(([n, col], i) => {
    const x = 0.9 + i * 1.5;
    const end = x + 0.625, left = end < 3.75;
    s.addShape(pres.ShapeType.line, { x: left ? end : 3.75, y: 4.25, w: Math.max(Math.abs(end - 3.75), 0.01), h: 0.55, flipH: left, line: { color: MUTED, width: 1.5, endArrowType: "triangle" } });
    s.addText([{ text: "Site " + (i + 1), options: { bold: true, fontSize: 14, breakLine: true } }, { text: n, options: { fontSize: 11 } }], {
      x, y: 4.8, w: 1.25, h: 0.95, shape: pres.ShapeType.roundRect, rectRadius: 0.1, fill: { color: col }, color: WHITE, align: "center", valign: "middle", margin: 2, isTextBox: true,
    });
  });
  s.addText("Ek update = saari sites update", { x: 0.9, y: 6.0, w: 5.7, h: 0.4, fontSize: 14, bold: true, color: NAVY, align: "center", margin: 0, isTextBox: true });
  // Pointers
  const what = [
    [I.wp, MAROON, "Ek core, anek sites", "Ek hi WordPress codebase se sau, hazaar sites chalti hain."],
    [I.plug, TEAL, "Plugins & themes shared", "Super Admin install karta hai, site admin bas activate karta hai."],
    [I.db, GOLD, "Content alag, users common", "Har site ki apni tables; users ek hi pool se aate hain."],
    [I.shield, PINK, "Network Admin = Thakur Sa", "Ek dashboard se poori haveli ka control."],
  ];
  what.forEach(([ic, col, h, d], i) => {
    const y = 1.95 + i * 1.2;
    diamondIcon(s, ic, 7.3, y + 0.05, 0.8, col, h);
    s.addText(h, { x: 8.35, y, w: 4.4, h: 0.42, fontSize: 18, bold: true, color: NAVY, margin: 0, isTextBox: true });
    s.addText(d, { x: 8.35, y: y + 0.42, w: 4.4, h: 0.6, fontSize: 14, color: INK, margin: 0, valign: "top", isTextBox: true });
  });
  menuButton(s);
  s.addNotes("Multisite WordPress core ka built-in feature hai, koi plugin nahi. Ek install, ek database, lekin har site ki apni posts/options tables. Users aur plugins/themes poore network mein shared. Super Admin = Thakur Sa — sab unke haath mein.");

  // =====================================================================
  // 5. MYTH BUSTER (dark)
  // =====================================================================
  s = pres.addSlide({ masterName: "WCR_DARK", sectionTitle: "Multisite 101" });
  s.addText("“Multisite koi use bhi karta hai?”", { placeholder: "title" });
  s.addText("Haan bhai, karta hai! Aur shayad aap roz unki sites padhte ho.", { x: 0.6, y: 1.25, w: 11, h: 0.45, fontSize: 18, bold: true, color: GOLD, margin: 0, isTextBox: true });
  const myths = [
    ["Multisite toh dead feature hai", "Core ka part hai, har release ke saath maintain hota hai"],
    ["Sirf badi companies ke liye", "2 se 20,000 sites tak, jahaan parivaar ek jaisa ho"],
    ["Bahut complex, mat chhedo", "Setup 10 minute ka; mehnat governance mein hai"],
  ];
  s.addText("Afwaah  vs  Asliyat", { x: 0.6, y: 1.95, w: 5.6, h: 0.45, fontSize: 18, bold: true, color: WHITE, margin: 0, isTextBox: true });
  myths.forEach(([m, f], i) => {
    const y = 2.5 + i * 1.4;
    s.addShape(pres.ShapeType.roundRect, { x: 0.6, y, w: 5.6, h: 1.2, rectRadius: 0.15, fill: { color: "1A3460" }, line: { color: "1A3460" }, objectName: "Myth card " + (i + 1) });
    s.addText([
      { text: m, options: { strike: "sngStrike", color: "F4A3A3", breakLine: true } },
      { text: f, options: { color: WHITE, bold: true } },
    ], { x: 0.85, y: y + 0.1, w: 5.2, h: 1.0, fontSize: 15, margin: 0, valign: "middle", isTextBox: true });
  });
  const users = [
    [I.wp, "WordPress.com", "Khud Multisite pe chalta hai", MAROON],
    [I.uni, "Universities & schools", "Har department, har class ki site", TEAL],
    [I.news, "Media houses", "Har shehar ka regional edition", PINK],
    [I.store, "Franchises & brands", "Har outlet / branch ki site", GOLD],
    [I.gov, "Government & NGOs", "Har vibhaag ek network mein", GREEN],
    [I.lang, "Multilingual", "Hindi, Marwari, English: ek-ek site", MAROON],
  ];
  users.forEach(([ic, h, d, col], i) => {
    const x = 6.75 + (i % 2) * 3.0, y = 1.95 + Math.floor(i / 2) * 1.62;
    s.addShape(pres.ShapeType.roundRect, { x, y, w: 2.8, h: 1.45, rectRadius: 0.15, fill: { color: PAPER }, line: { color: PAPER }, objectName: "User card " + (i + 1) });
    diamondIcon(s, ic, x + 0.15, y + 0.15, 0.6, col, h);
    s.addText(h, { x: x + 0.85, y: y + 0.15, w: 1.85, h: 0.6, fontSize: 14, bold: true, color: NAVY, margin: 0, valign: "middle", isTextBox: true });
    s.addText(d, { x: x + 0.15, y: y + 0.82, w: 2.55, h: 0.55, fontSize: 12, color: INK, margin: 0, valign: "top", isTextBox: true });
  });
  menuButton(s, true);
  s.addNotes("Yeh sabse bada stereotype hai: 'Multisite? Woh koi use karta hai kya?' WordPress.com khud Multisite pe chalta hai. Edublogs aur CampusPress education ke liye bade networks chalate hain. Newsrooms, franchises, sarkari vibhaag — sab. Audience se poochho: kitne logon ne kabhi Multisite banaya hai? Haath uthao! Ab kitne logon ne use production mein chalaya? ... Dekha, yahi farak hai.");

  // =====================================================================
  // 6. WHEN TO USE
  // =====================================================================
  pres.addSection({ title: "Kab use karein" });
  s = pres.addSlide({ masterName: "WCR_CONTENT", sectionTitle: "Kab use karein" });
  s.addText("Kab lo Multisite, aur kab bilkul nahi", { placeholder: "title" });
  const yes = [
    "Sites ka DNA same: wahi plugins, wahi theme family",
    "Ek central team, central control aur governance",
    "Users ko kai sites pe ek hi login chahiye",
    "Nayi site minute mein spin-up karni hai (template se)",
    "Updates & security ek jagah se sambhalni hai",
  ];
  const no = [
    "Har site ko alag plugins, alag PHP, alag hosting chahiye",
    "Clients ko apna server aur full admin control chahiye",
    "Site kal bechni ya network se alag karni hai",
    "Ek plugin se kaam chal jaaye (jaise multilingual)",
    "Sirf isliye ki naam cool lagta hai",
  ];
  const eg = ["Jaise: university, newsroom, franchise", "Jaise: agency ke 25 alag-alag clients"];
  [[yes, GREEN, I.check, "Haan, Multisite lo"], [no, MAROON, I.times, "Ruko, alag ghar do"]].forEach(([list, col, ic, head], c) => {
    const x = 0.6 + c * 6.2;
    card(s, x, 1.45, 5.9, 4.35, WHITE, head + " card");
    diamondIcon(s, ic, x + 0.3, 1.65, 0.75, col, head);
    s.addText(head, { x: x + 1.25, y: 1.65, w: 4.4, h: 0.75, fontSize: 22, bold: true, color: col, margin: 0, valign: "middle", isTextBox: true });
    s.addText(list.map((t, i) => ({ text: t, options: { bullet: { indent: 18 }, breakLine: i < list.length - 1 } })), {
      x: x + 0.35, y: 2.6, w: 5.3, h: 2.55, fontSize: 16, color: INK, paraSpaceAfter: 10, margin: 0, valign: "top", isTextBox: true,
    });
    s.addText(eg[c], { x: x + 0.35, y: 5.2, w: 5.3, h: 0.4, fontSize: 14, italic: true, bold: true, color: col, margin: 0, isTextBox: true });
  });
  s.addText([
    { text: "Golden rule:  ", options: { bold: true, color: GOLD } },
    { text: "Sites ek parivaar jaisi hain? Multisite. Sirf padosi hain? Alag-alag ghar.", options: { color: WHITE } },
  ], { x: 0.6, y: 6.0, w: 12.1, h: 0.7, shape: pres.ShapeType.roundRect, rectRadius: 0.15, fill: { color: NAVY }, fontSize: 17, align: "center", valign: "middle", margin: 6, isTextBox: true });
  menuButton(s);
  s.addNotes("Multisite ka decision technical se zyada organisational hai. Agar sites ek hi team chalati hai aur ek jaisi hain, Multisite jadoo hai. Agar agency ke 25 alag clients hain jinke apne hosts aur apne plugins hain, Multisite unhe ek hi ghar mein zabardasti bithana hai — roz jhagda hoga.");

  // =====================================================================
  // 7. QUIZ (interactive)
  // =====================================================================
  s = pres.addSlide({ masterName: "WCR_DARK", sectionTitle: "Kab use karein" });
  s.addText("Haath Uthao! Multisite ya Single?", { placeholder: "title" });
  s.addText("Har case pe vote karo, phir “Jawab dekho” dabao.", { x: 0.6, y: 1.25, w: 11, h: 0.45, fontSize: 17, color: GOLD, margin: 0, isTextBox: true });
  const quiz = [
    ["A", "University with 400 department sites", "Ek IT team, ek brand, ek login", TEAL],
    ["B", "Agency with 25 clients", "Har client ka alag host, alag bill", PINK],
    ["C", "Pizza chain with 60 city outlets", "Same design, local menu & offers", GOLD],
    ["D", "Aapka blog + mummy ka recipe blog", "Do sites, do log, zero IT team", MAROON],
  ];
  quiz.forEach(([l, t, d, col], i) => {
    const x = 0.6 + i * 3.075;
    s.addShape(pres.ShapeType.roundRect, { x, y: 2.0, w: 2.85, h: 4.1, rectRadius: 0.18, fill: { color: PAPER }, line: { color: PAPER }, objectName: "Quiz card " + l });
    s.addText(l, { x: x + 0.95, y: 2.25, w: 0.95, h: 0.95, shape: pres.ShapeType.diamond, fill: { color: col }, color: WHITE, fontSize: 26, bold: true, align: "center", valign: "middle", margin: 0, isTextBox: true });
    s.addText(t, { x: x + 0.2, y: 3.4, w: 2.45, h: 1.0, fontSize: 18, bold: true, color: NAVY, align: "center", valign: "top", margin: 0, isTextBox: true });
    s.addText(d, { x: x + 0.2, y: 4.45, w: 2.45, h: 0.9, fontSize: 14, color: INK, align: "center", valign: "top", margin: 0, isTextBox: true });
    button(s, "Jawab dekho  →", x + 0.3, 5.35, 2.25, 0.5, NAVY, WHITE, 8, "Reveal answer " + l);
  });
  menuButton(s, true);
  s.addNotes("Interactive part! Har case padho, audience se haath uthwao: Multisite wale ek taraf, Single wale doosri taraf. Phir 'Jawab dekho' pe click karo.");

  // =====================================================================
  // 8. QUIZ ANSWERS
  // =====================================================================
  s = pres.addSlide({ masterName: "WCR_CONTENT", sectionTitle: "Kab use karein" });
  s.addText("Jawab haazir hai", { placeholder: "title" });
  const ans = [
    ["A", "Multisite", "Ek team, ek brand, ek login: textbook case. CampusPress roz yahi karta hai.", GREEN, I.check],
    ["B", "Single sites", "Alag clients, alag hosts, alag billing. Ek ki galti sab pe na gire.", MAROON, I.times],
    ["C", "Multisite", "Brand central, menu local. Nayi branch = nayi site, 2 minute mein.", GREEN, I.check],
    ["D", "Single site", "2 sites ke liye Multisite = 2 mehmaanon ke liye shaadi ka tent.", MAROON, I.times],
  ];
  ans.forEach(([l, v, why, col, ic], i) => {
    const x = 0.6 + (i % 2) * 6.2, y = 1.5 + Math.floor(i / 2) * 2.55;
    card(s, x, y, 5.9, 2.25, WHITE, "Answer " + l);
    diamondIcon(s, ic, x + 0.3, y + 0.3, 0.85, col, v);
    s.addText([{ text: l + "  ·  ", options: { color: MUTED } }, { text: v, options: { color: col } }], { x: x + 1.4, y: y + 0.3, w: 4.3, h: 0.85, fontSize: 24, bold: true, margin: 0, valign: "middle", isTextBox: true });
    s.addText(why, { x: x + 0.3, y: y + 1.3, w: 5.3, h: 0.8, fontSize: 15, color: INK, margin: 0, valign: "top", isTextBox: true });
  });
  button(s, "←  Quiz pe wapas", 8.85, 6.93, 1.55, 0.42, GOLD, NAVY, 7, "Back to quiz");
  menuButton(s);
  s.addNotes("Jisne D ke liye Multisite bola — aapko main personally ek dal-baati free mein dunga, par Multisite nahi. Rule simple hai: shared ownership + shared stack = Multisite.");

  // =====================================================================
  // 9. SETUP REQUIREMENTS
  // =====================================================================
  pres.addSection({ title: "Setup" });
  s = pres.addSlide({ masterName: "WCR_CONTENT", sectionTitle: "Setup" });
  s.addText("Setup ki zaroorat: pehle checklist, phir magic", { placeholder: "title" });
  const req = [
    [I.server, MAROON, "Multisite-friendly hosting", "Kuch shared hosts mana karte hain, pehle pooch lo"],
    [I.link, TEAL, "Pretty permalinks + rewrites", "Apache mod_rewrite ya Nginx rules ready"],
    [I.globe, PINK, "Subdomain? Wildcard DNS + SSL", "*.example.com ka DNS record aur wildcard cert"],
    [I.backup, GOLD, "Full backup + plugins deactivate", "Files aur DB dono. “Backup nahi liya” = horror story"],
    [I.term, NAVY, "WP-CLI access", "Badi network ka asli jeevan-saathi"],
  ];
  req.forEach(([ic, col, h, d], i) => {
    const y = 1.45 + i * 1.05;
    diamondIcon(s, ic, 0.6, y + 0.05, 0.75, col, h);
    s.addText(h, { x: 1.55, y, w: 4.9, h: 0.42, fontSize: 17, bold: true, color: NAVY, margin: 0, isTextBox: true });
    s.addText(d, { x: 1.55, y: y + 0.42, w: 4.9, h: 0.45, fontSize: 14, color: INK, margin: 0, isTextBox: true });
  });
  s.addShape(pres.ShapeType.roundRect, { x: 6.75, y: 1.45, w: 6.0, h: 5.25, rectRadius: 0.18, fill: { color: NAVY }, line: { color: NAVY }, shadow: shadow(), objectName: "Code card" });
  s.addText("wp-config.php", { x: 7.05, y: 1.6, w: 4, h: 0.4, fontSize: 14, bold: true, color: GOLD, margin: 0, isTextBox: true });
  const code = [
    ["/* Step 1: network ka darwaza kholo */", "8FA3C7"],
    ["define( 'WP_ALLOW_MULTISITE', true );", HEX.white],
    ["", HEX.white],
    ["/* Step 2: Tools → Network Setup */", "8FA3C7"],
    ["/* Step 3: generated code paste karo */", "8FA3C7"],
    ["define( 'MULTISITE', true );", HEX.white],
    ["define( 'SUBDOMAIN_INSTALL', false );", HEX.white],
    ["define( 'DOMAIN_CURRENT_SITE', 'jaipur.test' );", HEX.white],
    ["define( 'PATH_CURRENT_SITE', '/' );", HEX.white],
    ["define( 'SITE_ID_CURRENT_SITE', 1 );", HEX.white],
    ["define( 'BLOG_ID_CURRENT_SITE', 1 );", HEX.white],
    ["", HEX.white],
    ["/* Step 4: dobara login → Network Admin */", "8FA3C7"],
  ];
  s.addText(code.map(([t, c], i) => ({ text: t || " ", options: { color: c, breakLine: i < code.length - 1 } })), {
    x: 7.05, y: 2.05, w: 5.5, h: 3.7, fontFace: "Courier New", fontSize: 13.5, margin: 0, valign: "top", isTextBox: true, objectName: "wp-config code",
  });
  s.addText([
    { text: "Shortcut:  ", options: { bold: true, color: NAVY } },
    { text: "wp core multisite-convert", options: { fontFace: "Courier New", color: NAVY } },
  ], { x: 7.05, y: 5.85, w: 5.4, h: 0.6, shape: pres.ShapeType.roundRect, rectRadius: 0.12, fill: { color: GOLD }, fontSize: 14, valign: "middle", margin: 8, isTextBox: true });
  menuButton(s);
  s.addNotes("Setup asaan hai, taiyari zaroori hai. Step 1: wp-config mein WP_ALLOW_MULTISITE. Step 2: Tools > Network Setup, subdomain ya subdirectory chuno. Step 3: jo code WordPress de, wp-config aur .htaccess mein paste karo, dobara login karo. Ya WP-CLI se ek line: wp core multisite-convert.");

  // =====================================================================
  // 10. SUBDOMAIN vs SUBDIRECTORY vs DOMAIN
  // =====================================================================
  s = pres.addSlide({ masterName: "WCR_CONTENT", sectionTitle: "Setup" });
  s.addText("Ghar ka pata kya rakhein?", { placeholder: "title" });
  const addr = [
    ["Subdomain", "jodhpur.example.com", ["Har site apni pehchaan wali lagti hai", "Wildcard DNS + wildcard SSL chahiye", "Badi networks ka favourite"], TEAL, I.globe, "Best for: schools, cities, brands"],
    ["Subdirectory", "example.com/jodhpur", ["Ek domain, ek SSL: sabse simple", "SEO authority ek hi domain pe", "Purani (1 mahine+) site pe option band milta hai"], MAROON, I.sitemap, "Best for: departments, languages"],
    ["Domain mapping", "jodhpur.in", ["Core mein built-in (WP 4.5+)", "Site URL network admin se badlo", "DNS, SSL aur cookies ka dhyaan rakho"], PINK, I.link, "Best for: alag brand, apna domain"],
  ];
  s.addText("Subdomain, subfolder ya apna domain: network banate waqt hi socho, baad mein badalna dard hai", { x: 0.6, y: 1.2, w: 10.5, h: 0.4, fontSize: 15, italic: true, color: MUTED, margin: 0, isTextBox: true });
  addr.forEach(([h, url, pts, col, ic, best], i) => {
    const x = 0.6 + i * 4.1;
    card(s, x, 1.8, 3.8, 4.9, WHITE, h + " card");
    diamondIcon(s, ic, x + 0.3, 2.0, 0.8, col, h);
    s.addText(h, { x: x + 1.25, y: 2.0, w: 2.4, h: 0.8, fontSize: 20, bold: true, color: NAVY, margin: 0, valign: "middle", isTextBox: true });
    s.addText(url, { x: x + 0.3, y: 3.0, w: 3.2, h: 0.55, shape: pres.ShapeType.roundRect, rectRadius: 0.1, fill: { color: col }, color: WHITE, fontFace: "Courier New", fontSize: 13, bold: true, align: "center", valign: "middle", margin: 0, isTextBox: true });
    s.addText(pts.map((t, j) => ({ text: t, options: { bullet: { indent: 16 }, breakLine: j < pts.length - 1 } })), {
      x: x + 0.3, y: 3.8, w: 3.25, h: 2.0, fontSize: 15, color: INK, paraSpaceAfter: 10, margin: 0, valign: "top", isTextBox: true,
    });
    s.addText(best, { x: x + 0.3, y: 5.95, w: 3.25, h: 0.45, fontSize: 14, bold: true, italic: true, color: col, margin: 0, valign: "middle", isTextBox: true });
  });
  menuButton(s);
  s.addNotes("Fun fact: agar aapki WordPress install ek mahine se purani hai, Network Setup subdirectory option disable kar deta hai, taaki purane permalinks se takraav na ho. Domain mapping ab core mein hai, plugin ki zaroorat nahi — bas DNS aur SSL sahi rakho.");

  // =====================================================================
  // 11. FILE & DB STRUCTURE
  // =====================================================================
  s = pres.addSlide({ masterName: "WCR_CONTENT", sectionTitle: "Setup" });
  s.addText("File structure: files ek, tables anek", { placeholder: "title" });
  const tree = (lines) => lines.map(([t, c, b], i) => ({ text: t, options: { color: c, bold: !!b, breakLine: i < lines.length - 1 } }));
  const cols = [
    ["Single Site", "1BHK flat", TEAL, [
      ["wordpress/", HEX.navy, 1],
      ["├─ wp-config.php", HEX.ink],
      ["├─ .htaccess", HEX.ink],
      ["└─ wp-content/", HEX.ink],
      ["   ├─ plugins/", HEX.ink],
      ["   ├─ themes/", HEX.ink],
      ["   └─ uploads/2026/10/", HEX.ink],
      [" ", HEX.ink],
      ["Database", HEX.navy, 1],
      ["wp_posts   wp_postmeta", HEX.ink],
      ["wp_options wp_terms …", HEX.ink],
      ["wp_users   wp_usermeta", HEX.ink],
    ]],
    ["Multisite", "Poori haveli", MAROON, [
      ["wordpress/", HEX.navy, 1],
      ["├─ wp-config.php  ← + MULTISITE", HEX.maroon],
      ["├─ .htaccess      ← network rules", HEX.maroon],
      ["└─ wp-content/", HEX.ink],
      ["   ├─ plugins/    ← sab ke liye", HEX.ink],
      ["   ├─ mu-plugins/ ← network-wide", HEX.maroon],
      ["   ├─ sunrise.php ← optional", HEX.maroon],
      ["   └─ uploads/sites/2/2026/10/", HEX.maroon],
      [" ", HEX.ink],
      ["Database", HEX.navy, 1],
      ["wp_posts → wp_2_posts, wp_3_posts …", HEX.maroon],
      ["Global: wp_blogs wp_site wp_sitemeta", HEX.maroon],
      ["        wp_blogmeta wp_signups", HEX.maroon],
      ["Shared: wp_users wp_usermeta", HEX.ink],
    ]],
  ];
  const verdict = ["Sab kuch ek site ka. Simple, seedha.", "Code shared, content alag-alag kamron mein."];
  cols.forEach(([h, sub, col, lines], i) => {
    const x = 0.6 + i * 5.05, w = i ? 5.35 : 4.75;
    card(s, x, 1.45, w, 5.25, WHITE, h + " tree");
    s.addText([{ text: h, options: { bold: true, color: col } }, { text: "   " + sub, options: { color: MUTED, italic: true, fontSize: 14 } }], { x: x + 0.3, y: 1.6, w: w - 0.6, h: 0.5, fontSize: 20, margin: 0, valign: "middle", isTextBox: true });
    s.addText(tree(lines), { x: x + 0.3, y: 2.2, w: w - 0.4, h: 3.75, fontFace: "Courier New", fontSize: 14, margin: 0, valign: "top", isTextBox: true, objectName: h + " file tree" });
    s.addText(verdict[i], { x: x + 0.3, y: 6.05, w: w - 0.6, h: 0.45, fontSize: 15, bold: true, italic: true, color: col, margin: 0, valign: "middle", isTextBox: true });
  });
  card(s, 11.25, 1.45, 1.5, 5.25, NAVY, "Legend");
  s.addText([
    { text: "Maroon", options: { bold: true, color: GOLD, breakLine: true } },
    { text: "= Multisite mein naya ya badla hua", options: { color: WHITE, breakLine: true } },
    { text: " ", options: { breakLine: true } },
    { text: "~10", options: { bold: true, color: GOLD, fontSize: 28, breakLine: true } },
    { text: "nayi tables har nayi site pe", options: { color: WHITE } },
  ], { x: 11.35, y: 1.7, w: 1.3, h: 4.8, fontSize: 13, margin: 0, valign: "top", isTextBox: true });
  menuButton(s);
  s.addNotes("Single site mein sab ek jagah. Multisite mein files wahi rehti hain, lekin wp-config mein constants, .htaccess mein naye rewrite rules, uploads har site ke liye uploads/sites/<id>/ mein. Database mein main site wp_ prefix rakhti hai, baaki wp_2_, wp_3_ ... Plus global tables: wp_blogs, wp_site, wp_sitemeta, wp_blogmeta, wp_signups, wp_registration_log. Users sab ke common. Isiliye 1000 sites = 10,000 se zyada tables. Yaad rakhna, agle slide mein yahi dard banega.");

  // =====================================================================
  // 12–13. CHALLENGES → SOLUTIONS
  // =====================================================================
  pres.addSection({ title: "Dard aur Dawa" });
  const painSlide = (title, sub, rows, notes) => {
    const sl = pres.addSlide({ masterName: "WCR_CONTENT", sectionTitle: "Dard aur Dawa" });
    sl.addText(title, { placeholder: "title" });
    sl.addText(sub, { x: 0.6, y: 1.2, w: 10.5, h: 0.4, fontSize: 15, italic: true, color: MUTED, margin: 0, isTextBox: true });
    sl.addText("DARD (problem)", { x: 1.6, y: 1.7, w: 4, h: 0.3, fontSize: 12, bold: true, color: MAROON, margin: 0, isTextBox: true });
    sl.addText("DAWA (solution)", { x: 7.15, y: 1.7, w: 4, h: 0.3, fontSize: 12, bold: true, color: TEAL, margin: 0, isTextBox: true });
    rows.forEach(([ic, ph, pd, sh, sd], i) => {
      const y = 2.1 + i * 1.18;
      sl.addShape(pres.ShapeType.roundRect, { x: 0.6, y, w: 5.6, h: 1.03, rectRadius: 0.14, fill: { color: "FBE5DF" }, line: { color: "FBE5DF" }, objectName: "Problem " + (i + 1) });
      diamondIcon(sl, ic, 0.75, y + 0.16, 0.7, MAROON, ph);
      sl.addText([{ text: ph, options: { bold: true, color: NAVY, fontSize: 16, breakLine: true } }, { text: pd, options: { color: INK, fontSize: 14 } }], { x: 1.6, y: y + 0.05, w: 4.5, h: 0.93, margin: 0, valign: "middle", isTextBox: true });
      sl.addImage({ data: I.arrow, x: 6.4, y: y + 0.33, w: 0.37, h: 0.37, altText: "leads to", objectName: "Arrow " + (i + 1) });
      sl.addShape(pres.ShapeType.roundRect, { x: 6.95, y, w: 5.8, h: 1.03, rectRadius: 0.14, fill: { color: "DDF1EF" }, line: { color: "DDF1EF" }, objectName: "Solution " + (i + 1) });
      sl.addText([{ text: sh, options: { bold: true, color: TEAL, fontSize: 16, breakLine: true } }, { text: sd, options: { color: INK, fontSize: 14 } }], { x: 7.15, y: y + 0.05, w: 5.5, h: 0.93, margin: 0, valign: "middle", isTextBox: true });
    });
    menuButton(sl);
    sl.addNotes(notes);
    return sl;
  };
  painSlide("Dard aur Dawa #1: performance & scale", "Haveli badi ho to bijli ka bill bhi bada aata hai", [
    [I.db, "Database bloat", "1,000 sites × ~10 tables = 10,000+ tables", "Object cache + DB sharding", "Redis/Memcached; HyperDB ya LudicrousDB se DB split"],
    [I.plug, "Ek plugin, poora network down", "Network-activate kiya, sab sites ka jhatka", "Staging + plugin governance", "Pehle staging network, approved list, rollback plan"],
    [I.clock, "WP-Cron ki laziness", "Cron sirf visit pe chalta hai, so rahi sites miss", "Real server cron", "DISABLE_WP_CRON + har site pe wp cron event run --due-now"],
    [I.hdd, "Uploads ka pahaad", "uploads/sites/* chupchaap TBs tak pahunch jaata hai", "Offload + quotas", "Media S3/CDN pe; Network Settings mein upload space limit"],
  ], "Yeh woh problems hain jo 10 sites pe nahi dikhti, 1000 pe neend uda deti hain. Object cache must hai. Bahut badi networks mein DB ko multiple servers pe baanto. Plugin ko network-activate karne se pehle staging network pe test karo — ek fatal error, poori haveli andhere mein. Cron ko traffic ke bharose mat chhodo.");
  painSlide("Dard aur Dawa #2: operations & people", "Joint family mein asli jhagde log aur kaagaz-patr ke hote hain", [
    [I.move, "Ek site andar/bahar migrate karna", "IDs, prefixes, uploads/sites/N paths sab badalte hain", "WP-CLI + Multisite-aware tools", "wp search-replace --url / --network, per-site export & backups"],
    [I.lock, "Domain mapping & SSL", "Cookie errors, redirect loops, “Not secure” warning", "Core domain mapping + auto SSL", "Har domain ka cert automate; COOKIE_DOMAIN sambhal ke"],
    [I.code, "Plugin compatibility", "get_option vs get_site_option, switch_to_blog() bhool gaye", "Multisite pe test karo", "Network vs site settings alag; “Network: true” header samjho"],
    [I.shield, "Roles ka confusion", "Site admin plugin install nahi kar sakta, unfiltered_html band", "Clear roles + kam Super Admins", "Role guide likho, onboarding karo, Super Admin VIP pass rakho"],
  ], "Technical se zyada process ki problems. Site ko network se nikaalna ya laana — table prefix, blog_id, uploads path sab badalte hain, isliye WP-CLI aur Multisite-aware migration tools. Developers ke liye: network options aur site options alag hain, switch_to_blog ke baad restore_current_blog bhoolna mat. Aur Super Admin ka role sirf 2-3 logon ko — sabko Thakur Sa mat banao.");

  // =====================================================================
  // 14. WP-CLI SURVIVAL KIT (dark)
  // =====================================================================
  s = pres.addSlide({ masterName: "WCR_DARK", sectionTitle: "Dard aur Dawa" });
  s.addText("Jugaad nahi, WP-CLI: survival kit", { placeholder: "title" });
  s.addText("1,000 sites pe click-click karoge to Diwali se Holi aa jaayegi", { x: 0.6, y: 1.25, w: 11, h: 0.45, fontSize: 17, color: GOLD, italic: true, margin: 0, isTextBox: true });
  const cli = [
    ["wp site list --fields=blog_id,url", "Network ki saari sites ki list"],
    ["wp site create --slug=jodhpur --title=\"Jodhpur\"", "Nayi site, 2 second mein"],
    ["wp plugin activate my-plugin --network", "Plugin poore network pe on"],
    ["wp search-replace old.test new.test --network", "Saari site tables mein URL badlo"],
    ["wp --url=jodhpur.example.com cache flush", "Kisi ek site pe command chalao"],
    ["wp super-admin list", "Dekho Thakur Sa kaun-kaun hain"],
  ];
  cli.forEach(([cmd, d], i) => {
    const y = 1.95 + i * 0.78;
    s.addShape(pres.ShapeType.roundRect, { x: 0.6, y, w: 12.15, h: 0.65, rectRadius: 0.12, fill: { color: "1A3460" }, line: { color: "1A3460" }, objectName: "CLI row " + (i + 1) });
    s.addText([{ text: "$ ", options: { color: GOLD, bold: true } }, { text: cmd, options: { color: WHITE } }], { x: 0.85, y, w: 7.4, h: 0.65, fontFace: "Courier New", fontSize: 14, margin: 0, valign: "middle", isTextBox: true });
    s.addText(d, { x: 8.4, y, w: 4.2, h: 0.65, fontSize: 15, color: PAPER, margin: 0, valign: "middle", isTextBox: true });
  });
  menuButton(s, true);
  s.addNotes("Live demo ka time ho to yahan 2-3 commands chala ke dikhao. --url flag Multisite ka jaadu hai: kisi bhi WP-CLI command ko kisi ek site pe target karo. --network flag poore network pe.");

  // =====================================================================
  // 15. TAKEAWAYS + THANK YOU
  // =====================================================================
  pres.addSection({ title: "Dhanyavaad" });
  s = pres.addSlide({ masterName: "WCR_COVER", sectionTitle: "Dhanyavaad" });
  s.addShape(pres.ShapeType.ellipse, { x: 8.1, y: 1.7, w: 4.6, h: 4.6, fill: { color: PAPER }, line: { color: GOLD, width: 3, dashType: "dash" }, objectName: "Cream circle" });
  s.addImage({ path: asset("turban.png"), x: 8.4, y: 2.0, w: 4.0, h: 4.0, altText: "Rajasthani mascot with folded hands", objectName: "Mascot" });
  bubble(s, "Padharo mhare network!", 8.6, 0.55, 3.6, 1.0, GOLD, NAVY, 17);
  s.addText("Teen baatein ghar le jao", { x: 0.6, y: 0.6, w: 7, h: 0.6, fontFace: THEME.headFontFace, fontSize: 32, bold: true, color: WHITE, margin: 0, isTextBox: true });
  const take = [
    ["1", "Multisite parivaar ke liye hai, padosiyon ke liye nahi", "Same stack + same team = haan. Warna alag ghar."],
    ["2", "Setup 10 minute, governance zindagi bhar", "Staging, plugin policy, roles, backups: pehle din se."],
    ["3", "Scale pe cache, cron aur WP-CLI hi asli dost", "Object cache, real cron, --network & --url."],
  ];
  take.forEach(([n, h, d], i) => {
    const y = 1.45 + i * 1.3;
    s.addText(n, { x: 0.6, y: y + 0.1, w: 0.8, h: 0.8, shape: pres.ShapeType.diamond, fill: { color: GOLD }, color: NAVY, fontSize: 22, bold: true, align: "center", valign: "middle", margin: 0, isTextBox: true });
    s.addText([{ text: h, options: { bold: true, color: WHITE, fontSize: 18, breakLine: true } }, { text: d, options: { color: PAPER, fontSize: 14 } }], { x: 1.65, y, w: 6.2, h: 1.0, margin: 0, valign: "middle", isTextBox: true });
  });
  s.addText("Dhanyavaad! Sawaal poochho, sharmao mat.", { x: 0.6, y: 5.45, w: 7.3, h: 0.55, fontSize: 24, bold: true, color: GOLD, margin: 0, isTextBox: true });
  s.addText([
    { text: "profiles.wordpress.org/prathameshp", options: { hyperlink: { url: "https://profiles.wordpress.org/prathameshp/" }, color: WHITE, breakLine: true } },
    { text: "rajasthan.wordcamp.org/2026", options: { hyperlink: { url: "https://rajasthan.wordcamp.org/2026/" }, color: WHITE } },
  ], { x: 0.6, y: 6.05, w: 7.3, h: 0.75, fontSize: 15, margin: 0, isTextBox: true });
  s.addText("#wcrajasthan", { x: 9.3, y: 6.55, w: 2.2, h: 0.4, fontSize: 16, bold: true, color: GOLD, align: "center", margin: 0, isTextBox: true });
  menuButton(s, true, 11.35);
  s.addNotes("Summary: Multisite parivaar hai — pyaar bhi, jhagde bhi; management sahi ho to maza hi maza. Dhanyavaad WordCamp Rajasthan! Q&A ke dauraan koi bhi topic chahiye to Menu se wahin jump kar sakte hain.");

  await pres.writeFile({ fileName: OUT });
  if (process.env.SKILL_DIR) {
    const { applyTheme } = require(path.join(process.env.SKILL_DIR, "scripts/apply_theme.js"));
    await applyTheme(OUT, THEME);
  }
  console.log("Wrote", OUT);
})();
