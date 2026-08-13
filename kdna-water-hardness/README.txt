=== Water Hardness Lookup ===
Contributors: krulldna
Tags: water hardness, postcode, lookup, elementor
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.2.0
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

Stages still to come: front-end lookup, results and band copy, geolocation and
logging, pluggable data sources, the Elementor widget, then caching,
accessibility and delivery.

== Importing data ==

Import the zones file first, then the postcode mappings, because a mapping has
to attach to a zone that already exists.

Zones need a zone name, a hardness value, a source URL and a publication date.
Utility name, confidence and country code are optional. A row without a
traceable source is rejected, because every figure shown to a customer should
be attributable to a published document. While you are still compiling, there
is an option to accept those rows anyway; they are stored as estimated, and no
estimated figure is ever presented as a straight answer on the front end.

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

== Changelog ==

= 0.2.0 =
* Stage 2. CSV import, source link registry and data management.

= 0.1.0 =
* Stage 1. Foundation and data layer.
