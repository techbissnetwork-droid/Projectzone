/* ==========================================================================
   TECHBISS — Soft UI Control Panel
   Demo data layer. Everything here is local-only: it reads and writes the
   browser's own localStorage/sessionStorage and never talks to a server.
   This file is a plain dependency-free script — load it before main.js on
   any page that needs project data, the client OTP flow, or the client
   session.
   ========================================================================== */
(function () {
  "use strict";

  var PROJECTS_KEY = "techbiss_demo_projects";
  var OTP_KEY = "techbiss_demo_otp";
  var SESSION_KEY = "techbiss_demo_client_email";
  var OTP_TTL_MS = 5 * 60 * 1000; // 5 minutes

  /* ---------------------------------------------------------------------
     Storage helpers — every call is wrapped so a private window, blocked
     storage, or a full quota degrades to "no data" instead of throwing.
     --------------------------------------------------------------------- */
  function readJSON(store, key, fallback) {
    try {
      var raw = store.getItem(key);
      if (!raw) return fallback;
      var parsed = JSON.parse(raw);
      return parsed === null || parsed === undefined ? fallback : parsed;
    } catch (e) {
      return fallback;
    }
  }

  function writeJSON(store, key, value) {
    try {
      store.setItem(key, JSON.stringify(value));
      return true;
    } catch (e) {
      return false;
    }
  }

  function removeKey(store, key) {
    try {
      store.removeItem(key);
    } catch (e) {
      /* ignore — nothing to clean up if storage isn't available */
    }
  }

  function localStore() {
    return window.localStorage;
  }
  function sessionStore() {
    return window.sessionStorage;
  }

  /* ---------------------------------------------------------------------
     Projects
     --------------------------------------------------------------------- */
  function genId() {
    return "proj-" + Date.now().toString(36) + "-" + Math.random().toString(36).slice(2, 8);
  }

  function getProjects() {
    var list = readJSON(localStore(), PROJECTS_KEY, []);
    return Array.isArray(list) ? list : [];
  }

  function saveProjects(list) {
    writeJSON(localStore(), PROJECTS_KEY, list);
  }

  function saveProject(project) {
    var list = getProjects();
    var record = {};
    for (var k in project) {
      if (Object.prototype.hasOwnProperty.call(project, k)) record[k] = project[k];
    }
    if (!record.id) record.id = genId();
    var idx = -1;
    for (var i = 0; i < list.length; i++) {
      if (list[i].id === record.id) {
        idx = i;
        break;
      }
    }
    if (idx > -1) {
      list[idx] = record;
    } else {
      list.push(record);
    }
    saveProjects(list);
    return record;
  }

  function deleteProject(id) {
    var list = getProjects().filter(function (p) {
      return p.id !== id;
    });
    saveProjects(list);
  }

  function findProjectsByEmail(email) {
    if (!email) return [];
    var needle = String(email).trim().toLowerCase();
    return getProjects().filter(function (p) {
      return String(p.clientEmail || "").trim().toLowerCase() === needle;
    });
  }

  function seedIfEmpty() {
    var existing = getProjects();
    if (existing.length) return existing;

    var seed = [
      {
        id: "proj-cascade-outfitters",
        name: "Cascade Outfitters — Website Redesign",
        clientName: "Dana Whitfield",
        clientBusiness: "Cascade Outfitters",
        clientEmail: "dana@cascadeoutfitters.com",
        industry: "Retail",
        status: "In Progress",
        progress: 55,
        startDate: "2026-06-01",
        targetDate: "2026-10-15",
        domain: "cascadeoutfitters.com",
        hostingProvider: "TECHBISS Managed Hosting",
        hostingExpiry: "2027-06-01",
        sslProvider: "TECHBISS SSL (Auto-Renew)",
        sslExpiry: "2027-06-01",
        ownerStaffName: "Priya Nataraj",
        resultMetric: "+38% projected online revenue",
        summary: "A full redesign of the Cascade Outfitters storefront, unifying online and in-store inventory ahead of the fall catalog launch.",
        showOnPortfolio: true
      },
      {
        id: "proj-ferro-vine",
        name: "Ferro & Vine Bistro Group — Business Digitization",
        clientName: "Marcus Ferro",
        clientBusiness: "Ferro & Vine Bistro Group",
        clientEmail: "marcus@ferroandvine.com",
        industry: "Hospitality",
        status: "In Progress",
        progress: 70,
        startDate: "2026-05-10",
        targetDate: "2026-09-20",
        domain: "ferroandvine.com",
        hostingProvider: "TECHBISS Managed Hosting",
        hostingExpiry: "2027-05-10",
        sslProvider: "TECHBISS SSL (Auto-Renew)",
        sslExpiry: "2027-05-10",
        ownerStaffName: "Owen Castillo",
        resultMetric: "Target: +60% direct bookings",
        summary: "Moving Ferro & Vine off third-party ordering apps and onto a direct booking and ordering system with in-house hosting and business email.",
        showOnPortfolio: false
      },
      {
        id: "proj-meadowcrest-dental",
        name: "Meadowcrest Dental Group — Domain & Hosting Migration",
        clientName: "Dr. Elaine Osei",
        clientBusiness: "Meadowcrest Dental Group",
        clientEmail: "elaine@meadowcrestdental.com",
        industry: "Healthcare",
        status: "Completed",
        progress: 100,
        startDate: "2026-02-01",
        targetDate: "2026-03-15",
        domain: "meadowcrestdental.com",
        hostingProvider: "TECHBISS Managed Hosting",
        hostingExpiry: "2027-03-15",
        sslProvider: "TECHBISS SSL (Auto-Renew)",
        sslExpiry: "2027-03-15",
        ownerStaffName: "Priya Nataraj",
        resultMetric: "Zero downtime during migration",
        summary: "A clean migration off legacy hosting with zero downtime, plus a renewed SSL certificate and a professional business email setup.",
        showOnPortfolio: true
      }
    ];
    saveProjects(seed);
    return seed;
  }

  /* ---------------------------------------------------------------------
     OTP / passwordless sign-in (demo only — codes are returned to the
     caller so the page can display them on-screen; nothing is ever sent
     anywhere).
     --------------------------------------------------------------------- */
  function randomCode() {
    return String(Math.floor(100000 + Math.random() * 900000));
  }

  function requestOtp(email) {
    var code = randomCode();
    writeJSON(sessionStore(), OTP_KEY, {
      email: String(email).trim().toLowerCase(),
      code: code,
      expiresAt: Date.now() + OTP_TTL_MS
    });
    return code;
  }

  function verifyOtp(email, code) {
    var record = readJSON(sessionStore(), OTP_KEY, null);
    if (!record) return false;
    if (Date.now() > record.expiresAt) return false;
    if (record.email !== String(email).trim().toLowerCase()) return false;
    if (String(record.code) !== String(code).trim()) return false;
    return true;
  }

  /* ---------------------------------------------------------------------
     Client session (sessionStorage — cleared when the tab closes)
     --------------------------------------------------------------------- */
  function startClientSession(email) {
    try {
      sessionStore().setItem(SESSION_KEY, String(email).trim().toLowerCase());
    } catch (e) {
      /* ignore — the page redirect still proceeds in this demo */
    }
  }

  function getClientSession() {
    try {
      return sessionStore().getItem(SESSION_KEY);
    } catch (e) {
      return null;
    }
  }

  function endClientSession() {
    removeKey(sessionStore(), SESSION_KEY);
    removeKey(sessionStore(), OTP_KEY);
  }

  window.TechbissDemo = {
    getProjects: getProjects,
    saveProject: saveProject,
    deleteProject: deleteProject,
    findProjectsByEmail: findProjectsByEmail,
    seedIfEmpty: seedIfEmpty,
    requestOtp: requestOtp,
    verifyOtp: verifyOtp,
    startClientSession: startClientSession,
    getClientSession: getClientSession,
    endClientSession: endClientSession
  };
})();
