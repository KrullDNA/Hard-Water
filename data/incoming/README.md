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

### barwon-water-drinking-water-quality-2023-24.pdf

**Usable. Thirty localities extracted and merged, marked `verified`.**

Table 20, "Hardness (total)", page 49. Monthly sampling per water quality
locality with minimum, maximum and average; the average is what went into the
dataset. Table 1 on pages 11 to 12 gives the population each locality serves,
which is how the Geelong postcodes were allocated: Lovely Banks is the largest
zone at 64,770 people and covers central and northern Geelong.

This is the shape every other Victorian corporation's report should take, so
the same extraction will work on them.

One thing worth seeing: postcode 3213 covers five Barwon zones, and they do not
agree. Anakie and Lovely Banks are 47, Batesford and Moorabool are 89. That
crosses the soft/moderate boundary, so the tool returns inconclusive for 3213
rather than picking one. It is the many-to-many case the brief was built around,
turning up unprompted in the first real dataset.
