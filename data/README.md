# Australian starter dataset

Two CSVs, ready to import, covering the metropolitan areas and a handful of
regional centres. Import `au-zones.csv` first, then `au-postcodes.csv`, because
a mapping has to attach to a zone that already exists.

- **30 supply zones** across all eight states and territories
- **1,476 postcodes** mapped to them
- Roughly **80% of the Australian population**

## Read this before going live

**Every row is marked `estimated`, and that is not a formality.**

The figures were gathered from published sources through search, not read out of
the source documents directly. The build container cannot reach the utility
websites, so no figure here has been checked against the report it is attributed
to. The postcode mapping is a second approximation on top of the first: supply
zone boundaries do not follow postcode boundaries, and most utilities do not
publish the correspondence at all.

While a row is `estimated`, the plugin shows an **inconclusive** result for it.
It explains what is known and why one figure would be misleading, and it names
no band. That is the correct behaviour for a figure nobody has verified, and it
is why this dataset is safe to import today.

Verifying a row is a one-word edit in the CSV: `estimated` becomes `verified`,
re-import, and that zone starts showing a band. Do it zone by zone as you check
each figure against its source document. There is no need to verify everything
before any of it is useful.

## Check these first

Five rows carry a `CHECK FIRST` note, for three different reasons:

| Zone | Why |
|---|---|
| Hunter Grahamstown | Published as a 35–103 range. 69 is the midpoint, and the range crosses the soft/moderate boundary, so the band is genuinely undetermined. |
| Ballarat | The utility publishes a hardness page; the figure was not captured. |
| Albany | Figure dates from 2010–11 reporting. |
| Esperance | Very hard at 340, and the figure is not recent. |
| Perth northern groundwater | Published as 124–145; 135 is the midpoint of a group of three localities. |

Two more are worth an early look because they carry a lot of traffic and sit
near a band boundary: **Brisbane Mount Crosby** at 114.8 is 5 mg/L below the
hard threshold, and **Adelaide metropolitan** at 97 is a metro average over a
published 47–133 range, which is nearly two bands wide.

## Where each figure came from

Each row's `source_url` is the utility's own publication. Nine of them are
annual report PDFs, where hardness sits in the **aesthetic** tables rather than
the health ones. Two are CSVs. Four utilities publish no file and the figure has
to be read off a suburb search screen.

The full register, with a button to each source, is in the plugin under
**Data Import → Countries and sources**.

## What is not covered

Regional Australia, mostly. Regional NSW, regional Queensland, most of regional
Victoria, and the smaller Western Australian and South Australian towns are
absent. Two datasets would fill most of that gap if someone wants to work
through them: the NSW Health drinking water database, and the Health Victoria
annual report, which covers every Victorian corporation in one document.

An unmapped postcode is not a broken result. The plugin returns its no-match
copy, which is editable per country, so partial coverage reads as deliberate.

## The disclaimer

Independent of all of the above, every result carries a disclaimer saying the
figure is a published zone figure rather than a measurement of the visitor's own
tap. It is on by default and editable per country under **Settings → Bands and
copy**. Leave it on.
