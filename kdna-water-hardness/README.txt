=== Water Hardness Lookup ===
Contributors: krulldna
Tags: water hardness, postcode, lookup, elementor
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.6.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A postcode lookup returning local tap water hardness, a classification band, and brand copy tailored to that band.

== Description ==

A visitor enters their postcode and is shown the hardness of their tap water as
a number, a classification band, and a block of editable copy for that band.

The plugin is brand-agnostic. All customer-facing copy is set in the admin
rather than hardcoded, so the same plugin can run on more than one site.

Two decisions shape the whole build:

1. Postcodes map to supply zones, not to hardness values. Utilities publish
   hardness by supply zone, and postcodes frequently span two or more zones,
   so the relationship is many to many. Where a postcode spans zones the front
   end says so rather than silently picking one.
2. One canonical unit is stored, mg/L as CaCO3, and converted on display.
   Source data in mg/L Ca, Clark degrees, German degrees or French degrees is
   converted at import. Mixed units are never stored.

Adding a country is a CSV upload and a settings change, not a development job.

== Build status ==

Stage 1, complete. Foundation and data layer.

* Main plugin file, activation hook and schema versioning
* Three custom tables created with dbDelta()
* Database access class, every query using $wpdb->prepare()
* Unit conversion covering mg/L CaCO3, mg/L Ca, Clark, dH and fH
* Top-level admin menu with a Settings page showing installation status

Stage 2, complete. CSV import, source registry and data management.

* Data Import screen taking two kinds of CSV, supply zones and postcode
  mappings
* Column mapping, guessed from your headings and correctable, with the first
  rows of the file shown alongside
* Source unit chosen per file and converted to mg/L as CaCO3 on the way in
* Row-level validation with a report naming the row, the value and the problem.
  One bad row never stops the rest of the file
* Large files imported in blocks across several requests, so a big postcode
  file does not have to finish inside one PHP timeout
* Re-importing the same file never doubles the data
* Source link registry per country: label, URL, region, last checked and
  publication date, with counts, last import date and a review flag where the
  newest source is more than 18 months old
* Data browser filterable by country, with deletion by selection or by country

Stage 3, complete. Front-end lookup.

* REST endpoint at /wp-json/kdna-wh/v1/lookup, public and read-only
* Shortcode [kdna_water_hardness] rendering the form
* Country selector built from the countries that have data, hidden
  automatically when only one does
* Per-country field rules: label, example, validation pattern, maximum length
  and the keyboard a phone raises, all re-rendered when the country changes
* Four outcomes handled: a single zone, a range across several zones, a valid
  postcode we hold nothing for, and a postcode that is not valid for the
  country
* Validated in the browser for immediate feedback and again on the server,
  where the answer counts
* Vanilla JavaScript, no jQuery, and assets loaded only where the tool appears

Stage 4, complete. Results panel and band copy.

* Results panel showing the figure, its band, and a visual scale marking where
  the reading sits. A reading that spans zones is drawn as a span rather than
  a point
* Classification bands editable per country: label, starting figure, colour,
  and whether the band is used at all, so a country running three bands
  instead of four is a setting rather than a change to the code
* Heading, body copy and call to action editable per band, per country, with
  a reminder of the claims rules sitting directly above the fields
* The fifth result state, inconclusive, now that there are bands to cross and
  copy to explain it. Any one of three things triggers it: the postcode spans
  zones in different bands, the figure is estimated rather than published, or
  the report it came from is older than a configurable threshold
* An inconclusive result names no band, but still gives the range, the zones
  involved, and the argument that does not depend on the reading

Stage 5, complete. Geolocation and logging.

* Country pre-selected from the visitor's location, resolved in order:
  Cloudflare's CF-IPCountry header, then a MaxMind GeoLite2 lookup against a
  local database, then Australia. A detected country holding no data also
  falls back to Australia, rather than showing an empty tool
* The MaxMind database is downloaded and refreshed monthly by WP-Cron, and can
  be updated on demand from the Settings screen
* Every lookup is logged: country, postcode, the figure served, the band, and
  the time. No IP address, no email address, nothing identifying a person
* Lookup Log screen grouped by postcode and band, filterable by country, band,
  date range and postcode, with a CSV export of exactly what is on screen

Stage 5b, complete. Pluggable data sources.

* A source adapter interface with two implementations: the imported data, and
  a base class for a remote provider
* Per-country source type, endpoint, key, adapter, cache lifetime and
  confidence, all set in the admin and none of it in the code
* Answers cached per country and postcode, for 30 days by default
* Successful answers written into this site's own tables, so the data set
  grows into its own fallback and the tool degrades rather than breaks if a
  provider disappears
* Any failure, timeout or exhausted quota falls back to the imported data
  silently. The visitor never sees a third party's error, and the reason is
  recorded in the admin instead

Stage 6a, complete. The Elementor widget and its content controls.

* Registered in its own KDNA Tools category, built for Elementor's Atomic
  markup: no inner wrapper under the optimized markup experiment, one wrapper
  div in the output, and no styling anywhere that depends on
  .elementor-widget-container
* Six content sections: Layout, Country selector, Postcode field, Submit
  button, Results display, and Copy overrides
* A preview state control that draws a sample result in the editor, so every
  state can be styled without hunting for a postcode that produces one. It
  has no effect on the live page
* The widget and the shortcode render through one function, so the markup,
  the stylesheet and the script cannot drift apart

Stage 6b, complete. The widget's style controls.

* All fourteen style sections: form container, country selector, input field,
  labels and help text, submit button, loading state, results container,
  result figure, band label, band scale, result copy, metadata line, result
  button, and the message states
* Every dimensional value is responsive, and every interactive element has
  its normal, hover, focus, error and disabled states as applicable
* A colour per band on both the band label and the scale, overriding the
  colours set in Settings for that one placement
* Every selector is scoped to its own placement and targets the plugin's own
  classes, so nothing breaks under Elementor's optimized markup

Stages still to come: caching, accessibility and delivery.

== The Elementor widget ==

Find it under KDNA Tools in the widget panel.

Elementor is not required. The shortcode works without it, and nothing in the
plugin loads Elementor code unless Elementor is active. The widget needs
Elementor 3.16 or later, which is where the method that switches off the inner
wrapper arrived.

Styling is per placement, as Elementor styling always is. The band colours set
under Water Hardness, Settings are the defaults every placement starts from,
and the widget's own colour controls override them where a particular page
needs something different.

The copy overrides in the widget are for one placement only. Anything left
empty falls back to the copy under Water Hardness, Settings, which is where
the wording for the whole site belongs. Use the overrides to give a landing
page its own angle without duplicating everything.

== Using an API for a country ==

Water Hardness, Data Import, Countries and sources.

Set the country's source to a remote API, give it an endpoint and a key, and
lookups will ask the provider first. Put {postcode} in the endpoint where the
postcode belongs; {country} and {key} work too, and without any placeholder
the postcode is added as a query argument. The key is sent as a bearer token
unless the endpoint asks for it by name, so it stays out of server logs.

The bundled JSON adapter reads the shapes a hardness API is likely to return:
a list of zones, a single zone, either wrapped in a container key, and the
field names providers commonly use. A provider needing more than that gets its
own adapter class extending KDNA_WH_Source_API and one line on the
kdna_wh_api_adapters filter. Nothing else changes.

Two things to confirm with any provider before depending on them. That caching
results locally and using the data commercially are both permitted, since some
prohibit storage: if yours does, return false from the
kdna_wh_api_write_through filter. And that you can live without them, which
you can, because every answer they give is kept.

== Country pre-selection ==

Water Hardness, Settings, Advanced.

If the site is behind Cloudflare nothing needs setting up: the CF-IPCountry
header is read and no external call is made. Otherwise a free MaxMind account
gives a licence key, and the plugin downloads and maintains the GeoLite2
Country database itself. The file lives in the uploads folder, so it survives
plugin updates.

Detection is only ever a convenience. Mobile traffic resolves to wherever the
carrier's gateway sits and a VPN resolves wherever it exits, so the selector
always stays changeable by hand.

Turn pre-selection off if the site uses full page caching. The first visitor's
country would otherwise be baked into the cached page for everyone after them,
which is worse than always starting on the default.

== Bands and copy ==

Water Hardness, Settings. Everything a visitor reads is here, per country.

A band runs from the figure it starts at until the next band begins, so you
only set the lower end and the bands cannot overlap or leave a gap. The
Australian defaults are soft to 60, moderately hard to 120, hard to 180, and
very hard above that, in mg/L as CaCO3. A band is inclusive at the bottom, so
a reading of exactly 60 is moderately hard rather than soft.

Two things about the default copy are deliberate. The soft-water block does not
read as a rejection: most of Australia is on soft water, and if that result
lands as "this is not for you" the tool works against itself for the majority
of the people who use it. And every line is an appearance claim, because the
same rules apply here as on pack.

== Using the shortcode ==

Put [kdna_water_hardness] on any page.

Attributes, all optional:

* country       Force a starting country, e.g. country="GB". Ignored if that
                country has no data.
* show_selector auto by default, which hides the selector when only one
                country has data. Set yes or no to override.
* button_text   Defaults to "Check my water".
* label         Overrides the country's own field label.
* placeholder   Overrides the country's own example.
* class         Extra CSS class on the wrapper.

Until data is imported the shortcode renders nothing at all for visitors, and
a short explanatory note for administrators.

== Importing data ==

Import the zones file first, then the postcode mappings, because a mapping has
to attach to a zone that already exists.

Zones need a zone name, a hardness value, a source URL and a publication date.
Utility name, confidence and country code are optional. A row without a
traceable source is rejected, because every figure shown to a customer should
be attributable to a published document. While you are still compiling, there
is an option to accept those rows anyway, and they are stored as estimated
whatever else the file says.

An estimated figure is never given as a straight answer. It produces an
inconclusive result, which explains what is known and why one figure would be
misleading.

Postcode mappings need a postcode and either a zone name or a zone ID. A
postcode that spans two supply zones gets one row per zone. That is not a
mistake in the data, it is the point: it is what lets the tool report a range,
or say the answer is inconclusive, rather than quietly picking one figure.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/, or install the zip through
   Plugins, Add New, Upload Plugin.
2. Activate it through the Plugins screen.
3. Open Water Hardness in the admin menu and confirm all three tables report as
   created.

== Data and privacy ==

The lookup log stores a country code, a postcode, the hardness figure served,
the band matched and a timestamp. It stores no IP address, no email address and
no other personal identifier.

An IP address is used, in memory, for the geolocation lookup when Cloudflare is
not supplying the country. It is never written to the database, never logged,
and never sent anywhere.

Invalid postcodes are not logged at all. A typo is not a place, and there is
nothing geographic to learn from it.

== Changelog ==

= 0.6.1 =
* Stage 6b. All fourteen style sections, responsive dimensional controls,
  interactive states throughout, and a colour per band.

= 0.6.0 =
* Stage 6a. Elementor widget, KDNA Tools category, six content sections and
  the editor preview state.

= 0.5.1 =
* Stage 5b. Source adapter layer, per-country API configuration, response
  caching, write-through to local tables and silent fallback.

= 0.5.0 =
* Stage 5. Country pre-selection, the MaxMind updater, lookup logging and the
  Lookup Log screen with CSV export.

= 0.4.0 =
* Stage 4. Results panel, band scale, per-country band copy, and the
  inconclusive result state.

= 0.3.0 =
* Stage 3. REST endpoint, shortcode, country selector and front-end lookup.

= 0.2.0 =
* Stage 2. CSV import, source link registry and data management.

= 0.1.0 =
* Stage 1. Foundation and data layer.
