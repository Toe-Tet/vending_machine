# App on Render (Docker), MySQL on Aiven

The brief requires MySQL and a single public preview link; we chose Render for the web app, but Render offers only managed Postgres. Rather than run MySQL as a paid Render private service with a disk, or switch hosts, the Dockerised app runs on Render's free tier and connects over TLS to a free-tier Aiven MySQL. Accepted cost: free Render services sleep after ~15 min idle, so the first request takes ~30 s.
