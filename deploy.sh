#!/bin/bash

docker login -u="$QUAY_USERNAME" -p="$QUAY_PASSWORD" quay.io
docker tag configmigrationtool_app quay.io/keboola/config-migration-tool:$TRAVIS_TAG
docker push quay.io/keboola/config-migration-tool:$TRAVIS_TAG
