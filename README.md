# DB setup

### Dump DB:
```
pg_dump -U aptvision -W --host=postgres --dbname=telediagnosis --schema-only > ./sql/initial_main_rss_db.sql
```

### Restore DB:
```
(optional)
psql -h postgres -p 5432 -U aptvision -d telediagnosis < ./sql/initial_main_rss_unaccent_fix.sql

psql -h postgres -p 5432 -U aptvision -d telediagnosis < ./sql/initial_main_rss_roles.sql
psql -h postgres -p 5432 -U aptvision -d telediagnosis < ./sql/initial_main_rss_db.sql
psql -h postgres -p 5432 -U aptvision -d telediagnosis < ./sql/initial_main_rss_data.sql
```