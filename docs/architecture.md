# Architecture notes

Rationale for the decisions that are not obvious from the code.

## No framework, no dependencies

The platform ships as source and installs by copying files. That constraint
rules out Composer and a build step, and it is what makes the Advanced
Installer viable: a customer with FTP access and a PHP host can deploy a
marketplace product without a terminal.

The cost is that routing, validation, sessions and templating are written here.
The core is about 2,500 lines and boots in a single pass, with services
constructed lazily so a cached marketing page never opens a database
connection.

## Server-rendered HTML

Client-side rendering trades first paint for subsequent navigation. For a
marketing site and marketplace where most sessions are a single page arriving
from search on a mobile connection, that is the wrong trade.

Server rendering plus an inlined critical stylesheet gets meaningful content
painted in one round trip. The 3.7 KB of JavaScript is deferred and purely
additive: with it disabled the navigation, forms, filters and installer all
still work.

## Two stylesheets, not one

`critical.css` covers everything above the fold on every page and is inlined in
the head. `main.css` carries the rest and loads via `rel=preload` with an
`onload` swap, with a `<noscript>` fallback.

One combined stylesheet would be simpler but would block first paint on a
network request. The split costs about 14 KB of inlined markup per page and
removes the render-blocking request entirely.

## Generated SVG artwork

`App\Support\Art` produces marketplace and case-study imagery as inline SVG,
seeded deterministically from each record's slug. A screenshot-based catalogue
would add roughly 200 KB per card; this adds about 1.5 KB, renders identically
on every request, stays sharp at any pixel density and inherits theme colours.

## Marketing content in config, operational data in the database

`config/site.php` and `config/solutions.php` hold structural marketing content.
Marketing pages therefore render with zero database round-trips, which is the
single largest TTFB win on the public site.

Anything an administrator manages — products, orders, licences, leads,
projects, tickets, resources, case studies, settings — lives in the database.

## One users table, three portals

`App\Core\Auth::PORTALS` maps each portal to the roles it accepts. A credential
is checked against that map on sign-in and again on every request through
portal middleware, so a valid client credential cannot open the admin console
even if someone posts it to `/admin/login`. Failed attempts are throttled by
both email and IP address.

## Installer state lives in the session

Nothing is written to disk or the database until the install step runs. Every
earlier step is reversible, the connection test is live rather than deferred to
the end, and credentials are cleared from the session as soon as the install
completes.

`Installer::scanExisting()` looks in the application root, one directory above
it and the public directory, and cross-references the database schema — so an
install into a sub-directory of an existing WordPress site is detected rather
than silently overwriting it.

## Deterministic performance budgets

`tests/performance.php` asserts byte budgets and critical-path structure rather
than timings. Timing measurements flake on shared CI runners; byte budgets do
not, and they catch the regressions that actually move Largest Contentful Paint.
A hero video or an added web font fails the build on the commit that introduced
it.
