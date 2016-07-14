# Config Migration Tool
[![Build Status](https://travis-ci.org/keboola/config-migration-tool.svg)](https://travis-ci.org/config-migration-tool)

Tool for migrating users configurations in SYS buckets to [Storage API components configuration](http://docs.keboola.apiary.io/#reference/component-configurations).

## Database Extractor Migration
Take all tables from sys.c-ex-db bucket and converts them to JSON based configurations in SAPI.
Orchestration tasks are also updated to use the new extractors.

### Example Configuration

``` yaml
parameters:
  component: 'ex-db'  
```

## Google Analytics Extractor Migration
Migrate Google Analytics Extractor configs form sys.c-ex-google-analytics bucket to JSON configurations in SAPI.

### Example Configuration

``` yaml
parameters:
  component: 'ex-google-analytics'  
```

Some things are quite different in Google Analytics V4 API. 
Therefor not everything is migrated automatically, because that will cause more confusion then benefit.
Users has to take care of following:

### ViewId (Profile)
In GA V4, every query must have viewId specified. In the old extractor, there was possible to use query across all profiles.
Those queries where "profile" attribute is not set, the migration script will set "viewId" to the first profile in the profiles list.

### Date Ranges
In the old extractor, data ranges were set in the orchestration tasks.
In the GA V4 you can set multiple data ranges within the query. It is no more possible to set date ranges in orchestration tasks.
After migration, all queries will have default date range:

    Since: -4 days
    Until: -1 day
    
### Filters
GA V4 supports V3 filters expression so there is no problem in migrations. 
In the future we will also add support for `dimensionFilterClauses` and `metricFilterClauses`.

### Segments
If **segment** is defined in the query configuration, **segment** dimension will be added to the query during migration. 
This is because the new extractor supports multiple segments per query.

