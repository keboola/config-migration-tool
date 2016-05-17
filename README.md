# Config Migration Tool

Tool for migrating users configurations in SYS buckets to [Storage API components configuration](http://docs.keboola.apiary.io/#reference/component-configurations).

## Database Extractor Migration
Take all tables from sys.c-ex-db bucket and converts them to JSON based configurations in SAPI. 

### Example Configuration

``` yaml

    parameters:
      component: 'ex-db'
      projectId: 395
      token: *INSERT_SAPI_TOKEN_HERE*
```

