=== Water Hardness Lookup ===
Contributors: krulldna
Tags: water hardness, postcode, lookup, elementor
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
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

Stages still to come: CSV import and source registry, front-end lookup, results
and band copy, geolocation and logging, pluggable data sources, the Elementor
widget, then caching, accessibility and delivery.

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

= 0.1.0 =
* Stage 1. Foundation and data layer.
