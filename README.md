# Config Migration Tool

Tool for migrating users configurations in SYS buckets to [Storage API components configuration](http://docs.keboola.apiary.io/#reference/component-configurations).

## Migration between docker apps

If you need to create a new app for the same service but differing e.g. only in version of the API, you can migrate configurations using built-in helpers.

Add definition to `definition.json`. Its format is:
 
 ``` json
 {
   "<originApp>": {
     "destinations": ["<destApp1>", "<destApp2>"],
     "migration": "GenericCopy"
   }
 }
 ```
 
 - `<originApp>` - is id of migrated app
 - `destinations` - is list of destination apps to which the origin may be migrated
 - `migration` - is name of the class used for migration (without `Migration` suffix)
 
 E.g.
```json
{
  "ex-adwords-v2": {
    "destinations": ["keboola.ex-adwords-v201705"],
    "migration": "ExAdWords"
  }
}
```

If there is no explicit definition of origin and destination, Generic Copy migration will be used.

### List of supported migrations

Can be obtained using this call:
```bash
curl -X "POST" "https://docker-runner.keboola.com/docker/keboola.config-migration-tool/action/supported-migrations" \
     -H "x-storageapi-token: TOKEN" \
     -H "Content-Type: text/plain; charset=utf-8" \
     -d $'{ "configData": { "parameters": {} } }'
```

Returns list like:
```json
{
  "ex-adwords-v2": [
    "keboola.ex-adwords-v201705"
  ],
  "keboola.ex-adwords-v201702": [
    "keboola.ex-adwords-v201705"
  ]
}
```

### GenericCopy Migration

If you just need to copy & paste configuration from origin to destination without any changes, you can use `GenericCopyMigration` class.


## Database Extractor Migration
Take all tables from sys.c-ex-db bucket and converts them to JSON based configurations in SAPI.
Orchestration tasks are also updated to use the new extractors.

### Example Configuration

``` json
{
  "parameters": {
    "component": "ex-db"
  }
}
```

## Google Analytics Extractor Migration
Migrate Google Analytics Extractor configs form sys.c-ex-google-analytics bucket to JSON configurations in SAPI.

### Example Configuration

``` json
{
  "parameters": {
    "component": "ex-google-analytics"
  }
}  
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

