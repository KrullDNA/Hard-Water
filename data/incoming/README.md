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
