# Drop source documents here

This container cannot reach utility websites: the network policy allows search
but blocks fetching. So the documents have to come from you. Put them in this
folder, commit, and I can read them and extend the dataset.

## What works

- **PDF** — fine. I can read the tables directly, including the aesthetic
  tables where hardness lives.
- **CSV or Excel** — better. Faster and less to go wrong.
- **A screenshot of one table** — fine if that is all you have.
- **Pasted text** — fine. Paste the hardness table into the chat and I will
  take it from there.

Don't rename anything. The original filename usually carries the year and the
authority, which is what the source registry needs.

## What I do with them

Read the hardness figures per supply zone, add the rows to `au-zones.csv` with
the real source document and its publication date, and mark them `verified`
rather than `estimated` because at that point they will have been read out of
the document rather than gathered from search.

Then extend `au-postcodes.csv` to cover the towns those zones serve.

## Priority

The two that buy the most coverage per document:

1. **Victoria** — eighteen corporations, one report each, and between them they
   cover the entire state including every regional town.
2. **Regional NSW** — no single document. The state database is not public, so
   this is council by council, roughly ninety of them. The fifteen largest
   regional centres get most of the population for a fraction of the work.

Everything else is a long tail: individual councils in regional Queensland,
Western Australia and South Australia, each covering one town.

## Already checked, and what came of it

### vic-safe-drinking-water-act-annual-report-2024-25.pdf

**No hardness data. Nothing usable.** Checked all 41 pages: the words hardness,
CaCO₃, calcium, magnesium and alkalinity do not appear once.

It is the regulator's compliance report, not a water quality dataset. What it
reports is breaches: E. coli, turbidity, trihalomethanes, manganese and lead.
Hardness is absent because Victoria sets no standard for it — the Safe Drinking
Water Regulations cover health parameters and a short list of aesthetic ones,
and hardness is not among them, so the regulator never collects it.

Appendix 3 looked promising and is not: "regulated water supplies" means the
*non-drinking* supplies piped for gardens and toilet flushing, which is the
opposite of what the tool needs.

The document itself says where the data is: "In-depth information on the
performance of each water agency can be found in their annual reports available
on their websites." That is the route — one report per corporation.
